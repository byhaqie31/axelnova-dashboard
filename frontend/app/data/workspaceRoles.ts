// Workspace role metadata — single source of truth for how a role renders on
// /admin/users (Task 8). Mirrors backend's User::WORKSPACE_ROLES. Founder gets
// a visually distinct (warning/gold) tone since it's the one role that can't
// be created or edited from this screen — everything else reads as a plain
// neutral/accent chip. Follows the {value,label,color,bg} map pattern
// established by data/availabilityStatuses.ts.
export interface RoleOption {
  value: 'marketer' | 'engineer'
  label: string
  color: string
  bg: string
}

/** Selectable on the create/edit forms — founder is deliberately excluded (not provisionable from the UI). */
export const workspaceRoleOptions: RoleOption[] = [
  { value: 'marketer', label: 'Marketer', color: 'var(--color-accent)', bg: 'var(--color-accent-soft)' },
  { value: 'engineer', label: 'Engineer', color: 'var(--color-text-secondary)', bg: 'var(--color-bg-secondary)' },
]

export const founderRoleMeta = { label: 'Founder', color: 'var(--color-warning)', bg: 'var(--color-warning-soft)' }

export function roleMeta(role: string): { label: string, color: string, bg: string } {
  if (role === 'founder') return founderRoleMeta
  const match = workspaceRoleOptions.find(o => o.value === role)
  return match ?? { label: role.charAt(0).toUpperCase() + role.slice(1), color: 'var(--color-text-secondary)', bg: 'var(--color-bg-secondary)' }
}
