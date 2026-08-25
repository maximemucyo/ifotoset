import { useMemo } from 'react';
import { PhotoItem } from '@/lib/queries/galleries';

export interface ComputedPhotoLayout {
  photo: PhotoItem;
  x: number;
  y: number;
  width: number;
  height: number;
  column: number;
  bottom: number;
}

export function useGalleryLayout(
  photos: PhotoItem[],
  containerWidth: number,
  gap = 6
) {
  return useMemo(() => {
    if (containerWidth <= 0 || photos.length === 0) {
      return { photos: [], totalHeight: 0 };
    }

    // Stable column count based on breakpoints
    let cols = 5;
    if (containerWidth < 640) {
      cols = 2;
    } else if (containerWidth < 768) {
      cols = 3;
    } else if (containerWidth < 1024) {
      cols = 4;
    }

    const colWidth = (containerWidth - (cols - 1) * gap) / cols;
    const colHeights = new Array(cols).fill(0);
    const computedPhotos: ComputedPhotoLayout[] = [];

    for (const photo of photos) {
      // Find shortest column
      let minColIdx = 0;
      for (let i = 1; i < cols; i++) {
        if (colHeights[i] < colHeights[minColIdx]) {
          minColIdx = i;
        }
      }

      const aspect = photo.width && photo.height ? photo.width / photo.height : 1.5;
      const width = colWidth;
      // Exact aspect ratio height calculation
      const height = colWidth / aspect;
      const x = minColIdx * (colWidth + gap);
      const y = colHeights[minColIdx];
      const bottom = y + height;

      computedPhotos.push({
        photo,
        x,
        y,
        width,
        height,
        column: minColIdx,
        bottom,
      });

      colHeights[minColIdx] = bottom + gap;
    }

    // Sort computed photos by y-coordinate for binary search efficiency
    computedPhotos.sort((a, b) => a.y - b.y);

    const maxColHeight = colHeights.length > 0 ? Math.max(...colHeights) : 0;
    const totalHeight = Math.max(0, maxColHeight - gap);

    return { photos: computedPhotos, totalHeight };
  }, [photos, containerWidth, gap]);
}

