<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ItemItem extends Pivot
{
    protected $table = 'item_item';
    protected $guarded = [];
}