// Plain fetcher for public, token-in-URL API calls (the access-link form).
// Auth here is bearer-only and the API answers CORS without
// Access-Control-Allow-Credentials (backend config/cors.php), so cookies must
// never be sent: a credentialed request from axelnovaventures.com to the API on
// the admin host (or localhost:3003 → :8003 in dev) is blocked by the browser.
export const useApi = () => {
  const config = useRuntimeConfig()
  return $fetch.create({
    baseURL: config.public.apiBase,
    credentials: 'omit',
  })
}
