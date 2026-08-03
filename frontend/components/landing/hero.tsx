import Link from 'next/link'
import Image from 'next/image'
import { Heart, Download, Share2, Shield, Image as ImageIcon } from 'lucide-react'

export function Hero() {
  return (
    <section className="relative min-h-screen pt-32 pb-20 flex items-center justify-center bg-gradient-to-b from-background to-secondary/15 overflow-hidden">
      {/* Decorative Blur Orbs */}
      <div className="absolute inset-0 opacity-15 dark:opacity-10 pointer-events-none">
        <div className="absolute top-20 right-[-10%] w-96 h-96 bg-primary rounded-full mix-blend-multiply filter blur-3xl"></div>
        <div className="absolute bottom-20 left-[-10%] w-96 h-96 bg-accent rounded-full mix-blend-multiply filter blur-3xl"></div>
      </div>

      <div className="relative max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 w-full grid lg:grid-cols-12 gap-12 lg:gap-8 items-center">
        {/* Left Column - Content */}
        <div className="lg:col-span-6 text-center lg:text-left flex flex-col items-center lg:items-start">
          <h1 className="text-4xl sm:text-5xl md:text-6xl font-bold tracking-tight text-foreground leading-[1.1] mb-6 text-balance">
            The Complete Photography Platform for East Africa & Beyond
          </h1>
          <p className="text-lg sm:text-xl text-muted-foreground mb-8 max-w-xl text-balance">
            Everything you need to deliver beautiful galleries, showcase your work, and manage your photography business.
          </p>

          <div className="flex flex-col sm:flex-row gap-4 w-full sm:w-auto mb-8">
            <Link
              href="/signup"
              className="px-8 py-4 bg-primary text-primary-foreground text-center rounded-lg font-semibold hover:bg-accent transition-all shadow-md hover:shadow-lg text-lg"
            >
              Get Started Free
            </Link>
            <Link
              href="#showcase"
              className="px-8 py-4 border border-border bg-card text-foreground hover:bg-secondary text-center rounded-lg font-semibold transition-all text-lg"
            >
              Explore a Gallery
            </Link>
          </div>

          {/* Trust Row */}
          <div className="flex flex-wrap justify-center lg:justify-start gap-x-6 gap-y-2 text-muted-foreground text-sm font-medium">
            <div className="flex items-center gap-1.5">
              <span className="text-primary font-bold">✓</span> Free plan available
            </div>
            <div className="flex items-center gap-1.5">
              <span className="text-primary font-bold">✓</span> No credit card required
            </div>
            <div className="flex items-center gap-1.5">
              <span className="text-primary font-bold">✓</span> Upgrade anytime
            </div>
          </div>
        </div>

        {/* Right Column - Gallery Mockup */}
        <div className="lg:col-span-6 w-full max-w-xl mx-auto lg:max-w-none flex justify-center">
          <div className="w-full bg-card rounded-2xl border border-border/80 shadow-2xl shadow-primary/5 overflow-hidden transition-all duration-500 hover:border-primary/30">
            {/* Top Toolbar / Status Bar */}
            <div className="border-b border-border/85 bg-secondary/30 px-6 py-3.5 flex items-center justify-between">
              <div className="flex items-center gap-4">
                {/* Logo and site tag */}
                <div className="flex items-center gap-2">
                  <div className="w-6 h-6 relative">
                    <Image
                      src="/logo.png"
                      alt="ifotoset Logo"
                      fill
                      className="object-contain"
                    />
                  </div>
                  <span className="font-bold text-sm tracking-tight text-foreground">
                    ifoto<span className="text-primary">set</span>
                  </span>
                </div>
                <span className="text-xs text-muted-foreground border-l border-border pl-3 hidden sm:inline">
                  Client View
                </span>
              </div>
              <div className="flex items-center gap-1">
                <div className="w-2.5 h-2.5 rounded-full bg-border"></div>
                <div className="w-2.5 h-2.5 rounded-full bg-border ml-1"></div>
                <div className="w-2.5 h-2.5 rounded-full bg-border ml-1"></div>
              </div>
            </div>

            {/* Gallery Cover Block */}
            <div className="relative h-60 w-full overflow-hidden group">
              <Image
                src="https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=800&q=80"
                alt="Sarah & James Wedding"
                fill
                className="object-cover"
                priority
              />
              <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>
              <div className="absolute bottom-6 left-6 text-white">
                <h3 className="text-2xl font-bold tracking-tight">Sarah & James</h3>
                <p className="text-white/80 text-sm mt-1">Wedding Collection</p>
              </div>
            </div>

            {/* Gallery Content Area */}
            <div className="p-6">
              {/* Metadata Info & Action Buttons */}
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-border/80 pb-5 mb-6">
                <div className="flex items-center gap-4 text-xs font-semibold text-muted-foreground">
                  <span className="flex items-center gap-1 bg-secondary/50 px-2.5 py-1 rounded">
                    <ImageIcon size={14} className="text-primary" /> 420 Photos
                  </span>
                  <span className="flex items-center gap-1 bg-secondary/50 px-2.5 py-1 rounded">
                    <Shield size={14} className="text-primary" /> Password Protected
                  </span>
                </div>
                <div className="flex items-center gap-2">
                  <button className="flex items-center gap-1.5 px-3 py-1.5 bg-secondary hover:bg-border text-foreground text-xs font-semibold rounded-lg transition-colors" title="Download Collection">
                    <Download size={14} className="text-muted-foreground" />
                    Download
                  </button>
                  <button className="flex items-center gap-1.5 px-3 py-1.5 bg-secondary hover:bg-border text-foreground text-xs font-semibold rounded-lg transition-colors" title="Favorite Photo">
                    <Heart size={14} className="text-muted-foreground" />
                    Favorite
                  </button>
                  <button className="flex items-center gap-1.5 px-3 py-1.5 bg-secondary hover:bg-border text-foreground text-xs font-semibold rounded-lg transition-colors" title="Share Collection">
                    <Share2 size={14} className="text-muted-foreground" />
                    Share
                  </button>
                </div>
              </div>

              {/* Grid representation */}
              <div className="grid grid-cols-3 gap-3">
                <div className="relative aspect-[3/2] rounded-lg overflow-hidden border border-border/50">
                  <Image
                    src="https://images.unsplash.com/photo-1472396961693-142e6e269027?auto=format&fit=crop&w=400&q=80"
                    alt="Outdoor couple portrait"
                    fill
                    className="object-cover"
                  />
                </div>
                <div className="relative aspect-[3/2] rounded-lg overflow-hidden border border-border/50">
                  <Image
                    src="https://images.unsplash.com/photo-1513694203232-719a280e022f?auto=format&fit=crop&w=400&q=80"
                    alt="Wedding rings details"
                    fill
                    className="object-cover"
                  />
                </div>
                <div className="relative aspect-[3/2] rounded-lg overflow-hidden border border-border/50">
                  <Image
                    src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=400&q=80"
                    alt="Bridal portrait details"
                    fill
                    className="object-cover"
                  />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  )
}
export default Hero
