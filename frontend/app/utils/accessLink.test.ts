// Access-link pages helpers (app/utils/accessLink.ts) — pure, no Nuxt runtime.
import { describe, expect, it } from 'vitest'
import { accessLinkCopy, loginPath, secretNoun, validateSecret, type AccessLinkInfo } from './accessLink'

const team: AccessLinkInfo = { purpose: 'setup', surface: 'team', name: 'Danial', email: 'danial@example.com', min_length: 12 }
const partner: AccessLinkInfo = { purpose: 'reset', surface: 'partner', name: null, email: 'farid@example.com', min_length: 10 }

describe('secretNoun', () => {
  it('is a passcode for partners and a password everywhere else', () => {
    expect(secretNoun('partner')).toBe('passcode')
    expect(secretNoun('team')).toBe('password')
    expect(secretNoun('admin')).toBe('password')
  })
})

describe('accessLinkCopy', () => {
  it('greets a new teammate by name and names the account', () => {
    const copy = accessLinkCopy(team)
    expect(copy.title).toBe('Set your password')
    expect(copy.intro).toBe('Hi Danial, choose a password for your account (danial@example.com).')
    expect(copy.submit).toBe('Set password')
  })

  it('handles a partner reset without a name', () => {
    const copy = accessLinkCopy(partner)
    expect(copy.title).toBe('Choose a new passcode')
    expect(copy.intro).toBe('Choose a new passcode for your account (farid@example.com).')
    expect(copy.submit).toBe('Save new passcode')
    expect(copy.done).toBe('Your new passcode is set. Sign in with it now.')
  })
})

describe('validateSecret', () => {
  it('enforces the minimum length the link asks for', () => {
    expect(validateSecret('short', 'short', 10, 'passcode')).toBe('Use at least 10 characters.')
  })

  it('requires both entries to match', () => {
    expect(validateSecret('a-long-passphrase', 'a-long-passphrasf', 12, 'password')).toBe('The two passwords don’t match.')
  })

  it('accepts a valid, confirmed secret', () => {
    expect(validateSecret('a-long-passphrase', 'a-long-passphrase', 12, 'password')).toBeNull()
  })
})

describe('loginPath', () => {
  it('sends each surface back to its own sign-in', () => {
    expect(loginPath('team')).toBe('/team/login')
    expect(loginPath('partner')).toBe('/partners/login')
    expect(loginPath('admin')).toBe('/admin/login')
  })
})
