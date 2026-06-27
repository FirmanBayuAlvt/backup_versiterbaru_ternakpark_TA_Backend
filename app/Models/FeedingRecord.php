<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedingRecord extends Model
{
    use HasFactory;
    protected $fillable = ['feed_id', 'livestock_id', 'pen_id', 'quantity_kg', 'feeding_date', 'notes'];
    protected $casts = ['quantity_kg' => 'decimal:2', 'feeding_date' => 'date'];

    public function feed(): BelongsTo { return $this->belongsTo(Feed::class); }
    public function livestock(): BelongsTo { return $this->belongsTo(Livestock::class); }
    public function pen(): BelongsTo { return $this->belongsTo(Pen::class); }
}