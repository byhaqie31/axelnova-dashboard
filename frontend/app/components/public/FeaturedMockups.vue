<script setup lang="ts">
import { useMockupRegistry, type RegistryMockup } from '~/composables/useMockupRegistry'
import FeaturedMockupCard from '~/components/public/FeaturedMockupCard.vue'
import MockupPreviewModal from '~/components/public/MockupPreviewModal.vue'

// Live client prototypes from the axelnova.my registry, in the same snap
// carousel used for featured projects. Clicking a card opens the in-page
// live preview popup; "Visit live" on the card hover skips straight out.
const { mockups, loading, load } = useMockupRegistry()
const previewing = ref<RegistryMockup | null>(null)

onMounted(load)

// Reduced-motion users get instant jumps instead of smooth scrolling.
const { reduced } = useMotion()

const track = ref<HTMLElement | null>(null)
const atStart = ref(true)
const atEnd = ref(false)
const overflowing = ref(false) // only show arrows when the row actually scrolls

const updateArrows = () => {
  const el = track.value
  if (!el) return
  const max = el.scrollWidth - el.clientWidth
  overflowing.value = max > 4
  atStart.value = el.scrollLeft <= 4
  atEnd.value = el.scrollLeft >= max - 4
}

// One "page" ≈ one card + the gap; falls back to most of the viewport.
const step = () => {
  const el = track.value
  if (!el) return 0
  const first = el.firstElementChild as HTMLElement | null
  return first ? first.offsetWidth + 24 : el.clientWidth * 0.8
}

const go = (dir: 1 | -1) => {
  track.value?.scrollBy({ left: dir * step(), behavior: reduced ? 'auto' : 'smooth' })
}

let ro: ResizeObserver | undefined
onMounted(() => {
  updateArrows()
  // Card widths shift once the webfont swaps in; recheck then.
  document.fonts?.ready?.then(updateArrows)
  if (typeof ResizeObserver !== 'undefined' && track.value) {
    ro = new ResizeObserver(updateArrows)
    ro.observe(track.value)
  }
})
onUnmounted(() => ro?.disconnect())

watch(mockups, () => nextTick(updateArrows))
</script>

<template>
  <div class="relative">
    <!-- scroll track -->
    <div
      ref="track"
      role="group"
      aria-label="Featured mockups"
      tabindex="0"
      class="mcarousel flex items-stretch gap-6 overflow-x-auto snap-x snap-mandatory pb-3"
      @scroll.passive="updateArrows"
    >
      <!-- loading skeletons keep the row's shape while the registry arrives -->
      <template v-if="loading">
        <div
          v-for="n in 3"
          :key="`skeleton-${n}`"
          class="snap-start shrink-0 w-[86vw] sm:w-100 lg:w-105 rounded-2xl border overflow-hidden"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
        >
          <div class="h-9 border-b" style="border-color: var(--color-border); background: var(--color-bg-secondary);" />
          <div class="mskel aspect-3/2" />
          <div class="p-6">
            <div class="mskel h-5 w-2/3 rounded-md mb-3" />
            <div class="mskel h-3.5 w-full rounded-md mb-2" />
            <div class="mskel h-3.5 w-3/4 rounded-md" />
          </div>
        </div>
      </template>

      <template v-else>
        <FeaturedMockupCard
          v-for="m in mockups"
          :key="m.slug"
          :mockup="m"
          class="snap-start shrink-0 w-[86vw] sm:w-100 lg:w-105"
          @preview="previewing = m"
        />
      </template>
    </div>

    <!-- edge fades hint there's more to either side -->
    <div
      v-show="overflowing && !atStart"
      aria-hidden
      class="pointer-events-none absolute inset-y-0 left-0 w-10 z-20"
      style="background: linear-gradient(to right, var(--color-bg), transparent);"
    />
    <div
      v-show="overflowing && !atEnd"
      aria-hidden
      class="pointer-events-none absolute inset-y-0 right-0 w-10 z-20"
      style="background: linear-gradient(to left, var(--color-bg), transparent);"
    />

    <!-- arrows (desktop; touch users swipe) -->
    <button
      v-show="overflowing"
      type="button"
      aria-label="Previous mockups"
      :disabled="atStart"
      class="mnav mnav--prev"
      @click="go(-1)"
    >
      <UIcon name="i-lucide-chevron-left" class="size-5" />
    </button>
    <button
      v-show="overflowing"
      type="button"
      aria-label="Next mockups"
      :disabled="atEnd"
      class="mnav mnav--next"
      @click="go(1)"
    >
      <UIcon name="i-lucide-chevron-right" class="size-5" />
    </button>

    <MockupPreviewModal :mockup="previewing" @close="previewing = null" />
  </div>
</template>

<style scoped>
/* Hide the scrollbar — navigation is via arrows / swipe. */
.mcarousel {
  scrollbar-width: none;
  -ms-overflow-style: none;
}
.mcarousel::-webkit-scrollbar {
  display: none;
}

.mnav {
  position: absolute;
  top: 45%;
  transform: translateY(-50%);
  z-index: 30;
  display: none;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 9999px;
  background: var(--color-bg-elevated);
  border: 1px solid var(--color-border-strong);
  color: var(--color-text);
  box-shadow: var(--shadow-card-hover);
  transition: opacity 0.2s ease, transform 0.2s ease, background 0.2s ease;
}
.mnav--prev { left: 0.5rem; }
.mnav--next { right: 0.5rem; }

@media (min-width: 640px) {
  .mnav { display: inline-flex; }
}

.mnav:hover:not(:disabled) {
  transform: translateY(-50%) scale(1.06);
  background: var(--color-bg-secondary);
}
.mnav:disabled {
  opacity: 0;
  pointer-events: none;
}

.mskel {
  background: linear-gradient(100deg, var(--color-bg-secondary) 20%, var(--color-bg-elevated) 50%, var(--color-bg-secondary) 80%);
  background-size: 200% 100%;
  animation: mskel 1.4s ease-in-out infinite;
}
@keyframes mskel {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

@media (prefers-reduced-motion: reduce) {
  .mnav { transition: none; }
  .mskel { animation: none; }
}
</style>
