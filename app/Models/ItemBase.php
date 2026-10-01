<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItemBase extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'status' => 'boolean',
        'is_sellable' => 'boolean',
        'json_specifications' => 'array',
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