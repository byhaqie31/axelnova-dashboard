// Dynamic sitemap entries: every published blog post. Fetched server-side from
// the backend over the docker-network base (runtimeConfig.apiBase). A backend
// hiccup yields an empty list rather than a broken sitemap.
export default defineSitemapEventHandler(async () => {
  const base = useRuntimeConfig().apiBase
  try {
    const res = await $fetch<{ data: { slug: string, updated_at: string | null }[] }>(`${base}/api/v1/blog/slugs`)
    return res.data.map(p => asSitemapUrl({ loc: `/blog/${p.slug}`, lastmod: p.updated_at ?? undefined }))
  }
  catch {
    return []
  }
})
