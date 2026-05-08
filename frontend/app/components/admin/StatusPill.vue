<script setup lang="ts">
type StatusKey =
  | 'new' | 'viewed' | 'contacted' | 'accepted' | 'rejected' | 'spam'
  | 'pending' | 'in_progress' | 'delivered' | 'completed' | 'cancelled'

const props = withDefaults(defineProps<{
  status: string | null | undefined
  size?: 'sm' | 'md'
  fallback?: StatusKey
}>(), {
  size: 'sm',
  fallback: 'pending',
})

// Sentence-case labels — single source of truth for status display.
const statusLabels: Record<StatusKey, string> = {
  new: 'New',
  viewed: 'Viewed',
  contacted: 'Contacted',
  accepted: 'Accepted',
  rejected: 'Rejected',
  spam: 'Spam',
  pending: 'Pending',
  in_progress: 'In progress',
  delivered: 'Delivered',
  completed: 'Completed',
  cancelled: 'Cancelled',
}

const resolved = computed<StatusKey>(() => {
  const s = (props.status ?? props.fallback) as StatusKey
  return s in statusLabels ? s : props.fallback
})

const label = computed(() => statusLabels[resolved.value])
</script>

<template>
  <span class="status-pill" :data-status="resolved" :class="{ 'text-[12px] px-3 py-1.5': size === 'md' }">
    {{ label }}
  </span>
</template>
