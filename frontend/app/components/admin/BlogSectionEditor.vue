<script setup lang="ts">
// One article section in the editor: heading + a Markdown-backed rich text
// body (Nuxt UI's Tiptap editor in `content-type="markdown"`, so what we store
// is plain Markdown the backend renders) + optional image URL / pull quote,
// with move up / down / remove. Reordering lives in the parent list.
import type { BlogSection } from '~/data/blog'

const props = defineProps<{ modelValue: BlogSection, index: number, count: number }>()
const emit = defineEmits<{
  'update:modelValue': [value: BlogSection]
  'move-up': []
  'move-down': []
  'remove': []
}>()

function patch(p: Partial<BlogSection>) {
  emit('update:modelValue', { ...props.modelValue, ...p })
}
const body = computed({
  get: () => props.modelValue.body_md,
  set: (v: string) => patch({ body_md: v }),
})
const showImage = ref(!!props.modelValue.image_url)
const showQuote = ref(!!props.modelValue.quote)

function inputValue(e: Event) {
  return (e.target as HTMLInputElement).value
}

// Compact toolbar — the article layout owns H2 (section headings), so the
// body offers H3 only. Nested arrays render as separated groups.
const toolbarItems = [
  [
    { kind: 'mark' as const, mark: 'bold' as const, icon: 'i-lucide-bold', label: 'Bold' },
    { kind: 'mark' as const, mark: 'italic' as const, icon: 'i-lucide-italic', label: 'Italic' },
    { kind: 'link' as const, icon: 'i-lucide-link', label: 'Link' },
  ],
  [
    { kind: 'heading' as const, level: 3 as const, icon: 'i-lucide-heading-3', label: 'Subheading' },
    { kind: 'bulletList' as const, icon: 'i-lucide-list', label: 'Bullet list' },
    { kind: 'orderedList' as const, icon: 'i-lucide-list-ordered', label: 'Numbered list' },
    { kind: 'blockquote' as const, icon: 'i-lucide-text-quote', label: 'Quote' },
    { kind: 'codeBlock' as const, icon: 'i-lucide-code', label: 'Code' },
  ],
]
</script>

<template>
  <div class="rounded-2xl border p-4 sm:p-5" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
    <div class="flex items-center gap-2 mb-3">
      <span class="text-[11px] font-semibold uppercase tracking-widest" :style="{ color: 'var(--color-text-tertiary)' }">Section {{ index + 1 }}</span>
      <div class="ml-auto flex items-center gap-1">
        <button type="button" class="btn-table-action" :disabled="index === 0" aria-label="Move up" @click="emit('move-up')">
          <UIcon name="i-lucide-chevron-up" class="size-3.5" />
        </button>
        <button type="button" class="btn-table-action" :disabled="index === count - 1" aria-label="Move down" @click="emit('move-down')">
          <UIcon name="i-lucide-chevron-down" class="size-3.5" />
        </button>
        <button type="button" class="btn-table-action is-danger" @click="emit('remove')">
          <UIcon name="i-lucide-trash-2" class="size-3.5" />Remove
        </button>
      </div>
    </div>

    <input
      :value="modelValue.heading" type="text" placeholder="Section heading" maxlength="120"
      class="contact-input w-full text-[16px] font-semibold mb-3"
      @input="patch({ heading: inputValue($event) })"
    >

    <UEditor
      v-slot="{ editor }"
      v-model="body"
      content-type="markdown"
      placeholder="Write this section…"
      class="blog-editor rounded-xl border overflow-hidden"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
    >
      <UEditorToolbar
        :editor="editor"
        :items="toolbarItems"
        layout="fixed"
        class="border-b px-2 py-1 flex-wrap"
        :style="{ borderColor: 'var(--color-border)' }"
      />
    </UEditor>

    <div class="flex flex-wrap gap-2 mt-3">
      <button v-if="!showImage" type="button" class="btn-table-action" @click="showImage = true">
        <UIcon name="i-lucide-image" class="size-3.5" />Add image
      </button>
      <button v-if="!showQuote" type="button" class="btn-table-action" @click="showQuote = true">
        <UIcon name="i-lucide-quote" class="size-3.5" />Add quote
      </button>
    </div>

    <div v-if="showImage" class="grid sm:grid-cols-2 gap-3 mt-3">
      <input
        :value="modelValue.image_url ?? ''" type="text" placeholder="Image URL (https://… or /path)" class="contact-input w-full"
        @input="patch({ image_url: inputValue($event) || null })"
      >
      <input
        :value="modelValue.image_alt ?? ''" type="text" placeholder="Image description (alt text)" maxlength="160" class="contact-input w-full"
        @input="patch({ image_alt: inputValue($event) || null })"
      >
    </div>

    <div v-if="showQuote" class="grid sm:grid-cols-[1fr_200px] gap-3 mt-3">
      <input
        :value="modelValue.quote ?? ''" type="text" placeholder="Pull quote" maxlength="500" class="contact-input w-full"
        @input="patch({ quote: inputValue($event) || null })"
      >
      <input
        :value="modelValue.quote_by ?? ''" type="text" placeholder="Attribution (optional)" maxlength="80" class="contact-input w-full"
        @input="patch({ quote_by: inputValue($event) || null })"
      >
    </div>
  </div>
</template>
