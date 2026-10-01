<script setup lang="ts">
import QuotationBuilder from '~/components/admin/QuotationBuilder.vue'

definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

useHead({ title: 'New quotation — Admin' })

const route = useRoute()
const inquiryId = computed(() => {
  const q = route.query.inquiry
  return typeof q === 'string' && q ? Number(q) : null
})

function onSaved(id: number) {
  navigateTo(`/admin/quotations/${id}`)
}
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-6 md:pt-10 pb-24 md:pb-32">
    <NuxtLink
to="/admin/quotations" class="inline-flex items-center gap-2 text-[13px] mb-8 max-md:mb-5 max-md:py-1.5 transition-opacity hover:opacity-70 max-md:active:opacity-60"
      style="color: var(--color-text-secondary);">
      <UIcon name="i-lucide-arrow-left" class="size-4" /> All quotations
    </NuxtLink>

    <div class="mb-8 max-md:mb-6">
      <h1 class="text-[24px] md:text-[28px] font-bold tracking-tight" style="color: var(--color-text);">New quotation</h1>
      <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">
        Build a priced quotation. Add the optional detailed-proposal section if you need a richer document. Saving creates a draft you can preview and send.
      </p>
    </div>

    <QuotationBuilder :inquiry-id="inquiryId" @saved="onSaved" />
  </div>
</template>
