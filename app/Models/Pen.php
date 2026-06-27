<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pen extends Model
{
    use HasFactory;

    /**
     * Daftar kategori kandang yang tersedia.
     *
     * @var array<int, string>
     */
    public const CATEGORIES = [
        'Fattening',
        'Fattening Percobaan',
        'Kawin',
        'Melahirkan',
        'Menyusui',
        'Prasapih',
        'Karantina',
        'Lapak',
        'Persiapan Breeding',
        'Kambing',
        'Kambing Jantan',
        'Breeding',
    ];

    /**
     * Daftar pilihan ABK (penanggung jawab kandang).
     *
     * @var array<int, string>
     */
    public const ABK_OPTIONS = [
        'Yudianto',
        'Rio Hanif',
        'Fais Al Aqib',
        'Didik Suharianto',
        'Herianto',
    ];

    /**
     * Kolom yang dapat diisi secara massal (mass assignable).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'category',
        'abk',
        'capacity',
        'status',
    ];

    /**
     * Tipe data untuk kolom tertentu.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'capacity' => 'integer',
    ];

    /**
     * Atribut tambahan (accessor) yang akan disertakan dalam response JSON.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'current_occupancy',
        'age_days',
    ];

    /**
     * Relasi satu ke banyak: kandang ini memiliki banyak ternak (hanya yang aktif).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function livestocks(): HasMany
    {
        return $this->hasMany(Livestock::class)->where('status', true);
    }

    /**
     * Accessor untuk mendapatkan jumlah ternak aktif saat ini di kandang.
     *
     * @return int
     */
    public function getCurrentOccupancyAttribute(): int
    {
        return $this->livestocks()->count();
    }

    /**
     * Accessor untuk mendapatkan umur kandang (dalam hari) sejak dibuat.
     *
     * @return int
     */
    public function getAgeDaysAttribute(): int
    {
        if ($this->created_at === null) {
            return 0;
        }
        return $this->created_at->diffInDays(now());
    }

    /**
     * Scope query untuk hanya mengambil kandang yang aktif.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Mendapatkan total jumlah ternak aktif di semua kandang.
     *
     * @return int
     */
    public static function getTotalLivestockCount(): int
    {
        return Livestock::where('status', true)->count();
    }
}