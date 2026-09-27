import type { InjectionKey } from 'vue'

// Shared-element handoff between the homepage hero's pill nav and the public
// layout's floating header. They're the same glass pill in two places — the
// bottom of the hero card on `/`, the top of the viewport everywhere else — so
// instead of one vanishing while the other slides in, the header itself
// travels between the two spots. The layout owns the header element and runs
// the flight; HeroEpoch reports where its pill is. See docs/frontend/MOTION.md.

/** A pill position in viewport coordinates. */
export interface NavFlightRect {
  top: number
  centerX: number
  width: number
}

export interface NavFlightApi {
  /** Leaving `/`: the header launches from the hero pill's spot and glides up to its home at the top. */
  launchFrom: (from: NavFlightRect) => void
  /**
   * Arriving at `/` with the header on screen: it glides down into the hero
   * pill's resting spot, then `onLand` hands over to the pill in the same
   * frame the header hides. Returns false when there's no flight to run —
   * the caller then sets up as usual.
   */
  landOn: (target: NavFlightRect, onLand: () => void) => boolean
  /** Abort an in-progress landing (the hero unmounted mid-flight). */
  cancelLanding: () => void
}

export const NAV_FLIGHT_KEY: InjectionKey<NavFlightApi> = Symbol('nav-flight')

/** The layout's flight controls, or null outside the public layout. */
export function useNavFlight(): NavFlightApi | null {
  return inject(NAV_FLIGHT_KEY, null)
}
