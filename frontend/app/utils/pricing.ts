// TS port of App\Services\Quoting\PricingEngine::calculate() — pure, so the
// admin builder (usePricingEngine) and the public /services estimator price
// through ONE function. MUST stay in step with the PHP engine (the repo's
// two-engines invariant): base → legacy JSON modifiers → scope fields → add-ons
// → rush → round to RM 50, with the same breakdown labels.

export type EtaUnit = 'hour' | 'day' | 'week' | 'month'

export interface QuoteCategory {
  key: string
  label: string
  icon: string
  packages: { key: string; name: string; tagline: string }[]
}

export type ScopeFieldType = 'slider' | 'toggle' | 'select'

export interface ScopeFieldConfig {
  // slider
  min?: number; max?: number; default?: number | boolean | string; unit?: string
  free_threshold?: number; price_per_unit?: number
  // toggle
  amount?: number
  // select
  options?: { value: string; label: string; amount: number }[]
}

export interface ScopeField {
  field_key: string
  label: string
  type: ScopeFieldType
  applies_to: string[] | 'all'
  config: ScopeFieldConfig
}

/** A legacy `pricing_configs.config.modifiers` entry. */
export interface LegacyModifier {
  amount: number
  /** Numeric modifier: charged per unit above this count. Absent = a toggle. */
  applies_after?: number
  applies_to: string[] | 'all'
  /** Per-package amount override (PricingEngine::modifierAmount). */
  amount_by_package?: Record<string, number>
  /** Display metadata — never affects the price. */
  label?: string
  unit?: string
}

export interface PricingConfig {
  version: string
  base_packages: Record<string, { min: number; max: number; eta_value: number; eta_unit: EtaUnit; name?: string }>
  categories: QuoteCategory[]
  modifiers: Record<string, LegacyModifier>
  addons: Record<string, { amount: number; label: string }>
  scope_fields: Record<string, ScopeField[]>   // category slug → fields
  rush_multiplier: number
  rush_units: EtaUnit[]
  currency: string
  valid_for_days: number
}

export interface EstimateResult {
  minMyr: number
  maxMyr: number
  etaValue: number
  etaUnit: EtaUnit
  breakdown: [string, number, number][]
}

export type ModifierValue = number | boolean | string

export interface EstimateInput {
  packageKey: string
  /** Legacy JSON modifier values (connector / estimator). */
  modifiers?: Record<string, ModifierValue>
  /** Scope-field values (admin builder). */
  scopeValues?: Record<string, ModifierValue>
  addonKeys?: string[]
  rush?: boolean
}

export function formatEta(value: number, unit: EtaUnit): string {
  return `${value} ${value === 1 ? unit : `${unit}s`}`
}

export function appliesTo(target: string[] | 'all' | undefined, packageKey: string): boolean {
  return target === undefined || target === 'all' || (Array.isArray(target) && target.includes(packageKey))
}

/** PricingEngine::modifierAmount — the per-package override, else the flat amount. */
export function modifierAmount(def: LegacyModifier, packageKey: string): number {
  return Number(def.amount_by_package?.[packageKey] ?? def.amount ?? 0)
}

const isOn = (v: unknown) => v === true || v === 1 || v === '1' || v === 'true'

/** The category slug a package key belongs to (scope fields are per category). */
export function packageCategory(cfg: Pick<PricingConfig, 'categories'>, packageKey: string): string | undefined {
  return cfg.categories.find(c => c.packages.some(p => p.key === packageKey))?.key
}

export function calculateEstimate(cfg: PricingConfig, input: EstimateInput): EstimateResult | null {
  const { packageKey } = input
  const base = cfg.base_packages[packageKey]
  if (!base) return null

  let min = Number(base.min)
  let max = Number(base.max)
  let etaValue = Number(base.eta_value)
  const etaUnit = base.eta_unit
  // Label with the catalog name (DB), never the slug — mirrors PricingEngine.php.
  const breakdown: [string, number, number][] = [[`Base: ${base.name ?? packageKey}`, min, max]]

  // Legacy JSON modifiers.
  for (const [key, value] of Object.entries(input.modifiers ?? {})) {
    const def = cfg.modifiers?.[key]
    if (!def || !appliesTo(def.applies_to ?? 'all', packageKey)) continue
    const amount = modifierAmount(def, packageKey)

    if (def.applies_after !== undefined && def.applies_after !== null) {
      const count = Math.trunc(Number(value) || 0)
      const threshold = Math.trunc(Number(def.applies_after))
      if (count > threshold) {
        const extra = (count - threshold) * amount
        min += extra
        max += extra
        breakdown.push([`+${count - threshold} ${key.replace(/_/g, ' ')}`, extra, extra])
      }
    }
    else if (isOn(value)) {
      min += amount
      max += amount
      breakdown.push([`+${key.replace(/_/g, ' ')}`, amount, amount])
    }
  }

  // Data-driven scope fields for the package's category, gated by applies_to.
  const categorySlug = packageCategory(cfg, packageKey)
  const fields = (categorySlug && cfg.scope_fields?.[categorySlug]) || []
  for (const field of fields) {
    if (!appliesTo(field.applies_to, packageKey)) continue

    const fc = field.config
    const value = input.scopeValues?.[field.field_key] ?? fc.default
    let extra = 0
    let label = ''

    if (field.type === 'slider') {
      const over = Math.max(0, Math.trunc(Number(value) || 0) - (fc.free_threshold ?? 0))
      extra = over * (fc.price_per_unit ?? 0)
      const unit = fc.unit ?? field.field_key.replace(/_/g, ' ')
      label = `+${over} ${unit}`
    }
    else if (field.type === 'toggle') {
      if (isOn(value)) {
        extra = fc.amount ?? 0
        label = `+${field.label}`
      }
    }
    else if (field.type === 'select') {
      const opt = (fc.options ?? []).find(o => String(o.value) === String(value))
      if (opt) {
        extra = opt.amount ?? 0
        label = `${field.label}: ${opt.label ?? opt.value}`
      }
    }

    if (extra > 0) {
      min += extra
      max += extra
      breakdown.push([label, extra, extra])
    }
  }

  for (const key of input.addonKeys ?? []) {
    const addon = cfg.addons?.[key]
    if (!addon) continue
    min += addon.amount
    max += addon.amount
    breakdown.push([`Addon: ${addon.label}`, addon.amount, addon.amount])
  }

  if (input.rush) {
    min *= cfg.rush_multiplier
    max *= cfg.rush_multiplier
    // Rush time-reduction only meaningful for week/month projects.
    if (cfg.rush_units.includes(etaUnit)) {
      etaValue = Math.max(1, Math.floor(etaValue * 0.7))
    }
    breakdown.push([`Rush delivery (×${cfg.rush_multiplier})`, 0, 0])
  }

  min = Math.round(min / 50) * 50
  max = Math.round(max / 50) * 50

  return { minMyr: min, maxMyr: max, etaValue, etaUnit, breakdown }
}
