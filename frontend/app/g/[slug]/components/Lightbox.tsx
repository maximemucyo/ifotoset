import React, { useState, useEffect, useRef, useCallback } from 'react';
import { createPortal } from 'react-dom';
import { ArrowLeft, Heart, Download, Share2, Play, Pause, ChevronLeft, ChevronRight, X, ZoomIn, ZoomOut } from 'lucide-react';
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
  autoPlay?: boolean;
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
  autoPlay,
}) => {
  const containerRef = useRef<HTMLDivElement>(null);
  const previousActiveElement = useRef<HTMLElement | null>(null);
  const preloadedImages = useRef<HTMLImageElement[]>([]);

  // Swipe gesture trackers
  const touchStartX = useRef<number | null>(null);
  const touchEndX = useRef<number | null>(null);
  const touchStartY = useRef<number | null>(null);
  const touchEndY = useRef<number | null>(null);

  // Zoom & Pan states
  const [scale, setScale] = useState(1);
  const [position, setPosition] = useState({ x: 0, y: 0 });
  const [isDragging, setIsDragging] = useState(false);
  const [dragStart, setDragStart] = useState({ x: 0, y: 0 });

  const imageRef = useRef<HTMLImageElement>(null);

  // Pinch zoom states
  const pinchStartDistance = useRef<number | null>(null);
  const pinchStartScale = useRef<number>(1);
  const pinchStartCenter = useRef<{ x: number; y: number } | null>(null);
  const isTouchPanning = useRef(false);
  const dragStartRef = useRef({ x: 0, y: 0 });

  const [isPlaying, setIsPlaying] = useState(false);

  useEffect(() => {
    if (autoPlay) {
      setIsPlaying(true);
    }
  }, [autoPlay]);

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

  // Reset zoom whenever active photo changes
  useEffect(() => {
    setScale(1);
    setPosition({ x: 0, y: 0 });
    setIsDragging(false);
  }, [photo.uuid]);

  const clampPosition = useCallback((currentScale: number, x: number, y: number) => {
    if (!imageRef.current || currentScale <= 1) {
      return { x: 0, y: 0 };
    }
    const containerWidth = window.innerWidth;
    const containerHeight = window.innerHeight;

    const imageWidth = imageRef.current.clientWidth;
    const imageHeight = imageRef.current.clientHeight;

    const maxTranslateX = Math.max(0, (imageWidth * currentScale - containerWidth) / 2);
    const maxTranslateY = Math.max(0, (imageHeight * currentScale - containerHeight) / 2);

    return {
      x: Math.max(-maxTranslateX, Math.min(maxTranslateX, x)),
      y: Math.max(-maxTranslateY, Math.min(maxTranslateY, y)),
    };
  }, []);

  const handleZoomIn = () => {
    setScale((prev) => {
      const nextScale = Math.min(5, prev + 0.5);
      return nextScale;
    });
  };

  const handleZoomOut = () => {
    setScale((prev) => {
      const nextScale = Math.max(1, prev - 0.5);
      return nextScale;
    });
  };

  const handleResetZoom = () => {
    setScale(1);
    setPosition({ x: 0, y: 0 });
  };

  // Clamp translation whenever scale changes
  useEffect(() => {
    if (scale === 1) {
      setPosition({ x: 0, y: 0 });
    } else {
      setPosition((prev) => clampPosition(scale, prev.x, prev.y));
    }
  }, [scale, clampPosition]);

  const handleWheel = (e: React.WheelEvent) => {
    if (!imageRef.current) return;

    const rect = imageRef.current.getBoundingClientRect();
    const mouseX = e.clientX - rect.left - rect.width / 2;
    const mouseY = e.clientY - rect.top - rect.height / 2;

    const zoomFactor = 1.1;
    const direction = e.deltaY < 0 ? 1 : -1;
    
    let newScale = scale * (direction > 0 ? zoomFactor : 1 / zoomFactor);
    newScale = Math.max(1, Math.min(5, newScale));

    if (newScale === 1) {
      setScale(1);
      setPosition({ x: 0, y: 0 });
    } else {
      const imageX = (mouseX - position.x) / scale;
      const imageY = (mouseY - position.y) / scale;

      const nextX = mouseX - imageX * newScale;
      const nextY = mouseY - imageY * newScale;

      const clamped = clampPosition(newScale, nextX, nextY);
      
      setScale(newScale);
      setPosition(clamped);
    }
  };

  const handleDoubleClick = (e: React.MouseEvent) => {
    if (!imageRef.current) return;
    
    if (scale > 1) {
      setScale(1);
      setPosition({ x: 0, y: 0 });
    } else {
      const rect = imageRef.current.getBoundingClientRect();
      const mouseX = e.clientX - rect.left - rect.width / 2;
      const mouseY = e.clientY - rect.top - rect.height / 2;

      const targetScale = 2.5;
      const imageX = (mouseX - position.x) / scale;
      const imageY = (mouseY - position.y) / scale;

      const nextX = mouseX - imageX * targetScale;
      const nextY = mouseY - imageY * targetScale;

      const clamped = clampPosition(targetScale, nextX, nextY);

      setScale(targetScale);
      setPosition(clamped);
    }
  };

  const handleMouseDown = (e: React.MouseEvent) => {
    if (scale <= 1) return;
    e.preventDefault();
    setIsDragging(true);
    setDragStart({
      x: e.clientX - position.x,
      y: e.clientY - position.y,
    });
  };

  const handleMouseMove = (e: React.MouseEvent) => {
    if (!isDragging) return;
    const newX = e.clientX - dragStart.x;
    const newY = e.clientY - dragStart.y;
    const clamped = clampPosition(scale, newX, newY);
    setPosition(clamped);
  };

  const handleMouseUp = () => {
    setIsDragging(false);
  };

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

  // Touch Swipe / Pinch Handlers for Mobile Devices
  const handleTouchStart = (e: React.TouchEvent) => {
    if (e.touches.length === 2) {
      const dist = Math.hypot(
        e.touches[0].clientX - e.touches[1].clientX,
        e.touches[0].clientY - e.touches[1].clientY
      );
      pinchStartDistance.current = dist;
      pinchStartScale.current = scale;
      pinchStartCenter.current = {
        x: (e.touches[0].clientX + e.touches[1].clientX) / 2,
        y: (e.touches[0].clientY + e.touches[1].clientY) / 2,
      };
      isTouchPanning.current = false;
    } else if (e.touches.length === 1) {
      touchStartX.current = e.touches[0].clientX;
      touchEndX.current = e.touches[0].clientX;
      touchStartY.current = e.touches[0].clientY;
      touchEndY.current = e.touches[0].clientY;
      
      if (scale > 1) {
        dragStartRef.current = { ...position };
        isTouchPanning.current = true;
      } else {
        isTouchPanning.current = false;
      }
    }
  };

  const handleTouchMove = (e: React.TouchEvent) => {
    if (e.touches.length === 2 && pinchStartDistance.current && pinchStartCenter.current && imageRef.current) {
      e.stopPropagation();
      const dist = Math.hypot(
        e.touches[0].clientX - e.touches[1].clientX,
        e.touches[0].clientY - e.touches[1].clientY
      );
      
      let newScale = pinchStartScale.current * (dist / pinchStartDistance.current);
      newScale = Math.max(1, Math.min(5, newScale));

      const center = {
        x: (e.touches[0].clientX + e.touches[1].clientX) / 2,
        y: (e.touches[0].clientY + e.touches[1].clientY) / 2,
      };

      const rect = imageRef.current.getBoundingClientRect();
      const pinchCenterX = center.x - rect.left - rect.width / 2;
      const pinchCenterY = center.y - rect.top - rect.height / 2;

      const imageX = (pinchCenterX - position.x) / scale;
      const imageY = (pinchCenterY - position.y) / scale;

      const nextX = pinchCenterX - imageX * newScale;
      const nextY = pinchCenterY - imageY * newScale;

      const clamped = clampPosition(newScale, nextX, nextY);

      setScale(newScale);
      setPosition(clamped);
    } else if (e.touches.length === 1) {
      touchEndX.current = e.touches[0].clientX;
      touchEndY.current = e.touches[0].clientY;

      if (scale > 1 && isTouchPanning.current) {
        e.stopPropagation();
        const deltaX = e.touches[0].clientX - touchStartX.current!;
        const deltaY = e.touches[0].clientY - touchStartY.current!;
        
        const newX = dragStartRef.current.x + deltaX;
        const newY = dragStartRef.current.y + deltaY;
        const clamped = clampPosition(scale, newX, newY);
        setPosition(clamped);
      }
    }
  };

  const handleTouchEnd = () => {
    pinchStartDistance.current = null;
    pinchStartCenter.current = null;
    isTouchPanning.current = false;

    if (scale > 1) {
      touchStartX.current = null;
      touchEndX.current = null;
      touchStartY.current = null;
      touchEndY.current = null;
      return; // Skip swipe transitions when zoomed in
    }

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
          {/* Zoom Controls */}
          <div className="hidden md:flex items-center gap-1 border-r border-border pr-3 mr-1">
            <button
              onClick={handleZoomOut}
              disabled={scale <= 1}
              aria-label="Zoom out"
              className="p-2 rounded-none text-foreground/80 hover:text-foreground hover:bg-secondary/25 transition-colors disabled:opacity-40"
            >
              <ZoomOut size={18} />
            </button>
            <span className="text-xs font-semibold text-muted-foreground w-10 text-center select-none">
              {Math.round(scale * 100)}%
            </span>
            <button
              onClick={handleZoomIn}
              disabled={scale >= 5}
              aria-label="Zoom in"
              className="p-2 rounded-none text-foreground/80 hover:text-foreground hover:bg-secondary/25 transition-colors disabled:opacity-40"
            >
              <ZoomIn size={18} />
            </button>
            {scale > 1 && (
              <button
                onClick={handleResetZoom}
                aria-label="Reset zoom"
                className="p-1 px-2 text-[10px] font-bold bg-secondary hover:bg-muted text-foreground rounded-none transition-colors ml-1"
              >
                Reset
              </button>
            )}
          </div>

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
        <div 
          className="relative w-full h-full flex items-center justify-center overflow-hidden"
          onWheel={handleWheel}
          onDoubleClick={handleDoubleClick}
          onMouseDown={handleMouseDown}
          onMouseMove={handleMouseMove}
          onMouseUp={handleMouseUp}
          onMouseLeave={handleMouseUp}
        >
          <img
            ref={imageRef}
            key={photo.uuid}
            src={currentFullUrl}
            alt={photo.filename}
            width={photo.width ?? undefined}
            height={photo.height ?? undefined}
            decoding="async"
            loading="eager"
            fetchPriority="high"
            className="max-w-full max-h-full object-contain rounded-none shadow-2xl transition-opacity duration-200 select-none pointer-events-none"
            style={{
              transform: `translate(${position.x}px, ${position.y}px) scale(${scale})`,
              transition: isDragging ? 'none' : 'transform 0.15s ease-out',
            }}
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
