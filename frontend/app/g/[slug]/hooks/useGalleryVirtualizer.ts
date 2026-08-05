import { useMemo } from 'react';
import { ComputedPhotoLayout } from './useGalleryLayout';

export interface VirtualizedResult {
  visiblePhotos: ComputedPhotoLayout[];
}

export function useGalleryVirtualizer(
  photos: ComputedPhotoLayout[],
  scrollTop: number,
  viewportHeight: number
): VirtualizedResult {
  return useMemo(() => {
    if (photos.length === 0 || viewportHeight <= 0) {
      return { visiblePhotos: [] };
    }

    const OVERSCAN_PX = 800;
    // Safety margin to account for photo heights
    const MAX_HEIGHT_MARGIN = 1000;
    const startY = Math.max(0, scrollTop - OVERSCAN_PX - MAX_HEIGHT_MARGIN);
    const endY = scrollTop + viewportHeight + OVERSCAN_PX;

    let startIdx = 0;

    // Binary search for the first photo where y >= startY
    let low = 0;
    let high = photos.length - 1;
    while (low <= high) {
      const mid = Math.floor((low + high) / 2);
      if (photos[mid].y >= startY) {
        startIdx = mid;
        high = mid - 1;
      } else {
        low = mid + 1;
      }
    }

    const visiblePhotos: ComputedPhotoLayout[] = [];
    const viewTop = Math.max(0, scrollTop - OVERSCAN_PX);

    for (let i = startIdx; i < photos.length; i++) {
      const item = photos[i];
      if (item.y > endY) {
        break;
      }

      if (item.bottom >= viewTop) {
        visiblePhotos.push(item);
      }
    }

    return { visiblePhotos };
  }, [photos, scrollTop, viewportHeight]);
}

