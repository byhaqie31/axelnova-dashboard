// The /services estimator is data-driven: every live service and package card
// is offered, each quote-builder package is priced by the shared engine with the
// controls it accepts, and open-ended cards read "From …".
import { describe, expect, it } from 'vitest'
import type { PricingConfig } from './pricing'
import {
  defaultPackage,
  defaultValues,
  estimatePackage,
  estimatorControls,
  type CatalogPackage,
  type CatalogService,
} from './serviceEstimator'

// The live catalog after the 2026-10 rewrite: [service, [key | null, slug, min, max | null, eta, unit, card unit, featured]].
type Row = [string | null, string, number, number | null, number, 'day' | 'week' | 'month', string, boolean?]
const CATALOG: Record<string, Row[]> = {
  'digital-marketing': [
    ['single_asset', 'creative-single-asset', 250, 450, 3, 'day', 'per design'],
    ['campaign_pack', 'creative-campaign-pack', 800, 1800, 7, 'day', 'per campaign', true],
    ['event_activation', 'creative-event-activation', 1800, 3500, 10, 'day', 'per event'],
    ['brand_refresh', 'creative-brand-refresh', 3000, 6000, 3, 'week', 'per project'],
    ['content_retainer', 'creative-content-retainer', 2500, 4000, 1, 'month', 'per month'],
  ],
  'web-presence': [
    ['web_starter', 'web-starter', 2500, 4000, 2, 'week', 'per project'],
    ['web_business', 'web-business', 5000, 8000, 4, 'week', 'per project', true],
    ['web_premium', 'web-premium', 10000, 16000, 6, 'week', 'per project'],
  ],
  'ui-ux-frontend': [
    ['ux_audit', 'ux-audit', 1500, 3500, 2, 'week', 'per audit'],
    ['design_build', 'design-build-sprint', 6000, 12000, 5, 'week', 'per project', true],
    ['design_system', 'design-system', 12000, 20000, 8, 'week', 'per project'],
  ],
  'booking-portal': [
    ['booking_lite', 'booking-lite', 5000, 8000, 5, 'week', 'per project'],
    ['booking_pro', 'booking-pro', 10000, 18000, 10, 'week', 'per project', true],
    [null, 'booking-custom', 20000, null, 14, 'week', 'custom quote'],
  ],
  'ecommerce': [
    ['store_starter', 'storefront-starter', 7000, 12000, 6, 'week', 'per project'],
    ['store_pro', 'storefront-pro', 14000, 24000, 10, 'week', 'per project', true],
    [null, 'commerce-custom', 25000, null, 16, 'week', 'custom quote'],
  ],
  'admin-portal': [
    ['admin_starter', 'admin-starter', 6000, 10000, 5, 'week', 'per project'],
    ['admin_business', 'admin-business', 12000, 22000, 10, 'week', 'per project', true],
    [null, 'enterprise-portal', 30000, null, 16, 'week', 'custom quote'],
  ],
}

const PAYMENTS_ON = [
  'web_starter', 'web_business', 'web_premium', 'booking_lite', 'booking_pro',
  'store_starter', 'store_pro', 'admin_starter', 'admin_business', 'design_build',
]

function build(catalog: Record<string, Row[]>, scopeFields: PricingConfig['scope_fields'] = {}) {
  const services: CatalogService[] = []
  const cfg: PricingConfig = {
    version: '2026.10.12',
    base_packages: {},
    categories: [],
    modifiers: {
      extra_page: { amount: 300, applies_after: 5, applies_to: ['web_essential', 'web_business', 'web_premium'], amount_by_package: { web_business: 500, web_premium: 800 }, label: 'Number of pages', unit: 'page' },
      cms: { amount: 1200, applies_to: ['web_business', 'web_premium'], label: 'CMS' },
      booking_flow: { amount: 1500, applies_to: ['web_business', 'web_premium'], label: 'Booking flow' },
      extra_language: { amount: 600, applies_to: 'all', label: 'Extra language' },
      stripe_integration: { amount: 1800, applies_to: PAYMENTS_ON, label: 'Stripe card payments' },
      fpx_integration: { amount: 2200, applies_to: PAYMENTS_ON, label: 'FPX online banking' },
      extra_module: { amount: 800, applies_after: 5, applies_to: ['dash_starter'] },
    },
    addons: {},
    scope_fields: scopeFields,
    rush_multiplier: 1.2,
    rush_units: ['week', 'month'],
    currency: 'MYR',
    valid_for_days: 30,
  }

  let id = 1
  for (const [slug, rows] of Object.entries(catalog)) {
    const packages: CatalogPackage[] = rows.map(([key, pslug, min, max, eta, etaUnit, unit, featured]) => {
      if (key && max !== null) cfg.base_packages[key] = { min, max, eta_value: eta, eta_unit: etaUnit, name: pslug }
      return {
        id: id++,
        slug: pslug,
        name: pslug,
        price_min_myr: min.toFixed(2),
        price_max_myr: max === null ? null : max.toFixed(2),
        unit,
        duration_text: max === null ? 'Scoped on call' : `${eta} ${etaUnit}s`,
        featured: !!featured,
        quote_key: key ? { category: slug, package: key } : null,
      }
    })
    cfg.categories.push({ key: slug, label: slug, icon: 'i-lucide-box', packages: rows.filter(r => r[0] && r[3] !== null).map(r => ({ key: r[0]!, name: r[1], tagline: '' })) })
    services.push({ slug, name: slug, icon: 'i-lucide-box', is_default: slug === 'web-presence', packages })
  }
  return { cfg, services }
}

const { cfg, services } = build(CATALOG)
const pkg = (slug: string) => services.flatMap(s => s.packages).find(p => p.slug === slug)!
const keys = (packageKey: string) => estimatorControls(cfg, packageKey).map(c => c.key)

describe('controls come from the package’s own modifiers', () => {
  it('a Business Site takes pages, CMS, booking flow, a language and both payments', () => {
    const controls = estimatorControls(cfg, 'web_business')
    expect(controls.map(c => c.key)).toEqual(['extra_page', 'cms', 'booking_flow', 'extra_language', 'stripe_integration', 'fpx_integration'])
    expect(controls[0]).toMatchObject({ kind: 'number', label: 'Number of pages', included: 5, default: 5, unit: 'page', amount: 500 })
    expect(estimatorControls(cfg, 'web_premium')[0]).toMatchObject({ key: 'extra_page', amount: 800 })
  })

  it('creative, audit and design-system packages take no payment modifiers', () => {
    expect(keys('event_activation')).toEqual(['extra_language'])
    expect(keys('single_asset')).toEqual(['extra_language'])
    expect(keys('design_system')).toEqual(['extra_language'])
    expect(keys('booking_lite')).toEqual(['extra_language', 'stripe_integration', 'fpx_integration'])
  })

  it('falls back to a readable label when the data has none', () => {
    const { cfg: bare } = build({ 'web-presence': CATALOG['web-presence']! })
    delete bare.modifiers.cms!.label
    expect(estimatorControls(bare, 'web_business').find(c => c.key === 'cms')?.label).toBe('Cms')
  })
})

describe('estimatePackage — every service, from the catalog', () => {
  const range = (min: number, max: number, etaValue: number, etaUnit = 'week') =>
    expect.objectContaining({ kind: 'range', min, max, etaValue, etaUnit })

  it('prices each service’s default (featured) package at its card range', () => {
    const expected: Record<string, unknown> = {
      'digital-marketing': range(800, 1800, 7, 'day'),
      'web-presence': range(5000, 8000, 4),
      'ui-ux-frontend': range(6000, 12000, 5),
      'booking-portal': range(10000, 18000, 10),
      'ecommerce': range(14000, 24000, 10),
      'admin-portal': range(12000, 22000, 10),
    }
    for (const service of services) {
      expect(estimatePackage(cfg, defaultPackage(service)), service.slug).toEqual(expected[service.slug])
    }
  })

  it('applies rush: price × 1.2, shorter ETA only for week and month packages', () => {
    expect(estimatePackage(cfg, pkg('web-business'), {}, true)).toEqual(range(6000, 9600, 2))
    expect(estimatePackage(cfg, pkg('booking-pro'), {}, true)).toEqual(range(12000, 21600, 7))
    expect(estimatePackage(cfg, pkg('creative-content-retainer'), {}, true)).toEqual(range(3000, 4800, 1, 'month'))
    // Day packages keep their ETA; the price multiplier still applies.
    expect(estimatePackage(cfg, pkg('creative-campaign-pack'), {}, true)).toEqual(range(950, 2150, 7, 'day'))
  })

  it('adds modifiers at their per-package price', () => {
    expect(estimatePackage(cfg, pkg('web-business'), { extra_page: 8 })).toEqual(range(6500, 9500, 4))
    expect(estimatePackage(cfg, pkg('web-premium'), { extra_page: 7 })).toEqual(range(11600, 17600, 6))
    expect(estimatePackage(cfg, pkg('booking-lite'), { stripe_integration: true })).toEqual(range(6800, 9800, 5))
    expect(estimatePackage(cfg, pkg('storefront-starter'), { stripe_integration: true, fpx_integration: true })).toEqual(range(11000, 16000, 6))
    // Not accepted by the package → ignored, never charged.
    expect(estimatePackage(cfg, pkg('creative-campaign-pack'), { stripe_integration: true })).toEqual(range(800, 1800, 7, 'day'))
  })

  it('rushes the extras too, then rounds to the nearest RM 50', () => {
    // (5000 + 2×500 + 1800) × 1.2 = 9360 → 9350; (8000 + 1000 + 1800) × 1.2 = 12960 → 12950
    expect(estimatePackage(cfg, pkg('web-business'), { extra_page: 7, stripe_integration: true }, true)).toEqual(range(9350, 12950, 2))
  })

  it('shows open-ended cards as "From", with the card’s unit and timeline', () => {
    expect(estimatePackage(cfg, pkg('enterprise-portal'), {}, true)).toEqual({ kind: 'from', min: 30000, unit: 'custom quote', duration: 'Scoped on call' })
    expect(estimatePackage(cfg, pkg('booking-custom'))).toMatchObject({ kind: 'from', min: 20000 })
  })

  it('is a custom quote when nothing is picked', () => {
    expect(estimatePackage(cfg, null)).toEqual({ kind: 'custom' })
  })
})

describe('dynamic', () => {
  it('a new service and package are offered and priced with no code change', () => {
    const { cfg: next, services: nextServices } = build({
      ...CATALOG,
      'ai-automation': [['ai_starter', 'ai-starter', 4000, 7000, 3, 'week', 'per project', true]],
    })
    const ai = nextServices.find(s => s.slug === 'ai-automation')!
    expect(defaultPackage(ai)?.slug).toBe('ai-starter')
    expect(estimatorControls(next, 'ai_starter').map(c => c.key)).toEqual(['extra_language'])
    expect(estimatePackage(next, defaultPackage(ai), { extra_language: true })).toEqual(
      expect.objectContaining({ kind: 'range', min: 4600, max: 7600, etaValue: 3 }),
    )
  })

  it('a scope field on the category becomes a control and wins over a legacy key', () => {
    const { cfg: scoped } = build({ 'web-presence': CATALOG['web-presence']! }, {
      'web-presence': [{ field_key: 'extra_language', label: 'Languages', type: 'slider', applies_to: 'all', config: { min: 1, max: 4, default: 1, unit: 'language', free_threshold: 1, price_per_unit: 700 } }],
    })
    const controls = estimatorControls(scoped, 'web_starter')
    expect(controls[0]).toMatchObject({ source: 'scope', key: 'extra_language', kind: 'number', amount: 700 })
    expect(controls.filter(c => c.key === 'extra_language')).toHaveLength(1)
    expect(estimatePackage(scoped, pkg('web-starter'), { extra_language: 3 })).toMatchObject({ min: 3900, max: 5400 })
    expect(defaultValues(controls)).toMatchObject({ extra_language: 1, stripe_integration: false })
  })
})
