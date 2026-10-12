import { describe, expect, it } from 'vitest'
import { formatPriceTag, formatPriceTagWithUnit } from './priceTag'

describe('formatPriceTag', () => {
  it('prints a whole-ringgit range with RM once, no decimals and an en dash', () => {
    expect(formatPriceTag({ min: 2500, max: 4000 })).toBe('RM 2,500 – 4,000')
    // The API sends decimal strings — "250.00" is still whole ringgit.
    expect(formatPriceTag({ min: '250.00', max: '450.00' })).toBe('RM 250 – 450')
  })

  it('keeps cents on both ends when either end has them', () => {
    expect(formatPriceTag({ min: 250.5, max: 450 })).toBe('RM 250.50 – 450.00')
  })

  it('prints an open-ended package as "From", never with a range', () => {
    expect(formatPriceTag({ min: 20000, max: null })).toBe('From RM 20,000')
    expect(formatPriceTag({ min: '30000.00' })).toBe('From RM 30,000')
  })

  it('collapses an equal min and max to one figure', () => {
    expect(formatPriceTag({ min: 2500, max: 2500 })).toBe('RM 2,500')
  })

  it('takes an indicative currency symbol', () => {
    expect(formatPriceTag({ min: 550, max: 900, symbol: '$' })).toBe('$550 – 900')
    expect(formatPriceTag({ min: 750, max: null, symbol: 'S$' })).toBe('From S$750')
  })

  it('appends the unit from data after the price', () => {
    expect(formatPriceTagWithUnit({ min: 2500, max: 4000, unit: 'per project' })).toBe('RM 2,500 – 4,000 per project')
    expect(formatPriceTagWithUnit({ min: 20000, max: null, unit: 'custom quote' })).toBe('From RM 20,000 custom quote')
    expect(formatPriceTagWithUnit({ min: 2500, max: 4000, unit: null })).toBe('RM 2,500 – 4,000')
  })
})
