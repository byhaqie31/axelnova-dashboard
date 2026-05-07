<script setup lang="ts">
definePageMeta({ layout: 'portal', middleware: 'portal-token' })
useHead({ title: 'Client Portal — Axel Nova Ventures' })

const route = useRoute()
const token = computed(() => route.params.token as string)
</script>

<template>
  <div>
    <h1 class="text-2xl font-semibold tracking-tight mb-3" :style="{ color: 'var(--color-text)' }">
      Client Portal
    </h1>
    <p class="text-[14px]" :style="{ color: 'var(--color-text-secondary)' }">
      Phase 4 work begins here. Token: <code class="font-mono">{{ token }}</code>
    </p>
  </div>
</template>
