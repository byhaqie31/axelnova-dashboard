<script setup lang="ts">
// The FAQ repeater of the blog editor: up to BLOG_FAQ_MAX plain-text question /
// answer rows with move up / down / remove. The parent owns the array through
// v-model (every change emits a new list — no mutation of the prop). The
// backend trims, strips tags and drops fully blank rows on save, and rejects a
// half-filled row with a field error, so there is no client-side gate here.
import { BLOG_FAQ_MAX, newFaq, type BlogFaq } from '~/data/blog'

const props = defineProps<{ modelValue: BlogFaq[] }>()
const emit = defineEmits<{ 'update:modelValue': [value: BlogFaq[]] }>()

const full = computed(() => props.modelValue.length >= BLOG_FAQ_MAX)

function inputValue(e: Event) {
  return (e.target as HTMLInputElement | HTMLTextAreaElement).value
}
function patch(i: number, p: Partial<BlogFaq>) {
  emit('update:modelValue', props.modelValue.map((f, j) => (j === i ? { ...f, ...p } : f)))
}
function add() {
  if (full.value) return
  emit('update:modelValue', [...props.modelValue, newFaq()])
}
function remove(i: number) {
  const f = props.modelValue[i]
  if ((f?.question || f?.answer) && !confirm(`Remove question ${i + 1}?`)) return
  emit('update:modelValue', props.modelValue.filter((_, j) => j !== i))
}
function swap(i: number, j: number) {
  if (j < 0 || j >= props.modelValue.length) return
  const next = [...props.modelValue]
  const moved = next[i]!
  next[i] = next[j]!
  next[j] = moved
  emit('update:modelValue', next)
}
</script>

<template>
  <div class="rounded-2xl border p-4 sm:p-5" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
    <div class="flex items-center gap-2 mb-1">
      <span class="text-[11px] font-semibold uppercase tracking-widest" :style="{ color: 'var(--color-text-tertiary)' }">Frequently asked questions</span>
      <span class="ml-auto text-[11px] tabular-nums" :style="{ color: full ? 'var(--color-warning)' : 'var(--color-text-tertiary)' }">{{ modelValue.length }} / {{ BLOG_FAQ_MAX }}</span>
    </div>
    <p class="text-[12px] mb-4" :style="{ color: 'var(--color-text-tertiary)' }">
      Shown after the last section and sent to search engines as FAQ schema. Three to six real questions a reader would ask, each answered directly in one to three sentences — plain text, no formatting.
    </p>

    <div v-if="modelValue.length" class="space-y-3">
      <div v-for="(f, i) in modelValue" :key="i" class="rounded-xl border p-3 sm:p-4" :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)' }">
        <div class="flex items-center gap-2 mb-2">
          <span class="text-[11px] font-semibold uppercase tracking-widest" :style="{ color: 'var(--color-text-tertiary)' }">Question {{ i + 1 }}</span>
          <div class="ml-auto flex items-center gap-1 max-md:gap-1.5">
            <button type="button" class="btn-table-action max-md:size-9! max-md:p-0!" :disabled="i === 0" aria-label="Move up" @click="swap(i, i - 1)">
              <UIcon name="i-lucide-chevron-up" class="size-3.5" />
            </button>
            <button type="button" class="btn-table-action max-md:size-9! max-md:p-0!" :disabled="i === modelValue.length - 1" aria-label="Move down" @click="swap(i, i + 1)">
              <UIcon name="i-lucide-chevron-down" class="size-3.5" />
            </button>
            <button type="button" class="btn-table-action is-danger max-md:h-9! max-md:px-3!" @click="remove(i)">
              <UIcon name="i-lucide-trash-2" class="size-3.5" />Remove
            </button>
          </div>
        </div>
        <input
          :value="f.question" type="text" maxlength="200" placeholder="How long does a customer portal take to build?"
          class="contact-input w-full font-medium mb-2"
          @input="patch(i, { question: inputValue($event) })"
        >
        <textarea
          :value="f.answer" rows="2" maxlength="1200" placeholder="Answer it directly in one to three sentences."
          class="contact-input w-full"
          @input="patch(i, { answer: inputValue($event) })"
        />
        <p class="text-[11px] text-right tabular-nums mt-1" :style="{ color: 'var(--color-text-tertiary)' }">{{ f.answer.length }}/1200</p>
      </div>
    </div>

    <button type="button" class="btn-table-action max-md:h-9! max-md:px-3!" :class="modelValue.length ? 'mt-3' : ''" :disabled="full" @click="add">
      <UIcon name="i-lucide-plus" class="size-3.5" />{{ full ? `Up to ${BLOG_FAQ_MAX} questions` : 'Add question' }}
    </button>
  </div>
</template>

<style scoped>
/* Mobile only (<768px) — desktop untouched. 16px inputs stop iOS focus-zoom. */
@media (max-width: 767.98px) {
  .contact-input { font-size: 16px; }
}
</style>
