@component('mail::message')
# Thanks for reaching out, {{ $inquiry->name }}

I've received your project inquiry and made a note of it. I'll review the details and get back to you with a tailored quote — usually within 1–2 business days.

@php
    $hints = array_filter([
        'Project type' => $inquiry->project_type,
        'Budget' => $inquiry->budget_hint,
        'Timeline' => $inquiry->timeline_hint,
    ]);
@endphp

@if(!empty($hints))
Here's what you shared:

@foreach($hints as $label => $value)
- **{{ $label }}:** {{ $value }}
@endforeach
@endif

If you'd like to talk it through sooner, you're welcome to book a quick call:

@if($calendlyUrl)
@component('mail::button', ['url' => $calendlyUrl, 'color' => 'blue'])
Book a Discovery Call
@endcomponent
@endif

In the meantime, just reply to this email with anything else that'll help me scope your project — I read every message personally.

Talk soon,

**Ahmad Baihaqie**<br>
Founder, Axel Nova Ventures<br>
baihaqie@axelnova.tech
@endcomponent
