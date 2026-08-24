'use client'

import { useState, useEffect } from 'react'
import { useParams, useRouter } from 'next/navigation'
import Link from 'next/link'
import { ArrowLeft, Save, Globe, Lock, User, Calendar, AlertTriangle, Image as ImageIcon, X } from 'lucide-react'
import { useGallery, useUpdateGalleryMutation, PhotoItem } from '@/lib/queries/galleries'
import { ApiError } from '@/lib/apiClient'
import { useInfiniteStudioGallery } from '../hooks/useInfiniteStudioGallery'

export default function EditGallery() {
  const { uuid } = useParams<{ uuid: string }>()
  const router = useRouter()

  const { data, isLoading, error } = useGallery(uuid)
  const updateMutation = useUpdateGalleryMutation()

  const [form, setForm] = useState({
    title: '',
    client_name: '',
    visibility: 'private' as 'public' | 'private',
    allow_photo_downloads: true,
    allow_gallery_downloads: true,
    allow_google_photos: true,
    cover_photo_uuid: null as string | null,
    clear_cover: false,
  })
  const [selectedCoverPhoto, setSelectedCoverPhoto] = useState<PhotoItem | null>(null)
  const [isPickerOpen, setIsPickerOpen] = useState(false)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [successMessage, setSuccessMessage] = useState('')

  const gallery = data?.data

  useEffect(() => {
    if (gallery) {
      setForm({
        title: gallery.title,
        client_name: gallery.client_name ?? '',
        visibility: gallery.visibility,
        allow_photo_downloads: gallery.allow_photo_downloads ?? true,
        allow_gallery_downloads: gallery.allow_gallery_downloads ?? true,
        allow_google_photos: gallery.allow_google_photos ?? true,
        cover_photo_uuid: gallery.cover_photo?.uuid ?? null,
        clear_cover: false,
      })
      setSelectedCoverPhoto(gallery.cover_photo ?? null)
    }
  }, [gallery])

  const validate = (): boolean => {
    const newErrors: Record<string, string> = {}
    if (!form.title.trim()) newErrors.title = 'Title is required.'
    setErrors(newErrors)
    return Object.keys(newErrors).length === 0
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!gallery || !validate()) return

    updateMutation.mutate(
      {
        uuid: gallery.uuid,
        title: form.title,
        client_name: form.client_name || null,
        visibility: form.visibility,
        allow_photo_downloads: form.allow_photo_downloads,
        allow_gallery_downloads: form.allow_gallery_downloads,
        allow_google_photos: form.allow_google_photos,
        cover_photo_uuid: form.cover_photo_uuid,
        clear_cover: form.clear_cover,
        version: gallery.version,
      },
      {
        onSuccess: () => {
          setSuccessMessage('Gallery updated successfully!')
          setTimeout(() => {
            router.push(`/studio/galleries/${uuid}`)
          }, 1200)
        },
        onError: (err) => {
          if (err instanceof ApiError) {
            if (err.status === 409) {
              setErrors({ general: 'This gallery was updated in another session. Please reload.' })
            } else if (err.validationErrors) {
              const mapped: Record<string, string> = {}
              Object.entries(err.validationErrors).forEach(([field, messages]) => {
                mapped[field] = messages[0]
              })
              setErrors(mapped)
            } else {
              setErrors({ general: err.message })
            }
          }
        },
      }
    )
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
          <Link
            href="/studio/galleries"
            className="px-6 py-3 bg-primary text-primary-foreground rounded-lg font-semibold hover:bg-accent transition-colors"
          >
            Back to Galleries
          </Link>
        </div>
      </main>
    )
  }

  return (
    <main className="flex-1 min-h-screen bg-background">
      {/* Header */}
      <div className="border-b border-border bg-card p-4 sm:p-6 flex items-center gap-4">
        <Link
          href={`/studio/galleries/${uuid}`}
          className="p-2 rounded-lg hover:bg-secondary transition-colors text-muted-foreground hover:text-foreground shrink-0"
        >
          <ArrowLeft size={20} />
        </Link>
        <div className="min-w-0 flex-1">
          <h1 className="text-2xl sm:text-3xl font-bold text-foreground truncate">Edit Gallery</h1>
          <p className="text-muted-foreground mt-1 text-sm sm:text-base truncate max-w-full" title={gallery.title}>
            Editing: <span className="font-semibold text-foreground">{gallery.title}</span>
          </p>
        </div>
      </div>

      {/* Content */}
      <div className="p-4 sm:p-6">
        <div className="max-w-2xl mx-auto">
          <form onSubmit={handleSubmit} className="space-y-6">
            {/* Gallery Info */}
            <div className="bg-card border border-border rounded-lg p-6">
              <h2 className="text-xl font-bold text-foreground mb-6">Gallery Information</h2>

              <div className="space-y-4">
                {/* Title */}
                <div>
                  <label className="block text-sm font-semibold text-foreground mb-2">
                    Gallery Title <span className="text-destructive">*</span>
                  </label>
                  <input
                    id="edit-gallery-title"
                    type="text"
                    value={form.title}
                    onChange={(e) => setForm((p) => ({ ...p, title: e.target.value }))}
                    className={`w-full px-4 py-2.5 bg-background border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:border-primary transition-colors ${
                      errors.title ? 'border-destructive' : 'border-border'
                    }`}
                  />
                  {errors.title && <p className="text-destructive text-xs mt-1">{errors.title}</p>}
                </div>

                {/* Slug (Read-only) */}
                <div>
                  <label className="block text-sm font-semibold text-foreground mb-2">URL Slug</label>
                  <div className="flex items-center bg-muted border border-border rounded-lg overflow-hidden">
                    <span className="pl-4 pr-2 text-muted-foreground text-sm select-none whitespace-nowrap">
                      gallery/
                    </span>
                    <span className="py-2.5 pr-4 text-muted-foreground text-sm">{gallery.slug}</span>
                  </div>
                  <p className="text-xs text-muted-foreground mt-1">Slug cannot be changed after creation.</p>
                </div>

                {/* Client Name */}
                <div>
                  <label className="block text-sm font-semibold text-foreground mb-2 flex items-center gap-1.5">
                    <User size={14} />
                    Client Name
                  </label>
                  <input
                    id="edit-client-name"
                    type="text"
                    value={form.client_name}
                    onChange={(e) => setForm((p) => ({ ...p, client_name: e.target.value }))}
                    placeholder="e.g. Alice & Bob Johnson"
                    className="w-full px-4 py-2.5 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:border-primary transition-colors"
                  />
                </div>

                {/* Event Date (read-only info) */}
                {gallery.event_date && (
                  <div className="flex items-center gap-2 p-3 bg-secondary/30 rounded-lg border border-border">
                    <Calendar size={16} className="text-muted-foreground shrink-0" />
                    <span className="text-sm text-muted-foreground">
                      Event Date: <strong className="text-foreground">{new Date(gallery.event_date).toLocaleDateString()}</strong>
                    </span>
                  </div>
                )}
              </div>
            </div>

            {/* Featured Image / Cover Photo */}
            <div className="bg-card border border-border rounded-lg p-6">
              <h2 className="text-xl font-bold text-foreground mb-4 font-semibold">Featured Cover Image</h2>
              
              <div className="flex flex-col md:flex-row gap-4 items-start">
                <div className="w-full md:w-48 aspect-[3/2] bg-muted border border-border rounded-lg overflow-hidden relative shrink-0">
                  {selectedCoverPhoto && !form.clear_cover ? (
                    <img
                      src={selectedCoverPhoto.variants?.sm || selectedCoverPhoto.cdn_url}
                      alt="Cover Preview"
                      className="w-full h-full object-cover"
                    />
                  ) : (
                    <div className="w-full h-full flex flex-col items-center justify-center text-muted-foreground text-xs p-3 text-center">
                      <ImageIcon size={24} className="mb-1 opacity-40" />
                      <span>No explicit cover image set</span>
                    </div>
                  )}
                </div>

                <div className="flex-1 space-y-3">
                  <div className="flex flex-wrap gap-2">
                    <button
                      type="button"
                      onClick={() => setIsPickerOpen(true)}
                      className="px-4 py-2 bg-primary text-primary-foreground hover:bg-accent rounded-lg text-sm font-semibold transition-colors"
                    >
                      Choose Cover Image
                    </button>
                    {(!form.clear_cover && selectedCoverPhoto) && (
                      <button
                        type="button"
                        onClick={() => {
                          setForm((p) => ({ ...p, cover_photo_uuid: null, clear_cover: true }))
                          setSelectedCoverPhoto(null)
                        }}
                        className="px-4 py-2 bg-secondary text-foreground hover:bg-muted rounded-lg text-sm font-semibold transition-colors"
                      >
                        Reset to Auto-Cover
                      </button>
                    )}
                  </div>

                  <div>
                    {form.clear_cover || !selectedCoverPhoto ? (
                      <div className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-500/10 text-blue-600 border border-blue-500/20">
                        Auto-Cover Mode
                      </div>
                    ) : gallery?.cover_photo?.uuid === selectedCoverPhoto.uuid && gallery?.has_explicit_cover ? (
                      <div className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-green-500/10 text-green-600 border border-green-500/20">
                        Custom Featured Image
                      </div>
                    ) : (
                      <div className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-600 border border-amber-500/20">
                        Custom Featured Image (Pending Save)
                      </div>
                    )}
                    <p className="text-xs text-muted-foreground mt-2 leading-relaxed">
                      Auto-Cover mode automatically sets the first uploaded photo in the gallery as the cover image. Choosing a custom image locks it as the featured cover.
                    </p>
                  </div>
                </div>
              </div>
            </div>

            {/* Visibility */}
            <div className="bg-card border border-border rounded-lg p-6">
              <div className="flex items-center gap-3 mb-6">
                <div className="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center">
                  <Globe size={20} className="text-primary" />
                </div>
                <h2 className="text-xl font-bold text-foreground">Visibility</h2>
              </div>

              <div className="grid sm:grid-cols-2 gap-3">
                <button
                  type="button"
                  id="edit-visibility-private"
                  onClick={() => setForm((p) => ({ ...p, visibility: 'private' }))}
                  className={`p-4 rounded-lg border-2 text-left transition-all ${
                    form.visibility === 'private'
                      ? 'border-primary bg-primary/5'
                      : 'border-border hover:border-primary/50'
                  }`}
                >
                  <div className="flex items-center gap-2 mb-1">
                    <Lock size={16} className={form.visibility === 'private' ? 'text-primary' : 'text-muted-foreground'} />
                    <span className={`font-semibold text-sm ${form.visibility === 'private' ? 'text-primary' : 'text-foreground'}`}>
                      Private
                    </span>
                  </div>
                  <p className="text-xs text-muted-foreground">Only accessible via direct link or password.</p>
                </button>

                <button
                  type="button"
                  id="edit-visibility-public"
                  onClick={() => setForm((p) => ({ ...p, visibility: 'public' }))}
                  className={`p-4 rounded-lg border-2 text-left transition-all ${
                    form.visibility === 'public'
                      ? 'border-primary bg-primary/5'
                      : 'border-border hover:border-primary/50'
                  }`}
                >
                  <div className="flex items-center gap-2 mb-1">
                    <Globe size={16} className={form.visibility === 'public' ? 'text-primary' : 'text-muted-foreground'} />
                    <span className={`font-semibold text-sm ${form.visibility === 'public' ? 'text-primary' : 'text-foreground'}`}>
                      Public
                    </span>
                  </div>
                  <p className="text-xs text-muted-foreground">Anyone with the link can view the gallery.</p>
                </button>
              </div>
            </div>

            {/* Permissions & Downloads */}
            <div className="bg-card border border-border rounded-lg p-6">
              <h2 className="text-xl font-bold text-foreground mb-4">Permissions & Downloads</h2>
              <div className="space-y-4">
                <label className="flex items-start gap-3 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={form.allow_photo_downloads}
                    onChange={(e) => setForm((p) => ({ ...p, allow_photo_downloads: e.target.checked }))}
                    className="mt-1 rounded border-border text-primary focus:ring-primary h-4 w-4 bg-background"
                  />
                  <div>
                    <span className="text-sm font-semibold text-foreground block">Allow individual photo downloads</span>
                    <span className="text-xs text-muted-foreground">Allows public visitors to download individual photos in original quality.</span>
                  </div>
                </label>

                <label className="flex items-start gap-3 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={form.allow_gallery_downloads}
                    onChange={(e) => setForm((p) => ({ ...p, allow_gallery_downloads: e.target.checked }))}
                    className="mt-1 rounded border-border text-primary focus:ring-primary h-4 w-4 bg-background"
                  />
                  <div>
                    <span className="text-sm font-semibold text-foreground block">Allow full gallery ZIP download</span>
                    <span className="text-xs text-muted-foreground">Allows public visitors to request and download a ZIP file of the entire gallery.</span>
                  </div>
                </label>

                <label className="flex items-start gap-3 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={form.allow_google_photos}
                    onChange={(e) => setForm((p) => ({ ...p, allow_google_photos: e.target.checked }))}
                    className="mt-1 rounded border-border text-primary focus:ring-primary h-4 w-4 bg-background"
                  />
                  <div>
                    <span className="text-sm font-semibold text-foreground block">Allow saving photos to Google Photos</span>
                    <span className="text-xs text-muted-foreground">Allows public visitors to synchronize the gallery or their selection directly to their Google Photos account.</span>
                  </div>
                </label>
              </div>
            </div>

            {/* Gallery Metadata */}
            <div className="bg-card border border-border rounded-lg p-6">
              <h2 className="text-xl font-bold text-foreground mb-4">Gallery Info</h2>
              <div className="grid sm:grid-cols-2 gap-4 text-sm">
                <div className="p-3 bg-secondary/30 rounded-lg border border-border">
                  <p className="text-muted-foreground text-xs uppercase tracking-wider mb-1">Photos</p>
                  <p className="font-bold text-foreground text-lg">{gallery.stats.photo_count}</p>
                </div>
                <div className="p-3 bg-secondary/30 rounded-lg border border-border">
                  <p className="text-muted-foreground text-xs uppercase tracking-wider mb-1">Storage</p>
                  <p className="font-bold text-foreground text-lg">
                    {gallery.stats.total_bytes > 0
                      ? `${(gallery.stats.total_bytes / (1024 * 1024)).toFixed(1)} MB`
                      : '0 MB'}
                  </p>
                </div>
                <div className="p-3 bg-secondary/30 rounded-lg border border-border">
                  <p className="text-muted-foreground text-xs uppercase tracking-wider mb-1">Downloads</p>
                  <p className="font-bold text-foreground text-lg">{gallery.stats.downloads_count}</p>
                </div>
                <div className="p-3 bg-secondary/30 rounded-lg border border-border">
                  <p className="text-muted-foreground text-xs uppercase tracking-wider mb-1">Favorites</p>
                  <p className="font-bold text-foreground text-lg">{gallery.stats.favorites_count}</p>
                </div>
              </div>
            </div>

            {/* Success Message */}
            {successMessage && (
              <div className="p-4 bg-green-500/10 border border-green-500/20 rounded-lg">
                <p className="text-green-600 text-sm font-medium">{successMessage}</p>
              </div>
            )}

            {/* Error Banner */}
            {errors.general && (
              <div className="p-4 bg-destructive/10 border border-destructive/30 rounded-lg">
                <p className="text-destructive text-sm font-medium">{errors.general}</p>
              </div>
            )}

            {/* Actions */}
            <div className="flex gap-4">
              <Link
                href={`/studio/galleries/${uuid}`}
                className="flex-1 py-3 px-6 bg-secondary text-foreground rounded-lg hover:bg-muted transition-colors font-semibold text-center"
              >
                Cancel
              </Link>
              <button
                type="submit"
                id="save-gallery-btn"
                disabled={updateMutation.isPending}
                className="flex-1 py-3 px-6 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-colors font-semibold disabled:opacity-60 disabled:cursor-not-allowed flex items-center justify-center gap-2"
              >
                {updateMutation.isPending ? (
                  <>
                    <div className="w-4 h-4 rounded-full border-2 border-primary-foreground/30 border-t-primary-foreground animate-spin" />
                    Saving...
                  </>
                ) : (
                  <>
                    <Save size={18} />
                    Save Changes
                  </>
                )}
              </button>
            </div>
          </form>
        </div>
      </div>
      {/* Cover Image Picker Modal */}
      {isPickerOpen && (
        <ImagePickerModal
          uuid={gallery.uuid}
          currentCoverUuid={form.clear_cover ? null : selectedCoverPhoto?.uuid}
          onClose={() => setIsPickerOpen(false)}
          onSelect={(photo) => {
            setSelectedCoverPhoto(photo)
            setForm((p) => ({ ...p, cover_photo_uuid: photo.uuid, clear_cover: false }))
            setIsPickerOpen(false)
          }}
        />
      )}
    </main>
  )
}

function ImagePickerModal({
  uuid,
  currentCoverUuid,
  onClose,
  onSelect,
}: {
  uuid: string
  currentCoverUuid?: string | null
  onClose: () => void
  onSelect: (photo: PhotoItem) => void
}) {
  const { photos, hasMore, isFetchingNextPage, fetchNextPage } = useInfiniteStudioGallery(uuid)

  return (
    <div className="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4" onClick={onClose}>
      <div
        className="bg-card border border-border rounded-xl max-w-2xl w-full max-h-[80vh] flex flex-col overflow-hidden shadow-2xl animate-in fade-in zoom-in duration-200"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Header */}
        <div className="p-4 border-b border-border flex items-center justify-between">
          <div>
            <h3 className="font-bold text-foreground text-lg">Select Cover Photo</h3>
            <p className="text-xs text-muted-foreground">Select an image from this gallery to feature as the cover</p>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 rounded-lg hover:bg-secondary text-muted-foreground hover:text-foreground transition-colors"
          >
            <X size={18} />
          </button>
        </div>

        {/* Content Grid */}
        <div className="flex-1 overflow-y-auto p-4">
          {photos.length === 0 ? (
            <div className="text-center py-12">
              <ImageIcon className="w-12 h-12 text-muted-foreground/45 mx-auto mb-2" />
              <p className="text-foreground font-semibold">No photos in gallery</p>
              <p className="text-xs text-muted-foreground">Upload photos to the gallery first to choose a cover.</p>
            </div>
          ) : (
            <div className="grid grid-cols-3 sm:grid-cols-4 gap-3">
              {photos.map((photo) => {
                const isSelected = currentCoverUuid === photo.uuid
                return (
                  <button
                    key={photo.uuid}
                    type="button"
                    onClick={() => onSelect(photo)}
                    className={`aspect-square rounded-lg overflow-hidden bg-muted relative group transition-all ${
                      isSelected ? 'ring-4 ring-primary scale-[0.97]' : 'hover:ring-2 hover:ring-primary/50'
                    }`}
                  >
                    <img
                      src={photo.variants?.sm || photo.cdn_url}
                      alt={photo.filename}
                      className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                      loading="lazy"
                    />
                    {isSelected && (
                      <div className="absolute inset-0 bg-primary/10 flex items-center justify-center">
                        <div className="bg-primary text-primary-foreground text-[10px] font-bold px-2 py-0.5 rounded-full">
                          Selected
                        </div>
                      </div>
                    )}
                  </button>
                )
              })}
            </div>
          )}

          {/* Load More */}
          {hasMore && (
            <div className="flex justify-center mt-6">
              <button
                type="button"
                onClick={() => fetchNextPage()}
                disabled={isFetchingNextPage}
                className="px-4 py-2 bg-secondary hover:bg-muted text-foreground text-xs font-semibold rounded-lg transition-colors disabled:opacity-50"
              >
                {isFetchingNextPage ? 'Loading...' : 'Load More Photos'}
              </button>
            </div>
          )}
        </div>
      </div>
    </div>
  )
}
