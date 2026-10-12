<script setup lang="ts">
import PriceTag from '~/components/shared/primitives/PriceTag.vue'

// One catalogue package as a card — the /services listing and each service
// page (/services/{slug}) share it, so the two always show the same package.
// The listing passes `featureLimit` + `moreTo` for the brief card: the first N
// features (in the admin's order, so the strongest go first) and a
// "+N more features" link to the full card on the service page, which carries
// the package slug as its `id` (the link's #anchor).
//
// `headingLevel` fits the name to the page outline: h3 on the listing, h4
// under the service page's "Packages" h3.
//
// The card is an @container: the price drops to 26px when the card is narrower
// than 24rem (the service page's 3-up, the listing below xl) so a range like
// "RM 10,000 – 16,000" stays on one line.
interface ServicePackage {
  slug: string
  name: string
  tagline: string
  price_min_myr: string | number
  price_max_myr: string | number | null
  unit: string
  duration_text: string
  revisions: string | null
  featured: boolean
  features: string[]
  cta: string | null
  quote_key: { category: string, package: string } | null
}

const props = withDefaults(defineProps<{
  pkg: ServicePackage
  headingLevel?: 'h3' | 'h4'
  /** Show only the first N features, then a "+N more features" link to `moreTo`. 0 = all. */
  featureLimit?: number
  /** The full card on the service page, e.g. `/services/web-presence#web-starter`. */
  moreTo?: string
  /** Indicative conversion from MYR — the listing's currency switcher. */
  convert?: (myr: number) => number
  /** Currency symbol matching `convert`. */
  symbol?: string
}>(), {
  headingLevel: 'h3',
  featureLimit: 0,
  moreTo: undefined,
  convert: undefined,
  symbol: 'RM',
})

const priceMin = computed(() => toCurrency(props.pkg.price_min_myr))
const priceMax = computed(() => props.pkg.price_max_myr === null ? null : toCurrency(props.pkg.price_max_myr))

function toCurrency(myr: string | number): number {
  const n = Number(myr)
  return props.convert ? props.convert(n) : n
}

const features = computed(() =>
  props.featureLimit ? props.pkg.features.slice(0, props.featureLimit) : props.pkg.features,
)
const moreCount = computed(() => props.pkg.features.length - features.value.length)
</script>

<template>
  <div
    :id="pkg.slug"
    class="@container relative rounded-2xl border p-8 flex flex-col"
    :style="{
      background: 'var(--color-bg-secondary)',
      borderColor: pkg.featured ? 'var(--color-accent)' : 'var(--color-border)',
      borderWidth: pkg.featured ? '2px' : '1px',
    }"
  >
    <!-- Popular badge -->
    <span
      v-if="pkg.featured"
      class="absolute -top-3 left-1/2 -translate-x-1/2 text-[11px] font-medium px-3 py-1 rounded-full"
      style="background: var(--color-accent); color: white;"
    >
      Most popular
    </span>

    <!-- Name + tagline -->
    <component :is="headingLevel" class="text-[20px] font-semibold tracking-tight mb-1" style="color: var(--color-text);">
      {{ pkg.name }}
    </component>
    <p class="text-[13px] mb-6" style="color: var(--color-text-secondary);">
      {{ pkg.tagline }}
    </p>

    <!-- Price -->
    <PriceTag
      class="mb-6 text-[26px] @sm:text-[30px] font-semibold tracking-tight leading-tight"
      stacked
      :min="priceMin"
      :max="priceMax"
      :unit="pkg.unit"
      :symbol="symbol"
    />

    <!-- Meta row -->
    <div
      class="flex items-center gap-4 pb-6 mb-6 border-b"
      :style="{ borderColor: 'var(--color-border)' }"
    >
      <span class="inline-flex items-center gap-1.5 text-[12px]" style="color: var(--color-text-secondary);">
        <UIcon name="i-lucide-clock" class="size-3.5 shrink-0" />
        {{ pkg.duration_text }}
      </span>
      <span
        v-if="pkg.revisions"
        class="inline-flex items-center gap-1.5 text-[12px]"
        style="color: var(--color-text-secondary);"
      >
        <UIcon name="i-lucide-rotate-ccw" class="size-3.5 shrink-0" />
        {{ pkg.revisions }}
      </span>
    </div>

    <!-- Features (+ the link to the rest on the brief card) -->
    <div class="flex-1 mb-8">
      <ul class="space-y-2.5">
        <li
          v-for="f in features"
          :key="f"
          class="flex items-start gap-2.5 text-[14px]"
          style="color: var(--color-text);"
        >
          <UIcon
            name="i-fluent-checkmark-24-regular"
            class="size-4 mt-0.5 shrink-0"
            style="color: var(--color-accent);"
          />
          <span>{{ f }}</span>
        </li>
      </ul>
      <NuxtLink
        v-if="moreTo && moreCount > 0"
        :to="moreTo"
        class="inline-flex items-center gap-1.5 text-[13px] font-medium mt-4 transition-all hover:gap-2.5"
        style="color: var(--color-accent);"
      >
        +{{ moreCount }} more {{ moreCount === 1 ? 'feature' : 'features' }} <span aria-hidden>→</span>
      </NuxtLink>
    </div>

    <!-- CTA -->
    <NuxtLink
      v-if="pkg.quote_key"
      :to="{ path: '/quote', query: { category: pkg.quote_key.category, package: pkg.quote_key.package } }"
      class="btn-pill text-center"
      :class="pkg.featured ? 'btn-pill-accent' : 'btn-pill-primary'"
    >
      {{ pkg.cta ?? 'Get a quote' }}
    </NuxtLink>
    <a
      v-else
      href="mailto:baihaqie@axelnova.tech"
      class="btn-pill text-center"
      :class="pkg.featured ? 'btn-pill-accent' : 'btn-pill-primary'"
    >
      {{ pkg.cta ?? 'Get a quote' }}
    </a>
  </div>
</template>
