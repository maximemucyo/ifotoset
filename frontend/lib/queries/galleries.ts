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
  has_password: boolean;
  password_hint: string | null;
  version: number;
  stats: GalleryStats;
  photos?: PhotoItem[];
  cover_photo?: PhotoItem | null;
  expires_at?: string | null;
  invitations?: GalleryInvitation[];
  created_at: string;
  deleted_at?: string | null;
  trash_expires_at?: string | null;
  days_remaining?: number | null;
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
  galleryToken?: string | null
): Promise<{ data: GalleryItem }> {
  const headers: Record<string, string> = {}
  if (galleryToken) {
    headers['X-Gallery-Token'] = galleryToken
  }

  let url = `/public/galleries/${slug}`
  if (inviteToken) {
    url += `?invite=${inviteToken}`
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
  galleryToken?: string | null
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
