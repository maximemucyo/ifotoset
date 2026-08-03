import { authFetch } from './auth';

export interface UploadSessionRequest {
  gallery_id: string;
  filename: string;
  file_size: number;
  mime_type: string;
  sha256: string;
  idempotency_key?: string;
}

export interface UploadSessionResponse {
  upload_session_id: string;
  object_key: string;
  presigned_url: string;
  headers: Record<string, string>;
  expires_at: string;
}

export interface PhotoConfirmResponse {
  photo_id: string;
  filename: string;
  status: string;
  cdn_url: string;
}

/**
 * Calculates SHA-256 checksum of a File or Blob in the browser.
 */
export async function calculateSha256(file: File): Promise<string> {
  const arrayBuffer = await file.arrayBuffer();
  const hashBuffer = await crypto.subtle.digest('SHA-256', arrayBuffer);
  const hashArray = Array.from(new Uint8Array(hashBuffer));
  return hashArray.map((b) => b.toString(16).padStart(2, '0')).join('');
}

/**
 * Executes a zero-bandwidth direct browser upload to Backblaze B2 via presigned URL.
 */
export async function uploadPhotoDirectly(
  galleryId: string,
  file: File,
  onProgress?: (percent: number) => void
): Promise<PhotoConfirmResponse> {
  // 1. Calculate SHA-256 checksum in browser
  const sha256 = await calculateSha256(file);
  const idempotencyKey = `up-${galleryId}-${file.name}-${file.size}-${sha256.substring(0, 16)}`;

  // 2. Request presigned upload session from Laravel API
  const session = await authFetch<UploadSessionResponse>('/uploads/request', {
    method: 'POST',
    headers: {
      'Idempotency-Key': idempotencyKey,
    },
    body: JSON.stringify({
      gallery_id: galleryId,
      filename: file.name,
      file_size: file.size,
      mime_type: file.type || 'image/jpeg',
      sha256: sha256,
    }),
  });

  try {
    // 3. Direct HTTP PUT upload to Backblaze B2 presigned URL
    await new Promise<void>((resolve, reject) => {
      const xhr = new XMLHttpRequest();
      xhr.open('PUT', session.presigned_url, true);

      // Set checksum & required headers returned by presigned URL session
      if (session.headers) {
        Object.entries(session.headers).forEach(([key, value]) => {
          xhr.setRequestHeader(key, value);
        });
      }
      xhr.setRequestHeader('Content-Type', file.type || 'image/jpeg');
      xhr.setRequestHeader('x-amz-checksum-sha256', sha256);

      if (onProgress) {
        xhr.upload.onprogress = (e) => {
          if (e.lengthComputable) {
            const percent = Math.round((e.loaded / e.total) * 100);
            onProgress(percent);
          }
        };
      }

      xhr.onload = () => {
        if (xhr.status >= 200 && xhr.status < 300) {
          resolve();
        } else {
          reject(new Error(`B2 Upload failed with status ${xhr.status}: ${xhr.statusText}`));
        }
      };

      xhr.onerror = () => reject(new Error('Network error during B2 direct upload'));
      xhr.onabort = () => reject(new Error('B2 upload aborted by client'));

      xhr.send(file);
    });

    // 4. Confirm successful upload with Laravel API (triggers 0-byte HeadObject check & DB insertion)
    const confirmedPhoto = await authFetch<PhotoConfirmResponse>('/uploads/confirm', {
      method: 'POST',
      body: JSON.stringify({
        upload_session_id: session.upload_session_id,
      }),
    });

    return confirmedPhoto;
  } catch (error) {
    // If upload fails or is aborted, notify backend to clean up upload session
    await authFetch('/uploads/abort', {
      method: 'POST',
      body: JSON.stringify({
        upload_session_id: session.upload_session_id,
        reason: error instanceof Error ? error.message : 'Upload failed',
      }),
    }).catch(() => {}); // Silent catch on abort cleanup failure

    throw error;
  }
}
