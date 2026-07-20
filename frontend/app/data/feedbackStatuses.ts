/**
 * Feedback review lifecycle + tone tokens. Single source of truth for the
 * admin feedback pill pickers, the index status filter, and any read-only
 * badge. Publishing is consent-gated server-side — see
 * docs/global/FEEDBACK-MODULE.md.
 */
export interface FeedbackStatusOption {
  value: 'pending' | 'approved' | 'published' | 'archived'
  label: string
  color: string
  bg: string
}

export const feedbackStatuses: FeedbackStatusOption[] = [
  { value: 'pending',   label: 'Pending',   color: 'var(--color-warning)',       bg: 'var(--color-warning-soft)' },
  { value: 'approved',  label: 'Approved',  color: 'var(--color-accent)',        bg: 'var(--color-accent-soft)' },
  { value: 'published', label: 'Published', color: 'var(--color-success)',       bg: 'var(--color-success-soft)' },
  { value: 'archived',  label: 'Archived',  color: 'var(--color-text-tertiary)', bg: 'var(--color-bg-secondary)' },
]

/** NPS banding chips (promoter ≥9 / passive 7–8 / detractor ≤6). */
export const npsBuckets: Record<string, { label: string, color: string, bg: string }> = {
  promoter:  { label: 'Promoter',  color: 'var(--color-success)', bg: 'var(--color-success-soft)' },
  passive:   { label: 'Passive',   color: 'var(--color-warning)', bg: 'var(--color-warning-soft)' },
  detractor: { label: 'Detractor', color: 'var(--color-danger)',  bg: 'var(--color-danger-soft)' },
}
