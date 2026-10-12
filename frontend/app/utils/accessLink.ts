// One-time access links (set-up / reset) — the pages behind them on the team,
// partner and admin surfaces share this copy and validation. Backend:
// GET/POST /api/v1/auth/access-links/{token} (Phase 2 of the email work).

export type AccessSurface = 'team' | 'partner' | 'admin'

export interface AccessLinkInfo {
  purpose: 'setup' | 'reset'
  surface: AccessSurface
  name: string | null
  email: string
  min_length: number
}

/** Partners sign in with a passcode; the team and the founder with a password. */
export function secretNoun(surface: AccessSurface): 'password' | 'passcode' {
  return surface === 'partner' ? 'passcode' : 'password'
}

export function accessLinkCopy(info: AccessLinkInfo): { title: string, intro: string, submit: string, done: string } {
  const noun = secretNoun(info.surface)
  const greeting = info.name ? `Hi ${info.name}, choose` : 'Choose'

  if (info.purpose === 'setup') {
    return {
      title: info.surface === 'partner' ? 'Set up your access' : `Set your ${noun}`,
      intro: `${greeting} a ${noun} for your account (${info.email}).`,
      submit: `Set ${noun}`,
      done: `Your ${noun} is set. Sign in with it now.`,
    }
  }

  return {
    title: `Choose a new ${noun}`,
    intro: `${greeting} a new ${noun} for your account (${info.email}).`,
    submit: `Save new ${noun}`,
    done: `Your new ${noun} is set. Sign in with it now.`,
  }
}

/** Null when acceptable; otherwise the message to show. */
export function validateSecret(secret: string, confirmation: string, minLength: number, noun: string): string | null {
  if (secret.length < minLength) return `Use at least ${minLength} characters.`
  if (secret !== confirmation) return `The two ${noun}s don’t match.`
  return null
}

export function loginPath(surface: AccessSurface): string {
  return surface === 'partner' ? '/partners/login' : surface === 'admin' ? '/admin/login' : '/team/login'
}
