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

