import { useQuery } from '@tanstack/react-query'
import { authFetch } from '../auth'

export interface OverviewMetrics {
  total_views: number;
  prev_views: number;
  views_change_pct: number;
  total_downloads: number;
  prev_downloads: number;
  downloads_change_pct: number;
  unique_visitors: number;
  gallery_shares: number;
  avg_views_per_gallery: number;
  avg_downloads_per_gallery: number;
}

export interface MonthlyDataPoint {
  month: string;
  count: number;
}

export interface DownloadSource {
  source: string;
  count: number;
  percent: number;
}

export interface TopGallery {
  uuid: string;
  title: string;
  views: number;
  downloads: number;
  favorites: number;
}

export interface RecentActivityItem {
  event: string;
  gallery_title: string;
  created_at: string;
}

export interface AnalyticsData {
  period: '7d' | '30d' | '90d';
  overview: OverviewMetrics;
  monthly_views: MonthlyDataPoint[];
  downloads_by_source: DownloadSource[];
  top_galleries: TopGallery[];
  recent_activity: RecentActivityItem[];
}

export async function getAnalytics(period: '7d' | '30d' | '90d' = '30d'): Promise<AnalyticsData> {
  return authFetch<AnalyticsData>(`/analytics?period=${period}`, {
    method: 'GET',
  })
}

export function useAnalytics(period: '7d' | '30d' | '90d' = '30d') {
  return useQuery<AnalyticsData>({
    queryKey: ['analytics', period],
    queryFn: () => getAnalytics(period),
  })
}
