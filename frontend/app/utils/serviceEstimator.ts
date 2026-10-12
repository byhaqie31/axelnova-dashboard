// The /services estimator — driven entirely by data, never a formula of its own.
// The services and their package cards come from GET /api/v1/services; a package
// wired to the quote builder is priced through utils/pricing.ts (the TS port of
// PricingEngine.php) with the controls that package actually accepts: the legacy
// JSON modifiers that apply to it, plus its category's scope fields. A new
// service, package or modifier shows up here with no code change.
import {
  appliesTo,
  calculateEstimate,
  modifierAmount,
  packageCategory,
  type EtaUnit,
  type ModifierValue,
  type PricingConfig,
} from './pricing'

/** A package card as GET /api/v1/services sends it. */
export interface CatalogPackage {
  id: number
  slug: string
  name: string
  price_min_myr: string | number
  price_max_myr: string | number | null
  unit: string
  duration_text: string
  featured: boolean
  quote_key: { category: string, package: string } | null
}

export interface CatalogService {
  slug: string
  name: string
  icon: string
  is_default: boolean
  packages: CatalogPackage[]
}

interface ControlBase {
  /** Where the value goes: a legacy modifier, or a scope field (wins on a shared key, like the engine). */
  source: 'modifier' | 'scope'
  key: string
  label: string
}

export type EstimatorControl =
  | (ControlBase & {
      kind: 'number'
      min: number
      max: number
      default: number
      /** Units included in the package price. */
      included: number
      unit: string
      /** Price per unit above `included`. */
      amount: number
    })
  | (ControlBase & { kind: 'toggle', default: boolean, amount: number })
  | (ControlBase & { kind: 'select', default: string, options: { value: string, label: string, amount: number }[] })

export type EstimatorResult =
  /** A quote-builder package, priced by the engine. */
  | { kind: 'range', min: number, max: number, etaValue: number, etaUnit: EtaUnit, unit: string }
  /** An open-ended card (no max price): "From RM 30,000, custom quote". */
  | { kind: 'from', min: number, unit: string, duration: string }
  /** A priced card that is not in the quote builder: its card price as listed. */
  | { kind: 'card', min: number, max: number, unit: string, duration: string }
  | { kind: 'custom' }

const headline = (key: string) => key.split('_').filter(Boolean).map(w => w[0]!.toUpperCase() + w.slice(1)).join(' ')

const KIND_ORDER = { number: 0, select: 1, toggle: 2 } as const

/** The package a service opens on: its featured card, else the first. */
export function defaultPackage(service: CatalogService | null | undefined): CatalogPackage | null {
  if (!service) return null
  return service.packages.find(p => p.featured) ?? service.packages[0] ?? null
}

/** The package's quote-builder key, when the engine can price it (wired, max price, Live). */
export function quotableKey(cfg: PricingConfig | null | undefined, pkg: CatalogPackage): string | null {
  const key = pkg.quote_key?.package
  if (!cfg || !key || pkg.price_max_myr === null || !cfg.base_packages[key]) return null
  return key
}

/** Every input this package takes, from data: its category's scope fields, then the legacy modifiers. */
export function estimatorControls(cfg: PricingConfig, packageKey: string): EstimatorControl[] {
  const controls: EstimatorControl[] = []
  const slug = packageCategory(cfg, packageKey)

  for (const field of (slug && cfg.scope_fields?.[slug]) || []) {
    if (!appliesTo(field.applies_to, packageKey)) continue
    const c = field.config
    const base = { source: 'scope' as const, key: field.field_key, label: field.label }
    if (field.type === 'slider') {
      controls.push({
        ...base,
        kind: 'number',
        min: c.min ?? 1,
        max: c.max ?? Math.max(c.min ?? 1, 10),
        default: Number(c.default ?? c.min ?? 1),
        included: c.free_threshold ?? 0,
        unit: c.unit ?? field.field_key.replace(/_/g, ' '),
        amount: c.price_per_unit ?? 0,
      })
    }
    else if (field.type === 'toggle') {
      controls.push({ ...base, kind: 'toggle', default: c.default === true, amount: c.amount ?? 0 })
    }
    else {
      const options = (c.options ?? []).map(o => ({ value: String(o.value), label: o.label ?? String(o.value), amount: o.amount ?? 0 }))
      controls.push({ ...base, kind: 'select', default: String(c.default ?? options[0]?.value ?? ''), options })
    }
  }

  const taken = new Set(controls.map(c => c.key))
  for (const [key, def] of Object.entries(cfg.modifiers ?? {})) {
    if (taken.has(key) || !appliesTo(def.applies_to ?? 'all', packageKey)) continue
    const base = { source: 'modifier' as const, key, label: def.label ?? headline(key) }
    const amount = modifierAmount(def, packageKey)
    if (def.applies_after !== undefined && def.applies_after !== null) {
      const included = Number(def.applies_after)
      controls.push({
        ...base,
        kind: 'number',
        min: 1,
        max: included + 15,
        default: included,
        included,
        unit: def.unit ?? key.replace(/^extra_/, '').replace(/_/g, ' '),
        amount,
      })
    }
    else {
      controls.push({ ...base, kind: 'toggle', default: false, amount })
    }
  }

  return controls
    .map((c, i) => ({ c, i }))
    .sort((a, b) => KIND_ORDER[a.c.kind] - KIND_ORDER[b.c.kind] || a.i - b.i)
    .map(({ c }) => c)
}

/** Each control at its default — what the package price already includes. */
export function defaultValues(controls: EstimatorControl[]): Record<string, ModifierValue> {
  return Object.fromEntries(controls.map(c => [c.key, c.default]))
}

export function estimatePackage(
  cfg: PricingConfig | null | undefined,
  pkg: CatalogPackage | null | undefined,
  values: Record<string, ModifierValue> = {},
  rush = false,
): EstimatorResult {
  if (!pkg) return { kind: 'custom' }

  const key = quotableKey(cfg, pkg)
  if (cfg && key) {
    const modifiers: Record<string, ModifierValue> = {}
    const scopeValues: Record<string, ModifierValue> = {}
    for (const control of estimatorControls(cfg, key)) {
      const value = values[control.key] ?? control.default
      if (control.source === 'scope') scopeValues[control.key] = value
      else modifiers[control.key] = value
    }
    const result = calculateEstimate(cfg, { packageKey: key, modifiers, scopeValues, rush })
    if (result) {
      return { kind: 'range', min: result.minMyr, max: result.maxMyr, etaValue: result.etaValue, etaUnit: result.etaUnit, unit: pkg.unit }
    }
  }

  const min = Number(pkg.price_min_myr)
  if (pkg.price_max_myr === null) return { kind: 'from', min, unit: pkg.unit, duration: pkg.duration_text }
  return { kind: 'card', min, max: Number(pkg.price_max_myr), unit: pkg.unit, duration: pkg.duration_text }
}
