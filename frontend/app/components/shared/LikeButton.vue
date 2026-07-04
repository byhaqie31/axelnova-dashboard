<script setup lang="ts">
/**
 * Anonymous like pill — heart + count. Optimistic toggle, reconciled with the
 * server. Identity is a per-browser cookie_id (localStorage) plus the server's
 * IP hash for dedupe; the "liked" state is mirrored in localStorage so the heart
 * stays filled across visits without an extra request. SSR-safe: starts unliked,
 * hydrates the real state on mount.
 */
const props = withDefaults(defineProps<{
  type: 'project' | 'service_package'
  id: number
  count?: number
  size?: 'sm' | 'md'
}>(), {
  count: 0,
  size: 'sm',
})

const base = useApiBase()
const liked = ref(false)
const count = ref(props.count)
const pending = ref(false)

const STORE = 'axn_likes'
const COOKIE = 'axn_like_cookie'

function readMap(): Record<string, boolean> {
  try { return JSON.parse(localStorage.getItem(STORE) || '{}') }
  catch { return {} }
}
const storeKey = computed(() => `${props.type}:${props.id}`)

function cookieId(): string {
  let c = localStorage.getItem(COOKIE)
  if (!c) {
    c = (crypto?.randomUUID?.() ?? `${Date.now()}-${Math.round(Math.random() * 1e9)}`)
    localStorage.setItem(COOKIE, c)
  }
  return c
}

onMounted(() => {
  liked.value = !!readMap()[storeKey.value]
})

// Keep in sync if the parent's count prop updates (e.g. list refetch).
watch(() => props.count, v => { count.value = v })

async function toggle() {
  if (pending.value) return
  pending.value = true

  const prevLiked = liked.value
  const prevCount = count.value
  liked.value = !liked.value
  count.value = Math.max(0, count.value + (liked.value ? 1 : -1))

  try {
    const res = await $fetch<{ liked: boolean, count: number }>(
      `${base}/api/v1/likes/${props.type}/${props.id}`,
      { method: 'POST', body: { cookie_id: cookieId() } },
    )
    liked.value = res.liked
    count.value = res.count
    const map = readMap()
    if (res.liked) map[storeKey.value] = true
    else Reflect.deleteProperty(map, storeKey.value)
    localStorage.setItem(STORE, JSON.stringify(map))
  }
  catch {
    liked.value = prevLiked
    count.value = prevCount
  }
  finally {
    pending.value = false
  }
}
</script>

<template>
  <button
    type="button"
    class="like-btn inline-flex items-center gap-1.5 rounded-full border transition-colors"
    :class="size === 'md' ? 'h-9 px-3.5 text-[13px]' : 'h-7 px-2.5 text-[12px]'"
    :data-liked="liked"
    :aria-pressed="liked"
    :disabled="pending"
    :title="liked ? 'Remove like' : 'Like'"
    @click.stop.prevent="toggle"
  >
    <UIcon
      :name="liked ? 'i-fluent-heart-24-filled' : 'i-fluent-heart-24-regular'"
      :class="size === 'md' ? 'size-4' : 'size-3.5'"
    />
    <span class="tabular-nums font-medium">{{ count }}</span>
  </button>
</template>

<style scoped>
.like-btn {
  border-color: var(--color-border);
  background: var(--color-bg);
  color: var(--color-text-secondary);
}
.like-btn:hover:not(:disabled) {
  border-color: var(--color-border-strong);
  color: var(--color-text);
}
.like-btn[data-liked="true"] {
  border-color: rgba(255, 59, 48, 0.35);
  background: rgba(255, 59, 48, 0.08);
  color: var(--color-danger);
}
.like-btn:active:not(:disabled) {
  transform: scale(0.96);
}
</style>
