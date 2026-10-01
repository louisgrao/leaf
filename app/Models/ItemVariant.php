<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItemVariant extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'status' => 'boolean',
        'json_specifications' => 'array',
        'json_options' => 'array',
        'shipping_dimensions' => 'array',
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'shipping_weight' => 'decimal:2',
    ];

    public function itemBase()
    {
        return $this->belongsTo(ItemBase::class);
    }
}