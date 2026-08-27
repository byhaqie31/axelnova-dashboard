// Shared team-roster lookup (`/v1/admin/users`) — a useState singleton so the
// tasks pages' assignee pickers stop refetching the same unpaginated roster on
// every navigation. The Users management page keeps its own fresh fetch and
// calls invalidate() after mutations so pickers never serve a stale roster.
export interface RosterUser {
  id: number
  name: string
  role: string
  deactivated_at: string | null
}

// Module-scoped so concurrent callers coalesce into one request.
let inflight: Promise<RosterUser[]> | null = null

export function useAdminRoster() {
  const roster = useState<RosterUser[] | null>('admin-roster', () => null)
  const { apiFetch } = useAdminAuth()

  /** Cached read — fetches once per hard load, then serves the singleton. */
  async function load(): Promise<RosterUser[]> {
    if (roster.value) return roster.value
    inflight ??= apiFetch<RosterUser[]>('/api/v1/admin/users')
      .then((res) => {
        roster.value = res
        return res
      })
      .finally(() => { inflight = null })
    return inflight
  }

  /** Drop the cache — call after any user mutation (create/update/de/reactivate). */
  function invalidate(): void {
    roster.value = null
  }

  return { roster, load, invalidate }
}
