// Shared `/v1/partner/me` state — a Nuxt useState singleton (mirrors useTeamMe)
// so the partner layout (type-filtered nav + signed-in name) and every page
// under it read the same reactive object, and the type-gate middleware can
// reuse an already-fetched `me` instead of re-hitting the API per navigation.
import type { PartnerType } from '~/data/partnersNav'

export interface PartnerReferrerProfile {
  id: number
  name: string
  code: string
  relationship_tier: string
  commission_pct: number
}

export interface PartnerInvestorProfile {
  id: number
  name: string
  company: string | null
}

export interface PartnerMe {
  id: number
  type: PartnerType
  email: string
  profile: PartnerReferrerProfile | PartnerInvestorProfile | null
}

export function usePartnerMe() {
  const me = useState<PartnerMe | null>('partner-me', () => null)
  const { apiFetch } = usePartnerAuth()

  async function refresh() {
    try {
      me.value = await apiFetch<PartnerMe>('/api/v1/partner/me')
    }
    catch {
      // Non-fatal — middleware bounces to /partners/login on hard auth failures.
    }
    return me.value
  }

  /** Fetch once; reuse the singleton on subsequent calls. */
  async function ensure() {
    return me.value ?? await refresh()
  }

  return { me, refresh, ensure }
}
