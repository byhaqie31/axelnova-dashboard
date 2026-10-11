/**
 * Axel Nova MCP server (Cloudflare Worker).
 *
 * Exposes the tools that proxy the Laravel scoped connector API so Claude can
 * drive the quotation pipeline from a client brief and draft blog posts. The Worker holds the scoped
 * bearer token (CONNECTOR_TOKEN) and adds it to every upstream call — Claude
 * never sees it. Access is gated by workers-oauth-provider (see auth.ts).
 *
 * Access model (v5), enforced by the Laravel token abilities (connector:read +
 * connector:draft, never cockpit). The two abilities are UNIVERSAL — they cover
 * every module's read / draft-write routes; there is no per-module ability.
 *   • READ everything  — list_catalog, list_quotations, get_quotation (ANY
 *     non-deleted quotation, whatever created it, any revision);
 *     get_blog_guide, list_blog_posts, get_blog_post (ANY non-deleted post).
 *   • WRITE with a gate — create_draft_quotation, create_revision (a new DRAFT
 *     revision of a sent / accepted quote), update_draft_quotation (the
 *     quotation's DRAFT revision only); create_blog_draft, update_blog_draft
 *     (a post while it is a DRAFT; refused once published).
 *   • DESTROY never     — there is NO delete, publish, send, or accept tool;
 *     those are portal-only, by hand.
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
 * v4: blog drafts (guide / list / read / create / partial update of a draft).
 * v4.1: detailed.deposit_amount_myr (fixed deposit, wins over deposit_pct) and
 * detailed.payment_plan (lump_sum | instalment | partner) with the instalment
 * fields; get_quotation returns the derived `payment_plan` block.
 * v4.2: `locale` (en | bm) — the PDF's template-chrome language, settable on
 * create / update (any mode) and returned by get_quotation. Content is never
 * translated or language-detected.
 * v5: quotation REVISIONS — create_revision (a new draft revision with a
 * change_reason), update_draft_quotation targets the family's draft revision,
 * get_quotation returns family / current_revision / revisions (and takes an
 * optional revision number); detailed.phases (a lump sum billed by phase —
 * 40/40/20, 50/50, 20×5 …) and detailed.care_plan_monthly_myr.
 */
const CONNECTOR_VERSION = "5.0.0";

const CATALOG_PATH = "/api/v1/connector/catalog";
const QUOTATIONS_PATH = "/api/v1/connector/quotations";
const DRAFT_PATH = "/api/v1/connector/quotations/draft";
const BLOG_GUIDE_PATH = "/api/v1/connector/blog/guide";
const BLOG_POSTS_PATH = "/api/v1/connector/blog/posts";

/** Editorial formats (mirrors BlogPost::FORMATS). */
const BLOG_FORMATS = ["article", "guide", "tutorial", "case_study", "opinion", "news"] as const;

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
  locale: z
    .enum(["en", "bm"])
    .optional()
    .describe(
      "Language of the PDF's TEMPLATE CHROME only — section headings, table captions, row labels, the footer and the formatted dates. Default en; bm = Bahasa Melayu. Everything you write (project, intro, section / row titles, included items, option cards, care rows) prints exactly as you wrote it in either locale — nothing is translated or detected from the content. On update, omit it to keep the stored choice.",
    ),
  detailed: z
    .object({
      subtitle: z.string().optional().describe("Short subtitle under the title, e.g. 'Website quotation'."),
      deposit_pct: z
        .number()
        .int()
        .min(0)
        .max(100)
        .optional()
        .describe("Deposit %, default 50. Ignored for display when deposit_amount_myr is set (the fixed amount wins)."),
      deposit_amount_myr: z
        .number()
        .nonnegative()
        .optional()
        .describe(
          "FIXED deposit in ringgit (e.g. 2700). Wins over deposit_pct — use it whenever the agreed deposit is a round figure, not a whole-number percentage. The effective % is derived for display, never stored.",
        ),
      payment_plan: z
        .enum(["lump_sum", "instalment", "partner"])
        .optional()
        .describe(
          "lump_sum (default): deposit now, balance on completion — or, with `phases`, billed by phase (40/40/20 …). instalment: deposit on acceptance then instalment_months × instalment_amount_myr. partner: monthly partnership — deposit_amount_myr is the setup fee, then instalment_amount_myr monthly for instalment_months (default 24).",
        ),
      instalment_months: z
        .number()
        .int()
        .min(1)
        .max(120)
        .optional()
        .describe("instalment / partner: how many monthly payments follow the deposit (partner defaults to 24)."),
      instalment_amount_myr: z
        .number()
        .nonnegative()
        .optional()
        .describe(
          "instalment / partner: the monthly amount. deposit_amount_myr + months × this should equal the sum of the sections — a mismatch is accepted but reported back as a variance for the founder.",
        ),
      billing_day: z
        .number()
        .int()
        .min(1)
        .max(28)
        .optional()
        .describe("Day of the month each instalment is billed, 1–28. Default 20."),
      first_instalment_date: z
        .string()
        .regex(/^\d{4}-\d{2}-\d{2}$/)
        .optional()
        .describe("ISO date (YYYY-MM-DD) of the first instalment, e.g. 2026-11-20. Omit to start on the next billing day after the quote is issued."),
      includes_care_plan: z
        .boolean()
        .optional()
        .describe("instalment / partner: true when the monthly figure already includes the care plan (printed on the PDF)."),
      phases: z
        .array(
          z.object({
            label: z.string().optional().describe("What triggers this payment, e.g. 'On acceptance', 'At demo', 'At go-live' — printed as written."),
            pct: z.number().positive().max(100).describe("This phase's share of the total, in percent."),
          }),
        )
        .min(2)
        .max(10)
        .optional()
        .describe(
          "lump_sum only: bill the total in phases — ANY split that adds up to 100%, e.g. 40/40/20, 50/50, 20/20/20/20/20. Phase 1 is the up-front payment (it replaces deposit_pct / deposit_amount_myr); amounts round to the ringgit and the last phase takes the remainder. The PDF prints a phases table.",
        ),
      care_plan_monthly_myr: z
        .number()
        .nonnegative()
        .optional()
        .describe(
          "A SEPARATE monthly Care Plan subscription (RM / month), billed month by month on its own invoices — use this when the care plan is NOT folded into an instalment figure (includes_care_plan). Printed under the payment plan.",
        ),
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

/** One blog section — limits mirror BlogPostInput::sectionRules(). */
const blogSection = z.object({
  id: z
    .string()
    .max(12)
    .optional()
    .describe("The section's stable s_xxxxxx id. KEEP it when editing an existing section; omit it for a new one."),
  heading: z.string().min(1).max(120).describe("Section heading — the H2 and the 'On this page' anchor."),
  body_md: z
    .string()
    .max(20000)
    .optional()
    .describe("Section body in Markdown (bold, italic, links, ### subheadings, lists, > quotes, code). Raw HTML is stripped."),
  image_url: z
    .string()
    .max(500)
    .nullable()
    .optional()
    .describe("Optional in-section image. ONLY a URL the user supplied; https:// or a root-relative path."),
  image_alt: z.string().max(160).nullable().optional().describe("Required whenever image_url is set."),
  quote: z.string().max(500).nullable().optional().describe("Optional pull quote."),
  quote_by: z.string().max(80).nullable().optional().describe("Optional quote attribution."),
});

/**
 * Every writable blog field. All optional here — update_blog_draft is a partial
 * patch; create_blog_draft overrides `title` as required.
 */
const blogFieldsShape = {
  title: z.string().min(1).max(160).optional().describe("Post title, ≤ 160 characters."),
  slug: z
    .string()
    .max(120)
    .nullable()
    .optional()
    .describe("URL slug. Omit to generate it from the title (on create). On update, the slug only changes if you send it."),
  excerpt: z
    .string()
    .max(500)
    .nullable()
    .optional()
    .describe("The introduction / hook (≤ 500). Shown as the lede on the article and as the card text on the index. Inline Markdown only: **bold** and *italic*, sparingly — no links, headings or lists."),
  sections: z
    .array(blogSection)
    .max(40)
    .optional()
    .describe(
      "The ordered body sections. On update this REPLACES the whole list — read the post first and send the full edited list, keeping each existing section's id.",
    ),
  cover_image_url: z
    .string()
    .max(500)
    .nullable()
    .optional()
    .describe("Cover image. ONLY a URL the user supplied (e.g. an images.unsplash.com link) — never search for or invent one."),
  cover_image_alt: z
    .string()
    .max(160)
    .nullable()
    .optional()
    .describe("Required whenever there is a cover image: describe it for someone who can't see it."),
  format: z.enum(BLOG_FORMATS).optional().describe("Editorial format — the eyebrow before the date. Default 'article'."),
  category: z
    .string()
    .max(60)
    .nullable()
    .optional()
    .describe("One category. Prefer one already in use (get_blog_guide.categories)."),
  tags: z.array(z.string().max(40)).max(10).optional().describe("Up to 10 topic tags. Prefer ones already in use."),
  cta_heading: z.string().max(120).nullable().optional().describe("Closing call to action. Leave all cta_* empty to use the defaults."),
  cta_body: z.string().max(500).nullable().optional(),
  cta_label: z.string().max(60).nullable().optional(),
  cta_url: z.string().max(500).nullable().optional(),
  seo_title: z.string().max(70).nullable().optional().describe("Only when the title is too long for search results (≤ 70)."),
  seo_description: z.string().max(160).nullable().optional().describe("Search snippet (≤ 160). Falls back to the excerpt."),
} as const;

/** The drafting rules both blog write tools share (the two modes + image/CTA rules). */
const BLOG_WRITING_RULES = [
  "Two modes. (1) Asked to WRITE about a topic or brief: write in the founder's voice from get_blog_guide, following its structure — the excerpt is the hook, then 2–4 practical sections and a key takeaway.",
  "(2) Given the user's OWN text (pasted or from a file): KEEP THEIR WORDING VERBATIM — only arrange it (first heading or line → title, text before the first section heading → excerpt, each heading → a section) and fill what is missing (image alt text, format, category, tags, SEO). Rewrite only when asked, and list any change you made in your reply.",
  "Either way: never repeat the closing call to action inside a section (the post ends with its own CTA card); use ONLY image URLs the user supplied, and always write alt text for them.",
].join(" ");

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
        "One row per quotation (a revised quote is still ONE row): status is the quotation's overall status; revision_number is the revision it currently presents and open_revision a newer draft/sent revision in progress (status draft also finds accepted quotes with a draft revision open).",
        "Returns SLIM rows (reference code, client, status, revision, layout/package label, estimate range, dates, admin URL) — no full document or scope. Call get_quotation with a reference_code for the full detail.",
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
        "Deposit: detailed.deposit_pct (whole %) OR detailed.deposit_amount_myr (a fixed ringgit figure — wins over the pct, use it for any agreed round amount). Payment plan: detailed.payment_plan lump_sum (default) | instalment (deposit, then instalment_months × instalment_amount_myr on billing_day from first_instalment_date) | partner (setup fee + monthly × months, default 24). A lump sum can instead be billed by phase: detailed.phases — any split totalling 100% (40/40/20, 50/50, 20×5 …), phase 1 is the up-front payment. A separate monthly Care Plan: detailed.care_plan_monthly_myr. The PDF prints the schedule / phases; the response's payment_plan shows the derived figures and any variance from the section total.",
        "project and intro set the document's title + lead-in on the PDF for any mode.",
        "locale (en, default | bm) picks the language of the PDF's template chrome (headings, captions, labels, footer, dates) — never of the content, which prints as written. Set bm when the founder wants a Bahasa Melayu document.",
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
        "Update the DRAFT REVISION of a quotation — identified by its reference_code. A new quotation's R1 is a draft until sent; a sent or accepted quotation gets a draft revision (R2, R3 …) from create_revision. Any draft works, whoever created it (public funnel, admin, or this connector).",
        "Refused (422) when the quotation has no draft revision (it was sent / accepted / rejected / expired and no revision is open): call create_revision with a change_reason first, then update that draft. A sent or accepted revision itself is never edited — the client saw (or agreed to) it.",
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
      [
        "Read back ANY quotation by its reference code (e.g. AXNQ-2026-0007) — not only connector-created ones.",
        "A quotation keeps one reference for life; its terms live in numbered REVISIONS (R1, R2 …). `family` (overall status, current / open / draft revision numbers), `current_revision` (the accepted or latest terms) and `revisions` (number, status — draft / sent / accepted / superseded / rejected / expired / discarded — estimate, dates, change_reason) describe the whole quotation.",
        "The rest of the record (estimate, line items, payment_plan, assumptions, open questions, provenance, admin URL) is ONE revision, named in `revision`: by default the working one — the open draft / sent revision if any, else the current — or pass `revision` for a specific one.",
        "To browse or filter without a reference code, use list_quotations.",
      ].join(" "),
      {
        reference_code: z
          .string()
          .describe("An AXNQ reference code (from create_draft_quotation, list_quotations, or the admin)."),
        revision: z
          .number()
          .int()
          .min(1)
          .optional()
          .describe("Read this revision number (1 = R1). Omit for the working revision (open draft/sent, else current)."),
      },
      async ({ reference_code, revision }) =>
        this.passthrough(
          await apiFetch(
            this.env,
            `${QUOTATIONS_PATH}/${encodeURIComponent(reference_code)}${revision ? `?revision=${revision}` : ""}`,
          ),
        ),
    );

    this.server.tool(
      "create_revision",
      [
        "Open a NEW DRAFT REVISION of a quotation that was already sent or accepted — the way to change terms after the client has seen them (e.g. they switch payment plan).",
        "It copies the latest sent / accepted revision into a new draft (R2, R3 …) carrying your change_reason, which is printed on the new revision's PDF and shown on the order timeline. Then call update_draft_quotation to change the terms.",
        "Nothing is sent and nothing is accepted: the founder sends or accepts the revision in the admin. Accepting a revision of an ACCEPTED quotation supersedes the old terms on the SAME order (unpaid invoices voided, paid ones kept, the new schedule netted against what was paid).",
        "Revising a SENT revision withdraws it (the client gets the replacement; the founder can undo by discarding the new draft).",
        "Refused (422, naming it) while another revision is still draft or sent, and once the order is completed or cancelled. A brand-new quotation that has never been sent needs no revision — just update_draft_quotation.",
      ].join(" "),
      {
        reference_code: z.string().describe("The AXNQ reference code of the quotation to revise."),
        change_reason: z
          .string()
          .min(1)
          .max(500)
          .describe("Why the quotation is changing, in the founder's words — e.g. 'Client switched to the 40/40/20 plan'."),
        from_revision: z
          .number()
          .int()
          .min(1)
          .optional()
          .describe("Revise this revision number. Omit to revise the sent one, else the accepted one."),
      },
      async ({ reference_code, ...body }) =>
        this.passthrough(
          await apiFetch(this.env, `${QUOTATIONS_PATH}/${encodeURIComponent(reference_code)}/revisions`, {
            method: "POST",
            body: JSON.stringify(body),
          }),
        ),
    );

    // ── Blog ────────────────────────────────────────────────────────────────

    this.server.tool(
      "get_blog_guide",
      "Always call this FIRST before writing or revising a blog post. Returns the founder's voice and structure rules, the two drafting modes, the valid formats, the categories and tags already in use, the default closing call to action, the section shape, field limits, and the image rules.",
      {},
      async () => this.passthrough(await apiFetch(this.env, BLOG_GUIDE_PATH)),
    );

    this.server.tool(
      "list_blog_posts",
      [
        "READ-ONLY. Browse blog posts (drafts and published), newest updated first — use this to find a post's id.",
        "Filter by status (draft | published) and q (matches the title). Paginated: per_page defaults to 10, max 25.",
        "Returns SLIM rows (id, title, slug, status, format, category, short excerpt, dates, admin_url, public_url when published). Call get_blog_post for the full text.",
      ].join(" "),
      {
        status: z.enum(["draft", "published"]).optional().describe("Omit for both."),
        q: z.string().max(120).optional().describe("Search term matched against the title."),
        page: z.number().int().min(1).optional().describe("1-based page number (default 1)."),
        per_page: z.number().int().min(1).max(25).optional().describe("Rows per page (default 10, max 25)."),
      },
      async (args) => {
        const params = new URLSearchParams();
        if (args.status) params.set("status", args.status);
        if (args.q) params.set("q", args.q);
        if (args.page) params.set("page", String(args.page));
        if (args.per_page) params.set("per_page", String(args.per_page));
        const qs = params.toString();
        return this.passthrough(await apiFetch(this.env, qs ? `${BLOG_POSTS_PATH}?${qs}` : BLOG_POSTS_PATH));
      },
    );

    this.server.tool(
      "get_blog_post",
      "Read back ANY blog post (draft or published) by its id — the full editable record in Markdown (sections with their ids, cover, format, category, tags, CTA, SEO, status) plus admin_url and public_url. Always read a post before updating it.",
      {
        id: z.number().int().min(1).describe("The post id (from list_blog_posts or create_blog_draft)."),
      },
      async ({ id }) => this.passthrough(await apiFetch(this.env, `${BLOG_POSTS_PATH}/${id}`)),
    );

    this.server.tool(
      "create_blog_draft",
      [
        "Create a blog post as a DRAFT in Axel Nova. It is NEVER published — the founder previews it in the admin and publishes it by hand. Call get_blog_guide first.",
        BLOG_WRITING_RULES,
        "Returns the saved record with admin_url — give the founder that link to preview and publish. On a validation error, read the message (it names the field) and retry.",
      ].join(" "),
      {
        ...blogFieldsShape,
        title: z.string().min(1).max(160).describe("Post title, ≤ 160 characters. Required."),
      },
      async (args) =>
        this.passthrough(await apiFetch(this.env, BLOG_POSTS_PATH, { method: "POST", body: JSON.stringify(args) })),
    );

    this.server.tool(
      "update_blog_draft",
      [
        "Update a blog post that is still a DRAFT, by id. PARTIAL: send only the fields that change — everything else is kept. The slug only changes if you send slug.",
        "sections, if sent, replaces the whole list: call get_blog_post first and send the full edited list, keeping each existing section's id.",
        "Refused (422) once the post is published — its edits would go live without the founder's preview. Then tell the founder to unpublish it in the admin first, or offer to create a new draft instead.",
        BLOG_WRITING_RULES,
        "Never publishes anything.",
      ].join(" "),
      {
        id: z.number().int().min(1).describe("The post id (from list_blog_posts or get_blog_post)."),
        ...blogFieldsShape,
      },
      async ({ id, ...body }) =>
        this.passthrough(
          await apiFetch(this.env, `${BLOG_POSTS_PATH}/${id}`, { method: "PUT", body: JSON.stringify(body) }),
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
