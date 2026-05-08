/**
 * Returns the right API base URL for the current execution context.
 *
 * - Server-side (SSR inside the frontend container): docker-network hostname
 *   (e.g. http://backend:8003) so the request resolves to the sibling container.
 * - Client-side (browser): host-loopback (e.g. http://127.0.0.1:8003) so the
 *   request leaves the browser and hits the published port on the host.
 *
 * Overridden at runtime by NUXT_API_BASE (server) and NUXT_PUBLIC_API_BASE (client).
 */
export function useApiBase(): string {
  const config = useRuntimeConfig()
  return import.meta.server ? config.apiBase : config.public.apiBase
}
