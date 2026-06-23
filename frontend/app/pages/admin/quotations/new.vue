<script setup lang="ts">
import QuotationBuilder from '~/components/admin/QuotationBuilder.vue'
import DetailedQuotationBuilder from '~/components/admin/DetailedQuotationBuilder.vue'

definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

useHead({ title: 'New quotation — Admin' })

const route = useRoute()
const inquiryId = computed(() => {
  const q = route.query.inquiry
  return typeof q === 'string' && q ? Number(q) : null
})
const isDetailed = computed(() => route.query.layout === 'detailed')

function onSaved(id: number) {
  navigateTo(`/admin/quotations/${id}`)
}
</script>

<template>
  <div class="max-w-6xl mx-auto px-4 sm:px-6 pt-10 pb-32">
    <NuxtLink to="/admin/quotations" class="inline-flex items-center gap-2 text-[13px] mb-8 transition-opacity hover:opacity-70"
      style="color: var(--color-text-secondary);">
      <UIcon name="i-lucide-arrow-left" class="size-4" /> All quotations
    </NuxtLink>

    <div class="mb-8">
      <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">New {{ isDetailed ? 'detailed ' : '' }}quotation</h1>
      <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">
        {{ isDetailed
          ? 'Compose a customized, sectioned proposal — scope sections, options, care plan. Saving creates a draft you can preview and send.'
          : 'Build a priced quotation. Saving creates a draft you can preview as a PDF and send.' }}
      </p>
    </div>

    <DetailedQuotationBuilder v-if="isDetailed" :inquiry-id="inquiryId" @saved="onSaved" />
    <QuotationBuilder v-else :inquiry-id="inquiryId" @saved="onSaved" />
  </div>
</template>
