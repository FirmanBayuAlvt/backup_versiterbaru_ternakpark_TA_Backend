<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Logbook extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terkait dengan model ini.
     *
     * @var string
     */
    protected $table = 'logbooks';

    /**
     * Nama primary key dari tabel.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Apakah primary key auto-increment.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * Tipe data primary key.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * Apakah model menggunakan timestamp (created_at, updated_at).
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * Atribut-atribut yang dapat diisi secara massal.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'livestock_id',
        'event_date',
        'event_type',
        'description',
        'handling',
        'new_tag',
        'new_pen_id',
        'new_pen_category',
        'officer_name',
        'pregnancy_date',
    ];

    /**
     * Casting tipe data untuk atribut tertentu.
     * event_date di-cast sebagai datetime (bukan date) agar menyimpan waktu (jam, menit, detik).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'event_date'     => 'datetime',
        'pregnancy_date' => 'date',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    /**
     * Atribut yang harus diperlakukan sebagai tanggal (opsional, bisa dihapus karena sudah ditangani casts).
     *
     * @var array<int, string>
     */
    protected $dates = [
        'event_date',
        'pregnancy_date',
        'created_at',
        'updated_at',
    ];

    /**
     * Relasi belongsTo ke model Livestock.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function livestock(): BelongsTo
    {
        return $this->belongsTo(Livestock::class, 'livestock_id');
    }

    /**
     * Relasi belongsTo ke model Pen untuk kandang baru (jika pindah kandang).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function newPen(): BelongsTo
    {
        return $this->belongsTo(Pen::class, 'new_pen_id');
    }

    /**
     * Scope query untuk filter berdasarkan event_type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('event_type', $type);
    }

    /**
     * Scope query untuk filter berdasarkan rentang tanggal event_date.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDateBetween($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('event_date', [$startDate, $endDate]);
    }

    /**
     * Scope query untuk filter berdasarkan livestock_id.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $livestockId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForLivestock($query, int $livestockId)
    {
        return $query->where('livestock_id', $livestockId);
    }

    /**
     * Accessor untuk mendapatkan event_date dalam format d/m/Y H:i:s (lengkap).
     *
     * @return string
     */
    public function getFormattedEventDateAttribute(): string
    {
        if ($this->event_date === null) {
            return '';
        }
        return $this->event_date->format('d/m/Y H:i:s');
    }

    /**
     * Accessor untuk mendapatkan pregnancy_date dalam format d/m/Y.
     *
     * @return string
     */
    public function getFormattedPregnancyDateAttribute(): string
    {
        if ($this->pregnancy_date === null) {
            return '';
        }
        return $this->pregnancy_date->format('d/m/Y');
    }
}
