import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { authFetch } from '../auth'
import { apiClient } from '../apiClient'

// ─── Types ────────────────────────────────────────────────────────────────────

export interface WeeklySetting {
  day_of_week: number // 0-6
  start_time: string // H:i
  end_time: string // H:i
  is_active: boolean
}

export interface AvailabilitySettingsResponse {
  settings: WeeklySetting[]
  timezone: string
  slot_interval_minutes: number
}

export interface AvailabilityExceptionItem {
  uuid: string
  date: string // Y-m-d
  start_time: string | null
  end_time: string | null
  is_closed: boolean
}

export interface BlockedSlotItem {
  uuid: string
  starts_at: string // ISO
  ends_at: string // ISO
  reason: string | null
  source: string
}

export interface StructuredAvailableSlot {
  starts_at: string // ISO timezone-aware
  ends_at: string // ISO timezone-aware
  display: string // H:i A
}

// ─── Query Helpers (Auth) ─────────────────────────────────────────────────────

export async function getAvailabilitySettings(): Promise<AvailabilitySettingsResponse> {
  return authFetch<AvailabilitySettingsResponse>('/availability/settings', {
    method: 'GET',
  })
}

export async function updateAvailabilitySettings(fields: {
  settings: WeeklySetting[]
  timezone: string
  slot_interval_minutes: number
}): Promise<void> {
  await authFetch<void>('/availability/settings', {
    method: 'PUT',
    body: JSON.stringify(fields),
  })
}

export async function getAvailabilityExceptions(): Promise<{ exceptions: AvailabilityExceptionItem[] }> {
  return authFetch<{ exceptions: AvailabilityExceptionItem[] }>('/availability/exceptions', {
    method: 'GET',
  })
}

export async function createAvailabilityException(fields: {
  date: string
  start_time: string | null
  end_time: string | null
  is_closed: boolean
}): Promise<void> {
  await authFetch<void>('/availability/exceptions', {
    method: 'POST',
    body: JSON.stringify(fields),
  })
}

export async function deleteAvailabilityException(uuid: string): Promise<void> {
  await authFetch<void>(`/availability/exceptions/${uuid}`, {
    method: 'DELETE',
  })
}

export async function getBlockedSlots(): Promise<{ blocked_slots: BlockedSlotItem[] }> {
  return authFetch<{ blocked_slots: BlockedSlotItem[] }>('/availability/blocked', {
    method: 'GET',
  })
}

export async function createBlockedSlot(fields: {
  starts_at: string
  ends_at: string
  reason?: string | null
}): Promise<void> {
  await authFetch<void>('/availability/blocked', {
    method: 'POST',
    body: JSON.stringify(fields),
  })
}

export async function deleteBlockedSlot(uuid: string): Promise<void> {
  await authFetch<void>(`/availability/blocked/${uuid}`, {
    method: 'DELETE',
  })
}

// ─── Public slots (No Auth) ───────────────────────────────────────────────────

export async function getPublicAvailableSlots(params: {
  username: string
  date: string
  packageUuid: string
}): Promise<{ data: StructuredAvailableSlot[] }> {
  const query = new URLSearchParams({
    date: params.date,
    package_uuid: params.packageUuid,
  })

  return apiClient<{ data: StructuredAvailableSlot[] }>(
    `/public/photographers/${encodeURIComponent(params.username)}/slots?${query.toString()}`,
    { method: 'GET' }
  )
}

// ─── React Query Hooks ────────────────────────────────────────────────────────

export function useAvailabilitySettings() {
  return useQuery<AvailabilitySettingsResponse>({
    queryKey: ['availability-settings'],
    queryFn: getAvailabilitySettings,
  })
}

export function useUpdateAvailabilitySettingsMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: updateAvailabilitySettings,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['availability-settings'] })
    },
  })
}

export function useAvailabilityExceptions() {
  return useQuery<{ exceptions: AvailabilityExceptionItem[] }>({
    queryKey: ['availability-exceptions'],
    queryFn: getAvailabilityExceptions,
  })
}

export function useCreateAvailabilityExceptionMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: createAvailabilityException,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['availability-exceptions'] })
    },
  })
}

export function useDeleteAvailabilityExceptionMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: deleteAvailabilityException,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['availability-exceptions'] })
    },
  })
}

export function useBlockedSlots() {
  return useQuery<{ blocked_slots: BlockedSlotItem[] }>({
    queryKey: ['blocked-slots'],
    queryFn: getBlockedSlots,
  })
}

export function useCreateBlockedSlotMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: createBlockedSlot,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['blocked-slots'] })
    },
  })
}

export function useDeleteBlockedSlotMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: deleteBlockedSlot,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['blocked-slots'] })
    },
  })
}

export function usePublicAvailableSlots(params: {
  username: string
  date: string
  packageUuid: string
}) {
  return useQuery<{ data: StructuredAvailableSlot[] }>({
    queryKey: ['public-available-slots', params],
    queryFn: () => getPublicAvailableSlots(params),
    enabled: !!params.username && !!params.date && !!params.packageUuid,
    staleTime: 60 * 1000, // 1 min cache
  })
}

export interface PublicAvailableDaysResponse {
  month: string
  timezone: string
  booking_window: {
    min_date: string
    max_date: string
  }
  days: Record<string, {
    available: boolean
    slot_count: number
  }>
}

export async function getPublicAvailableDays(
  params: { username: string; month: string; packageUuid: string },
  signal?: AbortSignal
): Promise<PublicAvailableDaysResponse> {
  const query = new URLSearchParams({
    month: params.month,
    package_uuid: params.packageUuid,
  })

  return apiClient<PublicAvailableDaysResponse>(
    `/public/photographers/${encodeURIComponent(params.username)}/available-days?${query.toString()}`,
    { method: 'GET', signal }
  )
}

export function usePublicAvailableDays(params: {
  username: string
  month: string
  packageUuid: string
}) {
  return useQuery<PublicAvailableDaysResponse>({
    queryKey: ['public-available-days', params],
    queryFn: ({ signal }) => getPublicAvailableDays(params, signal),
    enabled: !!params.username && !!params.month && !!params.packageUuid,
    staleTime: 60 * 1000, // 1 min cache
  })
}
