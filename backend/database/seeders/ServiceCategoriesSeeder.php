<?php

namespace Database\Seeders;

use App\Models\ServiceCategory;
use App\Models\ServicePackage;
use Illuminate\Database\Seeder;

class ServiceCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $data = $this->categories();

        foreach ($data as $catIndex => $cat) {
            $category = ServiceCategory::updateOrCreate(
                ['slug' => $cat['slug']],
                [
                    'name' => $cat['name'],
                    'icon' => $cat['icon'],
                    'description' => $cat['description'],
                    'sort_order' => $catIndex,
                    'active' => true,
                ],
            );

            foreach ($cat['packages'] as $pkgIndex => $pkg) {
                ServicePackage::updateOrCreate(
                    ['service_category_id' => $category->id, 'slug' => $pkg['slug']],
                    [
                        'name' => $pkg['name'],
                        'tagline' => $pkg['tagline'],
                        'price_min_myr' => $pkg['price_min_myr'],
                        'price_max_myr' => $pkg['price_max_myr'],
                        'unit' => $pkg['unit'],
                        'duration_text' => $pkg['duration_text'],
                        'eta_value' => $pkg['eta_value'] ?? 4,
                        'eta_unit' => $pkg['eta_unit'] ?? 'week',
                        'revisions' => $pkg['revisions'],
                        'featured' => $pkg['featured'],
                        'features' => $pkg['features'],
                        'cta' => $pkg['cta'],
                        'quote_key' => $pkg['quote_key'] ?? null,
                        'sort_order' => $pkgIndex,
                        'active' => true,
                    ],
                );
            }
        }

        $this->command->info('Seeded '.count($data).' service categories.');
    }

    /**
     * Mirrors frontend/app/data/services.ts. Keep in sync until the public site
     * reads from this DB instead of the static TS file.
     */
    private function categories(): array
    {
        return [
            [
                'slug' => 'web', 'name' => 'Web Presence', 'icon' => 'i-lucide-globe',
                'description' => 'From a single landing page to a full multi-page website. Clean, fast, and built to convert — for SMEs, hotels, clinics, and anyone who needs to look serious online.',
                'packages' => [
                    ['slug' => 'web-essential', 'name' => 'Essential', 'tagline' => 'Get online, fast.', 'price_min_myr' => 2000, 'price_max_myr' => 3000, 'unit' => 'per project', 'duration_text' => '5 days', 'eta_value' => 5, 'eta_unit' => 'day', 'revisions' => '1 round', 'featured' => false, 'features' => ['Single-page, mobile-first layout', 'WhatsApp + contact form integration', 'Basic on-page SEO setup', 'Google Analytics', '5-day delivery'], 'cta' => 'Get a quote', 'quote_key' => ['category' => 'web', 'package' => 'web_essential']],
                    ['slug' => 'web-business', 'name' => 'Business', 'tagline' => 'A proper web presence.', 'price_min_myr' => 3500, 'price_max_myr' => 5500, 'unit' => 'per project', 'duration_text' => '2 weeks', 'eta_value' => 2, 'eta_unit' => 'week', 'revisions' => '2 rounds', 'featured' => true, 'features' => ['4–6 pages, custom design', 'CMS-ready (Nuxt Content)', 'Booking / enquiry flow', 'Performance optimisation', 'SEO + sitemap + Open Graph', '2-week delivery'], 'cta' => 'Get a quote', 'quote_key' => ['category' => 'web', 'package' => 'web_business']],
                    ['slug' => 'web-premium', 'name' => 'Premium', 'tagline' => 'Built to impress internationally.', 'price_min_myr' => 6500, 'price_max_myr' => 11000, 'unit' => 'per project', 'duration_text' => '3–4 weeks', 'eta_value' => 4, 'eta_unit' => 'week', 'revisions' => '3 rounds', 'featured' => false, 'features' => ['8+ pages, full custom UI/UX', 'Multilingual support (EN / BM)', 'Micro-animations & scroll reveals', 'CMS + analytics dashboard', 'Lighthouse score target: 90+', 'Deploy + domain setup'], 'cta' => "Let's talk", 'quote_key' => ['category' => 'web', 'package' => 'web_premium']],
                ],
            ],
            [
                'slug' => 'dashboard', 'name' => 'Dashboard & Portal', 'icon' => 'i-lucide-layout-dashboard',
                'description' => 'Fintech-grade admin systems, client portals, and data-dense interfaces — built for clarity under complexity.',
                'packages' => [
                    ['slug' => 'dash-starter', 'name' => 'Starter Dashboard', 'tagline' => 'Core UI, shipped clean.', 'price_min_myr' => 4500, 'price_max_myr' => 7000, 'unit' => 'per project', 'duration_text' => '3 weeks', 'eta_value' => 3, 'eta_unit' => 'week', 'revisions' => '2 rounds', 'featured' => false, 'features' => ['3–5 modules (CRUD UI)', 'Auth screens (login, reset)', 'Responsive layout', 'Figma → Vue/Nuxt build', 'Basic charts & data tables'], 'cta' => 'Get a quote', 'quote_key' => ['category' => 'dashboard', 'package' => 'dash_starter']],
                    ['slug' => 'dash-business', 'name' => 'Business Dashboard', 'tagline' => 'Role-based, data-rich.', 'price_min_myr' => 8500, 'price_max_myr' => 13000, 'unit' => 'per project', 'duration_text' => '5–6 weeks', 'eta_value' => 5, 'eta_unit' => 'week', 'revisions' => '3 rounds', 'featured' => true, 'features' => ['6–10 modules', 'Role-based UI (admin / staff)', 'Advanced charts, sortable tables', 'REST / GraphQL API integration', 'Dark mode + responsive', 'Component documentation'], 'cta' => 'Get a quote', 'quote_key' => ['category' => 'dashboard', 'package' => 'dash_business']],
                    ['slug' => 'dash-enterprise', 'name' => 'Enterprise Portal', 'tagline' => 'Multi-role. Mission-critical.', 'price_min_myr' => 16000, 'price_max_myr' => null, 'unit' => 'custom quote', 'duration_text' => 'Scoped on call', 'eta_value' => 10, 'eta_unit' => 'week', 'revisions' => 'Unlimited', 'featured' => false, 'features' => ['Admin + client portal (multi-tenant)', 'Complex workflows & approvals', 'Real-time updates (WebSocket)', 'Full design system delivered', 'Technical handover documentation', 'Post-launch support window'], 'cta' => "Let's scope it", 'quote_key' => ['category' => 'dashboard', 'package' => 'dash_enterprise']],
                ],
            ],
            [
                'slug' => 'design-frontend', 'name' => 'Design & Frontend', 'icon' => 'i-lucide-pen-tool',
                'description' => 'From UX audits and Figma design to pixel-perfect Vue/Nuxt implementation. Whether you need design, code, or both — delivered as one cohesive workflow.',
                'packages' => [
                    ['slug' => 'df-audit', 'name' => 'UX Audit', 'tagline' => 'Find what’s broken, fix it fast.', 'price_min_myr' => 1000, 'price_max_myr' => 1800, 'unit' => 'per engagement', 'duration_text' => '1 week', 'eta_value' => 1, 'eta_unit' => 'week', 'revisions' => '—', 'featured' => false, 'features' => ['Heuristic review (10 principles)', 'Prioritised findings report', 'Annotated screen recordings', 'Actionable improvement list', 'One debrief call included'], 'cta' => 'Get a quote', 'quote_key' => ['category' => 'design', 'package' => 'design_audit']],
                    ['slug' => 'df-build', 'name' => 'Design + Build', 'tagline' => 'Figma design, then clean code.', 'price_min_myr' => 4000, 'price_max_myr' => 8000, 'unit' => 'per project', 'duration_text' => '3–4 weeks', 'eta_value' => 3, 'eta_unit' => 'week', 'revisions' => '2 rounds', 'featured' => true, 'features' => ['Up to 10 screens in Figma', 'Design system basics (tokens + components)', 'Responsive prototype', 'Vue/Nuxt implementation', 'Up to 15 components, Tailwind', 'API-ready frontend'], 'cta' => 'Get a quote', 'quote_key' => ['category' => 'design', 'package' => 'design_figma']],
                    ['slug' => 'df-system', 'name' => 'Full Design System', 'tagline' => 'Scale without losing consistency.', 'price_min_myr' => 10000, 'price_max_myr' => 18000, 'unit' => 'per project', 'duration_text' => '5–7 weeks', 'eta_value' => 6, 'eta_unit' => 'week', 'revisions' => '3 rounds', 'featured' => false, 'features' => ['20+ screens, full user flows', 'Complete design system (Figma)', 'Storybook component library', 'State management (Pinia)', 'Full frontend implementation', 'Design handoff documentation'], 'cta' => "Let's talk scope", 'quote_key' => ['category' => 'design', 'package' => 'design_full']],
                ],
            ],
            [
                'slug' => 'saas', 'name' => 'SaaS & Product', 'icon' => 'i-lucide-rocket',
                'description' => 'End-to-end product builds for founders and small teams — from landing page to paying users. Shipped fast, built to last.',
                'packages' => [
                    ['slug' => 'saas-sprint', 'name' => 'MVP Sprint', 'tagline' => 'Validate in weeks, not months.', 'price_min_myr' => 12000, 'price_max_myr' => 20000, 'unit' => 'per project', 'duration_text' => '6–8 weeks', 'eta_value' => 6, 'eta_unit' => 'week', 'revisions' => '2 rounds', 'featured' => false, 'features' => ['Landing page + auth flow', '1 core product feature', 'Admin panel (basic)', 'Nuxt + Laravel / Supabase API', 'Deploy to VPS + CI setup', 'Founder walkthrough session'], 'cta' => "Let's build", 'quote_key' => ['category' => 'saas', 'package' => 'saas_mvp_sprint']],
                    ['slug' => 'saas-full', 'name' => 'Full MVP', 'tagline' => 'Launch-ready. Investor-ready.', 'price_min_myr' => 25000, 'price_max_myr' => 40000, 'unit' => 'per project', 'duration_text' => '10–14 weeks', 'eta_value' => 10, 'eta_unit' => 'week', 'revisions' => '3 rounds', 'featured' => true, 'features' => ['Multi-feature SaaS product', 'Stripe / billing integration', 'Admin + client portals', 'Onboarding flow', 'Analytics + error tracking', 'Full technical documentation'], 'cta' => "Let's scope it", 'quote_key' => ['category' => 'saas', 'package' => 'saas_full_mvp']],
                    ['slug' => 'saas-custom', 'name' => 'Custom Scope', 'tagline' => 'Complex domain? Let’s talk.', 'price_min_myr' => 40000, 'price_max_myr' => null, 'unit' => 'custom quote', 'duration_text' => 'Scoped on call', 'eta_value' => 16, 'eta_unit' => 'week', 'revisions' => 'Unlimited', 'featured' => false, 'features' => ['Large-scale product architecture', 'Multi-team / multi-tenant systems', 'Custom integrations & APIs', 'Performance + security review', 'Ongoing embedded support', 'Flexible milestone-based billing'], 'cta' => 'Book a call', 'quote_key' => null],
                ],
            ],
            [
                'slug' => 'retainer', 'name' => 'Retainers', 'icon' => 'i-lucide-refresh-cw',
                'description' => 'Monthly design and development capacity — without the overhead of a full-time hire. Ideal for growing products that need consistent momentum.',
                'packages' => [
                    ['slug' => 'ret-maintenance', 'name' => 'Maintenance', 'tagline' => 'Keep it running, always.', 'price_min_myr' => 1800, 'price_max_myr' => null, 'unit' => 'per month', 'duration_text' => 'Up to 8 hrs / mo', 'eta_value' => 1, 'eta_unit' => 'month', 'revisions' => '—', 'featured' => false, 'features' => ['Bug fixes & content updates', 'Uptime + performance monitoring', 'Monthly status report', 'Priority response (next business day)', 'Rolling monthly contract'], 'cta' => 'Get started', 'quote_key' => null],
                    ['slug' => 'ret-growth', 'name' => 'Growth', 'tagline' => 'Steady progress, every month.', 'price_min_myr' => 3500, 'price_max_myr' => null, 'unit' => 'per month', 'duration_text' => 'Up to 20 hrs / mo', 'eta_value' => 1, 'eta_unit' => 'month', 'revisions' => '—', 'featured' => true, 'features' => ['Feature additions & UI iterations', 'Performance tuning', 'Weekly async updates', 'Figma design included', 'Unused hours roll forward (1 mo)', 'Rolling monthly contract'], 'cta' => 'Get started', 'quote_key' => null],
                    ['slug' => 'ret-partner', 'name' => 'Embedded Partner', 'tagline' => 'Like a senior hire, without the contract.', 'price_min_myr' => 6500, 'price_max_myr' => 8500, 'unit' => 'per month', 'duration_text' => 'Up to 50 hrs / mo', 'eta_value' => 1, 'eta_unit' => 'month', 'revisions' => '—', 'featured' => false, 'features' => ['Dedicated design + dev capacity', 'Weekly sync calls', 'Full product ownership possible', 'Architecture & code review', 'Priority availability', '3-month minimum engagement'], 'cta' => 'Book a call', 'quote_key' => null],
                ],
            ],
        ];
    }
}
