// Workspace (/team) sidebar IA. Reuses the exact NavGroup / AdminNavItem / Role
// shapes and the active-matcher from Phase 3a's adminNav — the workspace is just a
// leaner surface, not a different structure. Role metadata rides along so the two-
// level filter (group + item) works the moment `/team/me` returns a role: the
// referral programme belongs to the marketer, so engineers don't see it, while
// founder/partner (who also enter /team) keep it.

import { type AdminNavItem, isAdminNavActive, type NavGroup, type Role } from '~/data/adminNav'

export type { AdminNavItem, NavGroup, Role }
export { isAdminNavActive }

// Roles that own the referral programme (matches the /v1/team/referrals gate).
const PROGRAMME_ROLES: Role[] = ['founder', 'partner', 'marketer']

export const teamNav: NavGroup[] = [
  {
    label: 'Overview',
    items: [
      { to: '/team', label: 'Dashboard', icon: 'i-lucide-layout-dashboard' },
    ],
  },
  {
    label: 'Work',
    items: [
      { to: '/team/inquiries', label: 'Inquiries', icon: 'i-lucide-inbox', matchPrefix: '/team/inquiries' },
    ],
  },
  {
    label: 'Growth',
    roles: PROGRAMME_ROLES,
    items: [
      { to: '/team/referrals', label: 'Referrals', icon: 'i-lucide-share-2', matchPrefix: '/team/referrals', roles: PROGRAMME_ROLES },
    ],
  },
  {
    label: 'Personal',
    items: [
      // Payslips lands in Phase 5 (in-system payroll). Nav scaffolded ahead so the
      // phase only fills the page. Everyone sees their own payslip.
      { to: '/team/payslips', label: 'Payslips', icon: 'i-lucide-wallet', matchPrefix: '/team/payslips' },
    ],
  },
]

// Two-level role filter (group + item), permissive until `/team/me` returns a role
// — identical semantics to visibleAdminNav so both surfaces behave the same. Groups
// left empty after filtering are dropped.
export function visibleTeamNav(role?: Role): NavGroup[] {
  const allows = (roles?: Role[]) => !roles || role == null || roles.includes(role)
  return teamNav
    .filter(group => allows(group.roles))
    .map(group => ({ ...group, items: group.items.filter(item => allows(item.roles)) }))
    .filter(group => group.items.length > 0)
}
