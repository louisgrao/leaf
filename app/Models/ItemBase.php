<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemBase extends Model
{
    protected $guarded = [];

    protected $casts = [
        'base_status' => 'boolean',
        'base_price' => 'decimal:2',
    ];

    public function parent()
    {
        return $this->belongsTo(ItemBase::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(ItemBase::class, 'parent_id');
    }

    public function variants()
    {
        return $this->hasMany(ItemVariant::class);
    }

    public function describedBy()
    {
        return $this->belongsToMany(ItemBase::class, 'item_item', 'primary_item_id', 'secondary_item_id')
            ->withTimestamps();
    }

    public function describes()
    {
        return $this->belongsToMany(ItemBase::class, 'item_item', 'secondary_item_id', 'primary_item_id')
            ->withTimestamps();
    }
}