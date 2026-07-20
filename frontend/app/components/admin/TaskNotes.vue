<script setup lang="ts">
// The team's append-only notes, rendered as comments. Each line the team stamps
// on a status change is "[YYYY-MM-DD HH:MM] Author: text" (Team\TasksController),
// so we split on that prefix — text may span multiple lines until the next stamp.
// Anything that doesn't match the format falls back to the raw blob, so legacy /
// hand-entered notes still show.
const props = defineProps<{ notes: string }>()

interface Comment { time: string, author: string, text: string }

// Global flag so the lookahead can find the NEXT entry's stamp as the boundary.
const ENTRY = /\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2})\]\s*(.+?):\s*([\s\S]*?)(?=\n\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}\]|$)/g

const comments = computed<Comment[] | null>(() => {
  const out: Comment[] = []
  for (const m of props.notes.matchAll(ENTRY)) {
    out.push({ time: m[1] ?? '', author: (m[2] ?? '').trim(), text: (m[3] ?? '').trim() })
  }
  return out.length ? out : null
})

// "2026-07-04 12:30" → "4 Jul 2026, 12:30 PM"; raw stamp if the engine won't parse it.
function fmt(stamp: string): string {
  const d = new Date(stamp.replace(' ', 'T'))
  if (Number.isNaN(d.getTime())) return stamp
  return d.toLocaleString('en-MY', {
    day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true,
  })
}
</script>

<template>
  <ul v-if="comments" class="space-y-3.5">
    <li v-for="(c, i) in comments" :key="i" class="flex gap-2.5">
      <span
        class="grid place-items-center rounded-full shrink-0 mt-0.5"
        style="width: 1.5rem; height: 1.5rem;"
        :style="{ background: 'var(--color-bg-secondary)', color: 'var(--color-text-tertiary)' }"
      >
        <UIcon name="i-lucide-message-square" class="size-3" aria-hidden="true" />
      </span>
      <div class="min-w-0 flex-1">
        <div class="flex items-baseline justify-between gap-2">
          <span class="text-[12px] font-medium truncate" :style="{ color: 'var(--color-text)' }">{{ c.author }}</span>
          <span class="text-[11px] shrink-0" :style="{ color: 'var(--color-text-tertiary)' }">{{ fmt(c.time) }}</span>
        </div>
        <p class="text-[13px] leading-relaxed whitespace-pre-wrap mt-0.5" :style="{ color: 'var(--color-text-secondary)' }">{{ c.text }}</p>
      </div>
    </li>
  </ul>
  <!-- Fallback: notes that don't follow the stamped format. -->
  <p v-else class="text-[13px] leading-relaxed whitespace-pre-wrap" :style="{ color: 'var(--color-text-secondary)' }">{{ notes }}</p>
</template>
