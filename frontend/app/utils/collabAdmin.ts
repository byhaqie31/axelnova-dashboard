// Small pure helpers for the Partners › Collaborations admin pages.

/** One service per line → a clean list (trimmed, blanks and repeats dropped). */
export function servicesFromText(text: string): string[] {
  const seen = new Set<string>()
  return text
    .split('\n')
    .map(line => line.trim())
    .filter((line) => {
      const key = line.toLowerCase()
      if (!line || seen.has(key)) return false
      seen.add(key)
      return true
    })
}

export function servicesToText(services: string[]): string {
  return services.join('\n')
}

/** "Since 2026" — the site shows the partner-since year only. */
export function partnerSinceLabel(date: string | null): string {
  return date ? `Since ${date.slice(0, 4)}` : 'No start date'
}

/** A logo/symbol must be a site path ("/logos/x.svg") — mirrors the API rule. */
export function isSitePath(value: string): boolean {
  return value === '' || (value.startsWith('/') && !value.startsWith('//'))
}
