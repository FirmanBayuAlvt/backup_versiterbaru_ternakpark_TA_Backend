<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeedPurchase extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terkait dengan model ini.
     *
     * @var string
     */
    protected $table = 'feed_purchases';

    /**
     * Kolom yang dapat diisi secara massal (mass assignable).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'date',
        'supplier',
        'feed_name',
        'price_per_unit',
        'quantity',
        'unit',
        'notes',
    ];

    /**
     * Tipe data casting untuk kolom tertentu.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date'            => 'date',          // otomatis menjadi Carbon instance
        'price_per_unit'  => 'decimal:2',    // 2 angka di belakang koma
        'quantity'        => 'decimal:2',
    ];

    /**
     * Attribute default value untuk kolom 'unit'.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'unit' => 'kg',
    ];

    /**
     * Scope untuk filter berdasarkan jangka waktu tanggal.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string|null  $startDate
     * @param  string|null  $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDateBetween($query, $startDate = null, $endDate = null)
    {
        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('date', '<=', $endDate);
        }
        return $query;
    }

    /**
     * Scope untuk filter berdasarkan nama pakan.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string|null  $feedName
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByFeedName($query, $feedName = null)
    {
        if ($feedName) {
            $query->where('feed_name', 'like', '%' . $feedName . '%');
        }
        return $query;
    }

    /**
     * Hitung total harga dari pengadaan (quantity * price_per_unit).
     *
     * @return float
     */
    public function getTotalPriceAttribute(): float
    {
        return round($this->quantity * ($this->price_per_unit ?? 0), 2);
    }
}