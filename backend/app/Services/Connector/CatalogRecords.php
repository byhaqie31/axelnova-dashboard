<?php

namespace App\Services\Connector;

use App\Http\Resources\ProjectResource;
use App\Http\Resources\ServicePackageResource;
use App\Models\Project;
use App\Models\ServiceCategory;
use App\Models\ServicePackage;

/**
 * How the MCP connector presents the public catalogue — projects, services
 * (service_categories) and packages — to Claude (connector v6). Every record
 * says its `visibility` (live | hidden: whether the public site shows it), an
 * `admin_url` for the founder, and a `public_url` only while it is Live. A
 * package's `visibility` is its OWN flag; its `effective_visibility` folds in its
 * service's (ServicePackage::isEffectivelyLive) and is what public_url, quotable
 * and the connector's edit guard follow.
 */
final class CatalogRecords
{
    public static function visibility(bool $active): string
    {
        return $active ? 'live' : 'hidden';
    }

    /** A cockpit link — the admin origin (FRONTEND_URL). */
    public static function adminUrl(string $path): string
    {
        return rtrim((string) config('services.frontend.url'), '/').$path;
    }

    /** A public-site link — the public origin (PUBLIC_SITE_URL), never the admin host. */
    public static function publicUrl(string $path): string
    {
        return rtrim((string) config('services.frontend.public_url'), '/').$path;
    }

    // ── Projects ────────────────────────────────────────────────────────────

    public static function projectRow(Project $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'slug' => $project->slug,
            'status' => $project->status,
            'visibility' => self::visibility($project->active),
            'featured' => $project->featured,
            'description' => $project->description,
            'tags' => $project->tags ?? [],
            'updated_at' => $project->updated_at?->toISOString(),
            ...self::projectUrls($project),
        ];
    }

    public static function project(Project $project): array
    {
        return [
            ...(new ProjectResource($project))->resolve(),
            'visibility' => self::visibility($project->active),
            ...self::projectUrls($project),
        ];
    }

    /** @return array{admin_url: string, public_url: string|null} */
    private static function projectUrls(Project $project): array
    {
        return [
            'admin_url' => self::adminUrl("/admin/projects/{$project->id}"),
            'public_url' => $project->active ? self::publicUrl("/projects/{$project->slug}") : null,
        ];
    }

    // ── Services (categories) ───────────────────────────────────────────────

    /** A service with its packages — slim package rows for the list, full records for a read-back. */
    public static function service(ServiceCategory $service, bool $fullPackages = false): array
    {
        $packages = $service->packages()->orderBy('sort_order')->get()
            ->each(fn (ServicePackage $package) => $package->setRelation('category', $service));

        return [
            'id' => $service->id,
            'slug' => $service->slug,
            'name' => $service->name,
            'icon' => $service->icon,
            'description' => $service->description,
            'visibility' => self::visibility($service->active),
            'is_default' => (bool) $service->is_default,
            'sort_order' => $service->sort_order,
            'updated_at' => $service->updated_at?->toISOString(),
            'admin_url' => self::adminUrl("/admin/services/categories/{$service->id}"),
            'public_url' => $service->active ? self::publicUrl('/services') : null,
            'packages' => $packages
                ->map(fn (ServicePackage $package) => $fullPackages ? self::package($package) : self::packageRow($package))
                ->all(),
        ];
    }

    // ── Packages ────────────────────────────────────────────────────────────

    public static function packageRow(ServicePackage $package): array
    {
        return [
            'id' => $package->id,
            'slug' => $package->slug,
            'name' => $package->name,
            'tagline' => $package->tagline,
            'price_min_myr' => $package->price_min_myr,
            'price_max_myr' => $package->price_max_myr,
            'unit' => $package->unit,
            'visibility' => self::visibility($package->active),
            'effective_visibility' => $package->effective_visibility,
            'featured' => $package->featured,
            ...self::quoteState($package),
            'admin_url' => self::adminUrl("/admin/services/packages/{$package->id}"),
        ];
    }

    public static function package(ServicePackage $package): array
    {
        $service = $package->category;
        $record = (new ServicePackageResource($package))->resolve();
        unset($record['category'], $record['service_category_id'], $record['quote_key']);

        return [
            ...$record,
            'service' => [
                'id' => $service->id,
                'slug' => $service->slug,
                'name' => $service->name,
                'visibility' => self::visibility($service->active),
            ],
            'visibility' => self::visibility($package->active),
            'effective_visibility' => $package->effective_visibility,
            ...self::quoteState($package),
            'admin_url' => self::adminUrl("/admin/services/packages/{$package->id}"),
            'public_url' => $package->isEffectivelyLive() ? self::publicUrl('/services') : null,
        ];
    }

    /**
     * The package's quote-builder wiring: its quote package key (null = a plain
     * card, e.g. a custom-quote or retainer row) and whether it is quotable RIGHT
     * NOW — wired, with a max price, and effectively Live (both it and its service
     * Live, so it appears in list_catalog). A wired Hidden package becomes
     * quotable once the founder makes it — and its service — Live.
     *
     * @return array{quote_package_key: string|null, quotable: bool}
     */
    public static function quoteState(ServicePackage $package): array
    {
        $key = $package->quote_key['package'] ?? null;

        return [
            'quote_package_key' => $key,
            'quotable' => $key !== null && $package->price_max_myr !== null && $package->isEffectivelyLive(),
        ];
    }
}
