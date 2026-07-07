/**
 * The OAuth login gate for the connector Worker. workers-oauth-provider runs the
 * OAuth 2.1 machinery (discovery, /token, dynamic client registration) and hands
 * the interactive /authorize step to this Hono app. Single-user by design: there
 * is no user database — a correct ACCESS_USERNAME + ACCESS_PASSWORD (Worker
 * secrets) is the whole credential check, after which the grant is completed and
 * the MCP client (claude.ai) receives its access token.
 */

import { Hono } from "hono";
import type { Env } from "./api";

/** The subset of the OAuthProvider helper injected into env as OAUTH_PROVIDER. */
interface AuthRequest {
  responseType: string;
  clientId: string;
  redirectUri: string;
  scope: string[];
  state: string;
  codeChallenge?: string;
  codeChallengeMethod?: string;
}

interface OAuthHelpers {
  parseAuthRequest(request: Request): Promise<AuthRequest>;
  lookupClient(clientId: string): Promise<{ clientName?: string } | null>;
  completeAuthorization(options: {
    request: AuthRequest;
    userId: string;
    metadata?: Record<string, unknown>;
    scope: string[];
    props: Record<string, unknown>;
  }): Promise<{ redirectTo: string }>;
}

type Bindings = Env & { OAUTH_PROVIDER: OAuthHelpers };

export const authApp = new Hono<{ Bindings: Bindings }>();

authApp.get("/", (c) =>
  c.text("Axel Nova MCP connector — add it as a custom connector in claude.ai and connect to /mcp.", 200),
);

authApp.get("/authorize", async (c) => {
  const authRequest = await c.env.OAUTH_PROVIDER.parseAuthRequest(c.req.raw);
  const client = await c.env.OAUTH_PROVIDER.lookupClient(authRequest.clientId).catch(() => null);

  return c.html(loginPage(encodeState(authRequest), client?.clientName ?? "an MCP client"));
});

authApp.post("/authorize", async (c) => {
  const form = await c.req.parseBody();
  const username = String(form.username ?? "");
  const password = String(form.password ?? "");
  const stateField = String(form.state ?? "");

  const expectedUser = c.env.ACCESS_USERNAME ?? "";
  const expectedPass = c.env.ACCESS_PASSWORD ?? "";
  const authorized =
    expectedUser.length > 0 &&
    expectedPass.length > 0 &&
    timingSafeEqual(username, expectedUser) &&
    timingSafeEqual(password, expectedPass);

  if (!authorized) {
    return c.html(loginPage(stateField, "an MCP client", "Invalid username or password."), 401);
  }

  const authRequest = decodeState<AuthRequest>(stateField);
  const { redirectTo } = await c.env.OAUTH_PROVIDER.completeAuthorization({
    request: authRequest,
    userId: "axelnova-founder",
    metadata: { label: "Axel Nova founder" },
    scope: authRequest.scope ?? [],
    props: { login: "axelnova-founder" },
  });

  return c.redirect(redirectTo);
});

function loginPage(state: string, clientName: string, error?: string): string {
  const errorBlock = error
    ? `<p class="error">${escapeHtml(error)}</p>`
    : "";

  return `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="robots" content="noindex" />
  <title>Axel Nova · Connector sign-in</title>
  <style>
    :root { color-scheme: dark; }
    * { box-sizing: border-box; }
    body {
      margin: 0; min-height: 100vh; display: grid; place-items: center;
      font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
      background: #0a0a0b; color: #e7e7ea;
    }
    .card {
      width: min(92vw, 380px); padding: 2rem 1.75rem; border-radius: 16px;
      background: #131316; border: 1px solid #26262b;
      box-shadow: 0 20px 60px rgba(0,0,0,.45);
    }
    h1 { font-size: 1.15rem; margin: 0 0 .25rem; letter-spacing: -.01em; }
    p.sub { margin: 0 0 1.5rem; color: #9a9aa2; font-size: .85rem; }
    label { display: block; font-size: .78rem; color: #b7b7bf; margin: .9rem 0 .35rem; }
    input {
      width: 100%; padding: .6rem .7rem; border-radius: 9px;
      background: #0e0e10; border: 1px solid #303036; color: #f4f4f6; font-size: .9rem;
    }
    input:focus { outline: none; border-color: #6366f1; }
    button {
      width: 100%; margin-top: 1.4rem; padding: .65rem; border: 0; border-radius: 9px;
      background: #6366f1; color: #fff; font-weight: 600; font-size: .9rem; cursor: pointer;
    }
    button:hover { background: #4f52e0; }
    .error { color: #fb7185; font-size: .8rem; margin: .5rem 0 0; }
  </style>
</head>
<body>
  <main class="card">
    <h1>Axel Nova connector</h1>
    <p class="sub">${escapeHtml(clientName)} is requesting access to draft quotations. Sign in to authorize.</p>
    ${errorBlock}
    <form method="post" action="/authorize">
      <input type="hidden" name="state" value="${escapeHtml(state)}" />
      <label for="username">Username</label>
      <input id="username" name="username" autocomplete="username" autofocus />
      <label for="password">Password</label>
      <input id="password" name="password" type="password" autocomplete="current-password" />
      <button type="submit">Authorize</button>
    </form>
  </main>
</body>
</html>`;
}

function escapeHtml(value: string): string {
  return value.replace(/[&<>"']/g, (c) =>
    ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c] as string,
  );
}

/** Serialize the AuthRequest into a form-safe base64 blob carried between GET and POST. */
function encodeState(value: unknown): string {
  const bytes = new TextEncoder().encode(JSON.stringify(value));
  let binary = "";
  for (const b of bytes) binary += String.fromCharCode(b);
  return btoa(binary);
}

function decodeState<T>(state: string): T {
  const bytes = Uint8Array.from(atob(state), (ch) => ch.charCodeAt(0));
  return JSON.parse(new TextDecoder().decode(bytes)) as T;
}

/** Constant-time-ish comparison so credential checks don't leak length/prefix by timing. */
function timingSafeEqual(a: string, b: string): boolean {
  const enc = new TextEncoder();
  const ab = enc.encode(a);
  const bb = enc.encode(b);
  if (ab.length !== bb.length) {
    return false;
  }
  let diff = 0;
  for (let i = 0; i < ab.length; i++) {
    diff |= ab[i] ^ bb[i];
  }
  return diff === 0;
}
