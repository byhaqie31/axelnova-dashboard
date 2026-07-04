// Partner-portal (/partners) nav IA — Task 9 made the portal type-aware
// (referrer + investor share one login on external_accounts). Each item carries
// a `type` flag: 'shared' shows for everyone, 'referrer'/'investor' only for
// that account type. `visiblePartnersNav(type)` filters; before /me resolves
// (type undefined) only the shared items show, so the shell renders sanely
// pre-fetch. Reuses the AdminNavItem shape + active-matcher from adminNav so
// the three surfaces share one nav implementation.

import { type AdminNavItem, isAdminNavActive } from '~/data/adminNav'

export { isAdminNavActive }

export type PartnerType = 'referrer' | 'investor'

export interface PartnerNavItem extends AdminNavItem {
  type: PartnerType | 'shared'
}

export const partnersNav: PartnerNavItem[] = [
  { to: '/partners/home', label: 'Dashboard', icon: 'i-lucide-house', type: 'shared' },
  { to: '/partners/referrals', label: 'Referrals', icon: 'i-lucide-users', matchPrefix: '/partners/referrals', type: 'referrer' },
  { to: '/partners/earnings', label: 'Earnings', icon: 'i-lucide-wallet', matchPrefix: '/partners/earnings', type: 'referrer' },
  { to: '/partners/documents', label: 'Documents', icon: 'i-lucide-folder-lock', matchPrefix: '/partners/documents', type: 'investor' },
  { to: '/partners/reports', label: 'Reports', icon: 'i-lucide-chart-line', matchPrefix: '/partners/reports', type: 'investor' },
  { to: '/partners/profile', label: 'Profile', icon: 'i-lucide-user', matchPrefix: '/partners/profile', type: 'shared' },
]

/** The nav for one account type — unknown type (pre-fetch) shows shared only. */
export function visiblePartnersNav(type?: PartnerType | null): PartnerNavItem[] {
  return partnersNav.filter(item => item.type === 'shared' || item.type === type)
}
