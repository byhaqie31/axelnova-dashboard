// Shared scope model for the pricing engine — consumed by the admin quotation
// builder via <QuoteScopeFields>. Scope inputs are now data-driven (admin-managed
// `service_scope_fields`): the builder holds a flat `scopeValues` dict keyed by
// each field's `field_key`; the engine prices them. No per-category hardcoding.

import type { ScopeField } from '~/composables/usePricingEngine'

export type ScopeValue = number | boolean | string

// One package block in the multi-package builder. Rush is NOT here — it's a
// single quote-level flag (see the canonical form_payload: rush is top-level).
export interface QuoteScopeState {
  categoryKey: string
  packageKey: string
  /** Values keyed by scope-field `field_key` (e.g. { extra_page: 7, cms: true }). */
  scopeValues: Record<string, ScopeValue>
  addonKeys: string[]
}

export function defaultQuoteScope(): QuoteScopeState {
  return {
    categoryKey: '',
    packageKey: '',
    scopeValues: {},
    addonKeys: [],
  }
}

/** One entry of the canonical form_payload.packages[] shape. */
export interface NormalizedPackage {
  package_key: string
  scope_values: Record<string, ScopeValue>
  addon_keys: string[]
}

/**
 * TS port of the backend FormPayloadNormalizer — turn ANY stored form_payload
 * shape (new multi-package, current single scope_values, legacy flat, or MCP
 * connector) into the canonical packages[] the builder hydrates from. Rush is read
 * separately (fp.rush) since it's quote-level. Keep in sync with the PHP one.
 */
export function normalizePackages(
  fp: Record<string, any> | null | undefined,
  fallbackPackageKey?: string | null,
): NormalizedPackage[] {
  const p = fp ?? {}

  if (Array.isArray(p.packages) && p.packages.length) {
    return p.packages
      .map((x: any): NormalizedPackage => ({
        package_key: x?.package_key ?? '',
        scope_values: x?.scope_values ?? {},
        addon_keys: Array.isArray(x?.addon_keys) ? x.addon_keys : [],
      }))
      .filter((x: NormalizedPackage) => x.package_key)
  }

  const key = p.package_key ?? fallbackPackageKey ?? ''
  if (!key) return []

  return [{
    package_key: key,
    // New single-package rows store scope_values; older ones stored flat fields.
    scope_values: p.scope_values ?? legacyToScopeValues(p),
    addon_keys: Array.isArray(p.addon_keys) ? p.addon_keys : [],
  }]
}

/**
 * Fill any missing scope values with their field default (called when the
 * category's fields are known / change), so sliders start at their default and
 * the estimate is correct immediately. Existing values are left untouched.
 */
export function seedScopeDefaults(
  fields: ScopeField[],
  current: Record<string, ScopeValue>,
): Record<string, ScopeValue> {
  const next = { ...current }
  for (const f of fields) {
    if (next[f.field_key] === undefined && f.config.default !== undefined) {
      next[f.field_key] = f.config.default as ScopeValue
    }
  }
  return next
}

/**
 * Hydrate a pre–scope-builder draft (stored flat fields, no `scope_values`) into
 * the new `scopeValues` dict so legacy drafts stay editable. Keys mirror the
 * seeded scope fields. Unmapped legacy context (core_features, auth_methods) is
 * dropped — it was never priced.
 */
export function legacyToScopeValues(fp: Record<string, any>): Record<string, ScopeValue> {
  const v: Record<string, ScopeValue> = {}
  const num = (x: any) => Number(x)
  const bool = (x: any) => !!x

  if (fp.pages != null) v.extra_page = num(fp.pages)
  if (fp.cms != null) v.cms = bool(fp.cms)
  if (fp.booking_flow != null) v.booking_flow = bool(fp.booking_flow)
  if (Array.isArray(fp.languages)) v.extra_language = fp.languages.length || 1
  if (fp.modules != null) v.extra_module = num(fp.modules)
  if (fp.user_roles != null) v.user_roles = num(fp.user_roles)
  if (fp.real_time != null) v.real_time_features = bool(fp.real_time)
  if (fp.charts_complexity != null) v.charts_complexity = String(fp.charts_complexity)
  if (fp.screens_count != null) v.screens_count = num(fp.screens_count)
  if (fp.components_count != null) v.components_count = num(fp.components_count)
  if (fp.pages_count != null) v.pages_count = num(fp.pages_count)
  if (fp.design_system != null) v.design_system = bool(fp.design_system)
  if (fp.prototype != null) v.prototype = bool(fp.prototype)
  if (fp.state_management != null) v.state_management = bool(fp.state_management)
  if (fp.testing != null) v.testing = bool(fp.testing)
  if (fp.payment_method != null) v.payment_method = String(fp.payment_method)
  if (fp.admin_portal != null) v.admin_portal = bool(fp.admin_portal)

  return v
}
