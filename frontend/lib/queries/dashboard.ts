import { useQuery } from '@tanstack/react-query'
import { authFetch } from '../auth'
import { StorageStats } from './auth'

export interface DashboardStats {
  active_galleries: number;
  total_downloads: number;
  total_favorites: number;
  storage: StorageStats;
}

export interface RecentGallery {
  uuid: string;
  title: string;
  slug: string;
  client_name: string | null;
  event_date: string | null;
  visibility: 'public' | 'private';
  has_password: boolean;
  version: number;
  stats: {
    photo_count: number;
    video_count: number;
    downloads_count: number;
    favorites_count: number;
    total_bytes: number;
  };
  created_at: string;
}

export interface DashboardResponse {
  stats: DashboardStats;
  recent_galleries: RecentGallery[];
}

export async function getDashboardStats(): Promise<DashboardResponse> {
  const res = await authFetch<{ data: DashboardResponse }>('/dashboard', {
    method: 'GET',
  })
  return res.data
}

export function useDashboardStats() {
  return useQuery<DashboardResponse>({
    queryKey: ['dashboardStats'],
    queryFn: getDashboardStats,
    staleTime: 60 * 1000, // 1 minute
  })
}
