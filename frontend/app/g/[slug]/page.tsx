'use client';

import { useState, useEffect, useCallback, useRef } from 'react';
import { useParams, useSearchParams } from 'next/navigation';
import { createPortal } from 'react-dom';
import {
  LockKeyhole, Mail, AlertTriangle, Calendar, User, ImageIcon,
  Share2, Heart, Download, X, Copy, Check, SearchX
} from 'lucide-react';
import {
  getPublicGallery, unlockPublicGallery, GalleryItem, PhotoItem,
  recordPublicPhotoDownload, togglePublicPhotoFavorite
} from '@/lib/queries/galleries';
import { ApiError } from '@/lib/apiClient';
import { ThemeToggle } from '@/components/theme-toggle';
import { Logo } from '@/components/logo';

// Modular Hooks and Components
import { useInfiniteGallery } from './hooks/useInfiniteGallery';
import { VirtualGalleryGrid } from './components/VirtualGalleryGrid';
import { Lightbox } from './components/Lightbox';

export default function PublicGalleryView() {
  const { slug } = useParams<{ slug: string }>();
  const searchParams = useSearchParams();
  const inviteToken = searchParams.get('invite');

  const [gallery, setGallery] = useState<GalleryItem | null>(null);
  const [loading, setLoading] = useState(true);
  const [errorState, setErrorState] = useState<{
    code: string;
    message: string;
    httpStatus?: number;
    requiresPassword?: boolean;
    requiresInvitation?: boolean;
  } | null>(null);

  // Password unlock state
  const [password, setPassword] = useState('');
  const [unlockError, setUnlockError] = useState<string | null>(null);
  const [unlocking, setUnlocking] = useState(false);

  // Lightbox state
  const [selectedPhoto, setSelectedPhoto] = useState<PhotoItem | null>(null);

  // Lifted Gallery/Photo Stats & Selections State
  const [favorites, setFavorites] = useState<string[]>([]);
  const [visitorIdentity, setVisitorIdentity] = useState<{ email: string; created_at: string; version: number } | null>(null);
  const [downloadingUuids, setDownloadingUuids] = useState<string[]>([]);
  
  // Email Modal State
  const [isEmailModalOpen, setIsEmailModalOpen] = useState(false);
  const [emailModalTargetPhoto, setEmailModalTargetPhoto] = useState<PhotoItem | null>(null);
  const [emailInput, setEmailInput] = useState('');
  const [emailError, setEmailError] = useState('');

  // Share Modal State
  const [sharingPhoto, setSharingPhoto] = useState<PhotoItem | null>(null);
  const [isCopied, setIsCopied] = useState(false);

  // Scroll navigation, CTA ref, and client side mounting states
  const [mounted, setMounted] = useState(false);
  const [showHeader, setShowHeader] = useState(true);
  const lastScrollYRef = useRef(0);
  const mainRef = useRef<HTMLDivElement>(null);

  const scrollToGallery = () => {
    mainRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  // Mount effect: Set client-safe mounted flag and track dynamic header height CSS variable
  useEffect(() => {
    setMounted(true);

    const updateHeaderHeight = () => {
      const headerEl = document.querySelector('header');
      if (headerEl) {
        document.documentElement.style.setProperty('--header-height', `${headerEl.offsetHeight}px`);
      }
    };

    updateHeaderHeight();
    window.addEventListener('resize', updateHeaderHeight);
    return () => window.removeEventListener('resize', updateHeaderHeight);
  }, []);

  // Header scroll transition: hides on downward scroll, reveals on upward scroll, with movement threshold
  useEffect(() => {
    const threshold = 10;
    const handleScroll = () => {
      // Disable header hide behavior while lightbox or another modal is active
      const isAnyModalOpen = selectedPhoto !== null || isEmailModalOpen || sharingPhoto !== null;
      if (isAnyModalOpen) {
        return;
      }

      const currentScrollY = window.scrollY;
      const lastScrollY = lastScrollYRef.current;
      const diff = currentScrollY - lastScrollY;

      // Always show at the top of the page
      if (currentScrollY <= 50) {
        setShowHeader(true);
      } else if (Math.abs(diff) > threshold) {
        if (diff > 0) {
          // Scrolling down (content moving up) -> hide header
          setShowHeader(false);
        } else {
          // Scrolling up (content moving down) -> show header
          setShowHeader(true);
        }
      }

      lastScrollYRef.current = currentScrollY;
    };

    window.addEventListener('scroll', handleScroll, { passive: true });
    return () => window.removeEventListener('scroll', handleScroll);
  }, [selectedPhoto, isEmailModalOpen, sharingPhoto]);

  // Load identity and favorites on mount
  useEffect(() => {
    if (typeof window !== 'undefined' && slug) {
      const identityStr = localStorage.getItem(`visitor_identity_${slug}`);
      if (identityStr) {
        try {
          setVisitorIdentity(JSON.parse(identityStr));
        } catch (e) {
          console.error(e);
        }
      }
      
      const favoritesStr = localStorage.getItem(`gallery_favorites_${slug}`);
      if (favoritesStr) {
        try {
          setFavorites(JSON.parse(favoritesStr));
        } catch (e) {
          console.error(e);
        }
      }
    }
  }, [slug]);


  const handleToggleFavorite = async (photo: PhotoItem) => {
    if (!visitorIdentity?.email) {
      setEmailModalTargetPhoto(photo);
      setEmailInput('');
      setEmailError('');
      setIsEmailModalOpen(true);
      return;
    }

    const isCurrentlyFavorited = favorites.includes(photo.uuid);
    const nextState = !isCurrentlyFavorited;

    // Optimistic UI update
    let updatedFavorites;
    if (nextState) {
      updatedFavorites = [...favorites, photo.uuid];
    } else {
      updatedFavorites = favorites.filter(id => id !== photo.uuid);
    }
    setFavorites(updatedFavorites);
    localStorage.setItem(`gallery_favorites_${slug}`, JSON.stringify(updatedFavorites));

    try {
      await togglePublicPhotoFavorite(
        slug || '',
        photo.uuid,
        nextState,
        visitorIdentity.email,
        inviteToken,
        galleryToken
      );
    } catch (err) {
      console.error('Failed to toggle favorite on backend', err);
    }
  };

  const handleEmailSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const normalizedEmail = emailInput.trim().toLowerCase();
    
    // Simple email regex validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(normalizedEmail)) {
      setEmailError('Please enter a valid email address.');
      return;
    }

    const newIdentity = {
      email: normalizedEmail,
      created_at: new Date().toISOString(),
      version: 1
    };

    localStorage.setItem(`visitor_identity_${slug}`, JSON.stringify(newIdentity));
    setVisitorIdentity(newIdentity);
    setIsEmailModalOpen(false);

    if (emailModalTargetPhoto) {
      const photo = emailModalTargetPhoto;
      setEmailModalTargetPhoto(null);
      
      const isCurrentlyFavorited = favorites.includes(photo.uuid);
      const nextState = !isCurrentlyFavorited;

      let updatedFavorites;
      if (nextState) {
        updatedFavorites = [...favorites, photo.uuid];
      } else {
        updatedFavorites = favorites.filter(id => id !== photo.uuid);
      }
      setFavorites(updatedFavorites);
      localStorage.setItem(`gallery_favorites_${slug}`, JSON.stringify(updatedFavorites));

      togglePublicPhotoFavorite(
        slug || '',
        photo.uuid,
        nextState,
        normalizedEmail,
        inviteToken,
        galleryToken
      ).catch(err => {
        console.error('Failed to toggle favorite on backend', err);
      });
    }
  };

  const handleDownload = async (photo: PhotoItem) => {
    if (downloadingUuids.includes(photo.uuid)) return;

    setDownloadingUuids(prev => [...prev, photo.uuid]);

    try {
      const email = visitorIdentity?.email || null;
      await recordPublicPhotoDownload(slug || '', photo.uuid, email, inviteToken, galleryToken);
    } catch (err) {
      console.error('Failed to record download', err);
    } finally {
      setTimeout(() => {
        setDownloadingUuids(prev => prev.filter(id => id !== photo.uuid));
      }, 1500);
    }

    const a = document.createElement('a');
    a.href = photo.cdn_url;
    a.download = photo.filename;
    a.target = '_blank';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  };

  const handleShare = (photo: PhotoItem) => {
    const canonicalUrl = `${window.location.origin}/g/${slug}?photo=${photo.uuid}`;
    if (navigator.share) {
      navigator.share({
        title: photo.filename,
        text: `Check out this photo from the gallery "${gallery?.title || ''}"`,
        url: canonicalUrl,
      }).catch(err => {
        console.error('Web Share failed', err);
      });
    } else {
      setSharingPhoto(photo);
      setIsCopied(false);
    }
  };

  // Infinite Gallery Fetching Hook
  const galleryToken = typeof window !== 'undefined' ? sessionStorage.getItem(`gallery_token_${slug}`) : null;
  const {
    photos,
    hasMore,
    isFetchingNextPage,
    fetchNextPage,
  } = useInfiniteGallery(slug || '', inviteToken, galleryToken, 60);

  // Precompute slideshow/lightbox index positioning
  const currentIndex = selectedPhoto ? photos.findIndex((p) => p.uuid === selectedPhoto.uuid) : -1;
  const totalCount = gallery?.stats?.photo_count ?? 0;

  const handlePrevPhoto = useCallback(() => {
    if (currentIndex !== -1 && photos.length > 0) {
      const prevIndex = (currentIndex - 1 + photos.length) % photos.length;
      setSelectedPhoto(photos[prevIndex] || null);
    }
  }, [currentIndex, photos]);

  const handleNextPhoto = useCallback(() => {
    if (currentIndex !== -1 && photos.length > 0) {
      const nextIndex = (currentIndex + 1) % photos.length;
      setSelectedPhoto(photos[nextIndex] || null);
    }
  }, [currentIndex, photos]);

  // Background fetch next page when user is inside the Lightbox and nearing the end of fetched photos
  useEffect(() => {
    if (selectedPhoto && currentIndex !== -1) {
      if (currentIndex >= photos.length - 5 && hasMore && !isFetchingNextPage) {
        fetchNextPage();
      }
    }
  }, [selectedPhoto, currentIndex, photos.length, hasMore, isFetchingNextPage, fetchNextPage]);

  // Auto-open lightbox if `?photo={uuid}` is present in the URL on mount/photos load
  useEffect(() => {
    const photoUuidParam = searchParams.get('photo');
    if (photoUuidParam && photos.length > 0) {
      const targetPhoto = photos.find(p => p.uuid === photoUuidParam);
      if (targetPhoto) {
        setSelectedPhoto(targetPhoto);
      }
    }
  }, [searchParams, photos]);

  const fetchGallery = async () => {
    setLoading(true);
    setErrorState(null);
    try {
      const token = sessionStorage.getItem(`gallery_token_${slug}`);
      const res = await getPublicGallery(slug || '', inviteToken, token);
      setGallery(res.data);
    } catch (err) {
      if (err instanceof ApiError) {
        setErrorState({
          code: err.code,
          message: err.message,
          httpStatus: err.status,
          requiresPassword: err.code === 'PASSWORD_REQUIRED',
          requiresInvitation: err.code === 'INVITATION_REQUIRED',
        });
      } else {
        setErrorState({
          code: 'FETCH_ERROR',
          message: err instanceof Error ? err.message : 'Failed to load gallery.',
        });
      }
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (slug) {
      fetchGallery();
    }
  }, [slug, inviteToken]);

  const handleUnlockSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!password.trim()) return;

    setUnlocking(true);
    setUnlockError(null);
    try {
      const res = await unlockPublicGallery(slug || '', password);
      sessionStorage.setItem(`gallery_token_${slug}`, res.token);
      setPassword('');
      fetchGallery();
    } catch (err) {
      if (err instanceof ApiError) {
        setUnlockError(err.message);
      } else {
        setUnlockError('Unlock failed. Please try again.');
      }
    } finally {
      setUnlocking(false);
    }
  };

  // ─── Loading Screen ────────────────────────────────────────────────────────
  if (loading) {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center">
        <div className="flex flex-col items-center gap-4">
          <div className="w-12 h-12 rounded-full border-4 border-primary/20 border-t-primary animate-spin" />
          <p className="text-muted-foreground font-medium text-sm">Opening gallery...</p>
        </div>
      </div>
    );
  }

  // ─── Password Required Screen ──────────────────────────────────────────────
  if (errorState?.requiresPassword) {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center p-6 text-foreground transition-colors duration-200">
        <div className="bg-card border border-border rounded-none p-8 max-w-md w-full shadow-lg text-center space-y-6">
          <div className="w-16 h-16 bg-primary/10 flex items-center justify-center mx-auto text-primary">
            <LockKeyhole size={32} />
          </div>
          <div>
            <h1 className="text-2xl font-bold">Password Protected</h1>
            <p className="text-sm text-muted-foreground mt-2">
              This photo gallery is private. Please enter the password to unlock.
            </p>
            {errorState.message && (
              <p className="text-xs font-semibold text-primary mt-2">
                Hint: {errorState.message.includes('Hint:') ? errorState.message.split('Hint:')[1] : 'Check with the photographer.'}
              </p>
            )}
          </div>

          <form onSubmit={handleUnlockSubmit} className="space-y-4">
            <input
              type="password"
              placeholder="Enter password..."
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="w-full px-4 py-3 bg-background border border-border rounded-none placeholder-muted-foreground/60 focus:outline-none focus:border-primary transition-colors"
            />
            {unlockError && <p className="text-xs text-destructive text-left font-medium">{unlockError}</p>}
            <button
              type="submit"
              disabled={unlocking}
              className="w-full py-3 bg-primary text-primary-foreground rounded-none hover:bg-primary/95 font-semibold transition-colors disabled:opacity-60 flex items-center justify-center gap-2"
            >
              {unlocking ? 'Unlocking...' : 'Unlock Gallery'}
            </button>
          </form>
        </div>
      </div>
    );
  }

  // ─── Invitation Required Screen ────────────────────────────────────────────
  if (errorState?.requiresInvitation) {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center p-6 text-foreground transition-colors duration-200">
        <div className="bg-card border border-border rounded-none p-8 max-w-md w-full shadow-lg text-center space-y-6">
          <div className="w-16 h-16 bg-primary/10 flex items-center justify-center mx-auto text-primary">
            <Mail size={32} />
          </div>
          <div>
            <h1 className="text-2xl font-bold">Invitation Required</h1>
            <p className="text-sm text-muted-foreground mt-2">
              This gallery is private and restricted to invited email addresses. Please use the unique link sent to your email.
            </p>
          </div>
          <div className="p-4 bg-amber-500/10 border border-amber-500/20 rounded-none text-left">
            <p className="text-xs text-amber-600 dark:text-amber-400 font-medium leading-relaxed">
              If you received an invitation, make sure to click the "View Gallery" button in your email. Links are custom-generated for each guest.
            </p>
          </div>
        </div>
      </div>
    );
  }

  // ─── Gallery Not Found Screen (404) ───────────────────────────────────────
  if (errorState && (errorState.httpStatus === 404 || errorState.code === 'NOT_FOUND')) {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center p-6 text-foreground transition-colors duration-200">
        <div className="text-center max-w-md space-y-5">
          <div className="w-20 h-20 bg-muted flex items-center justify-center mx-auto">
            <SearchX className="w-10 h-10 text-muted-foreground" />
          </div>
          <div>
            <h1 className="text-2xl font-bold mb-2">Gallery Not Found</h1>
            <p className="text-sm text-muted-foreground">
              This gallery doesn&apos;t exist or may have been removed by the photographer.
            </p>
          </div>
          <a
            href="/"
            className="inline-block px-6 py-3 bg-primary text-primary-foreground rounded-none font-semibold hover:bg-primary/95 transition-colors"
          >
            Go to Homepage
          </a>
        </div>
      </div>
    );
  }

  // ─── Other Errors Screen ───────────────────────────────────────────────────
  if (errorState) {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center p-6 text-foreground transition-colors duration-200">
        <div className="text-center max-w-md space-y-5">
          <div className="w-20 h-20 bg-destructive/10 flex items-center justify-center mx-auto">
            <AlertTriangle className="w-10 h-10 text-destructive" />
          </div>
          <div>
            <h1 className="text-2xl font-bold mb-2">Access Denied</h1>
            <p className="text-sm text-muted-foreground">{errorState.message}</p>
          </div>
          <a
            href="/"
            className="inline-block px-6 py-3 bg-primary text-primary-foreground rounded-none font-semibold hover:bg-primary/95 transition-colors"
          >
            Go to Homepage
          </a>
        </div>
      </div>
    );
  }

  if (!gallery) return null;

  return (
    <div className="min-h-screen bg-background text-foreground transition-colors duration-200">
      {/* Public Navbar Header */}
      <header className={`border-b border-border bg-card/85 backdrop-blur-md sticky top-0 z-30 transition-transform duration-300 ${
        showHeader ? 'translate-y-0' : '-translate-y-full'
      }`}>
        <div className="w-full max-w-none px-4 md:px-8 xl:px-12 2xl:px-16 py-4 flex items-center justify-between gap-4">
          <div className="flex items-center gap-3 min-w-0">
            <Logo size="sm" href="/" />
            <div className="h-5 w-px bg-border shrink-0" />
            <h1 className="font-bold text-lg text-foreground truncate max-w-[140px] sm:max-w-sm shrink">
              {gallery.title}
            </h1>
          </div>
          <div className="flex items-center gap-2 sm:gap-4 text-xs text-muted-foreground shrink-0">
            {gallery.client_name && (
              <span className="hidden sm:flex items-center gap-1.5 font-medium">
                <User size={14} /> {gallery.client_name}
              </span>
            )}
            {gallery.event_date && (
              <span className="hidden sm:flex items-center gap-1.5 font-medium">
                <Calendar size={14} /> {new Date(gallery.event_date).toLocaleDateString()}
              </span>
            )}
            <div className="h-4 w-px bg-border hidden sm:block" />
            <ThemeToggle />
          </div>
        </div>
      </header>

      {/* Hero Banner Section */}
      {gallery.cover_photo ? (
        <section className="relative w-full h-[calc(100svh-var(--header-height,68px))] border-b border-border overflow-hidden select-none">
          <img
            src={gallery.cover_photo.variants?.xl || gallery.cover_photo.cdn_url}
            alt={gallery.title}
            className="absolute inset-0 w-full h-full object-cover object-center"
            fetchPriority="high"
          />
          <div className="absolute inset-0 bg-black/35 flex flex-col justify-between py-12 px-6">
            <div /> {/* Spacer */}
            <div className="max-w-3xl mx-auto space-y-3 text-center text-white drop-shadow-lg">
              <h2 className="text-4xl sm:text-5xl md:text-6xl font-extrabold tracking-tight">
                {gallery.title}
              </h2>
              {gallery.client_name && (
                <p className="text-md sm:text-lg font-medium text-white/90">
                  For {gallery.client_name}
                </p>
              )}
              {gallery.event_date && (
                <p className="text-xs sm:text-sm text-white/80">
                  {new Date(gallery.event_date).toLocaleDateString(undefined, { dateStyle: 'long' })}
                </p>
              )}
            </div>
            
            <div className="flex flex-col items-center gap-4">
              <button
                type="button"
                onClick={scrollToGallery}
                className="px-6 py-3 bg-white text-black hover:bg-white/90 active:scale-95 font-semibold text-sm rounded-full shadow-lg transition-all duration-200 hover:-translate-y-0.5"
              >
                View Gallery
              </button>
              <button
                type="button"
                onClick={scrollToGallery}
                className="p-2 rounded-full bg-white/10 hover:bg-white/20 text-white transition-all animate-bounce"
                aria-label="Scroll to photos"
              >
                <svg className="w-6 h-6 fill-none stroke-current stroke-2" viewBox="0 0 24 24">
                  <path d="M19 14l-7 7-7-7M12 21V3" />
                </svg>
              </button>
            </div>
          </div>
        </section>
      ) : (
        <section className="bg-gradient-to-b from-secondary/20 via-transparent to-transparent border-b border-border py-10 md:py-16 px-4 md:px-6 text-center">
          <div className="max-w-3xl mx-auto space-y-3">
            <h2 className="text-3xl sm:text-4xl md:text-5xl font-extrabold text-foreground tracking-tight">
              {gallery.title}
            </h2>
            <p className="text-sm md:text-md text-muted-foreground max-w-xl mx-auto mt-3">
              Welcome to your digital photo collection. View, browse, and explore high-resolution memories.
            </p>
          </div>
        </section>
      )}

      {/* Photo Grid Section */}
      <main ref={mainRef} className="w-full max-w-none py-12">
        <VirtualGalleryGrid
          photos={photos}
          hasMore={hasMore}
          isFetchingNextPage={isFetchingNextPage}
          fetchNextPage={fetchNextPage}
          onSelectPhoto={setSelectedPhoto}
          gap={6}
          favorites={favorites}
          downloadingUuids={downloadingUuids}
          onToggleFavorite={handleToggleFavorite}
          onDownload={handleDownload}
          onShare={handleShare}
        />
      </main>

      {/* Lightbox Modal overlay portal */}
      {selectedPhoto && currentIndex !== -1 && (
        <Lightbox
          photo={selectedPhoto}
          photos={photos}
          currentIndex={currentIndex}
          totalCount={totalCount}
          onClose={() => setSelectedPhoto(null)}
          onPrev={handlePrevPhoto}
          onNext={handleNextPhoto}
          slug={slug || ''}
          inviteToken={inviteToken}
          galleryToken={galleryToken}
          favorites={favorites}
          downloadingUuids={downloadingUuids}
          onToggleFavorite={handleToggleFavorite}
          onDownload={handleDownload}
          onShare={handleShare}
        />
      )}

      {/* ─── Email Collection Modal ─────────────────────────────────────────── */}
      {mounted && isEmailModalOpen && createPortal(
        <div className="fixed inset-0 bg-background/80 backdrop-blur-sm z-[60] flex items-center justify-center p-4" onClick={() => setIsEmailModalOpen(false)}>
          <div className="bg-card border border-border rounded-none p-6 max-w-sm w-full shadow-xl space-y-4 animate-in fade-in zoom-in duration-200" onClick={(e) => e.stopPropagation()}>
            <div className="flex justify-between items-center">
              <h3 className="text-lg font-bold text-foreground">Save Your Favorites</h3>
              <button onClick={() => setIsEmailModalOpen(false)} aria-label="Close modal" className="text-muted-foreground hover:text-foreground p-1 rounded-none hover:bg-secondary/35 transition-colors">
                <X size={18} />
              </button>
            </div>
            <p className="text-xs text-muted-foreground leading-relaxed">
              Please enter your email to create your selection. This lets the photographer know which photos you've selected.
            </p>
            <form onSubmit={handleEmailSubmit} className="space-y-3">
              <input
                type="email"
                required
                placeholder="Enter email address..."
                value={emailInput}
                onChange={(e) => {
                  setEmailInput(e.target.value);
                  setEmailError('');
                }}
                className="w-full px-3 py-2.5 bg-background border border-border rounded-none text-sm placeholder-muted-foreground/60 focus:outline-none focus:border-primary transition-colors text-foreground"
              />
              {emailError && <p className="text-[11px] text-destructive font-medium">{emailError}</p>}
              <div className="flex gap-2">
                <button
                  type="button"
                  onClick={() => setIsEmailModalOpen(false)}
                  className="flex-1 py-2.5 bg-secondary text-secondary-foreground hover:bg-secondary/80 rounded-none text-xs font-semibold transition-colors"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="flex-1 py-2.5 bg-primary text-primary-foreground hover:bg-accent rounded-none text-xs font-semibold transition-colors"
                >
                  Save & Favorite
                </button>
              </div>
            </form>
          </div>
        </div>,
        document.body
      )}

      {/* ─── Social Share Modal ─────────────────────────────────────────────── */}
      {mounted && sharingPhoto && (() => {
        const shareUrl = `${window.location.origin}/g/${slug}?photo=${sharingPhoto.uuid}`;
        const encodedUrl = encodeURIComponent(shareUrl);
        const shareText = encodeURIComponent(`Check out this photo from the gallery: ${shareUrl}`);
        
        return createPortal(
          <div className="fixed inset-0 bg-background/80 backdrop-blur-sm z-[60] flex items-center justify-center p-4" onClick={() => setSharingPhoto(null)}>
            <div className="bg-card border border-border rounded-none p-6 max-w-sm w-full shadow-xl space-y-5 animate-in fade-in zoom-in duration-200" onClick={(e) => e.stopPropagation()}>
              <div className="flex justify-between items-center">
                <h3 className="text-lg font-bold text-foreground">Share Photo</h3>
                <button onClick={() => setSharingPhoto(null)} aria-label="Close modal" className="text-muted-foreground hover:text-foreground p-1 rounded-none hover:bg-secondary/35 transition-colors">
                  <X size={18} />
                </button>
              </div>
              
              {/* Direct Link Input and Copy Button */}
              <div className="flex gap-2">
                <input
                  type="text"
                  readOnly
                  value={shareUrl}
                  onClick={(e) => (e.target as HTMLInputElement).select()}
                  className="flex-1 min-w-0 px-3 py-2 bg-secondary/30 border border-border rounded-none text-xs text-muted-foreground focus:outline-none select-all"
                />
                <button
                  onClick={() => {
                    navigator.clipboard.writeText(shareUrl);
                    setIsCopied(true);
                    setTimeout(() => setIsCopied(false), 2000);
                  }}
                  className="px-4 py-2 bg-primary text-primary-foreground hover:bg-accent font-semibold rounded-none text-xs transition-colors shrink-0 flex items-center gap-1.5 min-w-[90px] justify-center"
                >
                  {isCopied ? (
                    <>
                      <Check size={14} />
                      <span>Copied!</span>
                    </>
                  ) : (
                    <>
                      <Copy size={14} />
                      <span>Copy Link</span>
                    </>
                  )}
                </button>
              </div>

              {/* Social Grid */}
              <div className="grid grid-cols-4 gap-3 py-2">
                {/* WhatsApp */}
                <a
                  href={`https://api.whatsapp.com/send?text=${shareText}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="flex flex-col items-center gap-1.5 p-2 rounded-none hover:bg-secondary/25 transition-colors text-muted-foreground hover:text-foreground"
                >
                  <div className="w-10 h-10 rounded-none bg-emerald-500/10 flex items-center justify-center text-emerald-500">
                    <svg className="w-5 h-5 fill-current" viewBox="0 0 24 24">
                      <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 12.008.01c3.202.001 6.212 1.246 8.477 3.514 2.266 2.268 3.507 5.28 3.505 8.484-.004 6.657-5.34 11.997-11.953 11.997-2.005-.001-3.973-.502-5.717-1.455L0 24zm6.59-4.846c1.6.95 3.197 1.451 4.887 1.453 5.485 0 9.948-4.463 9.952-9.953.002-2.66-1.025-5.161-2.894-7.03C16.626 1.8 14.127.777 11.468.777c-5.482 0-9.94 4.466-9.944 9.954-.002 1.79.49 3.54 1.428 5.09L1.92 22.18l6.727-1.761c1.586.865 3.323 1.32 5.074 1.322z" />
                    </svg>
                  </div>
                  <span className="text-[10px] font-medium">WhatsApp</span>
                </a>

                {/* Messenger */}
                <a
                  href={`fb-messenger://share/?link=${encodedUrl}`}
                  onClick={(e) => {
                    if (!navigator.userAgent.match(/(iPad|iPhone|iPod|Android)/g)) {
                      e.preventDefault();
                      window.open(`https://www.facebook.com/dialog/send?link=${encodedUrl}&app_id=291494419107518&redirect_uri=${encodedUrl}`, '_blank');
                    }
                  }}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="flex flex-col items-center gap-1.5 p-2 rounded-none hover:bg-secondary/25 transition-colors text-muted-foreground hover:text-foreground"
                >
                  <div className="w-10 h-10 rounded-none bg-blue-500/10 flex items-center justify-center text-blue-500">
                    <svg className="w-5 h-5 fill-current" viewBox="0 0 24 24">
                      <path d="M12 0C5.37 0 0 4.97 0 11.11c0 3.5 1.74 6.62 4.47 8.58.23.17.37.44.37.73v2.81c0 .54.58.91 1.07.67l3.12-1.56c.21-.1.45-.13.68-.08 1.15.26 2.37.4 3.63.4 6.63 0 12-4.97 12-11.11S18.63 0 12 0zm1.22 14.86l-2.48-2.65-4.84 2.65c-.47.26-.98-.28-.7-.74l2.74-4.51L5.46 7c-.47-.26-.98.28-.7.74l2.74 4.51 2.48 2.65 4.84-2.65c.47-.26.98.28.7.74l-2.74 4.51c-.13.22-.36.36-.61.36z" />
                    </svg>
                  </div>
                  <span className="text-[10px] font-medium">Messenger</span>
                </a>

                {/* Facebook */}
                <a
                  href={`https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="flex flex-col items-center gap-1.5 p-2 rounded-none hover:bg-secondary/25 transition-colors text-muted-foreground hover:text-foreground"
                >
                  <div className="w-10 h-10 rounded-none bg-blue-600/10 flex items-center justify-center text-blue-600">
                    <svg className="w-5 h-5 fill-current" viewBox="0 0 24 24">
                      <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                    </svg>
                  </div>
                  <span className="text-[10px] font-medium">Facebook</span>
                </a>

                {/* Email */}
                <a
                  href={`mailto:?subject=Check%20out%20this%20photo&body=Here%20is%20the%20link%20to%20the%20photo%20I%20liked:%20${encodedUrl}`}
                  className="flex flex-col items-center gap-1.5 p-2 rounded-none hover:bg-secondary/25 transition-colors text-muted-foreground hover:text-foreground"
                >
                  <div className="w-10 h-10 rounded-none bg-indigo-500/10 flex items-center justify-center text-indigo-500">
                    <Mail size={18} />
                  </div>
                  <span className="text-[10px] font-medium">Email</span>
                </a>

                {/* X / Twitter */}
                <a
                  href={`https://twitter.com/intent/tweet?text=${shareText}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="flex flex-col items-center gap-1.5 p-2 rounded-none hover:bg-secondary/25 transition-colors text-muted-foreground hover:text-foreground"
                >
                  <div className="w-10 h-10 rounded-none bg-slate-900/10 dark:bg-white/10 flex items-center justify-center text-foreground">
                    <svg className="w-4.5 h-4.5 fill-current" viewBox="0 0 24 24">
                      <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z" />
                    </svg>
                  </div>
                  <span className="text-[10px] font-medium">X</span>
                </a>

                {/* Pinterest */}
                <a
                  href={`https://pinterest.com/pin/create/button/?url=${encodedUrl}&media=${encodeURIComponent(sharingPhoto.cdn_url)}&description=${shareText}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="flex flex-col items-center gap-1.5 p-2 rounded-none hover:bg-secondary/25 transition-colors text-muted-foreground hover:text-foreground"
                >
                  <div className="w-10 h-10 rounded-none bg-rose-600/10 flex items-center justify-center text-rose-600">
                    <svg className="w-5 h-5 fill-current" viewBox="0 0 24 24">
                      <path d="M12.017 0C5.396 0 .029 5.367.029 11.987c0 5.079 3.158 9.417 7.618 11.162-.105-.949-.199-2.403.041-3.439.219-.937 1.406-5.957 1.406-5.957s-.359-.72-.359-1.781c0-1.663.967-2.911 2.168-2.911 1.024 0 1.518.769 1.518 1.688 0 1.029-.653 2.567-.992 3.992-.285 1.193.6 2.168 1.777 2.168 2.133 0 3.77-2.249 3.77-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738.098.119.112.224.083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.359-.631-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146 1.124.347 2.317.535 3.554.535 6.621 0 11.988-5.367 11.988-11.987C24 5.367 18.638 0 12.017 0z" />
                    </svg>
                  </div>
                  <span className="text-[10px] font-medium">Pinterest</span>
                </a>

                {/* Threads */}
                <a
                  href={`https://threads.net/intent/post?text=${shareText}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="flex flex-col items-center gap-1.5 p-2 rounded-none hover:bg-secondary/25 transition-colors text-muted-foreground hover:text-foreground"
                >
                  <div className="w-10 h-10 rounded-none bg-black/10 dark:bg-white/10 flex items-center justify-center text-foreground">
                    <svg className="w-5 h-5 fill-current" viewBox="0 0 24 24">
                      <path d="M12 0c6.627 0 12 5.373 12 12s-5.373 12-12 12S0 19.627 0 12 5.373 0 12 0zm3.84 14.86c.64-.64 1.02-1.53 1.02-2.51v-.47a.9.9 0 00-.9-.9.9.9 0 00-.9.9v.47c0 .48-.19.92-.51 1.24a1.76 1.76 0 01-2.49 0c-.32-.32-.51-.76-.51-1.24v-1.7c0-.48.19-.92.51-1.24.32-.32.76-.51 1.24-.51.48 0 .92.19 1.24.51.1.1.18.21.25.32a.9.9 0 101.52-.96 3.56 3.56 0 00-.81-.97 3.55 3.55 0 00-2.2-.65 3.55 3.55 0 00-2.51 1.04 3.55 3.55 0 00-1.04 2.51v1.7c0 .98.39 1.87 1.04 2.51.64.64 1.53 1.03 2.51 1.03.98 0 1.87-.39 2.51-1.03z" />
                    </svg>
                  </div>
                  <span className="text-[10px] font-medium">Threads</span>
                </a>
              </div>
            </div>
          </div>,
          document.body
        );
      })()}
    </div>
  );
}
