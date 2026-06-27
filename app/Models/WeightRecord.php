<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeightRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'livestock_id',
        'weight_kg',
        'record_date',
        'notes'
    ];

    protected $casts = [
        'weight_kg' => 'decimal:2',
        'record_date' => 'date',
    ];

    public function livestock(): BelongsTo
    {
        return $this->belongsTo(Livestock::class);
    }
}