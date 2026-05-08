/**
 * Curated lucide icons for service-category branding. Used by the admin
 * category form to give admins a visual picker instead of free-typing
 * Iconify names. Add to this list rather than letting admins type any
 * icon — keeps the public services page visually consistent.
 *
 * Each entry's `name` is the Iconify name stored on `service_categories.icon`.
 * The `label` is the human-readable hint shown as a tooltip in the picker.
 */
export interface ServiceIcon {
  name: string
  label: string
}

export const serviceIcons: ServiceIcon[] = [
  // Web / digital presence
  { name: 'i-lucide-globe', label: 'Web presence' },
  { name: 'i-lucide-monitor', label: 'Website / marketing site' },
  { name: 'i-lucide-layout-dashboard', label: 'Dashboard / portal' },
  { name: 'i-lucide-smartphone', label: 'Mobile / app' },

  // Engineering
  { name: 'i-lucide-code-2', label: 'Frontend engineering' },
  { name: 'i-lucide-server', label: 'Backend / API' },
  { name: 'i-lucide-database', label: 'Data / storage' },
  { name: 'i-lucide-cloud', label: 'Cloud / hosting' },
  { name: 'i-lucide-cpu', label: 'Systems / performance' },
  { name: 'i-lucide-network', label: 'Integrations / networking' },

  // Design
  { name: 'i-lucide-pen-tool', label: 'UI / UX design' },
  { name: 'i-lucide-palette', label: 'Brand / visual design' },
  { name: 'i-lucide-figma', label: 'Figma / design tooling' },
  { name: 'i-lucide-layers', label: 'Design system' },

  // Product / SaaS
  { name: 'i-lucide-rocket', label: 'SaaS / product launch' },
  { name: 'i-lucide-package', label: 'Productized service' },
  { name: 'i-lucide-puzzle', label: 'Integrations / add-ons' },
  { name: 'i-lucide-bot', label: 'AI / automation' },

  // Growth / marketing
  { name: 'i-lucide-megaphone', label: 'Marketing / launch' },
  { name: 'i-lucide-bar-chart-3', label: 'Analytics / reporting' },
  { name: 'i-lucide-search', label: 'SEO / discoverability' },
  { name: 'i-lucide-mail', label: 'Email / lifecycle' },

  // Ongoing / support
  { name: 'i-lucide-refresh-cw', label: 'Retainer / ongoing' },
  { name: 'i-lucide-wrench', label: 'Maintenance / fixes' },
  { name: 'i-lucide-life-buoy', label: 'Support' },
  { name: 'i-lucide-shield', label: 'Security / compliance' },
]
