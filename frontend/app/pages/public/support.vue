<script setup lang="ts">
import { useClipboard } from '@vueuse/core'
import SectionHeader from '~/components/shared/SectionHeader.vue'
import { DUITNOW_QR_SRC, STUDIO_BANK } from '~/data/studioPayment'

definePageMeta({ layout: 'public' })

// "Support Our Work" (footer → Support). The studio's DuitNow QR and OCBC
// account for anyone who wants to chip in — it replaced the old Ko-fi link.
// Account details live in data/studioPayment.ts (kept in sync with the
// invoice PDF and the backend DocumentMapper).
usePublicSeo({
  title: 'Support Our Work | Axel Nova Ventures',
  description: 'Support Axel Nova Ventures with a contribution of any amount. Scan the DuitNow QR or transfer directly to the studio\'s OCBC account.',
  path: '/support',
  card: {
    label: 'SUPPORT',
    headline: 'Support our work.',
    subline: 'DuitNow QR or OCBC bank transfer.',
  },
})

const { copy, copied, isSupported } = useClipboard({ source: STUDIO_BANK.acct, copiedDuring: 2000, legacy: true })

useScrollReveal('.reveal')
</script>

<template>
  <div class="max-w-5xl mx-auto px-6 pt-24 pb-32">
    <SectionHeader
      as="h1"
      eyebrow="Support"
      title="Support our work."
      subtitle="If a project, tool or note here has helped you, you can support the studio with a contribution of any amount. Scan the DuitNow QR, or transfer straight to our OCBC account."
    />

    <div class="grid md:grid-cols-2 gap-6 md:gap-8 items-start">
      <!-- DuitNow QR -->
      <figure
        class="reveal rounded-3xl border p-6 sm:p-8 text-center"
        :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
      >
        <img
          :src="DUITNOW_QR_SRC"
          alt="DuitNow QR code for Axel Nova Ventures"
          width="974"
          height="978"
          class="mx-auto w-full max-w-80 h-auto rounded-2xl"
        >
        <figcaption class="mt-5 text-[13px] leading-relaxed max-w-xs mx-auto" style="color: var(--color-text-secondary);">
          Scan with any banking or e-wallet app that supports DuitNow, then key in the amount yourself.
        </figcaption>
        <a
          :href="DUITNOW_QR_SRC"
          download="axel-nova-ventures-duitnow-qr.png"
          class="btn-pill btn-pill-ghost mt-5 inline-flex items-center gap-2"
        >
          <UIcon name="i-lucide-download" class="size-4" />
          Download QR
        </a>
      </figure>

      <div class="space-y-5">
        <!-- Bank transfer — an inverted surface (text ↔ bg tokens), so it reads
             as the dark card in light mode and flips light in dark mode. -->
        <div class="reveal support-bank rounded-3xl p-7 sm:p-8">
          <p class="flex items-center gap-2 text-[12px] font-semibold uppercase tracking-widest">
            <span class="size-2 rounded-full" style="background: var(--color-accent);" aria-hidden="true" />
            {{ STUDIO_BANK.bank }}
          </p>
          <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-[32px] sm:text-[40px] font-semibold tracking-tight tabular-nums leading-none">
              {{ STUDIO_BANK.acct }}
            </p>
            <button
              v-if="isSupported"
              type="button"
              class="support-copy inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-[12px] font-medium"
              :aria-label="copied ? 'Account number copied' : 'Copy account number'"
              @click="copy()"
            >
              <UIcon :name="copied ? 'i-lucide-check' : 'i-lucide-copy'" class="size-3.5" />
              {{ copied ? 'Copied' : 'Copy' }}
            </button>
          </div>
          <div class="support-bank-rule mt-6 pt-5 flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1">
            <p class="text-[11px] font-medium uppercase tracking-widest support-bank-muted">Account name</p>
            <p class="text-[17px] font-semibold tracking-tight">{{ STUDIO_BANK.holder }}</p>
          </div>
        </div>

        <!-- Thanks -->
        <div
          class="reveal rounded-2xl border p-5 flex items-start gap-4"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
        >
          <span
            class="size-9 rounded-full flex items-center justify-center shrink-0"
            :style="{ background: 'var(--color-success-soft)' }"
          >
            <UIcon name="i-lucide-heart" class="size-4" style="color: var(--color-success);" />
          </span>
          <div>
            <p class="text-[15px] font-semibold tracking-tight mb-1" style="color: var(--color-text);">
              Thank you for your support.
            </p>
            <p class="text-[14px] leading-relaxed" style="color: var(--color-text-secondary);">
              It helps keep the projects and notes on this site going. If you'd like a thank-you note,
              send your transfer slip to
              <a href="mailto:baihaqie@axelnova.tech" class="font-medium link-underline" style="color: var(--color-accent);">baihaqie@axelnova.tech</a>.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Inverted surface: the theme's text colour as the card, its background as the
   ink — dark in light mode, light in dark mode, no hex. */
.support-bank {
  background: var(--color-text);
  color: var(--color-bg);
  box-shadow: var(--shadow-lg);
}
.support-bank-muted {
  color: color-mix(in srgb, var(--color-bg) 62%, transparent);
}
.support-bank-rule {
  border-top: 1px solid color-mix(in srgb, var(--color-bg) 18%, transparent);
}
.support-copy {
  background: color-mix(in srgb, var(--color-bg) 14%, transparent);
  color: var(--color-bg);
  transition: background-color 0.2s ease;
}
.support-copy:hover {
  background: color-mix(in srgb, var(--color-bg) 22%, transparent);
}
</style>
