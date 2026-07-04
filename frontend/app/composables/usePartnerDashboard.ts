// Shared `/v1/partner/dashboard` state for the referrer pages (Dashboard,
// Referrals, Earnings — Task 9 split the old single portal page into three).
// A useState singleton like usePartnerMe, so navigating between the pages
// reuses the already-fetched payload instead of re-hitting the API; pages that
// mutate (submit-referral) call refresh() to update everyone at once.
// Referrer-only: the API 403s investor tokens, and the pages behind it are
// gated by the partner-type middleware.

export interface PartnerDashboardReferral {
  id: number
  business_name: string
  status: string
  commission_pct: number
  has_order: boolean
  earned_myr: number | null
  created_at: string | null
}

export interface PartnerDashboard {
  partner: { name: string, code: string, relationship_tier: string, commission_tiers: Record<string, number> }
  stats: { earned_myr: number, estimated_myr: number, referrals_count: number }
  ref_link: string
  referrals: PartnerDashboardReferral[]
}

// Referral status → pill styling (mirrors the referral lifecycle).
export interface ReferralPillStyle { label: string, color: string, bg: string }
const PILL_NEW: ReferralPillStyle = { label: 'New', color: 'var(--color-text-secondary)', bg: 'var(--color-bg-secondary)' }
const REFERRAL_STATUS_PILLS: Record<string, ReferralPillStyle> = {
  new: PILL_NEW,
  contacted: { label: 'Contacted', color: 'var(--color-accent)', bg: 'var(--color-accent-soft)' },
  qualified: { label: 'Qualified', color: 'var(--color-accent)', bg: 'var(--color-accent-soft)' },
  draft: { label: 'Draft', color: 'var(--color-text-secondary)', bg: 'var(--color-bg-secondary)' },
  converted: { label: 'Earning', color: 'var(--color-success)', bg: 'var(--color-success-soft, var(--color-bg-secondary))' },
  rejected: { label: 'Not proceeding', color: 'var(--color-text-tertiary)', bg: 'var(--color-bg-secondary)' },
}

export const referralPill = (status: string): ReferralPillStyle => REFERRAL_STATUS_PILLS[status] ?? PILL_NEW

export const myr = (n: number) => new Intl.NumberFormat('en-MY', { style: 'currency', currency: 'MYR' }).format(n || 0)

export function usePartnerDashboard() {
  const data = useState<PartnerDashboard | null>('partner-dashboard', () => null)
  const loadError = useState<boolean>('partner-dashboard-error', () => false)
  const { apiFetch } = usePartnerAuth()

  async function refresh() {
    try {
      data.value = await apiFetch<PartnerDashboard>('/api/v1/partner/dashboard')
      loadError.value = false
    }
    catch {
      loadError.value = true
    }
    return data.value
  }

  /** Fetch once; reuse the singleton on subsequent page visits. */
  async function ensure() {
    return data.value ?? await refresh()
  }

  return { data, loadError, refresh, ensure }
}
