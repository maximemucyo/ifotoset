export class ApiError extends Error {
  constructor(
    public status: number,
    public code: string,
    public message: string,
    public validationErrors?: Record<string, string[]>
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

/**
 * Generates a unique client-side request correlation ID (UUIDv4)
 */
function generateRequestId(): string {
  if (typeof crypto !== 'undefined' && crypto.randomUUID) {
    return crypto.randomUUID();
  }
  return 'req-' + Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
}

export async function apiClient<T>(
  endpoint: string,
  options: RequestInit = {},
  csrfToken?: string
): Promise<T> {
  const baseUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';
  const headers = new Headers(options.headers);

  headers.set('Accept', 'application/json');
  if (!headers.has('Content-Type') && !(options.body instanceof FormData)) {
    headers.set('Content-Type', 'application/json');
  }

  // Set Request Correlation ID
  if (!headers.has('X-Request-ID')) {
    headers.set('X-Request-ID', generateRequestId());
  }

  // Set Sanctum CSRF Token if present
  if (csrfToken && options.method && options.method !== 'GET') {
    headers.set('X-XSRF-TOKEN', decodeURIComponent(csrfToken));
  }

  const response = await fetch(`${baseUrl}/api/v1${endpoint}`, {
    ...options,
    headers,
    credentials: 'include', // Ensures Sanctum HttpOnly cookies are passed
  });

  if (!response.ok) {
    const errorData = await response.json().catch(() => ({
      code: 'SERVER_ERROR',
      message: 'An unexpected server error occurred.',
    }));

    throw new ApiError(
      response.status,
      errorData.code || 'HTTP_ERROR',
      errorData.message || response.statusText,
      errorData.errors
    );
  }

  // Handle empty 204 No Content responses
  if (response.status === 204) {
    return {} as T;
  }

  return response.json();
}
