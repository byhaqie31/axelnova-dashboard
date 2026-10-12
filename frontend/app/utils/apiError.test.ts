// Failed-request helpers (app/utils/apiError.ts) — pure, no Nuxt runtime.
import { describe, expect, it } from 'vitest'
import { apiError, FORM_ERROR_FALLBACK, formErrorMessage } from './apiError'

describe('apiError', () => {
  it('reads the status and body of a FetchError-shaped error', () => {
    expect(apiError({ status: 409, data: { message: 'Already sent.' } })).toEqual({ status: 409, data: { message: 'Already sent.' } })
  })

  it('is empty for anything that is not an object', () => {
    expect(apiError(undefined)).toEqual({})
    expect(apiError('boom')).toEqual({})
    expect(apiError(null)).toEqual({})
  })
})

describe('formErrorMessage', () => {
  it('joins every validation message first', () => {
    const e = { status: 422, data: { message: 'Validation failed.', errors: { email: ['Email is invalid.'], message: ['Too short.', 'Say more.'] } } }
    expect(formErrorMessage(e)).toBe('Email is invalid. Too short. Say more.')
  })

  it('falls back to the API message, then the default', () => {
    expect(formErrorMessage({ status: 429, data: { message: 'Too many requests.' } })).toBe('Too many requests.')
    expect(formErrorMessage(new Error('network down'))).toBe(FORM_ERROR_FALLBACK)
    expect(formErrorMessage(undefined, 'Try again later.')).toBe('Try again later.')
  })
})
