import type { Role } from '~/data/adminNav'

// Shared `/v1/admin/me` state — a useState singleton (same pattern as
// useTeamMe / usePartnerMe) so the admin layout and any page needing the
// signed-in identity share one fetch per hard load instead of each firing
// their own `/admin/me` on mount.
export interface AdminMe {
  id: number
  name: string
  email: string
  role?: Role
}

// Module-scoped so concurrent callers (layout + page mounting together)
// coalesce into a single request.
let inflight: Promise<AdminMe | null> | null = null

export function useAdminMe() {
  const me = useState<AdminMe | null>('admin-me', () => null)
  const { apiFetch } = useAdminAuth()

  /** Cached read — fetches once per hard load, then serves the singleton. */
  async function load(): Promise<AdminMe | null> {
    if (me.value) return me.value
    inflight ??= apiFetch<AdminMe>('/api/v1/admin/me')
      .then((res) => {
        me.value = res
        return res
      })
      .catch(() => null) // non-fatal — middleware bounces on hard auth failures
      .finally(() => { inflight = null })
    return inflight
  }

  /** Force a refetch (e.g. after profile-affecting mutations). */
  async function refresh(): Promise<AdminMe | null> {
    me.value = null
    return load()
  }

  return { me, load, refresh }
}
