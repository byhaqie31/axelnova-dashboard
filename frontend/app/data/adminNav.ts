export interface AdminNavItem {
  to: string
  label: string
  icon: string
  matchPrefix?: string
}

export const adminNav: AdminNavItem[] = [
  { to: '/admin', label: 'Dashboard', icon: 'i-lucide-layout-dashboard' },
  { to: '/admin/quotations', label: 'Quotations', icon: 'i-lucide-file-text', matchPrefix: '/admin/quotations' },
  { to: '/admin/orders', label: 'Orders', icon: 'i-lucide-package-check', matchPrefix: '/admin/orders' },
  { to: '/admin/services', label: 'Services', icon: 'i-lucide-briefcase-business', matchPrefix: '/admin/services' },
  { to: '/admin/projects', label: 'Projects', icon: 'i-lucide-folder-kanban', matchPrefix: '/admin/projects' },
  { to: '/admin/analytics', label: 'Analytics', icon: 'i-lucide-chart-line', matchPrefix: '/admin/analytics' },
]

export function isAdminNavActive(item: AdminNavItem, currentPath: string): boolean {
  if (item.matchPrefix) return currentPath === item.matchPrefix || currentPath.startsWith(`${item.matchPrefix}/`)
  return currentPath === item.to
}
