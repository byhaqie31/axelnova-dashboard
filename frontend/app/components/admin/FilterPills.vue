<script setup lang="ts">
// A labelled pill group for use inside AdminFilterMenu (§12.6). Single-select;
// the empty-value option ('All') reads as "no filter".
interface Option { value: string, label: string }

defineProps<{
  label: string
  modelValue: string
  options: Option[]
}>()

defineEmits<{ 'update:modelValue': [value: string] }>()
</script>

<template>
  <div>
    <p class="text-[11px] font-medium uppercase tracking-wide mb-1.5" style="color: var(--color-text-tertiary);">{{ label }}</p>
    <div class="flex flex-wrap gap-1.5">
      <button
        v-for="o in options"
        :key="o.value"
        type="button"
        class="standard-pill"
        :style="modelValue === o.value ? { borderColor: 'var(--color-accent)', background: 'var(--color-accent-soft)', color: 'var(--color-accent)' } : {}"
        @click="$emit('update:modelValue', o.value)"
      >{{ o.label }}</button>
    </div>
  </div>
</template>
