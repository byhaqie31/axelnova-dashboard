<script setup lang="ts">
// The /company hero's square box: the Axel Nova mark sitting directly in one
// named card — the CollabLockup card on its own, no partner, no ×, and no
// logo tile under the mark. It replaced the old hero's circular aurora halo
// around the mark: the box is the frame now, and the page title sits beside it.
//
// Decorative only: the H1 next to it carries the words, so the box is
// aria-hidden and takes no heading level.
//
// Entrance hooks for the page: `data-hero-card="card|chrome|plate|name"` — the
// "plate" is the mark's own footprint (no visible tile); clipped to it, the
// card's surface is the small box the mark first arrives in, which then
// expands into the card. The card never changes size during an entrance — grow
// the chrome with clip-path and move the plate with a transform, so nothing
// re-lays out mid-animation (MOTION rule 11).
</script>

<template>
  <div
    data-hero-card="card"
    class="company-hero-card flex flex-col items-center justify-center shrink-0"
    aria-hidden="true"
  >
    <span data-hero-card="chrome" class="company-hero-card-chrome" />
    <span
      data-hero-card="plate"
      class="company-hero-card-mark relative flex items-center justify-center shrink-0 size-32 sm:size-40 rounded-[28px]"
    >
      <img src="/favicon/web-app-manifest-512x512.png" alt="" class="size-full object-contain">
    </span>
    <span data-hero-card="name" class="company-hero-card-name relative">Axel Nova Ventures</span>
  </div>
</template>

<style scoped>
/* A square card around the plate: sized against the viewport on phones so it
   never overflows the page gutter, a share of the viewport from sm up. */
.company-hero-card {
  position: relative;
  width: min(15rem, calc(100vw - 3rem));
  aspect-ratio: 1;
  gap: 1rem;
  padding: 1.25rem;
  border-radius: 2rem;
}
@media (min-width: 640px) {
  .company-hero-card {
    width: clamp(17rem, 26vw, 21.25rem);
    gap: 1.25rem;
    padding: 1.75rem;
    border-radius: 2.5rem;
  }
}

/* The card's surface, as its own layer so an entrance can grow it out of the
   plate (clip-path) without the card itself changing size. */
.company-hero-card-chrome {
  position: absolute;
  inset: 0;
  border-radius: inherit;
  border: 1px solid var(--color-border);
  background: var(--color-bg-elevated);
  box-shadow: var(--shadow-md);
}

/* The mark on the card, no tile — a soft brand shadow gives it its own depth. */
.company-hero-card-mark img {
  filter:
    drop-shadow(0 6px 14px color-mix(in srgb, var(--grad-aurora-blue) 22%, transparent))
    drop-shadow(0 0 28px color-mix(in srgb, var(--grad-aurora-violet) 14%, transparent));
}

/* Set like CollabLockup's names so the two heroes read as one family. */
.company-hero-card-name {
  font-size: 15px;
  font-weight: 600;
  line-height: 1.2;
  letter-spacing: -0.015em;
  text-align: center;
  color: var(--color-text);
}
@media (min-width: 640px) {
  .company-hero-card-name { font-size: 21px; }
}

/* Hover — the card lifts on a deeper shadow and the mark swells a touch.
   Pointer devices only; the curve is MOTION's `power3.out`. */
@media (hover: hover) and (prefers-reduced-motion: no-preference) {
  .company-hero-card,
  .company-hero-card [data-hero-card="plate"],
  .company-hero-card-chrome {
    transition:
      transform 0.6s cubic-bezier(0.215, 0.61, 0.355, 1),
      box-shadow 0.6s cubic-bezier(0.215, 0.61, 0.355, 1),
      border-color 0.6s cubic-bezier(0.215, 0.61, 0.355, 1);
  }
  .company-hero-card:hover { transform: translateY(-6px); }
  .company-hero-card:hover [data-hero-card="plate"] { transform: scale(1.06); }
  .company-hero-card:hover .company-hero-card-chrome {
    box-shadow: var(--shadow-lg);
    border-color: var(--color-border-strong);
  }
}
</style>
