import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { authFetch } from '../auth'
import { ClientItem } from './clients'
import { PackageItem } from './packages'

export type BookingStatus = 'pending' | 'confirmed' | 'completed' | 'cancelled';

export interface BookingItem {
  uuid: string;
  reference: string;
  title: string;
  client?: ClientItem | null;
  package?: PackageItem | null;
  starts_at: string;
  ends_at: string | null;
  timezone: string;
  location: string | null;
  status: BookingStatus;
  price: number | null;
  currency: string;
  notes: string | null;
  created_at: string;
}

export interface BookingsResponse {
  data: BookingItem[];
  links: {
    first: string;
    last: string;
    prev: string | null;
    next: string | null;
  };
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface CreateBookingRequest {
  title: string;
  client_id?: string | null;
  package_id?: string | null;
  starts_at: string;
  ends_at?: string | null;
  timezone?: string;
  location?: string | null;
  status?: BookingStatus;
  price?: number | null;
  currency?: string;
  notes?: string | null;
  ignore_overlap?: boolean;
}

export interface UpdateBookingRequest extends Partial<CreateBookingRequest> {
  uuid: string;
}

export async function getBookings(params: {
  status?: string;
  from?: string;
  to?: string;
  client?: string;
  package?: string;
  page?: number;
  per_page?: number;
} = {}): Promise<BookingsResponse> {
  const query = new URLSearchParams()
  if (params.status) query.append('status', params.status)
  if (params.from) query.append('from', params.from)
  if (params.to) query.append('to', params.to)
  if (params.client) query.append('client', params.client)
  if (params.package) query.append('package', params.package)
  if (params.page) query.append('page', params.page.toString())
  if (params.per_page) query.append('per_page', params.per_page.toString())

  return authFetch<BookingsResponse>(`/bookings?${query.toString()}`, {
    method: 'GET',
  })
}

export function useBookings(params: {
  status?: string;
  from?: string;
  to?: string;
  client?: string;
  package?: string;
  page?: number;
  per_page?: number;
} = {}) {
  return useQuery<BookingsResponse>({
    queryKey: ['bookings', params],
    queryFn: () => getBookings(params),
  })
}

export async function getBooking(uuid: string): Promise<{ data: BookingItem }> {
  return authFetch<{ data: BookingItem }>(`/bookings/${uuid}`, {
    method: 'GET',
  })
}

export function useBooking(uuid: string) {
  return useQuery<{ data: BookingItem }>({
    queryKey: ['booking', uuid],
    queryFn: () => getBooking(uuid),
    enabled: !!uuid,
  })
}

export async function createBooking(fields: CreateBookingRequest): Promise<{ data: BookingItem }> {
  return authFetch<{ data: BookingItem }>('/bookings', {
    method: 'POST',
    body: JSON.stringify(fields),
  })
}

export function useCreateBookingMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: createBooking,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['bookings'] })
    },
  })
}

export async function updateBooking({ uuid, ...fields }: UpdateBookingRequest): Promise<{ data: BookingItem }> {
  return authFetch<{ data: BookingItem }>(`/bookings/${uuid}`, {
    method: 'PATCH',
    body: JSON.stringify(fields),
  })
}

export function useUpdateBookingMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: updateBooking,
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: ['booking', data.data.uuid] })
      queryClient.invalidateQueries({ queryKey: ['bookings'] })
    },
  })
}

export async function deleteBooking(uuid: string): Promise<void> {
  await authFetch<void>(`/bookings/${uuid}`, {
    method: 'DELETE',
  })
}

export function useDeleteBookingMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: deleteBooking,
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ['bookings'] })
    },
  })
}
