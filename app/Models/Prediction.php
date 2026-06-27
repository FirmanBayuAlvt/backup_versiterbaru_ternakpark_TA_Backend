<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prediction extends Model
{
    use HasFactory;
    protected $fillable = ['livestock_id', 'prediction_days', 'predicted_gain', 'confidence', 'interval_lower', 'interval_upper', 'recommendations', 'input_features'];
    protected $casts = [
        'predicted_gain' => 'decimal:3',
        'confidence' => 'decimal:2',
        'interval_lower' => 'decimal:3',
        'interval_upper' => 'decimal:3',
        'recommendations' => 'array',
        'input_features' => 'array',
    ];

    public function livestock(): BelongsTo { return $this->belongsTo(Livestock::class); }
}
