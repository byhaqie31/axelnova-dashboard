import type Lenis from 'lenis'

// Landing on a #hash that lives on ANOTHER page (e.g. a brief package card's
// "+N more features" → /services/{slug}#{package}). Same-page hash links are
// handled by router.options.ts and the anchor-click handler in
// plugins/gsap.client.ts; this is for arriving on a new page:
//   - a full load      → the page:finish hook (gsap.client.ts)
//   - a client-side nav → the page transition's onEnter (app.vue), the first
//     moment the new page is in the DOM (with the out-in transition,
//     page:finish fires while the OLD page is still leaving)

/** Gap left above the target — clears the 48px header, matches same-page hash links. */
export const HASH_OFFSET = 64

/** The element the URL's #hash names, if it's on the page. */
export function hashTarget(): HTMLElement | null {
  try {
    const id = decodeURIComponent(window.location.hash.slice(1))
    return id ? document.getElementById(id) : null
  }
  catch {
    return null // malformed escape in the hash — treat as no target
  }
}

/**
 * The element's layout position in the document. Summed offsetTops, not
 * getBoundingClientRect: the new page is mid enter-transition (a translateY)
 * when this runs, and layout offsets ignore transforms.
 */
function documentTop(el: HTMLElement): number {
  let top = 0
  for (let node: HTMLElement | null = el; node; node = node.offsetParent as HTMLElement | null) {
    top += node.offsetTop
  }
  return top
}

/**
 * Jump (no animation) to the #hash target, or to the top when the URL has no
 * hash or nothing on the page matches it. Through Lenis when it owns scrolling,
 * so its internal position stays in sync.
 */
export function jumpToHashOrTop(lenis: Lenis | null): void {
  const anchor = hashTarget()
  const top = anchor ? Math.max(documentTop(anchor) - HASH_OFFSET, 0) : 0
  if (lenis) {
    // The page under Lenis just changed; re-measure before the jump, or the
    // target is clamped to the old page's scroll height.
    lenis.resize()
    lenis.scrollTo(top, { immediate: true, force: true })
  }
  else {
    window.scrollTo({ top, behavior: 'instant' })
  }
}
