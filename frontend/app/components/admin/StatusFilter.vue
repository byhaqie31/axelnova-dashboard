<script setup lang="ts">
interface StatusOption {
  value: string
  label: string
}

const props = withDefaults(defineProps<{
  modelValue: string
  options: StatusOption[]
  label?: string
  placeholder?: string
}>(), {
  label: 'Status',
  placeholder: 'All',
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const open = ref(false)
const root = ref<HTMLElement | null>(null)
onClickOutside(root, () => { open.value = false })

const activeLabel = computed(() => {
  return props.options.find(o => o.value === props.modelValue)?.label ?? props.placeholder
})

function pick(value: string) {
  emit('update:modelValue', value)
  open.value = false
}
</script>

<template>
  <div ref="root" class="relative inline-flex items-center gap-2">
    <span class="text-[11px] font-medium uppercase tracking-wide" style="color: var(--color-text-tertiary);">{{ label }}</span>
    <button
      type="button"
      :aria-expanded="open"
      aria-haspopup="listbox"
      class="inline-flex items-center gap-2 text-[12px] px-3.5 py-1.5 rounded-full border transition-all duration-200 min-w-32"
      :style="{
        borderColor: open ? 'var(--color-accent)' : 'var(--color-border-strong)',
        background: open ? 'var(--color-accent-soft)' : 'var(--color-bg-elevated)',
        color: open ? 'var(--color-accent)' : 'var(--color-text)',
        fontWeight: '500',
      }"
      @click="open = !open"
    >
      <span class="flex-1 text-left truncate">{{ activeLabel }}</span>
      <UIcon
        name="i-lucide-chevron-down"
        class="size-3.5 shrink-0 transition-transform duration-200"
        :style="{ transform: open ? 'rotate(180deg)' : 'rotate(0)' }"
      />
    </button>

    <Transition name="filter-menu">
      <ul
        v-if="open"
        role="listbox"
        class="absolute left-auto right-0 top-full mt-1.5 min-w-44 rounded-xl border p-1 z-30"
        :style="{
          background: 'var(--color-bg-elevated)',
          borderColor: 'var(--color-border)',
          boxShadow: 'var(--shadow-card-hover)',
        }"
      >
        <li v-for="opt in options" :key="opt.value">
          <button
            type="button"
            role="option"
            :aria-selected="modelValue === opt.value"
            class="w-full flex items-center justify-between gap-3 text-[13px] px-2.5 py-2 rounded-md transition-colors"
            :style="{
              background: modelValue === opt.value ? 'var(--color-accent-soft)' : 'transparent',
              color: modelValue === opt.value ? 'var(--color-accent)' : 'var(--color-text)',
              fontWeight: modelValue === opt.value ? '500' : '400',
            }"
            @click="pick(opt.value)"
          >
            <span class="truncate">{{ opt.label }}</span>
            <UIcon
              v-if="modelValue === opt.value"
              name="i-fluent-checkmark-24-regular"
              class="size-3.5 shrink-0"
            />
          </button>
        </li>
      </ul>
    </Transition>
  </div>
</template>

<style scoped>
.filter-menu-enter-active,
.filter-menu-leave-active {
  transition: opacity 0.14s ease, transform 0.14s ease;
}
.filter-menu-enter-from,
.filter-menu-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
