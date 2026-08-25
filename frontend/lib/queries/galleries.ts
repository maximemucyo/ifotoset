import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { authFetch } from '../auth'

export interface GalleryStats {
  photo_count: number;
  video_count: number;
  downloads_count: number;
  favorites_count: number;
  total_bytes: number;
}

export interface PhotoItem {
  uuid: string;
  filename: string;
  mime_type: string;
  size: number;
  width: number | null;
  height: number | null;
  blurhash: string | null;
  status: string;
  cdn_url: string;
  variants: {
    xs: string;
    sm: string;
    md: string;
    lg: string;
    xl: string;
  };
  taken_at: string | null;
  created_at: string;
  deleted_at?: string | null;
  trash_expires_at?: string | null;
  days_remaining?: number | null;
}

export interface GalleryInvitation {
  id: number;
  email: string;
  accepted: boolean;
  accepted_at: string | null;
  expired: boolean;
  expires_at: string | null;
  revoked: boolean;
  revoked_at: string | null;
  created_at: string;
}

export interface GalleryItem {
  uuid: string;
  title: string;
  slug: string;
  client_name: string | null;
  event_date: string | null;
  visibility: 'public' | 'private';
  allow_photo_downloads?: boolean;
  allow_gallery_downloads?: boolean;
  allow_google_photos?: boolean;
  has_password: boolean;
  password_hint: string | null;
  version: number;
  stats: GalleryStats;
  photos?: PhotoItem[];
  cover_photo?: PhotoItem | null;
  has_explicit_cover?: boolean;
  expires_at?: string | null;
  invitations?: GalleryInvitation[];
  created_at: string;
  deleted_at?: string | null;
  trash_expires_at?: string | null;
  days_remaining?: number | null;
  photographer?: {
    name: string;
    username: string;
    avatar_url: string | null;
  } | null;
  access_granted?: boolean;
  error_code?: string | null;
  error_message?: string | null;
  requires_password?: boolean;
  requires_invitation?: boolean;
}

export interface GalleriesResponse {
  data: GalleryItem[];
  links?: {
    first: string;
    last: string;
    prev: string | null;
    next: string | null;
  };
  meta?: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export async function getGalleries(page = 1, perPage = 10): Promise<GalleriesResponse> {
  return authFetch<GalleriesResponse>(`/galleries?page=${page}&per_page=${perPage}`, {
    method: 'GET',
  })
}

export function useGalleries(page = 1, perPage = 10) {
  return useQuery<GalleriesResponse>({
    queryKey: ['galleries', page, perPage],
    queryFn: () => getGalleries(page, perPage),
  })
}

export async function getGallery(uuid: string): Promise<{ data: GalleryItem }> {
  return authFetch<{ data: GalleryItem }>(`/galleries/${uuid}`, {
    method: 'GET',
  })
}

export function useGallery(uuid: string) {
  return useQuery<{ data: GalleryItem }>({
    queryKey: ['gallery', uuid],
    queryFn: () => getGallery(uuid),
    enabled: !!uuid,
  })
}

export interface CreateGalleryRequest {
  title: string;
  slug: string;
  client_name?: string;
  event_date?: string;
  visibility?: 'public' | 'private';
  password?: string;
  password_hint?: string;
  invite_emails?: string[];
  access_method?: 'password' | 'invite';
}

export async function createGallery(fields: CreateGalleryRequest): Promise<{ data: GalleryItem }> {
  return authFetch<{ data: GalleryItem }>('/galleries', {
    method: 'POST',
    body: JSON.stringify(fields),
  })
}

export function useCreateGalleryMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: createGallery,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['galleries'] })
      queryClient.invalidateQueries({ queryKey: ['dashboardStats'] })
    },
  })
}

export interface UpdateGalleryRequest {
  uuid: string;
  title?: string;
  client_name?: string | null;
  visibility?: 'public' | 'private';
  allow_photo_downloads?: boolean;
  allow_gallery_downloads?: boolean;
  allow_google_photos?: boolean;
  cover_photo_uuid?: string | null;
  clear_cover?: boolean;
  version: number;
}

export async function updateGallery({ uuid, ...fields }: UpdateGalleryRequest): Promise<{ data: GalleryItem }> {
  return authFetch<{ data: GalleryItem }>(`/galleries/${uuid}`, {
    method: 'PATCH',
    body: JSON.stringify(fields),
  })
}

export function useUpdateGalleryMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: updateGallery,
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: ['galleries'] })
      queryClient.invalidateQueries({ queryKey: ['gallery', data.data.uuid] })
    },
  })
}

export async function deleteGallery(uuid: string): Promise<void> {
  await authFetch<void>(`/galleries/${uuid}`, {
    method: 'DELETE',
  })
}

export function useDeleteGalleryMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: deleteGallery,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['galleries'] })
      queryClient.invalidateQueries({ queryKey: ['dashboardStats'] })
    },
  })
}

export async function getPublicGallery(
  slug: string,
  inviteToken?: string | null,
  galleryToken?: string | null,
  username?: string | null
): Promise<{ data: GalleryItem }> {
  const headers: Record<string, string> = {}
  if (galleryToken) {
    headers['X-Gallery-Token'] = galleryToken
  }

  let url = `/public/galleries/${slug}`
  const params = new URLSearchParams()
  if (inviteToken) params.append('invite', inviteToken)
  if (username) params.append('username', username)
  const queryStr = params.toString()
  if (queryStr) {
    url += `?${queryStr}`
  }

  return authFetch<{ data: GalleryItem }>(url, {
    method: 'GET',
    headers,
  })
}

export async function unlockPublicGallery(
  slug: string,
  password: string
): Promise<{ token: string; message: string }> {
  return authFetch<{ token: string; message: string }>(`/public/galleries/${slug}/unlock`, {
    method: 'POST',
    body: JSON.stringify({ password }),
  })
}

export interface PaginatedPhotosResponse {
  data: PhotoItem[];
  next_cursor: string | null;
  has_more: boolean;
}

export async function getGalleryPhotos(
  uuid: string,
  cursor?: string | null,
  perPage = 60
): Promise<PaginatedPhotosResponse> {
  let url = `/galleries/${uuid}/photos?per_page=${perPage}`
  if (cursor) {
    url += `&cursor=${cursor}`
  }
  return authFetch<PaginatedPhotosResponse>(url, {
    method: 'GET',
  })
}

export async function getPublicGalleryPhotos(
  slug: string,
  cursor?: string | null,
  perPage = 60,
  inviteToken?: string | null,
  galleryToken?: string | null,
  username?: string | null
): Promise<PaginatedPhotosResponse> {
  const headers: Record<string, string> = {}
  if (galleryToken) {
    headers['X-Gallery-Token'] = galleryToken
  }

  let url = `/public/galleries/${slug}/photos?per_page=${perPage}`
  if (cursor) {
    url += `&cursor=${cursor}`
  }
  if (inviteToken) {
    url += `&invite=${inviteToken}`
  }
  if (username) {
    url += `&username=${username}`
  }

  return authFetch<PaginatedPhotosResponse>(url, {
    method: 'GET',
    headers,
  })
}

export async function deletePhoto(photoUuid: string): Promise<void> {
  await authFetch<void>(`/photos/${photoUuid}`, {
    method: 'DELETE',
  })
}

export function useDeletePhotoMutation(galleryUuid: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: deletePhoto,
    onMutate: async (photoUuid) => {
      await queryClient.cancelQueries({ queryKey: ['gallery', galleryUuid] })

      const previousGallery = queryClient.getQueryData<{ data: GalleryItem }>(['gallery', galleryUuid])

      if (previousGallery) {
        queryClient.setQueryData<{ data: GalleryItem }>(['gallery', galleryUuid], {
          ...previousGallery,
          data: {
            ...previousGallery.data,
            photos: previousGallery.data.photos?.filter((p) => p.uuid !== photoUuid),
            stats: {
              ...previousGallery.data.stats,
              photo_count: Math.max(0, previousGallery.data.stats.photo_count - 1),
            },
          },
        })
      }

      return { previousGallery }
    },
    onError: (err, photoUuid, context) => {
      if (context?.previousGallery) {
        queryClient.setQueryData(['gallery', galleryUuid], context.previousGallery)
      }
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ['gallery', galleryUuid] })
      queryClient.invalidateQueries({ queryKey: ['galleries'] })
      queryClient.invalidateQueries({ queryKey: ['dashboardStats'] })
    },
  })
}

export async function recordPublicPhotoDownload(
  slug: string,
  photoUuid: string,
  email?: string | null,
  inviteToken?: string | null,
  galleryToken?: string | null
): Promise<void> {
  const headers: Record<string, string> = {}
  if (galleryToken) {
    headers['X-Gallery-Token'] = galleryToken
  }

  let url = `/public/galleries/${slug}/download`
  if (inviteToken) {
    url += `?invite=${inviteToken}`
  }

  await authFetch<void>(url, {
    method: 'POST',
    headers,
    body: JSON.stringify({ photo_uuid: photoUuid, email }),
  })
}

export async function togglePublicPhotoFavorite(
  slug: string,
  photoUuid: string,
  isFavorite: boolean,
  email?: string | null,
  inviteToken?: string | null,
  galleryToken?: string | null
): Promise<void> {
  const headers: Record<string, string> = {}
  if (galleryToken) {
    headers['X-Gallery-Token'] = galleryToken
  }

  let url = `/public/galleries/${slug}/favorite`
  if (inviteToken) {
    url += `?invite=${inviteToken}`
  }

  await authFetch<void>(url, {
    method: 'POST',
    headers,
    body: JSON.stringify({ photo_uuid: photoUuid, is_favorite: isFavorite, email }),
  })
}

export interface TriggerZipResponse {
  status: 'pending' | 'processing' | 'ready' | 'empty' | 'failed';
  download_id: number;
  download_url?: string;
  size?: number;
}

export async function triggerGalleryZip(
  slug: string,
  email?: string | null,
  notifyWhenReady?: boolean,
  inviteToken?: string | null,
  galleryToken?: string | null
): Promise<TriggerZipResponse> {
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
  }
  if (galleryToken) {
    headers['X-Gallery-Token'] = galleryToken
  }

  let url = `/public/galleries/${slug}/download-zip`
  if (inviteToken) {
    url += `?invite=${inviteToken}`
  }

  return authFetch<TriggerZipResponse>(url, {
    method: 'POST',
    headers,
    body: JSON.stringify({ email, notify_when_ready: notifyWhenReady }),
  })
}

export interface ZipStatusResponse {
  id: number;
  status: 'pending' | 'processing' | 'ready' | 'ready_with_errors' | 'empty' | 'failed';
  email: string | null;
  notify_when_ready: boolean;
  total_photos: number;
  processed_photos: number;
  failed_photos: number;
  error: string | null;
  download_url: string | null;
  size: number | null;
  percentage: number;
  remaining_seconds: number | null;
  estimated_finish_time: string | null;
}

export async function getGalleryZipStatus(
  slug: string,
  downloadId: number,
  inviteToken?: string | null,
  galleryToken?: string | null
): Promise<ZipStatusResponse> {
  const headers: Record<string, string> = {}
  if (galleryToken) {
    headers['X-Gallery-Token'] = galleryToken
  }

  let url = `/public/galleries/${slug}/download-zip/${downloadId}`
  if (inviteToken) {
    url += `?invite=${inviteToken}`
  }

  return authFetch<ZipStatusResponse>(url, {
    method: 'GET',
    headers,
  })
}

export interface GooglePhotosAuthorizeResponse {
  url: string;
  state: string;
}

export async function authorizeGooglePhotos(
  slug: string,
  photoUuids?: string[] | null,
  email?: string | null,
  notifyWhenReady?: boolean,
  inviteToken?: string | null,
  galleryToken?: string | null
): Promise<GooglePhotosAuthorizeResponse> {
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
  }
  if (galleryToken) {
    headers['X-Gallery-Token'] = galleryToken
  }

  let url = `/public/galleries/${slug}/google-photos/authorize`
  if (inviteToken) {
    url += `?invite=${inviteToken}`
  }

  return authFetch<GooglePhotosAuthorizeResponse>(url, {
    method: 'POST',
    headers,
    body: JSON.stringify({
      photo_uuids: photoUuids || null,
      email,
      notify_when_ready: notifyWhenReady
    }),
  })
}

export interface GooglePhotosCallbackResponse {
  status: 'processing';
  sync_uuid: string;
  gallery_slug: string;
}

export async function callbackGooglePhotos(
  code: string,
  state: string
): Promise<GooglePhotosCallbackResponse> {
  return authFetch<GooglePhotosCallbackResponse>('/public/google-photos/callback', {
    method: 'POST',
    body: JSON.stringify({ code, state }),
  })
}

export interface GooglePhotosSyncStatus {
  uuid: string;
  status: 'pending' | 'processing' | 'completed' | 'completed_with_errors' | 'failed';
  email: string | null;
  notify_when_ready: boolean;
  total_photos: number;
  processed_photos: number;
  failed_photos: number;
  album_url?: string | null;
  error?: string | null;
  completed_at?: string | null;
  percentage?: number;
  remaining_seconds?: number | null;
  estimated_finish_time?: string | null;
}

export async function getGooglePhotosSyncStatus(
  slug: string,
  syncUuid: string,
  inviteToken?: string | null,
  galleryToken?: string | null
): Promise<GooglePhotosSyncStatus> {
  const headers: Record<string, string> = {}
  if (galleryToken) {
    headers['X-Gallery-Token'] = galleryToken
  }

  let url = `/public/galleries/${slug}/google-photos/syncs/${syncUuid}/status`
  if (inviteToken) {
    url += `?invite=${inviteToken}`
  }

  return authFetch<GooglePhotosSyncStatus>(url, {
    method: 'GET',
    headers,
  })
}

export async function updateGooglePhotosSyncNotification(
  slug: string,
  syncUuid: string,
  email: string,
  notifyWhenReady: boolean,
  inviteToken?: string | null,
  galleryToken?: string | null
): Promise<any> {
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
  }
  if (galleryToken) {
    headers['X-Gallery-Token'] = galleryToken
  }

  let url = `/public/galleries/${slug}/google-photos/syncs/${syncUuid}/notify`
  if (inviteToken) {
    url += `?invite=${inviteToken}`
  }

  return authFetch<any>(url, {
    method: 'POST',
    headers,
    body: JSON.stringify({ email, notify_when_ready: notifyWhenReady }),
  })
}

