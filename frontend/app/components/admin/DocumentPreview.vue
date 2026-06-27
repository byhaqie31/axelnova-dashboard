<script setup lang="ts">
// Live document preview — renders the *actual* PDF (POST /documents/render-pdf,
// same renderer as the issued document) into the browser's PDF viewer, so it's
// true-A4 and pixel-accurate. Fills its container (use inside AdminDocumentPreviewModal).
// Generated on load + on demand (Refresh), not per-keystroke, so Chromium isn't
// launched on every edit.
const props = defineProps<{
  data: Record<string, any> | null
  loading?: boolean
  /** Show a close button in the header (when hosted in a modal). */
  closable?: boolean
}>()

const emit = defineEmits<{ close: [] }>()

const pdfUrl = ref('')
const generating = ref(false)
const stale = ref(false)
const failed = ref(false)
let blobUrl = ''

async function generate() {
  if (!props.data) return
  generating.value = true
  failed.value = false
  try {
    const blob = await $fetch<Blob>('/documents/render-pdf', { method: 'POST', body: props.data, responseType: 'blob' as any })
    if (blobUrl) URL.revokeObjectURL(blobUrl)
    blobUrl = URL.createObjectURL(blob as Blob)
    pdfUrl.value = `${blobUrl}#toolbar=0&navpanes=0&view=FitH`
    stale.value = false
  }
  catch {
    failed.value = true
  }
  finally {
    generating.value = false
  }
}

function openInTab() {
  if (blobUrl) window.open(blobUrl, '_blank', 'noopener')
}

watch(() => props.data, (d) => {
  if (!d) return
  if (!pdfUrl.value) generate()
  else stale.value = true
}, { deep: true, immediate: true })

onBeforeUnmount(() => { if (blobUrl) URL.revokeObjectURL(blobUrl) })
</script>

<template>
  <div class="flex flex-col h-full rounded-2xl border overflow-hidden" :style="{ background: 'var(--color-bg-secondary)', borderColor: 'var(--color-border)' }">
    <div class="flex items-center justify-between gap-3 px-4 py-2.5 border-b shrink-0" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)' }">
      <div class="flex items-center gap-2 min-w-0">
        <p class="text-[11px] font-semibold uppercase tracking-widest" style="color: var(--color-text-tertiary);">PDF preview</p>
        <span v-if="stale && !generating" class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full"
          :style="{ color: 'var(--color-warning)', background: 'var(--color-warning-soft, var(--color-bg-secondary))' }">Draft changed</span>
      </div>
      <div class="flex items-center gap-1.5 shrink-0">
        <button type="button" class="btn-pill btn-pill-ghost text-[11px]" style="height: 28px; padding: 0 12px;"
          :class="{ 'opacity-50': generating || !data }" :disabled="generating || !data" @click="generate">
          <UIcon :name="generating ? 'i-lucide-loader-circle' : 'i-lucide-refresh-cw'" class="size-3.5" :class="{ 'animate-spin': generating }" />
          {{ generating ? 'Generating…' : 'Refresh' }}
        </button>
        <button v-if="pdfUrl" type="button" class="btn-pill btn-pill-ghost text-[11px]" style="height: 28px; padding: 0 12px;" @click="openInTab">
          <UIcon name="i-lucide-external-link" class="size-3.5" /> Open
        </button>
        <button v-if="closable" type="button" class="btn-pill btn-pill-ghost text-[11px]" style="height: 28px; width: 28px; padding: 0;" aria-label="Close" @click="emit('close')">
          <UIcon name="i-lucide-x" class="size-4" />
        </button>
      </div>
    </div>

    <div class="relative flex-1 min-h-0">
      <iframe
        v-if="pdfUrl"
        :src="pdfUrl"
        title="Document preview"
        class="w-full h-full border-0"
        style="background: #e9e7ee;"
      />
      <div v-else class="absolute inset-0 grid place-items-center text-center px-6" style="color: var(--color-text-tertiary);">
        <div>
          <UIcon :name="generating ? 'i-lucide-loader-circle' : (failed ? 'i-lucide-triangle-alert' : 'i-lucide-file-text')"
            class="size-7 mx-auto mb-2" :class="{ 'animate-spin': generating }" />
          <p class="text-[12px]">
            {{ generating ? 'Generating preview…' : failed ? 'Couldn’t render the preview.' : (data ? 'Generating preview…' : 'Nothing to preview yet.') }}
          </p>
          <button v-if="failed" type="button" class="btn-pill btn-pill-ghost text-[11px] mt-3" style="height: 28px; padding: 0 14px;" @click="generate">Try again</button>
        </div>
      </div>

      <div v-if="generating && pdfUrl" class="absolute top-2 right-2 text-[10px] px-2 py-1 rounded-full"
        :style="{ background: 'var(--color-bg-elevated)', color: 'var(--color-text-secondary)', boxShadow: 'var(--shadow-card-hover)' }">
        Updating…
      </div>
    </div>
  </div>
</template>
