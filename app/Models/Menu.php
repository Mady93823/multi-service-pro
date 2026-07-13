<?php

namespace App\Models;

use App\Domain\Cms\Enums\MenuLocation;
use Database\Factories\MenuFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A navigation menu bound to one storefront location (M19).
 *
 * @property MenuLocation $location
 * @property string $name
 */
class Menu extends Model
{
    /** @use HasFactory<MenuFactory> */
    use HasFactory;

    protected $fillable = ['location', 'name'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['location' => MenuLocation::class];
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Top-level items only; children hang off each item.
     *
     * @return HasMany<MenuItem, $this>
     */
    public function rootItems(): HasMany
    {
        return $this->items()->whereNull('parent_id');
    }
}
