import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { authFetch } from '../auth'

export interface ClientItem {
  uuid: string;
  name: string;
  email: string | null;
  phone: string | null;
  company_name: string | null;
  location: string | null;
  instagram: string | null;
  notes: string | null;
  tags: string[];
  last_contacted_at: string | null;
  bookings_count?: number;
  created_at: string;
}

export interface ClientsResponse {
  data: ClientItem[];
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

export interface CreateClientRequest {
  name: string;
  email?: string | null;
  phone?: string | null;
  company_name?: string | null;
  location?: string | null;
  instagram?: string | null;
  notes?: string | null;
  tags?: string[];
  last_contacted_at?: string | null;
}

export interface UpdateClientRequest extends Partial<CreateClientRequest> {
  uuid: string;
}

export async function getClients(params: {
  page?: number;
  search?: string;
  sort?: string;
  trashed?: boolean;
  per_page?: number;
} = {}): Promise<ClientsResponse> {
  const query = new URLSearchParams()
  if (params.page) query.append('page', params.page.toString())
  if (params.per_page) query.append('per_page', params.per_page.toString())
  if (params.search) query.append('search', params.search)
  if (params.sort) query.append('sort', params.sort)
  if (params.trashed) query.append('trashed', '1')

  const res = await authFetch<ClientsResponse>(`/clients?${query.toString()}`, {
    method: 'GET',
  })
  return res
}

export function useClients(params: {
  page?: number;
  search?: string;
  sort?: string;
  trashed?: boolean;
  per_page?: number;
} = {}) {
  return useQuery<ClientsResponse>({
    queryKey: ['clients', params],
    queryFn: () => getClients(params),
  })
}

export async function getClient(uuid: string): Promise<{ data: ClientItem }> {
  return authFetch<{ data: ClientItem }>(`/clients/${uuid}`, {
    method: 'GET',
  })
}

export function useClient(uuid: string) {
  return useQuery<{ data: ClientItem }>({
    queryKey: ['client', uuid],
    queryFn: () => getClient(uuid),
    enabled: !!uuid,
  })
}

export async function createClient(fields: CreateClientRequest): Promise<{ data: ClientItem }> {
  return authFetch<{ data: ClientItem }>('/clients', {
    method: 'POST',
    body: JSON.stringify(fields),
  })
}

export function useCreateClientMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: createClient,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['clients'] })
    },
  })
}

export async function updateClient({ uuid, ...fields }: UpdateClientRequest): Promise<{ data: ClientItem }> {
  return authFetch<{ data: ClientItem }>(`/clients/${uuid}`, {
    method: 'PATCH',
    body: JSON.stringify(fields),
  })
}

export function useUpdateClientMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: updateClient,
    onMutate: async (updatedClient) => {
      await queryClient.cancelQueries({ queryKey: ['client', updatedClient.uuid] })
      const previousClient = queryClient.getQueryData<{ data: ClientItem }>(['client', updatedClient.uuid])

      if (previousClient) {
        queryClient.setQueryData<{ data: ClientItem }>(['client', updatedClient.uuid], {
          ...previousClient,
          data: {
            ...previousClient.data,
            ...updatedClient,
          },
        })
      }

      return { previousClient }
    },
    onError: (err, updatedClient, context) => {
      if (context?.previousClient) {
        queryClient.setQueryData(['client', updatedClient.uuid], context.previousClient)
      }
    },
    onSettled: (data) => {
      if (data) {
        queryClient.invalidateQueries({ queryKey: ['client', data.data.uuid] })
      }
      queryClient.invalidateQueries({ queryKey: ['clients'] })
    },
  })
}

export async function deleteClient(uuid: string): Promise<void> {
  await authFetch<void>(`/clients/${uuid}`, {
    method: 'DELETE',
  })
}

export function useDeleteClientMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: deleteClient,
    onMutate: async (uuid) => {
      // Optimistically remove client from list cache where possible or just wait for settlement
      // (invalidation on settle ensures correct counts/meta)
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ['clients'] })
    },
  })
}
