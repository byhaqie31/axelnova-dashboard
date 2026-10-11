{{-- Kit spacer. :lg adds class="gap-lg" (shrinks to 32px on mobile). --}}
@props(['height' => 32, 'lg' => false])
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td{!! $lg ? ' class="gap-lg"' : '' !!} height="{{ $height }}" style="height: {{ $height }}px; font-size: 0; line-height: 0;">&nbsp;</td></tr></table>
