<script setup lang="ts">
import type { RegistryMockup } from '~/composables/useMockupRegistry'
import FeaturedMockupCard from '~/components/public/FeaturedMockupCard.vue'

// One marquee row of the featured-mockups section: renders its card set twice
// and drifts the track with a GSAP ticker so the loop wraps seamlessly.
// `direction` is the sign of the drift (-1 left, +1 right). Reduced-motion
// users get a plain scrollable strip with no duplicate set — content never
// depends on JS to be reachable.
//
// The row does NOT decide when to stop: it reports whether it is being held
// (hovered or focused) via `hold`, and drifts or stops per the `paused` prop.
// The parent ORs both rows' holds together so touching one stops the pair.
const props = defineProps<{
  mockups: RegistryMockup[]
  direction: 1 | -1
  label?: string
  paused?: boolean
}>()
const emit = defineEmits<{ preview: [mockup: RegistryMockup], hold: [held: boolean] }>()

const { gsap, reduced } = useMotion()

const rowEl = ref<HTMLElement | null>(null)
const track = ref<HTMLElement | null>(null)
const firstSet = ref<HTMLElement | null>(null)

// Constant px/s so the pace feels the same however many cards a row holds.
const PX_PER_SEC = 28
// Eased toward 0 on hover for the soft pause a CSS play-state flip can't do.
const speed = { factor: 1 }

// How many duplicate sets follow `firstSet` — derived from measurement so the
// track always covers the container + one extra set, even on wide viewports
// where a single duplicate would leave a gap sweeping through the row.
const copies = ref(1)
const MAX_COPIES = 6

let setWidth = 0
let pos = 0
let ro: ResizeObserver | undefined
let tick: ((time: number, delta: number) => void) | undefined

// Card widths shift when the webfont swaps in and on resize — re-measure.
const measure = () => {
  setWidth = firstSet.value?.offsetWidth ?? 0
  const containerWidth = rowEl.value?.clientWidth ?? 0
  copies.value = setWidth
    ? Math.min(MAX_COPIES, Math.max(1, Math.ceil(containerWidth / setWidth)))
    : 1
}

onMounted(() => {
  if (reduced) return
  measure()
  document.fonts?.ready?.then(measure)
  if (typeof ResizeObserver !== 'undefined') {
    ro = new ResizeObserver(measure)
    if (firstSet.value) ro.observe(firstSet.value)
    if (rowEl.value) ro.observe(rowEl.value)
  }
  tick = (_time, delta) => {
    if (!setWidth || !track.value) return
    pos += props.direction * PX_PER_SEC * speed.factor * (delta / 1000)
    // Wrap into [-setWidth, 0): x and x ± setWidth are visually identical.
    pos = ((pos % setWidth) + setWidth) % setWidth - setWidth
    gsap.set(track.value, { x: pos })
  }
  gsap.ticker.add(tick)
})

onUnmounted(() => {
  if (tick) gsap.ticker.remove(tick)
  ro?.disconnect()
})

// Eased rather than cut so the pair stops and restarts together softly.
watch(() => props.paused, (isPaused) => {
  if (reduced) return
  gsap.to(speed, {
    factor: isPaused ? 0 : 1,
    duration: isPaused ? 0.45 : 0.6,
    ease: 'power2.out',
    overwrite: true,
  })
})

// Pointer and keyboard hold the row independently — releasing one while the
// other is still active must not restart the drift.
const hovering = ref(false)
const focusing = ref(false)
watch(
  () => hovering.value || focusing.value,
  held => emit('hold', held),
)

// Touch pointers never fire a matching leave — only mice get the pause.
const onEnter = (e: PointerEvent) => {
  if (e.pointerType !== 'mouse' || reduced) return
  hovering.value = true
}
const onLeave = (e: PointerEvent) => {
  if (e.pointerType !== 'mouse' || reduced) return
  hovering.value = false
}

// Keyboard focus holds the row too — released only once focus leaves the row
// entirely, not when it moves between cards inside it.
const onFocusIn = () => {
  if (reduced) return
  focusing.value = true
}
const onFocusOut = (e: FocusEvent) => {
  if (reduced) return
  const next = e.relatedTarget as Node | null
  if (!next || !rowEl.value?.contains(next)) focusing.value = false
}
</script>

<template>
  <div
    ref="rowEl"
    role="group"
    :aria-label="label"
    :class="reduced ? 'mrow-scroll overflow-x-auto' : 'overflow-x-clip overflow-y-clip'"
    @pointerenter="onEnter"
    @pointerleave="onLeave"
    @focusin="onFocusIn"
    @focusout="onFocusOut"
  >
    <div ref="track" class="flex w-max will-change-transform">
      <div ref="firstSet" class="flex gap-5 pr-5">
        <FeaturedMockupCard
          v-for="m in mockups"
          :key="m.slug"
          :mockup="m"
          compact
          class="shrink-0 w-60 sm:w-70"
          @preview="emit('preview', m)"
        />
      </div>
      <!-- duplicate sets for the seamless wrap — hidden from AT and focus -->
      <template v-if="!reduced">
        <div v-for="c in copies" :key="c" class="flex gap-5 pr-5" aria-hidden="true" inert>
          <FeaturedMockupCard
            v-for="m in mockups"
            :key="`dup-${c}-${m.slug}`"
            :mockup="m"
            compact
            class="shrink-0 w-60 sm:w-70"
          />
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
/* Reduced-motion fallback strip: swipe/scroll, no visible scrollbar. */
.mrow-scroll {
  scrollbar-width: none;
  -ms-overflow-style: none;
}
.mrow-scroll::-webkit-scrollbar {
  display: none;
}
</style>
