<script setup lang="ts">
definePageMeta({ layout: 'public' })

const stats = [
  { label: 'Registered Name', value: 'Axel Nova Ventures' },
  { label: 'Registration No.', value: '202603119899 (CA0420977-U)' },
  { label: 'Established', value: '2026' },
  { label: 'Headquarters', value: 'Kuala Lumpur, Malaysia' },
  { label: 'Focus', value: 'Design & Technology' },
  { label: 'Stage', value: 'Early — building boldly' },
]

const pillars = [
  {
    icon: 'i-lucide-layers',
    title: 'Design-first thinking',
    description: 'Every system begins with understanding the person who will use it — not the technical constraints.',
  },
  {
    icon: 'i-lucide-cpu',
    title: 'Engineering with intention',
    description: 'Clean, scalable systems that don\'t just work — they endure.',
  },
  {
    icon: 'i-lucide-heart',
    title: 'Human-centered by default',
    description: 'Technology built around how people actually live, think, and feel.',
  },
]

useScrollReveal('.reveal')
</script>

<template>
  <div class="max-w-7xl mx-auto px-6 pt-24 pb-32">

    <!-- Hero -->
    <section class="min-h-[58vh] grid lg:grid-cols-[minmax(0,1fr)_auto] gap-12 lg:gap-20 items-center mb-32">
      <div class="order-2 lg:order-1">
        <p class="eyebrow mb-6">Axel Nova Ventures</p>
        <h1
          class="text-[clamp(34px,5vw,68px)] font-semibold tracking-tighter leading-[1.06] mb-8 max-w-4xl"
          style="color: var(--color-text);"
        >
          Technology should not only<br class="hidden sm:block" />
          function well —<span class="text-gradient"> it should feel meaningful.</span>
        </h1>
        <p class="text-[17px] leading-relaxed max-w-lg" style="color: var(--color-text-secondary);">
          Built in Malaysia. Designed for the world. A company shaped by design, systems thinking,
          and a quiet refusal to build anything ordinary.
        </p>
      </div>

      <!-- Logo presentation -->
      <div class="order-1 lg:order-2 relative flex items-center justify-center mx-auto lg:mx-0">
        <span aria-hidden class="hero-logo-aurora" />
        <span aria-hidden class="hero-logo-orbit" />
        <img
          src="/favicon/web-app-manifest-512x512.png"
          alt=""
          aria-hidden="true"
          class="relative size-52 sm:size-64 lg:size-72 object-contain hero-logo-mark"
        />
      </div>
    </section>

    <!-- Origin -->
    <section class="mb-32 reveal">
      <div class="grid lg:grid-cols-[1fr_320px] gap-10 lg:gap-16 items-start">
        <div class="space-y-5">
          <p class="eyebrow mb-2">Origin</p>
          <h2 class="text-3xl font-semibold tracking-tight mb-8" style="color: var(--color-text);">
            Born from a simple belief.
          </h2>
          <p class="text-[17px] leading-[1.65]" style="color: var(--color-text);">
            Axel Nova Ventures was born from a simple belief: technology should not only function well —
            it should feel meaningful.
          </p>
          <p class="text-[17px] leading-[1.65]" style="color: var(--color-text-secondary);">
            Built by Qie, a UI/UX-focused software engineer from Malaysia, the company reflects a journey
            shaped by design, systems thinking, travel, and constant self-evolution. Years spent building
            fintech platforms and digital experiences revealed one thing clearly: most systems are created
            to work, but very few are created to truly connect with people.
          </p>
          <p class="text-[20px] font-semibold tracking-tight pt-2" style="color: var(--color-text);">
            Axel Nova Ventures exists to bridge that gap.
          </p>
        </div>

        <!-- Stats aside -->
        <div
          class="rounded-3xl border p-7 space-y-5 lg:sticky lg:top-28"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
        >
          <p class="text-[11px] font-medium uppercase tracking-widest" style="color: var(--color-text-tertiary);">
            At a glance
          </p>
          <div class="divide-y" :style="{ '--tw-divide-opacity': 1, borderColor: 'var(--color-border)' }">
            <div
              v-for="s in stats"
              :key="s.label"
              class="py-4 first:pt-0 last:pb-0"
              :style="{ borderColor: 'var(--color-border)' }"
            >
              <p class="text-[11px] font-medium uppercase tracking-wide mb-1" style="color: var(--color-text-tertiary);">
                {{ s.label }}
              </p>
              <p class="text-[15px] font-medium" style="color: var(--color-text);">{{ s.value }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- The Name -->
    <section class="mb-32 reveal">
      <p class="eyebrow mb-2">The Name</p>
      <h2 class="text-3xl font-semibold tracking-tight mb-10" style="color: var(--color-text);">
        Every word carries a meaning.
      </h2>

      <div class="grid md:grid-cols-2 gap-5 mb-5">
        <!-- Axel -->
        <div
          class="rounded-3xl border p-8 md:p-10 relative overflow-hidden"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
        >
          <span
            aria-hidden
            class="absolute -right-2 -top-3 text-[140px] font-bold tracking-tighter leading-none select-none pointer-events-none"
            style="color: var(--color-text); opacity: 0.04;"
          >A</span>
          <p class="eyebrow mb-5">Axel</p>
          <p class="text-[24px] font-semibold tracking-tight leading-snug mb-4" style="color: var(--color-text);">
            Movement. Structure.<br>Forward momentum.
          </p>
          <p class="text-[15px] leading-relaxed" style="color: var(--color-text-secondary);">
            The unseen core that keeps things turning — steady, intentional, and always in motion.
            An axis isn't visible, but everything revolves around it.
          </p>
        </div>

        <!-- Nova -->
        <div
          class="rounded-3xl border p-8 md:p-10 relative overflow-hidden"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
        >
          <span
            aria-hidden
            class="absolute -right-2 -top-3 text-[140px] font-bold tracking-tighter leading-none select-none pointer-events-none"
            style="color: var(--color-text); opacity: 0.04;"
          >N</span>
          <p class="eyebrow mb-5">Nova</p>
          <p class="text-[24px] font-semibold tracking-tight leading-snug mb-4" style="color: var(--color-text);">
            New beginnings.<br>Expansion. Transformation.
          </p>
          <p class="text-[15px] leading-relaxed" style="color: var(--color-text-secondary);">
            A nova is a star releasing tremendous energy — a moment of brilliant change. It represents the
            drive to start fresh, grow boldly, and transform continuously.
          </p>
        </div>
      </div>

      <!-- Tie them together -->
      <div
        class="rounded-2xl border px-7 py-5 flex items-start gap-4"
        :style="{ background: 'var(--color-accent-soft)', borderColor: 'var(--color-border)' }"
      >
        <UIcon name="i-lucide-sparkles" class="size-4 mt-0.5 shrink-0" style="color: var(--color-accent);" />
        <p class="text-[15px] leading-relaxed" style="color: var(--color-text);">
          Together, <strong>Axel Nova</strong> reflects a mindset of continuous growth — building better
          systems, better experiences, and ultimately, a better life.
        </p>
      </div>
    </section>

    <!-- Vision -->
    <section class="mb-32 reveal">
      <p class="eyebrow mb-2">Vision</p>
      <h2 class="text-3xl font-semibold tracking-tight mb-5" style="color: var(--color-text);">
        More than a digital company.
      </h2>
      <p class="text-[17px] leading-[1.65] max-w-2xl mb-14" style="color: var(--color-text-secondary);">
        Axel Nova Ventures is intended to become a long-term platform for ideas, products, and ventures
        that combine technology, design, and human experience. From software and digital platforms to future
        creative and business ventures — built with the belief that meaningful products should feel effortless,
        intentional, and timeless.
      </p>

      <div class="grid sm:grid-cols-3 gap-5">
        <div
          v-for="pillar in pillars"
          :key="pillar.title"
          class="rounded-2xl border p-6 space-y-3"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
        >
          <div
            class="w-9 h-9 rounded-xl flex items-center justify-center"
            style="background: var(--color-accent-soft);"
          >
            <UIcon :name="pillar.icon" class="size-4.5" style="color: var(--color-accent);" />
          </div>
          <p class="text-[16px] font-semibold tracking-tight" style="color: var(--color-text);">
            {{ pillar.title }}
          </p>
          <p class="text-[14px] leading-relaxed" style="color: var(--color-text-secondary);">
            {{ pillar.description }}
          </p>
        </div>
      </div>
    </section>

    <!-- Philosophy -->
    <section class="mb-32 reveal">
      <p class="eyebrow mb-2">Philosophy</p>
      <h2 class="text-3xl font-semibold tracking-tight mb-10" style="color: var(--color-text);">
        Shaped by the world.
      </h2>

      <div class="grid lg:grid-cols-2 gap-10 lg:gap-20 items-center">
        <div class="space-y-5 text-[16px] leading-[1.7]" style="color: var(--color-text-secondary);">
          <p>
            Traveling across different cities, cultures, airports, hotels, and unfamiliar environments shaped
            the way Qie sees the world. Every journey reinforced the same idea: great experiences are never
            accidental.
          </p>
          <p>
            They are carefully designed, emotionally aware, and thoughtfully executed. That same philosophy
            now carries into every product and system built under Axel Nova Ventures.
          </p>
          <p>
            At its core, the company represents a quiet refusal to live an average life — building with intention,
            creating things that matter, exploring beyond comfort zones, and proving that technology can still
            feel human in a world increasingly driven by automation and speed.
          </p>
        </div>

        <!-- Pull quote -->
        <blockquote
          class="border-l-[3px] pl-8 py-2"
          style="border-color: var(--color-accent);"
        >
          <p class="text-[21px] font-semibold tracking-tight leading-snug" style="color: var(--color-text);">
            "Great experiences are never accidental — they are carefully designed, emotionally aware,
            and thoughtfully executed."
          </p>
          <p class="mt-5 text-[13px] font-medium" style="color: var(--color-text-tertiary);">
            The philosophy behind every build.
          </p>
        </blockquote>
      </div>
    </section>

    <!-- Closing -->
    <section class="reveal">
      <div
        class="rounded-3xl border px-8 py-20 text-center"
        :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
      >
        <p class="eyebrow mb-8">A larger vision</p>
        <p
          class="text-[clamp(26px,4vw,48px)] font-semibold tracking-tighter leading-tight max-w-3xl mx-auto mb-3"
          style="color: var(--color-text);"
        >
          Axel Nova Ventures is not just a business.
        </p>
        <p class="text-[clamp(26px,4vw,48px)] font-semibold tracking-tighter leading-tight max-w-3xl mx-auto text-gradient">
          It is the beginning of a larger vision.
        </p>
        <p class="mt-7 text-[16px] leading-relaxed max-w-md mx-auto" style="color: var(--color-text-secondary);">
          One that grows together with the person building it.
        </p>
        <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
          <NuxtLink to="/services" class="btn-pill btn-pill-accent">
            Work with us →
          </NuxtLink>
          <NuxtLink to="/about" class="btn-pill btn-pill-ghost">
            Meet Qie
          </NuxtLink>
        </div>
      </div>
    </section>

  </div>
</template>

<style scoped>
.hero-logo-mark {
  filter:
    drop-shadow(0 6px 14px rgba(0, 113, 227, 0.28))
    drop-shadow(0 0 28px rgba(0, 113, 227, 0.4))
    drop-shadow(0 0 64px rgba(159, 113, 255, 0.22));
}

.hero-logo-aurora {
  position: absolute;
  inset: 50% auto auto 50%;
  width: clamp(280px, 32vw, 460px);
  height: clamp(280px, 32vw, 460px);
  transform: translate(-50%, -50%);
  border-radius: 9999px;
  background:
    radial-gradient(closest-side, rgba(0, 113, 227, 0.55), rgba(0, 113, 227, 0) 70%),
    conic-gradient(
      from 0deg,
      rgba(0, 113, 227, 0.65),
      rgba(159, 113, 255, 0.55),
      rgba(255, 127, 191, 0.45),
      rgba(0, 113, 227, 0.65)
    );
  filter: blur(60px);
  opacity: 0.55;
  animation: hero-aurora-spin 24s linear infinite;
  pointer-events: none;
}

.hero-logo-orbit {
  position: absolute;
  inset: 50% auto auto 50%;
  width: clamp(220px, 24vw, 340px);
  height: clamp(220px, 24vw, 340px);
  transform: translate(-50%, -50%);
  border-radius: 9999px;
  border: 1px solid var(--color-border);
  background:
    radial-gradient(closest-side, transparent 60%, var(--color-bg-elevated) 80%, transparent 100%);
  opacity: 0.6;
  pointer-events: none;
}

@keyframes hero-aurora-spin {
  to { transform: translate(-50%, -50%) rotate(360deg); }
}

@media (prefers-reduced-motion: reduce) {
  .hero-logo-aurora { animation: none; }
}
</style>
