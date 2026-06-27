<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Feed extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'category', 'current_stock', 'price_per_kg', 'unit', 'is_active', 'pk', 'lk', 'sk', 'abu', 'tdn', 'ndf'];
    protected $casts = ['current_stock' => 'decimal:2', 'price_per_kg' => 'decimal:2', 'is_active' => 'boolean', 'pk' => 'decimal:2', 'lk' => 'decimal:2', 'sk' => 'decimal:2', 'abu' => 'decimal:2', 'tdn' => 'decimal:2', 'ndf' => 'decimal:2'];
    protected $appends = ['is_stock_low'];

    public function feedingRecords(): HasMany { return $this->hasMany(FeedingRecord::class); }

    public function getIsStockLowAttribute(): bool
    {
        return $this->current_stock < 100; // threshold
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
