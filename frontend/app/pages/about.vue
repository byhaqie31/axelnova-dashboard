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

const beliefs = [
  'Design is thinking made visible.',
  'Good products respect the people who use them.',
  'The best engineers understand people, not just systems.',
  'Curiosity is the only sustainable competitive advantage.',
]

const timeline = [
  {
    year: '2019',
    chapter: 'The Beginning',
    title: 'Foundation at Universiti Malaya',
    description: 'Chose an unconventional path — Islamic Studies and Information Technology. Not despite the breadth, but because of it. Faith and technology were never opposites in my mind.',
    isCurrent: false,
  },
  {
    year: '2020',
    chapter: 'First Real Work',
    title: 'Designing for Real People',
    description: 'Started at a print shop in Johor — designing brochures and fixing computers for ordinary people with real problems. Learned that the gap between a design and the person it serves is the most important gap to close.',
    isCurrent: false,
  },
  {
    year: '2023',
    chapter: 'Graduating',
    title: 'First Class. Two Worlds.',
    description: 'Graduated with First Class Honours. The degree wasn\'t just a credential — it was proof that bridging two disciplines creates something neither alone can.',
    isCurrent: false,
  },
  {
    year: '2023',
    chapter: 'Into Fintech',
    title: 'Building Systems That Can\'t Fail',
    description: 'Joined Fiuu — payments infrastructure moving real money for real merchants. Learned what it means to build UI where errors carry real consequences. Every pixel has weight.',
    isCurrent: false,
  },
  {
    year: 'Now',
    chapter: 'In Progress',
    title: 'Researching. Building. Becoming.',
    description: 'Postgraduate research on AI and people at UPM. Building axelnova as a space to explore what I actually believe about technology. Still asking more questions than I can answer.',
    isCurrent: true,
  },
]

const dreams = [
  {
    icon: 'i-lucide-globe',
    title: 'A product that outlasts me',
    description: 'Build something people rely on — not because they have to, but because it genuinely improves how they live or work. A product with a soul.',
  },
  {
    icon: 'i-lucide-plane',
    title: 'See the world with intention',
    description: '50 countries before I turn 40. Not tourism — immersion. Every place I visit reshapes how I think and what I build.',
  },
  {
    icon: 'i-lucide-book-open',
    title: 'Write something worth keeping',
    description: 'A book. A body of essays. Thinking that survives the screen and lasts beyond the moment it was written.',
  },
  {
    icon: 'i-lucide-users',
    title: 'Lead a team worth following',
    description: 'Build an organisation where craft is valued, people grow, and the work actually matters. Culture is a product too.',
  },
  {
    icon: 'i-lucide-brain',
    title: 'Understand AI before it defines us',
    description: 'I want to understand what happens to human identity when machines become collaborators — and help shape that answer before someone else does.',
  },
  {
    icon: 'i-lucide-star',
    title: 'A life built on intention',
    description: 'Not defined by titles or metrics — by the quality of the work, the depth of the relationships, and the spaces I choose to inhabit.',
  },
]

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
          I'm <strong>Qie</strong>, a UI/UX-focused software engineer building products where
          design clarity meets real-world functionality.
        </p>
        <p style="color: var(--color-text-secondary);">
          I specialise in transforming complex systems into intuitive digital experiences, especially
          within fintech platforms, admin systems, and scalable web applications. My work sits between
          interface design, frontend engineering, and system thinking, allowing me to bridge user needs
          with technical execution in a seamless and thoughtful way.
        </p>
        <p style="color: var(--color-text-secondary);">
          I care deeply about how things feel in use. Every interaction, every flow, and every detail
          matters. To me, great products should feel effortless, almost invisible.
        </p>
        <p style="color: var(--color-text-secondary);">
          I'm constantly exploring the relationship between AI, human-centered technology, and digital
          experiences.
          <span style="color: var(--color-text);" class="font-medium">axelnova</span>
          is where I shape those ideas into something meaningful, both functionally and emotionally.
        </p>
        <p style="color: var(--color-text-secondary);">
          Outside of work, I'm drawn to growth, travel, and experiences that challenge perspective.
          I'm driven by the belief that life shouldn't be lived on autopilot. I want to build meaningful
          things, experience the world fully, and continuously evolve into a better version of myself
          through both the work I create and the life I choose to live.
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
            I grew up with a quiet obsession — how do things work, and how could they work better?
            That curiosity led me to an unconventional degree:
            <span style="color: var(--color-text);" class="font-medium">Islamic Studies and Information Technology</span>
            at Universiti Malaya. Not the obvious path for a software engineer. The best decision I ever made.
          </p>
          <p>
            That combination gave me something most engineers don't have — a framework for thinking about the
            human side of technology. Jurisprudence taught me to reason carefully under uncertainty.
            Islamic philosophy taught me that knowledge is service. Those ideas still show up in how I build today.
          </p>
          <p>
            My first real job wasn't at a tech company. It was a print shop in Johor — designing brochures
            and fixing computers for people with real, ordinary problems. I learned there that the gap between
            a design and the person it serves is the most important gap to close.
          </p>
          <p style="color: var(--color-text);" class="font-medium">
            Everything since has been about closing that gap. Building things that don't just work,
            but feel like they were made for the person using them.
          </p>
        </div>

        <!-- Beliefs sidebar -->
        <div>
          <p
            class="text-[11px] font-medium uppercase tracking-widest mb-5"
            style="color: var(--color-text-tertiary);"
          >
            Things I believe
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
            <p class="text-[11px] font-medium uppercase tracking-wider mb-1.5" style="color: var(--color-accent);">
              {{ item.chapter }}
            </p>
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
