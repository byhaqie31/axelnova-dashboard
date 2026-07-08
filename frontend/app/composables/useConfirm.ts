// Promise-based confirm-before-act gate for consequential admin actions (create
// order, send to client, …) so a single stray click can't fire them. Usage:
//
//   const { confirmOpen, confirmConfig, confirm, resolveConfirm } = useConfirm()
//   async function accept() {
//     if (!(await confirm({ title: '…', message: '…', confirmLabel: '…' }))) return
//     …proceed…
//   }
//
// Pair with <AdminConfirmDialog :open="confirmOpen" :config="confirmConfig" @resolve="resolveConfirm" />.
// The dialog is a pure yes/no gate — the caller's own loading state drives the
// button spinner once it resolves true.

export interface ConfirmConfig {
  title: string
  message?: string
  confirmLabel?: string
  /** CTA styling — positive (accent), convert (warning), or destructive (danger). */
  variant?: 'accent' | 'warning' | 'danger'
}

export function useConfirm() {
  const confirmOpen = ref(false)
  const confirmConfig = ref<ConfirmConfig>({ title: '' })
  let resolver: ((ok: boolean) => void) | null = null

  function confirm(config: ConfirmConfig): Promise<boolean> {
    confirmConfig.value = { confirmLabel: 'Confirm', variant: 'accent', ...config }
    confirmOpen.value = true
    return new Promise((resolve) => { resolver = resolve })
  }

  function resolveConfirm(ok: boolean) {
    confirmOpen.value = false
    resolver?.(ok)
    resolver = null
  }

  // Escape cancels.
  onKeyStroke('Escape', () => { if (confirmOpen.value) resolveConfirm(false) })

  return { confirmOpen, confirmConfig, confirm, resolveConfirm }
}
