'use client'

import { useEffect, useRef, useState } from 'react'
import { useParams } from 'next/navigation'
import Image from 'next/image'
import {
  MapPin, Globe, Phone, Clock, Check, Camera, ChevronDown,
  Loader2, AlertCircle, ExternalLink, Star, Mail
} from 'lucide-react'
import { usePhotographerProfile, FeaturedGallery, PublicPackageItem } from '@/lib/queries/public'
import { BookingForm } from '@/components/public/BookingForm'

// ─── WhatsApp helper ──────────────────────────────────────────────────────────
function waLink(phone: string, name: string) {
  const number = phone.replace(/\D/g, '')
  return `https://wa.me/${number}?text=${encodeURIComponent(`Hi ${name}, I'd like to book a photography session.`)}`
}

// ─── Sticky Nav ───────────────────────────────────────────────────────────────
function StickyNav({ name, avatar_url }: { name: string; avatar_url: string | null }) {
  const [scrolled, setScrolled] = useState(false)
  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 80)
    window.addEventListener('scroll', onScroll, { passive: true })
    return () => window.removeEventListener('scroll', onScroll)
  }, [])

  return (
    <nav className={`fixed top-0 left-0 right-0 z-50 transition-all duration-300 ${
      scrolled
        ? 'bg-[#0f0a07]/90 backdrop-blur-xl border-b border-white/5 py-3'
        : 'bg-transparent py-5'
    }`}>
      <div className="max-w-6xl mx-auto px-4 sm:px-6 flex items-center justify-between">
        <div className="flex items-center gap-3">
          {avatar_url ? (
            <Image
              src={avatar_url}
              alt={name}
              width={36}
              height={36}
              className="rounded-full object-cover ring-2 ring-primary/40"
            />
          ) : (
            <div className="w-9 h-9 rounded-full bg-primary/20 flex items-center justify-center">
              <Camera size={16} className="text-primary" />
            </div>
          )}
          <span className="font-bold text-white text-sm">{name}</span>
        </div>
        <div className="flex items-center gap-3">
          <a href="#portfolio" className="hidden sm:block text-white/60 hover:text-white text-sm transition-colors">
            Portfolio
          </a>
          <a href="#packages" className="hidden sm:block text-white/60 hover:text-white text-sm transition-colors">
            Packages
          </a>
          <a
            href="#book"
            className="px-5 py-2 bg-primary hover:bg-accent text-white rounded-full font-bold text-sm transition-all shadow-lg shadow-primary/30 hover:shadow-primary/50 hover:scale-105"
          >
            Book Now
          </a>
        </div>
      </div>
    </nav>
  )
}

// ─── Hero Section ─────────────────────────────────────────────────────────────
function HeroSection({
  name, bio, location, avatar_url, website, phone, coverImageUrl
}: {
  name: string; bio: string | null; location: string | null;
  avatar_url: string | null; website: string | null; phone: string | null;
  coverImageUrl: string | null
}) {
  return (
    <section className="relative min-h-screen flex flex-col items-center justify-center overflow-hidden">
      {/* Background */}
      <div className="absolute inset-0 bg-gradient-to-br from-[#0f0a07] via-[#1a100a] to-[#0a0705]" />
      {coverImageUrl && (
        <div className="absolute inset-0">
          <Image
            src={coverImageUrl}
            alt="Portfolio background"
            fill
            className="object-cover opacity-10 scale-110 blur-xl"
            priority
          />
          <div className="absolute inset-0 bg-gradient-to-b from-[#0f0a07]/60 via-[#0f0a07]/40 to-[#0f0a07]" />
        </div>
      )}

      {/* Decorative orbs */}
      <div className="absolute top-1/4 left-1/4 w-72 h-72 bg-primary/15 rounded-full blur-3xl animate-pulse pointer-events-none" />
      <div className="absolute bottom-1/3 right-1/4 w-48 h-48 bg-accent/10 rounded-full blur-3xl pointer-events-none" />

      <div className="relative z-10 text-center px-4 sm:px-6 max-w-3xl mx-auto pt-32 pb-24">
        {/* Avatar */}
        <div className="mb-8 flex justify-center">
          {avatar_url ? (
            <div className="relative">
              <div className="absolute inset-0 rounded-full bg-primary/30 blur-xl scale-110" />
              <Image
                src={avatar_url}
                alt={name}
                width={128}
                height={128}
                className="relative rounded-full object-cover ring-4 ring-primary/50"
                priority
              />
            </div>
          ) : (
            <div className="w-32 h-32 rounded-full bg-primary/20 flex items-center justify-center ring-4 ring-primary/30">
              <Camera size={40} className="text-primary" />
            </div>
          )}
        </div>

        {/* Name + Tagline */}
        <div className="mb-2">
          <span className="text-xs font-bold uppercase tracking-widest text-primary bg-primary/10 px-4 py-1.5 rounded-full border border-primary/20">
            Photographer
          </span>
        </div>
        <h1 className="text-5xl sm:text-6xl md:text-7xl font-extrabold text-white mt-4 leading-tight tracking-tight">
          {name}
        </h1>

        {bio && (
          <p className="mt-5 text-lg text-white/60 leading-relaxed max-w-xl mx-auto">
            {bio}
          </p>
        )}

        {/* Meta */}
        <div className="flex flex-wrap items-center justify-center gap-4 mt-6 text-white/50 text-sm">
          {location && (
            <span className="flex items-center gap-1.5">
              <MapPin size={14} className="text-primary" /> {location}
            </span>
          )}
          {website && (
            <a
              href={website.startsWith('http') ? website : `https://${website}`}
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center gap-1.5 hover:text-white transition-colors"
            >
              <Globe size={14} className="text-primary" /> {website.replace(/^https?:\/\//, '')}
            </a>
          )}
        </div>

        {/* CTAs */}
        <div className="flex flex-col sm:flex-row items-center justify-center gap-4 mt-10">
          <a
            href="#book"
            className="px-8 py-4 bg-primary hover:bg-accent text-white rounded-full font-bold text-base transition-all shadow-xl shadow-primary/30 hover:shadow-primary/50 hover:scale-105 group"
          >
            Book a Session
            <span className="ml-2 inline-block group-hover:translate-x-1 transition-transform">→</span>
          </a>
          <a
            href="#portfolio"
            className="px-8 py-4 bg-white/10 hover:bg-white/15 border border-white/20 text-white rounded-full font-semibold text-base transition-all hover:scale-105"
          >
            View Portfolio
          </a>
        </div>

        {phone && (
          <a
            href={waLink(phone, name)}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-2 mt-6 text-white/40 hover:text-green-400 text-sm transition-colors"
          >
            <span className="text-lg">💬</span> WhatsApp
          </a>
        )}
      </div>

      {/* Scroll hint */}
      <div className="absolute bottom-8 left-1/2 -translate-x-1/2 animate-bounce text-white/30">
        <ChevronDown size={24} />
      </div>
    </section>
  )
}

// ─── Portfolio Section ────────────────────────────────────────────────────────
function PortfolioSection({ galleries }: { galleries: FeaturedGallery[] }) {
  if (galleries.length === 0) return null
  const baseUrl = process.env.NEXT_PUBLIC_APP_URL || ''

  return (
    <section id="portfolio" className="py-24 px-4 sm:px-6 max-w-7xl mx-auto">
      <div className="text-center mb-14">
        <span className="text-xs font-bold uppercase tracking-widest text-primary">Portfolio</span>
        <h2 className="text-4xl font-extrabold text-white mt-2">Featured Work</h2>
        <p className="text-white/50 mt-3 max-w-md mx-auto">A curated selection of sessions that define my style and approach.</p>
      </div>

      <div className="columns-2 sm:columns-3 lg:columns-4 gap-3 space-y-3">
        {galleries.map((gallery, idx) => (
          <a
            key={gallery.uuid}
            href={`/g/${gallery.slug}`}
            target="_blank"
            rel="noopener noreferrer"
            className="group block break-inside-avoid relative rounded-2xl overflow-hidden bg-white/5 hover:ring-2 hover:ring-primary/50 transition-all"
          >
            {gallery.cover_url ? (
              <Image
                src={gallery.cover_url}
                alt={gallery.title}
                width={600}
                height={idx % 3 === 0 ? 500 : 350}
                className="w-full object-cover group-hover:scale-105 transition-transform duration-500"
              />
            ) : (
              <div className="w-full h-48 bg-white/5 flex items-center justify-center">
                <Camera size={24} className="text-white/20" />
              </div>
            )}
            <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex flex-col justify-end p-4">
              <p className="text-white font-bold text-sm truncate">{gallery.title}</p>
              {gallery.event_date && (
                <p className="text-white/60 text-xs mt-0.5">
                  {new Date(gallery.event_date).toLocaleDateString([], { month: 'short', year: 'numeric' })}
                </p>
              )}
              <div className="flex items-center gap-1.5 mt-1">
                <ExternalLink size={11} className="text-white/50" />
                <span className="text-white/50 text-xs">View gallery</span>
              </div>
            </div>
          </a>
        ))}
      </div>
    </section>
  )
}

// ─── Packages Section ─────────────────────────────────────────────────────────
function PackagesSection({ packages }: { packages: PublicPackageItem[] }) {
  if (packages.length === 0) return null

  const handleSelectPackage = (packageId: string) => {
    const bookSection = document.getElementById('book')
    if (bookSection) {
      bookSection.scrollIntoView({ behavior: 'smooth' })
    }
    // Set the URL param for pre-selection (handled by BookingForm via prop)
    const url = new URL(window.location.href)
    url.searchParams.set('package', packageId)
    window.history.replaceState({}, '', url.toString())
  }

  return (
    <section id="packages" className="py-24 px-4 sm:px-6 bg-white/[0.02] border-y border-white/5">
      <div className="max-w-6xl mx-auto">
        <div className="text-center mb-14">
          <span className="text-xs font-bold uppercase tracking-widest text-primary">Services</span>
          <h2 className="text-4xl font-extrabold text-white mt-2">Packages</h2>
          <p className="text-white/50 mt-3 max-w-md mx-auto">Choose the package that fits your occasion and vision.</p>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          {packages.map((pkg, idx) => (
            <div
              key={pkg.uuid}
              className={`relative rounded-2xl border p-6 flex flex-col transition-all hover:scale-[1.02] group ${
                idx === 0
                  ? 'border-primary bg-primary/5 shadow-xl shadow-primary/10'
                  : 'border-white/10 bg-white/[0.03] hover:border-white/20'
              }`}
            >
              {idx === 0 && (
                <div className="absolute -top-3 left-6">
                  <span className="px-3 py-1 bg-primary text-white text-[10px] font-extrabold uppercase tracking-widest rounded-full shadow-lg">
                    Most Popular
                  </span>
                </div>
              )}

              <div className="mb-4">
                <h3 className="text-xl font-extrabold text-white">{pkg.name}</h3>
                {pkg.description && (
                  <p className="text-white/50 text-sm mt-2 leading-relaxed">{pkg.description}</p>
                )}
              </div>

              <div className="flex items-baseline gap-1 mb-1">
                <span className="text-3xl font-extrabold text-white">{pkg.currency}</span>
                <span className="text-4xl font-extrabold text-white">{pkg.price.toLocaleString()}</span>
              </div>

              <div className="flex items-center gap-2 mb-5 text-white/40 text-xs">
                <Clock size={12} />
                <span>{pkg.duration_label}</span>
                {pkg.computed_deposit_amount !== null && pkg.computed_deposit_amount > 0 && (
                  <>
                    <span>·</span>
                    <span className="text-primary font-semibold">
                      {pkg.currency} {pkg.computed_deposit_amount.toLocaleString()} deposit
                    </span>
                  </>
                )}
              </div>

              {pkg.deliverables && pkg.deliverables.length > 0 && (
                <ul className="space-y-2 mb-6 flex-1">
                  {pkg.deliverables.map((item, i) => (
                    <li key={i} className="flex items-start gap-2 text-sm text-white/70">
                      <Check size={14} className="text-primary shrink-0 mt-0.5" />
                      {item}
                    </li>
                  ))}
                </ul>
              )}

              <button
                onClick={() => handleSelectPackage(pkg.uuid)}
                className={`mt-auto py-3 rounded-xl font-bold text-sm transition-all ${
                  idx === 0
                    ? 'bg-primary hover:bg-accent text-white shadow-lg shadow-primary/30'
                    : 'bg-white/10 hover:bg-white/15 text-white'
                }`}
              >
                Select & Book
              </button>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}

// ─── About Section ────────────────────────────────────────────────────────────
function AboutSection({
  name, bio, location, website, phone
}: {
  name: string; bio: string | null; location: string | null; website: string | null; phone: string | null
}) {
  if (!bio && !location && !website && !phone) return null

  return (
    <section id="about" className="py-24 px-4 sm:px-6 max-w-4xl mx-auto text-center">
      <span className="text-xs font-bold uppercase tracking-widest text-primary">About</span>
      <h2 className="text-4xl font-extrabold text-white mt-2 mb-6">The Photographer</h2>
      {bio && (
        <p className="text-white/60 text-lg leading-relaxed max-w-2xl mx-auto">{bio}</p>
      )}
      <div className="flex flex-wrap items-center justify-center gap-6 mt-10 text-white/50 text-sm">
        {location && (
          <span className="flex items-center gap-2">
            <MapPin size={16} className="text-primary" /> {location}
          </span>
        )}
        {website && (
          <a
            href={website.startsWith('http') ? website : `https://${website}`}
            target="_blank"
            rel="noopener noreferrer"
            className="flex items-center gap-2 hover:text-white transition-colors"
          >
            <Globe size={16} className="text-primary" /> {website.replace(/^https?:\/\//, '')}
          </a>
        )}
        {phone && (
          <a
            href={waLink(phone, name)}
            target="_blank"
            rel="noopener noreferrer"
            className="flex items-center gap-2 hover:text-green-400 transition-colors"
          >
            <Phone size={16} className="text-primary" /> {phone}
          </a>
        )}
      </div>
    </section>
  )
}

// ─── Booking Section ──────────────────────────────────────────────────────────
function BookingSection({
  username, packages, preSelectedPackageId
}: {
  username: string; packages: PublicPackageItem[]; preSelectedPackageId?: string
}) {
  return (
    <section id="book" className="py-24 px-4 sm:px-6 relative">
      {/* Background */}
      <div className="absolute inset-0 bg-gradient-to-b from-transparent via-primary/5 to-transparent pointer-events-none" />
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-primary/10 via-transparent to-transparent pointer-events-none" />

      <div className="relative max-w-2xl mx-auto">
        <div className="text-center mb-12">
          <span className="text-xs font-bold uppercase tracking-widest text-primary">Book a Session</span>
          <h2 className="text-4xl font-extrabold text-white mt-2">Let's Create Together</h2>
          <p className="text-white/50 mt-3 max-w-md mx-auto">
            Fill in your details and I'll be in touch to confirm your session.
          </p>
        </div>

        <div className="bg-white/[0.04] border border-white/10 rounded-3xl p-6 sm:p-8 backdrop-blur-sm shadow-2xl">
          <BookingForm
            username={username}
            packages={packages}
            preSelectedPackageId={preSelectedPackageId}
          />
        </div>
      </div>
    </section>
  )
}

// ─── Not Found ────────────────────────────────────────────────────────────────
function NotFound({ username }: { username: string }) {
  return (
    <div className="min-h-screen bg-[#0f0a07] flex items-center justify-center text-center px-4">
      <div>
        <Camera size={56} className="text-white/20 mx-auto mb-4" />
        <h1 className="text-2xl font-bold text-white">Photographer not found</h1>
        <p className="text-white/50 mt-2">No photographer with username @{username} was found.</p>
        <a href="/" className="mt-6 inline-block px-6 py-3 bg-primary text-white rounded-xl font-bold">
          Back to ifotoset
        </a>
      </div>
    </div>
  )
}

// ─── Footer ───────────────────────────────────────────────────────────────────
function Footer({ name }: { name: string }) {
  return (
    <footer className="border-t border-white/5 py-8 px-4 sm:px-6 text-center">
      <p className="text-white/25 text-xs">
        © {new Date().getFullYear()} {name} · Powered by{' '}
        <a href="https://ifotoset.com" target="_blank" rel="noopener noreferrer" className="text-primary hover:underline">
          ifotoset
        </a>
      </p>
    </footer>
  )
}

// ─── Main Page ────────────────────────────────────────────────────────────────
export default function PhotographerLandingPage() {
  const params = useParams<{ username: string }>()
  const username = params?.username ?? ''

  const { data, isLoading, isError, error } = usePhotographerProfile(username)

  // Read pre-selected package from URL
  const [preSelectedPackageId, setPreSelectedPackageId] = useState<string | undefined>()
  useEffect(() => {
    const searchParams = new URLSearchParams(window.location.search)
    const pkg = searchParams.get('package')
    if (pkg) setPreSelectedPackageId(pkg)

    // Smooth scroll to #book if ?package= is in URL
    if (pkg) {
      setTimeout(() => {
        const el = document.getElementById('book')
        if (el) el.scrollIntoView({ behavior: 'smooth' })
      }, 800)
    }
  }, [])

  if (isLoading) {
    return (
      <div className="min-h-screen bg-[#0f0a07] flex items-center justify-center">
        <div className="text-center">
          <Loader2 size={40} className="text-primary animate-spin mx-auto mb-4" />
          <p className="text-white/40 text-sm">Loading profile…</p>
        </div>
      </div>
    )
  }

  if (isError || !data) {
    const status = (error as any)?.status
    if (status === 404) return <NotFound username={username} />
    return (
      <div className="min-h-screen bg-[#0f0a07] flex items-center justify-center text-center px-4">
        <div>
          <AlertCircle size={48} className="text-red-400 mx-auto mb-4" />
          <h1 className="text-xl font-bold text-white">Something went wrong</h1>
          <p className="text-white/50 mt-2 text-sm">{(error as any)?.message}</p>
        </div>
      </div>
    )
  }

  const { photographer, packages, featured_galleries } = data
  const coverImageUrl = featured_galleries[0]?.cover_url ?? null

  return (
    <div className="min-h-screen bg-[#0f0a07] text-white">
      <StickyNav name={photographer.name} avatar_url={photographer.avatar_url} />

      <HeroSection
        name={photographer.name}
        bio={photographer.bio}
        location={photographer.location}
        avatar_url={photographer.avatar_url}
        website={photographer.website}
        phone={photographer.phone}
        coverImageUrl={coverImageUrl}
      />

      <PortfolioSection galleries={featured_galleries} />

      <PackagesSection packages={packages} />

      <AboutSection
        name={photographer.name}
        bio={photographer.bio}
        location={photographer.location}
        website={photographer.website}
        phone={photographer.phone}
      />

      <BookingSection
        username={username}
        packages={packages}
        preSelectedPackageId={preSelectedPackageId}
      />

      <Footer name={photographer.name} />
    </div>
  )
}
