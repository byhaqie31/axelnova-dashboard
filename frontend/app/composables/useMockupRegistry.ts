// Public mockup registry on axelnova.my — single source of truth for the
// admin dashboard "Featured Mockups" section and the public landing showcase.
// The registry is CORS-open (`access-control-allow-origin: *`), so it is
// fetched straight from the browser; a frozen snapshot backstops the section
// when the live fetch fails.

export interface RegistryMockup {
  id?: string
  name: string
  client: string
  type: string
  status: string
  slug: string
  updatedAt?: string
  summary?: string
  archetype?: string
  tint?: { h: number, c: number }
  internal?: boolean
}

export const MOCKUP_LISTING_URL = 'https://axelnova.my/projects/'
const REGISTRY_URL = 'https://axelnova.my/projects/registry.json'
const MAX_ITEMS = 6

// Frozen snapshot of the top 6 public mockups — used only when the live
// registry is unreachable (offline, DNS, CORS regression).
const FALLBACK: RegistryMockup[] = [
  { name: 'Setia Air-Cond & Electrical', client: 'Setia Air-Cond and Electrical', type: 'HVAC & electrical site', status: 'draft', slug: 'setiaaircond' },
  { name: 'Baaqeeelah', client: 'Baaqeeelah', type: 'Bridal assistant booking', status: 'draft', slug: 'baaqeeelah' },
  { name: "MU'MIN by Al-Meswak", client: "Al-Meswak Mu'min", type: 'Halal personal care', status: 'draft', slug: 'muminalmeswak' },
  { name: 'One Malaysia Taxi', client: 'One Malaysia Taxi', type: 'Private chauffeur', status: 'draft', slug: 'onemalaysiataxi' },
  { name: 'Hz Academy', client: 'Hz Academy', type: 'Tuition academy', status: 'draft', slug: 'hzacademy' },
  { name: 'missmacaron.co', client: 'missmacaron.co', type: 'Macaron doorgift vendor', status: 'draft', slug: 'missmacaron' },
]

export function mockupUrl(m: RegistryMockup) {
  return `https://axelnova.my/${encodeURIComponent(m.slug)}/`
}

// tint {h, c} → hsl(h, c*400%, 55%), kept subtle: real registry data lands
// at ~24–36% saturation. `alpha` < 1 gives the soft wash variant.
export function mockupAccent(m: RegistryMockup, alpha = 1) {
  if (!m.tint) return alpha < 1 ? 'var(--color-accent-soft)' : 'var(--color-accent)'
  const h = Math.round(Number(m.tint.h) || 0)
  const s = Math.min(100, Math.max(0, Math.round((Number(m.tint.c) || 0) * 400)))
  return alpha < 1 ? `hsl(${h} ${s}% 55% / ${alpha})` : `hsl(${h} ${s}% 55%)`
}

// internal === true rows are admin-only and must never render publicly.
function pickPublicTop(items: RegistryMockup[], limit: number): RegistryMockup[] {
  return items
    .filter(m => m.internal !== true && m.slug && m.name)
    .sort((a, b) => (b.updatedAt ?? '').localeCompare(a.updatedAt ?? ''))
    .slice(0, limit)
}

// `limit` defaults to the featured six; pass Infinity for the full listing.
export function useMockupRegistry(limit: number = MAX_ITEMS) {
  const mockups = ref<RegistryMockup[]>([])
  const loading = ref(true)

  async function load() {
    loading.value = true
    try {
      const items = await $fetch<RegistryMockup[]>(REGISTRY_URL, { timeout: 10_000 })
      mockups.value = pickPublicTop(Array.isArray(items) ? items : [], limit)
    }
    catch {
      mockups.value = FALLBACK
    }
    finally {
      loading.value = false
    }
  }

  return { mockups, loading, load }
}
