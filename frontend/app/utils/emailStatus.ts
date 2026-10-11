// Email history panel helpers — the shape of GET /api/v1/admin/emails rows and
// how each status shows. Pure TypeScript (unit-tested in emailStatus.test.ts).

export type EmailStatus = 'sent' | 'delayed' | 'delivered' | 'bounced' | 'complained' | 'failed'

export interface EmailLogRow {
  id: number
  type: string
  type_label: string
  audience: 'client' | 'partner' | 'team' | 'admin'
  to_email: string
  to_name: string | null
  subject: string | null
  status: EmailStatus
  status_label: string
  status_detail: string | null
  route: string | null
  triggered_by: { id: number, name: string } | null
  sent_at: string | null
  status_changed_at: string | null
  created_at: string | null
  resend_url: string | null
}

// AdminStatusPill keys. "delivered" already means an order was handed over
// (purple), so a delivered EMAIL gets its own green key.
const PILL_KEYS: Record<EmailStatus, string> = {
  sent: 'sent',
  delayed: 'delayed',
  delivered: 'email_delivered',
  bounced: 'bounced',
  complained: 'complained',
  failed: 'failed',
}

export function emailPillKey(status: string): string {
  return PILL_KEYS[status as EmailStatus] ?? 'sent'
}

export function isEmailProblem(status: string): boolean {
  return status === 'bounced' || status === 'complained' || status === 'failed'
}

/** Who caused the send — a person, or the system (a public form, the scheduler). */
export function triggeredByLabel(triggeredBy: EmailLogRow['triggered_by']): string {
  return triggeredBy ? `by ${triggeredBy.name}` : 'Automatic'
}

/** Only the backup route is worth calling out — Resend is the norm. */
export function emailRouteNote(route: string | null): string | null {
  return route === 'hostinger' ? 'via Hostinger backup' : null
}

/** "11 Oct 2026, 8:46 am" — the emails' own date format, in Malaysia time. */
export function formatEmailTime(iso: string | null): string {
  if (!iso) return '—'
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return '—'

  const parts = new Intl.DateTimeFormat('en-GB', {
    timeZone: 'Asia/Kuala_Lumpur',
    day: 'numeric', month: 'short', year: 'numeric',
    hour: 'numeric', minute: '2-digit', hour12: true,
  }).formatToParts(d)
  const get = (type: Intl.DateTimeFormatPartTypes) => parts.find(p => p.type === type)?.value ?? ''

  return `${get('day')} ${get('month')} ${get('year')}, ${get('hour')}:${get('minute')} ${get('dayPeriod').toLowerCase()}`
}
