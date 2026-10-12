// Parity with PricingEngine.php (tests/Feature/Pricing/PricingEngineTest.php):
// the same inputs give the same numbers and breakdown labels.
import { describe, expect, it } from 'vitest'
import { calculateEstimate, type PricingConfig } from './pricing'

function config(overrides: Partial<PricingConfig> = {}): PricingConfig {
  return {
    version: 'test',
    base_packages: { test_landing: { min: 1500, max: 2500, eta_value: 4, eta_unit: 'week', name: 'Landing' } },
    categories: [{ key: 'web', label: 'Web', icon: '', packages: [{ key: 'test_landing', name: 'Landing', tagline: '' }] }],
    modifiers: {},
    addons: {},
    scope_fields: {},
    rush_multiplier: 1.2,
    rush_units: ['week', 'month'],
    currency: 'MYR',
    valid_for_days: 30,
    ...overrides,
  }
}

describe('calculateEstimate', () => {
  it('prices the base package and names it', () => {
    expect(calculateEstimate(config(), { packageKey: 'test_landing' })).toEqual({
      minMyr: 1500, maxMyr: 2500, etaValue: 4, etaUnit: 'week', breakdown: [['Base: Landing', 1500, 2500]],
    })
    expect(calculateEstimate(config(), { packageKey: 'nope' })).toBeNull()
  })

  it('charges a threshold modifier only above the free allowance', () => {
    const cfg = config({ modifiers: { test_pages: { amount: 150, applies_after: 5, applies_to: 'all' } } })
    const r = calculateEstimate(cfg, { packageKey: 'test_landing', modifiers: { test_pages: 8 } })!
    expect([r.minMyr, r.maxMyr]).toEqual([1950, 2950])
    expect(r.breakdown[1]).toEqual(['+3 test pages', 450, 450])
  })

  it('uses the per-package amount override', () => {
    const cfg = config({ modifiers: { test_pages: { amount: 300, applies_after: 5, applies_to: 'all', amount_by_package: { test_landing: 800 } } } })
    expect(calculateEstimate(cfg, { packageKey: 'test_landing', modifiers: { test_pages: 7 } })!.minMyr).toBe(1500 + 1600)
  })

  it('adds a toggle modifier and skips one scoped to another package', () => {
    const cfg = config({ modifiers: {
      test_cms: { amount: 800, applies_to: 'all' },
      test_other: { amount: 500, applies_to: ['some_other_package'] },
    } })
    const r = calculateEstimate(cfg, { packageKey: 'test_landing', modifiers: { test_cms: true, test_other: true } })!
    expect([r.minMyr, r.maxMyr]).toEqual([2300, 3300])
    expect(r.breakdown[1]).toEqual(['+test cms', 800, 800])
  })

  it('prices scope fields and add-ons', () => {
    const cfg = config({
      scope_fields: { web: [{ field_key: 'pages', label: 'Pages', type: 'slider', applies_to: 'all', config: { free_threshold: 5, price_per_unit: 300, default: 5, unit: 'page' } }] },
      addons: { seo: { amount: 600, label: 'SEO setup' } },
    })
    const r = calculateEstimate(cfg, { packageKey: 'test_landing', scopeValues: { pages: 7 }, addonKeys: ['seo'] })!
    expect([r.minMyr, r.maxMyr]).toEqual([1500 + 600 + 600, 2500 + 600 + 600])
  })

  it('rush multiplies the price and shortens week ETAs, then rounds to RM 50', () => {
    const r = calculateEstimate(config(), { packageKey: 'test_landing', rush: true })!
    expect([r.minMyr, r.maxMyr, r.etaValue]).toEqual([1800, 3000, 2])
    expect(r.breakdown.at(-1)).toEqual(['Rush delivery (×1.2)', 0, 0])

    const day = config({ base_packages: { test_landing: { min: 1520, max: 2480, eta_value: 3, eta_unit: 'day' } } })
    const d = calculateEstimate(day, { packageKey: 'test_landing', rush: true })!
    expect([d.minMyr, d.maxMyr, d.etaValue]).toEqual([1800, 3000, 3])
  })
})
