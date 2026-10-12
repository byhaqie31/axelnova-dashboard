<script setup lang="ts">
// One-time access link landing (?token=…) — see components/shared/AccessLinkForm.vue.
import AccessLinkForm from '~/components/shared/AccessLinkForm.vue'

definePageMeta({ layout: false })
useHead({ title: 'Reset password — Axel Nova Admin' })
useSeoMeta({ robots: 'noindex, nofollow' })
</script>

<template>
  <AccessLinkForm surface="admin" />
</template>
