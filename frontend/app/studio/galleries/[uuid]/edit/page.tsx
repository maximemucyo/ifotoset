'use client'

import { useState, useEffect } from 'react'
import { useParams, useRouter } from 'next/navigation'
import Link from 'next/link'
import { ArrowLeft, Save, Globe, Lock, User, Calendar, AlertTriangle } from 'lucide-react'
import { useGallery, useUpdateGalleryMutation } from '@/lib/queries/galleries'
import { ApiError } from '@/lib/apiClient'

export default function EditGallery() {
  const { uuid } = useParams<{ uuid: string }>()
  const router = useRouter()

  const { data, isLoading, error } = useGallery(uuid)
  const updateMutation = useUpdateGalleryMutation()

  const [form, setForm] = useState({
    title: '',
    client_name: '',
    visibility: 'private' as 'public' | 'private',
  })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [successMessage, setSuccessMessage] = useState('')

  const gallery = data?.data

  useEffect(() => {
    if (gallery) {
      setForm({
        title: gallery.title,
        client_name: gallery.client_name ?? '',
        visibility: gallery.visibility,
      })
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
      <div className="border-b border-border bg-card p-6 flex items-center gap-4">
        <Link
          href={`/studio/galleries/${uuid}`}
          className="p-2 rounded-lg hover:bg-secondary transition-colors text-muted-foreground hover:text-foreground"
        >
          <ArrowLeft size={20} />
        </Link>
        <div>
          <h1 className="text-3xl font-bold text-foreground">Edit Gallery</h1>
          <p className="text-muted-foreground mt-1">
            Editing: <span className="font-semibold text-foreground">{gallery.title}</span>
          </p>
        </div>
      </div>

      {/* Content */}
      <div className="p-6">
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
    </main>
  )
}
