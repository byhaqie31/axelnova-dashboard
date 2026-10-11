<script setup lang="ts">
// "Axel Nova × {partner}" — the two studio marks with ONE × between them.
// Each mark sits on a .collab-logo-plate (a light tile in both themes, so a
// partner logo in dark ink stays legible on the navy dark-mode page).
//
// `named` wraps each plate in a theme-aware card with the studio name under
// it — the partner-page hero, where the pair IS the page heading (pass
// as="h1"; its text reads "Axel Nova × {partner}"). Named cards lift on hover
// and the × turns a quarter (CSS only, after any entrance has let go).
//
// Entrance hooks for the page: `data-lockup="left|x|right"` on the halves,
// `data-lockup-plate` on each tile, `data-lockup-chrome` on each card's
// surface layer, `data-lockup-name` on each name. The card never changes
// size during an entrance — grow the chrome with clip-path and move the plate
// with a transform, so nothing re-lays out mid-animation.
const props = withDefaults(defineProps<{
  partnerName: string
  partnerSymbol: string | null
  size?: 'md' | 'lg'
  named?: boolean
  as?: 'div' | 'h1'
}>(), { size: 'md', named: false, as: 'div' })

const lg = computed(() => props.size === 'lg')
const plateClass = computed(() => lg.value ? 'size-20 sm:size-28 rounded-3xl' : 'size-14 rounded-2xl')
// A bare (unnamed) lockup is one picture; a named one is real heading text.
const pictureAttrs = computed(() => props.named
  ? {}
  : { 'role': 'img', 'aria-label': `Axel Nova Ventures × ${props.partnerName}` })
</script>

<template>
  <component
    :is="as"
    class="inline-flex items-center"
    :class="[lg ? 'gap-3 sm:gap-7' : 'gap-3', named && 'collab-lockup-named']"
    v-bind="pictureAttrs"
  >
    <span
      data-lockup="left"
      class="flex flex-col items-center shrink-0"
      :class="named && 'collab-lockup-card self-stretch'"
    >
      <span v-if="named" data-lockup-chrome class="collab-lockup-chrome" aria-hidden="true" />
      <span data-lockup-plate class="collab-logo-plate relative flex items-center justify-center shrink-0" :class="plateClass">
        <!-- The Axel Nova mark carries its own padding; the partner symbol is
             sized down to match its visual weight. -->
        <img src="/favicon/web-app-manifest-512x512.png" alt="" class="size-full object-contain">
      </span>
      <span v-if="named" data-lockup-name class="collab-lockup-name relative">Axel Nova</span>
    </span>

    <span
      data-lockup="x"
      :aria-hidden="named ? undefined : 'true'"
      class="collab-lockup-x text-gradient font-light leading-none"
      :class="lg ? 'text-4xl sm:text-6xl' : 'text-3xl'"
    >×</span>

    <span
      data-lockup="right"
      class="flex flex-col items-center shrink-0"
      :class="named && 'collab-lockup-card self-stretch'"
    >
      <span v-if="named" data-lockup-chrome class="collab-lockup-chrome" aria-hidden="true" />
      <span data-lockup-plate class="collab-logo-plate relative flex items-center justify-center shrink-0" :class="plateClass">
        <img v-if="partnerSymbol" :src="partnerSymbol" alt="" class="w-[74%] object-contain">
        <span v-else class="text-[22px] font-semibold" style="color: var(--color-accent);">{{ partnerName.charAt(0) }}</span>
      </span>
      <span v-if="named" data-lockup-name class="collab-lockup-name relative">{{ partnerName }}</span>
    </span>
  </component>
</template>

<style scoped>
/* The named card: a theme-aware surface around the light logo plate. Equal
   widths keep the × dead centre whatever the names' lengths; on phones the
   width also yields to the viewport so two cards + the × fit a 320px screen. */
.collab-lockup-card {
  position: relative;
  width: min(8.25rem, calc(50vw - 3rem));
  gap: 0.875rem;
  padding: 1rem 0.75rem 1.125rem;
  border-radius: 1.75rem;
}
@media (min-width: 640px) {
  .collab-lockup-card {
    width: 15rem;
    gap: 1.25rem;
    padding: 1.5rem 1.25rem 1.625rem;
    border-radius: 2rem;
  }
}
/* The card's surface, as its own layer so an entrance can grow it out of the
   tile (clip-path) without the card itself changing size. */
.collab-lockup-chrome {
  position: absolute;
  inset: 0;
  border-radius: inherit;
  border: 1px solid var(--color-border);
  background: var(--color-bg-elevated);
  box-shadow: var(--shadow-md);
}

/* Reads as part of the h1, so pin the tracking: main.css's unlayered `h1 {}`
   rule (-0.045em) would otherwise squeeze the names. */
.collab-lockup-name {
  font-size: 15px;
  font-weight: 600;
  line-height: 1.2;
  letter-spacing: -0.015em;
  text-align: center;
  color: var(--color-text);
}
@media (min-width: 640px) {
  .collab-lockup-name { font-size: 21px; }
}

/* Hover — the card lifts on a deeper shadow, its mark swells a touch, and the
   × makes a quarter turn while either card is hovered (× is symmetric, so it
   lands looking the same — the turn is the flourish). Pointer devices only;
   the curve is MOTION's `power3.out`. */
@media (hover: hover) and (prefers-reduced-motion: no-preference) {
  .collab-lockup-card,
  .collab-lockup-card [data-lockup-plate],
  .collab-lockup-chrome,
  .collab-lockup-named .collab-lockup-x {
    transition:
      transform 0.6s cubic-bezier(0.215, 0.61, 0.355, 1),
      box-shadow 0.6s cubic-bezier(0.215, 0.61, 0.355, 1),
      border-color 0.6s cubic-bezier(0.215, 0.61, 0.355, 1);
  }
  .collab-lockup-card:hover { transform: translateY(-6px); }
  .collab-lockup-card:hover [data-lockup-plate] { transform: scale(1.06); }
  .collab-lockup-card:hover .collab-lockup-chrome {
    box-shadow: var(--shadow-lg);
    border-color: var(--color-border-strong);
  }
  .collab-lockup-named:has(.collab-lockup-card:hover) .collab-lockup-x {
    transform: rotate(90deg) scale(1.08);
  }
}
</style>
