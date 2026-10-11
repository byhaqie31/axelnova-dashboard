// Unit tests for the PDF document renderer (server/utils/pdf) and pure app
// helpers (app/utils) — plain TypeScript with no Nuxt runtime, so node is enough. Run: `npm test` (CI) or
// `docker compose -f docker-compose.dev.yml exec frontend npm test`.
import { defineConfig } from 'vitest/config'

export default defineConfig({
  test: {
    include: ['server/**/*.test.ts', 'app/utils/**/*.test.ts'],
    environment: 'node',
  },
})
