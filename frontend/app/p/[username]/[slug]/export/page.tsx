'use client'

import { useState, useEffect, useRef } from 'react'
import { useParams, useSearchParams, useRouter } from 'next/navigation'
import Link from 'next/link'
import { 
  Download, 
  Mail, 
  CheckCircle2, 
  XCircle, 
  AlertCircle, 
  Loader2, 
  ArrowLeft, 
  Image as ImageIcon,
  ExternalLink,
  Info
} from 'lucide-react'
import { 
  getPublicGallery, 
  triggerGalleryZip, 
  getGalleryZipStatus, 
  authorizeGooglePhotos,
  ZipStatusResponse 
} from '@/lib/queries/galleries'

export default function ExportPage() {
  const params = useParams()
  const searchParams = useSearchParams()
  const router = useRouter()

  const slug = params.slug as string
  const type = searchParams.get('type') as 'zip' | 'google-photos'
  const target = searchParams.get('target') as 'all' | 'favorites'
  const inviteToken = searchParams.get('invite')
  const galleryToken = searchParams.get('token')

  const [galleryTitle, setGalleryTitle] = useState<string>('Gallery')
  const [loading, setLoading] = useState(true)
  
  // Input fields
  const [email, setEmail] = useState('')
  const [notify, setNotify] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  
  // Status state
  const [status, setStatus] = useState<string | null>(null)
  const [downloadId, setDownloadId] = useState<number | null>(null)
  const [progress, setProgress] = useState<any>(null)
  const [errorMsg, setErrorMsg] = useState<string | null>(null)

  const pollIntervalRef = useRef<any>(null)

  // Fetch gallery title on mount
  useEffect(() => {
    const fetchGallery = async () => {
      try {
        const res = await getPublicGallery(slug, inviteToken, galleryToken)
        setGalleryTitle(res.data.title)
      } catch (err) {
        console.error('Failed to load gallery info', err)
      } finally {
        setLoading(false)
      }
    }
    fetchGallery()
  }, [slug, inviteToken, galleryToken])

  // Polling zip status if we have a downloadId
  const fetchStatus = async () => {
    if (!downloadId) return
    try {
      const res = await getGalleryZipStatus(slug, downloadId, inviteToken, galleryToken)
      setStatus(res.status)
      setProgress(res)
      
      if (['ready', 'ready_with_errors', 'failed', 'empty'].includes(res.status)) {
        if (pollIntervalRef.current) {
          clearInterval(pollIntervalRef.current)
          pollIntervalRef.current = null
        }
      }
    } catch (err) {
      console.error('Failed to poll status', err)
    }
  }

  // Setup visibility-aware polling
  useEffect(() => {
    if (!downloadId || ['ready', 'ready_with_errors', 'failed', 'empty'].includes(status || '')) return

    const startPolling = () => {
      if (pollIntervalRef.current) return
      fetchStatus() // Immediate check
      pollIntervalRef.current = setInterval(fetchStatus, 2000)
    }

    const stopPolling = () => {
      if (pollIntervalRef.current) {
        clearInterval(pollIntervalRef.current)
        pollIntervalRef.current = null
      }
    }

    const handleVisibilityChange = () => {
      if (document.visibilityState === 'visible') {
        startPolling()
      } else {
        stopPolling()
      }
    }

    // Start polling initially if active
    if (document.visibilityState === 'visible') {
      startPolling()
    }

    document.addEventListener('visibilitychange', handleVisibilityChange)

    return () => {
      stopPolling()
      document.removeEventListener('visibilitychange', handleVisibilityChange)
    }
  }, [downloadId, status])

  const handleStartExport = async (e: React.FormEvent) => {
    e.preventDefault()
    setSubmitting(true)
    setErrorMsg(null)

    if (notify && (!email || !email.includes('@'))) {
      setErrorMsg('Please enter a valid email address.')
      setSubmitting(false)
      return
    }

    try {
      if (type === 'zip') {
        const res = await triggerGalleryZip(
          slug, 
          notify ? email : null, 
          notify, 
          inviteToken, 
          galleryToken
        )
        setDownloadId(res.download_id)
        setStatus(res.status)
        if (res.status === 'ready' && res.download_url) {
          // Trigger immediate file download
          const a = document.createElement('a')
          a.href = res.download_url
          a.download = `${slug}-photos.zip`
          document.body.appendChild(a)
          a.click()
          document.body.removeChild(a)
        }
      } else if (type === 'google-photos') {
        const favoriteUuids = target === 'favorites' 
          ? JSON.parse(sessionStorage.getItem(`photos_export_favorites_${slug}`) || '[]')
          : null
          
        const res = await authorizeGooglePhotos(
          slug, 
          favoriteUuids, 
          notify ? email : null, 
          notify, 
          inviteToken, 
          galleryToken
        )
        
        if (res.url) {
          // If they wanted email alerts, we stored it in authorization state. Redirect to google photos callback.
          window.location.href = res.url
        } else {
          throw new Error('Failed to retrieve authentication url.')
        }
      }
    } catch (err: any) {
      console.error('Trigger export failed', err)
      setErrorMsg(err.message || 'Unable to start export. Please try again.')
    } finally {
      setSubmitting(false)
    }
  }

  // Format dynamic status values
  const total = progress?.total_photos || 0
  const processed = progress?.processed_photos || 0
  const failed = progress?.failed_photos || 0
  const totalDone = processed + failed
  const percentage = progress?.percentage ?? (total > 0 ? Math.min(100, Math.round((totalDone / total) * 100)) : 0)

  return (
    <main className="min-h-screen bg-background flex items-center justify-center p-6 text-foreground">
      <div className="bg-card border border-border p-8 rounded-2xl max-w-lg w-full shadow-2xl space-y-6 relative overflow-hidden">
        {/* Subtle decorative background gradient */}
        <div className="absolute top-0 inset-x-0 h-1.5 bg-gradient-to-r from-primary via-blue-500 to-indigo-500" />

        {loading ? (
          <div className="flex flex-col items-center justify-center py-10 space-y-4">
            <Loader2 className="w-12 h-12 text-primary animate-spin" />
            <h2 className="text-xl font-bold">Loading Gallery Export...</h2>
          </div>
        ) : (
          <div className="space-y-6">
            {/* Header */}
            <div className="flex items-center gap-3 pb-4 border-b border-border">
              <Link 
                href={`/${slug}${inviteToken ? `?invite=${inviteToken}` : ''}`}
                className="p-2 hover:bg-secondary rounded-lg transition-colors border border-border/50 text-muted-foreground hover:text-foreground"
              >
                <ArrowLeft size={16} />
              </Link>
              <div>
                <h1 className="text-xl font-bold tracking-tight">Gallery Export</h1>
                <p className="text-xs text-muted-foreground mt-0.5">{galleryTitle}</p>
              </div>
            </div>

            {/* Form step */}
            {!status && (
              <form onSubmit={handleStartExport} className="space-y-6">
                <div className="p-4 rounded-xl border border-border/60 bg-secondary/20 flex gap-3.5 items-start">
                  <div className="p-2 bg-primary/10 rounded-lg text-primary shrink-0 mt-0.5">
                    {type === 'zip' ? <Download size={20} /> : <ImageIcon size={20} />}
                  </div>
                  <div className="space-y-1">
                    <h3 className="font-bold text-sm">
                      {type === 'zip' ? 'Export entire gallery as ZIP archive' : 'Sync photos to Google Photos'}
                    </h3>
                    <p className="text-xs text-muted-foreground leading-relaxed">
                      {type === 'zip' 
                        ? 'We will pack all photos from the gallery into a single downloadable ZIP file. Large galleries may take a few minutes.' 
                        : 'Connect your Google account to automatically export the gallery photos into a dedicated album.'
                      }
                    </p>
                  </div>
                </div>

                {/* Email subscription panel */}
                <div className="p-5 border border-border/80 rounded-xl bg-card/50 space-y-4">
                  <div className="flex items-center justify-between">
                    <label className="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                      <Mail size={14} className="text-primary" />
                      Email Notification
                    </label>
                  </div>

                  <div className="space-y-3">
                    <div className="flex items-center gap-3">
                      <input 
                        type="checkbox"
                        id="notify_when_ready"
                        checked={notify}
                        onChange={(e) => setNotify(e.target.checked)}
                        className="rounded border-border text-primary focus:ring-primary w-4 h-4 bg-secondary cursor-pointer"
                      />
                      <label htmlFor="notify_when_ready" className="text-sm font-semibold text-foreground/95 cursor-pointer selection:bg-transparent">
                        Email me when the export is ready
                      </label>
                    </div>

                    {notify && (
                      <div className="space-y-1 pt-1.5 animate-fadeIn">
                        <input
                          type="email"
                          placeholder="e.g. user@example.com"
                          value={email}
                          onChange={(e) => setEmail(e.target.value)}
                          required={notify}
                          className="w-full bg-secondary/50 border border-border rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-primary transition-colors placeholder-muted-foreground"
                        />
                        <p className="text-[10px] text-muted-foreground flex items-center gap-1">
                          <Info size={10} className="text-primary shrink-0" />
                          We will only use this email to notify you when this export finishes.
                        </p>
                      </div>
                    )}
                  </div>
                </div>

                {errorMsg && (
                  <div className="p-3.5 bg-destructive/10 border border-destructive/20 text-destructive text-xs font-medium rounded-lg leading-snug">
                    {errorMsg}
                  </div>
                )}

                <button
                  type="submit"
                  disabled={submitting}
                  className="w-full inline-flex items-center justify-center gap-2 px-4 py-3 bg-primary hover:bg-accent text-primary-foreground font-bold text-sm rounded-lg transition-colors shadow disabled:opacity-50"
                >
                  {submitting && <Loader2 className="w-4 h-4 animate-spin" />}
                  {type === 'zip' ? 'Start ZIP Packaging' : 'Connect to Google Photos'}
                </button>
              </form>
            )}

            {/* Active Polling step */}
            {status && (
              <div className="space-y-6">
                {/* Status headers */}
                <div className="text-center space-y-2">
                  {status === 'pending' && (
                    <div className="space-y-3">
                      <div className="w-16 h-16 rounded-full bg-secondary/80 border border-border flex items-center justify-center text-muted-foreground mx-auto">
                        <Loader2 className="w-8 h-8 animate-spin" />
                      </div>
                      <h2 className="text-xl font-bold">Queueing Packaging Request</h2>
                      <p className="text-xs text-muted-foreground">Waiting for a background worker to claim this task...</p>
                    </div>
                  )}

                  {status === 'processing' && (
                    <div className="space-y-3">
                      <div className="w-16 h-16 rounded-full bg-primary/10 border border-primary/20 flex items-center justify-center text-primary mx-auto">
                        <Loader2 className="w-8 h-8 animate-spin" />
                      </div>
                      <h2 className="text-xl font-bold">Processing Photos</h2>
                    </div>
                  )}

                  {(status === 'ready' || status === 'ready_with_errors') && (
                    <div className="space-y-3">
                      <div className="w-16 h-16 rounded-full bg-green-500/10 border border-green-500/20 flex items-center justify-center text-green-500 mx-auto">
                        <CheckCircle2 className="w-8 h-8" />
                      </div>
                      <h2 className="text-xl font-bold">Export Completed!</h2>
                    </div>
                  )}

                  {(status === 'failed' || status === 'empty') && (
                    <div className="space-y-3">
                      <div className="w-16 h-16 rounded-full bg-destructive/10 border border-destructive/20 flex items-center justify-center text-destructive mx-auto">
                        <XCircle className="w-8 h-8" />
                      </div>
                      <h2 className="text-xl font-bold">Export Failed</h2>
                    </div>
                  )}
                </div>

                {/* Progress bar */}
                {(status === 'pending' || status === 'processing') && (
                  <div className="space-y-4 p-4 border border-border rounded-xl bg-secondary/20">
                    <div className="flex justify-between items-center text-sm font-semibold">
                      <span className="text-muted-foreground">Progress</span>
                      <span>{percentage}%</span>
                    </div>

                    <div className="w-full bg-secondary/50 rounded-full h-2.5 overflow-hidden border border-border">
                      <div
                        className="bg-primary h-full rounded-full transition-all duration-500 ease-out"
                        style={{ width: `${percentage}%` }}
                      />
                    </div>

                    <div className="flex justify-between text-xs text-muted-foreground font-medium">
                      <span>Packed {processed + failed} / {total} photos</span>
                      {failed > 0 && <span className="text-destructive font-semibold">{failed} skipped</span>}
                    </div>

                    {/* Dynamic ETA */}
                    {progress?.estimated_finish_time && (
                      <p className="text-center text-xs text-primary font-semibold">
                        Estimated completion: ~{progress?.remaining_seconds !== null ? (
                          progress.remaining_seconds < 60 ? 'less than a minute' :
                          `${Math.ceil(progress.remaining_seconds / 60)} minute${Math.ceil(progress.remaining_seconds / 60) > 1 ? 's' : ''}`
                        ) : 'calculating...'}
                      </p>
                    )}
                  </div>
                )}

                {/* Result descriptions */}
                {status === 'ready' && (
                  <p className="text-sm text-muted-foreground text-center leading-relaxed">
                    All <strong>{processed}</strong> photos have been successfully compiled. Click below to download your archive.
                  </p>
                )}

                {status === 'ready_with_errors' && (
                  <div className="space-y-4">
                    <p className="text-sm text-muted-foreground text-center leading-relaxed">
                      ZIP compiled with some exclusions. <strong>{processed}</strong> photos succeeded, and <strong>{failed}</strong> skipped due to source retrieval issues.
                    </p>
                  </div>
                )}

                {status === 'failed' && (
                  <p className="text-sm text-destructive bg-destructive/10 border border-destructive/20 p-4 rounded-xl text-center font-semibold leading-relaxed">
                    Error: {progress?.error || 'Gallery packaging job failed. Please try again later.'}
                  </p>
                )}

                {status === 'empty' && (
                  <p className="text-sm text-muted-foreground text-center leading-relaxed bg-secondary/30 border border-border p-4 rounded-xl">
                    No ready photos in gallery to pack.
                  </p>
                )}

                {/* opt-in safe exit instructions */}
                {(status === 'pending' || status === 'processing') && (
                  <div className="p-4 border border-border/80 rounded-xl bg-card text-xs leading-relaxed text-muted-foreground space-y-2">
                    <p className="font-semibold text-foreground flex items-center gap-1.5">
                      <Info size={14} className="text-primary shrink-0" />
                      Safe Exit
                    </p>
                    <p>
                      {notify 
                        ? `You can safely close this page. We will email you at ${email} as soon as the files are ready.`
                        : 'You can safely close this page. You can return to your gallery later to download the completed export.'
                      }
                    </p>
                  </div>
                )}

                {/* Actions */}
                <div className="pt-4 flex flex-col gap-2">
                  {(status === 'ready' || status === 'ready_with_errors') && progress?.download_url && (
                    <a
                      href={progress.download_url}
                      className="w-full inline-flex items-center justify-center gap-2 px-4 py-3 bg-primary hover:bg-accent text-primary-foreground font-bold text-sm rounded-lg transition-colors shadow"
                    >
                      Download ZIP Archive
                    </a>
                  )}

                  <Link
                    href={`/${slug}${inviteToken ? `?invite=${inviteToken}` : ''}`}
                    className="w-full inline-flex items-center justify-center gap-2 px-4 py-3 bg-secondary hover:bg-muted text-foreground font-semibold text-sm rounded-lg transition-colors border border-border"
                  >
                    <ArrowLeft size={16} />
                    Return to Gallery
                  </Link>
                </div>
              </div>
            )}
          </div>
        )}
      </div>
    </main>
  )
}
