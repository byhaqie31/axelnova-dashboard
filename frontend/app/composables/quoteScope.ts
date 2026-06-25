// Shared scope model for the pricing engine — consumed by the admin quotation
// builder via <QuoteScopeFields>. Scope inputs are now data-driven (admin-managed
// `service_scope_fields`): the builder holds a flat `scopeValues` dict keyed by
// each field's `field_key`; the engine prices them. No per-category hardcoding.

import type { ScopeField } from '~/composables/usePricingEngine'

export type ScopeValue = number | boolean | string

export interface QuoteScopeState {
  categoryKey: string
  packageKey: string
  /** Values keyed by scope-field `field_key` (e.g. { extra_page: 7, cms: true }). */
  scopeValues: Record<string, ScopeValue>
  addonKeys: string[]
  rush: boolean
}

export function defaultQuoteScope(): QuoteScopeState {
  return {
    categoryKey: '',
    packageKey: '',
    scopeValues: {},
    addonKeys: [],
    rush: false,
  }
}

/** Flatten the scope state into the form_payload shape the backend stores. */
export function scopeToPayload(s: QuoteScopeState): Record<string, unknown> {
  return { scope_values: s.scopeValues }
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
