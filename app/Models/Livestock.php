<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Livestock extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ear_tag',
        'breed_type',
        'gender',
        'birth_date',
        'initial_weight',
        'health_status',
        'condition',
        'date_in',
        'day_on_farm',
        'reproductive_age',
        'date_of_death_or_sold',
        'father_ear_tag',
        'mother_ear_tag',
        'notes',
        'status',
        'pen_id',
        'image_url',
        'purchase_price',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'birth_date'              => 'date',
        'date_in'                 => 'date',
        'date_of_death_or_sold'   => 'date',
        'initial_weight'          => 'decimal:2',
        'status'                  => 'boolean',
        'day_on_farm'             => 'integer',
        'created_at'              => 'datetime',
        'updated_at'              => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'current_weight',
        'average_daily_gain',
        'age_days',
        'last_weight_date',
    ];

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the logbook entries for this livestock.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function logbooks(): HasMany
    {
        return $this->hasMany(Logbook::class);
    }

    /**
     * Get the HPP detail for this livestock.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function hppDetail(): HasOne
    {
        return $this->hasOne(HppDetail::class);
    }

    /**
     * Get the pen where this livestock belongs.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function pen(): BelongsTo
    {
        return $this->belongsTo(Pen::class);
    }

    /**
     * Get the weight records for this livestock.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function weightRecords(): HasMany
    {
        return $this->hasMany(WeightRecord::class)->orderBy('record_date', 'desc');
    }

    /**
     * Get the predictions for this livestock.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class);
    }

    /**
     * Get the feeding records for this livestock.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function feedingRecords(): HasMany
    {
        return $this->hasMany(FeedingRecord::class);
    }

    // ==================== ACCESSORS ====================

    /**
     * Get the current weight (latest weight record or initial weight).
     *
     * @return float
     */
    public function getCurrentWeightAttribute(): float
    {
        $latestWeightRecord = $this->weightRecords()->latest('record_date')->first();
        if ($latestWeightRecord !== null) {
            return (float) $latestWeightRecord->weight_kg;
        }
        return (float) $this->initial_weight;
    }

    /**
     * Get the date of the last weight record.
     *
     * @return string|null
     */
    public function getLastWeightDateAttribute(): ?string
    {
        $lastWeightRecord = $this->weightRecords()->latest('record_date')->first();
        if ($lastWeightRecord !== null) {
            return $lastWeightRecord->record_date->toDateString();
        }
        return null;
    }

    /**
     * Calculate average daily gain from the oldest to the latest weight record.
     *
     * @return float
     */
    public function getAverageDailyGainAttribute(): float
    {
        $firstWeightRecord = $this->weightRecords()->oldest('record_date')->first();
        $lastWeightRecord = $this->weightRecords()->latest('record_date')->first();

        if ($firstWeightRecord === null || $lastWeightRecord === null || $firstWeightRecord->id === $lastWeightRecord->id) {
            return 0.0;
        }

        $daysDifference = $firstWeightRecord->record_date->diffInDays($lastWeightRecord->record_date);
        if ($daysDifference === 0) {
            return 0.0;
        }

        $totalGain = $lastWeightRecord->weight_kg - $firstWeightRecord->weight_kg;
        return round($totalGain / $daysDifference, 3);
    }

    /**
     * Calculate age in days based on birth_date.
     *
     * @return int
     */
    public function getAgeDaysAttribute(): int
    {
        if ($this->birth_date === null) {
            return 0;
        }
        return $this->birth_date->diffInDays(now());
    }

    /**
     * Get day on farm (manual or calculated from date_in).
     *
     * @param  mixed  $value
     * @return int
     */
    public function getDayOnFarmAttribute($value): int
    {
        if ($value !== null && $value > 0) {
            return (int) $value;
        }

        if ($this->date_in !== null) {
            return $this->date_in->diffInDays(now());
        }

        return 0;
    }
}
