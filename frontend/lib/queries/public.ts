import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../apiClient'
import { PackageItem } from './packages'

// ─── Types ────────────────────────────────────────────────────────────────────

export interface PhotographerProfile {
  name: string
  username: string
  bio: string | null
  avatar_url: string | null
  location: string | null
  website: string | null
  phone: string | null
}

export interface FeaturedGallery {
  uuid: string
  title: string
  slug: string
  client_name: string | null
  event_date: string | null
  cover_url: string | null
  photo_count: number
  featured_order: number
}

export interface PublicPackageItem extends PackageItem {
  deposit_type: 'none' | 'fixed' | 'percentage'
  deposit_amount: number | null
  computed_deposit_amount: number | null
}

export interface PublicPhotographerData {
  photographer: PhotographerProfile
  packages: PublicPackageItem[]
  featured_galleries: FeaturedGallery[]
}

export interface SubmitPublicBookingRequest {
  title: string
  client_name: string
  client_email: string
  client_phone?: string | null
  package_id: string
  starts_at: string
  ends_at?: string | null
  location?: string | null
  notes?: string | null
  ignore_overlap?: boolean
  _h?: string  // honeypot — always send empty string
}

export interface PublicBookingResult {
  uuid: string
  reference: string
  title: string
  starts_at: string
  ends_at: string | null
  status: string
  price: number | null
  currency: string
  location: string | null
}

export interface InitiatePublicPaymentRequest {
  booking_uuid: string
  phone_number: string
  provider: 'MTN' | 'AIRTEL'
  idempotency_key: string
  _h?: string  // honeypot
}

export interface PublicPaymentResult {
  payment_uuid: string
  status: string
  amount: number
  currency: string
}

export interface PublicPaymentStatusResult {
  uuid: string
  amount: number
  currency: string
  phone_number: string
  provider: string
  status: 'created' | 'submitted' | 'pending' | 'processing' | 'completed' | 'failed' | 'expired' | 'cancelled'
  error_message: string | null
}

// ─── Public fetch (no auth cookie required) ───────────────────────────────────

async function publicFetch<T>(endpoint: string, options: RequestInit = {}): Promise<T> {
  return apiClient<T>(endpoint, options)
}

// ─── Photographer profile ──────────────────────────────────────────────────────

export async function getPhotographerProfile(username: string): Promise<PublicPhotographerData> {
  return publicFetch<PublicPhotographerData>(`/public/photographers/${encodeURIComponent(username)}`, {
    method: 'GET',
  })
}

export function usePhotographerProfile(username: string) {
  return useQuery<PublicPhotographerData>({
    queryKey: ['public-profile', username],
    queryFn: () => getPhotographerProfile(username),
    enabled: !!username,
    staleTime: 2 * 60 * 1000,
    retry: false,
  })
}

// ─── Public booking submission ─────────────────────────────────────────────────

export async function submitPublicBooking(
  username: string,
  fields: SubmitPublicBookingRequest
): Promise<{ data: PublicBookingResult }> {
  return publicFetch<{ data: PublicBookingResult }>(`/public/booking/${encodeURIComponent(username)}`, {
    method: 'POST',
    body: JSON.stringify({ ...fields, _h: '' }), // honeypot always empty for real clients
  })
}

export function useSubmitPublicBookingMutation(username: string) {
  return useMutation({
    mutationFn: (fields: SubmitPublicBookingRequest) => submitPublicBooking(username, fields),
  })
}

// ─── Public booking deposit payment ───────────────────────────────────────────

export async function initiatePublicPayment(
  bookingUuid: string,
  fields: Omit<InitiatePublicPaymentRequest, 'booking_uuid'>
): Promise<PublicPaymentResult> {
  return publicFetch<PublicPaymentResult>(`/public/bookings/${encodeURIComponent(bookingUuid)}/payments`, {
    method: 'POST',
    body: JSON.stringify({ ...fields, _h: '' }),
  })
}

export function useInitiatePublicPaymentMutation() {
  return useMutation({
    mutationFn: ({
      booking_uuid,
      ...rest
    }: InitiatePublicPaymentRequest) => initiatePublicPayment(booking_uuid, rest),
  })
}

// ─── Public payment status polling ────────────────────────────────────────────

export async function getPublicPaymentStatus(paymentUuid: string): Promise<PublicPaymentStatusResult> {
  return publicFetch<PublicPaymentStatusResult>(`/public/payments/${paymentUuid}/status`, {
    method: 'GET',
  })
}

export function usePublicPaymentStatus(paymentUuid: string | null) {
  return useQuery<PublicPaymentStatusResult>({
    queryKey: ['public-payment-status', paymentUuid],
    queryFn: () => getPublicPaymentStatus(paymentUuid!),
    enabled: !!paymentUuid,
    refetchInterval: (query) => {
      const data = query.state.data
      if (data && ['completed', 'failed', 'expired', 'cancelled'].includes(data.status)) {
        return false
      }
      return 3000
    },
  })
}

// ─── Public Photographer Reviews ──────────────────────────────────────────────

export interface PublicReviewItem {
  uuid: string
  name: string
  quote: string
  rating: number
  detail: string | null
  date: string
}

export interface SubmitPublicReviewRequest {
  name: string
  quote: string
  rating: number
  detail?: string | null
  _h?: string
}

export async function getPhotographerReviews(username: string): Promise<PublicReviewItem[]> {
  const res = await publicFetch<{ data: PublicReviewItem[] }>(`/public/photographers/${encodeURIComponent(username)}/reviews`, {
    method: 'GET',
  })
  return res.data
}

export async function submitPhotographerReview(
  username: string,
  payload: SubmitPublicReviewRequest
): Promise<PublicReviewItem> {
  return publicFetch<PublicReviewItem>(`/public/photographers/${encodeURIComponent(username)}/reviews`, {
    method: 'POST',
    body: JSON.stringify({ ...payload, _h: '' }), // honey pot empty by default
  })
}

export function usePhotographerReviews(username: string) {
  return useQuery<PublicReviewItem[], Error>({
    queryKey: ['photographer-reviews', username],
    queryFn: () => getPhotographerReviews(username),
    enabled: !!username,
    staleTime: 2 * 60 * 1000,
  })
}

export function useSubmitPhotographerReview(username: string) {
  const queryClient = useQueryClient()
  return useMutation<PublicReviewItem, Error, SubmitPublicReviewRequest>({
    mutationFn: (payload) => submitPhotographerReview(username, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['photographer-reviews', username] })
    },
  })
}
