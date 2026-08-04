'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import Link from 'next/link'
import { ArrowLeft, ImagePlus, Lock, Globe, Calendar, User, Tag, Mail, AlertTriangle } from 'lucide-react'
import { useCreateGalleryMutation } from '@/lib/queries/galleries'
import { ApiError } from '@/lib/apiClient'
import { TagInput } from '@/components/tag-input'

function slugify(text: string): string {
  return text
    .toLowerCase()
    .trim()
    .replace(/[^\w\s-]/g, '')
    .replace(/[\s_-]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

export default function NewGallery() {
  const router = useRouter()
  const createMutation = useCreateGalleryMutation()

  const [form, setForm] = useState({
    title: '',
    slug: '',
    client_name: '',
    event_date: '',
    visibility: 'private' as 'public' | 'private',
    access_method: 'password' as 'password' | 'invite',
    password: '',
    password_hint: '',
    invite_emails: [] as string[],
  })

  const [slugManuallyEdited, setSlugManuallyEdited] = useState(false)
  const [errors, setErrors] = useState<Record<string, string>>({})

  const handleTitleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const title = e.target.value
    setForm((prev) => ({
      ...prev,
      title,
      slug: slugManuallyEdited ? prev.slug : slugify(title),
    }))
  }

  const handleSlugChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setSlugManuallyEdited(true)
    setForm((prev) => ({ ...prev, slug: slugify(e.target.value) }))
  }

  const handleVisibilityChange = (visibility: 'public' | 'private') => {
    setForm((prev) => ({
      ...prev,
      visibility,
      password: '',
      password_hint: '',
      invite_emails: [],
      access_method: 'password', // reset access method
    }))
    setErrors({})
  }

  const validate = (): boolean => {
    const newErrors: Record<string, string> = {}
    if (!form.title.trim()) newErrors.title = 'Title is required.'
    if (!form.slug.trim()) newErrors.slug = 'Slug is required.'
    
    if (form.visibility === 'private') {
      if (form.access_method === 'password') {
        if (!form.password) {
          newErrors.password = 'Password is required for password-protected galleries.'
        } else if (form.password.length < 6) {
          newErrors.password = 'Password must be at least 6 characters.'
        }
      } else if (form.access_method === 'invite') {
        if (form.invite_emails.length === 0) {
          newErrors.invite_emails = 'Please add at least one client email address.'
        }
      }
    }
    
    setErrors(newErrors)
    return Object.keys(newErrors).length === 0
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!validate()) return

    const payload: Parameters<typeof createMutation.mutate>[0] = {
      title: form.title,
      slug: form.slug,
      client_name: form.client_name || undefined,
      event_date: form.event_date || undefined,
      visibility: form.visibility,
    }

    if (form.visibility === 'private') {
      payload.access_method = form.access_method
      if (form.access_method === 'password') {
        payload.password = form.password
        payload.password_hint = form.password_hint || undefined
      } else if (form.access_method === 'invite') {
        payload.invite_emails = form.invite_emails
      }
    }

    createMutation.mutate(payload, {
      onSuccess: (data) => {
        router.push(`/studio/galleries/${data.data.uuid}`)
      },
      onError: (err) => {
        if (err instanceof ApiError && err.validationErrors) {
          const mapped: Record<string, string> = {}
          Object.entries(err.validationErrors).forEach(([field, messages]) => {
            mapped[field] = messages[0]
          })
          setErrors(mapped)
        }
      },
    })
  }

  return (
    <main className="flex-1 min-h-screen bg-background">
      {/* Header */}
      <div className="border-b border-border bg-card p-6 flex items-center gap-4">
        <Link
          href="/studio/galleries"
          className="p-2 rounded-lg hover:bg-secondary transition-colors text-muted-foreground hover:text-foreground"
        >
          <ArrowLeft size={20} />
        </Link>
        <div>
          <h1 className="text-3xl font-bold text-foreground">New Gallery</h1>
          <p className="text-muted-foreground mt-1">Create a new photo gallery for your client</p>
        </div>
      </div>

      {/* Content Container */}
      <div className="p-6">
        <div className="max-w-5xl mx-auto">
          <form onSubmit={handleSubmit} className="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            
            {/* Left Column: Gallery Details */}
            <div className="lg:col-span-2 space-y-6">
              <div className="bg-card border border-border rounded-lg p-6">
                <div className="flex items-center gap-3 mb-6">
                  <div className="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center">
                    <ImagePlus size={20} className="text-primary" />
                  </div>
                  <h2 className="text-xl font-bold text-foreground">Gallery Details</h2>
                </div>

                <div className="space-y-4">
                  {/* Title */}
                  <div>
                    <label className="block text-sm font-semibold text-foreground mb-2">
                      Gallery Title <span className="text-destructive">*</span>
                    </label>
                    <input
                      id="gallery-title"
                      type="text"
                      value={form.title}
                      onChange={handleTitleChange}
                      placeholder="e.g. Johnson Wedding 2026"
                      className={`w-full px-4 py-2.5 bg-background border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:border-primary transition-colors ${
                        errors.title ? 'border-destructive' : 'border-border'
                      }`}
                    />
                    {errors.title && <p className="text-destructive text-xs mt-1">{errors.title}</p>}
                  </div>

                  {/* Slug */}
                  <div>
                    <label className="block text-sm font-semibold text-foreground mb-2">
                      URL Slug <span className="text-destructive">*</span>
                    </label>
                    <div className="flex items-center bg-background border border-border rounded-lg focus-within:border-primary transition-colors overflow-hidden">
                      <span className="pl-4 pr-2 text-muted-foreground text-sm select-none whitespace-nowrap">
                        gallery/
                      </span>
                      <input
                        id="gallery-slug"
                        type="text"
                        value={form.slug}
                        onChange={handleSlugChange}
                        placeholder="johnson-wedding-2026"
                        className="flex-1 pr-4 py-2.5 bg-transparent text-foreground placeholder-muted-foreground focus:outline-none"
                      />
                    </div>
                    {errors.slug && <p className="text-destructive text-xs mt-1">{errors.slug}</p>}
                    <p className="text-xs text-muted-foreground mt-1">Auto-generated from title. Edit to customize.</p>
                  </div>

                  {/* Client Name */}
                  <div>
                    <label className="block text-sm font-semibold text-foreground mb-2 flex items-center gap-1.5">
                      <User size={14} />
                      Client Name
                    </label>
                    <input
                      id="gallery-client"
                      type="text"
                      value={form.client_name}
                      onChange={(e) => setForm((p) => ({ ...p, client_name: e.target.value }))}
                      placeholder="e.g. Alice & Bob Johnson"
                      className="w-full px-4 py-2.5 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:border-primary transition-colors"
                    />
                  </div>

                  {/* Event Date */}
                  <div>
                    <label className="block text-sm font-semibold text-foreground mb-2 flex items-center gap-1.5">
                      <Calendar size={14} />
                      Event Date
                    </label>
                    <input
                      id="gallery-event-date"
                      type="date"
                      value={form.event_date}
                      onChange={(e) => setForm((p) => ({ ...p, event_date: e.target.value }))}
                      className="w-full px-4 py-2.5 bg-background border border-border rounded-lg text-foreground focus:outline-none focus:border-primary transition-colors"
                    />
                  </div>
                </div>
              </div>
            </div>

            {/* Right Column: Settings & Visibility (Sticky) */}
            <div className="lg:col-span-1 lg:sticky lg:top-6 space-y-6">
              
              {/* Visibility Card */}
              <div className="bg-card border border-border rounded-lg p-6">
                <div className="flex items-center gap-3 mb-6">
                  <div className="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center">
                    <Globe size={20} className="text-primary" />
                  </div>
                  <h2 className="text-lg font-bold text-foreground">Visibility</h2>
                </div>

                <div className="grid grid-cols-1 gap-3">
                  {/* Private Button */}
                  <button
                    type="button"
                    id="visibility-private"
                    onClick={() => handleVisibilityChange('private')}
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

                  {/* Public Button */}
                  <button
                    type="button"
                    id="visibility-public"
                    onClick={() => handleVisibilityChange('public')}
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

              {/* Access Method Settings (Shown only for Private) */}
              {form.visibility === 'private' && (
                <div className="bg-card border border-border rounded-lg p-6 space-y-6">
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center">
                      <Lock size={20} className="text-primary" />
                    </div>
                    <div>
                      <h2 className="text-lg font-bold text-foreground">Access Control</h2>
                      <p className="text-xs text-muted-foreground">Secure your private gallery.</p>
                    </div>
                  </div>

                  {/* Selector Group */}
                  <div className="grid grid-cols-2 gap-2 p-1 bg-secondary rounded-lg">
                    <button
                      type="button"
                      onClick={() => setForm(p => ({ ...p, access_method: 'password' }))}
                      className={`py-2 text-xs font-semibold rounded-md transition-colors flex items-center justify-center gap-1.5 ${
                        form.access_method === 'password'
                          ? 'bg-card text-foreground shadow-sm'
                          : 'text-muted-foreground hover:text-foreground'
                      }`}
                    >
                      <Lock size={12} />
                      Password
                    </button>
                    <button
                      type="button"
                      onClick={() => setForm(p => ({ ...p, access_method: 'invite' }))}
                      className={`py-2 text-xs font-semibold rounded-md transition-colors flex items-center justify-center gap-1.5 ${
                        form.access_method === 'invite'
                          ? 'bg-card text-foreground shadow-sm'
                          : 'text-muted-foreground hover:text-foreground'
                      }`}
                    >
                      <Mail size={12} />
                      Invite List
                    </button>
                  </div>

                  {/* Password Protection Fields */}
                  {form.access_method === 'password' && (
                    <div className="space-y-4 pt-2 border-t border-border">
                      <div>
                        <label className="block text-sm font-semibold text-foreground mb-2">Password <span className="text-destructive">*</span></label>
                        <input
                          id="gallery-password"
                          type="password"
                          value={form.password}
                          onChange={(e) => setForm((p) => ({ ...p, password: e.target.value }))}
                          placeholder="Minimum 6 characters"
                          className={`w-full px-4 py-2.5 bg-background border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:border-primary transition-colors ${
                            errors.password ? 'border-destructive' : 'border-border'
                          }`}
                        />
                        {errors.password && <p className="text-destructive text-xs mt-1">{errors.password}</p>}
                      </div>
                      <div>
                        <label className="block text-sm font-semibold text-foreground mb-2 flex items-center gap-1.5">
                          <Tag size={14} />
                          Password Hint
                          <span className="font-normal text-muted-foreground">(optional)</span>
                        </label>
                        <input
                          id="gallery-password-hint"
                          type="text"
                          value={form.password_hint}
                          onChange={(e) => setForm((p) => ({ ...p, password_hint: e.target.value }))}
                          placeholder="e.g. Your wedding date"
                          className="w-full px-4 py-2.5 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:border-primary transition-colors"
                        />
                      </div>
                    </div>
                  )}

                  {/* Email Invitations Tag Input */}
                  {form.access_method === 'invite' && (
                    <div className="space-y-4 pt-2 border-t border-border">
                      <div>
                        <label className="block text-sm font-semibold text-foreground mb-2">Invite Clients <span className="text-destructive">*</span></label>
                        <TagInput
                          emails={form.invite_emails}
                          onChange={(emails) => setForm(p => ({ ...p, invite_emails: emails }))}
                          placeholder="Enter email and press Enter..."
                        />
                        {errors.invite_emails && <p className="text-destructive text-xs mt-1">{errors.invite_emails}</p>}
                        <p className="text-[11px] text-muted-foreground mt-1">Unique access links will be sent to these emails automatically.</p>
                      </div>
                    </div>
                  )}
                </div>
              )}

              {/* Action Actions Card */}
              <div className="bg-card border border-border rounded-lg p-6 space-y-4">
                {createMutation.isError && !(createMutation.error instanceof ApiError && createMutation.error.validationErrors) && (
                  <div className="p-4 bg-destructive/10 border border-destructive/30 rounded-lg">
                    <p className="text-destructive text-sm font-medium">
                      {createMutation.error instanceof Error ? createMutation.error.message : 'Failed to create gallery.'}
                    </p>
                  </div>
                )}

                <div className="flex flex-col gap-3">
                  <button
                    type="submit"
                    id="create-gallery-btn"
                    disabled={createMutation.isPending}
                    className="w-full py-3 px-6 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-colors font-semibold disabled:opacity-60 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                  >
                    {createMutation.isPending ? (
                      <>
                        <div className="w-4 h-4 rounded-full border-2 border-primary-foreground/30 border-t-primary-foreground animate-spin" />
                        Creating...
                      </>
                    ) : (
                      <>
                        <ImagePlus size={18} />
                        Create Gallery
                      </>
                    )}
                  </button>
                  <Link
                    href="/studio/galleries"
                    className="w-full py-3 px-6 bg-secondary text-foreground rounded-lg hover:bg-muted transition-colors font-semibold text-center text-sm"
                  >
                    Cancel
                  </Link>
                </div>
              </div>

            </div>

          </form>
        </div>
      </div>
    </main>
  )
}
