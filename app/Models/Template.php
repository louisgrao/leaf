<?php

namespace App\Models;

use Database\Factories\TemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Template extends Model
{
    /** @use HasFactory<TemplateFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'schema' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * @return HasMany<ItemBase, $this>
     */
    public function itemBases(): HasMany
    {
        return $this->hasMany(ItemBase::class);
    }
}
