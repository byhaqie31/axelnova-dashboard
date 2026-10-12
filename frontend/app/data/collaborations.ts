// Studio-partner shapes for the /collaborations pages. The runtime data lives
// in the `collaboration_partners` table (seeded — see
// docs/global/COLLABORATIONS.md) and is served by `/api/v1/collaborations`.
// Not to be confused with the referral partners behind /partners.

export type CollaborationMode = 'co_build' | 'subcontract' | 'referral'

export interface CollaborationProject {
  id: number
  slug: string
  name: string
  description: string
  long_description: string
  status: 'live' | 'soon' | 'wip' | 'planning'
  url: string | null
  repo: string | null
  cover_image_url: string | null
  tags: string[]
  stack: string[]
  featured: boolean
  likes_count: number
  collaboration: { mode: CollaborationMode, role_note: string | null }
}

export interface CollaborationPartner {
  slug: string
  name: string
  tagline: string | null
  summary: string
  /** Plain text; paragraphs separated by a blank line. */
  story: string | null
  logo_url: string | null
  symbol_url: string | null
  website_url: string | null
  founder_name: string | null
  founder_role: string | null
  location: string | null
  services: string[]
  /** YYYY-MM-DD */
  partner_since: string | null
  /** Listing only — public shared projects. */
  projects_count?: number
  /** Detail only — public shared projects. */
  projects?: CollaborationProject[]
}

export const collaborationModeLabels: Record<CollaborationMode, string> = {
  co_build: 'Co-build',
  subcontract: 'Subcontract',
  referral: 'Referral',
}

// ── Admin (Partners › Collaborations — /api/v1/admin/collaboration-partners) ──

/** Why the public site won't show a shared project (server-side rule, spelled out). */
export type SharedProjectHiddenReason = 'partner_hidden' | 'credit_off' | 'project_inactive'

export interface AdminSharedProject {
  id: number
  slug: string
  name: string
  /** The project's own "show on site" switch. */
  active: boolean
  mode: CollaborationMode
  role_note: string | null
  /** The link's "show publicly" switch. */
  credit_public: boolean
  public: boolean
  hidden_reason: SharedProjectHiddenReason | null
}

export interface AdminCollaborationPartnerRow {
  id: number
  slug: string
  name: string
  tagline: string | null
  logo_url: string | null
  symbol_url: string | null
  active: boolean
  partner_since: string | null
  sort_order: number
  projects_total: number
  projects_public: number
  enquiries_open: number
  enquiries_new: number
}

export interface AdminCollaborationPartner extends AdminCollaborationPartnerRow {
  summary: string
  story: string | null
  website_url: string | null
  founder_name: string | null
  founder_role: string | null
  location: string | null
  services: string[]
  updated_at: string | null
  projects: AdminSharedProject[]
}

export const sharedProjectHiddenReasonLabels: Record<SharedProjectHiddenReason, string> = {
  partner_hidden: 'Partner is hidden',
  credit_off: 'Not shown publicly',
  project_inactive: 'Project is off the site',
}
