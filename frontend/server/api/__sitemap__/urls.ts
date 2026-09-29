// Dynamic sitemap entries — the pages that only exist as data: published blog
// posts, the service categories managed in the admin (/services/{slug}), and
// the projects (/projects/{slug}). Fetched server-side from the backend over
// the docker-network base (runtimeConfig.apiBase). Each source is independent:
// a backend hiccup drops that source's URLs rather than breaking the sitemap.

interface Slugged { slug: string, updated_at?: string | null }

export default defineSitemapEventHandler(async () => {
  const base = useRuntimeConfig().apiBase

  const urls = async (endpoint: string, path: string) => {
    try {
      const res = await $fetch<{ data: Slugged[] }>(`${base}/api/v1/${endpoint}`)
      return res.data.map(r => asSitemapUrl({ loc: `/${path}/${r.slug}`, lastmod: r.updated_at ?? undefined }))
    }
    catch {
      return []
    }
  }

  const sources = await Promise.all([
    urls('blog/slugs', 'blog'),
    urls('services', 'services'),
    urls('projects', 'projects'),
  ])

  return sources.flat()
})
