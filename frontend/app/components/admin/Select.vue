<script setup lang="ts">
/**
 * Token-styled dropdown replacing the native <select> across admin forms, so the
 * control matches the design system (same surface, border, accent, dark mode) and
 * reads consistently with the Status filter. Same popover pattern: button trigger +
 * floating listbox, closes on outside click. v-model holds the chosen value.
 */
interface SelectItem {
  label: string
  value: string | number
  /** Greyed out and unpickable — for options that don't fit the current state. */
  disabled?: boolean
}

const props = withDefaults(defineProps<{
  modelValue: string | number
  items: SelectItem[]
  placeholder?: string
  disabled?: boolean
}>(), {
  placeholder: 'Select…',
  disabled: false,
})

const emit = defineEmits<{ 'update:modelValue': [value: string | number] }>()

const open = ref(false)
const root = ref<HTMLElement | null>(null)
onClickOutside(root, () => { open.value = false })

const activeItem = computed(() => props.items.find(o => o.value === props.modelValue))

function pick(value: string | number) {
  emit('update:modelValue', value)
  open.value = false
}
</script>

<template>
  <div ref="root" class="relative">
    <button
      type="button"
      :disabled="disabled"
      :aria-expanded="open"
      aria-haspopup="listbox"
      class="contact-input inline-flex w-full items-center justify-between gap-2 text-left text-[13px] disabled:opacity-50 disabled:cursor-not-allowed"
      :style="{
        borderColor: open ? 'var(--color-accent)' : 'var(--color-border)',
        background: 'var(--color-bg-elevated)',
      }"
      @click="open = !open"
    >
      <span class="truncate" :style="{ color: activeItem ? 'var(--color-text)' : 'var(--color-text-tertiary)' }">
        {{ activeItem ? activeItem.label : placeholder }}
      </span>
      <UIcon
        name="i-lucide-chevron-down"
        class="size-3.5 shrink-0 transition-transform duration-200"
        :style="{ color: 'var(--color-text-tertiary)', transform: open ? 'rotate(180deg)' : 'rotate(0)' }"
      />
    </button>

    <Transition name="admin-select">
      <ul
        v-if="open"
        role="listbox"
        class="absolute left-0 right-0 top-full mt-1.5 rounded-xl border p-1 z-30 max-h-60 overflow-auto"
        :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)', boxShadow: 'var(--shadow-card-hover)' }"
      >
        <li v-for="o in items" :key="String(o.value)">
          <button
            type="button"
            role="option"
            :aria-selected="modelValue === o.value"
            :disabled="o.disabled"
            class="w-full flex items-center justify-between gap-3 text-[13px] px-2.5 py-2 rounded-md transition-colors disabled:cursor-not-allowed"
            :style="{
              background: modelValue === o.value ? 'var(--color-accent-soft)' : 'transparent',
              color: o.disabled ? 'var(--color-text-tertiary)' : modelValue === o.value ? 'var(--color-accent)' : 'var(--color-text)',
              fontWeight: modelValue === o.value ? '500' : '400',
              opacity: o.disabled ? 0.55 : 1,
            }"
            @click="!o.disabled && pick(o.value)"
          >
            <span class="truncate">{{ o.label }}</span>
            <UIcon v-if="modelValue === o.value" name="i-fluent-checkmark-24-regular" class="size-3.5 shrink-0" />
          </button>
        </li>
      </ul>
    </Transition>
  </div>
</template>

<style scoped>
.admin-select-enter-active,
.admin-select-leave-active { transition: opacity 0.14s ease, transform 0.14s ease; }
.admin-select-enter-from,
.admin-select-leave-to { opacity: 0; transform: translateY(-4px); }
</style>
