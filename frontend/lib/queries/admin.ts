import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { authFetch } from '../auth'

export interface AdminStats {
  total_users: number;
  active_galleries: number;
  total_storage_bytes: number;
  total_revenue: number;
}

export interface QueueStats {
  queued: number;
  processing: number;
  completed: number;
  failed: number;
}

export interface AdminUser {
  uuid: string;
  name: string;
  email: string;
  joined: string;
  is_active: boolean;
  role: string;
  galleries_count: number;
  plan: string;
  storage_used_bytes: number;
  created_at: string;
}

export interface AdminGallery {
  uuid: string;
  title: string;
  owner: string;
  client_name: string | null;
  event_date: string | null;
  visibility: string;
  images: number;
  total_bytes: number;
  created_at: string;
}

export interface QueueJob {
  id: number;
  photo_uuid: string | null;
  filename: string;
  original_filename: string;
  stored_filename: string;
  gallery_title: string;
  studio_name: string;
  job_uuid: string | null;
  job_type: string | null;
  queue: string | null;
  status: 'queued' | 'processing' | 'completed' | 'failed';
  attempts: number;
  max_attempts: number;
  progress: string | null;
  started_at: string | null;
  completed_at: string | null;
  failed_at: string | null;
  duration_ms: number | null;
  error_message: string | null;
}

export interface AdminDashboardResponse {
  stats: AdminStats;
  queue_stats: QueueStats;
  recent_users: AdminUser[];
  recent_galleries: AdminGallery[];
  queue_jobs: QueueJob[];
}

export interface PaginatedResponse<T> {
  data: T[];
  links?: {
    first: string;
    last: string;
    prev: string | null;
    next: string | null;
  };
  meta?: {
    current_page: number;
    from: number;
    last_page: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
    path: string;
    per_page: number;
    to: number;
    total: number;
  };
}

export async function getAdminDashboard(): Promise<AdminDashboardResponse> {
  const res = await authFetch<{ data: AdminDashboardResponse }>('/admin/dashboard', {
    method: 'GET',
  })
  return res.data
}

export function useAdminDashboard(hasActiveJobs = false) {
  return useQuery<AdminDashboardResponse>({
    queryKey: ['adminDashboard'],
    queryFn: getAdminDashboard,
    refetchInterval: hasActiveJobs ? 2000 : 30000, // 2s when active, 30s when idle
  })
}

export async function getAdminQueue(status?: string, search?: string, page = 1): Promise<PaginatedResponse<QueueJob>> {
  const params = new URLSearchParams()
  if (status) params.append('status', status)
  if (search) params.append('search', search)
  params.append('page', String(page))

  return authFetch<PaginatedResponse<QueueJob>>(`/admin/queue?${params.toString()}`, {
    method: 'GET',
  })
}

export function useAdminQueue(status?: string, search?: string, page = 1, hasActiveJobs = false) {
  return useQuery<PaginatedResponse<QueueJob>>({
    queryKey: ['adminQueue', status, search, page],
    queryFn: () => getAdminQueue(status, search, page),
    refetchInterval: hasActiveJobs ? 2000 : 30000, // 2s when active, 30s when idle
    placeholderData: (previousData) => previousData,
  })
}

export async function getAdminUsers(search?: string, plan?: string, page = 1): Promise<PaginatedResponse<AdminUser>> {
  const params = new URLSearchParams()
  if (search) params.append('search', search)
  if (plan) params.append('plan', plan)
  params.append('page', String(page))

  return authFetch<PaginatedResponse<AdminUser>>(`/admin/users?${params.toString()}`, {
    method: 'GET',
  })
}

export function useAdminUsers(search?: string, plan?: string, page = 1) {
  return useQuery<PaginatedResponse<AdminUser>>({
    queryKey: ['adminUsers', search, plan, page],
    queryFn: () => getAdminUsers(search, plan, page),
    placeholderData: (previousData) => previousData,
  })
}

export interface SmtpSettings {
  host: string;
  port: number;
  username: string;
  encryption: string;
  from_address: string;
  from_name: string;
  has_password?: boolean;
}

export interface UpdateSmtpResponse {
  message: string;
  test_sent: boolean;
  test_error: string | null;
}

export async function getAdminSmtpSettings(): Promise<SmtpSettings> {
  const res = await authFetch<{ data: SmtpSettings }>('/admin/settings/smtp', {
    method: 'GET',
  })
  return res.data
}

export function useAdminSmtpSettings() {
  return useQuery<SmtpSettings>({
    queryKey: ['adminSmtpSettings'],
    queryFn: getAdminSmtpSettings,
  })
}

export async function updateAdminSmtpSettings(data: SmtpSettings & { password?: string; test_email?: string }): Promise<UpdateSmtpResponse> {
  return authFetch<UpdateSmtpResponse>('/admin/settings/smtp', {
    method: 'PUT',
    body: JSON.stringify(data),
  })
}

export function useUpdateAdminSmtpSettings() {
  const queryClient = useQueryClient()
  return useMutation<UpdateSmtpResponse, Error, SmtpSettings & { password?: string; test_email?: string }>({
    mutationFn: updateAdminSmtpSettings,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['adminSmtpSettings'] })
    },
  })
}
