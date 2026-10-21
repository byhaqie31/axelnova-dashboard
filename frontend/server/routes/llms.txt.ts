// GET /llms.txt — the site in one Markdown page for LLM-backed agents
// (https://llmstxt.org): who the studio is, then the live services, projects
// and posts as `- [Name](url): one line` entries, and the fixed public pages.
// The static facts live in server/utils/feed.ts (buildLlmsTxt) and mirror the
// homepage's ProfessionalService JSON-LD.
//
// Three backend sources, each independent: a failed fetch drops its section
// and the rest still renders (same stance as the sitemap source). Production
// caches it `swr: 300` and POST /_cache/purge clears it with the pages, so a
// new post or a service going Live shows on the next fetch. Lives in
// server/routes (not /api) because the VPS proxy sends /api/* to Laravel.

import { buildLlmsTxt, type FeedPost, type LlmsEntry, type LlmsPostEntry } from '../utils/feed'

/** The fields /llms.txt needs from GET /v1/services (ServiceCategoryResource). */
interface ApiService { slug: string, name: string, description_text?: string | null, description?: string | null }

/** The fields /llms.txt needs from GET /v1/projects (ProjectResource). */
interface ApiProject { slug: string, name: string, description?: string | null }

export default defineEventHandler(async (event) => {
  const base = useRuntimeConfig(event).apiBase

  // Null on failure — buildLlmsTxt drops that section.
  const source = async <T, R>(endpoint: string, map: (row: T) => R): Promise<R[] | null> => {
    try {
      const res = await $fetch<{ data: T[] }>(`${base}/api/v1/${endpoint}`)
      return Array.isArray(res?.data) ? res.data.map(map) : null
    }
    catch {
      return null
    }
  }

  const [services, projects, posts] = await Promise.all([
    // description_text is the plain rendering of the category's Markdown.
    source<ApiService, LlmsEntry>('services', s => ({ slug: s.slug, name: s.name, description: s.description_text ?? s.description ?? '' })),
    source<ApiProject, LlmsEntry>('projects', p => ({ slug: p.slug, name: p.name, description: p.description ?? '' })),
    source<FeedPost, LlmsPostEntry>('blog/feed', p => ({ slug: p.slug, title: p.title, excerpt: p.excerpt })),
  ])

  setHeader(event, 'Content-Type', 'text/plain; charset=utf-8')
  return buildLlmsTxt({ services, projects, posts }, { siteUrl: useRuntimeConfig(event).public.siteUrl })
})
