{{-- Email 22 — task assigned to a team member (new; mailable + trigger in Phase 6). --}}
@php
    $heading = "You've got a new task, {$name}";
@endphp
<x-email::layouts.client title="New task" :preheader="'New task: '.$taskTitle">
<x-email::blocks.heading-intro eyebrow="New task" :heading="$heading">
A task has been assigned to you in the team workspace.
</x-email::blocks.heading-intro>
<x-email::blocks.spacer :height="32" />
<x-email::blocks.summary-table :rows="[
    ['Task', $taskTitle],
    ['Deadline', $deadline],
    ['Priority', $priority],
    ['Pay', $pay],
]" />
<x-email::blocks.spacer :height="32" />
<x-email::blocks.main-button :url="$taskUrl" label="Open task" />
<x-email::blocks.signature />
</x-email::layouts.client>
