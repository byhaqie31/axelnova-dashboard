# Featured Mockups Marquee Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the manual snap-scroll "Featured mockups" carousel on the homepage with two GSAP-driven auto-scrolling marquee rows moving in opposite directions, using compact cards; the `SectionHeader` stays unchanged.

**Architecture:** `FeaturedMockups.vue` fetches ALL public registry mockups and splits them alternately into two rows, each rendered by a new `MockupMarqueeRow.vue` that duplicates its card set and drifts it with a `gsap.ticker` callback (px/s constant, wrap into `[-setWidth, 0)`). `FeaturedMockupCard.vue` gains a `compact` prop reusing all existing mShots logic. Reduced-motion users get plain scrollable strips with no duplicates.

**Tech Stack:** Nuxt 4 / Vue 3 / TypeScript, GSAP via `useMotion()` (`plugins/gsap.client.ts`), Tailwind v4 + design-token CSS variables, @nuxt/ui v4.

**Spec:** `docs/superpowers/specs/2026-07-24-featured-mockups-marquee-design.md`

## Global Constraints

- **NEVER commit or push.** The owner commits on request only (global rule overrides this plan template's commit steps). Each task ends at a verified-clean checkpoint instead.
- **No destructive DB commands** — frontend-only work; never touch the database.
- All colors via CSS design tokens (`var(--color-…)`) — no new hardcoded hex. (The three existing traffic-light dot hexes in `FeaturedMockupCard.vue` stay as-is.)
- Icons via `<UIcon name="i-lucide-…">` — never emojis.
- Content must never depend on JS to be visible (repo scroll-reveal rule): reduced-motion path renders plain scrollable rows.
- Branch: `feat/homepage-referral-band` (owner's single-branch workflow).
- No frontend unit-test runner exists in this repo — CI gates are `npm run typecheck` (vue-tsc) and `npm run lint` (ESLint), run inside the frontend container. These are the per-task verification cycle.
- Do NOT browser-screenshot to verify — the owner checks visually themselves.

Verification commands (run from repo root `/Users/BHQIMBP16/Developer/axelnova-dashboard`):

```bash
docker compose -f docker-compose.dev.yml exec frontend npm run typecheck
docker compose -f docker-compose.dev.yml exec frontend npm run lint
```

Expected: both exit 0. (If the containers aren't running, host-side `npm --prefix frontend run typecheck` / `lint` are acceptable — they don't need the DB.)

---

### Task 1: `compact` prop on `FeaturedMockupCard`

**Files:**
- Modify: `frontend/app/components/public/FeaturedMockupCard.vue`

**Interfaces:**
- Consumes: nothing new.
- Produces: `FeaturedMockupCard` accepts `compact?: boolean`. In compact mode the card renders chrome bar (`h-7`) + screenshot + name/type footer only; width is still set by the parent via `class`. Non-compact rendering is byte-for-byte unchanged. The `preview` emit is unchanged.

- [ ] **Step 1: Add the prop**

In the `<script setup>` block, change the props line:

```ts
const props = defineProps<{ mockup: RegistryMockup, compact?: boolean }>()
```

- [ ] **Step 2: Compact chrome bar**

Replace the chrome `<div>` (the one containing the traffic-light dots and the `{{ host }}` pill) with:

```html
      <!-- chrome -->
      <div
        class="flex items-center border-b"
        :class="compact ? 'gap-1.5 px-3 h-7' : 'gap-2 px-4 h-9'"
        style="border-color: var(--color-border); background: var(--color-bg-secondary);"
      >
        <span class="flex shrink-0" :class="compact ? 'gap-1' : 'gap-1.5'">
          <span class="rounded-full" :class="compact ? 'size-2' : 'size-2.5'" style="background:#ff5f57" />
          <span class="rounded-full" :class="compact ? 'size-2' : 'size-2.5'" style="background:#febc2e" />
          <span class="rounded-full" :class="compact ? 'size-2' : 'size-2.5'" style="background:#28c840" />
        </span>
        <span
          class="ml-2 flex-1 truncate text-center rounded-md border"
          :class="compact ? 'text-[10px] px-2' : 'text-[11px] px-3 py-0.5'"
          style="color: var(--color-text-secondary); background: var(--color-bg-elevated); border-color: var(--color-border);"
        >
          {{ host }}
        </span>
      </div>
```

- [ ] **Step 3: Compact "Visit live" pill**

Replace the `<a>` visit-live element's `class` attribute (keep `href`/`target`/`rel`/`aria-label`/`@click.stop`/`style` as they are):

```html
        <a
          :href="url"
          target="_blank"
          rel="noopener"
          :aria-label="`Open ${mockup.name} in a new tab`"
          class="mopen absolute z-20 inline-flex items-center gap-1.5 font-medium rounded-full opacity-0 group-hover:opacity-100 transition-all duration-300"
          :class="compact ? 'bottom-2 right-2 text-[11px] px-2.5 py-1' : 'bottom-3 right-3 text-[12px] px-3 py-1.5'"
          style="background: var(--color-accent); color:#fff; box-shadow: 0 6px 16px rgba(0,113,227,0.32);"
          @click.stop
        >
          Visit live <UIcon name="i-fluent-arrow-up-right-24-regular" class="size-3.5" />
        </a>
```

- [ ] **Step 4: Compact meta footer**

Immediately before the existing `<!-- META -->` `<div class="relative flex flex-col flex-1 p-6">`, add a compact variant and make the existing block the `v-else`:

```html
    <!-- META (compact: name + type only) -->
    <div v-if="compact" class="relative p-3.5">
      <h3 class="text-[14px] font-semibold tracking-tight line-clamp-1" style="color: var(--color-text);">
        {{ mockup.name }}
      </h3>
      <p class="mt-0.5 text-[12px] line-clamp-1" style="color: var(--color-text-tertiary);">
        {{ mockup.type }}
      </p>
    </div>

    <!-- META -->
    <div v-else class="relative flex flex-col flex-1 p-6">
      … (existing content, unchanged) …
    </div>
```

- [ ] **Step 5: Verify**

Run both verification commands (see Global Constraints). Expected: exit 0, no new warnings mentioning `FeaturedMockupCard`.

- [ ] **Step 6: Checkpoint — do NOT commit**

Leave changes in the working tree; the owner commits on request.

---

### Task 2: New `MockupMarqueeRow.vue`

**Files:**
- Create: `frontend/app/components/public/MockupMarqueeRow.vue`

**Interfaces:**
- Consumes: `FeaturedMockupCard` with `compact` prop (Task 1); `useMotion()` → `{ gsap, reduced }`; `RegistryMockup` type from `~/composables/useMockupRegistry`.
- Produces: `<MockupMarqueeRow :mockups="RegistryMockup[]" :direction="1 | -1" @preview="(m: RegistryMockup) => void" />`. `direction: -1` drifts left, `1` drifts right.

- [ ] **Step 1: Create the component**

Full file content:

```vue
<script setup lang="ts">
import type { RegistryMockup } from '~/composables/useMockupRegistry'
import FeaturedMockupCard from '~/components/public/FeaturedMockupCard.vue'

// One marquee row of the featured-mockups section: renders its card set twice
// and drifts the track with a GSAP ticker so the loop wraps seamlessly.
// `direction` is the sign of the drift (-1 left, +1 right). Reduced-motion
// users get a plain scrollable strip with no duplicate set — content never
// depends on JS to be reachable.
const props = defineProps<{ mockups: RegistryMockup[], direction: 1 | -1 }>()
const emit = defineEmits<{ preview: [mockup: RegistryMockup] }>()

const { gsap, reduced } = useMotion()

const track = ref<HTMLElement | null>(null)
const firstSet = ref<HTMLElement | null>(null)

// Constant px/s so the pace feels the same however many cards a row holds.
const PX_PER_SEC = 28
// Eased toward 0 on hover for the soft pause a CSS play-state flip can't do.
const speed = { factor: 1 }

let setWidth = 0
let pos = 0
let ro: ResizeObserver | undefined
let tick: ((time: number, delta: number) => void) | undefined

// Card widths shift when the webfont swaps in and on resize — re-measure.
const measure = () => { setWidth = firstSet.value?.offsetWidth ?? 0 }

onMounted(() => {
  if (reduced) return
  measure()
  document.fonts?.ready?.then(measure)
  if (typeof ResizeObserver !== 'undefined' && firstSet.value) {
    ro = new ResizeObserver(measure)
    ro.observe(firstSet.value)
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

// Touch pointers never fire a matching leave — only mice get the pause.
const onEnter = (e: PointerEvent) => {
  if (e.pointerType !== 'mouse' || reduced) return
  gsap.to(speed, { factor: 0, duration: 0.45, ease: 'power2.out', overwrite: true })
}
const onLeave = (e: PointerEvent) => {
  if (e.pointerType !== 'mouse' || reduced) return
  gsap.to(speed, { factor: 1, duration: 0.6, ease: 'power2.out', overwrite: true })
}
</script>

<template>
  <div
    :class="reduced ? 'mrow-scroll overflow-x-auto' : 'overflow-hidden'"
    @pointerenter="onEnter"
    @pointerleave="onLeave"
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
      <!-- duplicate set for the seamless wrap — hidden from AT and focus -->
      <div v-if="!reduced" class="flex gap-5 pr-5" aria-hidden="true" inert>
        <FeaturedMockupCard
          v-for="m in mockups"
          :key="`dup-${m.slug}`"
          :mockup="m"
          compact
          class="shrink-0 w-60 sm:w-70"
        />
      </div>
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
```

- [ ] **Step 2: Verify**

Run both verification commands. Expected: exit 0. (The component is not yet mounted anywhere — Task 3 wires it in.)

- [ ] **Step 3: Checkpoint — do NOT commit**

---

### Task 3: Rewrite `FeaturedMockups.vue` as the dual-row shell

**Files:**
- Modify: `frontend/app/components/public/FeaturedMockups.vue` (full rewrite)
- Unchanged (verify only): `frontend/app/pages/public/index.vue` — `SectionHeader` and `<FeaturedMockups class="reveal" />` stay exactly as they are.

**Interfaces:**
- Consumes: `MockupMarqueeRow` (Task 2), `useMockupRegistry(Infinity)`, `MockupPreviewModal` (unchanged — it Teleports to `body`, so the mask wrapper below cannot clip it).
- Produces: same external contract as before — a no-prop `<FeaturedMockups />` component.

- [ ] **Step 1: Replace the entire file**

Full new content:

```vue
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
        <MockupMarqueeRow :mockups="rowA" :direction="-1" @preview="previewing = $event" />
        <MockupMarqueeRow v-if="rowB.length" :mockups="rowB" :direction="1" @preview="previewing = $event" />
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
```

Deleted with the rewrite: snap classes, arrow buttons + `.mnav` styles, `atStart`/`atEnd`/`overflowing` tracking, `go()`/`step()`, the `v-show` gradient overlays (replaced by the mask), and the component-level ResizeObserver (each row measures itself now).

- [ ] **Step 2: Confirm the call site needs no change**

Run: `grep -n "FeaturedMockups" frontend/app/pages/public/index.vue`
Expected: the import line and `<FeaturedMockups class="reveal" />` — nothing else. Do not edit this file.

- [ ] **Step 3: Verify**

Run both verification commands. Expected: exit 0.

- [ ] **Step 4: Manual check note for the owner**

The owner verifies visually (no screenshots from us): light/dark, hover pause/resume, opposite drift directions, modal open on click, "Visit live" not opening the modal, reduced-motion (static scrollable rows, no duplicates), mobile width, and registry-failure fallback (6 items → rows of 3+3).

- [ ] **Step 5: Checkpoint — do NOT commit**

---

### Task 4: Update `PUBLIC-COMPONENTS.md`

**Files:**
- Modify: `docs/frontend/PUBLIC-COMPONENTS.md:30-49` (the `FeaturedMockups` section)

**Interfaces:** none — documentation only.

- [ ] **Step 1: Rewrite the section**

Replace the `### FeaturedMockups / FeaturedMockupCard / MockupPreviewModal` block (keep the heading line, the intro paragraph about the registry, and the `MockupPreviewModal` + `SectionHeader` bullets; replace the `FeaturedMockups` and `FeaturedMockupCard` bullets, and add a `MockupMarqueeRow` bullet):

```markdown
### `FeaturedMockups` / `MockupMarqueeRow` / `FeaturedMockupCard` / `MockupPreviewModal`
The "Featured mockups" section on the landing page (`pages/public/index.vue`) —
live client prototypes from the registry at `https://axelnova.my/projects/registry.json`
(CORS-open, fetched client-side; filtering/sorting/offline-fallback live in
`composables/useMockupRegistry.ts`, shared with the admin dashboard section).

- **`FeaturedMockups`** — dual-row counter-flow marquee shell: fetches ALL public
  mockups (`useMockupRegistry(Infinity)`), splits them alternately into two rows
  (even indices → top row drifting left, odd → bottom row drifting right), applies
  the edge-fade `mask-image`, renders compact-size loading skeletons, and owns the
  `previewing` state + modal mount.
- **`MockupMarqueeRow`** — one marquee row: renders its card set twice (duplicate
  set `aria-hidden` + `inert`) and drifts the track with a `gsap.ticker` callback
  at a constant px/s, wrapping into `[-setWidth, 0)`. Mouse hover eases the row's
  speed to 0 and back (touch keeps flowing). `prefers-reduced-motion` → plain
  scrollable strip, no duplicates, no ticker.
- **`FeaturedMockupCard`** — browser-frame card with an mShots live screenshot
  (retry-on-placeholder, tinted fallback tile from the registry `tint {h,c}` →
  `hsl(h, c*400%, 55%)`). Whole card opens the preview popup; the hover "Visit live"
  pill skips straight to the site. `compact` prop (used by the marquee) shrinks the
  chrome to `h-7` and trims meta to name + type — status badge, client, summary and
  tags render only in the full-size variant, where `draft` shows as **Concept**
  (internal lifecycle words stay off the marketing site).
- **`MockupPreviewModal`** — Teleported full-screen browser-window overlay with a live
  `<iframe>` of the mockup (axelnova.my pages send no `X-Frame-Options`). Esc/backdrop
  close, scroll lock, loading spinner, 15s timeout → "open live site" fallback panel.
- `SectionHeader` gained an optional `action.target: '_blank'` for the external
  "View all" → `https://axelnova.my/` (the root showcase; the admin dashboard's
  section keeps the `/projects/` listing).
```

- [ ] **Step 2: Grep for stale references**

Run: `grep -rn "snap carousel" docs/frontend/PUBLIC-COMPONENTS.md`
Expected: no matches after the edit.

- [ ] **Step 3: Checkpoint — do NOT commit**

Report completion to the owner and ask whether to commit.
