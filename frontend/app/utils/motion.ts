// Motion design tokens — single source of truth for every animation.
// Tuned in the "Axel Nova Landing" prototype; do not re-tune ad hoc.
// Dashboard/admin/portal surfaces use dur.fast–0.5s (daily-use tools);
// the marketing site uses base–hero. See docs/frontend/MOTION.md.
export const MOTION = {
  dur: { fast: 0.3, base: 0.6, slow: 0.9, hero: 1.2 },
  ease: {
    out: 'power3.out',          // default for everything
    hero: 'expo.out',           // hero/signature moments only
    inout: 'power2.inOut',      // page transitions
    spring: 'elastic.out(1, 0.4)', // magnetic-button release only
    settle: 'power1.out',       // counters decelerating into a number
  },
  stagger: { tight: 0.08, base: 0.1, loose: 0.14 },
  reveal: { y: 52, start: 'top 85%' },
} as const
