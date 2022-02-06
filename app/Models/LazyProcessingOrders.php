<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LazyProcessingOrders extends Model
{
    use HasFactory;

    public function grid()
    {
        return $this->belongsTo(Grid::class);
    }
}
