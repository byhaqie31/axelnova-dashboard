// The admin quote builder's pricing state (cached quote-builder config) over
// the pure engine in utils/pricing.ts — the TS port of PricingEngine.php, shared
// with the public /services estimator. Types are re-exported for existing imports.
import { calculateEstimate, formatEta, type EstimateResult, type PricingConfig } from '~/utils/pricing'

export type {
  EstimateResult,
  EtaUnit,
  LegacyModifier,
  PricingConfig,
  QuoteCategory,
  ScopeField,
  ScopeFieldConfig,
  ScopeFieldType,
} from '~/utils/pricing'
export { formatEta } from '~/utils/pricing'

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

  // Scope-field pricing for the builder; legacy JSON modifiers ride along when
  // a caller has them (the builder doesn't). Same engine as the PHP side.
  function calculate(
    packageKey: string,
    scopeValues: Record<string, number | boolean | string>,
    addonKeys: string[],
    rush: boolean,
    modifiers: Record<string, number | boolean | string> = {},
  ): EstimateResult | null {
    if (!config.value) return null
    return calculateEstimate(config.value, { packageKey, scopeValues, addonKeys, rush, modifiers })
  }

  return { config, configLoading, configError, loadConfig, invalidateConfig, calculate, formatEta }
}
