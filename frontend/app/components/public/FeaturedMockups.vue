<script setup lang="ts">
import { useMockupRegistry, type RegistryMockup } from '~/composables/useMockupRegistry'
import MockupMarqueeRow from '~/components/public/MockupMarqueeRow.vue'
import MockupPreviewModal from '~/components/public/MockupPreviewModal.vue'

// Dual-row counter-flow marquee over ALL public mockups from the axelnova.my
// registry. Rows are split alternately so they stay balanced as the registry
// grows; the top row drifts left, the bottom row right. Clicking a card opens
// the in-page live preview popup; "Visit live" on the card hover skips out.
const { mockups, loading, load } = useMockupRegistry(Infinity)
const previewing = ref<RegistryMockup | null>(null)

onMounted(load)

const rowA = computed(() => mockups.value.filter((_, i) => i % 2 === 0))
const rowB = computed(() => mockups.value.filter((_, i) => i % 2 === 1))

// Holding either row stops both — a single row drifting on while its partner
// sits still reads as a glitch rather than a deliberate pause.
const heldA = ref(false)
const heldB = ref(false)
const paused = computed(() => heldA.value || heldB.value)
</script>

<template>
  <div>
    <!-- edge fades hint the rows continue off-canvas (modal Teleports out) -->
    <div class="mmask space-y-5">
      <!-- loading skeletons keep both rows' shape while the registry arrives -->
      <template v-if="loading">
        <div v-for="row in 2" :key="`skrow-${row}`" class="flex gap-5 overflow-hidden">
          <div
            v-for="n in 5"
            :key="`skeleton-${row}-${n}`"
            class="shrink-0 w-60 sm:w-70 rounded-2xl border overflow-hidden"
            :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
          >
            <div class="h-7 border-b" style="border-color: var(--color-border); background: var(--color-bg-secondary);" />
            <div class="mskel aspect-3/2" />
            <div class="p-3.5">
              <div class="mskel h-4 w-2/3 rounded-md mb-1.5" />
              <div class="mskel h-3 w-1/2 rounded-md" />
            </div>
          </div>
        </div>
      </template>

      <template v-else>
        <MockupMarqueeRow
          v-if="rowA.length"
          :mockups="rowA"
          :direction="-1"
          :paused="paused"
          label="Featured mockups — row 1 of 2"
          @preview="previewing = $event"
          @hold="heldA = $event"
        />
        <MockupMarqueeRow
          v-if="rowB.length"
          :mockups="rowB"
          :direction="1"
          :paused="paused"
          label="Featured mockups — row 2 of 2"
          @preview="previewing = $event"
          @hold="heldB = $event"
        />
      </template>
    </div>

    <MockupPreviewModal :mockup="previewing" @close="previewing = null" />
  </div>
</template>

<style scoped>
.mmask {
  -webkit-mask-image: linear-gradient(to right, transparent, black 5%, black 95%, transparent);
  mask-image: linear-gradient(to right, transparent, black 5%, black 95%, transparent);
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
  .mskel { animation: none; }
}
</style>
