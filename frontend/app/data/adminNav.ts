// Admin sidebar information architecture. Grouped by workflow — originally
// Phase 3a of the dashboard revamp (see docs/global/DASHBOARD-REVAMP-PLAN.md),
// regrouped into 7 sections by Task 1 of the portal & workspace restructure
// (see .superpowers/sdd/portal-restructure-plan.md). Roles are carried as
// metadata now so Phase 0 (RBAC) can filter without re-touching this file;
// until a role is wired through `/admin/me`, `visibleAdminNav()` is permissive.

export type Role = 'founder' | 'marketer' | 'engineer'

export interface AdminNavItem {
  to: string
  label: string
  icon: string
  matchPrefix?: string
  roles?: Role[]
}

export interface NavGroup {
  label: string
  roles?: Role[]
  /** Always in the sidebar rail — no pin control (Overview). */
  mandatory?: boolean
  /** Starting pin state for customizable groups: pinned groups sit in the
   *  rail, unpinned ones live only in the "View more" launchpad. The user's
   *  own choices override this via the `axn_admin_nav_pinned` cookie
   *  (cookie-backed like the other sidebar prefs, so it's SSR-resolved with
   *  no flash on reload). Omitted = pinned. */
  defaultPinned?: boolean
  items: AdminNavItem[]
}

export const adminNav: NavGroup[] = [
  {
    label: 'Overview',
    mandatory: true,
    items: [
      { to: '/admin', label: 'Dashboard', icon: 'i-lucide-layout-dashboard' },
      { to: '/admin/mockups', label: 'Mockups', icon: 'i-lucide-monitor-smartphone', matchPrefix: '/admin/mockups' },
    ],
  },
  {
    label: 'Sales pipeline',
    items: [
      { to: '/admin/inquiries', label: 'Inquiries', icon: 'i-lucide-inbox', matchPrefix: '/admin/inquiries' },
      { to: '/admin/quotations', label: 'Quotations', icon: 'i-lucide-file-text', matchPrefix: '/admin/quotations' },
      { to: '/admin/orders', label: 'Orders', icon: 'i-lucide-package-check', matchPrefix: '/admin/orders' },
      { to: '/admin/clients', label: 'Clients', icon: 'i-lucide-users', matchPrefix: '/admin/clients' },
    ],
  },
  {
    label: 'Billing',
    items: [
      { to: '/admin/invoices', label: 'Invoices', icon: 'i-lucide-receipt-text', matchPrefix: '/admin/invoices' },
      { to: '/admin/payments', label: 'Payments', icon: 'i-lucide-wallet', matchPrefix: '/admin/payments' },
    ],
  },
  {
    label: 'Growth',
    defaultPinned: false,
    items: [
      { to: '/admin/analytics', label: 'Analytics', icon: 'i-lucide-chart-line', matchPrefix: '/admin/analytics' },
      // Marketing-spend ledger (Phase 5, record-only) — founder sees all.
      { to: '/admin/marketing', label: 'Marketing', icon: 'i-lucide-megaphone', matchPrefix: '/admin/marketing' },
    ],
  },
  {
    // External-relationship surfaces — referrers and investors both live here.
    // Referrals moved out of Growth; Investors moved out of Business/Workspace.
    label: 'Partners',
    defaultPinned: false,
    items: [
      { to: '/admin/referrals', label: 'Referrals', icon: 'i-lucide-share-2', matchPrefix: '/admin/referrals' },
      { to: '/admin/investors', label: 'Investors', icon: 'i-lucide-handshake', matchPrefix: '/admin/investors', roles: ['founder'] },
    ],
  },
  {
    label: 'Catalog',
    items: [
      { to: '/admin/services', label: 'Services', icon: 'i-lucide-briefcase-business', matchPrefix: '/admin/services' },
      { to: '/admin/projects', label: 'Projects', icon: 'i-lucide-folder-kanban', matchPrefix: '/admin/projects' },
    ],
  },
  {
    // Renamed from "Business" (Task 1 of the portal restructure) — internal
    // team/ops surfaces. Investors moved out to Partners above.
    label: 'Workspace',
    roles: ['founder'],
    defaultPinned: false,
    items: [
      // Team provisioning (Task 8) — create/edit/deactivate teammates, set the
      // monthly allowance. Founder-only.
      { to: '/admin/users', label: 'Users', icon: 'i-lucide-user-cog', matchPrefix: '/admin/users', roles: ['founder'] },
      // Payroll ledger (Phase 5 / Task 7, record-only) — founder-only; everyone
      // else reads their own payslips on /team.
      { to: '/admin/payroll', label: 'Payroll', icon: 'i-lucide-banknote', matchPrefix: '/admin/payroll', roles: ['founder'] },
      // Tasks + Announcements land in Task 5 / Task 6 — nav is scaffolded
      // ahead so those tasks only add the pages, not the nav entries.
      { to: '/admin/tasks', label: 'Tasks', icon: 'i-lucide-list-todo', matchPrefix: '/admin/tasks' },
      { to: '/admin/announcements', label: 'Announcements', icon: 'i-lucide-radio', matchPrefix: '/admin/announcements' },
      { to: '/admin/activity', label: 'Activity', icon: 'i-lucide-history', matchPrefix: '/admin/activity' },
    ],
  },
]

export function isAdminNavActive(item: AdminNavItem, currentPath: string): boolean {
  if (item.matchPrefix) return currentPath === item.matchPrefix || currentPath.startsWith(`${item.matchPrefix}/`)
  return currentPath === item.to
}

// Two-level role filter (group + item). A missing role (no RBAC yet) is
// permissive: every group/item shows, matching the current single-founder
// deployment. Once `/admin/me` returns a role, filtering becomes real with no
// further changes here. Groups left empty after filtering are dropped.
export function visibleAdminNav(role?: Role): NavGroup[] {
  const allows = (roles?: Role[]) => !roles || role == null || roles.includes(role)
  return adminNav
    .filter(group => allows(group.roles))
    .map(group => ({ ...group, items: group.items.filter(item => allows(item.roles)) }))
    .filter(group => group.items.length > 0)
}

// A group's pin state: the user's cookie override wins, then the data
// default (omitted defaultPinned = pinned). Mandatory groups can't unpin.
export function isGroupPinned(group: NavGroup, pins: Record<string, boolean>): boolean {
  if (group.mandatory) return true
  return pins[group.label] ?? group.defaultPinned !== false
}
