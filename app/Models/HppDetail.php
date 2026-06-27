<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HppDetail extends Model
{
    use HasFactory;

    protected $table = 'hpp_details';

    protected $fillable = [
        'livestock_id',
        'purchase_cost',
        'feed_cost',
        'operational_cost',
    ];

    protected $casts = [
        'purchase_cost' => 'decimal:2',
        'feed_cost' => 'decimal:2',
        'operational_cost' => 'decimal:2',
    ];

    public function livestock(): BelongsTo
    {
        return $this->belongsTo(Livestock::class);
    }
}
