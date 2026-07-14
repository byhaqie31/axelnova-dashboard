@component('mail::message')
# Invoice {{ $invoice->invoice_number }}

Hi {{ $clientName }},

Here's your invoice from Axel Nova Ventures.

## Invoice summary

| | |
|---|---|
| **Invoice** | `{{ $invoice->invoice_number }}` |
@if($referenceCode)
| **Quotation ref** | `{{ $referenceCode }}` |
@endif
| **Amount due** | RM {{ number_format($amountDue, 2) }} |
@if($invoice->due_at)
| **Due** | {{ $invoice->due_at->format('d F Y') }} |
@endif

@component('mail::button', ['url' => $pdfUrl, 'color' => 'blue'])
View invoice (PDF)
@endcomponent

Payment can be made to **OCBC Bank 7051415701** (Axel Nova Ventures), or by card / FPX online banking.

If you have any questions, just reply to this email. I read every message personally.

Thank you,

**Ahmad Baihaqie**<br>
Founder, Axel Nova Ventures<br>
baihaqie@axelnova.tech
@endcomponent
