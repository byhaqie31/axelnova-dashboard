// Geometry for the hand-drawn SVG area chart on /admin/revenue. Pure functions,
// no DOM: the page measures its width and maps values to pixels, these turn the
// result into ticks and path data. Everything is unit-tested in areaChart.test.ts.

export interface Point { x: number, y: number }

/** One cubic Bézier segment of a monotone curve, in the same space as its points. */
export interface Segment { from: Point, c1: Point, c2: Point, to: Point }

/**
 * Clean y-axis ticks from 0 up to (never past) `max`, in 1 / 2 / 5 multiples of
 * a power of ten — the same step rule d3 uses — so the grid reads as RM 20k,
 * RM 40k, RM 60k and never RM 15,250. `count` is the target number of
 * intervals; the actual count is whatever the clean step produces.
 */
export function yTicks(max: number, count = 4): number[] {
  if (!(max > 0)) return [0]

  const raw = max / count
  const power = 10 ** Math.floor(Math.log10(raw))
  const error = raw / power
  const factor = error >= Math.sqrt(50) ? 10 : error >= Math.sqrt(10) ? 5 : error >= Math.sqrt(2) ? 2 : 1
  const step = factor * power

  const ticks: number[] = []
  for (let v = 0; v <= max + step * 1e-9; v += step) {
    ticks.push(Number(v.toFixed(10)))
  }
  return ticks
}

/**
 * Compact ringgit for axis ticks ONLY — "RM 20k", "RM 1.5M". A tick labels a
 * scale, not an amount; every real figure goes through formatMyr().
 */
export function formatCompactMyr(value: number): string {
  const abs = Math.abs(value)
  const sign = value < 0 ? '-' : ''
  if (abs >= 1_000_000) return `${sign}RM ${trim(abs / 1_000_000)}M`
  if (abs >= 1_000) return `${sign}RM ${trim(abs / 1_000)}k`
  return `${sign}RM ${trim(abs)}`
}

function trim(n: number): string {
  return String(Number(n.toFixed(1)))
}

/**
 * Monotone cubic interpolation (Fritsch–Carlson, as d3's curveMonotoneX): the
 * curve through every point never overshoots or undershoots its neighbours, so
 * a month at RM 0 stays on the baseline and a spike never implies a value the
 * data doesn't have. Points must be in ascending x.
 */
export function monotoneSegments(points: Point[]): Segment[] {
  const n = points.length
  if (n < 2) return []

  const segs: Segment[] = []
  if (n === 2) {
    const [a, b] = points as [Point, Point]
    return [{ from: a, c1: lerp(a, b, 1 / 3), c2: lerp(a, b, 2 / 3), to: b }]
  }

  let t0 = 0
  for (let i = 2; i < n; i++) {
    const p0 = points[i - 2]!
    const p1 = points[i - 1]!
    const p2 = points[i]!
    const t1 = slope3(p0, p1, p2)
    const start = i === 2 ? slope2(p0, p1, t1) : t0
    segs.push(cubic(p0, p1, start, t1))
    t0 = t1
  }

  const p0 = points[n - 2]!
  const p1 = points[n - 1]!
  segs.push(cubic(p0, p1, t0, slope2(p0, p1, t0)))

  return segs
}

/** SVG path data for the monotone curve: `M … C … C …`, or `M … L …` for two points. */
export function monotonePath(points: Point[]): string {
  const n = points.length
  if (n === 0) return ''
  const first = points[0]!
  if (n === 1) return `M ${fmt(first.x)} ${fmt(first.y)}`
  if (n === 2) {
    const last = points[1]!
    return `M ${fmt(first.x)} ${fmt(first.y)} L ${fmt(last.x)} ${fmt(last.y)}`
  }

  return monotoneSegments(points).reduce(
    (d, s) => `${d} C ${fmt(s.c1.x)} ${fmt(s.c1.y)}, ${fmt(s.c2.x)} ${fmt(s.c2.y)}, ${fmt(s.to.x)} ${fmt(s.to.y)}`,
    `M ${fmt(first.x)} ${fmt(first.y)}`,
  )
}

/** Close a line path down to the baseline so it can be filled as an area. */
export function closeArea(linePath: string, firstX: number, lastX: number, baselineY: number): string {
  if (!linePath) return ''
  return `${linePath} L ${fmt(lastX)} ${fmt(baselineY)} L ${fmt(firstX)} ${fmt(baselineY)} Z`
}

/** Index of the slot closest to `x` — the crosshair snaps to a month, never to a 2px line. */
export function nearestIndex(xs: number[], x: number): number {
  let best = -1
  let bestDist = Infinity
  xs.forEach((sx, i) => {
    const d = Math.abs(sx - x)
    if (d < bestDist) {
      bestDist = d
      best = i
    }
  })
  return best
}

// --- internals -------------------------------------------------------------

/** Tangent at the middle point, limited so the curve stays monotone. */
function slope3(p0: Point, p1: Point, p2: Point): number {
  const h0 = p1.x - p0.x
  const h1 = p2.x - p1.x
  const s0 = (p1.y - p0.y) / (h0 || (h1 < 0 ? -0 : 0))
  const s1 = (p2.y - p1.y) / (h1 || (h0 < 0 ? -0 : 0))
  const p = (s0 * h1 + s1 * h0) / (h0 + h1)
  return (Math.sign(s0) + Math.sign(s1)) * Math.min(Math.abs(s0), Math.abs(s1), 0.5 * Math.abs(p)) || 0
}

/** One-sided tangent at an end point, given the tangent at its neighbour. */
function slope2(p0: Point, p1: Point, t: number): number {
  const h = p1.x - p0.x
  return h ? (3 * (p1.y - p0.y) / h - t) / 2 : t
}

function cubic(from: Point, to: Point, t0: number, t1: number): Segment {
  const dx = (to.x - from.x) / 3
  return {
    from,
    c1: { x: from.x + dx, y: from.y + dx * t0 },
    c2: { x: to.x - dx, y: to.y - dx * t1 },
    to,
  }
}

function lerp(a: Point, b: Point, t: number): Point {
  return { x: a.x + (b.x - a.x) * t, y: a.y + (b.y - a.y) * t }
}

/** Two decimals is plenty for pixels and keeps the path short. */
function fmt(n: number): string {
  return String(Number(n.toFixed(2)))
}
