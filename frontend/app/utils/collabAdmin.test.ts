import { describe, expect, it } from 'vitest'
import { isSitePath, partnerSinceLabel, servicesFromText, servicesToText } from './collabAdmin'

describe('servicesFromText', () => {
  it('splits lines, trims and drops blanks', () => {
    expect(servicesFromText(' Websites \n\nMobile apps\n  ')).toEqual(['Websites', 'Mobile apps'])
  })

  it('drops case-insensitive repeats, keeping the first spelling', () => {
    expect(servicesFromText('AI products\nai products\nWebsites')).toEqual(['AI products', 'Websites'])
  })

  it('round-trips with servicesToText', () => {
    expect(servicesFromText(servicesToText(['Websites', 'Mobile apps']))).toEqual(['Websites', 'Mobile apps'])
  })
})

describe('partnerSinceLabel', () => {
  it('shows the year only', () => {
    expect(partnerSinceLabel('2026-03-01')).toBe('Since 2026')
  })

  it('handles a missing date', () => {
    expect(partnerSinceLabel(null)).toBe('No start date')
  })
})

describe('isSitePath', () => {
  it('accepts a site path or empty', () => {
    expect(isSitePath('/logos/trlabs.svg')).toBe(true)
    expect(isSitePath('')).toBe(true)
  })

  it('rejects outside and protocol-relative URLs', () => {
    expect(isSitePath('https://cdn.example.com/x.svg')).toBe(false)
    expect(isSitePath('//evil.example.com/x.svg')).toBe(false)
  })
})
