<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseVariant extends Model
{
    protected $fillable = [
        'warehouse_id',
        'shopify_variant_id',
        'variant_name',
        'stock',
        'sku',
        'shopify_product_ksu',
        'cost_price',
        'selling_price',
        'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
    ];
}
