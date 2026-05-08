export type EtaUnit = 'hour' | 'day' | 'week' | 'month'

export interface QuoteCategory {
  key: string
  label: string
  icon: string
  packages: { key: string; name: string; tagline: string }[]
}

export interface PricingConfig {
  version: string
  base_packages: Record<string, { min: number; max: number; eta_value: number; eta_unit: EtaUnit }>
  categories: QuoteCategory[]
  modifiers: Record<string, {
    amount: number
    applies_after?: number
    applies_to: string[] | 'all'
  }>
  addons: Record<string, { amount: number; label: string }>
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

export function usePricingEngine() {
  const config = useState<PricingConfig | null>('pricingConfig', () => null)
  const configLoading = ref(false)
  const configError = ref('')

  async function loadConfig() {
    if (config.value) return
    configLoading.value = true
    configError.value = ''
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
    }
  }

  function calculate(
    packageKey: string,
    modifiers: Record<string, boolean | number>,
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
    const breakdown: [string, number, number][] = [[`Base: ${packageKey}`, min, max]]

    for (const [key, value] of Object.entries(modifiers)) {
      const def = cfg.modifiers[key]
      if (!def) continue

      const appliesTo = def.applies_to
      if (appliesTo !== 'all' && Array.isArray(appliesTo) && !appliesTo.includes(packageKey)) continue

      if (typeof def.applies_after === 'number') {
        const count = typeof value === 'number' ? value : 0
        if (count > def.applies_after) {
          const extra = (count - def.applies_after) * def.amount
          min += extra
          max += extra
          breakdown.push([`+${count - def.applies_after} ${key.replace('_', ' ')}`, extra, extra])
        }
      }
      else if (value === true || value === 1) {
        min += def.amount
        max += def.amount
        breakdown.push([`+${key.replace(/_/g, ' ')}`, def.amount, def.amount])
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

  function fmtMyr(amount: number): string {
    if (amount >= 1000) return `RM ${(amount / 1000).toFixed(0)}k`
    return `RM ${amount.toLocaleString()}`
  }

  return { config, configLoading, configError, loadConfig, calculate, fmtMyr, formatEta }
}
