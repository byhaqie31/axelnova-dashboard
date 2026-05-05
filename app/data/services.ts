export interface ServiceTier {
  id: string
  name: string
  priceMin: number
  priceMax: number
  unit: string
  description: string
  features: string[]
  featured: boolean
  cta: string
}

export const services: ServiceTier[] = [
  {
    id: 'starter',
    name: 'Starter',
    priceMin: 1500,
    priceMax: 2500,
    unit: 'landing page or small business site',
    description: 'Perfect for businesses that need a clean, fast, mobile-first presence.',
    features: [
      'Responsive layout (mobile-first)',
      'WhatsApp / contact form integration',
      'Basic SEO setup',
      '1 revision round',
      '5-day delivery',
    ],
    featured: false,
    cta: 'Get a quote',
  },
  {
    id: 'professional',
    name: 'Professional',
    priceMin: 3500,
    priceMax: 6000,
    unit: 'custom UI/UX + frontend build',
    description: 'For businesses that need a custom-designed, API-connected interface.',
    features: [
      'Figma → Vue/Nuxt implementation',
      'REST API integration',
      'Admin / dashboard pages',
      'Performance optimisation',
      '3 revision rounds',
      '2–3 week delivery',
    ],
    featured: true,
    cta: 'Most popular',
  },
  {
    id: 'premium',
    name: 'Premium',
    priceMin: 8000,
    priceMax: 15000,
    unit: 'full product interface',
    description: 'End-to-end product UI for SaaS, portals, and complex dashboard systems.',
    features: [
      'Full UX flow design (Figma)',
      'Multi-module dashboard / portal',
      'Payment / onboarding flow',
      'Technical documentation',
      'Handover + walkthrough session',
      'Unlimited revisions',
    ],
    featured: false,
    cta: 'Let’s talk scope',
  },
]
