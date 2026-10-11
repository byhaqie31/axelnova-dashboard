<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

/**
 * A studio partner on /collaborations (see docs/global/COLLABORATIONS.md) —
 * NOT a referral partner (`referral_partners`, the /partners commission
 * programme). Projects built together hang off the `collaboration_partner_project`
 * link, whose `mode` says how the studios worked and `credit_public` whether the
 * site may name the partner on that project.
 */
class CollaborationPartner extends Model
{
    use HasFactory, SoftDeletes;

    /** How a shared project was delivered. */
    public const MODES = ['co_build', 'subcontract', 'referral'];

    protected $fillable = [
        'slug',
        'name',
        'tagline',
        'summary',
        'story',
        'logo_url',
        'symbol_url',
        'website_url',
        'founder_name',
        'founder_role',
        'location',
        'services',
        'partner_since',
        'active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'services' => 'array',
            'partner_since' => 'date',
            'active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'collaboration_partner_project')
            ->withPivot(['mode', 'role_note', 'credit_public'])
            ->withTimestamps();
    }

    /** Shared projects the public site may show: credited, and the project itself live on the site. */
    public function publicProjects(): BelongsToMany
    {
        return $this->projects()
            ->wherePivot('credit_public', true)
            ->where('projects.active', true);
    }

    /**
     * Link a shared project. Credit defaults to public ONLY for co-builds —
     * subcontracted and referred work is often confidential to the end client,
     * so naming the partner on it is an explicit opt-in.
     */
    public function linkProject(Project $project, string $mode, ?string $roleNote = null, ?bool $creditPublic = null): void
    {
        if (! in_array($mode, self::MODES, true)) {
            throw new InvalidArgumentException("Unknown collaboration mode [{$mode}].");
        }

        $this->projects()->syncWithoutDetaching([
            $project->id => [
                'mode' => $mode,
                'role_note' => $roleNote,
                'credit_public' => $creditPublic ?? $mode === 'co_build',
            ],
        ]);
    }
}
