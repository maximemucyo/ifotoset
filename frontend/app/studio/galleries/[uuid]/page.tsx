'use client'

import { useState, useCallback, useEffect, useRef } from 'react'
import { useParams, useRouter } from 'next/navigation'
import Link from 'next/link'
import {
  ArrowLeft, Upload, Trash2, Edit, Share2, Lock, Globe, Image, Download,
  Heart, Eye, MoreHorizontal, X, Check, AlertTriangle, CloudUpload,
  ChevronLeft, ChevronRight, RotateCcw, Wifi, WifiOff, Star, ZoomIn, ZoomOut,
  BarChart3, Users, Mail
} from 'lucide-react'
import { useGallery, useDeleteGalleryMutation, PhotoItem, useDeletePhotoMutation, useUpdateGalleryMutation, GalleryItem } from '@/lib/queries/galleries'
import { useGalleryAnalytics } from '@/lib/queries/analytics'
import { uploadPhotoDirectly } from '@/lib/storage'
import { formatBytes } from '@/lib/utils'
import { useQueryClient } from '@tanstack/react-query'
import { useInfiniteStudioGallery } from './hooks/useInfiniteStudioGallery'
import { Routes } from '@/lib/routes'
import { useCurrentUser } from '@/lib/queries/auth'

type UploadStatus = 'queued' | 'uploading' | 'paused' | 'error' | 'done'

interface UploadFile {
  id: string
  file: File
  status: UploadStatus
  progress: number
  error?: string
  retryCount: number
}

export default function GalleryDetail() {
  const { uuid } = useParams<{ uuid: string }>()
  const router = useRouter()
  const queryClient = useQueryClient()

  const { data, isLoading, error } = useGallery(uuid)
  const gallery = data?.data
  const { data: currentUser } = useCurrentUser()
  const deleteMutation = useDeleteGalleryMutation()
  const updateGalleryMutation = useUpdateGalleryMutation()
  const [selectedPhoto, setSelectedPhoto] = useState<PhotoItem | null>(null)

  // Zoom & Pan states
  const [scale, setScale] = useState(1)
  const [position, setPosition] = useState({ x: 0, y: 0 })
  const [isZoomDragging, setIsZoomDragging] = useState(false)
  const [dragStart, setDragStart] = useState({ x: 0, y: 0 })

  const imageRef = useRef<HTMLImageElement>(null)

  // Pinch zoom states
  const pinchStartDistance = useRef<number | null>(null)
  const pinchStartScale = useRef<number>(1)
  const pinchStartCenter = useRef<{ x: number; y: number } | null>(null)
  const isTouchPanning = useRef(false)
  const dragStartRef = useRef({ x: 0, y: 0 })
  const touchStartX = useRef<number | null>(null)
  const touchEndX = useRef<number | null>(null)
  const touchStartY = useRef<number | null>(null)
  const touchEndY = useRef<number | null>(null)

  // Reset zoom whenever active photo changes
  useEffect(() => {
    setScale(1)
    setPosition({ x: 0, y: 0 })
    setIsZoomDragging(false)
  }, [selectedPhoto?.uuid])

  const clampPosition = useCallback((currentScale: number, x: number, y: number) => {
    if (!imageRef.current || currentScale <= 1) {
      return { x: 0, y: 0 }
    }
    const containerWidth = window.innerWidth
    const containerHeight = window.innerHeight

    const imageWidth = imageRef.current.clientWidth
    const imageHeight = imageRef.current.clientHeight

    const maxTranslateX = Math.max(0, (imageWidth * currentScale - containerWidth) / 2)
    const maxTranslateY = Math.max(0, (imageHeight * currentScale - containerHeight) / 2)

    return {
      x: Math.max(-maxTranslateX, Math.min(maxTranslateX, x)),
      y: Math.max(-maxTranslateY, Math.min(maxTranslateY, y)),
    }
  }, [])

  const handleZoomIn = () => {
    setScale((prev) => Math.min(5, prev + 0.5))
  }

  const handleZoomOut = () => {
    setScale((prev) => Math.max(1, prev - 0.5))
  }

  const handleResetZoom = () => {
    setScale(1)
    setPosition({ x: 0, y: 0 })
  }

  // Clamp translation whenever scale changes
  useEffect(() => {
    if (scale === 1) {
      setPosition({ x: 0, y: 0 })
    } else {
      setPosition((prev) => clampPosition(scale, prev.x, prev.y))
    }
  }, [scale, clampPosition])

  const handleWheel = (e: React.WheelEvent) => {
    if (!imageRef.current) return

    const rect = imageRef.current.getBoundingClientRect()
    const mouseX = e.clientX - rect.left - rect.width / 2
    const mouseY = e.clientY - rect.top - rect.height / 2

    const zoomFactor = 1.1
    const direction = e.deltaY < 0 ? 1 : -1
    
    let newScale = scale * (direction > 0 ? zoomFactor : 1 / zoomFactor)
    newScale = Math.max(1, Math.min(5, newScale))

    if (newScale === 1) {
      setScale(1)
      setPosition({ x: 0, y: 0 })
    } else {
      const imageX = (mouseX - position.x) / scale
      const imageY = (mouseY - position.y) / scale

      const nextX = mouseX - imageX * newScale
      const nextY = mouseY - imageY * newScale

      const clamped = clampPosition(newScale, nextX, nextY)
      
      setScale(newScale)
      setPosition(clamped)
    }
  }

  const handleDoubleClick = (e: React.MouseEvent) => {
    if (!imageRef.current) return
    
    if (scale > 1) {
      setScale(1)
      setPosition({ x: 0, y: 0 })
    } else {
      const rect = imageRef.current.getBoundingClientRect()
      const mouseX = e.clientX - rect.left - rect.width / 2
      const mouseY = e.clientY - rect.top - rect.height / 2

      const targetScale = 2.5
      const imageX = (mouseX - position.x) / scale
      const imageY = (mouseY - position.y) / scale

      const nextX = mouseX - imageX * targetScale
      const nextY = mouseY - imageY * targetScale

      const clamped = clampPosition(targetScale, nextX, nextY)

      setScale(targetScale)
      setPosition(clamped)
    }
  }

  const handleMouseDown = (e: React.MouseEvent) => {
    if (scale <= 1) return
    e.preventDefault()
    setIsZoomDragging(true)
    setDragStart({
      x: e.clientX - position.x,
      y: e.clientY - position.y,
    })
  }

  const handleMouseMove = (e: React.MouseEvent) => {
    if (!isZoomDragging) return
    const newX = e.clientX - dragStart.x
    const newY = e.clientY - dragStart.y
    const clamped = clampPosition(scale, newX, newY)
    setPosition(clamped)
  }

  const handleMouseUp = () => {
    setIsZoomDragging(false)
  }

  const handleTouchStart = (e: React.TouchEvent) => {
    if (e.touches.length === 2) {
      const dist = Math.hypot(
        e.touches[0].clientX - e.touches[1].clientX,
        e.touches[0].clientY - e.touches[1].clientY
      )
      pinchStartDistance.current = dist
      pinchStartScale.current = scale
      pinchStartCenter.current = {
        x: (e.touches[0].clientX + e.touches[1].clientX) / 2,
        y: (e.touches[0].clientY + e.touches[1].clientY) / 2,
      }
      isTouchPanning.current = false
    } else if (e.touches.length === 1) {
      touchStartX.current = e.touches[0].clientX
      touchStartY.current = e.touches[0].clientY
      
      if (scale > 1) {
        dragStartRef.current = { ...position }
        isTouchPanning.current = true
      } else {
        isTouchPanning.current = false
      }
    }
  }

  const handleTouchMove = (e: React.TouchEvent) => {
    if (e.touches.length === 2 && pinchStartDistance.current && pinchStartCenter.current && imageRef.current) {
      e.stopPropagation()
      const dist = Math.hypot(
        e.touches[0].clientX - e.touches[1].clientX,
        e.touches[0].clientY - e.touches[1].clientY
      )
      
      let newScale = pinchStartScale.current * (dist / pinchStartDistance.current)
      newScale = Math.max(1, Math.min(5, newScale))

      const center = {
        x: (e.touches[0].clientX + e.touches[1].clientX) / 2,
        y: (e.touches[0].clientY + e.touches[1].clientY) / 2,
      }

      const rect = imageRef.current.getBoundingClientRect()
      const pinchCenterX = center.x - rect.left - rect.width / 2
      const pinchCenterY = center.y - rect.top - rect.height / 2

      const imageX = (pinchCenterX - position.x) / scale
      const imageY = (pinchCenterY - position.y) / scale

      const nextX = pinchCenterX - imageX * newScale
      const nextY = pinchCenterY - imageY * newScale

      const clamped = clampPosition(newScale, nextX, nextY)

      setScale(newScale)
      setPosition(clamped)
    } else if (e.touches.length === 1) {
      if (scale > 1 && isTouchPanning.current && touchStartX.current && touchStartY.current) {
        e.stopPropagation()
        const deltaX = e.touches[0].clientX - touchStartX.current
        const deltaY = e.touches[0].clientY - touchStartY.current
        
        const newX = dragStartRef.current.x + deltaX
        const newY = dragStartRef.current.y + deltaY
        const clamped = clampPosition(scale, newX, newY)
        setPosition(clamped)
      }
    }
  }

  const handleTouchEnd = () => {
    pinchStartDistance.current = null
    pinchStartCenter.current = null
    isTouchPanning.current = false
  }

  const handleMakeFeatured = useCallback((photo: PhotoItem, e?: React.MouseEvent) => {
    e?.stopPropagation()
    if (!gallery || updateGalleryMutation.isPending) return

    const previousGallery = queryClient.getQueryData<{ data: GalleryItem }>(['gallery', uuid])

    // Optimistic UI cache update
    if (previousGallery) {
      queryClient.setQueryData<{ data: GalleryItem }>(['gallery', uuid], {
        ...previousGallery,
        data: {
          ...previousGallery.data,
          cover_photo: photo,
          has_explicit_cover: true,
        }
      })
    }

    updateGalleryMutation.mutate(
      {
        uuid: gallery.uuid,
        cover_photo_uuid: photo.uuid,
        version: gallery.version,
      },
      {
        onError: () => {
          // Revert cache on error
          if (previousGallery) {
            queryClient.setQueryData(['gallery', uuid], previousGallery)
          }
          alert('Failed to set featured image. Please try again.')
        },
      }
    )
  }, [gallery, uuid, queryClient, updateGalleryMutation])

  const {
    photos,
    setPhotos,
    hasMore,
    isFetchingNextPage,
    fetchNextPage,
  } = useInfiniteStudioGallery(uuid as string)

  const [uploads, setUploads] = useState<UploadFile[]>([])
  const [isDragging, setIsDragging] = useState(false)
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false)
  const [shareTooltip, setShareTooltip] = useState(false)
  const [isOnline, setIsOnline] = useState(true)
  const [isCoverSelectOpen, setIsCoverSelectOpen] = useState(false)
  const [isUploadingCover, setIsUploadingCover] = useState(false)
  const [coverUploadProgress, setCoverUploadProgress] = useState(0)
  const [activeView, setActiveView] = useState<'photos' | 'analytics'>('photos')
  const { data: analytics, isLoading: analyticsLoading } = useGalleryAnalytics(uuid as string)

  const abortControllersRef = useRef<Map<string, AbortController>>(new Map())
  const networkPausedRef = useRef<Set<string>>(new Set())
  const runningUploadsRef = useRef<Set<string>>(new Set())

  useEffect(() => {
    // Safely check navigator.onLine on client-side mount
    setIsOnline(window.navigator.onLine)

    const handleOnline = () => {
      setIsOnline(true)
      setUploads((prev) =>
        prev.map((u) => {
          if (u.status === 'paused' && u.retryCount < 1) {
            return {
              ...u,
              status: 'queued',
              progress: 0,
              error: undefined,
              retryCount: u.retryCount + 1,
            }
          } else if (u.status === 'paused') {
            return {
              ...u,
              status: 'error',
              error: 'Reconnection retry limit reached. Please retry manually.',
            }
          }
          return u
        })
      )
    }

    const handleOffline = () => {
      setIsOnline(false)
      setUploads((prev) =>
        prev.map((u) => {
          if (u.status === 'uploading') {
            networkPausedRef.current.add(u.id)
            const controller = abortControllersRef.current.get(u.id)
            if (controller) {
              controller.abort('network-paused')
            }
            return {
              ...u,
              status: 'paused',
              progress: 0,
              error: 'Network connection lost',
            }
          }
          return u
        })
      )
    }

    window.addEventListener('online', handleOnline)
    window.addEventListener('offline', handleOffline)

    return () => {
      window.removeEventListener('online', handleOnline)
      window.removeEventListener('offline', handleOffline)
    }
  }, [])

  const activeUploads = uploads.filter((u) => u.status !== 'done')

  const deletePhotoMutation = useDeletePhotoMutation(uuid as string)

  const handleClearCover = useCallback(() => {
    if (!gallery || updateGalleryMutation.isPending) return

    const previousGallery = queryClient.getQueryData<{ data: GalleryItem }>(['gallery', uuid])

    if (previousGallery) {
      queryClient.setQueryData<{ data: GalleryItem }>(['gallery', uuid], {
        ...previousGallery,
        data: {
          ...previousGallery.data,
          cover_photo: null,
          has_explicit_cover: false,
        }
      })
    }

    updateGalleryMutation.mutate(
      {
        uuid: gallery.uuid,
        clear_cover: true,
        version: gallery.version,
      },
      {
        onSuccess: () => {
          queryClient.invalidateQueries({ queryKey: ['gallery', uuid] })
        },
        onError: () => {
          if (previousGallery) {
            queryClient.setQueryData(['gallery', uuid], previousGallery)
          }
          alert('Failed to clear cover photo. Please try again.')
        },
      }
    )
  }, [gallery, uuid, queryClient, updateGalleryMutation])

  const handleCoverUpload = useCallback(async (e: React.ChangeEvent<HTMLInputElement>) => {
    if (!e.target.files || e.target.files.length === 0 || !gallery) return
    const file = e.target.files[0]
    if (!file.type.startsWith('image/')) {
      alert('Please upload an image file.')
      return
    }

    setIsUploadingCover(true)
    setCoverUploadProgress(0)

    try {
      const res = await uploadPhotoDirectly(
        gallery.uuid,
        file,
        (pct) => {
          setCoverUploadProgress(pct)
        }
      )

      const newPhotoItem: PhotoItem = {
        uuid: res.photo_id,
        filename: file.name,
        mime_type: file.type,
        size: file.size,
        width: null,
        height: null,
        blurhash: null,
        status: 'processing',
        cdn_url: res.cdn_url,
        variants: { xs: '', sm: '', md: '', lg: '', xl: '' },
        taken_at: null,
        created_at: new Date().toISOString(),
      }
      setPhotos((prev) => [newPhotoItem, ...prev])

      updateGalleryMutation.mutate(
        {
          uuid: gallery.uuid,
          cover_photo_uuid: res.photo_id,
          version: gallery.version,
        },
        {
          onSuccess: () => {
            setIsCoverSelectOpen(false)
            queryClient.invalidateQueries({ queryKey: ['gallery', uuid] })
          },
          onError: () => {
            alert('Failed to set uploaded image as cover. Please choose it manually.')
          }
        }
      )
    } catch (err: any) {
      alert(err instanceof Error ? err.message : 'Cover upload failed')
    } finally {
      setIsUploadingCover(false)
      setCoverUploadProgress(0)
    }
  }, [gallery, uuid, queryClient, updateGalleryMutation, setPhotos])

  // Infinite Scroll Sentinel Observer
  const sentinelRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (!sentinelRef.current) return

    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting && hasMore && !isFetchingNextPage) {
          fetchNextPage()
        }
      },
      { rootMargin: '400px' }
    )

    observer.observe(sentinelRef.current)
    return () => observer.disconnect()
  }, [hasMore, isFetchingNextPage, fetchNextPage])

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
        setPhotos((prev) => prev.filter((p) => p.uuid !== photoUuid))
      },
      onError: () => {
        alert('Failed to delete the photo. Please try again.')
        const restored = photos.find((p) => p.uuid === photoUuid)
        if (restored) {
          setSelectedPhoto(restored)
        }
      }
    })
  }, [deletePhotoMutation, photos, setPhotos])

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

  const updateUpload = useCallback((id: string, patch: Partial<UploadFile>) => {
    setUploads((prev) => prev.map((u) => (u.id === id ? { ...u, ...patch } : u)))
  }, [])

  const startUpload = useCallback(
    async (item: UploadFile) => {
      if (!gallery) return
      if (runningUploadsRef.current.has(item.id)) return
      runningUploadsRef.current.add(item.id)

      const controller = new AbortController()
      abortControllersRef.current.set(item.id, controller)

      updateUpload(item.id, { status: 'uploading', error: undefined })

      try {
        const res = await uploadPhotoDirectly(
          gallery.uuid,
          item.file,
          (pct) => {
            updateUpload(item.id, { progress: pct })
          },
          controller.signal
        )

        updateUpload(item.id, { status: 'done', progress: 100 })

        const newPhotoItem: PhotoItem = {
          uuid: res.photo_id,
          filename: item.file.name,
          mime_type: item.file.type,
          size: item.file.size,
          width: null,
          height: null,
          blurhash: null,
          status: 'processing',
          cdn_url: res.cdn_url,
          variants: { xs: '', sm: '', md: '', lg: '', xl: '' },
          taken_at: null,
          created_at: new Date().toISOString(),
        }
        setPhotos((prev) => [newPhotoItem, ...prev])

        queryClient.invalidateQueries({ queryKey: ['gallery', uuid] })
      } catch (err: any) {
        if (networkPausedRef.current.has(item.id)) {
          networkPausedRef.current.delete(item.id)
          return
        }

        // If it was aborted by client cancellation, it will have been cleaned up/removed
        if (err.name === 'AbortError' && !abortControllersRef.current.has(item.id)) {
          return
        }

        updateUpload(item.id, {
          status: 'error',
          error: err instanceof Error ? err.message : 'Upload failed',
        })
      } finally {
        runningUploadsRef.current.delete(item.id)
        abortControllersRef.current.delete(item.id)
      }
    },
    [gallery, uuid, queryClient, setPhotos]
  )

  const handleCancelUpload = useCallback((id: string) => {
    const controller = abortControllersRef.current.get(id)
    if (controller) {
      controller.abort('user-cancelled')
      abortControllersRef.current.delete(id)
    }
    runningUploadsRef.current.delete(id)
    networkPausedRef.current.delete(id)
    setUploads((prev) => prev.filter((u) => u.id !== id))
  }, [])

  const handleRetryUpload = useCallback((id: string) => {
    updateUpload(id, {
      status: 'queued',
      progress: 0,
      error: undefined,
      retryCount: 0,
    })
  }, [])

  const handleRetryAllFailed = useCallback(() => {
    setUploads((prev) =>
      prev.map((u) =>
        u.status === 'error'
          ? { ...u, status: 'queued', progress: 0, error: undefined, retryCount: 0 }
          : u
      )
    )
  }, [])

  const handleClearAllFailed = useCallback(() => {
    setUploads((prev) => prev.filter((u) => u.status !== 'error'))
  }, [])

  const processFiles = useCallback(
    (files: File[]) => {
      if (!gallery) return

      const imageFiles = files.filter((f) => f.type.startsWith('image/'))
      if (imageFiles.length === 0) return

      const newUploads: UploadFile[] = imageFiles.map((file) => ({
        id: `${file.name}-${file.size}-${Date.now()}-${Math.random()}`,
        file,
        status: 'queued',
        progress: 0,
        retryCount: 0,
      }))

      setUploads((prev) => [...prev, ...newUploads])
    },
    [gallery]
  )

  const CONCURRENCY_LIMIT = 4

  useEffect(() => {
    const queuedItems = uploads.filter((u) => u.status === 'queued')
    const uploadingItems = uploads.filter((u) => u.status === 'uploading')

    if (queuedItems.length > 0 && uploadingItems.length < CONCURRENCY_LIMIT) {
      const slotsAvailable = CONCURRENCY_LIMIT - uploadingItems.length
      const itemsToStart = queuedItems.slice(0, slotsAvailable)

      itemsToStart.forEach((item) => {
        startUpload(item)
      })
    }
  }, [uploads, startUpload])

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
    const username = currentUser?.user?.username || gallery.photographer?.username || 'photographer'
    const url = Routes.publicGalleryUrl(username, gallery.slug)
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



  const renderAnalyticsTab = () => {
    if (analyticsLoading) {
      return (
        <div className="flex flex-col items-center justify-center py-12 space-y-3">
          <div className="w-8 h-8 rounded-full border-4 border-primary/20 border-t-primary animate-spin" />
          <p className="text-muted-foreground text-sm font-medium">Loading visitor activity...</p>
        </div>
      )
    }

    if (!analytics) {
      return (
        <div className="bg-card border border-border rounded-xl p-12 text-center">
          <p className="text-muted-foreground text-sm font-medium">No visitor activity found yet.</p>
        </div>
      )
    }

    const { overview, visitors, recent_activity } = analytics

    return (
      <div className="space-y-8">
        {/* Gallery Analytics Stats */}
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          {[
            { label: 'Unique Visitors', value: overview.visitors, icon: Eye, description: 'Distinct browser sessions' },
            { label: 'Total Views', value: overview.views, icon: Globe, description: 'Page loads' },
            { label: 'Downloads', value: overview.downloads, icon: Download, description: 'Photos & ZIP downloads' },
            { label: 'Favorites', value: overview.favorites, icon: Heart, description: 'Heart selections' },
          ].map((stat) => {
            const Icon = stat.icon
            return (
              <div key={stat.label} className="bg-card/50 border border-border rounded-xl p-5 hover:bg-card hover:border-primary/30 transition-all">
                <div className="flex items-center justify-between mb-2">
                  <span className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{stat.label}</span>
                  <Icon className="w-5 h-5 text-primary opacity-80" />
                </div>
                <div className="text-2xl font-bold text-foreground mb-1">{stat.value}</div>
                <p className="text-[10px] text-muted-foreground leading-snug">{stat.description}</p>
              </div>
            )
          })}
        </div>

        {/* Visitor Activity & Emails */}
        <div className="bg-card border border-border rounded-xl overflow-hidden">
          <div className="px-6 py-5 border-b border-border/50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
              <h3 className="font-bold text-lg text-foreground">Visitor Emails & Actions</h3>
              <p className="text-xs text-muted-foreground">Emails entered by visitors when downloading or favoriting photos.</p>
            </div>
            <span className="shrink-0 px-3 py-1 bg-primary/10 text-primary rounded-full text-xs font-bold self-start sm:self-auto">
              {visitors.length} Unique Guests
            </span>
          </div>

          <div className="overflow-x-auto">
            {visitors.length === 0 ? (
              <div className="p-12 text-center text-muted-foreground text-sm">
                No visitor email records yet. Emails are captured when guests download photos or add them to favorites.
              </div>
            ) : (
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr className="border-b border-border/50 text-[11px] font-bold uppercase tracking-wider text-muted-foreground bg-muted/20">
                    <th className="px-6 py-4">Visitor Email</th>
                    <th className="px-6 py-4">Downloads</th>
                    <th className="px-6 py-4">Favorites</th>
                    <th className="px-6 py-4">Last Activity</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border/30">
                  {visitors.map((v, i) => (
                    <tr key={i} className="hover:bg-muted/10 transition-colors text-sm text-foreground">
                      <td className="px-6 py-4 font-medium max-w-[200px] truncate">
                        {v.email ? (
                          <div className="flex items-center gap-2">
                            <span className="w-2 h-2 rounded-full bg-green-500" />
                            {v.email}
                          </div>
                        ) : (
                          <span className="text-muted-foreground italic flex items-center gap-2">
                            <span className="w-2 h-2 rounded-full bg-neutral-400" />
                            Anonymous Visitor
                          </span>
                        )}
                      </td>
                      <td className="px-6 py-4 font-semibold text-primary">{v.downloads}</td>
                      <td className="px-6 py-4 font-semibold text-rose-500">{v.favorites}</td>
                      <td className="px-6 py-4 text-xs text-muted-foreground">
                        {new Date(v.last_active).toLocaleString()}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </div>

        {/* Recent Activity Log */}
        <div className="bg-card border border-border rounded-xl overflow-hidden">
          <div className="px-6 py-5 border-b border-border/50">
            <h3 className="font-bold text-lg text-foreground">Recent Event History</h3>
            <p className="text-xs text-muted-foreground">Real-time log of events from the past 30 actions.</p>
          </div>

          <div className="divide-y divide-border/30 max-h-[400px] overflow-y-auto">
            {recent_activity.length === 0 ? (
              <div className="p-12 text-center text-muted-foreground text-sm">
                No recent activity recorded.
              </div>
            ) : (
              recent_activity.map((act, i) => {
                let eventText = ''
                let iconColor = 'text-primary'

                if (act.event === 'gallery_viewed') {
                  eventText = 'viewed the gallery'
                  iconColor = 'text-blue-500'
                } else if (act.event === 'photo_downloaded') {
                  eventText = 'downloaded a photo'
                  iconColor = 'text-emerald-500'
                } else if (act.event === 'gallery_zip_file_downloaded') {
                  eventText = 'downloaded the gallery ZIP archive'
                  iconColor = 'text-emerald-500 font-semibold'
                } else if (act.event === 'photo_favorited') {
                  eventText = 'added a photo to favorites'
                  iconColor = 'text-rose-500'
                } else if (act.event === 'photo_unfavorited') {
                  eventText = 'removed a photo from favorites'
                  iconColor = 'text-muted-foreground'
                } else {
                  eventText = `performed action: ${act.event}`
                }

                return (
                  <div key={i} className="px-6 py-3.5 flex items-center justify-between text-xs text-foreground hover:bg-muted/10 transition-colors">
                    <div className="flex items-center gap-2">
                      <span className={`font-semibold ${iconColor}`}>
                        {act.email || 'Anonymous guest'}
                      </span>
                      <span className="text-muted-foreground">{eventText}</span>
                    </div>
                    <span className="text-[10px] text-muted-foreground">
                      {new Date(act.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
                    </span>
                  </div>
                )
              })
            )}
          </div>
        </div>
      </div>
    )
  }

  const renderCoverPhotoCard = () => (
    <div className="bg-card border border-border rounded-xl p-6 shadow-sm space-y-4">
      <div className="flex items-center justify-between pb-2 border-b border-border">
        <h3 className="text-sm font-semibold text-foreground flex items-center gap-2">
          <Star size={16} className="text-yellow-500" />
          Cover Photo
        </h3>
        {gallery?.has_explicit_cover && (
          <span className="text-[10px] font-bold px-2 py-0.5 bg-yellow-500/10 text-yellow-600 border border-yellow-500/20 rounded-full">
            Custom
          </span>
        )}
        {!gallery?.has_explicit_cover && gallery?.cover_photo && (
          <span className="text-[10px] font-bold px-2 py-0.5 bg-blue-500/10 text-blue-600 border border-blue-500/20 rounded-full">
            Auto
          </span>
        )}
      </div>

      {/* Preview Container */}
      <div className="relative aspect-[16/9] w-full rounded-lg bg-muted overflow-hidden border border-border flex items-center justify-center">
        {gallery?.cover_photo ? (
          <img
            src={gallery.cover_photo.variants?.md || gallery.cover_photo.cdn_url}
            alt="Cover photo preview"
            className="w-full h-full object-cover"
          />
        ) : photos[0] ? (
          <div className="relative w-full h-full">
            <img
              src={photos[0].variants?.md || photos[0].cdn_url}
              alt="Default Cover preview"
              className="w-full h-full object-cover opacity-60"
            />
            <div className="absolute inset-0 flex items-center justify-center bg-black/25">
              <span className="text-white text-xs font-semibold px-2.5 py-1 bg-black/40 rounded-full backdrop-blur-sm">
                Default Auto Cover
              </span>
            </div>
          </div>
        ) : (
          <div className="text-center p-6 space-y-2">
            <Image size={24} className="mx-auto text-muted-foreground/45" />
            <p className="text-xs text-muted-foreground">No cover photo set</p>
          </div>
        )}

        {/* Custom Cover Upload Progress Overlay */}
        {isUploadingCover && (
          <div className="absolute inset-0 bg-background/80 backdrop-blur-sm flex flex-col items-center justify-center p-4 gap-2">
            <div className="w-8 h-8 rounded-full border-2 border-primary/20 border-t-primary animate-spin" />
            <p className="text-xs font-medium text-foreground">Uploading cover... {coverUploadProgress}%</p>
          </div>
        )}
      </div>

      {/* Details & Actions */}
      <div className="space-y-2">
        {gallery?.cover_photo && (
          <p className="text-xs text-muted-foreground truncate max-w-full" title={gallery.cover_photo.filename}>
            File: <strong className="text-foreground font-medium">{gallery.cover_photo.filename}</strong>
          </p>
        )}
        
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-2">
          <button
            type="button"
            onClick={() => setIsCoverSelectOpen(true)}
            disabled={photos.length === 0}
            className="w-full py-2 bg-secondary text-foreground hover:bg-muted font-semibold text-xs rounded-lg transition-colors border border-border flex items-center justify-center gap-1.5 disabled:opacity-50"
          >
            Choose Existing
          </button>

          <label className="w-full py-2 bg-primary text-primary-foreground hover:bg-accent font-semibold text-xs rounded-lg transition-colors flex items-center justify-center gap-1.5 cursor-pointer text-center">
            Upload Custom
            <input
              type="file"
              accept="image/*"
              onChange={handleCoverUpload}
              disabled={isUploadingCover}
              className="hidden"
            />
          </label>
        </div>

        {gallery?.has_explicit_cover && (
          <button
            type="button"
            onClick={handleClearCover}
            disabled={updateGalleryMutation.isPending}
            className="w-full py-2 bg-destructive/10 text-destructive hover:bg-destructive/20 font-semibold text-xs rounded-lg transition-colors border border-destructive/20 mt-1"
          >
            Revert to Automatic Cover
          </button>
        )}
      </div>
    </div>
  )

  return (
    <main className="flex-1 min-h-screen bg-background">
      {/* Header */}
      <div className="border-b border-border bg-card p-4 sm:p-6">
        <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
          <div className="flex items-start gap-3 sm:gap-4 min-w-0 flex-1">
            <Link
              href="/studio/galleries"
              className="p-2 mt-0.5 rounded-lg hover:bg-secondary transition-colors text-muted-foreground hover:text-foreground shrink-0"
            >
              <ArrowLeft size={20} />
            </Link>
            <div className="min-w-0 flex-1">
              <div className="flex items-center gap-3 flex-wrap min-w-0">
                <h1 className="text-2xl sm:text-3xl font-bold text-foreground truncate max-w-full" title={gallery.title}>{gallery.title}</h1>
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
              <div className="flex flex-wrap gap-x-4 gap-y-1 mt-2 text-sm text-muted-foreground">
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

          <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto shrink-0">
            <div className="relative w-full sm:w-auto">
              <button
                id="share-gallery-btn"
                onClick={handleShare}
                className="flex items-center justify-center gap-2 px-4 py-2 bg-secondary text-foreground rounded-lg hover:bg-muted transition-colors font-semibold text-sm w-full sm:w-auto"
              >
                {shareTooltip ? <Check size={16} className="text-green-500" /> : <Share2 size={16} />}
                {shareTooltip ? 'Copied!' : 'Share'}
              </button>
            </div>
            <Link
              href={`/studio/galleries/${uuid}/edit`}
              id="edit-gallery-btn"
              className="flex items-center justify-center gap-2 px-4 py-2 bg-secondary text-foreground rounded-lg hover:bg-muted transition-colors font-semibold text-sm w-full sm:w-auto"
            >
              <Edit size={16} />
              Edit
            </Link>
            <button
              id="delete-gallery-btn"
              onClick={() => setShowDeleteConfirm(true)}
              className="flex items-center justify-center gap-2 px-4 py-2 bg-destructive/10 text-destructive rounded-lg hover:bg-destructive/20 transition-colors font-semibold text-sm w-full sm:w-auto"
            >
              <Trash2 size={16} />
              Delete
            </button>
          </div>
        </div>

        {/* Stats Row */}
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6 pt-6 border-t border-border">
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

      {/* Tab Switcher */}
      <div className="flex border-b border-border mt-6 px-4 sm:px-6">
        <button
          onClick={() => setActiveView('photos')}
          className={`px-6 py-3 text-sm font-semibold border-b-2 transition-all flex items-center gap-2 ${
            activeView === 'photos'
              ? 'border-primary text-primary'
              : 'border-transparent text-muted-foreground hover:text-foreground'
          }`}
        >
          <Image size={16} />
          Photos
        </button>
        <button
          onClick={() => setActiveView('analytics')}
          className={`px-6 py-3 text-sm font-semibold border-b-2 transition-all flex items-center gap-2 ${
            activeView === 'analytics'
              ? 'border-primary text-primary'
              : 'border-transparent text-muted-foreground hover:text-foreground'
          }`}
        >
          <BarChart3 size={16} />
          Visitor Activity & Emails
        </button>
      </div>

      {/* Content */}
      <div className="p-4 sm:p-6 max-w-none mx-auto">
        {activeView === 'photos' ? (
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {/* Left Column (Upload Zone + Active Uploads + Photo Grid) */}
            <div className="lg:col-span-2 space-y-6 min-w-0 flex-1">
              {/* Offline Warning Banner */}
              {!isOnline && (
                <div className="mb-6 bg-amber-500/10 border border-amber-500/20 text-amber-600 dark:text-amber-400 rounded-xl p-4 flex items-center gap-3 backdrop-blur-sm">
                  <WifiOff size={20} className="shrink-0 animate-pulse text-amber-500" />
                  <div className="text-sm">
                    🔌 You're offline. Active uploads are paused and will automatically resume when your connection is restored.
                  </div>
                </div>
              )}

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
                <div className="flex flex-col items-center justify-center space-y-2">
                  <CloudUpload className="w-12 h-12 text-muted-foreground/60" />
                  <p className="text-foreground font-semibold text-sm">Drag and drop your photos here</p>
                  <p className="text-muted-foreground text-xs">or click to browse from your device</p>
                  <input
                    type="file"
                    multiple
                    accept="image/*"
                    onChange={handleFileInput}
                    className="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                  />
                </div>
              </div>

              {/* Active Uploads */}
              {uploads.length > 0 && uploads.some((u) => u.status !== 'done') && (
                <div className="bg-card border border-border rounded-xl p-4 sm:p-6 shadow-sm space-y-4">
                  <div className="flex items-center justify-between pb-3 border-b border-border">
                    <div>
                      <h3 className="font-bold text-sm text-foreground">Uploading Photos</h3>
                      <p className="text-xs text-muted-foreground">Please keep this page open until completion.</p>
                    </div>
                    <span className="text-xs font-semibold px-2.5 py-1 bg-primary/10 text-primary rounded-full animate-pulse">
                      {uploads.filter((u) => u.status === 'uploading').length} Uploading
                    </span>
                  </div>

                  <div className="space-y-3 max-h-[300px] overflow-y-auto divide-y divide-border/30 pr-2">
                    {uploads.map((u) => {
                      if (u.status === 'done') return null;

                      let statusLabel = 'Queued';
                      let progressColor = 'bg-primary';

                      if (u.status === 'uploading') {
                        statusLabel = `${Math.round(u.progress)}%`;
                      } else if (u.status === 'paused') {
                        statusLabel = 'Offline';
                        progressColor = 'bg-amber-500';
                      } else if (u.status === 'error') {
                        statusLabel = 'Upload failed';
                        progressColor = 'bg-destructive';
                      }

                      return (
                        <div key={u.id} className="space-y-1.5 pb-3 border-b border-border/30 last:border-0 last:pb-0">
                          <div className="flex items-center justify-between gap-4">
                            <p className="text-xs font-medium text-foreground truncate max-w-[50%]" title={u.file.name}>
                              {u.file.name}
                            </p>
                            
                            <div className="flex items-center gap-3 shrink-0">
                              <span className={`text-[10px] font-semibold px-2 py-0.5 rounded-full ${
                                u.status === 'error'
                                  ? 'bg-destructive/10 text-destructive'
                                  : u.status === 'paused'
                                  ? 'bg-amber-500/10 text-amber-500'
                                  : u.status === 'uploading'
                                  ? 'bg-primary/10 text-primary animate-pulse'
                                  : 'bg-muted text-muted-foreground'
                              }`}>
                                {statusLabel}
                              </span>
                              
                              <div className="flex items-center gap-1.5">
                                {u.status === 'error' && (
                                  <button
                                    onClick={() => handleRetryUpload(u.id)}
                                    disabled={!isOnline}
                                    className="p-1 rounded hover:bg-secondary text-muted-foreground hover:text-foreground transition-colors disabled:opacity-40"
                                    title={isOnline ? 'Retry upload' : 'Reconnect to retry'}
                                  >
                                    <RotateCcw size={14} />
                                  </button>
                                )}
                                <button
                                  onClick={() => handleCancelUpload(u.id)}
                                  className="p-1 rounded hover:bg-secondary text-muted-foreground hover:text-destructive transition-colors"
                                  title="Cancel upload"
                                >
                                  <X size={14} />
                                </button>
                              </div>
                            </div>
                          </div>
                          
                          <div className="h-1.5 bg-muted rounded-full overflow-hidden">
                            <div
                              className={`h-full rounded-full transition-all duration-300 ${progressColor}`}
                              style={{ width: `${u.status === 'error' || u.status === 'paused' ? 100 : u.progress}%` }}
                            />
                          </div>
                          {u.status === 'error' && u.error && (
                            <p className="text-destructive text-[11px] mt-0.5">{u.error}</p>
                          )}
                        </div>
                      );
                    })}
                  </div>
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
                <>
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

                  {/* Infinite Scroll Sentinel */}
                  <div ref={sentinelRef} className="w-full h-10 mt-6 flex justify-center items-center">
                    {isFetchingNextPage ? (
                      <div className="flex items-center gap-2 py-4">
                        <div className="w-6 h-6 rounded-full border-2 border-primary/20 border-t-primary animate-spin" />
                        <p className="text-muted-foreground text-xs font-medium">Loading more photos...</p>
                      </div>
                    ) : hasMore ? (
                      <button
                        onClick={() => fetchNextPage()}
                        className="text-xs text-muted-foreground hover:text-foreground font-medium underline py-2"
                      >
                        Load More
                      </button>
                    ) : null}
                  </div>
                </>
              )}
            </div>

            {/* Cover Photo (Mobile Only) */}
            <div className="lg:hidden">
              {renderCoverPhotoCard()}
            </div>

            {/* Right Column (Cover Photo Sidebar Card) */}
            <div className="hidden lg:block space-y-6">
              {renderCoverPhotoCard()}
            </div>
          </div>
        ) : (
          <div className="max-w-5xl mx-auto py-6">
            {renderAnalyticsTab()}
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

          {/* Zoom Controls */}
          <div className="absolute top-4 right-40 hidden md:flex items-center gap-1 bg-black/40 rounded-full p-1 z-50 border border-white/10">
            <button
              onClick={(e) => { e.stopPropagation(); handleZoomOut(); }}
              disabled={scale <= 1}
              aria-label="Zoom out"
              className="p-1.5 rounded-full text-white/70 hover:text-white hover:bg-white/10 transition-colors disabled:opacity-40"
            >
              <ZoomOut size={16} />
            </button>
            <span className="text-xs font-semibold text-white/90 w-10 text-center select-none">
              {Math.round(scale * 100)}%
            </span>
            <button
              onClick={(e) => { e.stopPropagation(); handleZoomIn(); }}
              disabled={scale >= 5}
              aria-label="Zoom in"
              className="p-1.5 rounded-full text-white/70 hover:text-white hover:bg-white/10 transition-colors disabled:opacity-40"
            >
              <ZoomIn size={16} />
            </button>
            {scale > 1 && (
              <button
                onClick={(e) => { e.stopPropagation(); handleResetZoom(); }}
                aria-label="Reset zoom"
                className="text-[10px] font-bold bg-white/20 hover:bg-white/30 text-white rounded-full px-2 py-0.5 transition-colors ml-1"
              >
                Reset
              </button>
            )}
          </div>

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

          {/* Make Featured Star Button */}
          <button
            className={`absolute top-4 right-28 p-2.5 rounded-full transition-colors z-50 bg-black/40 disabled:opacity-50 flex items-center justify-center ${
              gallery?.cover_photo?.uuid === selectedPhoto.uuid
                ? 'text-yellow-400 hover:text-yellow-500 hover:bg-yellow-500/10'
                : 'text-white/70 hover:text-white hover:bg-white/10'
            }`}
            onClick={(e) => handleMakeFeatured(selectedPhoto, e)}
            disabled={updateGalleryMutation.isPending}
            aria-label="Make featured"
            title={gallery?.cover_photo?.uuid === selectedPhoto.uuid ? "Featured Image" : "Make Featured Cover"}
          >
            <Star size={24} fill={gallery?.cover_photo?.uuid === selectedPhoto.uuid ? "currentColor" : "none"} />
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

          <div 
            className="relative max-w-full max-h-[85vh] flex flex-col items-center justify-center overflow-hidden" 
            onClick={(e) => e.stopPropagation()}
            onWheel={handleWheel}
            onDoubleClick={handleDoubleClick}
            onMouseDown={handleMouseDown}
            onMouseMove={handleMouseMove}
            onMouseUp={handleMouseUp}
            onMouseLeave={handleMouseUp}
            onTouchStart={handleTouchStart}
            onTouchMove={handleTouchMove}
            onTouchEnd={handleTouchEnd}
          >
            <img
              ref={imageRef}
              src={selectedPhoto.variants?.lg || selectedPhoto.cdn_url}
              alt={selectedPhoto.filename}
              className="max-w-full max-h-[80vh] object-contain rounded-lg shadow-2xl select-none pointer-events-none"
              style={{
                transform: `translate(${position.x}px, ${position.y}px) scale(${scale})`,
                transition: isZoomDragging ? 'none' : 'transform 0.15s ease-out',
              }}
            />
            <div className="mt-4 bg-black/60 backdrop-blur-md text-white text-xs px-4 py-2 rounded-full flex items-center gap-4 pointer-events-auto">
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

      {/* Cover Photo Selection Modal */}
      {isCoverSelectOpen && (
        <div className="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4" onClick={() => setIsCoverSelectOpen(false)}>
          <div className="bg-card border border-border rounded-xl p-6 max-w-2xl w-full shadow-2xl space-y-4 max-h-[85vh] flex flex-col" onClick={(e) => e.stopPropagation()}>
            <div className="flex items-center justify-between pb-2 border-b border-border shrink-0">
              <div>
                <h3 className="font-bold text-foreground text-lg">Select Cover Photo</h3>
                <p className="text-xs text-muted-foreground">Select an image from this gallery to feature as the cover</p>
              </div>
              <button
                onClick={() => setIsCoverSelectOpen(false)}
                className="p-1.5 rounded-lg hover:bg-secondary text-muted-foreground hover:text-foreground transition-colors"
              >
                <X size={18} />
              </button>
            </div>
            
            <div className="flex-1 overflow-y-auto min-h-[300px] max-h-[50vh] pr-1">
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                {photos.map((photo) => {
                  const isCurrentCover = gallery?.cover_photo?.uuid === photo.uuid;
                  return (
                    <button
                      key={photo.uuid}
                      type="button"
                      onClick={() => handleMakeFeatured(photo)}
                      disabled={updateGalleryMutation.isPending}
                      className={`group relative aspect-[3/2] bg-muted rounded-lg overflow-hidden transition-all border-2 ${
                        isCurrentCover ? 'border-primary ring-2 ring-primary/20' : 'border-transparent hover:border-muted-foreground'
                      }`}
                    >
                      <img
                        src={photo.variants?.sm || photo.cdn_url}
                        alt={photo.filename}
                        className="w-full h-full object-cover"
                      />
                      {isCurrentCover && (
                        <div className="absolute inset-0 bg-primary/10 flex items-center justify-center">
                          <span className="bg-primary text-primary-foreground text-[10px] font-bold px-2 py-0.5 rounded-full flex items-center gap-1 shadow">
                            <Star size={10} fill="currentColor" /> Cover
                          </span>
                        </div>
                      )}
                    </button>
                  );
                })}
              </div>
            </div>
            
            <div className="pt-2 border-t border-border flex justify-end shrink-0">
              <button
                type="button"
                onClick={() => setIsCoverSelectOpen(false)}
                className="py-2 px-4 bg-secondary text-foreground rounded-lg hover:bg-muted transition-colors font-semibold text-xs"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      )}
    </main>
  )
}
