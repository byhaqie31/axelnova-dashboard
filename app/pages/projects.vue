<script setup lang="ts">
import { onClickOutside } from '@vueuse/core'
import { projects, stackFilters, statusFilters } from '~/data/projects'
import ProjectCard from '~/components/shared/ProjectCard.vue'
import SectionHeader from '~/components/shared/SectionHeader.vue'

const activeStack = ref<string>('All')
const activeStatus = ref<string>('All')

const filteredProjects = computed(() => {
  return projects.filter((p) => {
    const stackOk = activeStack.value === 'All' || p.stack.includes(activeStack.value)
    const statusOk = activeStatus.value === 'All'
      || (activeStatus.value === 'Live'        && p.status === 'live')
      || (activeStatus.value === 'In progress' && p.status === 'wip')
      || (activeStatus.value === 'Planning'    && (p.status === 'planning' || p.status === 'soon'))
    return stackOk && statusOk
  })
})

// Status dropdown
const statusOpen = ref(false)
const statusRef = ref<HTMLElement | null>(null)
onClickOutside(statusRef, () => { statusOpen.value = false })

const selectStatus = (s: string) => {
  activeStatus.value = s
  statusOpen.value = false
}

useScrollReveal('.reveal')
</script>

<template>
  <div class="max-w-7xl mx-auto px-6 pt-20 pb-24">
    <SectionHeader
      eyebrow="Project registry"
      title="Everything I'm building."
      subtitle="A growing index of shipped products, ongoing builds, and experiments. Filter by stack or status."
    />

    <div class="flex items-start md:items-center justify-between gap-4 flex-col md:flex-row mb-12">
      <!-- LEFT: stack pills -->
      <div class="flex items-center gap-2 flex-wrap">
        <button
          v-for="s in stackFilters" :key="s"
          class="text-[13px] px-4 py-1.5 rounded-full border transition-all duration-200"
          :style="{
            borderColor: activeStack === s ? 'transparent' : 'var(--color-border-strong)',
            background: activeStack === s ? 'var(--color-text)' : 'transparent',
            color: activeStack === s ? 'var(--color-bg)' : 'var(--color-text-secondary)',
            fontWeight: activeStack === s ? 500 : 400,
            boxShadow: activeStack === s ? 'var(--shadow-sm)' : 'none'
          }"
          @click="activeStack = s"
        >
          {{ s }}
        </button>
      </div>

      <!-- RIGHT: status dropdown -->
      <div ref="statusRef" class="relative shrink-0">
        <button
          class="inline-flex items-center gap-2 text-[13px] px-4 py-1.5 rounded-full border transition-colors"
          :style="{
            borderColor: 'var(--color-border-strong)',
            background: 'var(--color-bg-elevated)',
            color: 'var(--color-text)'
          }"
          :aria-expanded="statusOpen"
          aria-haspopup="listbox"
          @click="statusOpen = !statusOpen"
        >
          <span class="text-[12px]" style="color: var(--color-text-secondary);">Status</span>
          <span class="font-medium">{{ activeStatus }}</span>
          <UIcon
            name="i-fluent-chevron-down-24-regular"
            class="size-3.5 transition-transform"
            :class="{ 'rotate-180': statusOpen }"
            :style="{ color: 'var(--color-text-secondary)' }"
          />
        </button>

        <Transition name="dropdown">
          <ul
            v-if="statusOpen"
            role="listbox"
            class="absolute right-0 mt-2 min-w-40 rounded-xl border backdrop-blur-xl py-1 z-20"
            :style="{
              background: 'var(--nav-bg-scrolled)',
              borderColor: 'var(--color-border-strong)',
              boxShadow: 'var(--shadow-lg)'
            }"
          >
            <li v-for="s in statusFilters" :key="s">
              <button
                role="option"
                :aria-selected="activeStatus === s"
                class="w-full flex items-center justify-between gap-3 px-3.5 py-2 text-[13px] text-left rounded-lg transition-colors"
                :style="{
                  color: activeStatus === s ? 'var(--color-text)' : 'var(--color-text-secondary)',
                  fontWeight: activeStatus === s ? 500 : 400
                }"
                @click="selectStatus(s)"
              >
                <span class="inline-flex items-center gap-2">
                  <span
                    v-if="s !== 'All'"
                    class="size-1.5 rounded-full"
                    :style="{
                      background:
                        s === 'Live' ? 'var(--color-success)'
                        : s === 'In progress' ? 'var(--color-warning)'
                        : 'var(--color-accent)'
                    }"
                  />
                  {{ s }}
                </span>
                <UIcon
                  v-if="activeStatus === s"
                  name="i-fluent-checkmark-24-regular"
                  class="size-3.5"
                  :style="{ color: 'var(--color-accent)' }"
                />
              </button>
            </li>
          </ul>
        </Transition>
      </div>
    </div>

    <Transition name="page" mode="out-in">
      <div
        :key="`${activeStack}-${activeStatus}`"
        class="grid gap-5 md:grid-cols-2 lg:grid-cols-3"
      >
        <ProjectCard
          v-for="p in filteredProjects" :key="p.id"
          :project="p"
          class="reveal"
        />

        <div
          v-if="filteredProjects.length === 0"
          class="col-span-full text-center py-16 text-[14px]"
          style="color: var(--color-text-secondary);"
        >
          No projects match these filters.
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.dropdown-enter-active,
.dropdown-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
  transform-origin: top right;
}
.dropdown-enter-from,
.dropdown-leave-to {
  opacity: 0;
  transform: scale(0.96) translateY(-4px);
}
</style>
