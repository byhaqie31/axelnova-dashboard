<?php

namespace App\Services\Blog;

use App\Models\BlogPost;

/**
 * The categories and topics (tags) in use — derived from the posts themselves,
 * there is no taxonomy table. A name exists while at least one non-deleted post
 * (drafts included) carries it. Names are compared case-insensitively; the
 * oldest post's spelling wins, so "cloudflare" never becomes a second topic
 * beside "Cloudflare".
 *
 * inUse() feeds the admin editor's pickers and the connector guide; snap() is
 * the connector's write-side guard (the editor's picker already offers the
 * existing spelling, and snapping there would stop the founder re-casing a name).
 */
class BlogTaxonomy
{
    /** @return array{categories: list<string>, tags: list<string>} */
    public static function inUse(?int $ignoreId = null): array
    {
        $posts = BlogPost::query()
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->orderBy('id')
            ->get(['category', 'tags']);

        return [
            'categories' => self::unique($posts->pluck('category')->all()),
            'tags' => self::unique($posts->pluck('tags')->flatten()->all()),
        ];
    }

    /**
     * Rewrite `category` / `tags` (only the keys present) to the spelling other
     * posts already use. `$ignoreId` is the post being saved — its own names
     * don't count, so a re-cased name that only it carries sticks.
     */
    public static function snap(array $data, ?int $ignoreId = null): array
    {
        if (! array_key_exists('category', $data) && ! array_key_exists('tags', $data)) {
            return $data;
        }

        $inUse = self::inUse($ignoreId);
        $categories = self::index($inUse['categories']);
        $tags = self::index($inUse['tags']);
        $match = fn (array $known, $name) => is_string($name) ? ($known[mb_strtolower(trim($name))] ?? $name) : $name;

        if (is_string($data['category'] ?? null)) {
            $data['category'] = $match($categories, $data['category']);
        }
        if (is_array($data['tags'] ?? null)) {
            $data['tags'] = array_map(fn ($tag) => $match($tags, $tag), $data['tags']);
        }

        return $data;
    }

    /**
     * Trim, drop blanks, de-duplicate case-insensitively keeping the first
     * spelling seen. Sorted when `$sort` (the pickers), kept in order otherwise
     * (a post's own tag list).
     *
     * @return list<string>
     */
    public static function unique(array $names, bool $sort = true): array
    {
        $out = array_values(self::index($names));

        if ($sort) {
            natcasesort($out);
        }

        return array_values($out);
    }

    /** @return array<string, string> lower-cased name → first spelling */
    private static function index(array $names): array
    {
        $index = [];
        foreach ($names as $name) {
            $name = is_string($name) ? trim($name) : '';
            if ($name !== '') {
                $index[mb_strtolower($name)] ??= $name;
            }
        }

        return $index;
    }
}
