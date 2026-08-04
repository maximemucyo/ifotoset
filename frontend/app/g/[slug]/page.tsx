'use client'

import { useState, useEffect } from 'react'
import { useParams, useSearchParams } from 'next/navigation'
import Link from 'next/link'
import {
  Globe, Lock, Mail, Eye, Download, Image as ImageIcon,
  AlertTriangle, LockKeyhole, Calendar, User, X
} from 'lucide-react'
import { getPublicGallery, unlockPublicGallery, GalleryItem, PhotoItem } from '@/lib/queries/galleries'
import { ApiError } from '@/lib/apiClient'
import { formatBytes } from '@/lib/utils'

export default function PublicGalleryView() {
  const { slug } = useParams<{ slug: string }>()
  const searchParams = useSearchParams()
  const inviteToken = searchParams.get('invite')

  const [gallery, setGallery] = useState<GalleryItem | null>(null)
  const [loading, setLoading] = useState(true)
  const [errorState, setErrorState] = useState<{
    code: string
    message: string
    requiresPassword?: boolean
    requiresInvitation?: boolean
  } | null>(null)

  // Password unlock state
  const [password, setPassword] = useState('')
  const [unlockError, setUnlockError] = useState<string | null>(null)
  const [unlocking, setUnlocking] = useState(false)

  // Lightbox state
  const [selectedPhoto, setSelectedPhoto] = useState<PhotoItem | null>(null)

  const fetchGallery = async () => {
    setLoading(true)
    setErrorState(null)
    try {
      const galleryToken = sessionStorage.getItem(`gallery_token_${slug}`)
      const res = await getPublicGallery(slug, inviteToken, galleryToken)
      setGallery(res.data)
    } catch (err) {
      if (err instanceof ApiError) {
        setErrorState({
          code: err.code,
          message: err.message,
          requiresPassword: err.code === 'PASSWORD_REQUIRED',
          requiresInvitation: err.code === 'INVITATION_REQUIRED',
        })
      } else {
        setErrorState({
          code: 'FETCH_ERROR',
          message: err instanceof Error ? err.message : 'Failed to load gallery.',
        })
      }
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    if (slug) {
      fetchGallery()
    }
  }, [slug, inviteToken])

  const handleUnlockSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!password.trim()) return

    setUnlocking(true)
    setUnlockError(null)
    try {
      const res = await unlockPublicGallery(slug, password)
      sessionStorage.setItem(`gallery_token_${slug}`, res.token)
      setPassword('')
      fetchGallery()
    } catch (err) {
      if (err instanceof ApiError) {
        setUnlockError(err.message)
      } else {
        setUnlockError('Unlock failed. Please try again.')
      }
    } finally {
      setUnlocking(false)
    }
  }

  // ─── Loading Screen ────────────────────────────────────────────────────────
  if (loading) {
    return (
      <div className="min-h-screen bg-[#fafaf9] flex items-center justify-center">
        <div className="flex flex-col items-center gap-4">
          <div className="w-12 h-12 rounded-full border-4 border-[#dd7a53]/20 border-t-[#dd7a53] animate-spin" />
          <p className="text-[#78716c] font-medium text-sm">Opening gallery...</p>
        </div>
      </div>
    )
  }

  // ─── Password Required Screen ──────────────────────────────────────────────
  if (errorState?.requiresPassword) {
    return (
      <div className="min-h-screen bg-[#fafaf9] flex items-center justify-center p-6">
        <div className="bg-white border border-[#e7e5e4] rounded-2xl p-8 max-w-md w-full shadow-lg text-center space-y-6">
          <div className="w-16 h-16 rounded-full bg-[#dd7a53]/10 flex items-center justify-center mx-auto text-[#dd7a53]">
            <LockKeyhole size={32} />
          </div>
          <div>
            <h1 className="text-2xl font-bold text-[#1c1917]">Password Protected</h1>
            <p className="text-sm text-[#78716c] mt-2">
              This photo gallery is private. Please enter the password to unlock.
            </p>
            {errorState.message && (
              <p className="text-xs font-semibold text-[#dd7a53] mt-2">
                Hint: {errorState.message.includes('Hint:') ? errorState.message.split('Hint:')[1] : 'Check with the photographer.'}
              </p>
            )}
          </div>

          <form onSubmit={handleUnlockSubmit} className="space-y-4">
            <input
              type="password"
              placeholder="Enter password..."
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="w-full px-4 py-3 bg-[#fafaf9] border border-[#e7e5e4] rounded-xl text-[#1c1917] placeholder-[#a8a29e] focus:outline-none focus:border-[#dd7a53] transition-colors"
            />
            {unlockError && <p className="text-xs text-destructive text-left font-medium">{unlockError}</p>}
            <button
              type="submit"
              disabled={unlocking}
              className="w-full py-3 bg-[#dd7a53] text-white rounded-xl hover:bg-[#d16b44] font-semibold transition-colors disabled:opacity-60 flex items-center justify-center gap-2"
            >
              {unlocking ? 'Unlocking...' : 'Unlock Gallery'}
            </button>
          </form>
        </div>
      </div>
    )
  }

  // ─── Invitation Required Screen ────────────────────────────────────────────
  if (errorState?.requiresInvitation) {
    return (
      <div className="min-h-screen bg-[#fafaf9] flex items-center justify-center p-6">
        <div className="bg-white border border-[#e7e5e4] rounded-2xl p-8 max-w-md w-full shadow-lg text-center space-y-6">
          <div className="w-16 h-16 rounded-full bg-[#dd7a53]/10 flex items-center justify-center mx-auto text-[#dd7a53]">
            <Mail size={32} />
          </div>
          <div>
            <h1 className="text-2xl font-bold text-[#1c1917]">Invitation Required</h1>
            <p className="text-sm text-[#78716c] mt-2">
              This gallery is private and restricted to invited email addresses. Please use the unique link sent to your email.
            </p>
          </div>
          <div className="p-4 bg-[#fffbeb] border border-[#fef3c7] rounded-xl text-left">
            <p className="text-xs text-[#b45309] font-medium leading-relaxed">
              If you received an invitation, make sure to click the "View Gallery" button in your email. Links are custom-generated for each guest.
            </p>
          </div>
        </div>
      </div>
    )
  }

  // ─── Other Errors Screen ───────────────────────────────────────────────────
  if (errorState) {
    return (
      <div className="min-h-screen bg-[#fafaf9] flex items-center justify-center p-6">
        <div className="text-center max-w-md">
          <AlertTriangle className="w-16 h-16 text-destructive mx-auto mb-4 opacity-75" />
          <h1 className="text-2xl font-bold text-[#1c1917] mb-2">Access Denied</h1>
          <p className="text-sm text-[#78716c] mb-6">{errorState.message}</p>
          <a
            href="/"
            className="inline-block px-6 py-3 bg-[#dd7a53] text-white rounded-xl font-semibold hover:bg-[#d16b44] transition-colors"
          >
            Go to Homepage
          </a>
        </div>
      </div>
    )
  }

  // ─── Main Gallery Content Screen ───────────────────────────────────────────
  if (!gallery) return null

  const photos = gallery.photos ?? []

  return (
    <div className="min-h-screen bg-[#fafaf9] text-[#1c1917]">
      {/* Public Navbar Header */}
      <header className="border-b border-[#e7e5e4] bg-white sticky top-0 z-30">
        <div className="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <span className="font-extrabold text-xl text-[#dd7a53] tracking-tight">ifotoset</span>
            <div className="h-5 w-px bg-[#e7e5e4]" />
            <h1 className="font-bold text-lg text-[#1c1917] truncate max-w-[200px] sm:max-w-sm">
              {gallery.title}
            </h1>
          </div>
          <div className="flex items-center gap-4 text-xs text-[#78716c] shrink-0">
            {gallery.client_name && (
              <span className="hidden sm:flex items-center gap-1.5 font-medium">
                <User size={14} /> {gallery.client_name}
              </span>
            )}
            {gallery.event_date && (
              <span className="flex items-center gap-1.5 font-medium">
                <Calendar size={14} /> {new Date(gallery.event_date).toLocaleDateString()}
              </span>
            )}
          </div>
        </div>
      </header>

      {/* Hero Banner Section */}
      <section className="bg-white border-b border-[#e7e5e4] py-16 px-6 text-center">
        <div className="max-w-3xl mx-auto space-y-4">
          <h2 className="text-4xl font-extrabold text-[#1c1917] tracking-tight sm:text-5xl">
            {gallery.title}
          </h2>
          <p className="text-md text-[#78716c] max-w-xl mx-auto">
            Welcome to your digital photo collection. View, browse, and explore high-resolution memories.
          </p>
        </div>
      </section>

      {/* Photo Grid Section */}
      <main className="max-w-7xl mx-auto px-6 py-12">
        {photos.length === 0 ? (
          <div className="bg-white border border-[#e7e5e4] rounded-2xl p-16 text-center shadow-sm">
            <ImageIcon className="w-16 h-16 text-[#a8a29e]/50 mx-auto mb-4" />
            <h3 className="text-lg font-bold text-[#1c1917] mb-1">No photos yet</h3>
            <p className="text-sm text-[#78716c]">This gallery is empty. Check back again later.</p>
          </div>
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            {photos.map((photo) => (
              <button
                key={photo.uuid}
                onClick={() => setSelectedPhoto(photo)}
                className="group relative aspect-square bg-white border border-[#e7e5e4] rounded-xl overflow-hidden hover:ring-2 hover:ring-[#dd7a53] transition-all shadow-sm"
              >
                <img
                  src={photo.variants?.md || photo.cdn_url}
                  alt={photo.filename}
                  className="w-full h-full object-cover group-hover:scale-102 transition-transform duration-300"
                  loading="lazy"
                />
                <div className="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors flex items-end p-4 justify-between">
                  <div className="bg-black/60 backdrop-blur-sm text-white text-[10px] px-2 py-1 rounded-md opacity-0 group-hover:opacity-100 transition-opacity">
                    {photo.filename}
                  </div>
                  <div className="w-8 h-8 rounded-full bg-white/90 backdrop-blur-sm flex items-center justify-center text-[#1c1917] opacity-0 group-hover:opacity-100 transition-opacity">
                    <Eye size={16} />
                  </div>
                </div>
              </button>
            ))}
          </div>
        )}
      </main>

      {/* Lightbox / Overlay Modal */}
      {selectedPhoto && (
        <div
          className="fixed inset-0 bg-black/95 z-50 flex items-center justify-center p-4"
          onClick={() => setSelectedPhoto(null)}
        >
          <button
            className="absolute top-6 right-6 p-2 rounded-full bg-white/10 text-white/70 hover:text-white hover:bg-white/20 transition-colors"
            onClick={() => setSelectedPhoto(null)}
          >
            <X size={24} />
          </button>
          
          <img
            src={selectedPhoto.variants?.xl || selectedPhoto.cdn_url}
            alt={selectedPhoto.filename}
            className="max-w-full max-h-[85vh] object-contain rounded-lg shadow-2xl"
            onClick={(e) => e.stopPropagation()}
          />
          
          <div
            className="absolute bottom-6 left-1/2 -translate-x-1/2 bg-black/60 backdrop-blur-md text-white text-xs px-5 py-2.5 rounded-full flex items-center gap-4"
            onClick={(e) => e.stopPropagation()}
          >
            <span>{selectedPhoto.filename}</span>
            <span className="w-px h-3 bg-white/30" />
            <span>{formatBytes(selectedPhoto.size)}</span>
            <span className="w-px h-3 bg-white/30" />
            <a
              href={selectedPhoto.cdn_url}
              download={selectedPhoto.filename}
              className="text-[#dd7a53] hover:underline flex items-center gap-1 font-semibold"
            >
              <Download size={14} /> Download
            </a>
          </div>
        </div>
      )}
    </div>
  )
}
