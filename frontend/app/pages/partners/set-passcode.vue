<script setup lang="ts">
// One-time access link landing (?token=…) — see components/shared/AccessLinkForm.vue.
import AccessLinkForm from '~/components/shared/AccessLinkForm.vue'

definePageMeta({ layout: false })
useHead({ title: 'Set your passcode — Axel Nova Partner Portal' })
useSeoMeta({ robots: 'noindex, nofollow' })
</script>

<template>
  <AccessLinkForm surface="partner" />
</template>
