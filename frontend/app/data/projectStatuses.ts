/**
 * Project lifecycle statuses + their tone tokens. Single source of truth
 * for the admin project form pill picker AND the public ProjectCard badge.
 *
 * `color` / `bg` reference design tokens; `bg` uses raw rgba because the
 * existing ProjectCard pattern does the same. When `--color-success-soft`
 * etc. are added to main.css (see UI-Standards 12.2), swap these in.
 */
export interface ProjectStatusOption {
  value: 'planning' | 'wip' | 'soon' | 'live'
  label: string
  color: string
  bg: string
}

export const projectStatuses: ProjectStatusOption[] = [
  { value: 'planning', label: 'Planning',    color: 'var(--color-accent)',  bg: 'rgba(0, 113, 227, 0.12)' },
  { value: 'wip',      label: 'In progress', color: 'var(--color-warning)', bg: 'rgba(255, 159, 10, 0.14)' },
  { value: 'soon',     label: 'Soon',        color: 'var(--color-accent)',  bg: 'rgba(0, 113, 227, 0.12)' },
  { value: 'live',     label: 'Live',        color: 'var(--color-success)', bg: 'rgba(48, 209, 88, 0.14)' },
]
