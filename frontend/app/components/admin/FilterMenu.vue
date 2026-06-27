<script setup lang="ts">
// Standard secondary-filter affordance: a funnel button beside the search box
// that opens a popover holding the page's "other" filters (type/method/gateway…).
// Total + Status stay on the right via AdminStatusFilter — this keeps every list
// page's filter row visually identical.
const props = withDefaults(defineProps<{ activeCount?: number }>(), { activeCount: 0 })
const emit = defineEmits<{ clear: [] }>()

const open = ref(false)
const root = ref<HTMLElement | null>(null)
onClickOutside(root, () => { open.value = false })

const active = computed(() => props.activeCount > 0)

function clearAll() {
  emit('clear')
  open.value = false
}
</script>

<template>
  <div ref="root" class="relative inline-flex">
    <button
      type="button"
      :aria-expanded="open"
      aria-haspopup="dialog"
      aria-label="Filters"
      class="inline-flex items-center gap-2 h-9 px-3.5 rounded-full border transition-all duration-200"
      :style="{
        borderColor: open || active ? 'var(--color-accent)' : 'var(--color-border-strong)',
        background: open || active ? 'var(--color-accent-soft)' : 'var(--color-bg-elevated)',
        color: open || active ? 'var(--color-accent)' : 'var(--color-text)',
        fontWeight: '500',
      }"
      @click="open = !open"
    >
      <UIcon name="i-lucide-sliders-horizontal" class="size-4" />
      <span class="text-[12px]">Filters</span>
      <span
        v-if="active"
        class="inline-flex items-center justify-center min-w-[16px] h-4 px-1 rounded-full text-[10px] font-bold leading-none"
        :style="{ background: 'var(--color-accent)', color: '#fff' }"
      >{{ activeCount }}</span>
    </button>

    <Transition name="filter-menu">
      <div
        v-if="open"
        role="dialog"
        class="absolute left-0 top-full mt-1.5 w-72 rounded-xl border p-3.5 z-40"
        :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)', boxShadow: 'var(--shadow-card-hover)' }"
      >
        <div class="space-y-4">
          <slot />
        </div>
        <div v-if="active" class="pt-3 mt-3 border-t" :style="{ borderColor: 'var(--color-border)' }">
          <button type="button" class="text-[12px] font-medium transition-opacity hover:opacity-70"
            :style="{ color: 'var(--color-accent)' }" @click="clearAll">
            Clear filters
          </button>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.filter-menu-enter-active,
.filter-menu-leave-active {
  transition: opacity 0.14s ease, transform 0.14s ease;
}
.filter-menu-enter-from,
.filter-menu-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
