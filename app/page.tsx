import Link from 'next/link'
import { Logo } from '@/components/logo'
import { Navbar } from '@/components/landing/navbar'
import { Hero } from '@/components/landing/hero'
import { ProductSuite } from '@/components/landing/product-suite'
import { ShowcaseTabs } from '@/components/landing/showcase-tabs'
import { FeatureGrid } from '@/components/landing/feature-grid'
import { BrandStatement } from '@/components/landing/brand-statement'
import { Pricing } from '@/components/landing/pricing'
import { FAQ } from '@/components/landing/faq'
import { CTA } from '@/components/landing/cta'

export default function Home() {
  return (
    <div className="flex flex-col min-h-screen bg-background">
      <Navbar />

      <main className="flex-grow">
        <Hero />
        <ProductSuite />
        <ShowcaseTabs />
        <FeatureGrid />
        <BrandStatement />
        <Pricing />
        <FAQ />
        <CTA />
      </main>

      {/* Footer */}
      <footer className="bg-card border-t border-border py-12">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid grid-cols-2 md:grid-cols-4 gap-8 mb-8">
            <div className="col-span-2 md:col-span-1">
              <Logo size="md" href="/" />
              <p className="text-muted-foreground text-sm mt-4">Photography platform built for modern photographers.</p>
            </div>
            <div>
              <h4 className="font-semibold text-foreground mb-4">Product</h4>
              <ul className="space-y-2 text-muted-foreground text-sm">
                <li><Link href="/#features" className="hover:text-primary">Features</Link></li>
                <li><Link href="/#pricing" className="hover:text-primary">Pricing</Link></li>
                <li><Link href="#" className="hover:text-primary">Security</Link></li>
              </ul>
            </div>
            <div>
              <h4 className="font-semibold text-foreground mb-4">Company</h4>
              <ul className="space-y-2 text-muted-foreground text-sm">
                <li><Link href="#" className="hover:text-primary">About</Link></li>
                <li><Link href="#" className="hover:text-primary">Blog</Link></li>
                <li><Link href="#" className="hover:text-primary">Contact</Link></li>
              </ul>
            </div>
            <div>
              <h4 className="font-semibold text-foreground mb-4">Legal</h4>
              <ul className="space-y-2 text-muted-foreground text-sm">
                <li><Link href="#" className="hover:text-primary">Privacy</Link></li>
                <li><Link href="#" className="hover:text-primary">Terms</Link></li>
              </ul>
            </div>
          </div>
          <div className="border-t border-border pt-8 text-center text-muted-foreground text-sm">
            <p suppressHydrationWarning>&copy; {new Date().getFullYear()} ifotoset. All rights reserved.</p>
          </div>
        </div>
      </footer>
    </div>
  )
}
