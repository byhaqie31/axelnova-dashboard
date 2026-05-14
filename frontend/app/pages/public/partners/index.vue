<script setup lang="ts">
definePageMeta({ layout: 'public' })

import SectionHeader from '~/components/shared/SectionHeader.vue'

const siteUrl = 'https://axelnovaventures.com'
const ogImage = `${siteUrl}/og-image.png`
const seoTitle = 'Partner Program — Axel Nova Ventures'
const seoDescription = 'Refer a business to Axel Nova and earn up to 15% when the project closes. A professional referral program for property agents, freelancers, consultants, and agencies.'

useSeoMeta({
  title: seoTitle,
  description: seoDescription,
  ogTitle: seoTitle,
  ogDescription: seoDescription,
  ogImage,
  ogUrl: `${siteUrl}/partners`,
  twitterTitle: seoTitle,
  twitterDescription: seoDescription,
  twitterImage: ogImage,
  twitterCard: 'summary_large_image',
})

useHead({
  link: [{ rel: 'canonical', href: `${siteUrl}/partners` }],
})

const audience = [
  {
    icon: 'i-fluent-building-24-regular',
    label: 'Property agents',
    desc: 'You meet owners and developers who need a serious digital presence.',
  },
  {
    icon: 'i-fluent-design-ideas-24-regular',
    label: 'Freelancers & designers',
    desc: 'You take on work that needs senior frontend or full builds you don’t cover.',
  },
  {
    icon: 'i-fluent-briefcase-24-regular',
    label: 'Consultants & accountants',
    desc: 'Your SME clients keep asking who builds their software and tools.',
  },
  {
    icon: 'i-fluent-people-team-24-regular',
    label: 'Agencies & studios',
    desc: 'You have overflow or out-of-scope projects worth handing off.',
  },
  {
    icon: 'i-fluent-megaphone-24-regular',
    label: 'Creators & community builders',
    desc: 'Your audience includes founders and business owners who need to ship.',
  },
  {
    icon: 'i-fluent-wallet-24-regular',
    label: 'Anyone wanting a side income',
    desc: 'You know business owners in your circle — turn an introduction into income on the side.',
  },
]

const steps = [
  {
    title: 'Refer a business',
    desc: 'Tell us who they are and what they need. It takes about two minutes.',
  },
  {
    title: 'We take it from there',
    desc: 'We reach out, scope the project, and deliver to a senior standard. You stay in the loop.',
  },
  {
    title: 'You get paid',
    desc: 'When the project is signed and paid, your commission is paid within 14 working days.',
  },
]

const tiers = [
  {
    name: 'Cold lead',
    rate: '5%',
    desc: 'You pass us their name and contact. Axel Nova handles the entire conversation.',
    featured: false,
  },
  {
    name: 'Warm intro',
    rate: '10%',
    desc: 'You introduce us personally and vouch for Axel Nova to the business.',
    featured: false,
  },
  {
    name: 'Closed referral',
    rate: 'Up to 15%',
    desc: 'You’ve already talked it through with them — we only scope and deliver.',
    featured: true,
  },
]

const termsSnapshot = [
  'Commission applies only to projects that are signed and paid in full.',
  'First valid referral wins — a business stays attributed to you for 90 days.',
  'No commission on existing Axel Nova clients, duplicate referrals, or self-referrals.',
  'Paid within 14 working days of cleared payment, via local bank transfer.',
  'Minimum payout RM150 — smaller balances roll into the next cycle.',
]

const faqs = [
  {
    q: 'Who can refer a business?',
    a: 'Anyone who knows a company or owner that needs digital products — agents, freelancers, consultants, agencies, and creators. You don’t need a technical background, and there’s no cost to take part.',
  },
  {
    q: 'How much can I earn?',
    a: 'Between 5% and 15% of the project value, depending on how involved you are — from passing a cold contact to handing over a business you’ve already talked it through with. Starter-tier projects earn a flat RM150 referral fee.',
  },
  {
    q: 'Do I have to sell anything?',
    a: 'No. Even just passing a name and contact earns you 5% — we handle the entire conversation. The more you introduce and endorse, the higher your tier.',
  },
  {
    q: 'When and how do I get paid?',
    a: 'Within 14 working days of the referred business’s full payment clearing, via local bank transfer (FPX/IBG). The minimum payout is RM150 — smaller balances carry to the next cycle.',
  },
  {
    q: 'What if someone else refers the same business?',
    a: 'The first valid referral wins. A business you refer stays attributed to you for 90 days from the first time we receive it.',
  },
  {
    q: 'What doesn’t qualify for commission?',
    a: 'Existing Axel Nova clients, businesses already in our system, self-referrals, and projects that are refunded or disputed within 30 days of payment.',
  },
  {
    q: 'Is there a cost or commitment?',
    a: 'No. Referring a business is free, with no commitment. You can stop at any time, and any commission already earned on a closed project is still honoured.',
  },
  {
    q: 'What happens after I refer someone?',
    a: 'We reach out to the business — usually within 3 business days — scope the project, and keep you posted. If it’s signed and paid, your commission follows per the terms above.',
  },
]

const openFaq = ref<number | null>(0)
const toggleFaq = (i: number) => {
  openFaq.value = openFaq.value === i ? null : i
}

// Hero intro — page-specific GSAP animation (see CLAUDE.md: hero animations are page-specific).
const heroBadge = ref<HTMLElement | null>(null)
const heroLine1 = ref<HTMLElement | null>(null)
const heroLine2 = ref<HTMLElement | null>(null)
const heroSub = ref<HTMLElement | null>(null)
const heroCtas = ref<HTMLElement | null>(null)

onMounted(async () => {
  if (import.meta.server) return

  const targets = [heroBadge.value, heroLine1.value, heroLine2.value, heroSub.value, heroCtas.value]
    .filter(Boolean) as HTMLElement[]
  const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches

  if (prefersReduced) {
    targets.forEach((el) => { el.style.opacity = '1'; el.style.transform = 'none' })
    return
  }

  const { $gsap } = useNuxtApp() as unknown as { $gsap: typeof import('gsap').default }

  await nextTick()
  $gsap.set(targets, { opacity: 0, y: 20 })

  requestAnimationFrame(() => {
    $gsap.to(targets, { opacity: 1, y: 0, duration: 0.8, ease: 'power3.out', stagger: 0.09 })
  })
})

useScrollReveal('.reveal')
</script>

<template>
  <div>
    <!-- HERO -->
    <section
      class="bg-aurora flex flex-col items-center justify-center text-center px-6 relative overflow-hidden"
      style="min-height: calc(100vh - 49px);"
    >
      <div
        ref="heroBadge"
        class="inline-flex items-center gap-2 px-3 py-1 rounded-full border backdrop-blur-md"
        :style="{ borderColor: 'var(--color-border-strong)', background: 'var(--color-accent-soft)' }"
      >
        <span class="size-1.5 rounded-full" style="background: var(--color-accent);" />
        <span class="text-[12px] font-medium" style="color: var(--color-text);">
          Axel Nova Partner Program
        </span>
      </div>

      <h1
        class="mt-7 leading-[1.02] tracking-tighter font-semibold max-w-5xl"
        style="font-size: clamp(48px, 8vw, 104px);"
      >
        <span ref="heroLine1" class="block">Refer a business.</span>
        <span ref="heroLine2" class="block text-gradient">Earn up to 15%.</span>
      </h1>

      <p
        ref="heroSub"
        class="mt-7 max-w-2xl text-[19px] leading-normal"
        style="color: var(--color-text-secondary);"
      >
        Know a company or property owner who needs design-led software? Refer them to Axel Nova.
        We scope, build, and deliver — you earn a commission when the project closes.
      </p>

      <div ref="heroCtas" class="mt-9 flex flex-wrap items-center justify-center gap-3">
        <NuxtLink to="/partners/refer" class="btn-pill btn-pill-accent">
          Refer a business
        </NuxtLink>
        <a href="#how" class="btn-pill btn-pill-ghost">
          How it works
        </a>
      </div>
    </section>

    <!-- WHO THIS IS FOR -->
    <section class="max-w-7xl mx-auto px-6 py-32 reveal">
      <SectionHeader
        eyebrow="Who it’s for"
        title="Built for people with the right rooms."
        subtitle="You don’t need to be technical — you just need to know a business that’s ready to build."
      />

      <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <div
          v-for="a in audience"
          :key="a.label"
          class="reveal rounded-2xl border p-6"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
        >
          <div
            class="size-10 rounded-xl flex items-center justify-center mb-4"
            :style="{ background: 'var(--color-accent-soft)' }"
          >
            <UIcon :name="a.icon" class="size-5" :style="{ color: 'var(--color-accent)' }" />
          </div>
          <p class="text-[15px] font-semibold tracking-tight mb-1.5" style="color: var(--color-text);">
            {{ a.label }}
          </p>
          <p class="text-[14px] leading-relaxed" style="color: var(--color-text-secondary);">
            {{ a.desc }}
          </p>
        </div>
      </div>
    </section>

    <!-- HOW IT WORKS -->
    <section
      id="how"
      class="border-y reveal"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)' }"
    >
      <div class="max-w-7xl mx-auto px-6 py-32">
        <SectionHeader
          eyebrow="How it works"
          title="Three steps, no friction."
        />

        <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
          <div v-for="(s, i) in steps" :key="s.title" class="reveal">
            <div
              class="size-9 rounded-full flex items-center justify-center text-[14px] font-semibold mb-4 tabular-nums"
              :style="{ background: 'var(--color-text)', color: 'var(--color-bg)' }"
            >
              {{ i + 1 }}
            </div>
            <p class="text-[16px] font-semibold tracking-tight mb-2" style="color: var(--color-text);">
              {{ s.title }}
            </p>
            <p class="text-[14px] leading-relaxed" style="color: var(--color-text-secondary);">
              {{ s.desc }}
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- COMMISSION BREAKDOWN -->
    <section class="max-w-7xl mx-auto px-6 py-32 reveal">
      <SectionHeader
        eyebrow="Commission"
        title="The more you bring, the more you earn."
        subtitle="Three tiers, based on how far you carry the referral. Commission is paid on the final project value."
      />

      <div class="grid gap-5 lg:grid-cols-3">
        <div
          v-for="t in tiers"
          :key="t.name"
          class="reveal rounded-2xl border p-7 flex flex-col"
          :style="{
            background: t.featured ? 'var(--color-accent-soft)' : 'var(--color-bg-elevated)',
            borderColor: t.featured ? 'var(--color-accent)' : 'var(--color-border)',
          }"
        >
          <p class="text-[13px] font-medium uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">
            {{ t.name }}
          </p>
          <p class="text-5xl font-semibold tracking-tight tabular-nums mb-4" style="color: var(--color-text);">
            {{ t.rate }}
          </p>
          <p class="text-[14px] leading-relaxed" style="color: var(--color-text-secondary);">
            {{ t.desc }}
          </p>
        </div>
      </div>

      <div
        class="mt-6 rounded-xl border px-5 py-4 flex items-start gap-3 reveal"
        :style="{ background: 'var(--color-bg-secondary)', borderColor: 'var(--color-border)' }"
      >
        <UIcon
          name="i-fluent-info-24-regular"
          class="size-4 mt-0.5 shrink-0"
          style="color: var(--color-text-tertiary);"
        />
        <p class="text-[13px] leading-relaxed" style="color: var(--color-text-secondary);">
          Commission tiers apply to Professional-tier projects and above, from RM3,000.
          Smaller Starter-tier projects earn a flat RM150 referral fee. All commission is subject to the
          Partner Program terms.
        </p>
      </div>
    </section>

    <!-- WHAT WE SELL -->
    <section
      class="border-y reveal"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)' }"
    >
      <div class="max-w-7xl mx-auto px-6 py-28 flex flex-col md:flex-row md:items-center md:justify-between gap-8">
        <div class="max-w-2xl">
          <p class="eyebrow mb-3">What you’re referring</p>
          <h2 class="text-3xl md:text-4xl font-semibold tracking-tight mb-4" style="color: var(--color-text);">
            Design-led software, built to a senior standard.
          </h2>
          <p class="text-[16px] leading-relaxed" style="color: var(--color-text-secondary);">
            Axel Nova builds UI/UX design, frontend engineering, and full product builds for fintech,
            SaaS, and bespoke web. Premium work the business can be proud of — and that reflects well
            on you for making the introduction.
          </p>
        </div>
        <div class="flex flex-wrap gap-3 shrink-0">
          <NuxtLink to="/services" class="btn-pill btn-pill-ghost">
            See services
          </NuxtLink>
          <NuxtLink to="/projects" class="btn-pill btn-pill-ghost">
            View work
          </NuxtLink>
        </div>
      </div>
    </section>

    <!-- TERMS SNAPSHOT -->
    <section class="max-w-7xl mx-auto px-6 py-32 reveal">
      <SectionHeader
        eyebrow="The fine print, briefly"
        title="Fair terms, clearly stated."
        subtitle="The essentials below. Full terms are shared when a referral progresses to a project."
      />

      <ul class="max-w-3xl space-y-3.5">
        <li
          v-for="item in termsSnapshot"
          :key="item"
          class="reveal flex items-start gap-3"
        >
          <UIcon
            name="i-fluent-checkmark-circle-24-regular"
            class="size-5 mt-0.5 shrink-0"
            :style="{ color: 'var(--color-accent)' }"
          />
          <span class="text-[16px] leading-relaxed" style="color: var(--color-text-secondary);">
            {{ item }}
          </span>
        </li>
      </ul>
    </section>

    <!-- FAQ -->
    <section
      class="border-y reveal"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)' }"
    >
      <div class="max-w-3xl mx-auto px-6 py-32">
        <SectionHeader
          eyebrow="FAQ"
          title="Questions, answered."
        />

        <div class="space-y-3">
          <div
            v-for="(f, i) in faqs"
            :key="f.q"
            class="reveal rounded-xl border overflow-hidden"
            :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)' }"
          >
            <button
              type="button"
              class="w-full flex items-center justify-between gap-4 px-5 py-4 text-left"
              :aria-expanded="openFaq === i"
              @click="toggleFaq(i)"
            >
              <span class="text-[15px] font-medium" style="color: var(--color-text);">
                {{ f.q }}
              </span>
              <UIcon
                name="i-fluent-chevron-down-24-regular"
                class="size-4 shrink-0 transition-transform duration-200"
                :style="{
                  color: 'var(--color-text-tertiary)',
                  transform: openFaq === i ? 'rotate(180deg)' : 'rotate(0deg)',
                }"
              />
            </button>
            <Transition name="faq">
              <div v-if="openFaq === i" class="px-5 pb-4">
                <p class="text-[14px] leading-relaxed" style="color: var(--color-text-secondary);">
                  {{ f.a }}
                </p>
              </div>
            </Transition>
          </div>
        </div>
      </div>
    </section>

    <!-- FINAL CTA -->
    <section class="relative overflow-hidden">
      <div
        aria-hidden
        class="absolute inset-0 -z-10"
        style="
          background:
            radial-gradient(60% 80% at 15% 50%, rgba(168,85,247,0.16) 0%, transparent 60%),
            radial-gradient(50% 80% at 85% 50%, rgba(0,113,227,0.18) 0%, transparent 60%),
            var(--color-bg-secondary);
        "
      />
      <div class="max-w-7xl mx-auto px-6 py-24 flex flex-col md:flex-row items-center justify-between gap-6 text-center md:text-left">
        <div>
          <p class="text-3xl md:text-5xl font-semibold tracking-tight">
            Know a business that needs to build?
          </p>
          <p class="mt-3 text-[17px] max-w-lg" style="color: var(--color-text-secondary);">
            Refer them in two minutes — we’ll take it from there.
          </p>
        </div>
        <NuxtLink to="/partners/refer" class="btn-pill btn-pill-accent shrink-0">
          Refer a business
        </NuxtLink>
      </div>
    </section>
  </div>
</template>

<style scoped>
.faq-enter-active,
.faq-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}
.faq-enter-from,
.faq-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}

@media (prefers-reduced-motion: reduce) {
  .faq-enter-active,
  .faq-leave-active {
    transition: none;
  }
}
</style>
