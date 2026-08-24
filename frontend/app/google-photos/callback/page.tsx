'use client'

import { useState, useEffect, useRef } from 'react'
import { useSearchParams, useRouter } from 'next/navigation'
import Link from 'next/link'
import { CheckCircle2, AlertCircle, XCircle, ExternalLink, Loader2, ArrowLeft } from 'lucide-react'
import { callbackGooglePhotos, getGooglePhotosSyncStatus, GooglePhotosSyncStatus } from '@/lib/queries/galleries'

export default function GooglePhotosCallback() {
  const searchParams = useSearchParams()
  const router = useRouter()

  const code = searchParams.get('code')
  const state = searchParams.get('state')

  const [loading, setLoading] = useState(true)
  const [errorMsg, setErrorMsg] = useState<string | null>(null)
  const [syncUuid, setSyncUuid] = useState<string | null>(null)
  const [gallerySlug, setGallerySlug] = useState<string | null>(null)
  const [syncStatus, setSyncStatus] = useState<GooglePhotosSyncStatus | null>(null)

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

  // Poll sync status once sync_uuid is available
  useEffect(() => {
    if (!syncUuid || !gallerySlug) return

    const checkStatus = async () => {
      try {
        const res = await getGooglePhotosSyncStatus(gallerySlug, syncUuid)
        setSyncStatus(res)

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

    // Initial check
    checkStatus()

    // Polling
    pollIntervalRef.current = setInterval(checkStatus, 3000)

    return () => {
      if (pollIntervalRef.current) {
        clearInterval(pollIntervalRef.current)
      }
    }
  }, [syncUuid, gallerySlug])

  // Progress computation
  const total = syncStatus?.total_photos || 0
  const processed = syncStatus?.processed_photos || 0
  const failed = syncStatus?.failed_photos || 0
  const totalProcessed = processed + failed
  const progressPercent = total > 0 ? Math.min(100, Math.round((totalProcessed / total) * 100)) : 0

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
              <div className="space-y-4">
                <div className="flex justify-between items-center text-sm font-medium">
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

                <div className="flex justify-between text-xs text-muted-foreground">
                  <span>Synced {processed} / {total} photos</span>
                  {failed > 0 && <span className="text-destructive font-medium">{failed} failed</span>}
                </div>
              </div>
            )}

            {/* Results/Details */}
            {syncStatus.status === 'completed' && (
              <p className="text-sm text-muted-foreground text-center leading-relaxed">
                All <strong>{processed}</strong> photos from your selection have been successfully uploaded to Google Photos. You can find them in your new library album!
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
              <p className="text-sm text-destructive text-center font-medium bg-destructive/10 p-3 rounded-lg border border-destructive/20 leading-relaxed">
                Error: {syncStatus.error || 'Google Photos API synchronization failed.'}
              </p>
            )}

            {/* Actions button */}
            <div className="pt-4 flex flex-col gap-2">
              {syncStatus.album_url && (
                <a
                  href={syncStatus.album_url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-primary hover:bg-accent text-primary-foreground font-semibold text-sm rounded-lg transition-colors shadow"
                >
                  Open Google Photos Album
                  <ExternalLink size={14} />
                </a>
              )}

              {gallerySlug && (
                <Link
                  href={`/g/${gallerySlug}`}
                  className="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-secondary hover:bg-muted text-foreground font-semibold text-sm rounded-lg transition-colors border border-border"
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
