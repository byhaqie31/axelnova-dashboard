/**
 * What <AdminClientPicker> emits: an existing client by id, or the details for a
 * "create new" — the body shape the relink and client-delete endpoints accept
 * (see Client::resolveForRelink). null = nothing picked yet.
 */
export interface NewClientFields {
  name: string
  email: string
  phone: string | null
  company: string | null
}

export type ClientSelection = { client_id: number } | { client: NewClientFields }

/** Client-side guard before submitting a selection; '' when it's usable. */
export function clientSelectionError(sel: ClientSelection | null, noSelectionMessage: string): string {
  if (!sel) return noSelectionMessage
  if ('client' in sel && (sel.client.name.length < 2 || !sel.client.email.includes('@'))) {
    return 'A name and a valid email are required for the new client.'
  }
  return ''
}
