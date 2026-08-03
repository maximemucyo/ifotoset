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
  subtitle: string
  priceMonthly: string
  priceYearly: string
  priceYearlyBilled: string
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
    name: 'Free',
    subtitle: 'Learn the platform',
    priceMonthly: 'RWF 0',
    priceYearly: 'RWF 0',
    priceYearlyBilled: 'RWF 0',
    description: 'Perfect for new photographers starting to establish their presence.',
    features: [
      'Portfolio Website',
      'Unlimited Galleries',
      '2 GB Optimized Storage',
      'Photo galleries only',
      'Email Support',
    ],
    ctaText: 'Get Started Free',
    ctaHref: '/signup',
  },
  {
    name: 'Basic',
    subtitle: 'Start delivering professionally',
    priceMonthly: 'RWF 10,999',
    priceYearly: 'RWF 8,999',
    priceYearlyBilled: 'RWF 107,988',
    description: 'For growing photographers who need more storage and video tools.',
    features: [
      'Portfolio Website',
      'Unlimited Galleries',
      '50 GB Optimized Storage',
      'Up to 30 minutes of hosted video',
      'Email Support',
    ],
    ctaText: 'Choose Basic',
    ctaHref: '/signup',
  },
  {
    name: 'Professional',
    subtitle: 'Run your photography business',
    priceMonthly: 'RWF 29,999',
    priceYearly: 'RWF 24,999',
    priceYearlyBilled: 'RWF 299,988',
    description: 'For established photographers running a full-time business.',
    features: [
      'Everything in Basic',
      '1 TB Optimized Storage',
      'Up to 5 hours of hosted video',
      'Booking Manager',
      'Secure Payments integration',
      'Custom Domain Support',
      'Priority Support',
    ],
    highlighted: true,
    ctaText: 'Choose Professional',
    ctaHref: '/signup',
  },
  {
    name: 'Business',
    subtitle: 'Scale your studio',
    priceMonthly: 'RWF 59,999',
    priceYearly: 'RWF 49,999',
    priceYearlyBilled: 'RWF 599,988',
    description: 'For agencies, studios, and teams scaling their operations.',
    features: [
      'Everything in Professional',
      '3 TB Optimized Storage',
      'Up to 15 hours of hosted video',
      'Multi-user team accounts',
      'Dedicated setup & onboarding',
      'Priority Support',
    ],
    ctaText: 'Choose Business',
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
    answer: 'Yes. You have control over download privileges. You can allow clients to download high-resolution or web-sized versions, apply watermarks, or disable downloads altogether based on your agreement.',
  },
  {
    question: 'How much storage do I get?',
    answer: 'The Free plan includes 2 GB of storage. Basic includes 50 GB, Professional includes 1 TB, and Business provides 3 TB of optimized storage to handle high-volume RAW and high-resolution JPEG delivery.',
  },
  {
    question: 'How does video hosting work?',
    answer: 'Video hosting is optimized for premium client delivery (such as highlight films, ceremony recordings, or commercial clips). Basic includes up to 30 minutes, Professional includes up to 5 hours, and Business includes up to 15 hours of total hosted video. All uploads are transcoded and optimized to ensure ultra-fast client playback.',
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
    answer: 'Yes. ifotoset runs on a monthly or yearly subscription with no long-term contracts. You can cancel, upgrade, or downgrade your plan at any point in your billing settings.',
  },
]
