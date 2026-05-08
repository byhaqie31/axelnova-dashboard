<script setup lang="ts">
definePageMeta({ layout: 'public' })

import SectionHeader from '~/components/shared/SectionHeader.vue'

const form = reactive({
  name: '',
  email: '',
  subject: 'Project inquiry',
  message: '',
})

const submitted = ref(false)
const loading = ref(false)
const error = ref('')

const subjects = [
  'Project inquiry',
  'Collaboration',
  'General question',
  'Feedback',
  'Other',
]

const channels = [
  {
    label: 'WhatsApp',
    value: '+60 17-710 9486',
    helper: 'Fastest for quick questions.',
    icon: 'i-fluent-chat-24-regular',
    href: 'https://wa.me/60177109486?text=Hi%20Qie%2C%20I%27d%20like%20to%20connect.',
    external: true,
    iconColor: 'var(--color-success)',
    iconBg: 'rgba(48,209,88,0.14)',
  },
  {
    label: 'Email',
    value: 'baihaqie@axelnova.tech',
    helper: 'Best for detailed briefs.',
    icon: 'i-fluent-mail-24-regular',
    href: 'mailto:baihaqie@axelnova.tech',
    external: false,
    iconColor: 'var(--color-accent)',
    iconBg: 'var(--color-accent-soft)',
  },
  {
    label: 'Phone',
    value: '+60 17-710 9486',
    helper: 'For urgent conversations.',
    icon: 'i-fluent-call-24-regular',
    href: 'tel:+60177109486',
    external: false,
    iconColor: '#A855F7',
    iconBg: 'rgba(168,85,247,0.14)',
  },
]

const handleSubmit = async () => {
  loading.value = true
  error.value = ''
  try {
    const res = await fetch('https://api.web3forms.com/submit', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        access_key: 'a9100b0c-2c2b-4c5c-a381-543301ef9b17',
        name: form.name,
        email: form.email,
        subject: `${form.subject} — axelnova.tech`,
        message: form.message,
      }),
    })
    const result = await res.json()
    if (result.success) {
      submitted.value = true
    } else {
      error.value = 'Something went wrong. Please try again or email me directly.'
    }
  } catch {
    error.value = 'Network error. Please check your connection and try again.'
  } finally {
    loading.value = false
  }
}

useScrollReveal('.reveal')
</script>

<template>
  <div class="max-w-7xl mx-auto px-6 pt-24 pb-32">
    <SectionHeader
      eyebrow="Contact"
      title="Let's connect."
      subtitle="Whether you have a project in mind, a question, or just want to say hello, I'm happy to hear from you."
    />

    <!-- Main grid -->
    <div class="grid lg:grid-cols-[1.4fr_1fr] gap-10 lg:gap-16 reveal">

      <!-- Form -->
      <div>
        <!-- Submitted state -->
        <Transition name="page">
          <div
            v-if="submitted"
            class="rounded-2xl border p-12 text-center h-full flex flex-col items-center justify-center gap-5"
            :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
          >
            <div
              class="w-14 h-14 rounded-2xl flex items-center justify-center"
              style="background: rgba(48,209,88,0.14);"
            >
              <UIcon name="i-fluent-checkmark-circle-24-regular" class="size-7" style="color: var(--color-success);" />
            </div>
            <div>
              <p class="text-[20px] font-semibold tracking-tight mb-2" style="color: var(--color-text);">Message sent.</p>
              <p class="text-[14px] leading-relaxed" style="color: var(--color-text-secondary);">
                Your email client should have opened. I'll reply within one working day.
              </p>
            </div>
            <button
              class="btn-pill btn-pill-ghost mt-2"
              @click="submitted = false; error = ''; form.name = ''; form.email = ''; form.message = ''"
            >
              Send another
            </button>
          </div>
        </Transition>

        <!-- Form fields -->
        <form
          v-if="!submitted"
          class="space-y-5"
          @submit.prevent="handleSubmit"
        >
          <div class="grid sm:grid-cols-2 gap-5">
            <div class="space-y-1.5">
              <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Name</label>
              <input
                v-model="form.name"
                type="text"
                placeholder="John Doe"
                required
                class="contact-input"
                :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }"
              />
            </div>
            <div class="space-y-1.5">
              <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Email</label>
              <input
                v-model="form.email"
                type="email"
                placeholder="you@example.com"
                required
                class="contact-input"
                :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }"
              />
            </div>
          </div>

          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Subject</label>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="s in subjects"
                :key="s"
                type="button"
                class="text-[12px] px-3.5 py-1.5 rounded-full border transition-all"
                :style="{
                  borderColor: form.subject === s ? 'var(--color-accent)' : 'var(--color-border)',
                  background: form.subject === s ? 'var(--color-accent-soft)' : 'transparent',
                  color: form.subject === s ? 'var(--color-accent)' : 'var(--color-text-secondary)',
                  fontWeight: form.subject === s ? 500 : 400,
                }"
                @click="form.subject = s"
              >
                {{ s }}
              </button>
            </div>
          </div>

          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Message</label>
            <textarea
              v-model="form.message"
              rows="7"
              placeholder="Tell me about your project, idea, or question..."
              required
              class="contact-input resize-none"
              :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }"
            />
          </div>

          <button
            type="submit"
            class="btn-pill btn-pill-accent w-full justify-center"
            :disabled="loading"
            :style="{ opacity: loading ? '0.7' : '1', cursor: loading ? 'not-allowed' : 'pointer' }"
          >
            {{ loading ? 'Sending…' : 'Send message →' }}
          </button>

          <p v-if="error" class="text-[12px] text-center" style="color: var(--color-danger);">
            {{ error }}
          </p>
        </form>
      </div>

      <!-- Sidebar -->
      <div class="space-y-6 lg:pt-1">

        <!-- Availability -->
        <div
          class="rounded-2xl border p-5"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
        >
          <div class="flex items-center gap-2.5 mb-3">
            <span class="contact-avail-dot" aria-hidden />
            <p class="text-[13px] font-medium" style="color: var(--color-text);">Available for selected collaborations</p>
          </div>
          <p class="text-[13px] leading-relaxed" style="color: var(--color-text-secondary);">
            Based in Kuala Lumpur, Malaysia. Open to remote and global projects.
            Typically replies within <span style="color: var(--color-text);" class="font-medium">one working day</span>.
          </p>
        </div>

        <!-- Channels -->
        <div class="space-y-3">
          <p class="text-[11px] font-medium uppercase tracking-widest" style="color: var(--color-text-tertiary);">Or reach out directly</p>
          <a
            v-for="c in channels"
            :key="c.label"
            :href="c.href"
            :target="c.external ? '_blank' : undefined"
            :rel="c.external ? 'noopener' : undefined"
            class="channel-card flex items-center gap-4 rounded-xl border p-4 transition-all duration-200"
            :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
          >
            <div
              class="size-9 rounded-xl flex items-center justify-center shrink-0"
              :style="{ background: c.iconBg }"
            >
              <UIcon :name="c.icon" class="size-4.5" :style="{ color: c.iconColor }" />
            </div>
            <div class="min-w-0">
              <p class="text-[12px] font-medium mb-0.5" style="color: var(--color-text-tertiary);">{{ c.label }}</p>
              <p class="text-[14px] font-medium truncate" style="color: var(--color-text);">{{ c.value }}</p>
            </div>
            <UIcon
              name="i-fluent-arrow-up-right-24-regular"
              class="size-3.5 ml-auto shrink-0 opacity-0 transition-opacity channel-arrow"
              :style="{ color: 'var(--color-text-secondary)' }"
            />
          </a>
        </div>

        <!-- Note -->
        <div
          class="rounded-xl border px-4 py-3.5 flex items-start gap-3"
          :style="{ background: 'var(--color-bg-secondary)', borderColor: 'var(--color-border)' }"
        >
          <UIcon name="i-fluent-info-24-regular" class="size-4 mt-0.5 shrink-0" style="color: var(--color-text-tertiary);" />
          <p class="text-[12px] leading-relaxed" style="color: var(--color-text-secondary);">
            For project enquiries, sharing a brief or scope document helps us get started faster.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.contact-input {
  width: 100%;
  border-radius: 12px;
  border-width: 1px;
  border-style: solid;
  padding: 12px 16px;
  font-size: 14px;
  outline: none;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
  font-family: inherit;
}

.contact-input::placeholder {
  color: var(--color-text-tertiary);
}

.contact-input:focus {
  border-color: var(--color-accent);
  box-shadow: var(--shadow-glow);
}

.contact-avail-dot {
  width: 7px;
  height: 7px;
  border-radius: 9999px;
  background: var(--color-success);
  box-shadow: 0 0 0 0 rgba(48, 209, 88, 0.45);
  animation: avail-pulse 2.4s ease-in-out infinite;
  flex-shrink: 0;
}

@keyframes avail-pulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(48, 209, 88, 0.45); }
  50% { box-shadow: 0 0 0 5px rgba(48, 209, 88, 0); }
}

.channel-card:hover {
  border-color: var(--color-border-strong) !important;
  transform: translateY(-2px);
  box-shadow: var(--shadow-sm);
}

.channel-card:hover .channel-arrow {
  opacity: 1;
}

@media (prefers-reduced-motion: reduce) {
  .contact-avail-dot { animation: none; }
  .channel-card:hover { transform: none; }
}
</style>
