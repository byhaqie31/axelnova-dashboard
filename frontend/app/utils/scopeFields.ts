// Shared classification for the quotation/order "scope details" displays. Turns a
// raw form_payload into labelled, typed fields so both the quotation detail and the
// order Scope snapshot render the same polished spec grid (see AdminScopeDetails).

// Human labels for known scope keys; anything unmapped falls back to a tidy
// title-case so new fields still read correctly without a code change.
export const SCOPE_LABELS: Record<string, string> = {
  cms: 'CMS', pages: 'Pages', modules: 'Modules', testing: 'Testing',
  languages: 'Languages', prototype: 'Prototype', real_time: 'Real-time',
  user_roles: 'User roles', pages_count: 'Pages', admin_portal: 'Admin portal',
  booking_flow: 'Booking flow', design_system: 'Design system',
  screens_count: 'Screens', components_count: 'Components',
  state_management: 'State management', charts_complexity: 'Charts complexity',
  auth_methods: 'Auth methods', payment_method: 'Payment method', core_features: 'Core features',
}

export function humanizeScope(key: string): string {
  return SCOPE_LABELS[key] ?? key.replace(/_/g, ' ').replace(/^\w/, c => c.toUpperCase())
}

export type ScopeKind = 'bool' | 'number' | 'text'
export interface ScopeField { key: string; label: string; kind: ScopeKind; value: string; on: boolean }

// Pricing-control keys never belong in a human scope readout.
const SKIP = new Set(['package_key', 'modifiers', 'addon_keys', 'rush', 'breakdown', 'category_key'])

// Specs (numbers / text) lead; feature toggles (Yes/No) group together after them,
// so the badges line up as one scannable block rather than scattered through the grid.
export function classifyScopeFields(payload: Record<string, any> | null | undefined): ScopeField[] {
  if (!payload) return []
  const fields: ScopeField[] = []
  for (const [k, v] of Object.entries(payload)) {
    if (SKIP.has(k) || v === '' || v === null || (Array.isArray(v) && !v.length)) continue
    const label = humanizeScope(k)
    if (typeof v === 'boolean') fields.push({ key: k, label, kind: 'bool', value: v ? 'Yes' : 'No', on: v })
    else if (typeof v === 'number') fields.push({ key: k, label, kind: 'number', value: String(v), on: false })
    else fields.push({ key: k, label, kind: 'text', value: Array.isArray(v) ? v.join(', ') : String(v), on: false })
  }
  return fields.sort((a, b) => Number(a.kind === 'bool') - Number(b.kind === 'bool'))
}
