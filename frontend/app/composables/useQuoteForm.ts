// Shared state for the quote builder, persisted across `/quote` → `/quote/preview` → `/quote/success`.
// Uses `useState` so a hard refresh on a sub-route resets to defaults — preview/success pages must
// guard against an empty form and bounce the user back to `/quote`.

export interface QuoteFormState {
  // Section 1: About you
  name: string
  company: string
  email: string
  phone: string
  source: string

  // Section 2: Project type
  categoryKey: string
  packageKey: string

  // Section 3: Scope (conditional)
  pages: number
  languages: string[]
  cms: boolean
  bookingFlow: boolean
  modules: number
  userRoles: number
  realTime: boolean
  chartsComplexity: 'none' | 'basic' | 'advanced'
  screensCount: number
  designSystem: boolean
  prototype: boolean
  componentsCount: number
  pagesCount: number
  stateManagement: boolean
  testing: boolean
  coreFeatures: string
  authMethods: string[]
  paymentMethod: string
  adminPortal: boolean
  notSureNotes: string

  // Section 4: Tech stack
  frontendTech: string
  backendTech: string
  hostingPref: string

  // Section 5: Add-ons
  addonKeys: string[]

  // Section 6: Timeline & budget
  rush: boolean
  budgetRange: string
  notes: string
}

const defaults = (): QuoteFormState => ({
  name: '',
  company: '',
  email: '',
  phone: '',
  source: '',
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
  notSureNotes: '',
  frontendTech: '',
  backendTech: '',
  hostingPref: '',
  addonKeys: [],
  rush: false,
  budgetRange: '',
  notes: '',
})

export function useQuoteForm() {
  const formState = useState<QuoteFormState>('quote-form', defaults)

  // The inner object is deeply reactive thanks to ref's auto-conversion, so handing back
  // `.value` lets callers use `form.name` directly (matching the prior `reactive()` ergonomics)
  // while still sharing one instance across pages.
  const form = formState.value

  function resetForm() {
    Object.assign(formState.value, defaults())
  }

  // True when enough is filled in for the preview page to be meaningful.
  // Used by the preview route to bounce empty visitors back to the form.
  function hasMinimumData() {
    return form.name.trim().length >= 2
      && form.email.includes('@')
      && form.phone.trim().length >= 7
      && !!form.packageKey
  }

  return { form, resetForm, hasMinimumData }
}
