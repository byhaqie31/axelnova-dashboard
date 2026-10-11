// Locale coverage + date formatting for the document renderer. Every key the
// default (en) locale defines must exist in bm (and vice versa), no string may
// be blank, and the schedule / header dates must use each locale's month names.
import { describe, expect, it } from 'vitest'
import { DOCUMENT_LOCALES, LOCALES, fmt, formatDate, strings } from './locales'

/** Flatten an object to "a.b.c" → value, recursing into plain objects/arrays. */
function flatten(value: unknown, prefix = ''): Record<string, unknown> {
  if (value !== null && typeof value === 'object') {
    return Object.entries(value as Record<string, unknown>).reduce<Record<string, unknown>>((acc, [k, v]) => {
      Object.assign(acc, flatten(v, prefix ? `${prefix}.${k}` : k))
      return acc
    }, {})
  }
  return { [prefix]: value }
}

describe('locale coverage', () => {
  it('ships exactly en and bm, with en as the fallback', () => {
    expect(DOCUMENT_LOCALES).toEqual(['en', 'bm'])
    expect(strings('bm')).toBe(LOCALES.bm)
    expect(strings('en')).toBe(LOCALES.en)
    expect(strings(undefined)).toBe(LOCALES.en)
    expect(strings(null)).toBe(LOCALES.en)
    expect(strings('fr')).toBe(LOCALES.en)
  })

  it('every key exists in both en and bm', () => {
    const en = flatten(LOCALES.en)
    const bm = flatten(LOCALES.bm)
    expect(Object.keys(bm).sort()).toEqual(Object.keys(en).sort())
    expect(Object.keys(en).length).toBeGreaterThan(80)
  })

  it('no string is blank and every placeholder in en is kept in bm', () => {
    const en = flatten(LOCALES.en)
    const bm = flatten(LOCALES.bm)
    for (const [key, value] of Object.entries(en)) {
      if (typeof value === 'function') continue
      expect(typeof value, key).toBe('string')
      expect((value as string).trim(), key).not.toBe('')
      expect((bm[key] as string).trim(), key).not.toBe('')
      const placeholders = (s: string) => (s.match(/\{\w+\}/g) ?? []).sort()
      expect(placeholders(bm[key] as string), key).toEqual(placeholders(value as string))
    }
  })

  it('names the twelve months in order, per locale', () => {
    expect(LOCALES.en.months).toEqual(['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'])
    expect(LOCALES.bm.months).toEqual(['Januari', 'Februari', 'Mac', 'April', 'Mei', 'Jun', 'Julai', 'Ogos', 'September', 'Oktober', 'November', 'Disember'])
  })

  it('words the billing day per locale', () => {
    expect(LOCALES.en.dayOfMonth(20)).toBe('20th')
    expect(LOCALES.en.dayOfMonth(1)).toBe('1st')
    expect(LOCALES.en.dayOfMonth(2)).toBe('2nd')
    expect(LOCALES.en.dayOfMonth(3)).toBe('3rd')
    expect(LOCALES.en.dayOfMonth(11)).toBe('11th')
    expect(LOCALES.en.dayOfMonth(12)).toBe('12th')
    expect(LOCALES.en.dayOfMonth(13)).toBe('13th')
    expect(LOCALES.en.dayOfMonth(21)).toBe('21st')
    expect(LOCALES.bm.dayOfMonth(20)).toBe('20 haribulan')
  })
})

describe('formatDate', () => {
  const iso = (m: number) => `2026-${String(m).padStart(2, '0')}-20`

  it('formats every month with the locale month name', () => {
    for (let m = 1; m <= 12; m++) {
      expect(formatDate(iso(m), LOCALES.en)).toBe(`20 ${LOCALES.en.months[m - 1]} 2026`)
      expect(formatDate(iso(m), LOCALES.bm)).toBe(`20 ${LOCALES.bm.months[m - 1]} 2026`)
    }
  })

  it('matches the schedule examples: same in English, BM names where they differ', () => {
    expect(formatDate('2026-11-20', LOCALES.en)).toBe('20 November 2026')
    expect(formatDate('2026-11-20', LOCALES.bm)).toBe('20 November 2026')
    expect(formatDate('2026-12-20', LOCALES.bm)).toBe('20 Disember 2026')
    expect(formatDate('2027-01-20', LOCALES.bm)).toBe('20 Januari 2027')
    expect(formatDate('2027-03-20', LOCALES.bm)).toBe('20 Mac 2027')
    expect(formatDate('2027-05-20', LOCALES.bm)).toBe('20 Mei 2027')
    expect(formatDate('2027-06-20', LOCALES.bm)).toBe('20 Jun 2027')
    expect(formatDate('2027-07-20', LOCALES.bm)).toBe('20 Julai 2027')
    expect(formatDate('2027-08-20', LOCALES.bm)).toBe('20 Ogos 2027')
    expect(formatDate('2027-10-20', LOCALES.bm)).toBe('20 Oktober 2027')
  })

  it('drops the leading zero of the day and passes non-ISO strings through', () => {
    expect(formatDate('2026-10-09', LOCALES.en)).toBe('9 October 2026')
    expect(formatDate('22 June 2026', LOCALES.bm)).toBe('22 June 2026')
    expect(formatDate(null, LOCALES.en)).toBe('')
    expect(formatDate(undefined, LOCALES.en)).toBe('')
  })
})

describe('fmt', () => {
  it('fills placeholders and leaves unknown ones alone', () => {
    expect(fmt('{months}-Month Plan · {amount}', { months: 12, amount: 'RM 970.00' })).toBe('12-Month Plan · RM 970.00')
    expect(fmt('{x} and {y}', { x: 1 })).toBe('1 and {y}')
  })
})
