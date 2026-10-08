<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItemBase extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'is_sellable' => 'boolean',
        'json_specifications' => 'array',
    ];

    /**
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * @return BelongsTo<ItemBase, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ItemBase::class, 'parent_id');
    }

    /**
     * @return HasMany<ItemBase, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(ItemBase::class, 'parent_id');
    }

    /**
     * @return HasMany<ItemVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ItemVariant::class);
    }

    /**
     * @return BelongsToMany<ItemBase, $this>
     */
    public function describedBy(): BelongsToMany
    {
        return $this->belongsToMany(ItemBase::class, 'item_item', 'primary_item_id', 'secondary_item_id')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<ItemBase, $this>
     */
    public function describes(): BelongsToMany
    {
        return $this->belongsToMany(ItemBase::class, 'item_item', 'secondary_item_id', 'primary_item_id')
            ->withTimestamps();
    }
}
