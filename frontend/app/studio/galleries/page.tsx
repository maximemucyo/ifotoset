'use client'

import { Plus, Search, Filter, ChevronLeft, ChevronRight, Trash2, Edit, Share2, Check, AlertTriangle, MoreHorizontal } from 'lucide-react'
import Link from 'next/link'
import { useState } from 'react'
import { useGalleries, useDeleteGalleryMutation } from '@/lib/queries/galleries'
import { formatBytes } from '@/lib/utils'

export default function Galleries() {
  const [searchTerm, setSearchTerm] = useState('')
  const [page, setPage] = useState(1)
  const perPage = 5

  const { data, isLoading } = useGalleries(page, perPage)
  const deleteMutation = useDeleteGalleryMutation()

  const [copiedUuid, setCopiedUuid] = useState<string | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<{ uuid: string; title: string } | null>(null)
  const [openMenuUuid, setOpenMenuUuid] = useState<string | null>(null)

  const galleries = data?.data || []
  const meta = data?.meta

  const filteredGalleries = galleries.filter((gallery) =>
    gallery.title.toLowerCase().includes(searchTerm.toLowerCase()) ||
    (gallery.client_name && gallery.client_name.toLowerCase().includes(searchTerm.toLowerCase()))
  )

  const handlePrevPage = () => {
    if (page > 1) setPage((prev) => prev - 1)
  }

  const handleNextPage = () => {
    if (meta && page < meta.last_page) setPage((prev) => prev + 1)
  }

  const handleShare = (uuid: string, slug: string) => {
    const url = `${window.location.origin}/g/${slug}`
    navigator.clipboard.writeText(url).catch(() => {})
    setCopiedUuid(uuid)
    setTimeout(() => setCopiedUuid(null), 2000)
    setOpenMenuUuid(null)
  }

  const handleDelete = () => {
    if (!deleteTarget) return
    deleteMutation.mutate(deleteTarget.uuid, {
      onSuccess: () => setDeleteTarget(null),
    })
  }

  return (
    <main className="flex-1 min-h-screen bg-background">
      {/* Header */}
      <div className="border-b border-border bg-card p-6 flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold text-foreground">Galleries</h1>
          <p className="text-muted-foreground mt-1">Manage all your photo galleries and client deliveries</p>
        </div>
        <Link href="/studio/galleries/new" className="px-6 py-3 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-colors flex items-center gap-2 font-semibold">
          <Plus size={20} />
          New Gallery
        </Link>
      </div>

      {/* Content */}
      <div className="p-6">
        {/* Search and Filter */}
        <div className="flex flex-col sm:flex-row gap-4 mb-6">
          <div className="flex-1 relative">
            <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground" size={20} />
            <input
              type="text"
              placeholder="Search galleries..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className="w-full pl-10 pr-4 py-2 bg-card border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:border-primary"
            />
          </div>
          <button className="px-4 py-2 bg-card border border-border rounded-lg hover:border-primary transition-colors flex items-center gap-2 text-foreground">
            <Filter size={20} />
            Filter
          </button>
        </div>

        {/* Loading State */}
        {isLoading ? (
          <div className="flex flex-col items-center justify-center py-20 gap-4">
            <div className="w-12 h-12 rounded-full border-4 border-primary/20 border-t-primary animate-spin" />
            <p className="text-muted-foreground font-medium text-sm">Loading galleries...</p>
          </div>
        ) : filteredGalleries.length === 0 ? (
          <div className="bg-card border border-border rounded-lg p-12 text-center">
            <p className="text-muted-foreground text-lg">No galleries found</p>
            <p className="text-sm text-muted-foreground/75 mt-1">Try modifying your search or create a new gallery.</p>
          </div>
        ) : (
          <div className="grid gap-6">
            {filteredGalleries.map((gallery) => (
              <div key={gallery.uuid} className="bg-card border border-border rounded-lg p-6 hover:border-primary transition-colors">
                <div className="flex items-start justify-between">
                  <div className="flex-1">
                    <div className="flex items-center gap-3">
                      <h3 className="text-lg font-bold text-foreground">{gallery.title}</h3>
                      <span className={`text-xs font-semibold px-2 py-0.5 rounded-full ${
                        gallery.visibility === 'public'
                          ? 'bg-green-500/10 text-green-500 border border-green-500/20'
                          : 'bg-yellow-500/10 text-yellow-500 border border-yellow-500/20'
                      }`}>
                        {gallery.visibility}
                      </span>
                    </div>
                    <p className="text-sm text-muted-foreground mt-1">Created {new Date(gallery.created_at).toLocaleDateString()}</p>

                    <div className="flex flex-wrap gap-6 mt-4">
                      <div>
                        <p className="text-xs text-muted-foreground">Images</p>
                        <p className="text-lg font-semibold text-foreground">{gallery.stats.photo_count}</p>
                      </div>
                      <div>
                        <p className="text-xs text-muted-foreground">Client</p>
                        <p className="text-lg font-semibold text-foreground">{gallery.client_name || 'N/A'}</p>
                      </div>
                      <div>
                        <p className="text-xs text-muted-foreground">Size</p>
                        <p className="text-lg font-semibold text-foreground">{formatBytes(gallery.stats.total_bytes)}</p>
                      </div>
                    </div>
                  </div>

                  {/* Context Menu */}
                  <div className="relative">
                    <button
                      onClick={() => setOpenMenuUuid(openMenuUuid === gallery.uuid ? null : gallery.uuid)}
                      className="p-2 hover:bg-secondary rounded-lg text-muted-foreground hover:text-foreground transition-colors"
                    >
                      <MoreHorizontal size={20} />
                    </button>
                    {openMenuUuid === gallery.uuid && (
                      <>
                        <div className="fixed inset-0 z-10" onClick={() => setOpenMenuUuid(null)} />
                        <div className="absolute right-0 top-10 z-20 bg-card border border-border rounded-lg shadow-lg py-1 min-w-[140px]">
                          <Link
                            href={`/studio/galleries/${gallery.uuid}/edit`}
                            onClick={() => setOpenMenuUuid(null)}
                            className="flex items-center gap-2 px-4 py-2 text-sm text-foreground hover:bg-secondary transition-colors"
                          >
                            <Edit size={14} />
                            Edit
                          </Link>
                          <button
                            onClick={() => handleShare(gallery.uuid, gallery.slug)}
                            className="w-full flex items-center gap-2 px-4 py-2 text-sm text-foreground hover:bg-secondary transition-colors"
                          >
                            {copiedUuid === gallery.uuid ? <Check size={14} className="text-green-500" /> : <Share2 size={14} />}
                            {copiedUuid === gallery.uuid ? 'Copied!' : 'Share Link'}
                          </button>
                          <button
                            onClick={() => { setDeleteTarget({ uuid: gallery.uuid, title: gallery.title }); setOpenMenuUuid(null) }}
                            className="w-full flex items-center gap-2 px-4 py-2 text-sm text-destructive hover:bg-destructive/10 transition-colors"
                          >
                            <Trash2 size={14} />
                            Delete
                          </button>
                        </div>
                      </>
                    )}
                  </div>
                </div>

                <div className="flex gap-3 mt-6 pt-6 border-t border-border">
                  <Link href={`/studio/galleries/${gallery.uuid}`} className="flex-1 py-2 px-4 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-colors text-center font-semibold text-sm">
                    View Gallery
                  </Link>
                  <button
                    onClick={() => handleShare(gallery.uuid, gallery.slug)}
                    className="flex-1 py-2 px-4 bg-secondary text-foreground rounded-lg hover:bg-muted transition-colors font-semibold text-sm flex items-center justify-center gap-2"
                  >
                    {copiedUuid === gallery.uuid ? <Check size={14} className="text-green-500" /> : <Share2 size={14} />}
                    {copiedUuid === gallery.uuid ? 'Copied!' : 'Share Link'}
                  </button>
                  <Link
                    href={`/studio/galleries/${gallery.uuid}/edit`}
                    className="flex-1 py-2 px-4 bg-secondary text-foreground rounded-lg hover:bg-muted transition-colors font-semibold text-sm text-center flex items-center justify-center gap-2"
                  >
                    <Edit size={14} />
                    Edit
                  </Link>
                </div>
              </div>
            ))}
          </div>
        )}

        {/* Pagination Controls */}
        {meta && meta.last_page > 1 && (
          <div className="flex items-center justify-between border-t border-border mt-8 pt-6">
            <p className="text-sm text-muted-foreground">
              Showing page <span className="font-semibold text-foreground">{page}</span> of <span className="font-semibold text-foreground">{meta.last_page}</span>
            </p>
            <div className="flex gap-2">
              <button
                onClick={handlePrevPage}
                disabled={page === 1}
                className="p-2 bg-card border border-border rounded-lg text-foreground hover:border-primary disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                <ChevronLeft size={20} />
              </button>
              <button
                onClick={handleNextPage}
                disabled={page === meta.last_page}
                className="p-2 bg-card border border-border rounded-lg text-foreground hover:border-primary disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                <ChevronRight size={20} />
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Delete Confirmation Modal */}
      {deleteTarget && (
        <div className="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
          <div className="bg-card border border-border rounded-xl p-6 max-w-sm w-full shadow-2xl">
            <div className="flex items-center gap-3 mb-4">
              <div className="w-10 h-10 rounded-full bg-destructive/10 flex items-center justify-center shrink-0">
                <AlertTriangle size={20} className="text-destructive" />
              </div>
              <div>
                <h3 className="font-bold text-foreground">Delete Gallery</h3>
                <p className="text-sm text-muted-foreground">This action cannot be undone.</p>
              </div>
            </div>
            <p className="text-sm text-foreground mb-6">
              Are you sure you want to delete <strong>&quot;{deleteTarget.title}&quot;</strong>? All photos and data will be permanently removed.
            </p>
            <div className="flex gap-3">
              <button
                onClick={() => setDeleteTarget(null)}
                className="flex-1 py-2.5 px-4 bg-secondary text-foreground rounded-lg hover:bg-muted transition-colors font-semibold text-sm"
              >
                Cancel
              </button>
              <button
                onClick={handleDelete}
                disabled={deleteMutation.isPending}
                className="flex-1 py-2.5 px-4 bg-destructive text-white rounded-lg hover:bg-destructive/90 transition-colors font-semibold text-sm disabled:opacity-60"
              >
                {deleteMutation.isPending ? 'Deleting...' : 'Delete Gallery'}
              </button>
            </div>
          </div>
        </div>
      )}
    </main>
  )
}
