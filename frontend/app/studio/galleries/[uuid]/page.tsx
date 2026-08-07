'use client'

import { useState, useCallback, useEffect } from 'react'
import { useParams, useRouter } from 'next/navigation'
import Link from 'next/link'
import {
  ArrowLeft, Upload, Trash2, Edit, Share2, Lock, Globe, Image, Download,
  Heart, Eye, MoreHorizontal, X, Check, AlertTriangle, CloudUpload,
  ChevronLeft, ChevronRight
} from 'lucide-react'
import { useGallery, useDeleteGalleryMutation, PhotoItem, useDeletePhotoMutation } from '@/lib/queries/galleries'
import { uploadPhotoDirectly } from '@/lib/storage'
import { formatBytes } from '@/lib/utils'
import { useQueryClient } from '@tanstack/react-query'

interface UploadFile {
  id: string
  file: File
  status: 'queued' | 'uploading' | 'done' | 'error'
  progress: number
  error?: string
}

export default function GalleryDetail() {
  const { uuid } = useParams<{ uuid: string }>()
  const router = useRouter()
  const queryClient = useQueryClient()

  const { data, isLoading, error } = useGallery(uuid)
  const deleteMutation = useDeleteGalleryMutation()

  const [uploads, setUploads] = useState<UploadFile[]>([])
  const [isDragging, setIsDragging] = useState(false)
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false)
  const [selectedPhoto, setSelectedPhoto] = useState<PhotoItem | null>(null)
  const [shareTooltip, setShareTooltip] = useState(false)

  const gallery = data?.data
  const photos = gallery?.photos ?? []
  const activeUploads = uploads.filter((u) => u.status !== 'done')

  const deletePhotoMutation = useDeletePhotoMutation(uuid)

  // Precompute slideshow indices
  const currentIndex = selectedPhoto ? photos.findIndex((p) => p.uuid === selectedPhoto.uuid) : -1
  const nextIndex = selectedPhoto && photos.length > 0 ? (currentIndex + 1) % photos.length : -1
  const prevIndex = selectedPhoto && photos.length > 0 ? (currentIndex - 1 + photos.length) % photos.length : -1

  const handlePrevPhoto = useCallback((e?: React.MouseEvent) => {
    e?.stopPropagation()
    if (prevIndex !== -1 && photos[prevIndex]) {
      setSelectedPhoto(photos[prevIndex])
    }
  }, [prevIndex, photos])

  const handleNextPhoto = useCallback((e?: React.MouseEvent) => {
    e?.stopPropagation()
    if (nextIndex !== -1 && photos[nextIndex]) {
      setSelectedPhoto(photos[nextIndex])
    }
  }, [nextIndex, photos])

  const handleDeletePhoto = useCallback((photoUuid: string, e?: React.MouseEvent) => {
    e?.stopPropagation()
    if (deletePhotoMutation.isPending) return
    if (!confirm('Are you sure you want to delete this photo? It will be moved to the Trash and kept for 7 days.')) return

    // Precalculate post-delete target before mutation runs
    const cIdx = photos.findIndex((p) => p.uuid === photoUuid)
    let nextPhoto: PhotoItem | null = null
    if (photos.length > 1) {
      const nIdx = (cIdx + 1) % photos.length
      const pIdx = (cIdx - 1 + photos.length) % photos.length
      if (photos[nIdx] && photos[nIdx].uuid !== photoUuid) {
        nextPhoto = photos[nIdx]
      } else if (photos[pIdx] && photos[pIdx].uuid !== photoUuid) {
        nextPhoto = photos[pIdx]
      }
    }

    deletePhotoMutation.mutate(photoUuid, {
      onSuccess: () => {
        setSelectedPhoto(nextPhoto)
      },
      onError: () => {
        alert('Failed to delete the photo. Please try again.')
        const restored = photos.find((p) => p.uuid === photoUuid)
        if (restored) {
          setSelectedPhoto(restored)
        }
      }
    })
  }, [deletePhotoMutation, photos])

  useEffect(() => {
    if (!selectedPhoto) return

    const handleKeyDown = (e: KeyboardEvent) => {
      const target = e.target as HTMLElement
      if (
        target.tagName === 'INPUT' ||
        target.tagName === 'TEXTAREA' ||
        target.isContentEditable
      ) {
        return
      }

      if (e.key === 'ArrowLeft') {
        handlePrevPhoto()
      } else if (e.key === 'ArrowRight') {
        handleNextPhoto()
      } else if (e.key === 'Escape') {
        setSelectedPhoto(null)
      }
    }

    window.addEventListener('keydown', handleKeyDown)
    return () => {
      window.removeEventListener('keydown', handleKeyDown)
    }
  }, [selectedPhoto, handlePrevPhoto, handleNextPhoto])

  const updateUpload = (id: string, patch: Partial<UploadFile>) => {
    setUploads((prev) => prev.map((u) => (u.id === id ? { ...u, ...patch } : u)))
  }

  const processFiles = useCallback(
    async (files: File[]) => {
      if (!gallery) return

      const imageFiles = files.filter((f) => f.type.startsWith('image/'))
      if (imageFiles.length === 0) return

      const newUploads: UploadFile[] = imageFiles.map((file) => ({
        id: `${file.name}-${file.size}-${Date.now()}-${Math.random()}`,
        file,
        status: 'queued',
        progress: 0,
      }))

      setUploads((prev) => [...prev, ...newUploads])

      const CONCURRENCY_LIMIT = 4
      let index = 0

      const uploadNext = async () => {
        if (index >= newUploads.length) return
        const uploadItem = newUploads[index++]

        updateUpload(uploadItem.id, { status: 'uploading' })
        try {
          await uploadPhotoDirectly(gallery.uuid, uploadItem.file, (pct) => {
            updateUpload(uploadItem.id, { progress: pct })
          })
          updateUpload(uploadItem.id, { status: 'done', progress: 100 })
          queryClient.invalidateQueries({ queryKey: ['gallery', uuid] })
        } catch (err) {
          updateUpload(uploadItem.id, {
            status: 'error',
            error: err instanceof Error ? err.message : 'Upload failed',
          })
        } finally {
          await uploadNext()
        }
      }

      const promises = []
      const poolSize = Math.min(CONCURRENCY_LIMIT, newUploads.length)
      for (let i = 0; i < poolSize; i++) {
        promises.push(uploadNext())
      }
      await Promise.all(promises)
    },
    [gallery, uuid, queryClient]
  )

  const handleFileInput = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files) processFiles(Array.from(e.target.files))
    e.target.value = ''
  }

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault()
    setIsDragging(false)
    if (e.dataTransfer.files) processFiles(Array.from(e.dataTransfer.files))
  }

  const handleDelete = () => {
    deleteMutation.mutate(uuid, {
      onSuccess: () => router.push('/studio/galleries'),
    })
  }

  const handleShare = () => {
    if (!gallery) return
    const url = `${window.location.origin}/g/${gallery.slug}`
    navigator.clipboard.writeText(url).catch(() => {})
    setShareTooltip(true)
    setTimeout(() => setShareTooltip(false), 2000)
  }

  // ─── Loading ──────────────────────────────────────────────────────────────
  if (isLoading) {
    return (
      <main className="flex-1 min-h-screen bg-background flex items-center justify-center">
        <div className="flex flex-col items-center gap-4">
          <div className="w-12 h-12 rounded-full border-4 border-primary/20 border-t-primary animate-spin" />
          <p className="text-muted-foreground font-medium text-sm">Loading gallery...</p>
        </div>
      </main>
    )
  }

  // ─── Error ────────────────────────────────────────────────────────────────
  if (error || !gallery) {
    return (
      <main className="flex-1 min-h-screen bg-background flex items-center justify-center p-6">
        <div className="text-center">
          <AlertTriangle className="w-12 h-12 text-destructive mx-auto mb-4 opacity-70" />
          <h2 className="text-xl font-bold text-foreground mb-2">Gallery not found</h2>
          <p className="text-muted-foreground mb-6 text-sm">This gallery may have been deleted or does not exist.</p>
          <Link href="/studio/galleries" className="px-6 py-3 bg-primary text-primary-foreground rounded-lg font-semibold hover:bg-accent transition-colors">
            Back to Galleries
          </Link>
        </div>
      </main>
    )
  }



  return (
    <main className="flex-1 min-h-screen bg-background">
      {/* Header */}
      <div className="border-b border-border bg-card p-6">
        <div className="flex items-start justify-between gap-4">
          <div className="flex items-start gap-4">
            <Link
              href="/studio/galleries"
              className="p-2 mt-0.5 rounded-lg hover:bg-secondary transition-colors text-muted-foreground hover:text-foreground shrink-0"
            >
              <ArrowLeft size={20} />
            </Link>
            <div>
              <div className="flex items-center gap-3 flex-wrap">
                <h1 className="text-3xl font-bold text-foreground">{gallery.title}</h1>
                <span
                  className={`text-xs font-semibold px-2 py-0.5 rounded-full ${
                    gallery.visibility === 'public'
                      ? 'bg-green-500/10 text-green-600 border border-green-500/20'
                      : 'bg-yellow-500/10 text-yellow-600 border border-yellow-500/20'
                  }`}
                >
                  {gallery.visibility === 'public' ? (
                    <span className="flex items-center gap-1"><Globe size={10} />Public</span>
                  ) : (
                    <span className="flex items-center gap-1"><Lock size={10} />Private</span>
                  )}
                </span>
              </div>
              <div className="flex flex-wrap gap-4 mt-2 text-sm text-muted-foreground">
                {gallery.client_name && <span>Client: <strong className="text-foreground">{gallery.client_name}</strong></span>}
                {gallery.event_date && (
                  <span>
                    Date: <strong className="text-foreground">{new Date(gallery.event_date).toLocaleDateString()}</strong>
                  </span>
                )}
                <span>Created: <strong className="text-foreground">{new Date(gallery.created_at).toLocaleDateString()}</strong></span>
              </div>
            </div>
          </div>

          <div className="flex items-center gap-2 shrink-0">
            <div className="relative">
              <button
                id="share-gallery-btn"
                onClick={handleShare}
                className="flex items-center gap-2 px-4 py-2 bg-secondary text-foreground rounded-lg hover:bg-muted transition-colors font-semibold text-sm"
              >
                {shareTooltip ? <Check size={16} className="text-green-500" /> : <Share2 size={16} />}
                {shareTooltip ? 'Copied!' : 'Share'}
              </button>
            </div>
            <Link
              href={`/studio/galleries/${uuid}/edit`}
              id="edit-gallery-btn"
              className="flex items-center gap-2 px-4 py-2 bg-secondary text-foreground rounded-lg hover:bg-muted transition-colors font-semibold text-sm"
            >
              <Edit size={16} />
              Edit
            </Link>
            <button
              id="delete-gallery-btn"
              onClick={() => setShowDeleteConfirm(true)}
              className="flex items-center gap-2 px-4 py-2 bg-destructive/10 text-destructive rounded-lg hover:bg-destructive/20 transition-colors font-semibold text-sm"
            >
              <Trash2 size={16} />
              Delete
            </button>
          </div>
        </div>

        {/* Stats Row */}
        <div className="grid grid-cols-4 gap-4 mt-6 pt-6 border-t border-border">
          {[
            { icon: Image, label: 'Photos', value: gallery.stats.photo_count },
            { icon: Download, label: 'Downloads', value: gallery.stats.downloads_count },
            { icon: Heart, label: 'Favorites', value: gallery.stats.favorites_count },
            { icon: Eye, label: 'Storage', value: formatBytes(gallery.stats.total_bytes) },
          ].map((stat) => {
            const Icon = stat.icon
            return (
              <div key={stat.label} className="text-center">
                <Icon className="w-5 h-5 text-primary mx-auto mb-1 opacity-70" />
                <p className="text-xl font-bold text-foreground">{stat.value}</p>
                <p className="text-xs text-muted-foreground">{stat.label}</p>
              </div>
            )
          })}
        </div>
      </div>

      {/* Content */}
      <div className="p-6">
        {/* Upload Zone */}
        <div
          id="upload-drop-zone"
          onDragOver={(e) => { e.preventDefault(); setIsDragging(true) }}
          onDragLeave={() => setIsDragging(false)}
          onDrop={handleDrop}
          className={`relative mb-6 border-2 border-dashed rounded-xl p-8 text-center transition-all ${
            isDragging
              ? 'border-primary bg-primary/5 scale-[1.01]'
              : 'border-border hover:border-primary/50 hover:bg-secondary/20'
          }`}
        >
          <CloudUpload
            size={40}
            className={`mx-auto mb-3 transition-colors ${isDragging ? 'text-primary' : 'text-muted-foreground'}`}
          />
          <p className="text-foreground font-semibold mb-1">
            {isDragging ? 'Drop photos here' : 'Upload Photos'}
          </p>
          <p className="text-muted-foreground text-sm mb-4">Drag & drop images or click to browse</p>
          <label
            id="upload-file-btn"
            className="inline-flex items-center gap-2 px-6 py-2.5 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-colors font-semibold text-sm cursor-pointer"
          >
            <Upload size={16} />
            Choose Files
            <input
              type="file"
              multiple
              accept="image/*"
              onChange={handleFileInput}
              className="hidden"
            />
          </label>
        </div>

        {/* Active Uploads */}
        {activeUploads.length > 0 && (
          <div className="mb-6 bg-card border border-border rounded-lg p-4 space-y-3">
            <h3 className="text-sm font-semibold text-foreground mb-2">Uploading ({activeUploads.length})</h3>
            {activeUploads.map((u) => (
              <div key={u.id}>
                <div className="flex items-center justify-between mb-1">
                  <p className="text-xs text-foreground truncate max-w-[70%]">{u.file.name}</p>
                  <span className="text-xs text-muted-foreground">
                    {u.status === 'error' ? (
                      <span className="text-destructive">Error</span>
                    ) : u.status === 'uploading' ? (
                      `${u.progress}%`
                    ) : (
                      'Queued'
                    )}
                  </span>
                </div>
                <div className="h-1.5 bg-muted rounded-full overflow-hidden">
                  <div
                    className={`h-full rounded-full transition-all ${
                      u.status === 'error' ? 'bg-destructive' : 'bg-primary'
                    }`}
                    style={{ width: `${u.status === 'error' ? 100 : u.progress}%` }}
                  />
                </div>
                {u.status === 'error' && u.error && (
                  <p className="text-destructive text-xs mt-0.5">{u.error}</p>
                )}
              </div>
            ))}
          </div>
        )}

        {/* Photo Grid */}
        {photos.length === 0 ? (
          <div className="bg-card border border-border rounded-xl p-16 text-center">
            <Image className="w-16 h-16 text-muted-foreground/40 mx-auto mb-4" />
            <p className="text-foreground font-semibold text-lg mb-1">No photos yet</p>
            <p className="text-muted-foreground text-sm">Upload your first photos to get started.</p>
          </div>
        ) : (
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
            {photos.map((photo) => (
              <button
                key={photo.uuid}
                onClick={() => setSelectedPhoto(photo)}
                className="group relative aspect-square bg-muted rounded-lg overflow-hidden hover:ring-2 hover:ring-primary transition-all"
              >
                <img
                  src={photo.variants?.sm || photo.cdn_url}
                  alt={photo.filename}
                  className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                  loading="lazy"
                />
                <div className="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-colors flex items-center justify-center">
                  <Eye className="w-6 h-6 text-white opacity-0 group-hover:opacity-100 transition-opacity" />
                </div>
              </button>
            ))}
          </div>
        )}
      </div>

      {/* Photo Lightbox */}
      {selectedPhoto && (
        <div
          className="fixed inset-0 bg-black/95 z-50 flex items-center justify-center p-4 select-none"
          onClick={() => setSelectedPhoto(null)}
        >
          <button
            className="absolute top-4 right-4 p-2.5 text-white/70 hover:text-white hover:bg-white/10 rounded-full transition-colors z-50 bg-black/40"
            onClick={() => setSelectedPhoto(null)}
            aria-label="Close lightbox"
          >
            <X size={24} />
          </button>

          <button
            className="absolute top-4 right-16 p-2.5 text-red-400 hover:text-red-500 hover:bg-red-500/10 rounded-full transition-colors z-50 bg-black/40 disabled:opacity-50"
            onClick={(e) => handleDeletePhoto(selectedPhoto.uuid, e)}
            disabled={deletePhotoMutation.isPending}
            aria-label="Delete photo"
            title="Delete photo from gallery"
          >
            {deletePhotoMutation.isPending ? (
              <div className="w-6 h-6 border-2 border-red-400/20 border-t-red-500 rounded-full animate-spin" />
            ) : (
              <Trash2 size={24} />
            )}
          </button>

          {photos.length > 1 && (
            <button
              className="absolute left-4 p-3 text-white/70 hover:text-white hover:bg-white/10 rounded-full transition-colors z-50 bg-black/20"
              onClick={handlePrevPhoto}
              aria-label="Previous photo"
            >
              <ChevronLeft size={36} />
            </button>
          )}

          <div className="relative max-w-full max-h-[85vh] flex flex-col items-center justify-center" onClick={(e) => e.stopPropagation()}>
            <img
              src={selectedPhoto.variants?.lg || selectedPhoto.cdn_url}
              alt={selectedPhoto.filename}
              className="max-w-full max-h-[80vh] object-contain rounded-lg shadow-2xl"
            />
            <div className="mt-4 bg-black/60 backdrop-blur-md text-white text-xs px-4 py-2 rounded-full flex items-center gap-4">
              <span className="font-medium truncate max-w-[200px]">{selectedPhoto.filename}</span>
              <span className="opacity-40">·</span>
              <span>{formatBytes(selectedPhoto.size)}</span>
              <span className="opacity-40">·</span>
              <span>{currentIndex + 1} of {photos.length}</span>
            </div>
          </div>

          {photos.length > 1 && (
            <button
              className="absolute right-4 p-3 text-white/70 hover:text-white hover:bg-white/10 rounded-full transition-colors z-50 bg-black/20"
              onClick={handleNextPhoto}
              aria-label="Next photo"
            >
              <ChevronRight size={36} />
            </button>
          )}
        </div>
      )}

      {/* Delete Confirmation Modal */}
      {showDeleteConfirm && (
        <div className="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
          <div className="bg-card border border-border rounded-xl p-6 max-w-sm w-full shadow-2xl">
            <div className="flex items-center gap-3 mb-4">
              <div className="w-10 h-10 rounded-full bg-destructive/10 flex items-center justify-center shrink-0">
                <AlertTriangle size={20} className="text-destructive" />
              </div>
              <div>
                <h3 className="font-bold text-foreground">Move Gallery to Trash?</h3>
                <p className="text-sm text-muted-foreground">This gallery can be restored within 7 days.</p>
              </div>
            </div>
            <p className="text-sm text-foreground mb-6">
              Are you sure you want to delete <strong>"{gallery.title}"</strong>? The gallery will be moved to Trash and kept for 7 days. After 7 days it will be permanently deleted along with all original files and generated image variants.
            </p>
            <div className="flex gap-3">
              <button
                id="cancel-delete-btn"
                onClick={() => setShowDeleteConfirm(false)}
                className="flex-1 py-2.5 px-4 bg-secondary text-foreground rounded-lg hover:bg-muted transition-colors font-semibold text-sm"
              >
                Cancel
              </button>
              <button
                id="confirm-delete-btn"
                onClick={handleDelete}
                disabled={deleteMutation.isPending}
                className="flex-1 py-2.5 px-4 bg-destructive text-white rounded-lg hover:bg-destructive/90 transition-colors font-semibold text-sm disabled:opacity-60"
              >
                {deleteMutation.isPending ? 'Deleting...' : 'Move to Trash'}
              </button>
            </div>
          </div>
        </div>
      )}
    </main>
  )
}
