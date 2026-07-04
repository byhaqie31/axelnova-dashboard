// Workspace (/team) sidebar IA. Reframed by Task 4 of the portal & workspace
// restructure: the team no longer touches admin-owned operational data
// (inquiries, the referral programme, marketing spend all dropped), so the
// nav shrank to five personal destinations, flat, no role gating. Still reuses
// the exact NavGroup / AdminNavItem / Role shapes and the active-matcher from
// adminNav so the two sidebars share one implementation.
//
// Tasks (/team/tasks) and Calendar (/team/calendar) land in Task 5 — the nav
// points at them ahead of the pages existing (same "scaffold ahead" precedent
// as adminNav's Tasks/Announcements entries).

import { type AdminNavItem, isAdminNavActive, type NavGroup, type Role } from '~/data/adminNav'

export type { AdminNavItem, NavGroup, Role }
export { isAdminNavActive }

export const teamNav: NavGroup[] = [
  {
    label: 'Workspace',
    items: [
      { to: '/team', label: 'Home', icon: 'i-lucide-house' },
      { to: '/team/tasks', label: 'Tasks', icon: 'i-lucide-list-todo', matchPrefix: '/team/tasks' },
      { to: '/team/calendar', label: 'Calendar', icon: 'i-lucide-calendar', matchPrefix: '/team/calendar' },
      { to: '/team/payslips', label: 'Payslips', icon: 'i-lucide-wallet', matchPrefix: '/team/payslips' },
      { to: '/team/profile', label: 'Profile', icon: 'i-lucide-user', matchPrefix: '/team/profile' },
    ],
  },
]

// No role gating left in the workspace nav — every internal role sees the
// same five destinations. Kept as a named export (rather than inlining
// `teamNav` at call sites) so a future role-scoped item can reintroduce
// filtering here without touching team.vue.
export function visibleTeamNav(_role?: Role): NavGroup[] {
  return teamNav
}
