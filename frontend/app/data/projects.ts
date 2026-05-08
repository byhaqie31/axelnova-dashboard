// Project shape used by ProjectCard + public pages.
// The runtime data now lives in the `projects` DB table — public pages fetch
// from `/api/v1/projects` and reshape API responses into this type.

export type ProjectStatus = 'live' | 'soon' | 'wip' | 'planning'

export interface Project {
  id: string
  name: string
  description: string
  longDescription: string
  status: ProjectStatus
  url?: string
  repo?: string
  tags: string[]
  stack: string[]
  featured: boolean
}
