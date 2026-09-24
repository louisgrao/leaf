<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemVariant extends Model
{
    protected $guarded = [];

    protected $casts = [
        'status' => 'boolean',
        'price' => 'decimal:2',
        'json_attributes' => 'array',
    ];

    public function itemBase()
    {
        return $this->belongsTo(ItemBase::class);
    }
}