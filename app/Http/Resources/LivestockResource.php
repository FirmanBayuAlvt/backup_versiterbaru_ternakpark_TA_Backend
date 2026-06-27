<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LivestockResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // === KOLOM DARI MIGRASI (BARU) ===
            'condition'              => $this->condition,
            'date_in'                => $this->date_in?->format('Y-m-d'),
            'day_on_farm'            => $this->day_on_farm,
            'reproductive_age'       => $this->reproductive_age,
            'date_of_death_or_sold'  => $this->date_of_death_or_sold?->format('Y-m-d'),
            'father_ear_tag'         => $this->father_ear_tag,
            'mother_ear_tag'         => $this->mother_ear_tag,
            'last_weight_date'       => $this->last_weight_date?->format('Y-m-d'),

            // === KOLOM ASLI ===
            'id'                     => $this->id,
            'ear_tag'                => $this->ear_tag,
            'breed_type'             => $this->breed_type,
            'gender'                 => $this->gender,
            'birth_date'             => $this->birth_date?->format('Y-m-d'),
            'age_days'               => $this->age_days,
            'initial_weight'         => (float) $this->initial_weight,
            'current_weight'         => (float) $this->current_weight,
            'health_status'          => $this->health_status,
            'notes'                  => $this->notes,
            'status'                 => $this->status,
            'image_url'              => $this->image_url,
            'created_at'             => $this->created_at?->toISOString(),
            'updated_at'             => $this->updated_at?->toISOString(),

            // === RELASI (hanya dimuat jika sudah di- eager load) ===
            'pen'                    => $this->whenLoaded('pen', function () {
                return new PenResource($this->pen);
            }),
            'weight_records'         => WeightRecordResource::collection($this->whenLoaded('weightRecords')),

            // === DATA PERFORMANCE (aksesor dinamis) ===
            'performance' => [
                'average_daily_gain' => (float) $this->average_daily_gain,
                'total_gain'         => (float) ($this->current_weight - $this->initial_weight),
                'last_weight_record' => $this->whenLoaded('weightRecords', function () {
                    return $this->weightRecords->first()?->weight_kg;
                }),
            ],
        ];
    }
}