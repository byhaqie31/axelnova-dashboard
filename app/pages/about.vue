<script setup lang="ts">
import SectionHeader from '~/components/shared/SectionHeader.vue'

const photos = [
  '/baihaqie.png',
  '/baihaqie2.png',
  '/baihaqie3.png',
  '/baihaqie4.png',
  '/baihaqie5.png',
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

const education = [
  {
    period: 'In Progress',
    badge: 'In Progress',
    badgeTone: 'progress',
    title: 'Master of Science in Human Resource Development',
    institution: 'Universiti Putra Malaysia (UPM)',
    location: 'Serdang, Selangor',
    detail: 'Postgraduate research on AI adoption, digital transformation, and intergenerational dynamics in technology-driven organisations.',
  },
  {
    period: 'Oct 2023',
    badge: 'CGPA 3.77 / 4.00',
    badgeTone: 'success',
    title: 'Bachelor of Islamic Studies and Information Technology (First Class Honours)',
    institution: 'Universiti Malaya (UM)',
    location: 'Kuala Lumpur, W.P',
    detail: 'Bridged software engineering fundamentals with Islamic studies — graduated with First Class Honours.',
  },
  {
    period: 'May 2019',
    badge: 'CGPA 3.44 / 4.00',
    badgeTone: 'neutral',
    title: 'Foundation of Islamic Studies and Science',
    institution: 'Universiti Malaya (UM)',
    location: 'Kuala Lumpur, W.P',
    detail: 'Pre-university foundation programme covering core sciences alongside Islamic studies.',
  },
]

const experience = [
  {
    period: 'Dec 2023 — Present',
    badge: 'Current',
    badgeTone: 'success',
    role: 'Software Engineer — UI/UX',
    company: 'Fiuu (formerly Razer Merchant Services)',
    location: 'Shah Alam, Selangor',
    bullets: [
      'Designed and developed high-quality UIs using Vue.js, Tailwind CSS, and modern front-end architectures across the Merchant Portal, Admin Portal, and multiple fintech payment modules.',
      'Collaborated with product owners, backend developers, QA testers, and business teams to identify pain points, refine requirements, and deploy incremental improvements.',
      'Enhanced merchant experience by streamlining UI flows, refining error messages, improving form validations, and optimising data-driven interfaces.',
    ],
    stack: ['Vue.js', 'Tailwind CSS', 'JavaScript', 'REST API', 'Figma', 'GitLab'],
  },
  {
    period: 'Jul 2020 — Oct 2020',
    badge: null,
    badgeTone: 'neutral',
    role: 'Graphic Designer & Technician',
    company: 'Faztech Services',
    location: 'Tangkak, Johor',
    bullets: [
      'Designed professional artworks across 6 Adobe applications (Illustrator, After Effects) — brochures, business cards, and flyers — meeting full client briefs.',
      'Collaborated with a team of 8 technicians on program installation and OS maintenance, with 0% callback from upper management.',
      'Resolved recurring client issues (anti-virus, OS installation) — 8 of 10 cases per week closed within standard BAU protocols.',
    ],
    stack: ['Adobe Illustrator', 'After Effects', 'Photoshop'],
  },
]

const badgeStyle = (tone: string) => {
  switch (tone) {
    case 'success':  return { color: 'var(--color-success)', background: 'rgba(48,209,88,0.14)' }
    case 'progress': return { color: 'var(--color-warning)', background: 'rgba(255,159,10,0.14)' }
    case 'neutral':
    default:         return { color: 'var(--color-text-secondary)', background: 'var(--color-bg-secondary)' }
  }
}

useScrollReveal('.reveal')
</script>

<template>
  <div class="max-w-7xl mx-auto px-6 pt-24 pb-32">
    <SectionHeader
      eyebrow="About"
      title="The builder behind the screen."
    />

    <!-- Bio -->
    <section class="grid lg:grid-cols-[1.4fr_1fr] gap-10 lg:gap-16 mb-32 reveal">
      <div class="space-y-5 text-[17px] leading-[1.65]" style="color: var(--color-text);">
        <p>
          I'm <strong>Qie</strong>, a UI/UX-focused software engineer who builds products where
          design clarity meets real-world functionality.
        </p>
        <p style="color: var(--color-text-secondary);">
          I specialise in turning complex systems into simple, intuitive experiences, especially
          in high-stakes environments like fintech platforms, admin tools, and scalable web
          applications. My strength sits at the intersection of interface design, frontend
          engineering, and system thinking, allowing me to bridge user needs with technical
          execution in a way that feels natural and seamless.
        </p>
        <p style="color: var(--color-text-secondary);">
          I care deeply about how things feel in use. Every flow, every interaction, every edge
          case matters. To me, a product is not just something that works. It should feel
          effortless, almost invisible, like it was always meant to be that way.
        </p>
        <p style="color: var(--color-text-secondary);">
          I'm constantly evolving my thinking around AI, human-centered technology, and digital
          systems. I'm drawn to the idea of building things that matter, not just functionally,
          but emotionally and experientially.
          <span style="color: var(--color-text);" class="font-medium">axelnova</span>
          is where I explore that vision and slowly shape it into something real.
        </p>
        <p style="color: var(--color-text-secondary);">
          Outside of work, I'm building a life centered around growth, exploration, and intention.
          I find meaning in travelling, in stepping into unfamiliar places, in seeing the world
          from perspectives that challenge and reshape my own.
        </p>
        <p style="color: var(--color-text-secondary);">
          I'm driven by a quiet refusal to live an average life. I want to create work that feels
          meaningful, to experience life fully, and to keep evolving into someone better than I
          was yesterday, both in what I build and in who I become.
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
            href="mailto:byhaqie1455@gmail.com?subject=Hello%20Qie"
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

    <!-- Academic -->
    <section class="mb-32 reveal">
      <div class="mb-10">
        <p class="eyebrow mb-2">Academic</p>
        <h3 class="text-3xl font-semibold tracking-tight">Where I study.</h3>
      </div>

      <div class="space-y-4">
        <div
          v-for="e in education" :key="e.title"
          class="grid grid-cols-1 md:grid-cols-[200px_1fr] gap-3 md:gap-10 rounded-2xl border p-7 transition-colors"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
        >
          <div>
            <p class="text-[13px] font-medium" style="color: var(--color-text-secondary);">
              {{ e.period }}
            </p>
            <p class="text-[12px] mt-1" style="color: var(--color-text-tertiary);">
              {{ e.location }}
            </p>
          </div>

          <div>
            <div class="flex items-start justify-between gap-3 mb-2 flex-wrap">
              <p class="text-[18px] font-semibold tracking-tight" style="color: var(--color-text);">
                {{ e.title }}
              </p>
              <span
                class="text-[11px] font-medium px-2.5 py-1 rounded-full whitespace-nowrap"
                :style="badgeStyle(e.badgeTone)"
              >
                {{ e.badge }}
              </span>
            </div>
            <p class="text-[14px] mb-3" style="color: var(--color-text-secondary);">
              {{ e.institution }}
            </p>
            <p class="text-[14px] leading-relaxed" style="color: var(--color-text-secondary);">
              {{ e.detail }}
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Experience -->
    <section class="mb-32 reveal">
      <div class="mb-10">
        <p class="eyebrow mb-2">Experience</p>
        <h3 class="text-3xl font-semibold tracking-tight">Where I've worked.</h3>
      </div>

      <div class="space-y-4">
        <div
          v-for="job in experience" :key="job.role + job.company"
          class="grid grid-cols-1 md:grid-cols-[200px_1fr] gap-3 md:gap-10 rounded-2xl border p-7 transition-colors"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
        >
          <div>
            <p class="text-[13px] font-medium" style="color: var(--color-text-secondary);">
              {{ job.period }}
            </p>
            <p class="text-[12px] mt-1" style="color: var(--color-text-tertiary);">
              {{ job.location }}
            </p>
          </div>

          <div>
            <div class="flex items-start justify-between gap-3 mb-2 flex-wrap">
              <p class="text-[18px] font-semibold tracking-tight" style="color: var(--color-text);">
                {{ job.role }}
              </p>
              <span
                v-if="job.badge"
                class="text-[11px] font-medium px-2.5 py-1 rounded-full inline-flex items-center gap-1.5 whitespace-nowrap"
                :style="badgeStyle(job.badgeTone)"
              >
                <span class="size-1.5 rounded-full" :style="{ background: badgeStyle(job.badgeTone).color }" />
                {{ job.badge }}
              </span>
            </div>
            <p class="text-[14px] mb-4" style="color: var(--color-text-secondary);">
              {{ job.company }}
            </p>

            <ul class="space-y-2 mb-5">
              <li
                v-for="b in job.bullets" :key="b"
                class="flex gap-2.5 text-[14px] leading-relaxed"
                style="color: var(--color-text-secondary);"
              >
                <span aria-hidden class="mt-2 size-1 shrink-0 rounded-full" :style="{ background: 'var(--color-text-tertiary)' }" />
                <span>{{ b }}</span>
              </li>
            </ul>

            <div class="flex flex-wrap gap-1.5">
              <span
                v-for="s in job.stack" :key="s"
                class="text-[11px] px-2 py-0.5 rounded-md border"
                :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text-secondary)' }"
              >{{ s }}</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Closing note -->
    <section class="reveal">
      <h3 class="text-3xl font-semibold tracking-tight mb-6">A note.</h3>
      <p class="text-[17px] leading-[1.6] max-w-2xl" style="color: var(--color-text-secondary);">
        I build things that hold up — under real load, real deadlines, and real users.
        Not everything I make is finished. But everything I start, I care about.
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
</style>
