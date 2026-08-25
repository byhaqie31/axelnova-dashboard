// Project shape used by ProjectCard + public pages.
// The runtime data now lives in the `projects` DB table — public pages fetch
// from `/api/v1/projects` and reshape API responses into this type.

export type ProjectStatus = 'live' | 'soon' | 'wip' | 'planning'

export interface Project {
  id: string
  /** numeric DB id — keys likes (routing still uses the slug `id`) */
  dbId?: number
  likes?: number
  name: string
  description: string
  longDescription: string
  status: ProjectStatus
  url?: string
  repo?: string
  /**
   * Preview image for the card's browser-window viewport — maps to the API's
   * `cover_image_url`. Either a self-hosted capture under `/previews/<slug>.webp`
   * or an absolute URL. Set it: it renders during SSR, so the card is never
   * blank. Without it the card falls back to a live mShots screenshot, which
   * generates asynchronously and can stay empty for seconds or forever.
   */
  coverImage?: string
  tags: string[]
  stack: string[]
  featured: boolean
}
