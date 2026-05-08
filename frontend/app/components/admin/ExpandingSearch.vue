<script setup lang="ts">
const props = defineProps<{
  modelValue: string
  placeholder?: string
  ariaLabel?: string
}>()
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const expanded = ref(false)
const inputRef = ref<HTMLInputElement>()

const value = computed({
  get: () => props.modelValue,
  set: v => emit('update:modelValue', v),
})

async function expand() {
  expanded.value = true
  await nextTick()
  inputRef.value?.focus()
}

function maybeCollapse() {
  // Stay expanded if the user typed something — otherwise the magnifier returns.
  if (!value.value) expanded.value = false
}

function clearAndCollapse() {
  value.value = ''
  expanded.value = false
}

// If a parent already pre-fills the value (e.g. from a query string), open immediately.
onMounted(() => { if (value.value) expanded.value = true })
</script>

<template>
  <div class="relative inline-flex items-stretch" :class="expanded ? 'w-full sm:w-auto' : ''">
    <button v-if="!expanded" type="button" @click="expand"
      :aria-label="ariaLabel ?? 'Search'"
      class="size-9 rounded-full inline-flex items-center justify-center border transition-colors hover:bg-(--color-bg-secondary)"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)', color: 'var(--color-text-secondary)' }">
      <UIcon name="i-lucide-search" class="size-4" />
    </button>

    <Transition name="expanding-search">
      <div v-if="expanded" class="relative w-full sm:w-auto">
        <UIcon name="i-lucide-search" class="size-4 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"
          :style="{ color: 'var(--color-text-tertiary)' }" />
        <input ref="inputRef" v-model="value" type="search" :placeholder="placeholder"
          class="contact-input w-full sm:w-80"
          :style="{ paddingLeft: '2.25rem', paddingRight: value ? '2.25rem' : '1rem',
                    borderColor: 'var(--color-border)',
                    color: 'var(--color-text)',
                    background: 'var(--color-bg-elevated)' }"
          @blur="maybeCollapse"
          @keydown.escape="clearAndCollapse" />
        <button v-if="value" type="button" @click="clearAndCollapse"
          aria-label="Clear search"
          class="absolute right-2 top-1/2 -translate-y-1/2 size-6 rounded-full inline-flex items-center justify-center transition-colors hover:bg-(--color-bg-secondary)"
          :style="{ color: 'var(--color-text-tertiary)' }">
          <UIcon name="i-lucide-x" class="size-3.5" />
        </button>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.expanding-search-enter-active,
.expanding-search-leave-active {
  transition: opacity 0.15s ease, transform 0.2s cubic-bezier(0.32, 0.72, 0, 1);
}
.expanding-search-enter-from,
.expanding-search-leave-to {
  opacity: 0;
  transform: translateX(8px);
}
@media (prefers-reduced-motion: reduce) {
  .expanding-search-enter-active,
  .expanding-search-leave-active { transition: none; }
}
</style>
