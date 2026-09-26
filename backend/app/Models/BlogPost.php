<?php

namespace App\Models;

use App\Support\BlogMarkdown;
use App\Support\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A blog article (see docs/global/BLOG.md). `sections` is the ordered list of
 * {id, heading, body_md, image_url, image_alt, quote, quote_by}; Markdown is
 * the storage format and BlogMarkdown renders it to safe HTML on read
 * (renderedSections()). `status` is the public gate — only `published` rows
 * with a `published_at` are served; drafts may be incomplete. Slugs are unique
 * across ALL rows including soft-deleted ones (the DB index is absolute).
 */
class BlogPost extends Model
{
    use HasFactory, RecordsActivity, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'slug',
        'title',
        'excerpt',
        'sections',
        'cover_image_url',
        'cover_image_alt',
        'category',
        'tags',
        'cta_heading',
        'cta_body',
        'cta_label',
        'cta_url',
        'seo_title',
        'seo_description',
        'reading_minutes',
        'status',
        'published_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sections' => 'array',
        'tags' => 'array',
        'reading_minutes' => 'integer',
        'published_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'tags' => '[]',
        'sections' => '[]',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)->whereNotNull('published_at');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * A slug from a title (or a hand-typed slug), unique across ALL rows —
     * soft-deleted ones included, since the unique index counts them. `$ignoreId`
     * lets an existing post keep its own slug on update.
     */
    public static function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $root = Str::limit(Str::slug($base), 110, '') ?: 'post';
        $candidate = $root;

        for ($n = 2; static::withTrashed()->where('slug', $candidate)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists(); $n++) {
            $candidate = "{$root}-{$n}";
        }

        return $candidate;
    }

    /** Trim strings, null the empties, and give every section a stable id. */
    public static function normaliseSections(array $raw): array
    {
        $clean = fn ($v) => is_string($v) && trim($v) !== '' ? trim($v) : null;

        return array_values(array_map(fn (array $s) => [
            'id' => is_string($s['id'] ?? null) && preg_match('/^s_[a-z0-9]{6}$/', $s['id'])
                ? $s['id']
                : 's_'.Str::lower(Str::random(6)),
            'heading' => $clean($s['heading'] ?? null) ?? '',
            'body_md' => is_string($s['body_md'] ?? null) ? trim($s['body_md']) : '',
            'image_url' => $clean($s['image_url'] ?? null),
            'image_alt' => $clean($s['image_alt'] ?? null),
            'quote' => $clean($s['quote'] ?? null),
            'quote_by' => $clean($s['quote_by'] ?? null),
        ], $raw));
    }

    /**
     * Table-of-contents anchors from the section headings, de-duplicated within
     * the post so two identical headings never point at the same element.
     *
     * @return array<int, array{id: string, heading: string}>
     */
    public function toc(): array
    {
        $seen = [];
        $out = [];

        foreach ($this->sections ?? [] as $section) {
            $root = Str::slug($section['heading'] ?? '') ?: 'section';
            $id = $root;
            for ($n = 2; isset($seen[$id]); $n++) {
                $id = "{$root}-{$n}";
            }
            $seen[$id] = true;
            $out[] = ['id' => $id, 'heading' => $section['heading'] ?? ''];
        }

        return $out;
    }

    /** Sections with `body_html` rendered and their TOC anchor attached as `anchor`. */
    public function renderedSections(): array
    {
        $toc = $this->toc();
        $sections = array_values($this->sections ?? []);

        return array_map(fn (array $section, int $i) => [
            ...$section,
            'anchor' => $toc[$i]['id'],
            'body_html' => BlogMarkdown::toHtml($section['body_md'] ?? ''),
        ], $sections, array_keys($sections));
    }
}
