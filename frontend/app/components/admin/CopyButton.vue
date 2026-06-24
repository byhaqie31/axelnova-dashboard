<script setup lang="ts">
/**
 * Small copy-to-clipboard icon button. Shows a transient check + a toast on
 * success. Stops click propagation so it works inside clickable rows/cards.
 */
const props = withDefaults(defineProps<{
  value: string
  label?: string
  size?: 'sm' | 'md'
}>(), {
  size: 'sm',
})

const toast = useAdminToast()
const copied = ref(false)
let timer: ReturnType<typeof setTimeout>

async function copy() {
  try {
    await navigator.clipboard.writeText(props.value)
    copied.value = true
    clearTimeout(timer)
    timer = setTimeout(() => { copied.value = false }, 1500)
    toast.success('Copied', props.value)
  }
  catch {
    toast.error('Couldn’t copy', 'Clipboard isn’t available in this browser.')
  }
}

onUnmounted(() => clearTimeout(timer))
</script>

<template>
  <button
    type="button"
    :aria-label="label ?? `Copy ${value}`"
    :title="copied ? 'Copied' : 'Copy'"
    class="shrink-0 rounded-md inline-flex items-center justify-center transition-colors hover:bg-(--color-bg-secondary)"
    :class="size === 'md' ? 'size-7' : 'size-5'"
    :style="{ color: copied ? 'var(--color-success)' : 'var(--color-text-tertiary)' }"
    @click.stop.prevent="copy"
  >
    <UIcon :name="copied ? 'i-lucide-check' : 'i-lucide-copy'" :class="size === 'md' ? 'size-4' : 'size-3.5'" />
  </button>
</template>
