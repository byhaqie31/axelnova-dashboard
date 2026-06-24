<script setup lang="ts">
// Always-visible search bar (leading magnifier, inline clear). Sized to match the
// AdminStatusFilter control (h-9) so the two sit level in a filter row.
const props = defineProps<{
  modelValue: string
  placeholder?: string
  ariaLabel?: string
}>()
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const value = computed({
  get: () => props.modelValue,
  set: v => emit('update:modelValue', v),
})
</script>

<template>
  <div class="relative w-full sm:w-72">
    <UIcon name="i-lucide-search" class="size-4 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none"
      :style="{ color: 'var(--color-text-tertiary)' }" />
    <input
      v-model="value"
      type="search"
      :placeholder="placeholder"
      :aria-label="ariaLabel ?? 'Search'"
      class="search-bar w-full h-9 rounded-full border text-[13px]"
      :style="{
        paddingLeft: '2.5rem',
        paddingRight: value ? '2.25rem' : '1rem',
        borderColor: 'var(--color-border-strong)',
        background: 'var(--color-bg-elevated)',
        color: 'var(--color-text)',
      }"
      @keydown.escape="value = ''"
    >
    <button
      v-if="value"
      type="button"
      aria-label="Clear search"
      class="absolute right-2 top-1/2 -translate-y-1/2 size-6 rounded-full inline-flex items-center justify-center transition-colors hover:bg-(--color-bg-secondary)"
      :style="{ color: 'var(--color-text-tertiary)' }"
      @click="value = ''"
    >
      <UIcon name="i-lucide-x" class="size-3.5" />
    </button>
  </div>
</template>

<style scoped>
.search-bar {
  outline: none;
  font-family: inherit;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.search-bar::placeholder { color: var(--color-text-tertiary); }
.search-bar:focus { border-color: var(--color-accent); box-shadow: var(--shadow-glow); }
/* Hide the native search "clear" so only our button shows. */
.search-bar::-webkit-search-decoration,
.search-bar::-webkit-search-cancel-button { -webkit-appearance: none; appearance: none; }
</style>
