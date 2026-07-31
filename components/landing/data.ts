import { Camera, Layers, Users, Shield, Zap, Calendar, CreditCard, BarChart3, Mail, Heart, Download, Share2 } from 'lucide-react'

export interface NavLink {
  label: string
  href: string
}

export interface SuiteItem {
  id: string
  title: string
  description: string
  learnMoreHref: string
  previewImage: string
}

export interface ShowcaseTab {
  id: string
  title: string
  tagline: string
  description: string
  mockupType: 'gallery' | 'portfolio' | 'studio'
}

export interface FeatureItem {
  title: string
  description: string
}

export interface PricingPlan {
  name: string
  price: string
  period: string
  description: string
  features: string[]
  highlighted?: boolean
  ctaText: string
  ctaHref: string
}

export interface FaqItem {
  question: string
  answer: string
}

export const navLinks: NavLink[] = [
  { label: 'Features', href: '#features' },
  { label: 'Showcase', href: '#showcase' },
  { label: 'Pricing', href: '#pricing' },
  { label: 'FAQ', href: '#faq' },
]

export const suiteItems: SuiteItem[] = [
  {
    id: 'galleries',
    title: 'Client Galleries',
    description: 'Deliver beautiful, private galleries to clients with fast uploads, secure access, downloads, and sharing.',
    learnMoreHref: '#showcase',
    previewImage: 'https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=400&q=80',
  },
  {
    id: 'portfolio',
    title: 'Portfolio Showcase',
    description: 'Turn your best work into a professional online portfolio designed to attract your next client.',
    learnMoreHref: '#showcase',
    previewImage: 'https://images.unsplash.com/photo-1472396961693-142e6e269027?auto=format&fit=crop&w=400&q=80',
  },
  {
    id: 'studio',
    title: 'Studio Manager',
    description: 'Manage bookings, clients, packages, pricing, payments, and your business from one workspace.',
    learnMoreHref: '#showcase',
    previewImage: 'https://images.unsplash.com/photo-1513694203232-719a280e022f?auto=format&fit=crop&w=400&q=80',
  },
]

export const showcaseTabs: ShowcaseTab[] = [
  {
    id: 'galleries',
    title: 'Client Galleries',
    tagline: 'Beautiful galleries your clients will love.',
    description: 'Deliver their photos through a fast, branded experience designed around your work. Give clients the options to download, share, and favorite their photos seamlessly.',
    mockupType: 'gallery',
  },
  {
    id: 'portfolio',
    title: 'Portfolio Showcase',
    tagline: 'Turn your photography into your best marketing tool.',
    description: 'Create a clean, stunning public profile that displays your editorial collections. Let prospects explore your portfolios and book inquiries directly from your website.',
    mockupType: 'portfolio',
  },
  {
    id: 'studio',
    title: 'Studio Manager',
    tagline: 'Spend less time managing and more time creating.',
    description: 'An all-in-one studio management workspace. Track your booking calendar, package details, client lists, and monthly earnings directly from an interactive panel.',
    mockupType: 'studio',
  },
]

export const featureItems: FeatureItem[] = [
  {
    title: 'Beautiful Client Galleries',
    description: 'Provide a premium delivery experience with gorgeous, fluid image grids customizable to match your photography style.',
  },
  {
    title: 'Professional Portfolio',
    description: 'Showcase your editorial collections on a clean, modern online portfolio optimized to attract your next client.',
  },
  {
    title: 'Client Management',
    description: 'Keep client details, gallery access logs, download history, and communication history organized in one database.',
  },
  {
    title: 'Bookings & Scheduling',
    description: 'Allow clients to view your availability, select packages, and schedule portrait or wedding shoots online.',
  },
  {
    title: 'Packages & Pricing',
    description: 'Build flexible service packages with standard pricing tiers to easily communicate options and close deals.',
  },
  {
    title: 'Secure Payments',
    description: 'Enable seamless regional and international transactions to accept deposits and package fees securely.',
  },
  {
    title: 'Analytics Dashboard',
    description: 'Gain insights into gallery views, download logs, popular photos, and client engagement to grow your business.',
  },
]

export const pricingPlans: PricingPlan[] = [
  {
    name: 'Starter',
    price: 'RWF 4,999',
    period: '/month',
    description: 'Perfect for modern photographers starting to establish their online presence.',
    features: [
      'Portfolio Website',
      'Up to 3 Galleries',
      '50 GB Optimized Storage',
      'Email Support',
    ],
    ctaText: 'Get Started',
    ctaHref: '/signup',
  },
  {
    name: 'Professional',
    price: 'RWF 12,499',
    period: '/month',
    description: 'For active photographers and studios looking to scale their client management.',
    features: [
      'Everything in Starter',
      'Unlimited Galleries',
      'Booking Manager',
      'Secure Payments integration',
      'Custom Domain Support',
      'Priority Email/Chat Support',
    ],
    highlighted: true,
    ctaText: 'Start Free Trial',
    ctaHref: '/signup',
  },
]

export const faqItems: FaqItem[] = [
  {
    question: 'What is ifotoset?',
    answer: 'ifotoset is a complete, all-in-one photography platform built for modern photographers and studios in East Africa and beyond. It combines elegant client gallery delivery, portfolio showcase creation, and studio management tools (bookings, payments, and client management) into a single, unified experience.',
  },
  {
    question: 'Who is ifotoset for?',
    answer: 'It is built for modern photographers, videographers, independent creatives, and studios—ranging from wedding and event photographers to portrait and commercial designers—who want a professional client delivery and studio booking system.',
  },
  {
    question: 'How do client galleries work?',
    answer: 'You create a gallery inside your studio dashboard, upload your photos, and set privacy preferences. Your client receives an elegant password-protected gallery link where they can browse photos, download individual items or full sets, and share them directly.',
  },
  {
    question: 'Can I password-protect galleries?',
    answer: 'Yes. Every client gallery can be protected with custom passwords to ensure only authorized viewers have access to your high-resolution photos.',
  },
  {
    question: 'Can clients download their photos?',
    answer: 'Yes. You have full control over download privileges. You can allow clients to download high-resolution or web-sized versions, apply watermarks, or disable downloads altogether based on your agreement.',
  },
  {
    question: 'How much storage do I get?',
    answer: 'The Starter plan includes 50 GB of optimized storage, while the Professional plan supports scale-ready storage limits suited to handle high-volume RAW and high-resolution JPEG delivery.',
  },
  {
    question: 'Can I customize my portfolio?',
    answer: 'Yes. The portfolio builder lets you showcase curated galleries, add your brand logo, adjust theme styling, and set custom domain mapping on the Professional plan.',
  },
  {
    question: 'Can I manage bookings and clients?',
    answer: 'Yes. Our integrated Studio Manager handles booking calendars, customizable packages, client contact profiles, and invoices in a central panel.',
  },
  {
    question: 'Can I cancel anytime?',
    answer: 'Yes. ifotoset runs on a monthly subscription with no long-term contracts. You can cancel, upgrade, or downgrade your plan at any point in your billing settings.',
  },
]
