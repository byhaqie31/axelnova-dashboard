// Email history panel helpers (app/utils/emailStatus.ts) — pure, no Nuxt runtime.
import { describe, expect, it } from 'vitest'
import { emailPillKey, emailRouteNote, formatEmailTime, isEmailProblem, triggeredByLabel } from './emailStatus'

describe('emailPillKey', () => {
  it('maps each email status to its AdminStatusPill key', () => {
    expect(emailPillKey('sent')).toBe('sent')
    expect(emailPillKey('delayed')).toBe('delayed')
    // "delivered" already means an order was handed over (purple) — emails get their own green key
    expect(emailPillKey('delivered')).toBe('email_delivered')
    expect(emailPillKey('bounced')).toBe('bounced')
    expect(emailPillKey('complained')).toBe('complained')
    expect(emailPillKey('failed')).toBe('failed')
  })

  it('falls back to sent for an unknown status', () => {
    expect(emailPillKey('opened')).toBe('sent')
  })
})

describe('isEmailProblem', () => {
  it('flags bounces, spam complaints and failures only', () => {
    expect(['bounced', 'complained', 'failed'].every(isEmailProblem)).toBe(true)
    expect(['sent', 'delayed', 'delivered'].some(isEmailProblem)).toBe(false)
  })
})

describe('emailRouteNote', () => {
  it('only calls out the Hostinger backup route', () => {
    expect(emailRouteNote('hostinger')).toBe('via Hostinger backup')
    expect(emailRouteNote('resend')).toBeNull()
    expect(emailRouteNote(null)).toBeNull()
  })
})

describe('formatEmailTime', () => {
  it('reads like the emails themselves, in Malaysia time', () => {
    expect(formatEmailTime('2026-10-11T00:46:00Z')).toBe('11 Oct 2026, 8:46 am')
  })

  it('handles a missing time', () => {
    expect(formatEmailTime(null)).toBe('—')
  })
})

describe('triggeredByLabel', () => {
  it('names the person who sent it, or calls it automatic', () => {
    expect(triggeredByLabel({ id: 1, name: 'Qie' })).toBe('by Qie')
    expect(triggeredByLabel(null)).toBe('Automatic')
  })
})
