// Unit tests for the PDF document renderer (server/utils/pdf) — pure TypeScript
// with no Nuxt runtime, so plain node is enough. Run: `npm test` (CI) or
// `docker compose -f docker-compose.dev.yml exec frontend npm test`.
import { defineConfig } from 'vitest/config'

export default defineConfig({
  test: {
    include: ['server/**/*.test.ts'],
    environment: 'node',
  },
})
