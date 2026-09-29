<script setup lang="ts">
definePageMeta({ layout: 'public' })

usePublicSeo({
  title: 'About Ahmad Baihaqie (Qie) | Axel Nova Ventures',
  description: 'Meet Ahmad Baihaqie, known as Qie, the Kuala Lumpur software engineer behind Axel Nova Ventures. His work spans UI/UX, frontend and fintech systems.',
  path: '/about',
})

// The founder as a Person, tied to the homepage's ProfessionalService node.
useHead({
  script: [{
    type: 'application/ld+json',
    innerHTML: JSON.stringify({
      '@context': 'https://schema.org',
      '@type': 'Person',
      '@id': 'https://axelnovaventures.com/about#person',
      name: 'Ahmad Baihaqie',
      alternateName: 'Qie',
      jobTitle: 'Founder & Software Engineer',
      url: 'https://axelnovaventures.com/about',
      worksFor: { '@id': 'https://axelnovaventures.com/#business' },
      address: { '@type': 'PostalAddress', addressLocality: 'Kuala Lumpur', addressCountry: 'MY' },
      knowsAbout: ['Web development', 'UI/UX design', 'Frontend engineering', 'Laravel', 'Nuxt'],
      sameAs: ['https://github.com/byhaqie31', 'https://linkedin.com/in/byhaqieyusri', 'https://baihaqie.com'],
    }),
  }],
})

import SectionHeader from '~/components/shared/SectionHeader.vue'
import { usePublicSeo } from '~/composables/usePublicSeo'

const photos = [
  '/baihaqie.png',
  '/baihaqie2.png',
  '/baihaqie3.png',
  '/baihaqie4.png',
  '/baihaqie5.png',
  '/baihaqie6.png',
]
const photoIndex = ref(0)
const isPaused = ref(false)
let photoTimer: ReturnType<typeof setInterval> | null = null

const goToPhoto = (i: number) => { photoIndex.value = (i + photos.length) % photos.length }
const nextPhoto = () => goToPhoto(photoIndex.value + 1)
const prevPhoto = () => goToPhoto(photoIndex.value - 1)

onMounted(() => {
  if (import.meta.server) return
  const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  if (prefersReduced) return

  photoTimer = setInterval(() => {
    if (!isPaused.value) nextPhoto()
  }, 4500)
})

onBeforeUnmount(() => {
  if (photoTimer) clearInterval(photoTimer)
})

const skills = [
  {
    group: 'Frontend',
    items: ['Vue 3', 'Nuxt 4', 'Tailwind CSS', 'GSAP', 'TypeScript', 'Figma', 'Adobe Suite'],
  },
  {
    group: 'Backend',
    items: ['PHP', 'Laravel', 'Node.js', 'FastAPI', 'REST API design'],
  },
  {
    group: 'Data & Queue',
    items: ['MySQL', 'CouchDB', 'Redis', 'RabbitMQ', 'Gearmand'],
  },
  {
    group: 'Infrastructure',
    items: ['Docker', 'AWS', 'Nginx', 'GitHub Actions', 'Linux', 'Hostinger VPS'],
  },
  {
    group: 'Tools',
    items: ['Git', 'Postman', 'DBeaver', 'phpMyAdmin', 'Jira', 'VSCode'],
  },
]

const beliefs = [
  'Good products respect the people who use them.',
]

const timeline = [
  {
    year: '2019',
    title: 'Finding my direction.',
    description: 'I chose Islamic Studies and Information Technology at Universiti Malaya, a combination that let me study systems and people together.',
    isCurrent: false,
  },
  {
    year: '2020',
    title: 'First real work.',
    description: 'At a print shop in Johor, I learned to solve everyday problems for customers and explain the result clearly.',
    isCurrent: false,
  },
  {
    year: '2023',
    title: 'Graduating.',
    description: 'I completed my degree with First Class Honours and moved into software engineering.',
    isCurrent: false,
  },
  {
    year: '2023',
    title: 'Building payment experiences.',
    description: 'At Fiuu, I worked on interfaces where accuracy and clarity matter to merchants and their customers.',
    isCurrent: false,
  },
  {
    year: 'Now',
    title: 'Research and building.',
    description: 'I\'m pursuing postgraduate research on AI and workplace change while building Axel Nova Ventures.',
    isCurrent: true,
  },
]

const dreams = [
  {
    icon: 'i-lucide-globe',
    title: 'Build a product people rely on.',
    description: 'I want to create something useful enough to become part of someone\'s daily work or life.',
  },
  {
    icon: 'i-lucide-plane',
    title: 'Visit 50 countries before 40.',
    description: 'I want to learn from the places I visit, beyond the usual landmarks.',
  },
  {
    icon: 'i-lucide-book-open',
    title: 'Write a book.',
    description: 'I want to give my ideas the time and care they need beyond a short post.',
  },
  {
    icon: 'i-lucide-users',
    title: 'Lead a strong team.',
    description: 'I want to build a workplace where people can do careful work and keep learning.',
  },
  {
    icon: 'i-lucide-brain',
    title: 'Understand AI\'s effect on work.',
    description: 'My research asks how people adapt when AI becomes part of their everyday tasks.',
  },
  {
    icon: 'i-lucide-star',
    title: 'Make room for a full life.',
    description: 'Work matters to me, and so do relationships, travel and time to think.',
  },
]

useScrollReveal('.reveal')
</script>

<template>
  <div class="max-w-7xl mx-auto px-6 pt-24 pb-32">
    <SectionHeader
      as="h1"
      wide
      eyebrow="About"
      title="The builder behind Axel Nova."
    />

    <!-- Bio -->
    <section class="grid lg:grid-cols-[1.4fr_1fr] gap-10 lg:gap-16 mb-32 reveal">
      <div class="space-y-5 text-[17px] leading-[1.65]" style="color: var(--color-text);">
        <p>
          I'm <strong>Ahmad Baihaqie</strong>. Most people call me <strong>Qie</strong>.
          I'm a UI/UX-focused software engineer in Kuala Lumpur, working across interface design,
          frontend development and digital products.
        </p>
        <p style="color: var(--color-text-secondary);">
          In fintech, I've worked on payment experiences where a confusing screen or an unclear error
          message has real consequences. That work shaped how I approach every project: understand the
          task first, then make each step clear to the person using it.
        </p>
        <p style="color: var(--color-text-secondary);">
          I enjoy the point where design decisions meet working software. I can explore an idea in
          Figma, build it in code and see how it behaves on a real device.
        </p>
        <p style="color: var(--color-text-secondary);">
          I'm also studying how people adapt to AI at work.
          <span style="color: var(--color-text);" class="font-medium">Axel Nova Ventures</span>
          gives me a place to apply that curiosity to useful products and client projects.
        </p>
        <p style="color: var(--color-text-secondary);">
          Outside work, I travel whenever I can. Airports, hotels and unfamiliar cities have made me
          notice the small details that turn a complicated journey into an easy one.
        </p>

        <div class="pt-3 flex flex-wrap items-center gap-3">
          <a
            href="https://baihaqie.com"
            target="_blank"
            rel="noopener"
            class="btn-pill btn-pill-ghost"
          >
            See full portfolio →
          </a>
          <a
            href="mailto:baihaqie@axelnova.tech?subject=Hello%20Qie"
            class="text-[13px] font-medium"
            style="color: var(--color-accent);"
          >
            Or just say hi!
          </a>
        </div>
      </div>

      <div
        class="photo-carousel rounded-3xl border aspect-square relative overflow-hidden group"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-secondary)' }"
        role="region"
        aria-roledescription="carousel"
        aria-label="Photos of Ahmad Baihaqie"
        @mouseenter="isPaused = true"
        @mouseleave="isPaused = false"
        @focusin="isPaused = true"
        @focusout="isPaused = false"
      >
        <!-- Slides (crossfade) -->
        <img
          v-for="(src, i) in photos" :key="src"
          :src="src"
          :alt="`Ahmad Baihaqie — ${i + 1}`"
          :loading="i === 0 ? 'eager' : 'lazy'"
          decoding="async"
          width="640"
          height="640"
          class="photo-slide absolute inset-0 w-full h-full object-cover transition-opacity duration-700 ease-out"
          :class="i === photoIndex ? 'opacity-100' : 'opacity-0'"
          :aria-hidden="i !== photoIndex"
        />

        <!-- Subtle Apple Blue tint, on top of image -->
        <div
          aria-hidden
          class="absolute inset-0 pointer-events-none mix-blend-overlay opacity-25"
          style="background: radial-gradient(circle at 30% 20%, rgba(0,113,227,0.4), transparent 55%);"
        />

        <!-- Bottom gradient for caption legibility -->
        <div
          aria-hidden
          class="absolute inset-x-0 bottom-0 h-28 pointer-events-none"
          style="background: linear-gradient(to top, rgba(0,0,0,0.55), transparent);"
        />

        <!-- Caption -->
        <span
          class="absolute bottom-5 left-5 text-[12px] font-medium tracking-wide"
          style="color: rgba(255,255,255,0.94);"
        >
          Ahmad Baihaqie
        </span>

        <!-- Prev / Next arrows (appear on hover) -->
        <button
          type="button"
          class="carousel-arrow left-3 backdrop-blur-md"
          aria-label="Previous photo"
          @click="prevPhoto"
        >
          <UIcon name="i-fluent-chevron-left-24-regular" class="size-4" />
        </button>
        <button
          type="button"
          class="carousel-arrow right-3 backdrop-blur-md"
          aria-label="Next photo"
          @click="nextPhoto"
        >
          <UIcon name="i-fluent-chevron-right-24-regular" class="size-4" />
        </button>

        <!-- Dot pagination -->
        <div class="absolute bottom-5 right-5 flex items-center gap-1.5">
          <button
            v-for="(_, i) in photos" :key="i"
            type="button"
            class="dot transition-all duration-300"
            :class="i === photoIndex ? 'dot-active' : ''"
            :aria-label="`Go to photo ${i + 1}`"
            :aria-current="i === photoIndex"
            @click="goToPhoto(i)"
          />
        </div>
      </div>
    </section>

    <!-- Skills -->
    <section class="mb-32 reveal">
      <h3 class="text-3xl font-semibold tracking-tight mb-10">Skills.</h3>
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-10">
        <div v-for="g in skills" :key="g.group">
          <p class="text-[13px] font-medium mb-4" style="color: var(--color-accent);">
            {{ g.group }}
          </p>
          <div class="flex flex-wrap gap-1.5">
            <span
              v-for="s in g.items" :key="s"
              class="text-[12px] px-2.5 py-1 rounded-md border"
              :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text-secondary)' }"
            >{{ s }}</span>
          </div>
        </div>
      </div>
    </section>

    <!-- My Story -->
    <section class="mb-32 reveal">
      <div class="mb-10">
        <p class="eyebrow mb-2">My Story</p>
        <h3 class="text-3xl font-semibold tracking-tight">How I got here.</h3>
      </div>
      <div class="grid lg:grid-cols-[1fr_320px] gap-10 lg:gap-16">
        <div class="space-y-5 text-[17px] leading-[1.65]" style="color: var(--color-text-secondary);">
          <p>
            My route into software began with
            <span style="color: var(--color-text);" class="font-medium">Islamic Studies and Information Technology</span>
            at Universiti Malaya. The mix taught me to question how a system works and whom it serves.
          </p>
          <p>
            My first job was at a print shop in Johor, designing brochures and fixing computers. People
            arrived with practical problems and needed a clear answer. I still think about that when I
            design a screen or build a workflow.
          </p>
          <p style="color: var(--color-text);" class="font-medium">
            Since then, I've brought that same attention to fintech platforms and other digital products.
            The details matter because someone depends on them to get something done.
          </p>
        </div>

        <!-- Beliefs sidebar -->
        <div>
          <p
            class="text-[11px] font-medium uppercase tracking-widest mb-5"
            style="color: var(--color-text-tertiary);"
          >
            What I believe
          </p>
          <div class="space-y-2.5">
            <div
              v-for="belief in beliefs" :key="belief"
              class="rounded-xl border px-4 py-3.5"
              :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
            >
              <p class="text-[14px] leading-relaxed" style="color: var(--color-text);">{{ belief }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Timeline -->
    <section class="mb-32 reveal">
      <div class="mb-10">
        <p class="eyebrow mb-2">Life in chapters</p>
        <h3 class="text-3xl font-semibold tracking-tight">How it unfolded.</h3>
      </div>

      <div class="max-w-2xl">
        <div
          v-for="(item, idx) in timeline"
          :key="item.year + item.title"
          class="relative pl-8"
          :class="idx < timeline.length - 1 ? 'pb-10' : ''"
        >
          <!-- Connecting line to next item -->
          <div
            v-if="idx < timeline.length - 1"
            class="absolute left-1.25 top-5 bottom-0 w-px"
            :style="{ background: 'var(--color-border)' }"
          />

          <!-- Dot -->
          <div
            class="absolute left-0 top-1.5 w-3 h-3 rounded-full"
            :class="item.isCurrent ? 'timeline-dot-current' : 'timeline-dot'"
          />

          <!-- Content -->
          <div>
            <div class="flex items-center gap-2.5 mb-1">
              <p class="text-[12px] font-medium" style="color: var(--color-text-tertiary);">
                {{ item.year }}
              </p>
              <span
                v-if="item.isCurrent"
                class="text-[11px] font-medium px-2 py-0.5 rounded-full"
                style="color: var(--color-warning); background: rgba(255,159,10,0.14);"
              >
                In progress
              </span>
            </div>
            <p class="text-[18px] font-semibold tracking-tight mb-2" style="color: var(--color-text);">
              {{ item.title }}
            </p>
            <p class="text-[14px] leading-relaxed" style="color: var(--color-text-secondary);">
              {{ item.description }}
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Dreams & Ambitions -->
    <section class="mb-32 reveal">
      <div class="mb-10">
        <p class="eyebrow mb-2">Ambitions</p>
        <h3 class="text-3xl font-semibold tracking-tight">What I'm after.</h3>
      </div>

      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
        <div
          v-for="dream in dreams"
          :key="dream.title"
          class="rounded-2xl border p-6 space-y-3"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
        >
          <div
            class="w-9 h-9 rounded-xl flex items-center justify-center"
            style="background: var(--color-accent-soft);"
          >
            <UIcon :name="dream.icon" class="size-4.5" style="color: var(--color-accent);" />
          </div>
          <p class="text-[16px] font-semibold tracking-tight" style="color: var(--color-text);">
            {{ dream.title }}
          </p>
          <p class="text-[14px] leading-relaxed" style="color: var(--color-text-secondary);">
            {{ dream.description }}
          </p>
        </div>
      </div>
    </section>

    <!-- Closing note -->
    <section class="reveal">
      <h3 class="text-3xl font-semibold tracking-tight mb-6">A note.</h3>
      <p class="text-[17px] leading-[1.6] max-w-2xl" style="color: var(--color-text-secondary);">
        I build for real people, real deadlines and real constraints. Each project teaches me
        something I carry into the next.
      </p>
    </section>
  </div>
</template>

<style scoped>
.carousel-arrow {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  width: 36px;
  height: 36px;
  border-radius: 9999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.18);
  color: rgba(255, 255, 255, 0.95);
  border: 1px solid rgba(255, 255, 255, 0.22);
  opacity: 0;
  transition: opacity 0.25s ease, background 0.2s ease, transform 0.2s ease;
}
.carousel-arrow:hover {
  background: rgba(255, 255, 255, 0.28);
}
.carousel-arrow:active {
  transform: translateY(-50%) scale(0.94);
}
.photo-carousel:hover .carousel-arrow,
.photo-carousel:focus-within .carousel-arrow {
  opacity: 1;
}

.dot {
  width: 6px;
  height: 6px;
  border-radius: 9999px;
  background: rgba(255, 255, 255, 0.45);
  cursor: pointer;
  border: none;
  padding: 0;
}
.dot:hover {
  background: rgba(255, 255, 255, 0.7);
}
.dot-active {
  width: 18px;
  background: rgba(255, 255, 255, 0.95);
}

.timeline-dot {
  background: var(--color-border-strong);
}

.timeline-dot-current {
  background: var(--color-warning);
  box-shadow: 0 0 0 4px rgba(255, 159, 10, 0.18);
  animation: pulse-dot 2.5s ease-in-out infinite;
}

@keyframes pulse-dot {
  0%, 100% { box-shadow: 0 0 0 4px rgba(255, 159, 10, 0.18); }
  50% { box-shadow: 0 0 0 8px rgba(255, 159, 10, 0.06); }
}

@media (prefers-reduced-motion: reduce) {
  .timeline-dot-current {
    animation: none;
  }
}
</style>
