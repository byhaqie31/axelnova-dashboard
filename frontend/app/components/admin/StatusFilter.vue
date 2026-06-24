<script setup lang="ts">
interface StatusOption {
  value: string
  label: string
}

const props = withDefaults(defineProps<{
  modelValue: string | string[]
  options: StatusOption[]
  label?: string
  placeholder?: string
  /** Multi-select mode: render checkboxes; model is a string[] where [] means "All". */
  multiple?: boolean
  /** Optional record count shown as "TOTAL n |" before the status control. */
  total?: number | null
}>(), {
  label: 'Status',
  placeholder: 'All',
  multiple: false,
  total: null,
})

const emit = defineEmits<{
  'update:modelValue': [value: string | string[]]
}>()

const open = ref(false)
const root = ref<HTMLElement | null>(null)
onClickOutside(root, () => { open.value = false })

// Multi-select model normalised to an array; an empty array is the "All" state.
const selected = computed<string[]>(() => Array.isArray(props.modelValue) ? props.modelValue : [])

const activeLabel = computed(() => {
  if (props.multiple) {
    if (!selected.value.length) return 'All'
    const labels = props.options.filter(o => selected.value.includes(o.value)).map(o => o.label)
    return labels.length <= 2 ? labels.join(', ') : `${labels.length} selected`
  }
  return props.options.find(o => o.value === props.modelValue)?.label ?? props.placeholder
})

// Single-select: pick and close.
function pick(value: string) {
  emit('update:modelValue', value)
  open.value = false
}

// Multi-select: toggle a status, keeping the menu open. Unticking the last status
// leaves [] — which reads as "All".
function toggle(value: string) {
  const next = selected.value.includes(value)
    ? selected.value.filter(v => v !== value)
    : [...selected.value, value]
  emit('update:modelValue', next)
}

// "All" clears the selection (= no status filter, show everything).
function selectAll() {
  emit('update:modelValue', [])
  open.value = false
}
</script>

<template>
  <div ref="root" class="relative inline-flex items-center gap-2.5">
    <span v-if="total != null" class="inline-flex items-center gap-1.5">
      <span class="text-[11px] font-medium uppercase tracking-wide" style="color: var(--color-text-tertiary);">Total</span>
      <span class="text-[14px] font-bold tabular-nums leading-none" style="color: var(--color-text);">{{ total }}</span>
    </span>
    <span v-if="total != null" aria-hidden="true" class="select-none text-[13px]" style="color: var(--color-text-tertiary);">|</span>
    <span class="text-[11px] font-medium uppercase tracking-wide" style="color: var(--color-text-tertiary);">{{ label }}</span>
    <button
      type="button"
      :aria-expanded="open"
      aria-haspopup="listbox"
      class="inline-flex items-center gap-2 text-[12px] px-3.5 h-9 rounded-full border transition-all duration-200 min-w-32"
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
        <!-- Multi-select: an "All" reset row + a checkbox per status. -->
        <template v-if="multiple">
          <li>
            <button
              type="button"
              role="option"
              :aria-selected="!selected.length"
              class="w-full flex items-center gap-2.5 text-[13px] px-2.5 py-2 rounded-md transition-colors"
              :style="{
                background: !selected.length ? 'var(--color-accent-soft)' : 'transparent',
                color: !selected.length ? 'var(--color-accent)' : 'var(--color-text)',
                fontWeight: !selected.length ? '500' : '400',
              }"
              @click="selectAll"
            >
              <span
                class="grid place-items-center size-4 rounded-md border shrink-0"
                :style="{
                  borderColor: !selected.length ? 'var(--color-accent)' : 'var(--color-border-strong)',
                  background: !selected.length ? 'var(--color-accent-soft)' : 'transparent',
                }"
              >
                <UIcon v-if="!selected.length" name="i-fluent-checkmark-24-regular" class="size-3" />
              </span>
              <span class="truncate">All</span>
            </button>
          </li>
          <li aria-hidden="true" class="my-1 mx-2 border-t" :style="{ borderColor: 'var(--color-border)' }" />
          <li v-for="opt in options" :key="opt.value">
            <button
              type="button"
              role="option"
              :aria-selected="selected.includes(opt.value)"
              class="w-full flex items-center gap-2.5 text-[13px] px-2.5 py-2 rounded-md transition-colors"
              :style="{
                background: selected.includes(opt.value) ? 'var(--color-accent-soft)' : 'transparent',
                color: selected.includes(opt.value) ? 'var(--color-accent)' : 'var(--color-text)',
                fontWeight: selected.includes(opt.value) ? '500' : '400',
              }"
              @click="toggle(opt.value)"
            >
              <span
                class="grid place-items-center size-4 rounded-md border shrink-0"
                :style="{
                  borderColor: selected.includes(opt.value) ? 'var(--color-accent)' : 'var(--color-border-strong)',
                  background: selected.includes(opt.value) ? 'var(--color-accent-soft)' : 'transparent',
                }"
              >
                <UIcon v-if="selected.includes(opt.value)" name="i-fluent-checkmark-24-regular" class="size-3" />
              </span>
              <span class="truncate">{{ opt.label }}</span>
            </button>
          </li>
        </template>

        <!-- Single-select. -->
        <template v-else>
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
        </template>
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
