/**
 * Standardised admin toasts — one place for the icon + colour of every
 * success / error notification raised by an admin action (saving a quote,
 * issuing an invoice, sending a PDF, …). Toaster position (top-right) is
 * configured once on `<UApp>` in `app.vue`; toasts persist across route
 * changes, so it's safe to fire one immediately before `navigateTo`.
 */
export function useAdminToast() {
  const toast = useToast()

  function success(title: string, description?: string) {
    toast.add({ title, description, icon: 'i-lucide-circle-check', color: 'success' })
  }

  function error(title: string, description?: string) {
    toast.add({ title, description, icon: 'i-lucide-circle-alert', color: 'error' })
  }

  return { success, error }
}
