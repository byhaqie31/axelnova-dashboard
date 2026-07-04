<script setup lang="ts">
import { mockupUrl, mockupAccent, type RegistryMockup } from '~/composables/useMockupRegistry'

// Full-screen live preview of a mockup site, framed as a browser window.
// The mockup pages on axelnova.my send no X-Frame-Options/CSP frame rules,
// so they embed cleanly; if the iframe never loads (offline, future header
// change) a fallback panel offers the direct link instead.
const props = defineProps<{ mockup: RegistryMockup | null }>()
const emit = defineEmits<{ close: [] }>()

const url = computed(() => (props.mockup ? mockupUrl(props.mockup) : ''))
const host = computed(() => (props.mockup ? `axelnova.my/${props.mockup.slug}` : ''))

const frameLoaded = ref(false)
const frameFailed = ref(false)
const closeBtn = ref<HTMLButtonElement | null>(null)
let failTimer: ReturnType<typeof setTimeout> | undefined

// Fresh load state per mockup; give the iframe 15s before offering the
// direct link, and lock page scroll while the overlay is up.
watch(() => props.mockup, (m) => {
  clearTimeout(failTimer)
  frameLoaded.value = false
  frameFailed.value = false
  if (import.meta.client) {
    document.documentElement.style.overflow = m ? 'hidden' : ''
    if (m) {
      failTimer = setTimeout(() => { if (!frameLoaded.value) frameFailed.value = true }, 15_000)
      nextTick(() => closeBtn.value?.focus())
    }
  }
})

function onFrameLoad() {
  frameLoaded.value = true
  clearTimeout(failTimer)
}

onKeyStroke('Escape', () => { if (props.mockup) emit('close') })

onUnmounted(() => {
  clearTimeout(failTimer)
  if (import.meta.client) document.documentElement.style.overflow = ''
})
</script>

<template>
  <Teleport to="body">
    <Transition name="mockup-modal">
      <div
        v-if="mockup"
        class="fixed inset-0 z-[70] flex items-center justify-center p-3 sm:p-6 lg:p-10"
        role="dialog"
        aria-modal="true"
        :aria-label="`Preview of ${mockup.name}`"
      >
        <div
          class="absolute inset-0"
          style="background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);"
          @click="emit('close')"
        />

        <!-- browser window -->
        <div
          class="mwindow relative w-full max-w-[1200px] h-full max-h-[88vh] rounded-2xl border overflow-hidden flex flex-col shadow-2xl"
          style="background: var(--color-bg); border-color: var(--color-border-strong);"
        >
          <!-- chrome -->
          <div
            class="flex items-center gap-2 sm:gap-3 px-3 sm:px-4 h-12 border-b shrink-0"
            style="border-color: var(--color-border); background: var(--color-bg-secondary);"
          >
            <span class="hidden sm:flex gap-1.5 shrink-0" aria-hidden>
              <span class="size-3 rounded-full" style="background:#ff5f57" />
              <span class="size-3 rounded-full" style="background:#febc2e" />
              <span class="size-3 rounded-full" style="background:#28c840" />
            </span>

            <span
              class="flex-1 min-w-0 truncate text-center text-[12px] px-3 py-1 rounded-lg border"
              style="color: var(--color-text-secondary); background: var(--color-bg-elevated); border-color: var(--color-border);"
            >
              {{ host }}
            </span>

            <a
              :href="url"
              target="_blank"
              rel="noopener"
              class="shrink-0 inline-flex items-center gap-1.5 text-[12px] font-medium px-3 py-1.5 rounded-full transition-opacity hover:opacity-85"
              style="background: var(--color-accent); color: #fff;"
            >
              <span class="hidden sm:inline">Open live</span>
              <UIcon name="i-lucide-arrow-up-right" class="size-3.5" />
            </a>

            <button
              ref="closeBtn"
              type="button"
              aria-label="Close preview"
              class="shrink-0 inline-flex items-center justify-center size-8 rounded-full border transition-colors hover:bg-(--color-bg-elevated)"
              :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text-secondary)' }"
              @click="emit('close')"
            >
              <UIcon name="i-lucide-x" class="size-4" />
            </button>
          </div>

          <!-- live site -->
          <div class="relative flex-1 min-h-0">
            <!-- shimmer while the site boots -->
            <div
              v-if="!frameLoaded && !frameFailed"
              class="absolute inset-0 flex flex-col items-center justify-center gap-3"
              style="background: var(--color-bg-secondary);"
            >
              <span aria-hidden class="mwshimmer absolute inset-0" />
              <UIcon name="i-lucide-loader-circle" class="mwspin size-6 relative" :style="{ color: mockupAccent(mockup) }" />
              <p class="text-[13px] relative" style="color: var(--color-text-secondary);">Loading live preview…</p>
            </div>

            <!-- fallback: the frame never arrived -->
            <div
              v-if="frameFailed"
              class="absolute inset-0 flex flex-col items-center justify-center gap-4 text-center p-6"
              :style="{ background: `radial-gradient(120% 120% at 50% 0%, ${mockupAccent(mockup, 0.12)} 0%, transparent 60%), var(--color-bg-secondary)` }"
            >
              <UIcon name="i-lucide-monitor-x" class="size-10" :style="{ color: mockupAccent(mockup) }" />
              <p class="text-[14px] max-w-sm" style="color: var(--color-text-secondary);">
                The live preview didn't load — you can still open the site directly.
              </p>
              <a :href="url" target="_blank" rel="noopener" class="btn-pill btn-pill-accent text-[13px]">
                Visit {{ mockup.name }} <UIcon name="i-lucide-arrow-up-right" class="size-4" />
              </a>
            </div>

            <iframe
              v-if="!frameFailed"
              :src="url"
              :title="`Live preview of ${mockup.name}`"
              class="absolute inset-0 size-full border-0 transition-opacity duration-300"
              :style="{ opacity: frameLoaded ? 1 : 0 }"
              allowfullscreen
              @load="onFrameLoad"
            />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.mockup-modal-enter-active,
.mockup-modal-leave-active {
  transition: opacity 0.22s ease;
}
.mockup-modal-enter-active .mwindow {
  transition: transform 0.22s cubic-bezier(0.33, 1, 0.68, 1);
}
.mockup-modal-enter-from,
.mockup-modal-leave-to {
  opacity: 0;
}
.mockup-modal-enter-from .mwindow {
  transform: translateY(10px) scale(0.985);
}

.mwshimmer {
  background: linear-gradient(100deg, transparent 20%, var(--color-bg-elevated) 50%, transparent 80%);
  background-size: 200% 100%;
  animation: mwshimmer 1.4s ease-in-out infinite;
}
@keyframes mwshimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

.mwspin {
  animation: mwspin 0.9s linear infinite;
}
@keyframes mwspin {
  to { transform: rotate(360deg); }
}

@media (prefers-reduced-motion: reduce) {
  .mockup-modal-enter-active,
  .mockup-modal-leave-active,
  .mockup-modal-enter-active .mwindow {
    transition: none;
  }
  .mwshimmer, .mwspin { animation: none; }
}
</style>
