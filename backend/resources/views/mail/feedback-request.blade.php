@component('mail::message')
# How did we do{{ $feedback->name ? ', '.$feedback->name : '' }}?

@if($feedback->order_id)
@if($feedback->project_label)
Your project — **{{ $feedback->project_label }}** — has wrapped, and I'd love to hear how the experience was for you.
@else
Your project with Axel Nova Ventures has wrapped, and I'd love to hear how the experience was for you.
@endif
@else
I'd love to hear your honest take on Axel Nova Ventures — how we come across, and how the experience of dealing with us has been.
@endif

It's a short form — a couple of quick ratings and two open questions. Two minutes, tops.

@component('mail::button', ['url' => $feedbackUrl, 'color' => 'blue'])
Share your feedback
@endcomponent

Your answers come straight to me. If you're happy for your words to appear on the Axel Nova site, there's an optional permission toggle on the form — nothing is ever published without it.

Thanks for building with me,

**Ahmad Baihaqie**<br>
Founder, Axel Nova Ventures<br>
baihaqie@axelnova.tech
@endcomponent
