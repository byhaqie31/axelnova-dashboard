@component('mail::message')
# Thanks for reaching out, {{ $inquiry->name }}

@if($isProject)
I've received your project inquiry and made a note of it. I'll review the details and get back to you with a tailored quote, usually within 1–2 business days.
@else
I've received your message and will get back to you personally, usually within one working day.
@endif

@php
    $hints = array_filter([
        'Subject' => $inquiry->subject,
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

If you'd like to talk it through sooner, the quickest way to reach me is a WhatsApp message:

@if($whatsappUrl)
@component('mail::button', ['url' => $whatsappUrl, 'color' => 'green'])
Chat on WhatsApp
@endcomponent
@endif

@if($isProject)
In the meantime, just reply to this email with anything else that'll help me scope your project. I read every message personally.
@else
If there's anything you'd like to add, just reply to this email. I read every message personally.
@endif

Talk soon,

**Ahmad Baihaqie**<br>
Founder, Axel Nova Ventures<br>
baihaqie@axelnova.tech
@endcomponent
