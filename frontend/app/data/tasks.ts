// Task domain metadata — single source of truth for the shapes and option maps
// shared by the admin cockpit (/admin/tasks) and the team workspace
// (/team/tasks kanban + /team/calendar). Mirrors backend's TaskResource and the
// tasks table enums; follows the {value,label,color,bg} status-map pattern
// established by data/availabilityStatuses.ts.

/** Matches backend App\Http\Resources\TaskResource. */
export interface TaskRecord {
  id: number
  title: string
  description: string | null
  created_by: number
  created_by_name: string | null
  assignee_id: number | null
  assignee_name: string | null
  pay_amount_myr: number | null
  payment_state: 'none' | 'pending' | 'paid'
  /** Task 7 — non-null once a payslip has swept this bonus up (admin hides ad-hoc mark-paid). */
  payroll_entry_id: number | null
  /** Resolves only where the payrollEntry relation is eager-loaded (admin endpoints). */
  payroll_period_label?: string | null
  duration_estimate: string | null
  deadline: string | null
  priority: 'low' | 'medium' | 'high'
  status: 'open' | 'in_progress' | 'completed' | 'payment_pending' | 'paid'
  notes: string | null
  completed_at: string | null
  paid_at: string | null
  created_at: string
  updated_at: string
}

/** GET /v1/team/tasks — the kanban/calendar feed in one round-trip. */
export interface TeamTasksFeed {
  pool: TaskRecord[]
  mine: TaskRecord[]
}

/**
 * The admin create/edit form shape — string-typed for the inputs (assignee_id,
 * pay are strings in the form, coerced on submit). Shared by /admin/tasks/new,
 * /admin/tasks/[id] and the AdminTaskFormFields component.
 */
export interface TaskFormShape {
  title: string
  description: string
  assignee_id: string
  pay: string
  duration_estimate: string
  deadline: string
  priority: TaskRecord['priority']
}

export interface TaskPriorityOption {
  value: TaskRecord['priority']
  label: string
  color: string
  bg: string
}

// Priority tints ride the existing semantic tokens (no new colors): high is
// the only alarming one; medium is the default "scheduled work" amber; low
// stays quiet.
export const taskPriorityOptions: TaskPriorityOption[] = [
  { value: 'low', label: 'Low', color: 'var(--color-text-tertiary)', bg: 'var(--color-bg-secondary)' },
  { value: 'medium', label: 'Medium', color: 'var(--color-warning)', bg: 'var(--color-warning-soft)' },
  { value: 'high', label: 'High', color: 'var(--color-danger)', bg: 'var(--color-danger-soft)' },
]

export function taskPriorityMeta(value?: string | null): TaskPriorityOption | undefined {
  return taskPriorityOptions.find(o => o.value === value)
}

/** Filter options for the admin list (AdminStatusFilter shape). */
export const taskStatusOptions = [
  { value: '', label: 'All' },
  { value: 'open', label: 'Open' },
  { value: 'in_progress', label: 'In progress' },
  { value: 'completed', label: 'Completed' },
  { value: 'payment_pending', label: 'Payment pending' },
  { value: 'paid', label: 'Paid' },
]
