// Reading a failed API call ($fetch's FetchError) without `any` — the status
// and Laravel's JSON error body. Pure, no Nuxt runtime; auto-imported.

/** Laravel's JSON error body: a message, plus per-field messages on a 422. */
export interface ApiErrorBody {
  message?: string
  errors?: Record<string, string[]>
}

/** The HTTP status and JSON body of a failed request (both optional — a network error has neither). */
export function apiError(e: unknown): { status?: number, data?: ApiErrorBody } {
  return e && typeof e === 'object' ? e as { status?: number, data?: ApiErrorBody } : {}
}

export const FORM_ERROR_FALLBACK = 'Something went wrong. Please try again, or email baihaqie@axelnova.tech directly.'

/** What a public form shows: every validation message, else the API's message, else the fallback. */
export function formErrorMessage(e: unknown, fallback: string = FORM_ERROR_FALLBACK): string {
  const { data } = apiError(e)
  const fieldErrors = data?.errors ? Object.values(data.errors).flat().join(' ') : ''
  return fieldErrors || data?.message || fallback
}
