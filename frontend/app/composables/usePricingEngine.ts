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

export interface PricingConfig {
  version: string
  base_packages: Record<string, { min: number; max: number; eta_value: number; eta_unit: EtaUnit; name?: string }>
  categories: QuoteCategory[]
  modifiers: Record<string, {
    amount: number
    applies_after?: number
    applies_to: string[] | 'all'
  }>
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

export function formatEta(value: number, unit: EtaUnit): string {
  return `${value} ${value === 1 ? unit : `${unit}s`}`
}

// Shared across every composable instance on the client (loadConfig only runs in
// onMounted, so this never leaks across SSR requests). Dedupes concurrent loads —
// the builder and its scope child both call loadConfig() on mount, and before this
// the second call fired a second request because config.value wasn't set yet.
let configRequest: Promise<void> | null = null

export function usePricingEngine() {
  const config = useState<PricingConfig | null>('pricingConfig', () => null)
  const configLoading = ref(false)
  const configError = ref('')

  // Drop the session-cached config so the next loadConfig() refetches. Call after
  // a catalog edit (package/add-on/category) so the quote builder reflects it
  // without a hard refresh.
  function invalidateConfig() {
    config.value = null
  }

  async function loadConfig() {
    if (config.value) return
    // Join an in-flight request instead of starting a second one.
    if (configRequest) return configRequest
    configLoading.value = true
    configError.value = ''
    configRequest = (async () => {
      try {
        const runtimeConfig = useRuntimeConfig()
        const res = await $fetch<{ data: PricingConfig }>(
          `${runtimeConfig.public.apiBase}/api/v1/quote-builder/config`
        )
        config.value = res.data
      }
      catch {
        configError.value = 'Could not load pricing config. Please refresh.'
      }
      finally {
        configLoading.value = false
        configRequest = null
      }
    })()
    return configRequest
  }

  function calculate(
    packageKey: string,
    scopeValues: Record<string, number | boolean | string>,
    addonKeys: string[],
    rush: boolean,
  ): EstimateResult | null {
    if (!config.value) return null

    const cfg = config.value
    const base = cfg.base_packages[packageKey]
    if (!base) return null

    let min = base.min
    let max = base.max
    let etaValue = base.eta_value
    const etaUnit = base.eta_unit
    // Label with the catalog name (DB), never the slug — mirrors PricingEngine.php.
    const breakdown: [string, number, number][] = [[`Base: ${base.name ?? packageKey}`, min, max]]

    // Data-driven scope fields — MUST stay byte-for-byte in sync with
    // PricingEngine::calculate() (the repo's two-engines invariant).
    const categorySlug = cfg.categories.find(c => c.packages.some(p => p.key === packageKey))?.key
    const fields = (categorySlug && cfg.scope_fields?.[categorySlug]) || []
    for (const field of fields) {
      const appliesTo = field.applies_to
      if (appliesTo !== 'all' && Array.isArray(appliesTo) && !appliesTo.includes(packageKey)) continue

      const fc = field.config
      const value = scopeValues[field.field_key] ?? fc.default
      let extra = 0
      let label = ''

      if (field.type === 'slider') {
        const over = Math.max(0, Math.trunc(Number(value) || 0) - (fc.free_threshold ?? 0))
        extra = over * (fc.price_per_unit ?? 0)
        const unit = fc.unit ?? field.field_key.replace(/_/g, ' ')
        label = `+${over} ${unit}`
      }
      else if (field.type === 'toggle') {
        if (value === true || value === 1 || value === '1' || value === 'true') {
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

    for (const key of addonKeys) {
      const addon = cfg.addons[key]
      if (!addon) continue
      min += addon.amount
      max += addon.amount
      breakdown.push([`Addon: ${addon.label}`, addon.amount, addon.amount])
    }

    if (rush) {
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

  // Rounded "k" shorthand — ONLY for min–max range estimates (e.g. "RM 7k – RM 10k").
  function fmtMyr(amount: number): string {
    if (amount >= 1000) return `RM ${(amount / 1000).toFixed(0)}k`
    return `RM ${amount.toLocaleString()}`
  }

  // Precise whole-RM — for any single exact value (add-on price, fixed fee). Never
  // rounds (RM 1,500 stays RM 1,500, not "RM 2k").
  function fmtMyrExact(amount: number | string): string {
    return `RM ${Math.round(Number(amount) || 0).toLocaleString('en-US')}`
  }

  return { config, configLoading, configError, loadConfig, invalidateConfig, calculate, fmtMyr, fmtMyrExact, formatEta }
}
