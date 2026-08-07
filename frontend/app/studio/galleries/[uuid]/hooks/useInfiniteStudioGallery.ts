import { useState, useEffect, useCallback, useRef } from 'react';
import { PhotoItem, getGalleryPhotos } from '@/lib/queries/galleries';

export function useInfiniteStudioGallery(uuid: string, pageSize = 60) {
  const [photos, setPhotos] = useState<PhotoItem[]>([]);
  const [nextCursor, setNextCursor] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(true);
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
      const res = await getGalleryPhotos(uuid, currentCursor, pageSize);

      setPhotos((prev) => {
        if (isInitial) return res.data;
        const existingUuids = new Set(prev.map((p) => p.uuid));
        const uniqueNewPhotos = res.data.filter((p) => !existingUuids.has(p.uuid));
        return [...prev, ...uniqueNewPhotos];
      });

      setNextCursor(res.next_cursor);
      setHasMore(res.has_more);
    } catch (err) {
      console.error('Error fetching studio photos page', err);
      setError(err instanceof Error ? err : new Error('Failed to fetch photos'));
    } finally {
      fetchingRef.current = false;
      setIsFetchingNextPage(false);
    }
  }, [uuid, nextCursor, hasMore, pageSize]);

  // Reset photos when uuid changes
  useEffect(() => {
    if (uuid) {
      fetchNextPage(true);
    }
  }, [uuid]);

  return {
    photos,
    setPhotos,
    hasMore,
    isFetchingNextPage,
    fetchNextPage,
    error,
  };
}
