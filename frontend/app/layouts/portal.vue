<script setup lang="ts">
import BrandMark from '~/components/shared/BrandMark.vue'
</script>

<template>
  <div class="min-h-screen flex flex-col" style="background: var(--color-bg); color: var(--color-text);">
    <header
      class="border-b"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
    >
      <div class="max-w-5xl mx-auto h-20 px-6 flex items-center justify-between">
        <BrandMark />
        <span class="text-[12px]" :style="{ color: 'var(--color-text-tertiary)' }">
          <!-- Client name displayed here in Phase 4 -->
          Client Portal
        </span>
      </div>
    </header>

    <main class="flex-1">
      <div class="max-w-5xl mx-auto px-6 py-12">
        <slot />
      </div>
    </main>

    <footer class="border-t" :style="{ borderColor: 'var(--color-border)' }">
      <div class="max-w-5xl mx-auto px-6 py-6 flex items-center justify-between">
        <p class="text-[12px]" :style="{ color: 'var(--color-text-secondary)' }">
          Powered by Axel Nova Ventures
        </p>
        <p class="text-[12px]" :style="{ color: 'var(--color-text-tertiary)' }">
          © 2026 Axel Nova Ventures
        </p>
      </div>
    </footer>
  </div>
</template>
