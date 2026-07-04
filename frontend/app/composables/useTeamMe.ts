// Shared `/v1/team/me` state — a Nuxt useState singleton so the team layout
// (avatar dot + role/availability pills) and every page under it (Home,
// Profile) read and write the same reactive object. Without this, saving a
// new availability status on /team/profile wouldn't be reflected in the
// layout header until a full reload, since each component would otherwise
// hold its own local `me` ref.
export interface TeamMe {
  id: number
  name: string
  email: string
  role?: string
  tier?: string
  availability?: 'available' | 'busy'
}

export function useTeamMe() {
  const me = useState<TeamMe | null>('team-me', () => null)
  const { apiFetch } = useTeamAuth()

  async function refresh() {
    try {
      me.value = await apiFetch<TeamMe>('/api/v1/team/me')
    }
    catch {
      // Non-fatal — middleware bounces to /team/login on hard auth failures.
    }
    return me.value
  }

  return { me, refresh }
}
