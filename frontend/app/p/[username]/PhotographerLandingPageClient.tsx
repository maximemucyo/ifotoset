'use client'

import { useEffect, useMemo, useState } from 'react'
import Link from 'next/link'
import Image from 'next/image'
import {
  ArrowLeft,
  ArrowRight,
  Camera,
  Mail,
  MapPin,
  MessageCircle,
  Phone,
  Star,
  Clock3,
  Check,
  Globe,
  Loader2,
} from 'lucide-react'
import {
  usePhotographerProfile,
  usePhotographerReviews,
  useSubmitPhotographerReview,
  PublicPhotographerData,
  PublicReviewItem,
} from '@/lib/queries/public'
import { BookingForm } from '@/components/public/BookingForm'
import { Logo } from '@/components/logo'
import { ThemeToggle } from '@/components/theme-toggle'

// ─── WhatsApp helper ──────────────────────────────────────────────────────────
function waLink(phone: string, name: string) {
  const number = phone.replace(/\D/g, '')
  return `https://wa.me/${number}?text=${encodeURIComponent(`Hi ${name}, I'd like to book a photography session.`)}`
}

// ─── Category classification helper ───────────────────────────────────────────
function getGalleryCategory(title: string): string {
  const t = title.trim().toLowerCase()
  if (
    t.includes('wedding') ||
    t.includes('bride') ||
    t.includes('groom') ||
    t.includes('couple') ||
    t.includes('marry') ||
    t.includes('marriage') ||
    t.includes('ceremony')
  ) {
    return 'Weddings'
  }
  if (
    t.includes('portrait') ||
    t.includes('headshot') ||
    t.includes('model') ||
    t.includes('studio')
  ) {
    return 'Portraits'
  }
  if (
    t.includes('fashion') ||
    t.includes('magazine') ||
    t.includes('editorial')
  ) {
    return 'Editorial'
  }
  if (
    t.includes('party') ||
    t.includes('event') ||
    t.includes('celebrat') ||
    t.includes('festival') ||
    t.includes('concert') ||
    t.includes('gig')
  ) {
    return 'Events'
  }
  return 'Other'
}

interface PhotographerLandingPageClientProps {
  username: string
  initialData: PublicPhotographerData
  initialReviews: PublicReviewItem[]
}

export function PhotographerLandingPageClient({
  username,
  initialData,
  initialReviews,
}: PhotographerLandingPageClientProps) {
  // Query with hydration initialData, avoiding double fetches
  const { data } = usePhotographerProfile(username, initialData)
  const { data: reviewsData } = usePhotographerReviews(username, initialReviews)

  const photographerData = data || initialData
  const reviews = reviewsData || initialReviews

  const [activeCategory, setActiveCategory] = useState('All work')
  const [preSelectedPackageId, setPreSelectedPackageId] = useState<string | undefined>()

  const reviewMutation = useSubmitPhotographerReview(username)

  const [showReviewForm, setShowReviewForm] = useState(false)
  const [newReview, setNewReview] = useState({
    name: '',
    quote: '',
    rating: 5,
    detail: '',
  })

  const handleAddReview = (e: React.FormEvent) => {
    e.preventDefault()
    if (!newReview.name.trim() || !newReview.quote.trim()) return

    reviewMutation.mutate({
      name: newReview.name,
      quote: newReview.quote,
      rating: newReview.rating,
      detail: newReview.detail || null,
    }, {
      onSuccess: () => {
        setNewReview({ name: '', quote: '', rating: 5, detail: '' })
        setShowReviewForm(false)
      }
    })
  }

  useEffect(() => {
    const searchParams = new URLSearchParams(window.location.search)
    const pkg = searchParams.get('package')
    if (pkg) setPreSelectedPackageId(pkg)

    if (pkg) {
      setTimeout(() => {
        const el = document.getElementById('book')
        if (el) el.scrollIntoView({ behavior: 'smooth' })
      }, 800)
    }
  }, [])

  const scrollToBooking = (packageId?: string) => {
    const el = document.getElementById('book')
    if (el) {
      el.scrollIntoView({ behavior: 'smooth' })
    }
    if (packageId) {
      setPreSelectedPackageId(packageId)
      const url = new URL(window.location.href)
      url.searchParams.set('package', packageId)
      window.history.replaceState({}, '', url.toString())
    }
  }

  // Compile categories & filter galleries based on live data
  const { categories, filteredGalleries, coverImageUrl, firstGallery } = useMemo(() => {
    const galleries = photographerData.featured_galleries ?? []
    const first = galleries[0] ?? null
    const cover = first?.cover_url ?? null

    const found = new Set<string>()
    galleries.forEach((g) => {
      found.add(getGalleryCategory(g.title))
    })

    const ordered = ['Weddings', 'Portraits', 'Editorial', 'Events', 'Other'].filter((c) => found.has(c))
    const cats = ['All work', ...ordered]

    const filtered = activeCategory === 'All work'
      ? galleries
      : galleries.filter((g) => getGalleryCategory(g.title) === activeCategory)

    return {
      categories: cats,
      filteredGalleries: filtered,
      coverImageUrl: cover,
      firstGallery: first,
    }
  }, [photographerData, activeCategory])

  const { photographer, packages } = photographerData

  return (
    <main className="min-h-screen bg-background text-foreground">
      {/* ─── Header ─── */}
      <header className="sticky top-0 z-40 border-b border-border/80 bg-background/90 backdrop-blur-xl">
        <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-5 lg:px-8">
          <div className="flex items-center gap-8">
            <Link
              href="/"
              aria-label="Back to ifotoset home"
              className="hidden items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground sm:flex"
            >
              <ArrowLeft className="size-4" /> Back to ifotoset
            </Link>
            <Logo size="sm" href="/" />
          </div>
          <div className="flex items-center gap-3">
            <ThemeToggle />
            <button
              onClick={() => scrollToBooking()}
              className="hidden rounded-full bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground transition-transform hover:-translate-y-0.5 sm:block"
            >
              Check availability
            </button>
          </div>
        </div>
      </header>

      {/* ─── Hero Section ─── */}
      <section className="relative overflow-hidden border-b border-border">
        <div className="mx-auto grid max-w-7xl gap-10 px-5 py-12 lg:grid-cols-[1fr_1.15fr] lg:items-center lg:px-8 lg:py-20">
          <div className="max-w-xl">
            <div className="mb-5 inline-flex items-center gap-2 rounded-full border border-primary/25 bg-primary/10 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.18em] text-primary">
              <span className="size-1.5 rounded-full bg-primary" /> Available for {new Date().getFullYear()} bookings
            </div>
            <h1 className="text-pretty font-serif text-5xl leading-[0.98] tracking-tight text-foreground sm:text-7xl">
              Stories that feel <em className="text-primary">like home.</em>
            </h1>
            <p className="mt-6 max-w-lg text-lg leading-8 text-muted-foreground">
              I&apos;m {photographer.name}. {photographer.bio || 'Preserving the warmth, joy, and quiet details of your most meaningful days.'}
            </p>
            <div className="mt-8 flex flex-wrap items-center gap-5 text-sm text-muted-foreground">
              {photographer.location && (
                <span className="inline-flex items-center gap-2">
                  <MapPin className="size-4 text-primary" /> {photographer.location}
                </span>
              )}
              {photographer.website && (
                <a
                  href={photographer.website.startsWith('http') ? photographer.website : `https://${photographer.website}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center gap-2 hover:text-foreground transition-colors"
                >
                  <Globe className="size-4 text-primary" /> {photographer.website.replace(/^https?:\/\//, '')}
                </a>
              )}
            </div>
            <div className="mt-9 flex flex-wrap gap-3">
              <button
                onClick={() => scrollToBooking()}
                className="inline-flex items-center gap-2 rounded-full bg-primary px-6 py-3.5 font-semibold text-primary-foreground transition-transform hover:-translate-y-0.5"
              >
                Book a session <ArrowRight className="size-4" />
              </button>
              {photographer.phone && (
                <a
                  href={waLink(photographer.phone, photographer.name)}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center gap-2 rounded-full border border-border px-6 py-3.5 font-semibold text-foreground transition-colors hover:bg-secondary"
                >
                  <MessageCircle className="size-4 text-primary" /> WhatsApp
                </a>
              )}
            </div>
          </div>

          <div className="relative min-h-[430px] overflow-hidden rounded-[2rem] bg-secondary lg:min-h-[560px]">
            {coverImageUrl ? (
              <Image
                src={coverImageUrl}
                alt={`${photographer.name} featured photograph`}
                fill
                className="absolute inset-0 size-full object-cover"
                sizes="(max-width: 1024px) 100vw, 50vw"
                priority
              />
            ) : (
              <div className="absolute inset-0 flex items-center justify-center bg-secondary">
                <Camera className="size-16 text-muted-foreground/30 animate-pulse" />
              </div>
            )}
            <div className="absolute inset-0 bg-gradient-to-t from-foreground/60 via-transparent to-transparent pointer-events-none" />
            {firstGallery && (
              <div className="absolute bottom-6 left-6 right-6 flex items-end justify-between text-primary-foreground">
                <div>
                  <p className="text-xs uppercase tracking-[0.2em] text-primary-foreground/70">Featured story</p>
                  <p className="mt-1 font-serif text-2xl">{firstGallery.title}</p>
                </div>
                <a
                  href={`/g/${firstGallery.slug}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="rounded-full border border-primary-foreground/40 bg-black/25 backdrop-blur-md px-3 py-1.5 text-xs hover:bg-black/50 transition-colors"
                >
                  View gallery
                </a>
              </div>
            )}
          </div>
        </div>
      </section>

      {/* ─── Portfolio Section ─── */}
      {filteredGalleries.length > 0 && (
        <section className="mx-auto max-w-7xl px-5 py-20 lg:px-8" id="portfolio">
          <div className="flex flex-col justify-between gap-6 sm:flex-row sm:items-end">
            <div>
              <p className="text-xs font-semibold uppercase tracking-[0.2em] text-primary">Selected work</p>
              <h2 className="mt-3 font-serif text-4xl tracking-tight sm:text-5xl">A visual love letter</h2>
            </div>
            {categories.length > 1 && (
              <div className="flex flex-wrap gap-2">
                {categories.map((category) => (
                  <button
                    key={category}
                    onClick={() => setActiveCategory(category)}
                    className={`rounded-full px-4 py-2 text-sm transition-colors ${
                      activeCategory === category
                        ? 'bg-foreground text-background font-semibold'
                        : 'bg-secondary text-muted-foreground hover:text-foreground'
                    }`}
                  >
                    {category}
                  </button>
                ))}
              </div>
            )}
          </div>

          <div className="mt-10 grid auto-rows-[220px] grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:auto-rows-[260px]">
            {filteredGalleries.map((gallery, index) => {
              const isLarge = index === 0 || index === 3
              const gridClass = index === 0 ? 'col-span-2 row-span-2' : index === 3 ? 'row-span-2' : ''
              return (
                <a
                  key={gallery.uuid}
                  href={`/g/${gallery.slug}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  className={`group relative overflow-hidden rounded-2xl bg-secondary ${gridClass}`}
                >
                  {gallery.cover_url ? (
                    <Image
                      src={gallery.cover_url}
                      alt={gallery.title}
                      fill
                      className="size-full object-cover transition duration-700 group-hover:scale-105"
                      sizes={isLarge ? '(max-width: 768px) 100vw, 50vw' : '(max-width: 768px) 50vw, 33vw'}
                    />
                  ) : (
                    <div className="w-full h-full bg-secondary flex items-center justify-center">
                      <Camera className="size-8 text-muted-foreground/30" />
                    </div>
                  )}
                  <div className="absolute inset-0 bg-gradient-to-t from-foreground/70 via-foreground/20 to-transparent opacity-0 transition-opacity group-hover:opacity-100" />
                  <div className="absolute bottom-4 left-4 right-4 opacity-0 transition-opacity group-hover:opacity-100 flex flex-col text-primary-foreground">
                    <span className="text-xs font-medium uppercase tracking-[0.16em] text-primary-foreground/70">
                      {getGalleryCategory(gallery.title)}
                    </span>
                    <span className="text-sm font-semibold mt-1 truncate">
                      {gallery.title}
                    </span>
                    {gallery.event_date && (
                      <span className="text-[10px] text-primary-foreground/60 mt-0.5">
                        {new Date(gallery.event_date).toLocaleDateString([], { month: 'short', year: 'numeric' })}
                      </span>
                    )}
                  </div>
                </a>
              )
            })}
          </div>
        </section>
      )}

      {/* ─── Experience Section ─── */}
      <section className="border-y border-border bg-card/50">
        <div className="mx-auto grid max-w-7xl gap-10 px-5 py-20 lg:grid-cols-[1fr_1.2fr] lg:px-8">
          <div>
            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-primary">The experience</p>
            <h2 className="mt-3 max-w-md font-serif text-4xl leading-tight sm:text-5xl">Your memories deserve more than a folder.</h2>
            <p className="mt-6 max-w-md leading-7 text-muted-foreground">
              From our first conversation to the final gallery, I create a calm, intentional experience that lets you stay present while I take care of the details.
            </p>
            <div className="mt-8 flex items-center gap-4">
              <div className="flex -space-x-3">
                {[
                  'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=100&q=80',
                  'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=100&q=80',
                  'https://images.unsplash.com/photo-1531123897727-8f129e1688ce?auto=format&fit=crop&w=100&q=80',
                ].map((src, i) => (
                  <img
                    key={i}
                    src={src}
                    alt="Client portrait placeholder"
                    className="size-10 rounded-full border-2 border-background object-cover"
                  />
                ))}
              </div>
              <p className="text-sm text-muted-foreground">Trusted by clients across East Africa</p>
            </div>
          </div>
          <div className="grid gap-4 sm:grid-cols-2">
            <div className="rounded-2xl bg-secondary p-6">
              <span className="text-3xl font-serif text-primary">01</span>
              <h3 className="mt-8 text-lg font-semibold">A thoughtful plan</h3>
              <p className="mt-2 text-sm leading-6 text-muted-foreground">
                A relaxed discovery call and a custom timeline built around what matters most to you.
              </p>
            </div>
            <div className="rounded-2xl bg-foreground p-6 text-background">
              <span className="text-3xl font-serif text-primary-foreground/80">02</span>
              <h3 className="mt-8 text-lg font-semibold text-background">A calm presence</h3>
              <p className="mt-2 text-sm leading-6 text-background/70">
                Gentle direction when you want it, room to be yourselves when you don&apos;t.
              </p>
            </div>
            <div className="rounded-2xl bg-secondary p-6">
              <span className="text-3xl font-serif text-primary">03</span>
              <h3 className="mt-8 text-lg font-semibold">A gallery to keep</h3>
              <p className="mt-2 text-sm leading-6 text-muted-foreground">
                Hand-finished images in a private gallery, made to be revisited for years.
              </p>
            </div>
            <div className="rounded-2xl bg-primary p-6 text-primary-foreground">
              <span className="text-3xl font-serif text-primary-foreground/70">04</span>
              <h3 className="mt-8 text-lg font-semibold text-primary-foreground">Always human</h3>
              <p className="mt-2 text-sm leading-6 text-primary-foreground/75">
                Fast replies, local knowledge, and someone in your corner throughout.
              </p>
            </div>
          </div>
        </div>
      </section>

      {/* ─── Packages / Collections Section ─── */}
      {packages.length > 0 && (
        <section className="mx-auto max-w-7xl px-5 py-20 lg:px-8" id="packages">
          <div className="text-center">
            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-primary">Collections</p>
            <h2 className="mt-3 font-serif text-4xl tracking-tight sm:text-5xl">Choose your way to remember</h2>
            <p className="mx-auto mt-4 max-w-xl text-muted-foreground">
              Every collection can be shaped around your story. We&apos;ll make sure the right one feels just right.
            </p>
          </div>
          <div className="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            {packages.map((pkg, idx) => {
              const isFeatured = idx === 0
              return (
                <article
                  key={pkg.uuid}
                  className={`relative flex flex-col rounded-2xl border p-7 ${
                    isFeatured
                      ? 'border-primary bg-primary text-primary-foreground shadow-xl shadow-primary/10'
                      : 'border-border bg-card'
                  }`}
                >
                  {isFeatured && (
                    <span className="absolute -top-3 left-7 rounded-full bg-foreground px-3 py-1 text-xs font-semibold text-background">
                      Most popular
                    </span>
                  )}
                  <p className={`text-xs font-semibold uppercase tracking-[0.18em] ${isFeatured ? 'text-primary-foreground/70' : 'text-primary'}`}>
                    {pkg.name}
                  </p>
                  <p className="mt-5 font-serif text-4xl">
                    {pkg.currency} {pkg.price.toLocaleString()}
                  </p>
                  <p className={`mt-3 min-h-12 text-sm leading-6 ${isFeatured ? 'text-primary-foreground/75' : 'text-muted-foreground'}`}>
                    {pkg.description || 'A custom-tailored package designed for your specific needs.'}
                  </p>

                  {pkg.duration_label && (
                    <div className={`mt-2 text-xs font-semibold ${isFeatured ? 'text-primary-foreground/70' : 'text-primary/80'}`}>
                      Duration: {pkg.duration_label}
                    </div>
                  )}

                  <div className={`my-7 h-px ${isFeatured ? 'bg-primary-foreground/20' : 'bg-border'}`} />

                  {pkg.deliverables && pkg.deliverables.length > 0 && (
                    <ul className="flex flex-1 flex-col gap-3 mb-8">
                      {pkg.deliverables.map((feature, i) => (
                        <li key={i} className="flex items-center gap-2 text-sm">
                          <Check className={`size-4 shrink-0 ${isFeatured ? 'text-primary-foreground' : 'text-primary'}`} />
                          <span>{feature}</span>
                        </li>
                      ))}
                    </ul>
                  )}

                  <button
                    onClick={() => scrollToBooking(pkg.uuid)}
                    className={`w-full rounded-full py-3 text-sm font-semibold transition-colors ${
                      isFeatured
                        ? 'bg-primary-foreground text-foreground hover:bg-background'
                        : 'bg-secondary text-foreground hover:bg-primary hover:text-primary-foreground'
                    }`}
                  >
                    Start with {pkg.name}
                  </button>
                </article>
              )
            })}
          </div>
        </section>
      )}

      {/* ─── Booking Section ─── */}
      <section className="border-y border-border bg-card/50" id="availability">
        <div className="mx-auto grid max-w-7xl gap-10 px-5 py-20 lg:grid-cols-[0.85fr_1.15fr] lg:px-8">
          <div className="max-w-md">
            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-primary">Let&apos;s make a plan</p>
            <h2 className="mt-3 font-serif text-4xl leading-tight sm:text-5xl">Find a date that feels right.</h2>
            <p className="mt-5 leading-7 text-muted-foreground">
              Pick a package and date to book your session. We&apos;ll talk about your plans, answer your questions, and create something beautiful together.
            </p>
            <div className="mt-8 space-y-4">
              <div className="flex items-center gap-3 text-sm">
                <span className="flex size-9 items-center justify-center rounded-full bg-primary/10 text-primary">
                  <Camera className="size-4" />
                </span>
                <span>
                  <strong className="block">Professional Photography</strong>
                  <span className="text-muted-foreground">High-quality edited images in a private online gallery</span>
                </span>
              </div>
              <div className="flex items-center gap-3 text-sm">
                <span className="flex size-9 items-center justify-center rounded-full bg-primary/10 text-primary">
                  <Clock3 className="size-4" />
                </span>
                <span>
                  <strong className="block">Flexible booking</strong>
                  <span className="text-muted-foreground">Choose the date and time that works best for you</span>
                </span>
              </div>
            </div>
          </div>

          <div id="book" className="rounded-3xl border border-border bg-background p-6 sm:p-8 shadow-2xl">
            <BookingForm
              username={username}
              packages={packages}
              preSelectedPackageId={preSelectedPackageId}
              studioLocation={photographer.location}
            />
          </div>
        </div>
      </section>

      {/* ─── Reviews / Testimonials ─── */}
      <section className="mx-auto max-w-7xl px-5 py-20 lg:px-8">
        <div className="flex flex-col items-center justify-between gap-6 sm:flex-row border-b border-border pb-6 mb-12">
          <div>
            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-primary">Reviews</p>
            <h2 className="mt-3 font-serif text-4xl tracking-tight sm:text-5xl">What clients say</h2>
          </div>
          <button
            onClick={() => setShowReviewForm(!showReviewForm)}
            className="rounded-full bg-primary/10 border border-primary/20 px-5 py-2.5 text-sm font-semibold text-primary hover:bg-primary hover:text-primary-foreground transition-all"
          >
            {showReviewForm ? 'Cancel review' : 'Write a review'}
          </button>
        </div>

        {/* Review Form */}
        {showReviewForm && (
          <form onSubmit={handleAddReview} className="max-w-xl mx-auto bg-card border border-border rounded-3xl p-6 sm:p-8 mb-12 space-y-4 animate-in fade-in slide-in-from-top-3 duration-300">
            <h3 className="font-serif text-2xl mb-4">Share your experience</h3>
            
            <div className="grid gap-4 sm:grid-cols-2">
              <div>
                <label className="block text-xs font-bold text-muted-foreground uppercase mb-1">Your Name *</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Marie Claire"
                  value={newReview.name}
                  onChange={e => setNewReview({ ...newReview, name: e.target.value })}
                  className="w-full px-4 py-2.5 rounded-xl border border-border bg-input text-foreground placeholder-muted-foreground text-sm focus:outline-none focus:border-primary"
                />
              </div>
              <div>
                <label className="block text-xs font-bold text-muted-foreground uppercase mb-1">Service / Detail</label>
                <input
                  type="text"
                  placeholder="e.g. Wedding in Kigali"
                  value={newReview.detail}
                  onChange={e => setNewReview({ ...newReview, detail: e.target.value })}
                  className="w-full px-4 py-2.5 rounded-xl border border-border bg-input text-foreground placeholder-muted-foreground text-sm focus:outline-none focus:border-primary"
                />
              </div>
            </div>

            <div>
              <label className="block text-xs font-bold text-muted-foreground uppercase mb-2">Rating</label>
              <div className="flex gap-1">
                {[1, 2, 3, 4, 5].map((star) => (
                  <button
                    key={star}
                    type="button"
                    onClick={() => setNewReview({ ...newReview, rating: star })}
                    className="text-2xl hover:scale-110 transition-transform focus:outline-none"
                  >
                    <Star
                      className={`size-6 ${
                        star <= newReview.rating
                          ? 'fill-primary text-primary'
                          : 'text-muted-foreground/30'
                      }`}
                    />
                  </button>
                ))}
              </div>
            </div>

            <div>
              <label className="block text-xs font-bold text-muted-foreground uppercase mb-1">Your Review *</label>
              <textarea
                required
                rows={3}
                placeholder="How was your experience working together?..."
                value={newReview.quote}
                onChange={e => setNewReview({ ...newReview, quote: e.target.value })}
                className="w-full px-4 py-2.5 rounded-xl border border-border bg-input text-foreground placeholder-muted-foreground text-sm focus:outline-none focus:border-primary"
              />
            </div>

            <button
              type="submit"
              disabled={reviewMutation.isPending}
              className="w-full py-3 bg-primary text-primary-foreground font-semibold rounded-xl hover:bg-accent transition-colors disabled:opacity-60 flex items-center justify-center gap-2"
            >
              {reviewMutation.isPending && <Loader2 size={16} className="animate-spin" />}
              Submit Review
            </button>
          </form>
        )}

        {/* Reviews list */}
        {reviews.length === 0 ? (
          <div className="text-center py-12 border border-dashed border-border rounded-3xl text-muted-foreground bg-card/20">
            <Star className="size-10 mx-auto text-muted-foreground/30 mb-3 animate-pulse" />
            <p className="font-semibold text-sm">No reviews yet</p>
            <p className="text-xs mt-1">Be the first to share your experience with {photographer.name}!</p>
          </div>
        ) : (
          <div className="grid gap-6 md:grid-cols-3">
            {reviews.map((review, i) => (
              <figure key={i} className="rounded-2xl border border-border bg-card p-7 animate-in fade-in zoom-in-95 duration-300">
                <div className="flex gap-1 text-primary">
                  {Array.from({ length: 5 }).map((_, index) => (
                    <Star
                      key={index}
                      className={`size-4 ${
                        index < review.rating ? 'fill-current' : 'text-muted-foreground/20'
                      }`}
                    />
                  ))}
                </div>
                <blockquote className="mt-6 font-serif text-xl leading-8">
                  &quot;{review.quote}&quot;
                </blockquote>
                <figcaption className="mt-7 text-sm flex items-center justify-between">
                  <div>
                    <strong className="block">{review.name}</strong>
                    <span className="text-muted-foreground">{review.detail}</span>
                  </div>
                  {review.date && (
                    <span className="text-[10px] text-muted-foreground/50">
                      {new Date(review.date).toLocaleDateString([], { month: 'short', year: 'numeric' })}
                    </span>
                  )}
                </figcaption>
              </figure>
            ))}
          </div>
        )}
      </section>

      {/* ─── Footer ─── */}
      <footer className="border-t border-border">
        <div className="mx-auto flex max-w-7xl flex-col gap-6 px-5 py-8 sm:flex-row sm:items-center sm:justify-between lg:px-8">
          <Logo size="sm" href="/" />
          <div className="flex flex-wrap items-center gap-4 text-muted-foreground">
            <a href="https://instagram.com" aria-label="Instagram" className="hover:text-primary transition-colors">
              <Camera className="size-5" />
            </a>
            {photographer.phone && (
              <>
                <a
                  href={`tel:${photographer.phone}`}
                  aria-label={`Call ${photographer.name}`}
                  className="hover:text-primary transition-colors"
                >
                  <Phone className="size-5" />
                </a>
                <a
                  href={waLink(photographer.phone, photographer.name)}
                  aria-label={`WhatsApp ${photographer.name}`}
                  className="hover:text-primary transition-colors"
                >
                  <MessageCircle className="size-5" />
                </a>
              </>
            )}
            <span className="ml-3 text-xs">
              © {new Date().getFullYear()} {photographer.name} Studio. All rights reserved.
            </span>
          </div>
        </div>
      </footer>
    </main>
  )
}
