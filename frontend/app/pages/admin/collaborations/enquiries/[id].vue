<script setup lang="ts">
// A lead from /collaborations — a client hiring a partner studio through us
// (origin partner) or a studio applying to partner (origin collaboration).
// Same inquiries table and screen as Sales › Inquiries (AdminInquiryDetail).
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const route = useRoute()
</script>

<template>
  <AdminInquiryDetail :inquiry-id="String(route.params.id)" section="collaborations" />
</template>
