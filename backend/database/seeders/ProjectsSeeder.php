<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

class ProjectsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = $this->projects();

        foreach ($rows as $i => $row) {
            Project::updateOrCreate(
                ['slug' => $row['slug']],
                array_merge($row, ['sort_order' => $i, 'active' => true]),
            );
        }

        $this->command->info('Seeded '.count($rows).' projects.');
    }

    /**
     * Mirrors frontend/app/data/projects.ts. Keep in sync until the public site reads from DB.
     */
    private function projects(): array
    {
        return [
            ['slug' => 'house-of-parfum', 'name' => 'HouseOfParfum', 'description' => 'Perfume e-commerce with 74k+ fragrance catalogue via Fragella API.', 'long_description' => 'Full-stack e-commerce platform. Laravel backend with Redis caching on the Fragella API (74k+ fragrances), Stripe payments, RabbitMQ for async order processing, and a Nuxt 3 storefront with a custom admin dashboard.', 'status' => 'wip', 'url' => null, 'repo' => 'https://github.com/byhaqie31/HouseOfParfum', 'tags' => ['E-commerce', 'Fintech'], 'stack' => ['Laravel', 'Nuxt', 'Redis', 'RabbitMQ', 'MySQL', 'Docker', 'Stripe'], 'featured' => true, 'cover_image_url' => null],
            ['slug' => 'portfolio-maker', 'name' => 'Portfolio Maker', 'description' => 'Subdomain-based portfolio hosting — your own space at username.folio.dev.', 'long_description' => 'SaaS platform where developers and creatives get a personal portfolio at a custom subdomain. Built on Laravel wildcard routing + Nuxt SSR, with a drag-and-drop section builder and custom domain support planned for Phase 2.', 'status' => 'planning', 'url' => null, 'repo' => null, 'tags' => ['SaaS', 'Platform'], 'stack' => ['Laravel', 'Nuxt', 'Docker', 'MySQL'], 'featured' => true, 'cover_image_url' => null],
            ['slug' => 'travel-planner', 'name' => 'Travel Planner', 'description' => 'Smart itinerary builder with maps integration and budget tracking.', 'long_description' => 'AI-assisted travel planner with Google Maps integration, collaborative itineraries, real-time budget tracking, and trip cost alerts. Built for the modern traveller who plans obsessively.', 'status' => 'planning', 'url' => null, 'repo' => null, 'tags' => ['Travel', 'AI'], 'stack' => ['Nuxt', 'Laravel', 'Maps API'], 'featured' => true, 'cover_image_url' => null],
            ['slug' => 'abventures', 'name' => 'abventures.my', 'description' => 'Full business operations platform — client portal, admin panel, and AI invoice pipeline.', 'long_description' => 'Mega project. A complete business ops platform with client-facing and admin portals, event-driven invoice generation via RabbitMQ, AI integration via FastAPI, and a Laravel + Nuxt architecture deployed on AWS.', 'status' => 'wip', 'url' => 'https://abventures.my', 'repo' => null, 'tags' => ['SaaS', 'Business', 'AI'], 'stack' => ['Laravel', 'Nuxt', 'FastAPI', 'RabbitMQ', 'Redis', 'MySQL', 'Docker', 'AWS'], 'featured' => false, 'cover_image_url' => null],
            ['slug' => 'axelnova-dashboard', 'name' => 'Axelnova Dashboard', 'description' => 'This site — personal brand hub, project registry, and client pitch platform.', 'long_description' => 'Personal brand dashboard built with Nuxt 4 + Tailwind CSS v4. Features a filterable project registry, interactive service tier calculator, and private client proposal pages at /proposals/[slug].', 'status' => 'live', 'url' => 'https://axelnova.tech', 'repo' => 'https://github.com/byhaqie31/axelnova-dashboard', 'tags' => ['Portfolio', 'Dashboard'], 'stack' => ['Nuxt 4', 'Tailwind', 'Nuxt UI', 'GSAP'], 'featured' => false, 'cover_image_url' => null],
            ['slug' => 'parfum-api', 'name' => 'Parfum API', 'description' => 'Headless fragrance catalog REST API with token auth and Redis caching.', 'long_description' => 'A standalone REST API serving a curated fragrance catalog. Supports filtering by note, brand, and concentration. Built with token-based auth, rate limiting, and Redis caching for high-throughput read performance.', 'status' => 'planning', 'url' => null, 'repo' => null, 'tags' => ['API', 'Backend'], 'stack' => ['Laravel', 'MySQL', 'Redis'], 'featured' => false, 'cover_image_url' => null],
            ['slug' => 'auto-hub', 'name' => 'Auto Hub', 'description' => 'Peer-to-peer car listing marketplace with search, comparison, and seller verification.', 'long_description' => 'A local car marketplace where private sellers can list vehicles and buyers can search, compare, and inquire. Includes seller verification, price history, and an inquiry chat system.', 'status' => 'planning', 'url' => null, 'repo' => null, 'tags' => ['Marketplace'], 'stack' => ['Nuxt', 'Laravel', 'MySQL', 'Redis'], 'featured' => false, 'cover_image_url' => null],
            ['slug' => 'expense-split', 'name' => 'Expense Split', 'description' => 'Group expense tracker that splits bills and settles debts with minimal transactions.', 'long_description' => 'Track shared expenses across groups, split bills fairly, and calculate the minimum number of transactions to settle all debts. Exports summaries for easy reconciliation.', 'status' => 'planning', 'url' => null, 'repo' => null, 'tags' => ['Fintech', 'Utility'], 'stack' => ['Laravel', 'MySQL', 'Nuxt', 'Docker'], 'featured' => false, 'cover_image_url' => null],
            ['slug' => 'api-vault', 'name' => 'API Vault', 'description' => 'Personal API collection manager — a lightweight Postman alternative.', 'long_description' => 'Store, organise, and test API endpoints with auth headers, environment variable switching, and response logging. Built for developers who want a self-hosted, distraction-free alternative to bloated API clients.', 'status' => 'planning', 'url' => null, 'repo' => null, 'tags' => ['Dev Tool', 'Internal'], 'stack' => ['Nuxt', 'CouchDB', 'Laravel', 'Docker'], 'featured' => false, 'cover_image_url' => null],
            ['slug' => 'prayer-time', 'name' => 'Prayer Time', 'description' => 'Location-based Islamic prayer time app with Azan alerts and Qibla compass.', 'long_description' => 'A PWA that surfaces accurate prayer times based on GPS location, with Azan push notifications, a Qibla compass, and a downloadable monthly timetable. Clean, minimal, distraction-free.', 'status' => 'planning', 'url' => null, 'repo' => null, 'tags' => ['Utility', 'PWA'], 'stack' => ['Nuxt', 'Tailwind', 'REST API'], 'featured' => false, 'cover_image_url' => null],
            ['slug' => 'json-beautifier', 'name' => 'JSON Beautifier', 'description' => 'Browser-based JSON formatter, validator, and diff viewer for developers.', 'long_description' => 'A fast, no-install browser tool for formatting, validating, minifying, and diffing JSON. Includes syntax highlighting, schema validation, and one-click copy. Zero tracking, zero signup.', 'status' => 'planning', 'url' => null, 'repo' => null, 'tags' => ['Dev Tool'], 'stack' => ['Nuxt', 'Tailwind', 'JavaScript'], 'featured' => false, 'cover_image_url' => null],
            ['slug' => 'task-tracker', 'name' => 'Task Tracker', 'description' => 'Kanban-style task board with real-time updates and team assignment.', 'long_description' => 'A focused project task board with kanban columns, deadline alerts, team member assignment, progress tracking, and real-time status updates via Laravel websockets.', 'status' => 'planning', 'url' => null, 'repo' => null, 'tags' => ['Productivity', 'Project Tool'], 'stack' => ['Nuxt', 'Laravel', 'Redis', 'Docker'], 'featured' => false, 'cover_image_url' => null],
            ['slug' => 'my-timetable', 'name' => 'My Timetable', 'description' => 'Drag-and-drop weekly schedule planner with recurring events and conflict detection.', 'long_description' => 'A personal weekly planner with drag-and-drop time blocks, recurring event support, conflict detection, and exportable calendar views. Designed for students and professionals managing complex schedules.', 'status' => 'planning', 'url' => null, 'repo' => null, 'tags' => ['Productivity'], 'stack' => ['Nuxt', 'Tailwind', 'Laravel', 'MySQL'], 'featured' => false, 'cover_image_url' => null],
            ['slug' => 'trip-wire', 'name' => 'Trip Wire', 'description' => 'Travel budget monitor — alerts you when trip costs hit your set thresholds.', 'long_description' => 'Set budget thresholds for trips and get notified when flight prices, hotel rates, or overall trip costs breach your limits. Pulls live pricing data and sends alerts via email or push notification.', 'status' => 'planning', 'url' => null, 'repo' => null, 'tags' => ['Travel', 'Utility'], 'stack' => ['Nuxt', 'Laravel', 'Redis'], 'featured' => false, 'cover_image_url' => null],
        ];
    }
}
