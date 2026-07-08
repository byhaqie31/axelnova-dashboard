// Shared soft-delete flow for quotations — one implementation behind the list row
// action and the detail page, so both confirm before deleting, surface the same
// order-attached (409) block, and report the same linked-record cleanup. Pass the
// success callback (refetch the list, or navigate away from the detail page).

export interface DeletableQuotation {
  id: number
  reference_code: string
  name: string
}

export interface DeleteBlocked {
  message: string
  order_id: number
  order_number: string
}

interface DeleteResponse {
  message: string
  unlinked_inquiries?: number
  untied_referrals?: number
}

export function useQuotationDelete(onDeleted: (q: DeletableQuotation) => void) {
  const { apiFetch } = useAdminAuth()
  const toast = useAdminToast()

  // The quotation pending deletion (null = dialog closed).
  const target = ref<DeletableQuotation | null>(null)
  const deleting = ref(false)
  // Set from a 409 — anchored to an order, so it can't be deleted; shown inline.
  const blocked = ref<DeleteBlocked | null>(null)

  function open(q: DeletableQuotation) {
    blocked.value = null
    target.value = q
  }

  function close() {
    if (deleting.value) return
    target.value = null
    blocked.value = null
  }

  async function confirm() {
    const q = target.value
    if (!q || deleting.value) return
    deleting.value = true
    try {
      const res = await apiFetch<DeleteResponse>(`/api/v1/admin/quotations/${q.id}`, { method: 'DELETE' })
      // Mention any linked records that were unlinked, so the cleanup isn't silent.
      const bits = [
        res.unlinked_inquiries ? `${res.unlinked_inquiries} inquiry unlinked` : '',
        res.untied_referrals ? `${res.untied_referrals} referral untied` : '',
      ].filter(Boolean)
      toast.success('Quotation deleted', bits.length ? `${q.reference_code} removed — ${bits.join(', ')}.` : `${q.reference_code} was removed.`)
      target.value = null
      onDeleted(q)
    }
    catch (e) {
      const err = e as { status?: number, data?: { message?: string, order_id?: number, order_number?: string } }
      if (err?.status === 409 && err.data?.order_id) {
        blocked.value = {
          message: err.data.message ?? 'This quotation is attached to an order and can’t be deleted.',
          order_id: err.data.order_id,
          order_number: err.data.order_number ?? '',
        }
      }
      else {
        toast.error('Couldn’t delete quotation', err?.data?.message ?? 'Something went wrong. Please try again.')
        target.value = null
      }
    }
    finally {
      deleting.value = false
    }
  }

  // Escape closes the dialog (but not mid-delete).
  onKeyStroke('Escape', () => { if (target.value) close() })

  return { target, deleting, blocked, open, close, confirm }
}
