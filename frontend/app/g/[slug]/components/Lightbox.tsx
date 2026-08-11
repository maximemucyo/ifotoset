import React, { useState, useEffect, useRef, useCallback } from 'react';
import { createPortal } from 'react-dom';
import { ArrowLeft, Heart, Download, Share2, Play, Pause, ChevronLeft, ChevronRight, X } from 'lucide-react';
import { PhotoItem } from '@/lib/queries/galleries';

interface LightboxProps {
  photo: PhotoItem;
  photos: PhotoItem[];
  currentIndex: number;
  totalCount: number;
  onClose: () => void;
  onPrev: () => void;
  onNext: () => void;
  slug: string;
  inviteToken?: string | null;
  galleryToken?: string | null;
  favorites: string[];
  downloadingUuids: string[];
  onToggleFavorite: (photo: PhotoItem) => void;
  onDownload: (photo: PhotoItem) => void;
  onShare: (photo: PhotoItem) => void;
}

export const Lightbox: React.FC<LightboxProps> = ({
  photo,
  photos,
  currentIndex,
  totalCount,
  onClose,
  onPrev,
  onNext,
  slug,
  inviteToken,
  galleryToken,
  favorites,
  downloadingUuids,
  onToggleFavorite,
  onDownload,
  onShare,
}) => {
  const containerRef = useRef<HTMLDivElement>(null);
  const previousActiveElement = useRef<HTMLElement | null>(null);
  const preloadedImages = useRef<HTMLImageElement[]>([]);

  // Swipe gesture trackers
  const touchStartX = useRef<number | null>(null);
  const touchEndX = useRef<number | null>(null);
  const touchStartY = useRef<number | null>(null);
  const touchEndY = useRef<number | null>(null);

  const [isPlaying, setIsPlaying] = useState(false);
  const [viewportWidth, setViewportWidth] = useState(1200);

  const targetVariant = viewportWidth < 768 ? 'lg' : 'xl';

  const isFavorited = favorites.includes(photo.uuid);
  const isDownloading = downloadingUuids.includes(photo.uuid);

  // Track window resizing
  useEffect(() => {
    if (typeof window !== 'undefined') {
      setViewportWidth(window.innerWidth);
      const handleResize = () => setViewportWidth(window.innerWidth);
      window.addEventListener('resize', handleResize);
      return () => window.removeEventListener('resize', handleResize);
    }
  }, []);

  // Save focus and trap focus
  useEffect(() => {
    previousActiveElement.current = document.activeElement as HTMLElement;
    containerRef.current?.focus();

    // Prevent background scrolling
    document.body.style.overflow = 'hidden';

    return () => {
      document.body.style.overflow = '';
      previousActiveElement.current?.focus();
    };
  }, []);

  const currentFullUrl = photo.variants?.[targetVariant] || photo.cdn_url;

  // Cancel obsolete image preloads and load adjacent ones in the background
  useEffect(() => {
    // Cancel old preloads
    preloadedImages.current.forEach((img) => {
      img.src = '';
      img.onload = null;
      img.onerror = null;
    });
    preloadedImages.current = [];

    // Queue new preloads
    const targetIndices = [
      currentIndex - 2,
      currentIndex - 1,
      currentIndex + 1,
      currentIndex + 2,
    ];

    targetIndices.forEach((idx) => {
      const wrappedIdx = (idx + photos.length) % photos.length;
      const targetPhoto = photos[wrappedIdx];
      if (targetPhoto) {
        const img = new Image();
        img.src = targetPhoto.variants?.[targetVariant] || targetPhoto.cdn_url;
        preloadedImages.current.push(img);
      }
    });
  }, [currentIndex, photos, targetVariant]);

  // Slideshow handling
  useEffect(() => {
    if (!isPlaying) return;
    const timer = setInterval(() => {
      onNext();
    }, 3000);
    return () => clearInterval(timer);
  }, [isPlaying, onNext]);

  // Keyboard navigation & hotkeys
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      const target = e.target as HTMLElement;
      if (
        target.tagName === 'INPUT' ||
        target.tagName === 'TEXTAREA' ||
        target.isContentEditable
      ) {
        return;
      }

      switch (e.key) {
        case 'ArrowLeft':
          onPrev();
          break;
        case 'ArrowRight':
          onNext();
          break;
        case 'Escape':
          onClose();
          break;
        case ' ':
          e.preventDefault();
          setIsPlaying((prev) => !prev);
          break;
        case 'f':
        case 'F':
          onToggleFavorite(photo);
          break;
        case 'd':
        case 'D':
          onDownload(photo);
          break;
        case 'Tab':
          // Simple focus trap
          if (containerRef.current) {
            const focusables = containerRef.current.querySelectorAll('button, a');
            if (focusables.length > 0) {
              const first = focusables[0] as HTMLElement;
              const last = focusables[focusables.length - 1] as HTMLElement;
              if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
              } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
              }
            }
          }
          break;
        default:
          break;
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [onPrev, onNext, onClose, photo, onToggleFavorite, onDownload]);

  // Touch Swipe Handlers for Mobile Devices
  const handleTouchStart = (e: React.TouchEvent) => {
    touchStartX.current = e.touches[0].clientX;
    touchEndX.current = e.touches[0].clientX;
    touchStartY.current = e.touches[0].clientY;
    touchEndY.current = e.touches[0].clientY;
  };

  const handleTouchMove = (e: React.TouchEvent) => {
    touchEndX.current = e.touches[0].clientX;
    touchEndY.current = e.touches[0].clientY;
  };

  const handleTouchEnd = () => {
    if (
      touchStartX.current === null ||
      touchEndX.current === null ||
      touchStartY.current === null ||
      touchEndY.current === null
    ) {
      return;
    }

    const diffX = touchStartX.current - touchEndX.current;
    const diffY = touchStartY.current - touchEndY.current;
    const minSwipeDistance = 50; // pixels

    // Only transition if horizontal drag is dominant and exceeds threshold
    if (Math.abs(diffX) > minSwipeDistance && Math.abs(diffX) > Math.abs(diffY)) {
      if (diffX > 0) {
        onNext();
      } else {
        onPrev();
      }
    }

    touchStartX.current = null;
    touchEndX.current = null;
    touchStartY.current = null;
    touchEndY.current = null;
  };

  return createPortal(
    <div
      ref={containerRef}
      role="dialog"
      aria-modal="true"
      tabIndex={-1}
      className="fixed inset-0 h-screen w-screen bg-background/98 z-50 flex flex-col justify-between select-none focus:outline-none transition-colors duration-200 overflow-hidden touch-none"
      onClick={onClose}
      onTouchStart={handleTouchStart}
      onTouchMove={handleTouchMove}
      onTouchEnd={handleTouchEnd}
    >
      {/* Lightbox Header Bar */}
      <header
        className="w-full px-4 py-3 md:px-6 md:py-4 flex items-center justify-between z-10 shrink-0"
        style={{
          paddingTop: 'calc(0.75rem + env(safe-area-inset-top))',
          paddingLeft: 'calc(1rem + env(safe-area-inset-left))',
          paddingRight: 'calc(1rem + env(safe-area-inset-right))',
        }}
        onClick={(e) => e.stopPropagation()}
      >
        <button
          onClick={onClose}
          aria-label="Back to Gallery"
          className="flex items-center gap-2 text-foreground/80 hover:text-foreground hover:bg-secondary/25 px-4 py-2 rounded-none font-medium transition-colors"
        >
          <ArrowLeft size={20} />
          <span className="hidden sm:inline">Gallery</span>
        </button>

        <div className="flex items-center gap-1.5 md:gap-3">
          <button
            onClick={() => setIsPlaying((p) => !p)}
            aria-label={isPlaying ? "Pause Slideshow" : "Play Slideshow"}
            className={`p-2.5 rounded-none transition-colors hidden sm:inline-flex ${
              isPlaying ? 'text-primary bg-primary/10' : 'text-foreground/80 hover:text-foreground hover:bg-secondary/25'
            }`}
          >
            {isPlaying ? <Pause size={20} /> : <Play size={20} />}
          </button>
          <button
            onClick={() => onToggleFavorite(photo)}
            aria-label="Favorite image"
            className={`p-2.5 rounded-none transition-colors ${
              isFavorited ? 'text-rose-500 bg-rose-500/10' : 'text-foreground/80 hover:text-foreground hover:bg-secondary/25'
            }`}
          >
            <Heart size={20} fill={isFavorited ? "currentColor" : "none"} />
          </button>
          <button
            onClick={() => onShare(photo)}
            aria-label="Copy photo link"
            className="p-2.5 rounded-none text-foreground/80 hover:text-foreground hover:bg-secondary/25 transition-colors"
          >
            <Share2 size={20} />
          </button>
          <button
            onClick={() => onDownload(photo)}
            disabled={isDownloading}
            aria-label="Download high resolution image"
            className="p-2.5 rounded-none text-foreground/80 hover:text-foreground hover:bg-secondary/25 transition-colors disabled:opacity-70 disabled:cursor-not-allowed flex items-center justify-center min-w-[40px] min-h-[40px]"
          >
            {isDownloading ? (
              <div className="w-5 h-5 rounded-full border-2 border-primary/20 border-t-primary animate-spin" />
            ) : (
              <Download size={20} />
            )}
          </button>
          <button
            onClick={onClose}
            aria-label="Close Lightbox"
            className="p-2.5 rounded-none text-foreground/60 hover:text-foreground hover:bg-secondary/25 transition-colors hidden sm:inline-flex"
          >
            <X size={20} />
          </button>
        </div>
      </header>

      {/* Main Image Viewport Area */}
      <div className="relative flex-1 min-h-0 w-full flex items-center justify-center px-4" onClick={(e) => e.stopPropagation()}>
        {/* Left Nav Chevron */}
        <button
          onClick={onPrev}
          aria-label="Previous photo"
          className="absolute left-2 md:left-6 p-2 md:p-4 text-foreground/45 hover:text-foreground hover:bg-secondary/15 rounded-none transition-all duration-200 z-10 flex items-center justify-center"
        >
          <ChevronLeft size={24} className="md:w-10 md:h-10" />
        </button>

        {/* Dynamic Decoded Image View */}
        <div className="relative w-full h-full flex items-center justify-center overflow-hidden">
          <img
            key={photo.uuid}
            src={currentFullUrl}
            alt={photo.filename}
            width={photo.width ?? undefined}
            height={photo.height ?? undefined}
            decoding="async"
            loading="eager"
            fetchPriority="high"
            className="max-w-full max-h-full object-contain rounded-none shadow-2xl transition-opacity duration-200"
          />
        </div>

        {/* Right Nav Chevron */}
        <button
          onClick={onNext}
          aria-label="Next photo"
          className="absolute right-2 md:right-6 p-2 md:p-4 text-foreground/45 hover:text-foreground hover:bg-secondary/15 rounded-none transition-all duration-200 z-10 flex items-center justify-center"
        >
          <ChevronRight size={24} className="md:w-10 md:h-10" />
        </button>
      </div>

      {/* Lightbox Footer Bar */}
      <footer
        className="w-full py-3 md:py-6 text-center z-10 flex flex-col items-center gap-1 shrink-0"
        style={{
          paddingBottom: 'calc(0.75rem + env(safe-area-inset-bottom))',
          paddingLeft: 'calc(1rem + env(safe-area-inset-left))',
          paddingRight: 'calc(1rem + env(safe-area-inset-right))',
        }}
        onClick={(e) => e.stopPropagation()}
      >
        <span className="text-sm font-medium text-foreground/90 truncate max-w-[85vw]">{photo.filename}</span>
        <span className="text-xs text-muted-foreground">{currentIndex + 1} / {totalCount}</span>
      </footer>
    </div>,
    document.body
  );
};
