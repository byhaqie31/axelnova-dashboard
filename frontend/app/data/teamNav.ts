// Workspace (/team) sidebar IA. Reframed by Task 4 of the portal & workspace
// restructure: the team no longer touches admin-owned operational data
// (inquiries, the referral programme, marketing spend all dropped), so the
// nav shrank to five personal destinations, flat, no role gating. Still reuses
// the exact NavGroup / AdminNavItem / Role shapes and the active-matcher from
// adminNav so the two sidebars share one implementation.
//
// Tasks (/team/tasks) and Calendar (/team/calendar) landed in Task 5 — both
// pages exist alongside this nav entry.

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
  {
    // Marketer surface — Threads analytics (coming soon) + the read-only site
    // traffic mirror. Founder passes too (previews via the admin→team jump);
    // engineers never see the group (and the backend 403s them anyway).
    label: 'Marketing',
    items: [
      { to: '/team/marketing', label: 'Marketing', icon: 'i-lucide-megaphone', matchPrefix: '/team/marketing', roles: ['founder', 'marketer'] },
      { to: '/team/analytics', label: 'Analytics', icon: 'i-lucide-chart-line', matchPrefix: '/team/analytics', roles: ['founder', 'marketer'] },
    ],
    roles: ['founder', 'marketer'],
  },
]

// Role-scoped filtering, same semantics as visibleAdminNav: items/groups
// without `roles` show to everyone; before `me` resolves (role undefined)
// everything renders so the sidebar doesn't pop in late.
export function visibleTeamNav(role?: Role): NavGroup[] {
  const allows = (roles?: Role[]) => !roles || role == null || roles.includes(role)
  return teamNav
    .filter(group => allows(group.roles))
    .map(group => ({ ...group, items: group.items.filter(item => allows(item.roles)) }))
    .filter(group => group.items.length > 0)
}
