@component('mail::message')
# New inquiry

@if($inquiry->origin === 'contact')
A new message came in through the contact form.
@elseif($inquiry->origin === 'partner')
A client wants to hire **{{ $inquiry->collaborationPartner?->name ?? 'a partner studio' }}** through Axel Nova, from the collaboration page. Log the lead, then pass it on.
@elseif($inquiry->origin === 'collaboration')
A studio applied to partner with Axel Nova through the collaborations page.
@else
A new project inquiry came in through the quote form.
@endif

@php
    $rows = array_filter([
        'Partner' => $inquiry->collaborationPartner?->name,
        'Subject' => $inquiry->subject,
        'Name' => $inquiry->name,
        'Email' => $inquiry->email,
        'Phone' => $inquiry->phone,
        'Company' => $inquiry->company,
        'Project type' => $inquiry->project_type,
        'Budget' => $inquiry->budget_hint,
        'Timeline' => $inquiry->timeline_hint,
        'Source' => $inquiry->source === 'referral' ? 'Referral' : null,
        'Submitted' => $inquiry->created_at?->format('d M Y, H:i'),
    ]);
@endphp

| | |
|---|---|
@foreach($rows as $label => $value)
| **{{ $label }}** | {{ $value }} |
@endforeach

**Message**

{{ $inquiry->message }}

@component('mail::button', ['url' => $adminUrl, 'color' => 'blue'])
Open in Admin
@endcomponent

Reply to this email to answer {{ $inquiry->name }} directly.
@endcomponent
