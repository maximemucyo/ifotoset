import { apiClient } from './apiClient';

/**
 * Retrieves the XSRF-TOKEN cookie across Client Components and React Server Components.
 */
export async function getCsrfToken(): Promise<string | undefined> {
  if (typeof window === 'undefined') {
    // React Server Component (RSC execution environment)
    try {
      const { cookies } = await import('next/headers');
      const cookieStore = await cookies();
      return cookieStore.get('XSRF-TOKEN')?.value;
    } catch {
      return undefined;
    }
  } else {
    // Client-side environment
    const match = document.cookie.match(new RegExp('(^| )XSRF-TOKEN=([^;]+)'));
    return match ? match[2] : undefined;
  }
}

/**
 * Requests a fresh CSRF cookie from Laravel Sanctum.
 */
export async function initializeCsrf(): Promise<void> {
  const baseUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';
  await fetch(`${baseUrl}/sanctum/csrf-cookie`, {
    credentials: 'include',
  });
}

/**
 * Wrapper function for making authenticated API requests with Sanctum.
 */
export async function authFetch<T>(endpoint: string, options: RequestInit = {}): Promise<T> {
  let csrfToken = await getCsrfToken();

  // If making a mutating request without a CSRF token on client-side, initialize CSRF first
  if (!csrfToken && typeof window !== 'undefined' && options.method && options.method !== 'GET') {
    await initializeCsrf();
    csrfToken = await getCsrfToken();
  }

  return apiClient<T>(endpoint, options, csrfToken);
}
