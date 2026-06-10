// Typed accessor for the GSAP/Lenis instances provided by plugins/gsap.client.ts,
// plus the shared reduced-motion flag. Client-side only — callers must guard
// with `import.meta.server` before using the returned instances.
export function useMotion() {
  const { $gsap, $ScrollTrigger, $SplitText, $lenis } = useNuxtApp() as unknown as {
    $gsap: typeof import('gsap').default
    $ScrollTrigger: typeof import('gsap/ScrollTrigger').ScrollTrigger
    $SplitText: typeof import('gsap/SplitText').SplitText
    $lenis: import('lenis').default | null
  }

  const reduced = import.meta.client
    && window.matchMedia('(prefers-reduced-motion: reduce)').matches

  return { gsap: $gsap, ScrollTrigger: $ScrollTrigger, SplitText: $SplitText, lenis: $lenis, reduced }
}
