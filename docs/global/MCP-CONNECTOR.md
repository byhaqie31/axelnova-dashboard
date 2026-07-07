# MCP Quotation Connector

Lets Claude (claude.ai / Cowork) draft **quotations** in `axelnova-dashboard` straight from a client brief, without giving it a login to the cockpit. Two halves:

1. **Laravel** — a scoped `/api/v1/connector/*` route group (read catalog, create/read **draft** quotations only), gated by Sanctum token *abilities*, not admin role.
2. **Cloudflare Worker** — a remote MCP server at `mcp.axelnova.tech` that exposes three tools and proxies them to the Laravel API, adding the scoped bearer token server-side (Claude never sees it).

```
Claude (claude.ai)  ──OAuth──▶  mcp.axelnova.tech  ──Bearer CONNECTOR_TOKEN──▶  /api/v1/connector/*  (Laravel)
     (MCP client)               (Worker, connector/)                            (draft-only surface)
```

## Guardrails — what it can and cannot do

The connector is **draft-only by construction**. Its Sanctum token carries only `connector:read` + `connector:draft` (never `cockpit`), and each route opens exactly one ability:

- ✅ read the catalog, create a **draft** quotation, read back a **connector-created** draft.
- ❌ change quotation status, send/accept a quote, create orders, touch clients/services/payments, or delete anything.

A connector token is rejected by every `/v1/admin/*` route (they demand `abilities:cockpit`); the admin cockpit token is likewise rejected by `/v1/connector/*`. Read-back is scoped to rows the connector authored (`document.created_via = mcp_connector`) so the token can't enumerate arbitrary quotations.

## Backend endpoints

| Method | Path | Ability | Purpose |
|---|---|---|---|
| GET | `/v1/connector/catalog` | `connector:read` | Merged catalog: quotable packages (key, name, tagline, price range, ETA, the modifier keys each accepts), global add-ons, rush rules, bespoke note |
| POST | `/v1/connector/quotations/draft` | `connector:draft` | Create a draft quotation (contract below) |
| GET | `/v1/connector/quotations/{reference_code}` | `connector:read` | Read back a connector-created draft by AXNQ code |

Backend code: `app/Http/Controllers/Api/V1/Connector/{CatalogController,QuotationDraftController}.php`, `app/Http/Requests/Connector/DraftQuotationRequest.php`, `app/Services/Connector/ConnectorCatalog.php`. Pricing is reused verbatim from [`PricingEngine`](../../backend/app/Services/Quoting/PricingEngine.php); reference codes from [`ReferenceCodeGenerator`](../../backend/app/Support/ReferenceCodeGenerator.php). See [QUOTE_BUILDER.md](./QUOTE_BUILDER.md) for the pricing model.

## The three MCP tools

| Tool | Maps to | Notes |
|---|---|---|
| `list_catalog` | `GET /catalog` | **Call first.** Returns valid package/modifier/add-on keys. |
| `create_draft_quotation` | `POST /quotations/draft` | Creates a DRAFT only — never sends to the client. Single-package (`package_key`), multi-package (`packages[]`), or bespoke (`line_items`, no package). |
| `get_quotation` | `GET /quotations/{ref}` | Read-back by reference code. |

### Draft request contract

```jsonc
{
  "client": { "name": "…", "email": "…", "phone": null, "company": null },  // name + email required

  // Single-package sugar (a convenience for a one-entry packages[]):
  "package_key": "web_business" | null,          // null = fully bespoke
  "modifiers": { "cms": true, "extra_page": 7 }, // only with the top-level package_key; keys must be valid for it
  "addon_keys": ["seo"],                          // only with the top-level package_key

  // OR the canonical multi-package shape (mutually exclusive with the sugar above):
  "packages": [
    { "package_key": "web_business", "modifiers": { "cms": true }, "addon_keys": ["seo"] },
    { "package_key": "dash_starter" }
  ],

  "rush": false,                                  // one flag for the whole quote
  "line_items": [                                 // REQUIRED (non-empty) when there is no package (bespoke)
    { "label": "Custom booking engine", "description": "…", "amount_myr": 12000 }
  ],
  "assumptions": ["…"],                            // the AI's guesses — for admin review
  "open_questions": ["…"],                         // what to confirm with the client
  "notes": "…"
}
```

**Priced path (a package is set).** Each package is re-priced through the same `PricingEngine` as the public funnel; a multi-package quote **sums** the per-package min/max and takes the **longest** ETA. Each package's flat `modifiers` map is split onto the engine's two inputs — admin-managed **scope fields** → `scope_values`, legacy JSON **modifiers** → `modifiers` (a scope field wins over a legacy key of the same name). Add-ons are persisted as `quotation_addons` rows. The draft is stored in the **canonical multi-package `form_payload`** (`packages[]` with resolved `service_package_id`, top-level `rush`, grouped `breakdown`, `source_meta.created_via`) and a **seeded `document`** — see [QUOTE_BUILDER.md](./QUOTE_BUILDER.md). Because the document is seeded (via the shared `DocumentSeeder`), a connector draft opens fully hydrated in the admin builder and the PDF previews with real numbers. Any `line_items` ride along as **extra document lines** (added to `document.items`), never folded into the engine estimate.

**Bespoke path (no package).** `estimate_min_myr = estimate_max_myr = Σ line_items.amount_myr`; the `line_items` become the document. ETA is stored as the codebase's `0`/`week` "no ETA yet" sentinel (the columns are `NOT NULL`) and surfaced as `null` — the admin sets the real timeline. Rejected if `line_items` is empty. `modifiers`/`addon_keys`/`packages` are rejected on a bespoke quote.

> **Shape note.** `get_quotation` still returns `line_items` (now derived from `document.items`); the old connector-only `document.line_items` key is retired but legacy rows still read back.

**Instructive validation.** Unknown package / modifier / add-on keys → **422** whose message *lists the valid keys*. The Worker passes the Laravel body through verbatim, so Claude reads the message and self-corrects. Every draft lands `status=draft`, `source=admin`, with an `AXNQ-YYYY-NNNN` reference code, and `document.created_via = mcp_connector`. The response includes the estimate and the admin URL (`/admin/quotations/{id}`).

## Minting / rotating the token

The Worker authenticates to Laravel with a scoped Sanctum token. Mint it on the **API host** (prod), never in CI:

```bash
php artisan connector:token                 # the sole founder
php artisan connector:token --email=founder@example.com   # if there are several founders
```

It revokes any prior `mcp-connector` token (so re-running **rotates**), mints a new one with `connector:read` + `connector:draft`, and prints the plaintext **once**. Paste it into the Worker secret (below).

> **Expiry caveat.** Sanctum enforces the global `SANCTUM_EXPIRATION_MINUTES` (if set) against every token's `created_at` — a per-token expiry can't extend past it. For a long-lived connector, leave `SANCTUM_EXPIRATION_MINUTES` **unset** in the API env, or re-run `connector:token` to rotate on your own cadence.

## Worker deploy (runbook)

From `connector/`. Steps marked ⚠️ create/rotate real infrastructure — they were **not** run during development.

```bash
cd connector
npm install

# ⚠️ 1. OAuth grant store — create the KV namespace and paste its id into wrangler.toml (kv_namespaces.id)
npx wrangler kv namespace create OAUTH_KV

# ⚠️ 2. Secrets (never committed)
npx wrangler secret put API_BASE          # e.g. https://axelnovaventures.com  (no trailing /api)
npx wrangler secret put CONNECTOR_TOKEN   # the token from `php artisan connector:token`
npx wrangler secret put ACCESS_USERNAME   # login for the OAuth gate (single-user)
npx wrangler secret put ACCESS_PASSWORD   # a strong passphrase

# ⚠️ 3. Deploy — provisions the mcp.axelnova.tech DNS record + Worker route (custom_domain in wrangler.toml)
npx wrangler deploy
```

Non-deploy checks that are safe to run any time: `npm run typecheck`, `npx wrangler deploy --dry-run --outdir=dist`.

**Local dev:** copy `.dev.vars.example` → `.dev.vars` (gitignored), then `npm run dev`. Point `API_BASE` at the dev backend (`http://host.docker.internal:8003`).

### Auth model

`workers-oauth-provider` runs the OAuth 2.1 flow (discovery, `/token`, dynamic client registration); claude.ai registers itself automatically. The security boundary is the **login gate** at `/authorize` (`src/auth.ts`): a correct `ACCESS_USERNAME` + `ACCESS_PASSWORD` (the static, single-user credential) completes the grant. No user database. If claude.ai's *advanced settings* ask for an OAuth client id/secret, leave them blank — DCR fills them in.

## Adding it in claude.ai

1. **Settings → Connectors → Add custom connector** (Customize → Connectors).
2. **URL:** `https://mcp.axelnova.tech/mcp`
3. Connect → you'll be redirected to the connector login → enter `ACCESS_USERNAME` / `ACCESS_PASSWORD` → authorize.
4. The three tools (`list_catalog`, `create_draft_quotation`, `get_quotation`) appear. Ask Claude to draft a quote; it calls `list_catalog` first, then `create_draft_quotation`. Review the draft in `/admin/quotations`.

## File map

- Backend: `app/Http/Controllers/Api/V1/Connector/*`, `app/Http/Requests/Connector/DraftQuotationRequest.php`, `app/Services/Connector/ConnectorCatalog.php`, `app/Console/Commands/MintConnectorToken.php`, route group in [`routes/api.php`](../../backend/routes/api.php).
- Worker: `connector/src/{index,api,auth}.ts`, `connector/wrangler.toml`, `connector/package.json`.
- Tests: `backend/tests/Feature/Connector/ConnectorDraftTest.php`.

## Out of scope (v1)

No status/accept/order tools, no delete, no client management, no multi-user OAuth. In-dashboard AI UI is a separate, API-billed phase.
