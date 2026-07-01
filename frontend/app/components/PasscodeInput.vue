<script setup lang="ts">
// Segmented numeric passcode entry — one box per digit. v-model binds the joined
// string (digits only). Handles auto-advance, backspace-to-previous, arrow nav, and
// pasting a full code. Kept as text inputs with inputmode=numeric (not type=number)
// so leading zeros survive.
const props = withDefaults(defineProps<{
  modelValue: string
  length?: number
  autofocus?: boolean
}>(), {
  length: 8,
  autofocus: false,
})

const emit = defineEmits<{
  'update:modelValue': [string]
  'complete': [string]
}>()

const inputs = ref<HTMLInputElement[]>([])
const digits = ref<string[]>(Array.from({ length: props.length }, () => ''))

function setRef(el: unknown, i: number) {
  if (el) inputs.value[i] = el as HTMLInputElement
}

// Reflect an external modelValue change (e.g. a reset to '') into the boxes.
watch(() => props.modelValue, (v) => {
  const chars = (v ?? '').replace(/\D/g, '').slice(0, props.length).split('')
  const next = Array.from({ length: props.length }, (_, i) => chars[i] ?? '')
  if (next.join('') !== digits.value.join('')) digits.value = next
})

function emitValue() {
  const val = digits.value.join('')
  emit('update:modelValue', val)
  if (val.length === props.length) emit('complete', val)
}

function focusBox(i: number) {
  const el = inputs.value[i]
  if (el) {
    el.focus()
    el.select()
  }
}

function onBeforeInput(e: InputEvent) {
  // Numbers only — block any non-digit character before it lands. Deletions and
  // navigation carry null/empty data and pass through.
  if (typeof e.data === 'string' && /\D/.test(e.data)) {
    e.preventDefault()
  }
}

function onInput(i: number, e: Event) {
  const target = e.target as HTMLInputElement
  const raw = target.value.replace(/\D/g, '')

  if (!raw) {
    digits.value[i] = ''
    target.value = '' // clear any stray non-digit that slipped past beforeinput
    emitValue()
    return
  }

  // Spread the typed/dropped digits across boxes from here (covers fast typing).
  let idx = i
  for (const c of raw.split('')) {
    if (idx >= props.length) break
    digits.value[idx] = c
    idx++
  }
  target.value = digits.value[i] ?? ''
  focusBox(Math.min(idx, props.length - 1))
  emitValue()
}

function onKeydown(i: number, e: KeyboardEvent) {
  if (e.key === 'Backspace') {
    if (digits.value[i]) {
      digits.value[i] = ''
      emitValue()
    }
    else if (i > 0) {
      e.preventDefault()
      digits.value[i - 1] = ''
      focusBox(i - 1)
      emitValue()
    }
  }
  else if (e.key === 'ArrowLeft' && i > 0) {
    e.preventDefault()
    focusBox(i - 1)
  }
  else if (e.key === 'ArrowRight' && i < props.length - 1) {
    e.preventDefault()
    focusBox(i + 1)
  }
}

function onPaste(e: ClipboardEvent) {
  e.preventDefault()
  const text = (e.clipboardData?.getData('text') ?? '').replace(/\D/g, '').slice(0, props.length)
  if (!text) return
  digits.value = Array.from({ length: props.length }, (_, i) => text[i] ?? '')
  focusBox(Math.min(text.length, props.length - 1))
  emitValue()
}

function selectOnFocus(e: Event) {
  (e.target as HTMLInputElement).select()
}

onMounted(() => {
  if (props.autofocus) focusBox(0)
})
</script>

<template>
  <div class="passcode-grid" :style="{ gridTemplateColumns: `repeat(${length}, minmax(0, 1fr))` }" @paste="onPaste">
    <input
      v-for="(_, i) in length"
      :key="i"
      :ref="el => setRef(el, i)"
      :value="digits[i]"
      type="text"
      inputmode="numeric"
      autocomplete="off"
      maxlength="1"
      :aria-label="`Passcode digit ${i + 1}`"
      class="passcode-box"
      :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-secondary)' }"
      @beforeinput="onBeforeInput"
      @input="onInput(i, $event)"
      @keydown="onKeydown(i, $event)"
      @focus="selectOnFocus"
    >
  </div>
</template>

<style scoped>
.passcode-grid {
  display: grid;
  gap: 6px;
}

@media (min-width: 400px) {
  .passcode-grid { gap: 8px; }
}

.passcode-box {
  width: 100%;
  height: 52px;
  text-align: center;
  font-size: 19px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  border-width: 1px;
  border-style: solid;
  border-radius: 12px;
  outline: none;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.passcode-box:focus {
  border-color: var(--color-accent) !important;
  box-shadow: 0 0 0 3px var(--color-accent-soft);
}
</style>
