<script setup lang="ts">
// The three ways Axel Nova works with a studio partner — shared by the
// /collaborations index and each partner's page. Inherits the page's accent,
// so inside a partner scope (.collab-trlabs) the icons take the co-brand.
const modes = [
  {
    icon: 'i-lucide-handshake',
    title: 'Co-build',
    desc: 'Both studios work on one project, each owning the part it does best. You get one plan, one timeline and one point of contact.',
  },
  {
    icon: 'i-lucide-puzzle',
    title: 'Subcontract',
    desc: 'When a project needs extra hands or a specific skill, the partner studio delivers that part to the lead studio’s standard.',
  },
  {
    icon: 'i-lucide-arrow-left-right',
    title: 'Referral',
    desc: 'If your project fits the other studio better, we say so and make the introduction, so you start with the right team.',
  },
]
</script>

<template>
  <div class="grid gap-5 md:grid-cols-3">
    <div
      v-for="(m, i) in modes"
      :key="m.title"
      class="reveal rounded-2xl border p-7"
      :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
    >
      <div class="flex items-center justify-between mb-6">
        <div
          class="size-10 rounded-xl flex items-center justify-center"
          :style="{ background: 'var(--color-accent-soft)' }"
        >
          <UIcon :name="m.icon" class="size-5" :style="{ color: 'var(--color-accent)' }" />
        </div>
        <span class="text-[12px] font-medium tabular-nums" style="color: var(--color-text-tertiary);">0{{ i + 1 }}</span>
      </div>
      <p class="text-[17px] font-semibold tracking-tight mb-2" style="color: var(--color-text);">
        {{ m.title }}
      </p>
      <p class="text-[14px] leading-relaxed" style="color: var(--color-text-secondary);">
        {{ m.desc }}
      </p>
    </div>
  </div>
</template>
