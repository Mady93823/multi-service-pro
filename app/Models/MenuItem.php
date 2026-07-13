<?php

namespace App\Models;

use App\Domain\Cms\Enums\MenuItemType;
use App\Domain\Cms\Enums\MenuVisibility;
use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One link in a menu. `target` means whatever `type` says it means: a route
 * name, a page slug, or a URL — resolving it is `SiteMenus`' job, never the
 * model's (an unresolvable item must be dropped, not crash the storefront).
 *
 * @property MenuItemType $type
 * @property MenuVisibility $visibility
 * @property string $label
 * @property ?string $target
 * @property int $sort_order
 * @property bool $is_active
 */
class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory;

    protected $fillable = [
        'menu_id',
        'parent_id',
        'label',
        'type',
        'target',
        'visibility',
        'sort_order',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MenuItemType::class,
            'visibility' => MenuVisibility::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @param  Builder<MenuItem>  $query
     * @return Builder<MenuItem>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
