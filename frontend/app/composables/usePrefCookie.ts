// A persistent UI-preference cookie (sidebar pins, collapsed rail, open groups).
// Cookie rather than localStorage so SSR reads it and the layout renders in its
// saved state with no flash. A plain useCookie() with no maxAge is a SESSION
// cookie — the browser drops it on quit and prefs silently reset — so every pref
// gets a 1-year expiry, renewed on each write.
const PREF_MAX_AGE = 60 * 60 * 24 * 365

export function usePrefCookie<T>(name: string, fallback: () => T) {
  return useCookie<T>(name, { default: fallback, maxAge: PREF_MAX_AGE })
}
