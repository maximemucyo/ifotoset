import Link from 'next/link'
import { Navigation } from '@/components/navigation'
import { Logo } from '@/components/logo'
import { Camera, Users, BarChart3, Download, Lock, Zap } from 'lucide-react'

export default function Home() {
  return (
    <>
      <Navigation />

      {/* Hero Section */}
      <section className="relative min-h-[calc(100vh-64px)] flex items-center justify-center bg-gradient-to-b from-background to-secondary/20 overflow-hidden">
        <div className="absolute inset-0 opacity-10">
          <div className="absolute top-20 right-10 w-72 h-72 bg-primary rounded-full mix-blend-multiply filter blur-3xl"></div>
          <div className="absolute bottom-20 left-10 w-72 h-72 bg-accent rounded-full mix-blend-multiply filter blur-3xl"></div>
        </div>

        <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 text-center">
          <h1 className="text-5xl md:text-7xl font-bold text-foreground mb-6 text-balance">
            Your Photography Platform Built for Rwanda
          </h1>
          <p className="text-xl md:text-2xl text-muted-foreground mb-8 text-balance max-w-3xl mx-auto">
            Showcase your work, deliver galleries to clients, and manage your photography business—all in one powerful platform built for Rwandan creatives.
          </p>

          <div className="flex flex-col sm:flex-row gap-4 justify-center mb-12">
            <Link href="/signup" className="px-8 py-4 bg-primary text-primary-foreground rounded-lg text-lg font-semibold hover:bg-accent transition-colors">
              Start Free Trial
            </Link>
            <Link href="/#features" className="px-8 py-4 border-2 border-primary text-primary rounded-lg text-lg font-semibold hover:bg-secondary transition-colors">
              Explore Features
            </Link>
          </div>

          <div className="text-muted-foreground text-sm">
            No credit card required • 30-day free trial
          </div>
        </div>
      </section>

      {/* Features Section */}
      <section id="features" className="py-20 bg-background">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <h2 className="text-4xl md:text-5xl font-bold text-center mb-4 text-foreground">
            Everything You Need
          </h2>
          <p className="text-xl text-center text-muted-foreground mb-16 text-balance">
            Built for Rwandan photographers who want to focus on their craft, not on complicated software.
          </p>

          <div className="grid md:grid-cols-3 gap-8">
            {[
              {
                icon: Camera,
                title: 'Portfolio Showcase',
                description: 'Create stunning galleries to showcase your best work. Beautiful, fast, and completely customizable.'
              },
              {
                icon: Users,
                title: 'Client Delivery',
                description: 'Deliver final photos to clients with password-protected galleries. Easy sharing and beautiful presentations.'
              },
              {
                icon: Download,
                title: 'Smart Downloads',
                description: 'Control downloads with watermarks, expiration dates, and usage rights. Protect your work.'
              },
              {
                icon: BarChart3,
                title: 'Analytics & Insights',
                description: 'Track gallery views, downloads, and engagement. Understand what resonates with your audience.'
              },
              {
                icon: Zap,
                title: 'Fast & Reliable',
                description: 'Lightning-fast galleries built on modern infrastructure. Your work deserves the best experience.'
              },
              {
                icon: Lock,
                title: 'Secure & Private',
                description: 'Enterprise-grade security with password protection, SSL encryption, and secure backups.'
              }
            ].map((feature, idx) => (
              <div key={idx} className="p-6 bg-card rounded-lg border border-border hover:border-primary transition-colors">
                <feature.icon className="w-12 h-12 text-primary mb-4" />
                <h3 className="text-xl font-bold text-foreground mb-2">{feature.title}</h3>
                <p className="text-muted-foreground">{feature.description}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* How It Works */}
      <section className="py-20 bg-secondary/30">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <h2 className="text-4xl font-bold text-center mb-16 text-foreground">
            How It Works
          </h2>

          <div className="grid md:grid-cols-4 gap-8">
            {[
              { number: '1', title: 'Sign Up', desc: 'Create your free account in seconds' },
              { number: '2', title: 'Upload', desc: 'Add your gallery of beautiful photos' },
              { number: '3', title: 'Share', desc: 'Get a unique link to share with clients' },
              { number: '4', title: 'Earn', desc: 'Manage packages and accept payments' }
            ].map((step, idx) => (
              <div key={idx} className="text-center">
                <div className="w-16 h-16 bg-primary text-primary-foreground rounded-full flex items-center justify-center text-2xl font-bold mx-auto mb-4">
                  {step.number}
                </div>
                <h3 className="text-xl font-bold text-foreground mb-2">{step.title}</h3>
                <p className="text-muted-foreground">{step.desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Pricing Section */}
      <section id="pricing" className="py-20 bg-background">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <h2 className="text-4xl font-bold text-center mb-4 text-foreground">
            Simple, Transparent Pricing
          </h2>
          <p className="text-xl text-center text-muted-foreground mb-16 text-balance">
            Choose the plan that fits your photography business.
          </p>

          <div className="grid md:grid-cols-3 gap-8">
            {[
              {
                name: 'Starter',
                price: 'Free',
                period: 'Forever',
                features: ['Up to 3 galleries', '1GB storage', 'Basic gallery link', 'Email support'],
                cta: 'Get Started'
              },
              {
                name: 'Professional',
                price: '4,999',
                period: 'per month',
                features: ['Unlimited galleries', '100GB storage', 'Custom domain', 'Client management', 'Analytics dashboard', 'Priority support'],
                cta: 'Start Free Trial',
                highlighted: true
              },
              {
                name: 'Studio',
                price: '12,499',
                period: 'per month',
                features: ['Everything in Pro', 'Team accounts', '500GB storage', 'Advanced analytics', 'Custom branding', '24/7 support'],
                cta: 'Start Free Trial'
              }
            ].map((plan, idx) => (
              <div
                key={idx}
                className={`rounded-lg p-8 border transition-all ${
                  plan.highlighted
                    ? 'border-primary bg-card scale-105 shadow-lg'
                    : 'border-border bg-card hover:border-primary'
                }`}
              >
                <h3 className="text-2xl font-bold text-foreground mb-2">{plan.name}</h3>
                <div className="mb-6">
                  <span className="text-4xl font-bold text-primary">
                    {plan.price === 'Free' ? 'Free' : `RWF ${plan.price}`}
                  </span>
                  {plan.price !== 'Free' && (
                    <p className="text-muted-foreground text-sm mt-1">{plan.period}</p>
                  )}
                </div>
                <ul className="space-y-3 mb-8">
                  {plan.features.map((feature, i) => (
                    <li key={i} className="flex items-center gap-2 text-foreground">
                      <span className="w-5 h-5 bg-primary text-primary-foreground rounded-full flex items-center justify-center text-sm">✓</span>
                      {feature}
                    </li>
                  ))}
                </ul>
                <Link href="/signup" className={`block w-full py-3 px-4 rounded-lg font-semibold text-center transition-colors ${
                  plan.highlighted
                    ? 'bg-primary text-primary-foreground hover:bg-accent'
                    : 'bg-secondary text-foreground hover:bg-muted'
                }`}>
                  {plan.cta}
                </Link>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* CTA Section */}
      <section className="py-20 bg-gradient-to-r from-primary to-accent text-primary-foreground">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
          <h2 className="text-4xl font-bold mb-6">
            Ready to Transform Your Photography Business?
          </h2>
          <p className="text-xl mb-8 text-primary-foreground/90">
            Join hundreds of Rwandan photographers already using ifotoset to showcase their work and grow their business.
          </p>
          <Link href="/signup" className="inline-block px-8 py-4 bg-background text-primary rounded-lg font-semibold hover:bg-secondary transition-colors">
            Start Your Free Trial
          </Link>
        </div>
      </section>

      {/* Footer */}
      <footer className="bg-card border-t border-border py-12">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid grid-cols-2 md:grid-cols-4 gap-8 mb-8">
            <div className="col-span-2 md:col-span-1">
              <Logo size="md" href="/" />
              <p className="text-muted-foreground text-sm mt-4">Photography platform built for Rwanda.</p>
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
    </>
  )
}
