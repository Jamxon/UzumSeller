<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductMapping extends Model
{
    protected $fillable = [
        'user_id',
        'uzum_sku_id',
        'yandex_offer_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
