/**
 * Team availability status options — single source of truth for the Profile
 * page's pill picker (§12.6) AND the read-only indicator surfaced in the team
 * layout header/dropdown. Two values only, per the Task 4 brief (no
 * "away"/"offline" spectrum) — mirrors `backend`'s `users.availability` enum.
 */
export interface AvailabilityOption {
  value: 'available' | 'busy'
  label: string
  color: string
  bg: string
}

export const availabilityOptions: AvailabilityOption[] = [
  { value: 'available', label: 'Available', color: 'var(--color-success)', bg: 'var(--color-success-soft)' },
  { value: 'busy', label: 'Busy', color: 'var(--color-warning)', bg: 'var(--color-warning-soft)' },
]

export function availabilityMeta(value?: string | null): AvailabilityOption | undefined {
  return availabilityOptions.find(o => o.value === value)
}
