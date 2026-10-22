// The RSS 2.0 feed (/feed.xml) and llms.txt (/llms.txt) builders: pure
// string transforms over the backend's blog / catalogue JSON, so they are
// tested here in plain node. The feed is what Google Publisher Center,
// Samsung News and feed readers ingest — every item must be well-formed XML
// with absolute URLs, however odd the post's text is.
import { describe, expect, it } from 'vitest'
import { absolutize, buildLlmsTxt, buildRssFeed, cdata, escapeXml, FEED_ITEM_LIMIT, rfc822, type FeedPost } from './feed'

const SITE = 'https://axelnovaventures.com'
const DESCRIPTION = 'Axel Nova Ventures designs and builds websites in Kuala Lumpur.'

function post(overrides: Partial<FeedPost> = {}): FeedPost {
  return {
    slug: 'when-to-build-a-customer-portal',
    title: 'When to build a customer portal',
    excerpt: 'WhatsApp is convenient. Here is when a portal pays off.',
    cover_image_url: 'https://images.example.com/cover.png',
    cover_image_alt: 'A phone showing WhatsApp',
    category: 'Systems',
    tags: ['Customer portals', 'WhatsApp'],
    published_at: '2026-09-26T01:04:03.000000Z',
    updated_at: '2026-09-29T04:27:03.000000Z',
    sections: [
      { heading: 'Notice what keeps repeating', body_html: '<p>Do customers <strong>ask</strong> twice? <a href="/services/booking-portal">Portals</a> help.</p>', image_url: null, image_alt: null, quote: null, quote_by: null },
      { heading: 'Keep the human part', body_html: '<p>Keep WhatsApp for the conversation.</p>', image_url: '/previews/portal.jpg', image_alt: 'The portal', quote: 'Calm beats fast', quote_by: 'A client' },
    ],
    ...overrides,
  }
}

const feed = (posts: FeedPost[]) => buildRssFeed(posts, { siteUrl: SITE, description: DESCRIPTION, now: new Date('2026-10-21T00:00:00Z') })

describe('escapeXml', () => {
  it('escapes the five XML specials and drops control characters', () => {
    expect(escapeXml('Tom & "Jerry" <b>\'x\'</b>')).toBe('Tom &amp; &quot;Jerry&quot; &lt;b&gt;&#39;x&#39;&lt;/b&gt;')
    expect(escapeXml('a\u0000b\u0007c')).toBe('abc')
  })
})

describe('cdata', () => {
  it('wraps the text and splits an embedded ]]> so the section cannot close early', () => {
    expect(cdata('<p>hi</p>')).toBe('<![CDATA[<p>hi</p>]]>')
    expect(cdata('a ]]> b')).toBe('<![CDATA[a ]]]]><![CDATA[> b]]>')
  })
})

describe('rfc822', () => {
  it('formats an ISO timestamp as an RFC 822 date in GMT', () => {
    expect(rfc822('2026-09-26T01:04:03.000000Z')).toBe('Sat, 26 Sep 2026 01:04:03 GMT')
  })
})

describe('absolutize', () => {
  it('prefixes root-relative and bare-relative URLs with the site, leaves absolute ones alone', () => {
    expect(absolutize('/previews/portal.jpg', SITE)).toBe(`${SITE}/previews/portal.jpg`)
    expect(absolutize('previews/portal.jpg', SITE)).toBe(`${SITE}/previews/portal.jpg`)
    expect(absolutize('https://images.example.com/x.jpg', SITE)).toBe('https://images.example.com/x.jpg')
    expect(absolutize('//cdn.example.com/x.jpg', SITE)).toBe('https://cdn.example.com/x.jpg')
    expect(absolutize('#keep-the-human-part', SITE)).toBe('#keep-the-human-part')
    expect(absolutize('mailto:baihaqie@axelnova.tech', SITE)).toBe('mailto:baihaqie@axelnova.tech')
  })
})

describe('buildRssFeed', () => {
  it('is a valid RSS 2.0 channel with the atom self link, image and namespaces', () => {
    const xml = feed([post()])
    expect(xml.startsWith('<?xml version="1.0" encoding="UTF-8"?>')).toBe(true)
    expect(xml).toContain('<rss version="2.0"')
    expect(xml).toContain('xmlns:atom="http://www.w3.org/2005/Atom"')
    expect(xml).toContain('xmlns:content="http://purl.org/rss/1.0/modules/content/"')
    expect(xml).toContain('xmlns:dc="http://purl.org/dc/elements/1.1/"')
    expect(xml).toContain('xmlns:media="http://search.yahoo.com/mrss/"')
    expect(xml).toContain('<title>Axel Nova Ventures — Blog</title>')
    expect(xml).toContain(`<link>${SITE}/blog</link>`)
    expect(xml).toContain(`<description>${DESCRIPTION}</description>`)
    expect(xml).toContain('<language>en-my</language>')
    expect(xml).toContain(`<atom:link href="${SITE}/feed.xml" rel="self" type="application/rss+xml"/>`)
    expect(xml).toContain(`<image><url>${SITE}/axel_nova_logo.png</url>`)
    // lastBuildDate is the newest change in the feed, not the render time.
    expect(xml).toContain('<lastBuildDate>Tue, 29 Sep 2026 04:27:03 GMT</lastBuildDate>')
    expect(xml.trim().endsWith('</rss>')).toBe(true)
  })

  it('renders an item with permalink guid, RFC 822 pubDate, creator, categories and the plain excerpt', () => {
    const xml = feed([post()])
    expect(xml).toContain(`<link>${SITE}/blog/when-to-build-a-customer-portal</link>`)
    expect(xml).toContain(`<guid isPermaLink="true">${SITE}/blog/when-to-build-a-customer-portal</guid>`)
    expect(xml).toContain('<pubDate>Sat, 26 Sep 2026 01:04:03 GMT</pubDate>')
    expect(xml).toContain('<dc:creator>Ahmad Baihaqie</dc:creator>')
    expect(xml).toContain('<category>Systems</category>')
    expect(xml).toContain('<category>Customer portals</category>')
    expect(xml).toContain('<category>WhatsApp</category>')
    expect(xml).toContain('<description>WhatsApp is convenient. Here is when a portal pays off.</description>')
  })

  it('puts the whole article in content:encoded — cover first, then each section as an h2 + body, with absolute URLs', () => {
    const xml = feed([post()])
    const encoded = xml.slice(xml.indexOf('<content:encoded>'), xml.indexOf('</content:encoded>'))
    const cover = encoded.indexOf('<figure><img src="https://images.example.com/cover.png" alt="A phone showing WhatsApp"></figure>')
    const h2 = encoded.indexOf('<h2>Notice what keeps repeating</h2>')
    expect(cover).toBeGreaterThan(-1)
    expect(h2).toBeGreaterThan(cover)
    expect(encoded).toContain(`<a href="${SITE}/services/booking-portal">Portals</a>`)
    expect(encoded).toContain(`<figure><img src="${SITE}/previews/portal.jpg" alt="The portal"></figure>`)
    expect(encoded).toContain('<blockquote><p>Calm beats fast</p><cite>A client</cite></blockquote>')
    expect(encoded).toContain('<![CDATA[')
  })

  it('escapes text nodes and attributes, and guards ]]> inside the CDATA body', () => {
    const xml = feed([post({
      title: 'Tom & Jerry <3 "quotes"',
      excerpt: 'A & B',
      cover_image_alt: 'Say "cheese" & smile',
      tags: ['R&D'],
      sections: [{ heading: 'Odd & <ends>', body_html: '<p>closing ]]> early?</p>', image_url: null, image_alt: null, quote: null, quote_by: null }],
    })])
    expect(xml).toContain('<title>Tom &amp; Jerry &lt;3 &quot;quotes&quot;</title>')
    expect(xml).toContain('<description>A &amp; B</description>')
    expect(xml).toContain('<category>R&amp;D</category>')
    expect(xml).toContain('<h2>Odd &amp; &lt;ends&gt;</h2>')
    expect(xml).toContain('alt="Say &quot;cheese&quot; &amp; smile"')
    expect(xml).toContain(']]]]><![CDATA[>')
    expect(xml).not.toContain('<p>closing ]]> early?</p>')
  })

  it('adds media:content and media:thumbnail only when there is a cover, typed from the extension', () => {
    const png = feed([post()])
    expect(png).toContain('<media:content url="https://images.example.com/cover.png" medium="image" type="image/png"/>')
    expect(png).toContain('<media:thumbnail url="https://images.example.com/cover.png"/>')

    // A root-relative cover is absolutized; an unknown/missing extension defaults to JPEG.
    const relative = feed([post({ cover_image_url: '/previews/cover?v=2' })])
    expect(relative).toContain(`<media:content url="${SITE}/previews/cover?v=2" medium="image" type="image/jpeg"/>`)

    const none = feed([post({ cover_image_url: null, cover_image_alt: null })])
    expect(none).not.toContain('<media:')
    // No cover figure at the top; the in-section image figure still renders.
    expect(none).not.toContain('<![CDATA[<figure>')
    expect(none).toContain('<![CDATA[<h2>Notice what keeps repeating</h2>')
  })

  it('keeps the newest-first order it is given and caps the item count', () => {
    const posts = Array.from({ length: FEED_ITEM_LIMIT + 5 }, (_, i) => post({
      slug: `post-${i}`,
      title: `Post ${i}`,
      published_at: new Date(Date.UTC(2026, 0, 1 + (FEED_ITEM_LIMIT + 5 - i))).toISOString(),
      updated_at: null,
    }))
    const xml = feed(posts)
    const items = xml.match(/<item>/g) ?? []
    expect(items).toHaveLength(FEED_ITEM_LIMIT)
    expect(xml.indexOf('<title>Post 0</title>')).toBeLessThan(xml.indexOf('<title>Post 1</title>'))
    expect(xml).toContain('<title>Post 19</title>')
    expect(xml).not.toContain('<title>Post 20</title>')
  })

  it('renders a valid empty channel when there are no posts', () => {
    const xml = feed([])
    expect(xml).toContain('<channel>')
    expect(xml).not.toContain('<item>')
    expect(xml).toContain('<lastBuildDate>Wed, 21 Oct 2026 00:00:00 GMT</lastBuildDate>')
    expect(xml.trim().endsWith('</rss>')).toBe(true)
  })
})

describe('buildLlmsTxt', () => {
  const sources = {
    services: [{ slug: 'booking-portal', name: 'Booking & Customer Portal', description: 'Let customers book online.\n\nAny time.' }],
    projects: [{ slug: 'house-of-parfum', name: 'HouseOfParfum', description: 'Perfume e-commerce.' }],
    posts: [{ slug: 'when-to-build-a-customer-portal', title: 'When to build a customer portal', excerpt: 'WhatsApp is convenient.' }],
  }

  it('starts with the H1, a one-line summary, the facts and the fixed pages', () => {
    const txt = buildLlmsTxt(sources, { siteUrl: SITE })
    expect(txt.startsWith('# Axel Nova Ventures\n\n> ')).toBe(true)
    expect(txt).toContain('Kuala Lumpur')
    expect(txt).toContain('baihaqie@axelnova.tech')
    expect(txt).toContain('+60 18-317 3103')
    expect(txt).toContain('## Pages')
    expect(txt).toContain(`- [Quote](${SITE}/quote)`)
    expect(txt).toContain(`- [Contact](${SITE}/contact)`)
  })

  it('lists each source as "- [Name](absolute url): one-line description"', () => {
    const txt = buildLlmsTxt(sources, { siteUrl: SITE })
    expect(txt).toContain(`## Services\n\n- [Booking & Customer Portal](${SITE}/services/booking-portal): Let customers book online. Any time.`)
    expect(txt).toContain(`## Projects\n\n- [HouseOfParfum](${SITE}/projects/house-of-parfum): Perfume e-commerce.`)
    expect(txt).toContain(`## Blog\n\n- [When to build a customer portal](${SITE}/blog/when-to-build-a-customer-portal): WhatsApp is convenient.`)
  })

  it('drops a section whose source failed (null) and keeps the rest', () => {
    const txt = buildLlmsTxt({ ...sources, services: null, posts: [] }, { siteUrl: SITE })
    expect(txt).not.toContain('## Services')
    expect(txt).not.toContain('## Blog')
    expect(txt).toContain('## Projects')
    expect(txt).toContain('## Pages')
  })

  it('keeps every description on one line and clips long ones at a word boundary', () => {
    const long = 'word '.repeat(60).trim()
    const txt = buildLlmsTxt({ services: null, projects: null, posts: [{ slug: 'x', title: 'X', excerpt: long }] }, { siteUrl: SITE })
    const line = txt.split('\n').find(l => l.startsWith('- [X]'))!
    expect(line.length).toBeLessThan(220)
    expect(line.endsWith('…')).toBe(true)
  })
})
