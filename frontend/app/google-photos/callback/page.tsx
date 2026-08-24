'use client'

import { useState, useEffect, useRef, Suspense } from 'react'
import { useSearchParams, useRouter } from 'next/navigation'
import Link from 'next/link'
import { CheckCircle2, AlertCircle, XCircle, ExternalLink, Loader2, ArrowLeft, Info } from 'lucide-react'
import { 
  callbackGooglePhotos, 
  getGooglePhotosSyncStatus, 
  updateGooglePhotosSyncNotification, 
  GooglePhotosSyncStatus 
} from '@/lib/queries/galleries'

function GooglePhotosCallbackInner() {
  const searchParams = useSearchParams()
  const router = useRouter()

  const code = searchParams.get('code')
  const state = searchParams.get('state')

  const [loading, setLoading] = useState(true)
  const [errorMsg, setErrorMsg] = useState<string | null>(null)
  const [syncUuid, setSyncUuid] = useState<string | null>(null)
  const [gallerySlug, setGallerySlug] = useState<string | null>(null)
  const [syncStatus, setSyncStatus] = useState<GooglePhotosSyncStatus | null>(null)

  // Subscription states
  const [email, setEmail] = useState('')
  const [notify, setNotify] = useState(false)
  const [updatingMail, setUpdatingMail] = useState(false)

  const isExchangingRef = useRef(false)
  const pollIntervalRef = useRef<any>(null)

  useEffect(() => {
    if (!code || !state) {
      setErrorMsg('Invalid OAuth callback parameters. Missing code or state.')
      setLoading(false)
      return
    }

    if (isExchangingRef.current) return
    isExchangingRef.current = true

    const initiateSync = async () => {
      try {
        const res = await callbackGooglePhotos(code, state)
        setSyncUuid(res.sync_uuid)
        setGallerySlug(res.gallery_slug)
        setLoading(false)
      } catch (err: any) {
        console.error('Callback OAuth exchange failed', err)
        setErrorMsg(err.message || 'Failed to exchange authentication tokens with Google.')
        setLoading(false)
      }
    }

    initiateSync()
  }, [code, state])

  const checkStatus = async () => {
    if (!syncUuid || !gallerySlug) return
    try {
      const res = await getGooglePhotosSyncStatus(gallerySlug, syncUuid)
      setSyncStatus(res)

      // Initialize notify/email state from backend if present
      if (res.notify_when_ready && res.email) {
        setNotify(true)
        setEmail(res.email)
      }

      if (['completed', 'completed_with_errors', 'failed'].includes(res.status)) {
        if (pollIntervalRef.current) {
          clearInterval(pollIntervalRef.current)
          pollIntervalRef.current = null
        }
      }
    } catch (err) {
      console.error('Status check failed', err)
    }
  }

  // Poll sync status once sync_uuid is available (visibility-aware)
  useEffect(() => {
    if (!syncUuid || !gallerySlug || ['completed', 'completed_with_errors', 'failed'].includes(syncStatus?.status || '')) return

    const startPolling = () => {
      if (pollIntervalRef.current) return
      checkStatus() // Immediate check
      pollIntervalRef.current = setInterval(checkStatus, 3000)
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

    if (document.visibilityState === 'visible') {
      startPolling()
    }

    document.addEventListener('visibilitychange', handleVisibilityChange)

    return () => {
      stopPolling()
      document.removeEventListener('visibilitychange', handleVisibilityChange)
    }
  }, [syncUuid, gallerySlug, syncStatus])

  const handleUpdateNotify = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!gallerySlug || !syncUuid) return
    setUpdatingMail(true)

    try {
      await updateGooglePhotosSyncNotification(gallerySlug, syncUuid, email, notify)
      // Re-fetch status immediately to sync UI
      await checkStatus()
    } catch (err) {
      console.error('Failed to update email preferences', err)
    } finally {
      setUpdatingMail(false)
    }
  }

  // Progress computation
  const total = syncStatus?.total_photos || 0
  const processed = syncStatus?.processed_photos || 0
  const failed = syncStatus?.failed_photos || 0
  const totalProcessed = processed + failed
  const progressPercent = syncStatus?.percentage ?? (total > 0 ? Math.min(100, Math.round((totalProcessed / total) * 100)) : 0)

  return (
    <main className="min-h-screen bg-background flex items-center justify-center p-6 text-foreground">
      <div className="bg-card border border-border p-8 rounded-2xl max-w-md w-full shadow-2xl space-y-6 relative overflow-hidden">
        {/* Subtle decorative background gradient */}
        <div className="absolute top-0 inset-x-0 h-1.5 bg-gradient-to-r from-primary via-blue-500 to-indigo-500" />

        {loading && (
          <div className="flex flex-col items-center justify-center py-10 space-y-4">
            <Loader2 className="w-12 h-12 text-primary animate-spin" />
            <h2 className="text-xl font-bold">Connecting to Google</h2>
            <p className="text-sm text-muted-foreground text-center max-w-xs">
              Verifying credentials and initiating photo synchronization...
            </p>
          </div>
        )}

        {errorMsg && (
          <div className="flex flex-col items-center text-center space-y-4 py-6">
            <div className="w-16 h-16 rounded-full bg-destructive/10 border border-destructive/20 flex items-center justify-center text-destructive">
              <XCircle className="w-8 h-8" />
            </div>
            <h2 className="text-xl font-bold text-foreground">Authentication Failed</h2>
            <p className="text-sm text-muted-foreground leading-relaxed">
              {errorMsg}
            </p>
            <div className="pt-4 w-full">
              <Link
                href="/"
                className="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-secondary hover:bg-muted text-foreground font-semibold text-sm rounded-lg transition-colors border border-border"
              >
                <ArrowLeft size={16} />
                Return Home
              </Link>
            </div>
          </div>
        )}

        {!loading && !errorMsg && syncStatus && (
          <div className="space-y-6">
            {/* Header Status */}
            <div className="text-center space-y-2">
              {syncStatus.status === 'pending' && (
                <h2 className="text-xl font-bold">Queueing Sync Request</h2>
              )}
              {syncStatus.status === 'processing' && (
                <h2 className="text-xl font-bold">Syncing Photos</h2>
              )}
              {syncStatus.status === 'completed' && (
                <div className="flex flex-col items-center space-y-3">
                  <div className="w-16 h-16 rounded-full bg-green-500/10 border border-green-500/20 flex items-center justify-center text-green-500">
                    <CheckCircle2 className="w-8 h-8" />
                  </div>
                  <h2 className="text-2xl font-bold">Sync Completed!</h2>
                </div>
              )}
              {syncStatus.status === 'completed_with_errors' && (
                <div className="flex flex-col items-center space-y-3">
                  <div className="w-16 h-16 rounded-full bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-500">
                    <AlertCircle className="w-8 h-8" />
                  </div>
                  <h2 className="text-2xl font-bold">Sync Finished</h2>
                </div>
              )}
              {syncStatus.status === 'failed' && (
                <div className="flex flex-col items-center space-y-3">
                  <div className="w-16 h-16 rounded-full bg-destructive/10 border border-destructive/20 flex items-center justify-center text-destructive">
                    <XCircle className="w-8 h-8" />
                  </div>
                  <h2 className="text-2xl font-bold">Sync Failed</h2>
                </div>
              )}
            </div>

            {/* Progress indicators */}
            {(syncStatus.status === 'pending' || syncStatus.status === 'processing') && (
              <div className="space-y-4 p-4 border border-border rounded-xl bg-secondary/20">
                <div className="flex justify-between items-center text-sm font-semibold">
                  <span className="text-muted-foreground">Progress</span>
                  <span>{progressPercent}%</span>
                </div>
                
                {/* Progress bar */}
                <div className="w-full bg-secondary/50 rounded-full h-2.5 overflow-hidden border border-border">
                  <div
                    className="bg-primary h-full rounded-full transition-all duration-500 ease-out"
                    style={{ width: `${progressPercent}%` }}
                  />
                </div>

                <div className="flex justify-between text-xs text-muted-foreground font-medium">
                  <span>Synced {processed + failed} / {total} photos</span>
                  {failed > 0 && <span className="text-destructive font-semibold">{failed} skipped</span>}
                </div>

                {/* Dynamic ETA */}
                {syncStatus.estimated_finish_time && (
                  <p className="text-center text-xs text-primary font-semibold">
                    Estimated completion: ~{typeof syncStatus.remaining_seconds === 'number' ? (
                      syncStatus.remaining_seconds < 60 ? 'less than a minute' :
                      `${Math.ceil(syncStatus.remaining_seconds / 60)} minute${Math.ceil(syncStatus.remaining_seconds / 60) > 1 ? 's' : ''}`
                    ) : 'calculating...'}
                  </p>
                )}
              </div>
            )}

            {/* Notification signup if they didn't opt-in yet */}
            {(syncStatus.status === 'pending' || syncStatus.status === 'processing') && !syncStatus.notify_when_ready && (
              <form onSubmit={handleUpdateNotify} className="p-4 border border-border/80 rounded-xl bg-card/50 space-y-3">
                <div className="flex items-center gap-2">
                  <input
                    type="checkbox"
                    id="notify_sync"
                    checked={notify}
                    onChange={(e) => setNotify(e.target.checked)}
                    className="rounded border-border text-primary focus:ring-primary w-4 h-4 bg-secondary cursor-pointer"
                  />
                  <label htmlFor="notify_sync" className="text-xs font-semibold text-foreground/90 cursor-pointer selection:bg-transparent">
                    Email me when this export finishes
                  </label>
                </div>

                {notify && (
                  <div className="flex gap-2 animate-fadeIn">
                    <input
                      type="email"
                      placeholder="e.g. user@example.com"
                      value={email}
                      onChange={(e) => setEmail(e.target.value)}
                      required={notify}
                      className="flex-1 bg-secondary/50 border border-border rounded-lg px-3 py-1.5 text-xs focus:outline-none focus:border-primary transition-colors text-foreground"
                    />
                    <button
                      type="submit"
                      disabled={updatingMail}
                      className="px-3 py-1.5 bg-primary text-primary-foreground text-xs font-semibold rounded-lg hover:bg-accent transition-colors disabled:opacity-50"
                    >
                      {updatingMail ? 'Saving...' : 'Opt-In'}
                    </button>
                  </div>
                )}
              </form>
            )}

            {/* Results/Details */}
            {syncStatus.status === 'completed' && (
              <p className="text-sm text-muted-foreground text-center leading-relaxed">
                All <strong>{processed}</strong> photos have been successfully uploaded to Google Photos. You can find them in your new album!
              </p>
            )}

            {syncStatus.status === 'completed_with_errors' && (
              <div className="space-y-2 text-center">
                <p className="text-sm text-muted-foreground leading-relaxed">
                  The synchronization finished with some issues:
                </p>
                <div className="p-3 bg-secondary/30 border border-border rounded-xl inline-grid grid-cols-2 gap-4 text-xs font-semibold">
                  <div className="text-green-500">
                    Succeeded: {processed}
                  </div>
                  <div className="text-destructive">
                    Failed: {failed}
                  </div>
                </div>
              </div>
            )}

            {syncStatus.status === 'failed' && (
              <p className="text-sm text-destructive text-center font-semibold bg-destructive/10 p-3 rounded-lg border border-destructive/20 leading-relaxed">
                Error: {syncStatus.error || 'Google Photos API synchronization failed.'}
              </p>
            )}

            {/* opt-in safe exit instructions */}
            {(syncStatus.status === 'pending' || syncStatus.status === 'processing') && (
              <div className="p-4 border border-border/80 rounded-xl bg-card text-xs leading-relaxed text-muted-foreground space-y-2">
                <p className="font-semibold text-foreground flex items-center gap-1.5">
                  <Info size={14} className="text-primary shrink-0" />
                  Safe Exit
                </p>
                <p>
                  {syncStatus.notify_when_ready && syncStatus.email
                    ? `You can safely close this page. We will email you at ${syncStatus.email} when the sync is complete.`
                    : 'You can safely close this page. You can return to your gallery later to verify the sync.'
                  }
                </p>
              </div>
            )}

            {/* Actions button */}
            <div className="pt-4 flex flex-col gap-2">
              {syncStatus.album_url && (
                <a
                  href={syncStatus.album_url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="w-full inline-flex items-center justify-center gap-2 px-4 py-3 bg-primary hover:bg-accent text-primary-foreground font-bold text-sm rounded-lg transition-colors shadow"
                >
                  Open Google Photos Album
                  <ExternalLink size={14} />
                </a>
              )}

              {gallerySlug && (
                <Link
                  href={`/g/${gallerySlug}`}
                  className="w-full inline-flex items-center justify-center gap-2 px-4 py-3 bg-secondary hover:bg-muted text-foreground font-semibold text-sm rounded-lg transition-colors border border-border"
                >
                  <ArrowLeft size={16} />
                  Return to Gallery
                </Link>
              )}
            </div>
          </div>
        )}
      </div>
    </main>
  )
}

export default function GooglePhotosCallback() {
  return (
    <Suspense fallback={
      <main className="min-h-screen bg-background flex items-center justify-center p-6">
        <div className="flex flex-col items-center space-y-4">
          <Loader2 className="w-12 h-12 text-primary animate-spin" />
          <p className="text-muted-foreground text-sm">Loading...</p>
        </div>
      </main>
    }>
      <GooglePhotosCallbackInner />
    </Suspense>
  )
}
