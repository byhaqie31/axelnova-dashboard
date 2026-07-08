/**
 * Axel Nova quotation MCP server (Cloudflare Worker).
 *
 * Exposes the tools that proxy the Laravel scoped connector API so Claude can
 * drive the quotation pipeline from a client brief. The Worker holds the scoped
 * bearer token (CONNECTOR_TOKEN) and adds it to every upstream call — Claude
 * never sees it. Access is gated by workers-oauth-provider (see auth.ts).
 *
 * Access model (v3), enforced by the Laravel token abilities (connector:read +
 * connector:draft, never cockpit):
 *   • READ everything  — list_catalog, list_quotations, get_quotation (ANY
 *     non-deleted quotation, whatever created it).
 *   • WRITE with a gate — create_draft_quotation, update_draft_quotation (any
 *     quotation while it is PRE-SEND; refused once sent).
 *   • DESTROY never     — there is NO delete tool; deletion is portal-only, by hand.
 */

import OAuthProvider from "@cloudflare/workers-oauth-provider";
import { McpAgent } from "agents/mcp";
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod";
import { apiFetch, type ApiResult, type Env } from "./api";
import { authApp } from "./auth";

/**
 * Contract version — bump on any change to the tool surface or its semantics, so a
 * session can tell which contract it's talking to (advertised as the MCP server
 * version in the initialize handshake). v3: read-open reads + lifecycle-gated update.
 */
const CONNECTOR_VERSION = "3.0.0";

const CATALOG_PATH = "/api/v1/connector/catalog";
const QUOTATIONS_PATH = "/api/v1/connector/quotations";
const DRAFT_PATH = "/api/v1/connector/quotations/draft";

/** Every lifecycle status (mirrors Quotation::STATUSES). */
const QUOTATION_STATUSES = ["draft", "sent", "accepted", "rejected", "expired"] as const;

/**
 * The shared draft input shape — create_draft_quotation and update_draft_quotation
 * take the SAME pricing basis (an update is a full re-specification of the quote).
 */
const draftInputShape = {
  client: z
    .object({
      name: z.string().describe("Client or company contact name."),
      email: z.string().describe("Client email — the client record is upserted by this address."),
      phone: z.string().nullable().optional(),
      company: z.string().nullable().optional(),
    })
    .describe("Who the quotation is for. name + email are required."),
  package_key: z
    .string()
    .nullable()
    .optional()
    .describe(
      "Single-package quote: a package key from list_catalog. Use null for a fully bespoke quote priced only from line_items. For multiple packages, use packages[] instead (not both).",
    ),
  modifiers: z
    .record(z.string(), z.union([z.boolean(), z.number(), z.string()]))
    .optional()
    .describe(
      "Only with the single top-level package_key. Map of modifier key → value: boolean for toggles, an integer for number/slider fields, or the option value for selects. Keys must be valid for the chosen package (see that package's `modifiers` in list_catalog).",
    ),
  addon_keys: z
    .array(z.string())
    .optional()
    .describe("Only with the single top-level package_key. Add-on keys from list_catalog.addons."),
  packages: z
    .array(
      z.object({
        package_key: z.string().describe("A package key from list_catalog."),
        modifiers: z
          .record(z.string(), z.union([z.boolean(), z.number(), z.string()]))
          .optional()
          .describe("Modifier keys valid for THIS package (see its `modifiers` in list_catalog)."),
        addon_keys: z.array(z.string()).optional().describe("Add-on keys from list_catalog.addons."),
      }),
    )
    .optional()
    .describe(
      "Multi-package quote: one entry per catalog package, each with its own modifiers + add-ons. The estimate sums all packages and the ETA is the longest. Use this OR the top-level package_key, never both. Omit for a bespoke quote.",
    ),
  rush: z
    .boolean()
    .optional()
    .describe("Rush delivery for the whole quote — always raises the price, and shortens the timeline for week/month ETAs."),
  line_items: z
    .array(
      z.object({
        label: z.string(),
        description: z.string().nullable().optional(),
        amount_myr: z.number().nonnegative(),
      }),
    )
    .optional()
    .describe(
      "Required (non-empty) when package_key is null — these ARE the bespoke quote (total = their sum). On a priced quote they are stored as extras for the founder and are NOT added to the engine estimate.",
    ),
  project: z
    .string()
    .optional()
    .describe(
      "Quotation project title shown on the PDF, e.g. 'Brand website — design & front-end build'. Optional; a sensible default is used if omitted.",
    ),
  intro: z
    .string()
    .optional()
    .describe("A one–two sentence lead-in shown under the project title on the PDF. Optional."),
  detailed: z
    .object({
      subtitle: z.string().optional().describe("Short subtitle under the title, e.g. 'Website quotation'."),
      deposit_pct: z.number().int().min(0).max(100).optional().describe("Deposit %, default 50."),
      sections: z
        .array(
          z.object({
            title: z.string().describe("Section heading, e.g. 'Design' or 'Build'."),
            rows: z.array(
              z.object({
                title: z.string(),
                detail: z.string().nullable().optional(),
                amount_myr: z.number().nonnegative(),
              }),
            ),
          }),
        )
        .describe("The priced scope, grouped into sections. The quote total is the SUM of every row's amount_myr."),
      included: z
        .array(
          z.object({
            eyebrow: z.string().optional(),
            items: z.array(z.string()).describe("Bullet points."),
            columns: z.union([z.literal(1), z.literal(2)]).optional(),
            note: z.string().optional(),
          }),
        )
        .optional()
        .describe("'What's included' tick-list groups."),
      options: z
        .array(
          z.object({
            badge: z.string().optional().describe("e.g. 'OPTION A'."),
            title: z.string(),
            sub: z.string().optional(),
            amount_myr: z.number().nonnegative(),
            was_myr: z.number().nonnegative().optional().describe("Strikethrough 'was' price."),
            price_note: z.string().optional().describe("e.g. 'one-time'."),
            recommended: z.boolean().optional().describe("Highlights this card as the recommended pick."),
          }),
        )
        .optional()
        .describe("Side-by-side option cards the client chooses between."),
      care: z
        .array(
          z.object({
            label: z.string(),
            detail: z.string().optional(),
            amount_myr: z.number().nonnegative(),
            period: z.enum(["month", "year"]).optional(),
          }),
        )
        .optional()
        .describe("Ongoing care / support plan rows."),
    })
    .optional()
    .describe(
      "A rich, self-priced DETAILED proposal (scope sections + What's included + option cards + care plan). Priced from the section row amounts — do NOT combine with package_key/packages/line_items.",
    ),
  assumptions: z
    .array(z.string())
    .optional()
    .describe("Every assumption you made about scope/price — for the founder to verify."),
  open_questions: z
    .array(z.string())
    .optional()
    .describe("Everything still to confirm with the client before sending."),
  notes: z.string().optional().describe("Any extra free-text context for the founder."),
} as const;

export class AxelNovaMCP extends McpAgent<Env> {
  server = new McpServer({
    name: "axelnova-quotations",
    version: CONNECTOR_VERSION,
  });

  async init(): Promise<void> {
    this.server.tool(
      "list_catalog",
      "Always call this FIRST to get valid package keys, modifier keys, and add-on keys before drafting a quotation. Returns the quotable packages (each with its price range, ETA, and the modifier keys it accepts), the global add-ons, the rush rules, and how to draft a bespoke (non-package) quote.",
      {},
      async () => this.passthrough(await apiFetch(this.env, CATALOG_PATH)),
    );

    this.server.tool(
      "list_quotations",
      [
        "READ-ONLY. Browse quotations, newest first — use this to find a quotation without knowing its reference code.",
        "Filter by status[] (any of draft/sent/accepted/rejected/expired; omit for all), q (matches name / email / reference code), and from/to (created-date range, ISO YYYY-MM-DD).",
        "Paginated: per_page defaults to 10, max 25; pass page to walk further.",
        "Returns SLIM rows (reference code, client, status, layout/package label, estimate range, dates, admin URL) — no full document or scope. Call get_quotation with a reference_code for the full detail.",
      ].join(" "),
      {
        status: z
          .array(z.enum(QUOTATION_STATUSES))
          .optional()
          .describe("Lifecycle statuses to include. Omit for every (non-deleted) quotation."),
        q: z.string().optional().describe("Search term matched against name, email, and reference_code."),
        from: z.string().optional().describe("Created on/after this date (ISO YYYY-MM-DD)."),
        to: z.string().optional().describe("Created on/before this date (ISO YYYY-MM-DD)."),
        page: z.number().int().min(1).optional().describe("1-based page number (default 1)."),
        per_page: z.number().int().min(1).max(25).optional().describe("Rows per page (default 10, max 25)."),
      },
      async (args) => {
        const params = new URLSearchParams();
        for (const s of args.status ?? []) params.append("status[]", s);
        if (args.q) params.set("q", args.q);
        if (args.from) params.set("from", args.from);
        if (args.to) params.set("to", args.to);
        if (args.page) params.set("page", String(args.page));
        if (args.per_page) params.set("per_page", String(args.per_page));
        const qs = params.toString();
        return this.passthrough(await apiFetch(this.env, qs ? `${QUOTATIONS_PATH}?${qs}` : QUOTATIONS_PATH));
      },
    );

    this.server.tool(
      "create_draft_quotation",
      [
        "Create a DRAFT quotation in Axel Nova from a client brief.",
        "Creates a DRAFT only — it never sends anything to the client; the founder reviews and delivers it.",
        "Use package_key: null with line_items for bespoke projects that don't fit a catalog package.",
        "When package_key is set, modifiers/addon_keys/rush price it through the same engine as the public quote funnel; any line_items are stored as extras and NOT added to that estimate.",
        "For a quote spanning several catalog packages, pass packages[] (each entry its own package_key + modifiers + addon_keys) INSTEAD of the top-level package_key — the estimate sums the packages and the ETA is the longest. rush is still one flag for the whole quote.",
        "For a rich, presentation-grade proposal (grouped scope sections + What's included + option cards + a care plan), pass the `detailed` object INSTEAD — it is self-priced from its section amounts and must not be combined with package_key/packages/line_items.",
        "project and intro set the document's title + lead-in on the PDF for any mode.",
        "Put every guess in assumptions and every unknown in open_questions so the founder can verify them.",
        "Call list_catalog first to get the valid keys. On a validation error, read the returned message — it lists the valid keys — and retry.",
      ].join(" "),
      draftInputShape,
      async (args) =>
        this.passthrough(await apiFetch(this.env, DRAFT_PATH, { method: "POST", body: JSON.stringify(args) })),
    );

    this.server.tool(
      "update_draft_quotation",
      [
        "Update an existing quotation that is still a PRE-SEND draft (status: draft) — identified by its reference_code. Any draft works, whoever created it (public funnel, admin, or this connector).",
        "Refused (422) once the quote has been sent/accepted/rejected/expired: the client has seen it, so a change is a manual admin revision, out of scope here.",
        "Takes the SAME input as create_draft_quotation — it fully re-specifies the pricing basis (package/packages/modifiers/add-ons/rush, or line_items, or a detailed proposal) and the client — so send the complete intended state, not a partial patch.",
        "It re-prices the estimate. The seeded document is regenerated from the new scope ONLY when it is still untouched (or you pass reseed_document: true); a document a human has edited by hand is preserved and only the estimate is re-priced (the response's document_reseeded tells you which happened).",
        "Pass reseed_document: true to force the document to be regenerated from the new scope even if it was hand-edited.",
        "Still a DRAFT-side action — it never sends anything to the client. Use list_catalog for valid keys and get_quotation to inspect the current state first.",
      ].join(" "),
      {
        reference_code: z
          .string()
          .describe("The AXNQ reference code of the quotation to update (from list_quotations or get_quotation)."),
        reseed_document: z
          .boolean()
          .optional()
          .describe(
            "Force the document to be regenerated from the new scope, replacing any hand-edited line items. Default false — an edited document is preserved and only the estimate is re-priced.",
          ),
        ...draftInputShape,
      },
      async ({ reference_code, ...body }) =>
        this.passthrough(
          await apiFetch(this.env, `${QUOTATIONS_PATH}/${encodeURIComponent(reference_code)}`, {
            method: "PUT",
            body: JSON.stringify(body),
          }),
        ),
    );

    this.server.tool(
      "get_quotation",
      "Read back ANY quotation by its reference code (e.g. AXNQ-2026-0007) — not only connector-created ones. Returns the full stored estimate, line items, assumptions, open questions, provenance (created_via / last_updated_via), and the admin URL. To browse or filter without a reference code, use list_quotations.",
      {
        reference_code: z
          .string()
          .describe("An AXNQ reference code (from create_draft_quotation, list_quotations, or the admin)."),
      },
      async ({ reference_code }) =>
        this.passthrough(
          await apiFetch(this.env, `${QUOTATIONS_PATH}/${encodeURIComponent(reference_code)}`),
        ),
    );
  }

  /**
   * Return the Laravel response body to Claude verbatim. A non-2xx status becomes
   * an MCP tool error, but the instructive body (valid keys, what to fix) still
   * reaches Claude so it can self-correct.
   */
  private passthrough(result: ApiResult) {
    return {
      content: [{ type: "text" as const, text: result.body }],
      ...(result.ok ? {} : { isError: true }),
    };
  }
}

export default new OAuthProvider({
  apiRoute: "/mcp",
  // Cast around the provider's handler types (its ExportedHandler wants a
  // required fetch, McpAgent.serve()/Hono expose an optional one) — the runtime
  // shapes match; this is the same escape hatch Cloudflare's MCP templates use.
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  apiHandler: AxelNovaMCP.serve("/mcp") as any,
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  defaultHandler: authApp as any,
  authorizeEndpoint: "/authorize",
  tokenEndpoint: "/token",
  clientRegistrationEndpoint: "/register",
});
