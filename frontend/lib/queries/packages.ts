import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { authFetch } from '../auth'

export interface PackageItem {
  uuid: string;
  name: string;
  description: string | null;
  price: number;
  currency: string;
  duration_minutes: number;
  duration_label: string;
  deliverables: string[];
  sort_order: number;
  is_active: boolean;
  created_at: string;
}

export interface PackagesResponse {
  data: PackageItem[];
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

export interface CreatePackageRequest {
  name: string;
  description?: string | null;
  price: number;
  currency?: string;
  duration_minutes: number;
  deliverables: string[];
  sort_order?: number;
  is_active?: boolean;
}

export interface UpdatePackageRequest extends Partial<CreatePackageRequest> {
  uuid: string;
}

export async function getPackages(params: { active?: boolean; sort?: string } = {}): Promise<PackagesResponse> {
  const query = new URLSearchParams()
  if (params.active !== undefined) query.append('active', params.active ? '1' : '0')
  if (params.sort) query.append('sort', params.sort)

  return authFetch<PackagesResponse>(`/packages?${query.toString()}`, {
    method: 'GET',
  })
}

export function useStudioPackages(params: { active?: boolean; sort?: string } = {}) {
  return useQuery<PackagesResponse>({
    queryKey: ['packages', params],
    queryFn: () => getPackages(params),
  })
}

export async function createPackage(fields: CreatePackageRequest): Promise<{ data: PackageItem }> {
  return authFetch<{ data: PackageItem }>('/packages', {
    method: 'POST',
    body: JSON.stringify(fields),
  })
}

export function useCreatePackageMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: createPackage,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['packages'] })
    },
  })
}

export async function updatePackage({ uuid, ...fields }: UpdatePackageRequest): Promise<{ data: PackageItem }> {
  return authFetch<{ data: PackageItem }>(`/packages/${uuid}`, {
    method: 'PATCH',
    body: JSON.stringify(fields),
  })
}

export function useUpdatePackageMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: updatePackage,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['packages'] })
    },
  })
}

export async function deletePackage(uuid: string): Promise<void> {
  await authFetch<void>(`/packages/${uuid}`, {
    method: 'DELETE',
  })
}

export function useDeletePackageMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: deletePackage,
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ['packages'] })
    },
  })
}
