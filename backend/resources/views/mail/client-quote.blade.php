@component('mail::message')
# Thanks for reaching out, {{ $quote->name }}

Here's the estimate for your project, based on what you submitted on the quote builder.

## Quote Summary

| | |
|---|---|
| **Reference** | `{{ $quote->reference_code }}` |
| **Estimated Investment** | RM {{ number_format($quote->estimate_min_myr) }} – RM {{ number_format($quote->estimate_max_myr) }} |
| **Estimated Timeline** | {{ $quote->eta_label }} |
| **Valid Until** | {{ $validUntil }} |

@if($pdfUrl)
@component('mail::button', ['url' => $pdfUrl, 'color' => 'blue'])
Download your quotation (PDF)
@endcomponent
@endif

@php
    $breakdown = $quote->form_payload['breakdown'] ?? [];
@endphp

@if(!empty($breakdown))
## What's included

@foreach($breakdown as $item)
- **{{ $item[0] ?? '' }}** — RM {{ number_format($item[1] ?? 0) }} – RM {{ number_format($item[2] ?? 0) }}
@endforeach
@endif

@if($quote->addons->isNotEmpty())
## Add-ons selected

@foreach($quote->addons as $addon)
- **{{ $addon->addon_label }}** — RM {{ number_format($addon->amount_myr) }}
@endforeach
@endif

This is an indicative estimate based on your inputs. Final scope and price are confirmed after a quick chat. That's where I dig into the actual details and tighten the numbers.

@if($whatsappUrl)
@component('mail::button', ['url' => $whatsappUrl, 'color' => 'green'])
Chat on WhatsApp
@endcomponent
@endif

If you have any questions in the meantime, just reply to this email. I read every message personally.

Looking forward to working together,

**Ahmad Baihaqie**<br>
Founder, Axel Nova Ventures<br>
baihaqie@axelnova.tech
@endcomponent
