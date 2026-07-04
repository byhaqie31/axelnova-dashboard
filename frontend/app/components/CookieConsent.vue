<script setup lang="ts">
// Lightweight consent banner. Shown until the visitor decides; the choice is
// recorded in the axn_consent cookie (see useCookieConsent). We set no non-essential
// cookie before consent — the functional axn_ref attribution cookie is only written
// after "Accept" (handled by the ref-capture plugin watching `granted`).
const { decided, accept, decline } = useCookieConsent()
</script>

<template>
  <Transition name="consent-fade">
    <div
      v-if="!decided"
      class="consent-banner"
      role="dialog"
      aria-live="polite"
      aria-label="Cookie consent"
    >
      <div
        class="mx-auto max-w-3xl rounded-2xl border p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-5"
        :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)', boxShadow: 'var(--shadow-lg)' }"
      >
        <p class="text-[13px] leading-relaxed flex-1" style="color: var(--color-text-secondary);">
          We use essential cookies to run this site and, with your consent, a functional cookie to
          credit the partner who referred you. See our
          <NuxtLink to="/legal/cookies" style="color: var(--color-accent);">Cookie Policy</NuxtLink>.
        </p>
        <div class="flex items-center gap-2 shrink-0">
          <button type="button" class="consent-btn-ghost" @click="decline">
            Decline
          </button>
          <button type="button" class="btn-pill btn-pill-accent consent-btn-accept" @click="accept">
            Accept
          </button>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.consent-banner {
  position: fixed;
  left: 12px;
  right: 12px;
  bottom: 12px;
  z-index: 60;
}

.consent-btn-accept {
  height: 36px;
  font-size: 13px;
  padding: 0 18px;
}

.consent-btn-ghost {
  height: 36px;
  padding: 0 14px;
  border-radius: 9999px;
  font-size: 13px;
  color: var(--color-text-secondary);
  transition: background 0.15s ease, color 0.15s ease;
}
.consent-btn-ghost:hover {
  background: var(--color-bg-secondary);
  color: var(--color-text);
}

.consent-fade-enter-active,
.consent-fade-leave-active {
  transition: opacity 0.25s ease, transform 0.25s ease;
}
.consent-fade-enter-from,
.consent-fade-leave-to {
  opacity: 0;
  transform: translateY(8px);
}

@media (prefers-reduced-motion: reduce) {
  .consent-fade-enter-active,
  .consent-fade-leave-active { transition: none; }
}
</style>
