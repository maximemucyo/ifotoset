import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { authFetch } from '../auth';

export interface StorageStats {
  plan_name: string;
  limit_bytes: number | null;
  active_bytes: number;
  trash_bytes: number;
  used_bytes: number;
  remaining_bytes: number | null;
  active_percent: number;
  trash_percent: number;
  percent_used: number;
  is_unlimited: boolean;
}

export interface User {
  uuid: string;
  name: string;
  email: string;
  role: 'admin' | 'photographer';
  plan: string;
  storage: StorageStats;
}

export interface UserProfile {
  user: User;
  permissions: string[];
}

/**
 * Retrieves the current authenticated user's profile info.
 */
export async function getCurrentUser(): Promise<UserProfile | null> {
  try {
    const res = await authFetch<{ data: UserProfile }>('/auth/user', {
      method: 'GET',
    });
    return res.data;
  } catch (error) {
    // If unauthorized (401), return null
    return null;
  }
}

/**
 * Logs in the user.
 */
export async function loginUser(credentials: Record<string, any>): Promise<UserProfile> {
  const res = await authFetch<{ data: UserProfile }>('/auth/login', {
    method: 'POST',
    body: JSON.stringify(credentials),
  });
  return res.data;
}

/**
 * Registers a new photographer.
 */
export async function registerUser(fields: Record<string, any>): Promise<UserProfile> {
  const res = await authFetch<{ data: UserProfile }>('/auth/register', {
    method: 'POST',
    body: JSON.stringify(fields),
  });
  return res.data;
}

/**
 * Logs out the photographer and clears auth sessions.
 */
export async function logoutUser(): Promise<void> {
  await authFetch('/auth/logout', {
    method: 'POST',
  });
}

/**
 * Hook to retrieve the current user details with TanStack Query.
 */
export function useCurrentUser() {
  return useQuery<UserProfile | null>({
    queryKey: ['currentUser'],
    queryFn: getCurrentUser,
    staleTime: 5 * 60 * 1000, // 5 minutes cache
  });
}

/**
 * Hook to log in a user with optimistic query cache updates.
 */
export function useLoginMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: loginUser,
    onMutate: async () => {
      // Cancel any in-flight /auth/user requests so they don't overwrite
      // the cache after login succeeds (race condition prevention)
      await queryClient.cancelQueries({ queryKey: ['currentUser'] });
    },
    onSuccess: (user) => {
      queryClient.setQueryData(['currentUser'], user);
    },
  });
}

/**
 * Hook to register a new user.
 */
export function useRegisterMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: registerUser,
    onSuccess: (user) => {
      queryClient.setQueryData(['currentUser'], user);
    },
  });
}

/**
 * Hook to log out a user.
 */
export function useLogoutMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: logoutUser,
    onSuccess: () => {
      queryClient.setQueryData(['currentUser'], null);
      queryClient.invalidateQueries({ queryKey: ['currentUser'] });
    },
  });
}
