'use client'

import { useState } from 'react'
import { Trash2, AlertTriangle, RotateCcw, Image, HardDrive, ChevronLeft, ChevronRight } from 'lucide-react'
import { formatBytes } from '@/lib/utils'
import {
  useTrash,
  useRestoreTrashMutation,
  usePurgeTrashMutation,
  useEmptyTrashMutation
} from '@/lib/queries/trash'
import { useCurrentUser } from '@/lib/queries/auth'
import { GalleryItem, PhotoItem } from '@/lib/queries/galleries'

export default function Trash() {
  const [activeTab, setActiveTab] = useState<'gallery' | 'photo'>('gallery')
  const [galleryPage, setGalleryPage] = useState(1)
  const [photoPage, setPhotoPage] = useState(1)
  const perPage = 8

  // Queries
  const { data: currentUser } = useCurrentUser()
  const { data: galleryData, isLoading: isLoadingGalleries } = useTrash<GalleryItem>('gallery', galleryPage, perPage)
  const { data: photoData, isLoading: isLoadingPhotos } = useTrash<PhotoItem>('photo', photoPage, perPage)

  // Mutations
  const restoreMutation = useRestoreTrashMutation()
  const purgeMutation = usePurgeTrashMutation()
  const emptyTrashMutation = useEmptyTrashMutation()

  // Modals state
  const [purgeTarget, setPurgeTarget] = useState<{ type: 'gallery' | 'photo'; uuid: string; title: string } | null>(null)
  const [showEmptyConfirm, setShowEmptyConfirm] = useState(false)

  const galleries = galleryData?.data || []
  const galleryMeta = galleryData?.meta

  const photos = photoData?.data || []
  const photoMeta = photoData?.meta

  const isBackgroundPending = restoreMutation.isPending || purgeMutation.isPending || emptyTrashMutation.isPending

  const trashBytes = currentUser?.user?.storage?.trash_bytes ?? 0

  const handleRestore = (type: 'gallery' | 'photo', uuid: string) => {
    restoreMutation.mutate({ type, uuid })
  }

  const handlePurgeConfirm = (type: 'gallery' | 'photo', uuid: string, title: string) => {
    setPurgeTarget({ type, uuid, title })
  }

  const executePurge = () => {
    if (!purgeTarget) return
    purgeMutation.mutate(
      { type: purgeTarget.type, uuid: purgeTarget.uuid },
      {
        onSuccess: () => setPurgeTarget(null)
      }
    )
  }

  const executeEmptyTrash = () => {
    emptyTrashMutation.mutate(undefined, {
      onSuccess: () => setShowEmptyConfirm(false)
    })
  }

  return (
    <main className="flex-1 min-h-screen bg-background">
      {/* Header */}
      <div className="border-b border-border bg-card p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold text-foreground">Trash</h1>
          <p className="text-muted-foreground mt-1">Review, restore, or permanently purge recently deleted items</p>
        </div>
        {(galleries.length > 0 || photos.length > 0) && (
          <button
            onClick={() => setShowEmptyConfirm(true)}
            disabled={isBackgroundPending}
            className="px-5 py-2.5 bg-destructive/10 text-destructive border border-destructive/20 rounded-lg hover:bg-destructive hover:text-white transition-colors flex items-center justify-center gap-2 font-semibold text-sm disabled:opacity-50"
          >
            <Trash2 size={16} />
            Empty Trash
          </button>
        )}
      </div>

      {/* Content */}
      <div className="p-6">
        {/* Background Status Alert */}
        {isBackgroundPending && (
          <div className="mb-6 bg-primary/5 border border-primary/20 rounded-lg p-4 flex items-center justify-between animate-pulse">
            <p className="text-sm font-medium text-primary">
              {emptyTrashMutation.isPending ? 'Emptying trash in background...' : 'Processing permanent deletion job...'}
            </p>
            <div className="w-5 h-5 border-2 border-primary/20 border-t-primary rounded-full animate-spin" />
          </div>
        )}

        {/* Trash storage info card */}
        {currentUser?.user && (
          <div className="mb-6 bg-card border border-border rounded-xl p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-sm hover:border-primary/30 transition-colors">
            <div className="flex items-start gap-4">
              <div className="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center text-primary shrink-0">
                <HardDrive size={24} />
              </div>
              <div>
                <h3 className="font-bold text-foreground text-base">Trash Storage Status</h3>
                {trashBytes > 0 ? (
                  <p className="text-sm text-muted-foreground mt-0.5">
                    Trash currently occupies <span className="font-semibold text-foreground">{formatBytes(trashBytes)}</span> of space.{' '}
                    <span className="text-accent font-medium">Emptying the trash will immediately free up {formatBytes(trashBytes)} of storage.</span>
                  </p>
                ) : (
                  <p className="text-sm text-muted-foreground mt-0.5">
                    Your trash is empty. No storage is currently being reserved by deleted items.
                  </p>
                )}
              </div>
            </div>
            {trashBytes > 0 && (
              <button
                onClick={() => setShowEmptyConfirm(true)}
                disabled={isBackgroundPending}
                className="px-4 py-2 bg-destructive/10 text-destructive border border-destructive/20 rounded-lg hover:bg-destructive hover:text-white transition-colors flex items-center justify-center gap-2 font-semibold text-xs disabled:opacity-50 shrink-0 self-start sm:self-center"
              >
                <Trash2 size={14} />
                Empty Trash
              </button>
            )}
          </div>
        )}

        {/* Tabs switcher */}
        <div className="flex gap-2 border-b border-border mb-6">
          <button
            onClick={() => setActiveTab('gallery')}
            className={`px-4 py-2 border-b-2 text-sm font-semibold transition-colors ${
              activeTab === 'gallery'
                ? 'border-primary text-primary'
                : 'border-transparent text-muted-foreground hover:text-foreground'
            }`}
          >
            Galleries ({galleryMeta?.total ?? 0})
          </button>
          <button
            onClick={() => setActiveTab('photo')}
            className={`px-4 py-2 border-b-2 text-sm font-semibold transition-colors ${
              activeTab === 'photo'
                ? 'border-primary text-primary'
                : 'border-transparent text-muted-foreground hover:text-foreground'
            }`}
          >
            Individual Photos ({photoMeta?.total ?? 0})
          </button>
        </div>

        {/* TAB 1: GALLERIES */}
        {activeTab === 'gallery' && (
          <>
            {isLoadingGalleries ? (
              <div className="flex flex-col items-center justify-center py-20 gap-4">
                <div className="w-12 h-12 rounded-full border-4 border-primary/20 border-t-primary animate-spin" />
                <p className="text-muted-foreground font-medium text-sm">Loading deleted galleries...</p>
              </div>
            ) : galleries.length === 0 ? (
              <div className="bg-card border border-border rounded-xl p-16 text-center shadow-sm">
                <Trash2 className="w-16 h-16 text-muted-foreground/30 mx-auto mb-4" />
                <h3 className="text-lg font-bold text-foreground mb-1">Gallery trash is empty</h3>
                <p className="text-muted-foreground text-sm max-w-sm mx-auto">
                  Galleries moved to the trash will appear here and be kept for 7 days before permanent removal.
                </p>
              </div>
            ) : (
              <div className="grid gap-4">
                {galleries.map((gallery) => (
                  <div
                    key={gallery.uuid}
                    className="bg-card border border-border rounded-xl p-5 hover:border-primary/40 transition-colors flex flex-col sm:flex-row sm:items-center justify-between gap-4"
                  >
                    <div className="flex-1">
                      <div className="flex items-center gap-3 flex-wrap">
                        <h3 className="text-lg font-bold text-foreground">{gallery.title}</h3>
                        <span className="text-xs bg-destructive/10 text-destructive border border-destructive/20 font-semibold px-2 py-0.5 rounded-full">
                          Purges in {gallery.days_remaining} {gallery.days_remaining === 1 ? 'day' : 'days'}
                        </span>
                      </div>
                      <p className="text-xs text-muted-foreground mt-1">
                        Deleted: {gallery.deleted_at ? new Date(gallery.deleted_at).toLocaleString() : 'N/A'}
                      </p>

                      <div className="flex flex-wrap gap-6 mt-3 text-sm text-muted-foreground">
                        <div>
                          <span>Photos: </span>
                          <strong className="text-foreground">{gallery.stats?.photo_count ?? 0}</strong>
                        </div>
                        <div>
                          <span>Storage size: </span>
                          <strong className="text-foreground">{formatBytes(gallery.stats?.total_bytes ?? 0)}</strong>
                        </div>
                      </div>
                    </div>

                    <div className="flex items-center gap-2 self-end sm:self-center">
                      <button
                        onClick={() => handleRestore('gallery', gallery.uuid)}
                        disabled={isBackgroundPending}
                        className="p-2.5 bg-secondary text-foreground rounded-lg hover:bg-muted transition-colors font-semibold text-sm flex items-center gap-1.5 disabled:opacity-50"
                        title="Restore gallery"
                      >
                        <RotateCcw size={16} />
                        <span className="hidden md:inline">Restore</span>
                      </button>
                      <button
                        onClick={() => handlePurgeConfirm('gallery', gallery.uuid, gallery.title)}
                        disabled={isBackgroundPending}
                        className="p-2.5 bg-destructive/10 text-destructive rounded-lg hover:bg-destructive/20 transition-colors font-semibold text-sm flex items-center gap-1.5 disabled:opacity-50"
                        title="Delete permanently"
                      >
                        <Trash2 size={16} />
                        <span className="hidden md:inline">Delete Forever</span>
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            )}

            {/* Pagination galleries */}
            {galleryMeta && galleryMeta.last_page > 1 && (
              <div className="flex items-center justify-between border-t border-border mt-8 pt-6">
                <p className="text-sm text-muted-foreground">
                  Showing page <span className="font-semibold text-foreground">{galleryPage}</span> of <span className="font-semibold text-foreground">{galleryMeta.last_page}</span>
                </p>
                <div className="flex gap-2">
                  <button
                    onClick={() => setGalleryPage((prev) => Math.max(1, prev - 1))}
                    disabled={galleryPage === 1}
                    className="p-2 bg-card border border-border rounded-lg text-foreground hover:border-primary disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                  >
                    <ChevronLeft size={20} />
                  </button>
                  <button
                    onClick={() => setGalleryPage((prev) => Math.min(galleryMeta.last_page, prev + 1))}
                    disabled={galleryPage === galleryMeta.last_page}
                    className="p-2 bg-card border border-border rounded-lg text-foreground hover:border-primary disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                  >
                    <ChevronRight size={20} />
                  </button>
                </div>
              </div>
            )}
          </>
        )}

        {/* TAB 2: PHOTOS */}
        {activeTab === 'photo' && (
          <>
            {isLoadingPhotos ? (
              <div className="flex flex-col items-center justify-center py-20 gap-4">
                <div className="w-12 h-12 rounded-full border-4 border-primary/20 border-t-primary animate-spin" />
                <p className="text-muted-foreground font-medium text-sm">Loading deleted photos...</p>
              </div>
            ) : photos.length === 0 ? (
              <div className="bg-card border border-border rounded-xl p-16 text-center shadow-sm">
                <Image className="w-16 h-16 text-muted-foreground/30 mx-auto mb-4" />
                <h3 className="text-lg font-bold text-foreground mb-1">Photo trash is empty</h3>
                <p className="text-muted-foreground text-sm max-w-sm mx-auto">
                  Photos deleted individually from galleries will appear here and be kept for 7 days.
                </p>
              </div>
            ) : (
              <div className="grid gap-4">
                {photos.map((photo) => (
                  <div
                    key={photo.uuid}
                    className="bg-card border border-border rounded-xl p-4 hover:border-primary/40 transition-colors flex items-center justify-between gap-4"
                  >
                    <div className="flex items-center gap-4 min-w-0">
                      <div className="w-14 h-14 bg-muted rounded-lg overflow-hidden shrink-0 border border-border">
                        <img
                          src={photo.variants?.xs || photo.cdn_url}
                          alt={photo.filename}
                          className="w-full h-full object-cover"
                          loading="lazy"
                        />
                      </div>
                      <div className="min-w-0">
                        <h4 className="font-bold text-foreground text-sm truncate">{photo.filename}</h4>
                        <p className="text-xs text-muted-foreground truncate mt-0.5">
                          Size: {formatBytes(photo.size)}
                        </p>
                        <p className="text-xs text-muted-foreground truncate">
                          Deleted: {photo.deleted_at ? new Date(photo.deleted_at).toLocaleDateString() : 'N/A'}
                        </p>
                      </div>
                    </div>

                    <div className="flex items-center gap-2 shrink-0">
                      <span className="hidden sm:inline-block text-xs bg-destructive/10 text-destructive border border-destructive/20 font-semibold px-2.5 py-0.5 rounded-full mr-2">
                        {photo.days_remaining} {photo.days_remaining === 1 ? 'day' : 'days'}
                      </span>
                      <button
                        onClick={() => handleRestore('photo', photo.uuid)}
                        disabled={isBackgroundPending}
                        className="p-2 bg-secondary text-foreground rounded-lg hover:bg-muted transition-colors font-semibold text-sm"
                        title="Restore photo"
                      >
                        <RotateCcw size={16} />
                      </button>
                      <button
                        onClick={() => handlePurgeConfirm('photo', photo.uuid, photo.filename)}
                        disabled={isBackgroundPending}
                        className="p-2 bg-destructive/10 text-destructive rounded-lg hover:bg-destructive/20 transition-colors font-semibold text-sm"
                        title="Delete permanently"
                      >
                        <Trash2 size={16} />
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            )}

            {/* Pagination photos */}
            {photoMeta && photoMeta.last_page > 1 && (
              <div className="flex items-center justify-between border-t border-border mt-8 pt-6">
                <p className="text-sm text-muted-foreground">
                  Showing page <span className="font-semibold text-foreground">{photoPage}</span> of <span className="font-semibold text-foreground">{photoMeta.last_page}</span>
                </p>
                <div className="flex gap-2">
                  <button
                    onClick={() => setPhotoPage((prev) => Math.max(1, prev - 1))}
                    disabled={photoPage === 1}
                    className="p-2 bg-card border border-border rounded-lg text-foreground hover:border-primary disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                  >
                    <ChevronLeft size={20} />
                  </button>
                  <button
                    onClick={() => setPhotoPage((prev) => Math.min(photoMeta.last_page, prev + 1))}
                    disabled={photoPage === photoMeta.last_page}
                    className="p-2 bg-card border border-border rounded-lg text-foreground hover:border-primary disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                  >
                    <ChevronRight size={20} />
                  </button>
                </div>
              </div>
            )}
          </>
        )}
      </div>

      {/* Delete Permanently Confirmation Modal */}
      {purgeTarget && (
        <div className="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
          <div className="bg-card border border-border rounded-xl p-6 max-w-sm w-full shadow-2xl">
            <div className="flex items-center gap-3 mb-4">
              <div className="w-10 h-10 rounded-full bg-destructive/10 flex items-center justify-center shrink-0">
                <AlertTriangle size={20} className="text-destructive" />
              </div>
              <div>
                <h3 className="font-bold text-foreground">Delete Permanently?</h3>
                <p className="text-sm text-muted-foreground">This action is irreversible.</p>
              </div>
            </div>
            <p className="text-sm text-foreground mb-6">
              Are you sure you want to permanently delete <strong>"{purgeTarget.title}"</strong>? All associated original files and responsive variants in storage will be deleted immediately.
            </p>
            <div className="flex gap-3">
              <button
                onClick={() => setPurgeTarget(null)}
                disabled={purgeMutation.isPending}
                className="flex-1 py-2.5 px-4 bg-secondary text-foreground rounded-lg hover:bg-muted transition-colors font-semibold text-sm"
              >
                Cancel
              </button>
              <button
                onClick={executePurge}
                disabled={purgeMutation.isPending}
                className="flex-1 py-2.5 px-4 bg-destructive text-white rounded-lg hover:bg-destructive/90 transition-colors font-semibold text-sm disabled:opacity-60 flex items-center justify-center gap-2"
              >
                {purgeMutation.isPending ? 'Purging...' : 'Delete Forever'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Empty Trash Confirmation Modal */}
      {showEmptyConfirm && (
        <div className="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
          <div className="bg-card border border-border rounded-xl p-6 max-w-sm w-full shadow-2xl">
            <div className="flex items-center gap-3 mb-4">
              <div className="w-10 h-10 rounded-full bg-destructive/10 flex items-center justify-center shrink-0">
                <AlertTriangle size={20} className="text-destructive" />
              </div>
              <div>
                <h3 className="font-bold text-foreground">Empty Entire Trash?</h3>
                <p className="text-sm text-muted-foreground">This will clear everything.</p>
              </div>
            </div>
            <p className="text-sm text-foreground mb-6">
              Are you sure you want to empty the trash? All galleries, photos, and files will be queued for permanent removal.
            </p>
            <div className="flex gap-3">
              <button
                onClick={() => setShowEmptyConfirm(false)}
                disabled={emptyTrashMutation.isPending}
                className="flex-1 py-2.5 px-4 bg-secondary text-foreground rounded-lg hover:bg-muted transition-colors font-semibold text-sm"
              >
                Cancel
              </button>
              <button
                onClick={executeEmptyTrash}
                disabled={emptyTrashMutation.isPending}
                className="flex-1 py-2.5 px-4 bg-destructive text-white rounded-lg hover:bg-destructive/90 transition-colors font-semibold text-sm disabled:opacity-60 flex items-center justify-center gap-2"
              >
                {emptyTrashMutation.isPending ? 'Emptying...' : 'Empty Trash'}
              </button>
            </div>
          </div>
        </div>
      )}
    </main>
  )
}
