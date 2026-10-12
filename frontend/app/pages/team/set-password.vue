<script setup lang="ts">
// One-time access link landing (?token=…) — see components/shared/AccessLinkForm.vue.
import AccessLinkForm from '~/components/shared/AccessLinkForm.vue'

definePageMeta({ layout: false })
useHead({ title: 'Set your password — Axel Nova Team' })
useSeoMeta({ robots: 'noindex, nofollow' })
</script>

<template>
  <AccessLinkForm surface="team" />
</template>
