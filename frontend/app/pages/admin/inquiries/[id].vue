<script setup lang="ts">
// Sales inquiry (quote + contact). Collab leads open under Partners ›
// Collaborations instead — AdminInquiryDetail redirects them there.
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const route = useRoute()
</script>

<template>
  <AdminInquiryDetail :inquiry-id="String(route.params.id)" section="sales" />
</template>
