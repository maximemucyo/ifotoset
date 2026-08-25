import { useState, useEffect, useCallback, useRef } from 'react';
import { PhotoItem, getPublicGalleryPhotos } from '@/lib/queries/galleries';

export function useInfiniteGallery(
  slug: string,
  inviteToken: string | null,
  galleryToken: string | null,
  pageSize = 60,
  initialPhotos?: PhotoItem[],
  initialNextCursor?: string | null,
  initialHasMore?: boolean
) {
  const [photos, setPhotos] = useState<PhotoItem[]>(initialPhotos || []);
  const [nextCursor, setNextCursor] = useState<string | null>(initialNextCursor || null);
  const [hasMore, setHasMore] = useState(initialHasMore !== undefined ? initialHasMore : true);
  const [isFetchingNextPage, setIsFetchingNextPage] = useState(false);
  const [error, setError] = useState<Error | null>(null);

  const fetchingRef = useRef(false);

  const fetchNextPage = useCallback(async (isInitial = false) => {
    if (fetchingRef.current) return;
    if (!isInitial && !hasMore) return;

    fetchingRef.current = true;
    setIsFetchingNextPage(true);
    setError(null);

    try {
      const currentCursor = isInitial ? null : nextCursor;
      const res = await getPublicGalleryPhotos(
        slug,
        currentCursor,
        pageSize,
        inviteToken,
        galleryToken
      );

      setPhotos((prev) => {
        if (isInitial) return res.data;
        const existingUuids = new Set(prev.map((p) => p.uuid));
        const uniqueNewPhotos = res.data.filter((p) => !existingUuids.has(p.uuid));
        return [...prev, ...uniqueNewPhotos];
      });

      setNextCursor(res.next_cursor);
      setHasMore(res.has_more);
    } catch (err) {
      console.error("Error fetching photos page", err);
      setError(err instanceof Error ? err : new Error("Failed to fetch photos"));
    } finally {
      fetchingRef.current = false;
      setIsFetchingNextPage(false);
    }
  }, [slug, nextCursor, hasMore, pageSize, inviteToken, galleryToken]);

  useEffect(() => {
    if (slug) {
      if (initialPhotos && initialPhotos.length > 0) {
        // Skip initial fetch if we already have preloaded photos from the server component
        return;
      }
      fetchNextPage(true);
    }
  }, [slug, inviteToken, initialPhotos]);

  return {
    photos,
    hasMore,
    isFetchingNextPage,
    fetchNextPage,
    error,
  };
}
