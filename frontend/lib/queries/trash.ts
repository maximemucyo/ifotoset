import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { authFetch } from '../auth'
import { GalleryItem, PhotoItem } from './galleries'

export interface TrashResponse<T> {
  data: T[];
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

export async function getTrash<T>(type: 'gallery' | 'photo', page = 1, perPage = 10): Promise<TrashResponse<T>> {
  return authFetch<TrashResponse<T>>(`/trash?type=${type}&page=${page}&per_page=${perPage}`, {
    method: 'GET',
  })
}

export function useTrash<T = GalleryItem | PhotoItem>(type: 'gallery' | 'photo', page = 1, perPage = 10) {
  return useQuery<TrashResponse<T>>({
    queryKey: ['trash', type, page, perPage],
    queryFn: () => getTrash<T>(type, page, perPage),
  })
}

export interface TrashActionRequest {
  type: 'gallery' | 'photo';
  uuid: string;
}

export async function restoreTrash(payload: TrashActionRequest): Promise<any> {
  return authFetch<any>('/trash/restore', {
    method: 'POST',
    body: JSON.stringify(payload),
  })
}

export function useRestoreTrashMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: restoreTrash,
    onSuccess: (data, variables) => {
      // Optimistically remove from Trash queries list
      queryClient.setQueriesData<TrashResponse<any>>({ queryKey: ['trash', variables.type] }, (old) => {
        if (!old) return old
        return {
          ...old,
          data: old.data.filter((item) => item.uuid !== variables.uuid),
          meta: old.meta ? { ...old.meta, total: Math.max(0, old.meta.total - 1) } : undefined,
        }
      })

      // Invalidate queries to sync with backend
      queryClient.invalidateQueries({ queryKey: ['trash'] })
      queryClient.invalidateQueries({ queryKey: ['galleries'] })
      queryClient.invalidateQueries({ queryKey: ['dashboardStats'] })
    },
  })
}

export async function purgeTrash(payload: TrashActionRequest): Promise<any> {
  return authFetch<any>('/trash/purge', {
    method: 'DELETE',
    body: JSON.stringify(payload),
  })
}

export function usePurgeTrashMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: purgeTrash,
    onSuccess: (data, variables) => {
      // Optimistically remove from Trash queries list
      queryClient.setQueriesData<TrashResponse<any>>({ queryKey: ['trash', variables.type] }, (old) => {
        if (!old) return old
        return {
          ...old,
          data: old.data.filter((item) => item.uuid !== variables.uuid),
          meta: old.meta ? { ...old.meta, total: Math.max(0, old.meta.total - 1) } : undefined,
        }
      })

      // Delayed sync to allow background job to finish processing
      setTimeout(() => {
        queryClient.invalidateQueries({ queryKey: ['trash'] })
        queryClient.invalidateQueries({ queryKey: ['dashboardStats'] })
      }, 1500)
    },
  })
}

export async function emptyTrash(): Promise<any> {
  return authFetch<any>('/trash/empty', {
    method: 'POST',
  })
}

export function useEmptyTrashMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: emptyTrash,
    onSuccess: () => {
      // Optimistically clear all local trash queries cache lists
      queryClient.setQueriesData<TrashResponse<any>>({ queryKey: ['trash', 'gallery'] }, (old) => {
        if (!old) return old
        return { ...old, data: [], meta: old.meta ? { ...old.meta, total: 0 } : undefined }
      })
      queryClient.setQueriesData<TrashResponse<any>>({ queryKey: ['trash', 'photo'] }, (old) => {
        if (!old) return old
        return { ...old, data: [], meta: old.meta ? { ...old.meta, total: 0 } : undefined }
      })

      // Delayed sync to allow background jobs to finish processing
      setTimeout(() => {
        queryClient.invalidateQueries({ queryKey: ['trash'] })
        queryClient.invalidateQueries({ queryKey: ['dashboardStats'] })
      }, 1500)
    },
  })
}
