@component('mail::message')
# New Quote Request

A new lead has been submitted via the quote builder.

| | |
|---|---|
| **Reference** | `{{ $quote->reference_code }}` |
| **Name** | {{ $quote->name }} |
| **Email** | {{ $quote->email }} |
| **Phone** | {{ $quote->phone }} |
| **Company** | {{ $quote->company ?: '—' }} |
| **Package** | `{{ $quote->form_payload['package_key'] ?? '—' }}` |
| **Estimate** | RM {{ number_format($quote->estimate_min_myr) }} – RM {{ number_format($quote->estimate_max_myr) }} |
| **Timeline** | {{ $quote->eta_label }} |
| **Submitted** | {{ $quote->submitted_at->format('d M Y, H:i') }} |

@component('mail::button', ['url' => $adminUrl, 'color' => 'blue'])
View Lead in Admin
@endcomponent

@endcomponent
