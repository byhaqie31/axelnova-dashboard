<script setup lang="ts">
/**
 * Homepage shortcut to the Partner Program — sits right above the
 * testimonial wall. Deliberately the inverse pitch of the closing blue
 * band ("Have a project in mind?"): this one catches visitors who don't,
 * but know someone who does. Full details live at /partners; this band
 * only teases the offer and routes there.
 */
const steps = [
  {
    icon: 'i-lucide-send',
    title: 'Send the intro',
    desc: 'Tell us who they are and what they need — it takes about two minutes.',
  },
  {
    icon: 'i-lucide-hammer',
    title: 'We do the building',
    desc: 'We reach out, scope, and deliver to a senior standard. You stay in the loop.',
  },
  {
    icon: 'i-lucide-banknote',
    title: 'You collect the commission',
    desc: 'Once the project is signed and paid, your cut lands within 14 working days.',
  },
]
</script>

<template>
  <section
    class="border-y reveal relative overflow-hidden"
    :style="{ borderColor: 'var(--color-border)' }"
  >
    <!-- Quiet accent wash — kept far softer than the blue CTA band below,
         so the testimonials between them still get visual air. -->
    <div
      aria-hidden
      class="absolute inset-0 -z-10"
      style="
        background:
          radial-gradient(55% 90% at 85% 20%, var(--color-accent-soft) 0%, transparent 65%),
          var(--color-bg-elevated);
      "
    />

    <div class="max-w-7xl mx-auto px-6 py-24 grid gap-14 lg:grid-cols-[1.1fr_1fr] lg:items-center">
      <!-- Pitch -->
      <div>
        <div
          class="inline-flex items-center gap-2 px-3 py-1 rounded-full border"
          :style="{ borderColor: 'var(--color-border-strong)', background: 'var(--color-accent-soft)' }"
        >
          <span class="size-1.5 rounded-full" style="background: var(--color-accent);" />
          <span class="text-[12px] font-medium" style="color: var(--color-text);">
            Partner Program · Earn up to 15%
          </span>
        </div>

        <h2
          class="mt-6 text-3xl md:text-5xl font-semibold tracking-tight"
          style="color: var(--color-text); text-wrap: balance;"
        >
          No project of your own?<br>
          Refer one instead.
        </h2>

        <p class="mt-4 max-w-xl text-[16px] leading-relaxed" style="color: var(--color-text-secondary);">
          You probably know a business owner who keeps saying they need a website,
          a system, an app. Make the introduction — we scope, build, and deliver,
          and you earn a commission when the project closes.
        </p>

        <div class="mt-8 flex flex-wrap items-center gap-3">
          <NuxtLink to="/partners/refer" class="btn-pill btn-pill-accent">
            Refer a business
          </NuxtLink>
          <NuxtLink to="/partners" class="btn-pill btn-pill-ghost">
            Explore the program
          </NuxtLink>
        </div>

        <p class="mt-5 text-[13px]" style="color: var(--color-text-tertiary);">
          5% for a name, up to 15% for a closed referral · smaller projects earn a flat RM150.
        </p>
      </div>

      <!-- Three steps, no friction -->
      <ol class="space-y-8">
        <li v-for="(s, i) in steps" :key="s.title" class="reveal flex gap-5">
          <div class="flex flex-col items-center">
            <div
              class="size-10 rounded-xl flex items-center justify-center shrink-0"
              :style="{ background: 'var(--color-accent-soft)' }"
            >
              <UIcon :name="s.icon" class="size-4.5" :style="{ color: 'var(--color-accent)' }" />
            </div>
            <div
              v-if="i < steps.length - 1"
              aria-hidden
              class="w-px flex-1 mt-3"
              :style="{ background: 'var(--color-border)' }"
            />
          </div>
          <div class="pb-1">
            <p class="text-[15px] font-semibold tracking-tight" style="color: var(--color-text);">
              {{ s.title }}
            </p>
            <p class="mt-1 text-[14px] leading-relaxed" style="color: var(--color-text-secondary);">
              {{ s.desc }}
            </p>
          </div>
        </li>
      </ol>
    </div>
  </section>
</template>
