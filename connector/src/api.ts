/**
 * Thin client for the Laravel scoped connector API. The bearer token
 * (CONNECTOR_TOKEN) never leaves the Worker — Claude only ever sees the tool
 * results, not the credential. Laravel error bodies are returned verbatim so the
 * MCP layer can pass their instructive messages straight back to Claude.
 */

export interface Env {
  /** Laravel API origin, e.g. https://axelnovaventures.com (no trailing /api). */
  API_BASE: string;
  /** Scoped Sanctum token minted by `php artisan connector:token`. */
  CONNECTOR_TOKEN: string;
  /** OAuth login gate (single-user, static credential check). */
  ACCESS_USERNAME: string;
  ACCESS_PASSWORD: string;
  /** workers-oauth-provider grant store. */
  OAUTH_KV: KVNamespace;
  /** McpAgent session Durable Object. */
  MCP_OBJECT: DurableObjectNamespace;
}

export interface ApiResult {
  ok: boolean;
  status: number;
  /** Raw response body (JSON string), passed through to Claude verbatim. */
  body: string;
}

export async function apiFetch(env: Env, path: string, init: RequestInit = {}): Promise<ApiResult> {
  const base = (env.API_BASE ?? "").replace(/\/+$/, "");

  const headers: Record<string, string> = {
    Authorization: `Bearer ${env.CONNECTOR_TOKEN}`,
    Accept: "application/json",
    ...(init.headers as Record<string, string> | undefined),
  };
  if (init.body !== undefined) {
    headers["Content-Type"] = "application/json";
  }

  try {
    const res = await fetch(`${base}${path}`, { ...init, headers });
    const body = await res.text();
    return { ok: res.ok, status: res.status, body };
  } catch (err) {
    // Network/DNS failure reaching Laravel — surface it as an error result so the
    // tool reports it rather than throwing an opaque 500 at the MCP client.
    return {
      ok: false,
      status: 0,
      body: JSON.stringify({
        message: `Could not reach the Axel Nova API at ${base}. ${err instanceof Error ? err.message : String(err)}`,
      }),
    };
  }
}
