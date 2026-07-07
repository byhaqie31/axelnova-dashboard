/**
 * Axel Nova quotation MCP server (Cloudflare Worker).
 *
 * Exposes three tools that proxy the Laravel scoped connector API so Claude can
 * DRAFT quotations from a client brief. The Worker holds the scoped bearer token
 * (CONNECTOR_TOKEN) and adds it to every upstream call — Claude never sees it.
 * Access is gated by workers-oauth-provider (see auth.ts); the surface is
 * draft-only because the underlying token only carries connector:read +
 * connector:draft.
 */

import OAuthProvider from "@cloudflare/workers-oauth-provider";
import { McpAgent } from "agents/mcp";
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod";
import { apiFetch, type ApiResult, type Env } from "./api";
import { authApp } from "./auth";

const CATALOG_PATH = "/api/v1/connector/catalog";
const DRAFT_PATH = "/api/v1/connector/quotations/draft";

export class AxelNovaMCP extends McpAgent<Env> {
  server = new McpServer({
    name: "axelnova-quotations",
    version: "1.0.0",
  });

  async init(): Promise<void> {
    this.server.tool(
      "list_catalog",
      "Always call this FIRST to get valid package keys, modifier keys, and add-on keys before drafting a quotation. Returns the quotable packages (each with its price range, ETA, and the modifier keys it accepts), the global add-ons, the rush rules, and how to draft a bespoke (non-package) quote.",
      {},
      async () => this.passthrough(await apiFetch(this.env, CATALOG_PATH)),
    );

    this.server.tool(
      "create_draft_quotation",
      [
        "Create a DRAFT quotation in Axel Nova from a client brief.",
        "Creates a DRAFT only — it never sends anything to the client; the founder reviews and delivers it.",
        "Use package_key: null with line_items for bespoke projects that don't fit a catalog package.",
        "When package_key is set, modifiers/addon_keys/rush price it through the same engine as the public quote funnel; any line_items are stored as extras and NOT added to that estimate.",
        "Put every guess in assumptions and every unknown in open_questions so the founder can verify them.",
        "Call list_catalog first to get the valid keys. On a validation error, read the returned message — it lists the valid keys — and retry.",
      ].join(" "),
      {
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
          .describe("A package key from list_catalog, or null for a fully bespoke quote priced only from line_items."),
        modifiers: z
          .record(z.string(), z.union([z.boolean(), z.number(), z.string()]))
          .optional()
          .describe(
            "Only when package_key is set. Map of modifier key → value: boolean for toggles, an integer for number/slider fields, or the option value for selects. Keys must be valid for the chosen package (see that package's `modifiers` in list_catalog).",
          ),
        addon_keys: z
          .array(z.string())
          .optional()
          .describe("Only when package_key is set. Add-on keys from list_catalog.addons."),
        rush: z
          .boolean()
          .optional()
          .describe("Rush delivery — always raises the price, and shortens the timeline for week/month ETAs."),
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
        assumptions: z
          .array(z.string())
          .optional()
          .describe("Every assumption you made about scope/price — for the founder to verify."),
        open_questions: z
          .array(z.string())
          .optional()
          .describe("Everything still to confirm with the client before sending."),
        notes: z.string().optional().describe("Any extra free-text context for the founder."),
      },
      async (args) =>
        this.passthrough(await apiFetch(this.env, DRAFT_PATH, { method: "POST", body: JSON.stringify(args) })),
    );

    this.server.tool(
      "get_quotation",
      "Read back a connector-created draft quotation by its reference code (e.g. AXNQ-2026-0007) — returns the stored estimate, line items, assumptions, open questions, and the admin URL. Only quotations created via this connector are readable.",
      {
        reference_code: z
          .string()
          .describe("The AXNQ reference code returned by create_draft_quotation."),
      },
      async ({ reference_code }) =>
        this.passthrough(
          await apiFetch(this.env, `/api/v1/connector/quotations/${encodeURIComponent(reference_code)}`),
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
