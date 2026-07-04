// Announcement domain metadata (Task 6) — single source of truth for the
// shape and the `audience` option map shared by the admin cockpit
// (/admin/announcements) and the team workspace feed (Team Home). Mirrors
// backend's AnnouncementResource and the announcements table's enum; follows
// the {value,label,color,bg} option-map pattern established by
// data/tasks.ts (taskPriorityOptions).

/** Matches backend App\Http\Resources\AnnouncementResource. */
export interface AnnouncementRecord {
  id: number
  title: string
  body: string
  audience: 'team' | 'partners' | 'all'
  published_at: string | null
  created_by: number
  created_by_name: string | null
  created_at: string
  updated_at: string
}

export interface AudienceOption {
  value: AnnouncementRecord['audience']
  label: string
  color: string
  bg: string
}

// Human-readable labels — 'all' reads as "Everyone" (not "All", which reads
// as a filter's "no filter" option elsewhere in the admin UI). Tints ride
// existing semantic tokens: team is the default/brand tint, partners is the
// forward-hook audience (not yet consumed anywhere) so it gets the "not
// fully wired up yet" warning tint, everyone is the broadest/success tint.
export const announcementAudienceOptions: AudienceOption[] = [
  { value: 'team', label: 'Team', color: 'var(--color-accent)', bg: 'var(--color-accent-soft)' },
  { value: 'partners', label: 'Partners', color: 'var(--color-warning)', bg: 'var(--color-warning-soft)' },
  { value: 'all', label: 'Everyone', color: 'var(--color-success)', bg: 'var(--color-success-soft)' },
]

export function announcementAudienceMeta(value?: string | null): AudienceOption | undefined {
  return announcementAudienceOptions.find(o => o.value === value)
}
