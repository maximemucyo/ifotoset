import React, { useState, useEffect, useRef } from 'react';
import { Eye, ImageIcon, Heart, Download, Share2 } from 'lucide-react';
import { PhotoItem } from '@/lib/queries/galleries';
import { useGalleryLayout } from '../hooks/useGalleryLayout';
import { useGalleryVirtualizer } from '../hooks/useGalleryVirtualizer';
import { ProgressiveImage } from './ProgressiveImage';

interface VirtualGalleryGridProps {
  photos: PhotoItem[];
  hasMore: boolean;
  isFetchingNextPage: boolean;
  fetchNextPage: () => void;
  onSelectPhoto: (photo: PhotoItem) => void;
  gap?: number;
  favorites: string[];
  downloadingUuids: string[];
  onToggleFavorite: (photo: PhotoItem) => void;
  onDownload: (photo: PhotoItem) => void;
  onShare: (photo: PhotoItem) => void;
  showFavoritesOnly?: boolean;
}

export const VirtualGalleryGrid: React.FC<VirtualGalleryGridProps> = ({
  photos,
  hasMore,
  isFetchingNextPage,
  fetchNextPage,
  onSelectPhoto,
  gap = 6,
  favorites,
  downloadingUuids,
  onToggleFavorite,
  onDownload,
  onShare,
  showFavoritesOnly = false,
}) => {
  const containerRef = useRef<HTMLDivElement>(null);
  const sentinelRef = useRef<HTMLDivElement>(null);
  const lastWidth = useRef(0);
  const lastPhotosCount = useRef(0);

  // States
  const [containerWidth, setContainerWidth] = useState(0);
  const [scrollTop, setScrollTop] = useState(0);
  const [viewportHeight, setViewportHeight] = useState(800);

  // Measure container width and ignore tiny sub-pixel changes
  useEffect(() => {
    if (!containerRef.current) return;

    setContainerWidth(containerRef.current.clientWidth);
    lastWidth.current = containerRef.current.clientWidth;

    const observer = new ResizeObserver((entries) => {
      if (!entries || entries.length === 0) return;
      const width = entries[0].contentRect.width;
      if (Math.abs(width - lastWidth.current) >= 6) {
        window.requestAnimationFrame(() => {
          setContainerWidth(width);
          lastWidth.current = width;
        });
      }
    });

    observer.observe(containerRef.current);
    return () => observer.disconnect();
  }, []);

  // Track window scroll position relative to container
  useEffect(() => {
    if (typeof window === 'undefined') return;

    setViewportHeight(window.innerHeight);

    const handleScroll = () => {
      if (containerRef.current) {
        const rect = containerRef.current.getBoundingClientRect();
        const absoluteTop = rect.top + window.scrollY;
        const currentScrollTop = Math.max(0, window.scrollY - absoluteTop);
        setScrollTop(currentScrollTop);
      } else {
        setScrollTop(window.scrollY);
      }
    };

    const handleResize = () => {
      setViewportHeight(window.innerHeight);
    };

    window.addEventListener('scroll', handleScroll, { passive: true });
    window.addEventListener('resize', handleResize);
    
    // Initial sync
    handleScroll();

    return () => {
      window.removeEventListener('scroll', handleScroll);
      window.removeEventListener('resize', handleResize);
    };
  }, []);

  // Image preloading on page fetch
  useEffect(() => {
    if (photos.length > lastPhotosCount.current) {
      const targetVariant = containerWidth >= 1024 ? 'md' : 'sm';
      const newPhotos = photos.slice(lastPhotosCount.current, lastPhotosCount.current + 8);
      newPhotos.forEach((photo) => {
        const img = new Image();
        img.src = photo.variants?.[targetVariant] || photo.cdn_url;
      });
      lastPhotosCount.current = photos.length;
    }
  }, [photos, containerWidth]);

  // Infinite Scroll Sentinel
  useEffect(() => {
    if (!sentinelRef.current) return;

    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting && hasMore && !isFetchingNextPage) {
          fetchNextPage();
        }
      },
      { rootMargin: '1000px' }
    );

    observer.observe(sentinelRef.current);
    return () => observer.disconnect();
  }, [hasMore, isFetchingNextPage, fetchNextPage]);

  // Compute gap internally based on container width to keep layout self-contained and responsive
  const calculatedGap = containerWidth > 0 && containerWidth < 768 ? 12 : gap;

  // Layout and virtualization hooks
  const { photos: computedPhotos, totalHeight } = useGalleryLayout(photos, containerWidth, calculatedGap);
  const { visiblePhotos } = useGalleryVirtualizer(computedPhotos, scrollTop, viewportHeight);

  if (showFavoritesOnly && photos.length === 0) {
    return (
      <div className="bg-card border border-border rounded-xl p-16 text-center shadow-sm max-w-md mx-auto my-8">
        <Heart className="w-16 h-16 text-rose-500/40 mx-auto mb-4" />
        <h3 className="text-lg font-bold text-foreground mb-1">No favorites yet</h3>
        <p className="text-sm text-muted-foreground">You haven't marked any photos as favorites. Go back to all photos and click the heart icon on any photo to build your collection.</p>
      </div>
    );
  }

  if (photos.length === 0 && !isFetchingNextPage) {
    return (
      <div className="bg-card border border-border rounded-2xl p-16 text-center shadow-sm max-w-md mx-auto">
        <ImageIcon className="w-16 h-16 text-muted-foreground/40 mx-auto mb-4" />
        <h3 className="text-lg font-bold text-foreground mb-1">No photos yet</h3>
        <p className="text-sm text-muted-foreground">This gallery is empty. Check back again later.</p>
      </div>
    );
  }

  return (
    <div ref={containerRef} className="w-full relative" style={{ height: totalHeight }}>
      {/* Visible Masonry Items */}
      {visiblePhotos.map((item) => {
        const isFavorited = favorites.includes(item.photo.uuid);
        const isDownloading = downloadingUuids.includes(item.photo.uuid);
        
        return (
          <div
            key={item.photo.uuid}
            style={{
              position: 'absolute',
              left: item.x,
              top: item.y,
              width: item.width,
              height: item.height,
            }}
            tabIndex={0}
            onClick={() => onSelectPhoto(item.photo)}
            onKeyDown={(e) => {
              if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                onSelectPhoto(item.photo);
              }
            }}
            className="overflow-hidden group cursor-pointer border border-border/15 rounded-none hover:-translate-y-0.5 hover:shadow-md hover:z-10 focus-within:ring-2 focus-within:ring-primary focus-within:z-10 transition-all duration-300 shadow-sm"
          >
            <ProgressiveImage
              photo={item.photo}
              alt={item.photo.filename}
              onClick={() => onSelectPhoto(item.photo)}
              targetVariant={containerWidth >= 1024 ? 'md' : 'sm'}
            />
            
            {/* Persistent heart indicator when NOT hovered/focused */}
            {isFavorited && (
              <div className="absolute top-3 right-3 p-1.5 rounded-none bg-black/65 backdrop-blur-sm text-rose-500 shadow-sm transition-opacity duration-300 group-hover:opacity-0 group-focus-within:opacity-0 pointer-events-none">
                <Heart size={14} fill="currentColor" />
              </div>
            )}
            
            {/* Hover Actions Overlay */}
            <div 
              className="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 transition-opacity duration-300 flex flex-col justify-between p-3"
              onClick={(e) => {
                // Background overlay click defaults to opening the lightbox.
              }}
            >
              {/* Top row: accessible overlay buttons */}
              <div className="flex justify-end gap-2" onClick={(e) => e.stopPropagation()}>
                {/* Favorite Button */}
                <button
                  type="button"
                  aria-label="Favorite photo"
                  onClick={() => onToggleFavorite(item.photo)}
                  className="w-8 h-8 rounded-none bg-white/90 hover:bg-white text-black hover:text-rose-500 flex items-center justify-center shadow-md transition-all hover:scale-105"
                >
                  <Heart 
                    size={16} 
                    fill={isFavorited ? "currentColor" : "none"} 
                    className={isFavorited ? "text-rose-500" : ""}
                  />
                </button>

                {/* Share Button */}
                <button
                  type="button"
                  aria-label="Share photo"
                  onClick={() => onShare(item.photo)}
                  className="w-8 h-8 rounded-none bg-white/90 hover:bg-white text-black hover:text-primary flex items-center justify-center shadow-md transition-all hover:scale-105"
                >
                  <Share2 size={16} />
                </button>

                {/* Download Button */}
                <button
                  type="button"
                  aria-label="Download photo"
                  disabled={isDownloading}
                  onClick={() => onDownload(item.photo)}
                  className="w-8 h-8 rounded-none bg-white/90 hover:bg-white text-black hover:text-primary flex items-center justify-center shadow-md transition-all hover:scale-105 disabled:opacity-75 disabled:cursor-not-allowed"
                >
                  {isDownloading ? (
                    <div className="w-4 h-4 rounded-full border-2 border-primary/20 border-t-primary animate-spin" />
                  ) : (
                    <Download size={16} />
                  )}
                </button>
              </div>

              {/* Bottom row: filename indicator & view eye */}
              <div className="flex items-center justify-between pointer-events-none">
                <span className="bg-black/60 backdrop-blur-sm text-white text-[10px] px-2 py-1 rounded-none truncate max-w-[70%]">
                  {item.photo.filename}
                </span>
                <div className="w-8 h-8 rounded-none bg-white/90 backdrop-blur-sm flex items-center justify-center text-black shadow-sm shrink-0">
                  <Eye size={14} />
                </div>
              </div>
            </div>
          </div>
        );
      })}

      {/* Intersection Observer Sentinel */}
      <div ref={sentinelRef} className="absolute w-full h-10 mt-6 flex justify-center items-center" style={{ top: totalHeight }}>
        {isFetchingNextPage && (
          <div className="flex items-center gap-2 py-4">
            <div className="w-6 h-6 rounded-full border-2 border-primary/20 border-t-primary animate-spin" />
            <p className="text-muted-foreground text-xs font-medium">Loading more photos...</p>
          </div>
        )}
      </div>
    </div>
  );
};
