# MCP Quotation Connector

Lets Claude (claude.ai / Cowork) drive the **quotation pipeline** in `axelnova-dashboard` straight from a client brief, without giving it a login to the cockpit. Two halves:

1. **Laravel** — a scoped `/api/v1/connector/*` route group, gated by Sanctum token *abilities*, not admin role.
2. **Cloudflare Worker** — a remote MCP server at `mcp.axelnova.tech` that exposes the tools and proxies them to the Laravel API, adding the scoped bearer token server-side (Claude never sees it). Its `CONNECTOR_VERSION` (advertised as the MCP server version in the initialize handshake) tags the contract — **v3** is read-open reads + a lifecycle-gated update.

```
Claude (claude.ai)  ──OAuth──▶  mcp.axelnova.tech  ──Bearer CONNECTOR_TOKEN──▶  /api/v1/connector/*  (Laravel)
     (MCP client)               (Worker, connector/)                            (scoped connector surface)
```

## Guardrails — the access model (v3)

**Read everything, write with a lifecycle guardrail, destroy only by hand.** The Sanctum token carries only `connector:read` + `connector:draft` (never `cockpit`), and each route opens exactly one ability:

- ✅ **READ everything** — the catalog, a slim **list** of ANY non-deleted quotation, and full **read-back** of ANY non-deleted quotation (whatever created it — funnel, admin, or connector).
- ✅ **WRITE with a gate** — create a **draft**; **update** any quotation while it is a **pre-send draft** (status `draft`). Locked once `sent` (or accepted/rejected/expired).
- ❌ **never** — change status, send/accept a quote, create orders, touch clients/services/payments, or **delete** anything. Deletion is **portal-only, by hand** — there is no delete tool (irreversible actions stay human-only).

A connector token is rejected by every `/v1/admin/*` route (they demand `abilities:cockpit`); the admin cockpit token is likewise rejected by `/v1/connector/*`. **Soft-deleted quotations never surface through any connector read.** Read endpoints are throttled 60/min, writes 30/min.

## Backend endpoints

| Method | Path | Ability | Throttle | Purpose |
|---|---|---|---|---|
| GET | `/v1/connector/catalog` | `connector:read` | 60/min | Merged catalog: quotable packages (key, name, tagline, price range, ETA, the modifier keys each accepts), global add-ons, rush rules, bespoke note |
| GET | `/v1/connector/quotations` | `connector:read` | 60/min | Slim list of non-deleted quotations — `status[]`, `q`, `from`/`to`, `page`/`per_page` (default 10, capped 25), newest first |
| GET | `/v1/connector/quotations/{reference_code}` | `connector:read` | 60/min | Read back ANY non-deleted quotation by AXNQ code |
| POST | `/v1/connector/quotations/draft` | `connector:draft` | 30/min | Create a draft quotation (contract below) |
| PUT | `/v1/connector/quotations/{reference_code}` | `connector:draft` | 30/min | Update a PRE-SEND draft (same body as draft + `reseed_document`) |

Backend code: `app/Http/Controllers/Api/V1/Connector/{CatalogController,QuotationDraftController}.php`, `app/Http/Requests/Connector/{DraftQuotationRequest,UpdateDraftQuotationRequest,ListQuotationsRequest}.php`, `app/Services/Connector/ConnectorCatalog.php`, `app/Services/Quoting/QuotationIndexQuery.php` (the list query, shared with the admin index). Pricing is reused verbatim from [`PricingEngine`](../../backend/app/Services/Quoting/PricingEngine.php); reference codes from [`ReferenceCodeGenerator`](../../backend/app/Support/ReferenceCodeGenerator.php). See [QUOTE_BUILDER.md](./QUOTE_BUILDER.md) for the pricing model + the connector tool contract table.

## The five MCP tools

| Tool | Maps to | Scope / gate | Notes |
|---|---|---|---|
| `list_catalog` | `GET /catalog` | read | **Call first.** Returns valid package/modifier/add-on keys. |
| `list_quotations` | `GET /quotations` | read (any) | Browse/filter slim rows to find a quotation without its code. No `form_payload`/`document`. |
| `get_quotation` | `GET /quotations/{ref}` | read (any) | Full read-back of ANY quotation by reference code. |
| `create_draft_quotation` | `POST /quotations/draft` | write | Creates a DRAFT only. Single-package (`package_key`), multi-package (`packages[]`), bespoke (`line_items`), or `detailed`. |
| `update_draft_quotation` | `PUT /quotations/{ref}` | write · **gate: pre-send draft** | Re-specifies + re-prices a pre-send draft. Refused 422 once sent. `reseed_document` controls document regeneration. |

There is deliberately **no delete tool** — deletion is portal-only (`DELETE /v1/admin/quotations/{id}`, soft delete, blocked 409 when an order is attached).

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

  // OR a rich, self-priced DETAILED proposal (mutually exclusive with package/packages/line_items):
  "detailed": {
    "subtitle": "Website quotation",
    "deposit_pct": 50,
    "sections": [                                  // the priced scope; quote total = Σ every row's amount_myr
      { "title": "Design", "rows": [ { "title": "Brand + UI", "detail": "…", "amount_myr": 3500 } ] }
    ],
    "included": [ { "eyebrow": "SEO", "items": ["…"], "columns": 2 } ],   // "What's included" groups
    "options":  [ { "badge": "OPTION A", "title": "Standard", "amount_myr": 13000, "recommended": true } ], // option cards
    "care":     [ { "label": "Basic", "detail": "…", "amount_myr": 250, "period": "month" } ]   // care plan
  },

  "project": "Brand website — design & build",    // optional: document title on the PDF (any mode)
  "intro": "A fast, clean marketing site.",        // optional: lead-in under the title (any mode)
  "assumptions": ["…"],                            // the AI's guesses — for admin review
  "open_questions": ["…"],                         // what to confirm with the client
  "notes": "…"
}
```

**Priced path (a package is set).** Each package is re-priced through the same `PricingEngine` as the public funnel; a multi-package quote **sums** the per-package min/max and takes the **longest** ETA. Each package's flat `modifiers` map is split onto the engine's two inputs — admin-managed **scope fields** → `scope_values`, legacy JSON **modifiers** → `modifiers` (a scope field wins over a legacy key of the same name). Add-ons are persisted as `quotation_addons` rows. The draft is stored in the **canonical multi-package `form_payload`** (`packages[]` with resolved `service_package_id`, top-level `rush`, grouped `breakdown`, `source_meta.created_via`) and a **seeded `document`** — see [QUOTE_BUILDER.md](./QUOTE_BUILDER.md). Because the document is seeded (via the shared `DocumentSeeder`), a connector draft opens fully hydrated in the admin builder and the PDF previews with real numbers. Any `line_items` ride along as **extra document lines** (added to `document.items`), never folded into the engine estimate.

**Bespoke path (no package).** `estimate_min_myr = estimate_max_myr = Σ line_items.amount_myr`; the `line_items` become the document. ETA is stored as the codebase's `0`/`week` "no ETA yet" sentinel (the columns are `NOT NULL`) and surfaced as `null` — the admin sets the real timeline. Rejected if `line_items` is empty. `modifiers`/`addon_keys`/`packages` are rejected on a bespoke quote.

**Detailed path (`detailed` set).** The connector's richest mode — a self-priced, presentation-grade proposal: grouped scope `sections` (each `rows[].amount_myr` priced), plus optional `included` tick-list groups, `options` cards, and a `care` plan. Priced from the section totals (`estimate_min = estimate_max = Σ section amounts`), NOT the engine — so `detailed` is **mutually exclusive** with `package_key`/`packages`/`line_items`. Built by [`DetailedDocumentBuilder`](../../backend/app/Services/Quoting/DetailedDocumentBuilder.php) into the same `layout: 'detailed'` `document.payload` shape the admin detailed builder produces, so it re-opens in the admin builder's detailed mode and the PDF renders the full proposal. ETA left as the `0`/`week` sentinel. `deposit_pct` defaults to 50.

**Document title / intro (any mode).** `project` and `intro` set `document.project` / `document.intro` (the quotation's title + lead-in on the PDF). Optional — the mapper falls back to a default project title when `project` is omitted.

> **Shape note.** `get_quotation` still returns `line_items` (now derived from `document.items`); the old connector-only `document.line_items` key is retired but legacy rows still read back.

**Instructive validation.** Unknown package / modifier / add-on keys → **422** whose message *lists the valid keys*. The Worker passes the Laravel body through verbatim, so Claude reads the message and self-corrects. Every draft lands `status=draft`, `source=admin`, with an `AXNQ-YYYY-NNNN` reference code, and `document.created_via = mcp_connector`. The response includes the estimate and the admin URL (`/admin/quotations/{id}`).

## Minting / rotating the token

The Worker authenticates to Laravel with a scoped Sanctum token. **Rotation is one command, run locally** (needs the `vps` SSH alias and wrangler auth):

```bash
cd connector && npm run rotate-token        # 30-day token; or ./rotate-token.sh 90
```

[`rotate-token.sh`](../../connector/rotate-token.sh) mints on the VPS (`connector:token --plain` inside the prod backend container), **verifies the new token against the live catalog endpoint before touching anything**, then pipes it into `npx wrangler secret put CONNECTOR_TOKEN`. Putting a secret already restarts the Worker on a new version — the script deliberately does **not** run `wrangler deploy`, which would ship whatever code sits in your local checkout. The token never touches disk or shell history.

Manual fallback — mint on the **API host** (prod), never in CI:

```bash
php artisan connector:token                 # the sole founder, 30-day lifetime
php artisan connector:token --days=90       # longer lifetime (its expires_at)
php artisan connector:token --email=founder@example.com   # if there are several founders
```

It revokes any prior `mcp-connector` token (so re-running **rotates**), mints a new one with `connector:read` + `connector:draft` and an `expires_at` of `--days` (default **30**), and prints the plaintext **once**. Paste it into the Worker secret (below).

> **Lifetime.** The global `SANCTUM_EXPIRATION_MINUTES` cap (default **720 min = 12 h** in `config/sanctum.php` — a Phase-0 guard so leaked admin *login* tokens die fast) would otherwise kill this token half a day after minting, regardless of its own expiry. `AppServiceProvider` therefore exempts exactly the `mcp-connector` token from the global cap: its lifetime is its **own `expires_at`**. The exemption requires an explicit future expiry — a connector token minted without one falls back under the global cap. When it expires (or to rotate early), run `npm run rotate-token` from `connector/` — or the manual steps above.

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
4. The five tools (`list_catalog`, `list_quotations`, `get_quotation`, `create_draft_quotation`, `update_draft_quotation`) appear. Ask Claude to draft a quote; it calls `list_catalog` first, then `create_draft_quotation`. Ask it to "show pending quotes from this month" and it uses `list_quotations`; ask it to tweak a draft and it uses `update_draft_quotation`. Review everything in `/admin/quotations`.

## File map

- Backend: `app/Http/Controllers/Api/V1/Connector/*`, `app/Http/Requests/Connector/DraftQuotationRequest.php`, `app/Services/Connector/ConnectorCatalog.php`, `app/Console/Commands/MintConnectorToken.php`, route group in [`routes/api.php`](../../backend/routes/api.php).
- Worker: `connector/src/{index,api,auth}.ts`, `connector/wrangler.toml`, `connector/package.json`, `connector/rotate-token.sh` (one-command token rotation).
- Tests: `backend/tests/Feature/Connector/ConnectorDraftTest.php`.

## Out of scope

Still no status/accept/order tools, no delete tool (portal-only), no client-management surface, no multi-user OAuth, and no post-send "revision" concept (a sent quote is locked to the connector). In-dashboard AI UI is a separate, API-billed phase.

## Version history

- **v3** — `list_quotations` (browse/filter), read-open `get_quotation` (any quotation, not just connector-created), `update_draft_quotation` (lifecycle-gated: pre-send drafts only, re-prices + guards the document via `reseed_document`, stamps `last_updated_via`), portal-only soft delete, read/write throttles, `CONNECTOR_VERSION` tag.
- **v2** — canonical multi-package `form_payload`, `DocumentSeeder`, detailed proposals, project/intro.
- **v1** — draft-only: `list_catalog`, `create_draft_quotation`, connector-scoped `get_quotation`.
