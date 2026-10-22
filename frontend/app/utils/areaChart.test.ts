import { describe, expect, it } from 'vitest'
import { closeArea, formatCompactMyr, monotonePath, monotoneSegments, nearestIndex, yTicks } from './areaChart'

describe('yTicks', () => {
  it('steps in clean 1 / 2 / 5 multiples and never passes the ceiling', () => {
    expect(yTicks(61000)).toEqual([0, 20000, 40000, 60000])
    expect(yTicks(41300)).toEqual([0, 10000, 20000, 30000, 40000])
    expect(yTicks(950)).toEqual([0, 200, 400, 600, 800])
    expect(yTicks(110100)).toEqual([0, 20000, 40000, 60000, 80000, 100000])
  })

  it('is a lone baseline when there is nothing to scale', () => {
    expect(yTicks(0)).toEqual([0])
    expect(yTicks(-5)).toEqual([0])
  })

  it('honours the requested density', () => {
    expect(yTicks(61000, 8)).toEqual([0, 10000, 20000, 30000, 40000, 50000, 60000])
  })
})

describe('formatCompactMyr', () => {
  it('abbreviates thousands and millions for axis ticks only', () => {
    expect(formatCompactMyr(0)).toBe('RM 0')
    expect(formatCompactMyr(800)).toBe('RM 800')
    expect(formatCompactMyr(1500)).toBe('RM 1.5k')
    expect(formatCompactMyr(20000)).toBe('RM 20k')
    expect(formatCompactMyr(1_000_000)).toBe('RM 1M')
    expect(formatCompactMyr(2_500_000)).toBe('RM 2.5M')
  })
})

describe('monotonePath', () => {
  it('is empty with no points and a bare move with one', () => {
    expect(monotonePath([])).toBe('')
    expect(monotonePath([{ x: 10, y: 20 }])).toBe('M 10 20')
  })

  it('joins two points with a straight segment', () => {
    expect(monotonePath([{ x: 0, y: 100 }, { x: 50, y: 20 }])).toBe('M 0 100 L 50 20')
  })

  it('emits one cubic per gap, starting from the first point', () => {
    const d = monotonePath([{ x: 0, y: 100 }, { x: 50, y: 20 }, { x: 100, y: 60 }])
    expect(d.startsWith('M 0 100 C ')).toBe(true)
    expect(d.match(/ C /g)).toHaveLength(2)
    expect(d.endsWith(' 100 60')).toBe(true)
  })

  it('never overshoots a month: every curve stays between its two endpoints', () => {
    // The Revenue shape that broke a naive spline: long flat zeros, a spike, a
    // dip, more zeros, a bigger spike. Y is screen space (baseline at 200).
    const values = [0, 0, 0, 0, 0, 0, 0, 41300, 7800, 0, 0, 61000]
    const points = values.map((v, i) => ({ x: i * 40, y: 200 - (v / 61000) * 200 }))

    for (const seg of monotoneSegments(points)) {
      const lo = Math.min(seg.from.y, seg.to.y) - 1e-6
      const hi = Math.max(seg.from.y, seg.to.y) + 1e-6
      for (let t = 0; t <= 1.0001; t += 0.05) {
        const y = bezierY(seg.from.y, seg.c1.y, seg.c2.y, seg.to.y, t)
        expect(y).toBeGreaterThanOrEqual(lo)
        expect(y).toBeLessThanOrEqual(hi)
      }
    }
  })

  it('keeps a flat run perfectly flat', () => {
    const points = [0, 1, 2, 3].map(i => ({ x: i * 40, y: 200 }))
    for (const seg of monotoneSegments(points)) {
      expect(seg.c1.y).toBe(200)
      expect(seg.c2.y).toBe(200)
    }
  })
})

describe('closeArea', () => {
  it('drops to the baseline at both ends and closes', () => {
    expect(closeArea('M 0 100 L 50 20', 0, 50, 200)).toBe('M 0 100 L 50 20 L 50 200 L 0 200 Z')
  })

  it('stays empty for an empty line', () => {
    expect(closeArea('', 0, 50, 200)).toBe('')
  })
})

describe('nearestIndex', () => {
  const xs = [0, 40, 80, 120]

  it('snaps to the closest slot, including the edges', () => {
    expect(nearestIndex(xs, -30)).toBe(0)
    expect(nearestIndex(xs, 19)).toBe(0)
    expect(nearestIndex(xs, 21)).toBe(1)
    expect(nearestIndex(xs, 101)).toBe(3)
    expect(nearestIndex(xs, 500)).toBe(3)
  })

  it('is -1 with nothing to snap to', () => {
    expect(nearestIndex([], 10)).toBe(-1)
  })
})

/** Cubic Bézier y(t) for one segment — the shape the browser draws for a `C`. */
function bezierY(y0: number, y1: number, y2: number, y3: number, t: number): number {
  const u = 1 - t
  return u * u * u * y0 + 3 * u * u * t * y1 + 3 * u * t * t * y2 + t * t * t * y3
}
