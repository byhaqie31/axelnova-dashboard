<script setup lang="ts">
// A "Preview" button that pops the live PDF preview in a centered overlay —
// keeps the editing surface clean (no permanent preview pane). The PDF only
// renders once the modal opens (the child auto-generates on mount).
withDefaults(defineProps<{
  data: Record<string, any> | null
  label?: string
  disabled?: boolean
  variant?: 'ghost' | 'primary' | 'preview'
  /** Full-width button (for stacked sidebars). */
  block?: boolean
}>(), {
  label: 'Preview',
  disabled: false,
  // Soft light-orange by default so Preview reads distinct from the neutral
  // "View PDF" beside it. Callers can still pass 'ghost'/'primary'.
  variant: 'preview',
  block: false,
})

const variantClass = {
  primary: 'btn-pill-primary',
  ghost: 'btn-pill-ghost',
  preview: 'btn-pill-preview',
}

const open = ref(false)

onKeyStroke('Escape', () => { if (open.value) open.value = false })
</script>

<template>
  <button
    type="button"
    class="btn-pill"
    :class="[variantClass[variant], block ? 'w-full justify-center text-[13px]' : 'text-[12px]']"
    :style="block ? undefined : { height: '34px', padding: '0 16px' }"
    :disabled="disabled"
    @click="open = true"
  >
    <UIcon name="i-lucide-eye" class="size-4" /> {{ label }}
  </button>

  <Teleport to="body">
    <Transition name="preview-modal">
      <div
v-if="open" class="fixed inset-0 z-[60] flex items-center justify-center p-3 sm:p-6"
        @click.self="open = false">
        <div class="absolute inset-0" style="background: rgba(0,0,0,0.55); backdrop-filter: blur(2px);" @click="open = false" />
        <div class="relative w-full max-w-[900px] h-[92vh] shadow-2xl" @click.stop>
          <AdminDocumentPreview :data="data" closable class="h-full" @close="open = false" />
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.preview-modal-enter-active,
.preview-modal-leave-active {
  transition: opacity 0.18s ease;
}
.preview-modal-enter-from,
.preview-modal-leave-to {
  opacity: 0;
}
</style>
