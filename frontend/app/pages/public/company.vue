<script setup lang="ts">
definePageMeta({ layout: 'public' })

usePublicSeo({
  title: 'About Our Digital Studio in Kuala Lumpur | Axel Nova',
  description: 'Learn how Axel Nova Ventures approaches website design and custom business systems in Kuala Lumpur, from the first conversation to launch.',
  path: '/company',
})

const stats = [
  { label: 'Registered name', value: 'Axel Nova Ventures' },
  { label: 'Registration number', value: '202603119899 (CA0420977-U)' },
  { label: 'Established', value: '2026' },
  { label: 'Based in', value: 'Kuala Lumpur, Malaysia' },
  { label: 'Focus', value: 'Websites and custom business systems' },
]

const pillars = [
  {
    icon: 'i-lucide-layers',
    title: 'Start with the user.',
    description: 'Understand the task, the context and where people get stuck.',
  },
  {
    icon: 'i-lucide-cpu',
    title: 'Build for real use.',
    description: 'Choose the technology and level of complexity the project needs.',
  },
  {
    icon: 'i-lucide-heart',
    title: 'Make it maintainable.',
    description: 'Leave clients with clear handover materials and a system they can keep using.',
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
          A digital studio built around<br class="hidden sm:block" />
          <span class="text-gradient">how people use technology.</span>
        </h1>
        <p class="text-[17px] leading-relaxed max-w-lg" style="color: var(--color-text-secondary);">
          Axel Nova Ventures is based in Kuala Lumpur and works with businesses across Malaysia. The
          studio designs and builds websites and custom systems with clear interfaces, practical
          features and room to grow.
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
            How Axel Nova Ventures began
          </h2>
          <p class="text-[17px] leading-[1.65]" style="color: var(--color-text);">
            Axel Nova Ventures began with a personal decision: Qie wanted to give his work in design and
            software a home of its own. In 2026, he registered the venture in Kuala Lumpur and began
            building it as a design-led digital studio.
          </p>
          <p class="text-[17px] leading-[1.65]" style="color: var(--color-text-secondary);">
            From the start, the focus has been practical: websites that help businesses present
            themselves clearly, and custom systems designed around the way their teams work. The studio
            takes each project from an early idea through design and development, choosing the approach
            and technology to fit the need.
          </p>
          <p class="text-[17px] leading-[1.65]" style="color: var(--color-text-secondary);">
            The company is still young. Client work is its foundation, while the longer ambition is to
            create original products and ventures under the Axel Nova name. Each project helps define
            what the company will become.
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
        The story behind the name
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
            Axel draws from &ldquo;axis&rdquo;, the point around which things move.
          </p>
          <p class="text-[15px] leading-relaxed" style="color: var(--color-text-secondary);">
            It reflects the structure that keeps a system working as it grows.
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
            Nova evokes a new beginning and the energy behind change.
          </p>
          <p class="text-[15px] leading-relaxed" style="color: var(--color-text-secondary);">
            It reflects the urge to keep learning and building.
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
          <strong>Axel Nova</strong> brings those ideas together: strong foundations and the freedom to
          move forward.
        </p>
      </div>
    </section>

    <!-- Vision -->
    <section class="mb-32 reveal">
      <p class="eyebrow mb-2">Vision</p>
      <h2 class="text-3xl font-semibold tracking-tight mb-5" style="color: var(--color-text);">
        Where the studio is heading
      </h2>
      <p class="text-[17px] leading-[1.65] max-w-2xl mb-14" style="color: var(--color-text-secondary);">
        Axel Nova Ventures began with client work in design and software. Over time, I want it to become
        a home for useful products and new ventures. The common thread is simple: build things that solve
        real problems and feel clear to use.
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
            Travel has made Qie pay attention to how people move through unfamiliar places. A clear sign,
            a timely message or a simple check-in can change the whole experience.
          </p>
          <p>
            The same idea applies to software: the useful details are often the ones people barely notice
            when they work well.
          </p>
        </div>

        <!-- Pull quote -->
        <blockquote
          class="border-l-[3px] pl-8 py-2"
          style="border-color: var(--color-accent);"
        >
          <p class="text-[21px] font-semibold tracking-tight leading-snug" style="color: var(--color-text);">
            &ldquo;The best interface helps someone get on with what they came to do.&rdquo;
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
        <p class="eyebrow mb-8">Looking ahead</p>
        <p
          class="text-[clamp(26px,4vw,48px)] font-semibold tracking-tighter leading-tight max-w-3xl mx-auto mb-3"
          style="color: var(--color-text);"
        >
          This is a young studio
        </p>
        <p class="text-[clamp(26px,4vw,48px)] font-semibold tracking-tighter leading-tight max-w-3xl mx-auto text-gradient">
          with a long view.
        </p>
        <p class="mt-7 text-[16px] leading-relaxed max-w-md mx-auto" style="color: var(--color-text-secondary);">
          Client work today shapes the products and team Axel Nova Ventures hopes to build next.
        </p>
        <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
          <NuxtLink to="/services" class="btn-pill btn-pill-accent">
            Explore the services →
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
