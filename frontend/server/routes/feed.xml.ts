// GET /feed.xml — RSS 2.0 of the latest 20 published posts, full text.
//
// Subscribed to by feed readers, and the technical prerequisite for Google
// Publisher Center (Discover "Follow") and Samsung News — both ingest a
// standard RSS feed with Media RSS covers. Fetched server-side from the
// backend over the docker-network base (runtimeConfig.apiBase), like the
// sitemap source. A backend hiccup yields a VALID empty channel (200) rather
// than a 500: an aggregator that sees an error may drop the subscription,
// while an empty channel just means "nothing new". In production the route
// is `swr: 300` (nuxt.config.ts) and POST /_cache/purge clears it with the
// pages, so a publish shows on the next fetch. Lives in server/routes (not
// /api) because the VPS proxy sends /api/* to Laravel.

import { buildRssFeed, SITE_DESCRIPTION, type FeedPost } from '../utils/feed'

export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig(event)

  const posts = await $fetch<{ data: FeedPost[] }>(`${config.apiBase}/api/v1/blog/feed`)
    .then(res => (Array.isArray(res?.data) ? res.data : []))
    .catch((): FeedPost[] => [])

  setHeader(event, 'Content-Type', 'application/rss+xml; charset=utf-8')
  return buildRssFeed(posts, { siteUrl: config.public.siteUrl, description: SITE_DESCRIPTION })
})
