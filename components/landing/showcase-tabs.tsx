'use client'

import { useState, useRef, KeyboardEvent } from 'react'
import Image from 'next/image'
import { Heart, Download, Share2, Shield, Calendar, Users, BarChart3, ChevronRight, Eye } from 'lucide-react'
import { Section } from './section'
import { showcaseTabs } from './data'

export function ShowcaseTabs() {
  const [activeTab, setActiveTab] = useState<'galleries' | 'portfolio' | 'studio'>('galleries')
  const tabRefs = {
    galleries: useRef<HTMLButtonElement>(null),
    portfolio: useRef<HTMLButtonElement>(null),
    studio: useRef<HTMLButtonElement>(null),
  }

  const handleKeyDown = (e: KeyboardEvent<HTMLButtonElement>, tabId: 'galleries' | 'portfolio' | 'studio') => {
    const tabKeys: ('galleries' | 'portfolio' | 'studio')[] = ['galleries', 'portfolio', 'studio']
    const currentIndex = tabKeys.indexOf(tabId)
    let nextIndex = currentIndex

    if (e.key === 'ArrowRight') {
      nextIndex = (currentIndex + 1) % tabKeys.length
    } else if (e.key === 'ArrowLeft') {
      nextIndex = (currentIndex - 1 + tabKeys.length) % tabKeys.length
    } else {
      return
    }

    const nextTabId = tabKeys[nextIndex]
    setActiveTab(nextTabId)
    tabRefs[nextTabId].current?.focus()
  }

  // Active descriptive info
  const activeConfig = showcaseTabs.find((t) => t.id === activeTab) || showcaseTabs[0]

  return (
    <Section id="showcase" className="border-t border-border/80">
      <div className="text-center max-w-3xl mx-auto mb-12">
        <h2 className="text-3xl sm:text-4xl font-bold tracking-tight text-foreground mb-4">
          See the Product in Action
        </h2>
        <p className="text-lg text-muted-foreground">
          Delivering files, showing off your best work, and closing client bookings has never been so seamless.
        </p>
      </div>

      {/* Tab controls */}
      <div className="flex justify-center border-b border-border mb-12">
        <div role="tablist" aria-label="ifotoset Product Showcase" className="flex gap-2 sm:gap-6">
          {showcaseTabs.map((tab) => {
            const isSelected = activeTab === tab.id
            return (
              <button
                key={tab.id}
                ref={tabRefs[tab.id as 'galleries' | 'portfolio' | 'studio']}
                role="tab"
                aria-selected={isSelected}
                aria-controls={`showcase-panel-${tab.id}`}
                id={`showcase-tab-${tab.id}`}
                tabIndex={isSelected ? 0 : -1}
                onClick={() => setActiveTab(tab.id as any)}
                onKeyDown={(e) => handleKeyDown(e, tab.id as any)}
                className={`py-4 px-4 sm:px-6 font-semibold text-sm sm:text-base border-b-2 transition-all outline-none focus-visible:ring-2 focus-visible:ring-primary rounded-t-lg ${
                  isSelected
                    ? 'border-primary text-primary font-bold'
                    : 'border-transparent text-muted-foreground hover:text-foreground'
                }`}
              >
                {tab.title}
              </button>
            )
          })}
        </div>
      </div>

      {/* Showcase Grid (Dynamic Layout) */}
      <div className="grid lg:grid-cols-12 gap-12 items-center min-h-[480px]">
        {/* Left Side: Dynamic Copy */}
        <div className="lg:col-span-5 flex flex-col justify-center text-center lg:text-left">
          <div className="transition-all duration-300">
            <span className="text-xs uppercase tracking-widest text-primary font-bold mb-2 block">
              {activeConfig.title}
            </span>
            <h3 className="text-2xl sm:text-3xl font-bold text-foreground mb-4">
              {activeConfig.tagline}
            </h3>
            <p className="text-muted-foreground leading-relaxed text-base">
              {activeConfig.description}
            </p>
          </div>
        </div>

        {/* Right Side: High-Fidelity Mockups */}
        <div className="lg:col-span-7 w-full flex justify-center">
          <div
            role="tabpanel"
            id={`showcase-panel-${activeTab}`}
            aria-labelledby={`showcase-tab-${activeTab}`}
            className="w-full relative aspect-[16/10] bg-card rounded-xl border border-border/80 shadow-xl overflow-hidden transition-all duration-500 hover:border-primary/20"
          >
            {/* Mockup Renderer with opacity/slide transitions */}
            {activeTab === 'galleries' && (
              <div className="w-full h-full p-4 flex flex-col justify-between bg-card text-foreground animate-in fade-in slide-in-from-right-4 duration-300">
                {/* Galleries Header */}
                <div className="flex items-center justify-between border-b border-border/80 pb-3">
                  <div className="flex items-center gap-2">
                    <div className="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center font-bold text-primary text-xs">SJ</div>
                    <div>
                      <h4 className="font-bold text-xs">Sarah Jenkins</h4>
                      <p className="text-[10px] text-muted-foreground">Kigali, Rwanda</p>
                    </div>
                  </div>
                  <div className="flex gap-2">
                    <span className="px-2 py-1 bg-secondary text-muted-foreground text-[10px] rounded font-semibold flex items-center gap-1">
                      <Shield size={10} /> Private
                    </span>
                  </div>
                </div>

                {/* Galleries Body Grid */}
                <div className="flex-1 py-4 grid grid-cols-3 gap-2 overflow-hidden">
                  <div className="relative rounded overflow-hidden border border-border/50">
                    <Image
                      src="https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=400&q=80"
                      alt="Gallery Photo 1"
                      fill
                      className="object-cover"
                    />
                  </div>
                  <div className="relative rounded overflow-hidden border border-border/50">
                    <Image
                      src="https://images.unsplash.com/photo-1472396961693-142e6e269027?auto=format&fit=crop&w=400&q=80"
                      alt="Gallery Photo 2"
                      fill
                      className="object-cover"
                    />
                  </div>
                  <div className="relative rounded overflow-hidden border border-border/50">
                    <Image
                      src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=400&q=80"
                      alt="Gallery Photo 3"
                      fill
                      className="object-cover"
                    />
                  </div>
                </div>

                {/* Galleries Footer Controls */}
                <div className="border-t border-border/80 pt-3 flex items-center justify-between text-xs text-muted-foreground">
                  <span>Wedding - Sarah & John</span>
                  <div className="flex gap-3">
                    <button className="flex items-center gap-1 hover:text-primary transition-colors text-[11px] font-semibold"><Heart size={12} /> Favorite</button>
                    <button className="flex items-center gap-1 hover:text-primary transition-colors text-[11px] font-semibold"><Download size={12} /> Download</button>
                    <button className="flex items-center gap-1 hover:text-primary transition-colors text-[11px] font-semibold"><Share2 size={12} /> Share</button>
                  </div>
                </div>
              </div>
            )}

            {activeTab === 'portfolio' && (
              <div className="w-full h-full p-4 flex flex-col justify-between bg-card text-foreground animate-in fade-in slide-in-from-right-4 duration-300">
                {/* Portfolio Branding */}
                <div className="flex items-center justify-between border-b border-border/80 pb-3">
                  <span className="font-bold text-xs uppercase tracking-wider">SARAH JENKINS</span>
                  <div className="flex gap-4 text-[10px] font-medium text-muted-foreground">
                    <span className="text-primary font-semibold">Galleries</span>
                    <span>About</span>
                    <span>Contact</span>
                  </div>
                </div>

                {/* Portfolio Collage */}
                <div className="flex-1 py-4 grid grid-cols-12 gap-2 overflow-hidden">
                  <div className="col-span-8 relative rounded overflow-hidden border border-border/50">
                    <Image
                      src="https://images.unsplash.com/photo-1472396961693-142e6e269027?auto=format&fit=crop&w=400&q=80"
                      alt="Landscape cover"
                      fill
                      className="object-cover"
                    />
                    <div className="absolute bottom-2 left-2 bg-black/60 backdrop-blur-sm px-2 py-0.5 rounded text-[8px] text-white">East African Landscapes</div>
                  </div>
                  <div className="col-span-4 flex flex-col gap-2">
                    <div className="flex-1 relative rounded overflow-hidden border border-border/50">
                      <Image
                        src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=400&q=80"
                        alt="Portrait collection"
                        fill
                        className="object-cover"
                      />
                    </div>
                    <div className="flex-1 relative rounded overflow-hidden border border-border/50">
                      <Image
                        src="https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=400&q=80"
                        alt="Weddings"
                        fill
                        className="object-cover"
                      />
                    </div>
                  </div>
                </div>

                {/* Portfolio Info */}
                <div className="border-t border-border/80 pt-3 flex items-center justify-between text-xs">
                  <span className="text-muted-foreground">© {new Date().getFullYear()} Sarah Jenkins. All rights reserved.</span>
                  <button className="px-3 py-1 bg-primary text-primary-foreground font-semibold rounded text-[10px] hover:bg-accent transition-colors">Book Inquiry</button>
                </div>
              </div>
            )}

            {activeTab === 'studio' && (
              <div className="w-full h-full p-4 flex flex-col bg-secondary/10 text-foreground animate-in fade-in slide-in-from-right-4 duration-300">
                {/* Studio Bar Header */}
                <div className="flex items-center justify-between border-b border-border/80 pb-3 mb-3">
                  <div className="flex items-center gap-1.5">
                    <span className="text-[10px] bg-primary text-primary-foreground font-semibold px-2 py-0.5 rounded">STUDIO</span>
                    <span className="font-bold text-xs">Sarah&apos;s Workspace</span>
                  </div>
                  <div className="flex items-center gap-2 text-[10px] text-muted-foreground font-semibold">
                    <span className="w-2 h-2 rounded-full bg-green-500"></span> Live Status
                  </div>
                </div>

                {/* Studio Metrics Row */}
                <div className="grid grid-cols-3 gap-2 mb-3">
                  <div className="bg-card border border-border/80 p-2.5 rounded-lg flex flex-col justify-between">
                    <span className="text-[9px] text-muted-foreground block font-medium">Active Galleries</span>
                    <span className="text-sm font-bold text-foreground mt-0.5">12</span>
                  </div>
                  <div className="bg-card border border-border/80 p-2.5 rounded-lg flex flex-col justify-between">
                    <span className="text-[9px] text-muted-foreground block font-medium">Monthly Views</span>
                    <span className="text-sm font-bold text-foreground mt-0.5">2,341</span>
                  </div>
                  <div className="bg-card border border-border/80 p-2.5 rounded-lg flex flex-col justify-between">
                    <span className="text-[9px] text-muted-foreground block font-medium">Total Earnings</span>
                    <span className="text-sm font-bold text-primary mt-0.5">RWF 627K</span>
                  </div>
                </div>

                {/* Mini Table List */}
                <div className="flex-1 bg-card border border-border/80 rounded-lg p-2.5 overflow-hidden flex flex-col">
                  <span className="text-[9px] font-bold text-muted-foreground mb-1.5 uppercase tracking-wider block">Recent Galleries</span>
                  <div className="flex-1 overflow-y-auto space-y-1.5 text-[10px]">
                    <div className="flex items-center justify-between py-1 border-b border-border/40">
                      <span className="font-semibold text-foreground truncate max-w-[120px]">Wedding - Sarah & John</span>
                      <span className="px-1.5 py-0.5 bg-green-100 text-green-700 text-[8px] rounded font-bold">Published</span>
                    </div>
                    <div className="flex items-center justify-between py-1 border-b border-border/40">
                      <span className="font-semibold text-foreground truncate max-w-[120px]">Corporate Tech Summit</span>
                      <span className="px-1.5 py-0.5 bg-green-100 text-green-700 text-[8px] rounded font-bold">Published</span>
                    </div>
                    <div className="flex items-center justify-between py-1 border-b border-border/40">
                      <span className="font-semibold text-foreground truncate max-w-[120px]">Portrait Session - Jan</span>
                      <span className="px-1.5 py-0.5 bg-yellow-100 text-yellow-700 text-[8px] rounded font-bold">Draft</span>
                    </div>
                  </div>
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
    </Section>
  )
}
export default ShowcaseTabs
