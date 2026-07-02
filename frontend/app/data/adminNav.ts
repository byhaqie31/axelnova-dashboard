// Admin sidebar information architecture. Grouped by workflow (Phase 3a of the
// dashboard revamp — see docs/global/DASHBOARD-REVAMP-PLAN.md). Roles are carried
// as metadata now so Phase 0 (RBAC) can filter without re-touching this file;
// until a role is wired through `/admin/me`, `visibleAdminNav()` is permissive.

export type Role = 'founder' | 'partner' | 'marketer' | 'engineer'

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
  items: AdminNavItem[]
}

export const adminNav: NavGroup[] = [
  {
    label: 'Overview',
    items: [
      { to: '/admin', label: 'Dashboard', icon: 'i-lucide-layout-dashboard' },
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
    items: [
      { to: '/admin/referrals', label: 'Referrals', icon: 'i-lucide-share-2', matchPrefix: '/admin/referrals' },
      { to: '/admin/referral-partners', label: 'Partners', icon: 'i-lucide-user-check', matchPrefix: '/admin/referral-partners' },
      { to: '/admin/analytics', label: 'Analytics', icon: 'i-lucide-chart-line', matchPrefix: '/admin/analytics' },
      // Marketing-spend ledger (Phase 5, record-only) — founder + partner see all.
      { to: '/admin/marketing', label: 'Marketing', icon: 'i-lucide-megaphone', matchPrefix: '/admin/marketing' },
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
    label: 'Business',
    roles: ['founder', 'partner'],
    items: [
      // Users + Activity land in Phase 0 / Phase 1 — nav is scaffolded ahead so
      // those phases only add the page, not the nav entry.
      { to: '/admin/users', label: 'Users', icon: 'i-lucide-user-cog', matchPrefix: '/admin/users', roles: ['founder'] },
      // Payroll ledger (Phase 5, record-only) — founder-only; partners read
      // their own payslips on /team like everyone else.
      { to: '/admin/payroll', label: 'Payroll', icon: 'i-lucide-banknote', matchPrefix: '/admin/payroll', roles: ['founder'] },
      { to: '/admin/activity', label: 'Activity', icon: 'i-lucide-history', matchPrefix: '/admin/activity' },
      { to: '/admin/investors', label: 'Investors', icon: 'i-lucide-handshake', matchPrefix: '/admin/investors' },
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
