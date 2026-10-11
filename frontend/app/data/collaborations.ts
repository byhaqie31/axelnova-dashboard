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
