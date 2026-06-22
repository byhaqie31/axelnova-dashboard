// Shared scope model for the pricing engine — consumed by the admin quotation
// builder via <QuoteScopeFields>. The pricing-relevant subset of the old public
// quote form: category + package + per-category scope inputs + add-ons + rush.

export interface QuoteScopeState {
  categoryKey: string
  packageKey: string

  // web
  pages: number
  languages: string[]
  cms: boolean
  bookingFlow: boolean

  // dashboard
  modules: number
  userRoles: number
  realTime: boolean
  chartsComplexity: 'none' | 'basic' | 'advanced'

  // design & frontend (the combined `design-frontend` category)
  screensCount: number
  designSystem: boolean
  prototype: boolean
  componentsCount: number
  pagesCount: number
  stateManagement: boolean
  testing: boolean

  // saas
  coreFeatures: string
  authMethods: string[]
  paymentMethod: string
  adminPortal: boolean

  // shared
  addonKeys: string[]
  rush: boolean
}

export function defaultQuoteScope(): QuoteScopeState {
  return {
    categoryKey: '',
    packageKey: '',
    pages: 5,
    languages: [],
    cms: false,
    bookingFlow: false,
    modules: 5,
    userRoles: 2,
    realTime: false,
    chartsComplexity: 'basic',
    screensCount: 10,
    designSystem: false,
    prototype: false,
    componentsCount: 10,
    pagesCount: 5,
    stateManagement: false,
    testing: false,
    coreFeatures: '',
    authMethods: [],
    paymentMethod: '',
    adminPortal: false,
    addonKeys: [],
    rush: false,
  }
}

/**
 * Derive the pricing-engine modifiers from the scope state — the single source
 * of truth shared by the live estimate and the server re-price payload. Only the
 * web + dashboard categories carry priced modifiers in the config; design-frontend
 * and saas scope inputs are captured for context but don't move the estimate.
 * (Mirrors the legacy public-quote mapping exactly to keep client/server in sync.)
 */
export function deriveModifiers(s: QuoteScopeState): Record<string, boolean | number> {
  const m: Record<string, boolean | number> = {}

  if (s.categoryKey === 'web') {
    if (s.pages > 5) m.extra_page = s.pages
    if (s.cms) m.cms = true
    if (s.bookingFlow) m.booking_flow = true
    if (s.languages.length > 1) m.extra_language = s.languages.length - 1
  }
  else if (s.categoryKey === 'dashboard') {
    if (s.modules > 5) m.extra_module = s.modules
    if (s.realTime) m.real_time_features = true
    if (s.chartsComplexity === 'advanced') m.advanced_charts = true
  }

  return m
}

/** Flatten the scope state into the form_payload shape the backend stores. */
export function scopeToPayload(s: QuoteScopeState): Record<string, unknown> {
  return {
    pages: s.pages,
    languages: s.languages,
    cms: s.cms,
    booking_flow: s.bookingFlow,
    modules: s.modules,
    user_roles: s.userRoles,
    real_time: s.realTime,
    charts_complexity: s.chartsComplexity,
    screens_count: s.screensCount,
    design_system: s.designSystem,
    prototype: s.prototype,
    components_count: s.componentsCount,
    pages_count: s.pagesCount,
    state_management: s.stateManagement,
    testing: s.testing,
    core_features: s.coreFeatures,
    auth_methods: s.authMethods,
    payment_method: s.paymentMethod,
    admin_portal: s.adminPortal,
  }
}
