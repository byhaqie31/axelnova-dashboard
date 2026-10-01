<script setup lang="ts">
/**
 * Searchable dropdown over a list of existing names that can also create a new
 * one — the blog editor's category (single) and topics (`multiple`, checkbox
 * rows + chips). Names match case-insensitively: typing "cloudflare" when
 * "Cloudflare" exists picks the existing spelling instead of offering a
 * duplicate. v-model is a string ('' = none) in single mode, a string[] in
 * multiple mode. Same token-styled popover as AdminSelect.
 */
const props = withDefaults(defineProps<{
  modelValue: string | string[]
  /** The existing names to offer (e.g. the categories in use). */
  items: string[]
  multiple?: boolean
  placeholder?: string
  /** Search / create input limit — match the backend's field max. */
  maxLength?: number
  /** Multiple mode: most names that can be picked. */
  max?: number
  /** Word used in the create row and empty state, e.g. "topic". */
  noun?: string
}>(), {
  multiple: false,
  placeholder: 'Select…',
  maxLength: 60,
  max: Infinity,
  noun: 'item',
})

const emit = defineEmits<{ 'update:modelValue': [value: string | string[]] }>()

const open = ref(false)
const query = ref('')
const highlight = ref(-1)
const root = ref<HTMLElement | null>(null)
const searchInput = ref<HTMLInputElement | null>(null)
onClickOutside(root, close)

const key = (s: string) => s.trim().toLowerCase()

const selected = computed<string[]>(() => {
  if (Array.isArray(props.modelValue)) return props.modelValue
  return props.modelValue ? [props.modelValue] : []
})
const selectedKeys = computed(() => new Set(selected.value.map(key)))
const atMax = computed(() => props.multiple && selected.value.length >= props.max)

// Existing names plus whatever is picked (a name just created lives only in the
// model until the post is saved), de-duplicated by case, sorted.
const options = computed(() => {
  const byKey = new Map<string, string>()
  for (const name of [...props.items, ...selected.value]) {
    if (name.trim() && !byKey.has(key(name))) byKey.set(key(name), name.trim())
  }
  return [...byKey.values()].sort((a, b) => a.localeCompare(b, undefined, { sensitivity: 'base', numeric: true }))
})

const filtered = computed(() => {
  const q = key(query.value)
  return q ? options.value.filter(o => key(o).includes(q)) : options.value
})
const exactMatch = computed(() => options.value.find(o => key(o) === key(query.value)))
const canCreate = computed(() => query.value.trim() !== '' && !exactMatch.value)
const createDisabled = computed(() => atMax.value)

// Rows the keyboard walks: the filtered names, then the create row.
const rowCount = computed(() => filtered.value.length + (canCreate.value ? 1 : 0))
const isChecked = (name: string) => selectedKeys.value.has(key(name))
const isDisabled = (name: string) => atMax.value && !isChecked(name)

watch(query, () => {
  // Enter should do the obvious thing: pick the exact match, else create.
  if (!query.value.trim()) highlight.value = -1
  else if (exactMatch.value) highlight.value = filtered.value.indexOf(exactMatch.value)
  else highlight.value = canCreate.value ? filtered.value.length : 0
})

async function toggleOpen() {
  if (open.value) return close()
  open.value = true
  await nextTick()
  searchInput.value?.focus()
}

function close() {
  open.value = false
  query.value = ''
  highlight.value = -1
}

function pick(name: string) {
  if (!props.multiple) {
    emit('update:modelValue', name)
    close()
    return
  }
  if (isChecked(name)) {
    emit('update:modelValue', selected.value.filter(s => key(s) !== key(name)))
  }
  else if (!atMax.value) {
    emit('update:modelValue', [...selected.value, name])
  }
  query.value = ''
}

function create() {
  const name = query.value.trim()
  if (!name || createDisabled.value) return
  pick(name)
}

function remove(name: string) {
  emit('update:modelValue', props.multiple ? selected.value.filter(s => key(s) !== key(name)) : '')
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
    e.preventDefault()
    if (!rowCount.value) return
    const step = e.key === 'ArrowDown' ? 1 : -1
    highlight.value = (highlight.value + step + rowCount.value) % rowCount.value
  }
  else if (e.key === 'Enter') {
    e.preventDefault()
    const name = filtered.value[highlight.value]
    if (name !== undefined) {
      if (!isDisabled(name)) pick(name)
    }
    else if (highlight.value === filtered.value.length && canCreate.value) {
      create()
    }
  }
  else if (e.key === 'Escape') {
    e.preventDefault()
    close()
  }
  else if (e.key === 'Backspace' && props.multiple && !query.value && selected.value.length) {
    remove(selected.value[selected.value.length - 1]!)
  }
}
</script>

<template>
  <div ref="root" class="relative" @keydown="open && onKeydown($event)">
    <div
      class="contact-input flex w-full min-h-[46px] flex-wrap items-center gap-1.5 text-[13px] cursor-pointer py-2!"
      :style="{
        borderColor: open ? 'var(--color-accent)' : 'var(--color-border)',
        background: 'var(--color-bg-elevated)',
      }"
      @click="toggleOpen"
    >
      <span
        v-for="name in selected"
        :key="name"
        class="inline-flex items-center gap-1 rounded-full pl-2.5 pr-1 py-0.5 text-[12px] font-medium"
        :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }"
      >
        <span class="truncate max-w-[180px]">{{ name }}</span>
        <button
          type="button"
          class="inline-flex size-4 items-center justify-center rounded-full transition-opacity hover:opacity-70 max-md:relative max-md:after:absolute max-md:after:-inset-2 max-md:active:opacity-60"
          :aria-label="`Remove ${name}`"
          @click.stop="remove(name)"
        >
          <UIcon name="i-lucide-x" class="size-3" />
        </button>
      </span>
      <button
        type="button"
        :aria-expanded="open"
        aria-haspopup="listbox"
        class="flex min-w-[80px] flex-1 items-center justify-between gap-2 text-left"
        @click.stop="toggleOpen"
      >
        <span class="truncate" :style="{ color: 'var(--color-text-tertiary)' }">
          {{ selected.length ? '' : placeholder }}
        </span>
        <UIcon
          name="i-lucide-chevron-down"
          class="size-3.5 shrink-0 transition-transform duration-200"
          :style="{ color: 'var(--color-text-tertiary)', transform: open ? 'rotate(180deg)' : 'rotate(0)' }"
        />
      </button>
    </div>

    <Transition name="admin-creatable">
      <div
        v-if="open"
        class="absolute left-0 right-0 top-full mt-1.5 rounded-xl border z-30 overflow-hidden"
        :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)', boxShadow: 'var(--shadow-card-hover)' }"
      >
        <div class="flex items-center gap-2 border-b px-3" :style="{ borderColor: 'var(--color-border)' }">
          <UIcon name="i-lucide-search" class="size-3.5 shrink-0" :style="{ color: 'var(--color-text-tertiary)' }" />
          <input
            ref="searchInput"
            v-model="query"
            type="text"
            :maxlength="maxLength"
            :placeholder="`Search or add a ${noun}…`"
            class="creatable-search w-full bg-transparent py-2.5 text-[13px] outline-none"
            :style="{ color: 'var(--color-text)' }"
          >
        </div>

        <ul role="listbox" :aria-multiselectable="multiple" class="max-h-60 overflow-auto p-1">
          <li v-for="(name, i) in filtered" :key="name">
            <button
              type="button"
              role="option"
              :aria-selected="isChecked(name)"
              :disabled="isDisabled(name)"
              class="w-full flex items-center gap-2.5 text-[13px] max-md:text-[14px] px-2.5 py-2 max-md:py-2.5 rounded-md transition-colors disabled:cursor-not-allowed"
              :style="{
                background: highlight === i ? 'var(--color-bg-secondary)' : !multiple && isChecked(name) ? 'var(--color-accent-soft)' : 'transparent',
                color: isDisabled(name) ? 'var(--color-text-tertiary)' : !multiple && isChecked(name) ? 'var(--color-accent)' : 'var(--color-text)',
                fontWeight: isChecked(name) ? '500' : '400',
                opacity: isDisabled(name) ? 0.55 : 1,
              }"
              @mouseenter="highlight = i"
              @click="!isDisabled(name) && pick(name)"
            >
              <span
                v-if="multiple"
                class="inline-flex size-4 shrink-0 items-center justify-center rounded border transition-colors"
                :style="{
                  background: isChecked(name) ? 'var(--color-accent)' : 'transparent',
                  borderColor: isChecked(name) ? 'var(--color-accent)' : 'var(--color-border-strong)',
                  color: 'var(--color-on-accent, #fff)',
                }"
              >
                <UIcon v-if="isChecked(name)" name="i-lucide-check" class="size-3" />
              </span>
              <span class="truncate flex-1 text-left">{{ name }}</span>
              <UIcon v-if="!multiple && isChecked(name)" name="i-fluent-checkmark-24-regular" class="size-3.5 shrink-0" />
            </button>
          </li>

          <li v-if="canCreate">
            <button
              type="button"
              :disabled="createDisabled"
              class="w-full flex items-center gap-2.5 text-[13px] max-md:text-[14px] px-2.5 py-2 max-md:py-2.5 rounded-md transition-colors disabled:cursor-not-allowed"
              :style="{
                background: highlight === filtered.length ? 'var(--color-bg-secondary)' : 'transparent',
                color: createDisabled ? 'var(--color-text-tertiary)' : 'var(--color-accent)',
                opacity: createDisabled ? 0.55 : 1,
              }"
              @mouseenter="highlight = filtered.length"
              @click="create"
            >
              <UIcon name="i-lucide-plus" class="size-3.5 shrink-0" />
              <span class="truncate">Add “{{ query.trim() }}”</span>
            </button>
          </li>

          <li v-if="!rowCount" class="px-2.5 py-2 text-[12px]" :style="{ color: 'var(--color-text-tertiary)' }">
            No {{ noun }}s yet — type to add one.
          </li>
        </ul>

        <p v-if="multiple && max !== Infinity" class="border-t px-3 py-1.5 text-[11px]" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text-tertiary)' }">
          {{ selected.length }} / {{ max }} {{ noun }}s{{ atMax ? ' — remove one to add another' : '' }}
        </p>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.admin-creatable-enter-active,
.admin-creatable-leave-active { transition: opacity 0.14s ease, transform 0.14s ease; }
.admin-creatable-enter-from,
.admin-creatable-leave-to { opacity: 0; transform: translateY(-4px); }
/* The open popover already shows where focus is — drop the global focus-visible glow. */
.creatable-search:focus-visible { box-shadow: none; }
/* Mobile: ≥16px form text stops iOS Safari zooming the page on focus. */
@media (max-width: 767.98px) {
  .creatable-search { font-size: 16px; }
}
</style>
