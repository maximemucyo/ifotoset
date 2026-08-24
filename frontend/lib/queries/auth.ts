import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { authFetch } from '../auth';
import { uploadAvatarDirectly } from '../storage';

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
  username: string | null;
  email: string;
  role: 'admin' | 'photographer';
  plan: string;
  phone: string | null;
  location: string | null;
  website: string | null;
  bio: string | null;
  avatar_url: string | null;
  notification_preferences: {
    new_bookings: boolean;
    new_messages: boolean;
    gallery_activity: boolean;
    payment_received: boolean;
  } | null;
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

/**
 * Hook to update user profile.
 */
export function useUpdateProfileMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (fields: Record<string, any>) => {
      const res = await authFetch<{ data: User }>('/settings/profile', {
        method: 'PATCH',
        body: JSON.stringify(fields),
      });
      return res.data;
    },
    onMutate: async (updatedFields) => {
      await queryClient.cancelQueries({ queryKey: ['currentUser'] });
      const previousProfile = queryClient.getQueryData<UserProfile>(['currentUser']);

      if (previousProfile) {
        queryClient.setQueryData<UserProfile>(['currentUser'], {
          ...previousProfile,
          user: {
            ...previousProfile.user,
            ...updatedFields,
          },
        });
      }

      return { previousProfile };
    },
    onError: (err, variables, context) => {
      if (context?.previousProfile) {
        queryClient.setQueryData(['currentUser'], context.previousProfile);
      }
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ['currentUser'] });
    },
  });
}

/**
 * Hook to change user password.
 */
export function useChangePasswordMutation() {
  return useMutation({
    mutationFn: async (fields: Record<string, any>) => {
      await authFetch('/settings/password', {
        method: 'POST',
        body: JSON.stringify(fields),
      });
    },
  });
}

/**
 * Hook to update notification preferences.
 */
export function useUpdateNotificationsMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (fields: Record<string, boolean>) => {
      await authFetch('/settings/notifications', {
        method: 'PATCH',
        body: JSON.stringify(fields),
      });
      return fields;
    },
    onMutate: async (updatedPrefs) => {
      await queryClient.cancelQueries({ queryKey: ['currentUser'] });
      const previousProfile = queryClient.getQueryData<UserProfile>(['currentUser']);

      if (previousProfile) {
        queryClient.setQueryData<UserProfile>(['currentUser'], {
          ...previousProfile,
          user: {
            ...previousProfile.user,
            notification_preferences: {
              ...previousProfile.user.notification_preferences,
              ...updatedPrefs,
            } as any,
          },
        });
      }

      return { previousProfile };
    },
    onError: (err, variables, context) => {
      if (context?.previousProfile) {
        queryClient.setQueryData(['currentUser'], context.previousProfile);
      }
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ['currentUser'] });
    },
  });
}

/**
 * Hook to upload and confirm user avatar/logo.
 */
export function useUploadAvatarMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (file: File) => {
      return uploadAvatarDirectly(file);
    },
    onSuccess: (avatarUrl) => {
      const previousProfile = queryClient.getQueryData<UserProfile>(['currentUser']);
      if (previousProfile) {
        queryClient.setQueryData<UserProfile>(['currentUser'], {
          ...previousProfile,
          user: {
            ...previousProfile.user,
            avatar_url: avatarUrl,
          },
        });
      }
      queryClient.invalidateQueries({ queryKey: ['currentUser'] });
    },
  });
}

/**
 * Sends a password reset link to the given email.
 */
export async function forgotPasswordUser(email: string): Promise<{ message: string }> {
  return authFetch<{ message: string }>('/auth/forgot-password', {
    method: 'POST',
    body: JSON.stringify({ email }),
  });
}

/**
 * Resets the user's password using the token received by email.
 */
export async function resetPasswordUser(fields: Record<string, any>): Promise<{ message: string }> {
  return authFetch<{ message: string }>('/auth/reset-password', {
    method: 'POST',
    body: JSON.stringify(fields),
  });
}

/**
 * Hook to request a password reset link.
 */
export function useForgotPasswordMutation() {
  return useMutation({
    mutationFn: forgotPasswordUser,
  });
}

/**
 * Hook to reset password.
 */
export function useResetPasswordMutation() {
  return useMutation({
    mutationFn: resetPasswordUser,
  });
}

