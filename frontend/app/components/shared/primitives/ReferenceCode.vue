<script setup lang="ts">
import { useClipboard } from '@vueuse/core'

const props = withDefaults(defineProps<{
  code: string
  copyable?: boolean
}>(), {
  copyable: true,
})

const toast = useToast()
const { copy, copied, isSupported } = useClipboard({ source: () => props.code })

async function onCopy() {
  if (!props.copyable || !isSupported.value) return
  await copy(props.code)
  toast.add({ title: 'Copied', description: props.code, icon: 'i-fluent-checkmark-24-regular' })
}
</script>

<template>
  <button
    v-if="copyable"
    type="button"
    class="inline-flex items-center gap-1.5 font-mono text-[12px] tracking-wide rounded-md px-2 py-0.5 transition-colors hover:bg-(--color-bg-secondary)"
    :style="{ color: 'var(--color-text)' }"
    :aria-label="`Copy reference code ${code}`"
    @click="onCopy"
  >
    {{ code }}
    <UIcon
      :name="copied ? 'i-fluent-checkmark-24-regular' : 'i-fluent-copy-24-regular'"
      class="size-3"
      :style="{ color: 'var(--color-text-tertiary)' }"
    />
  </button>
  <span
    v-else
    class="font-mono text-[12px] tracking-wide"
    :style="{ color: 'var(--color-text)' }"
  >
    {{ code }}
  </span>
</template>
