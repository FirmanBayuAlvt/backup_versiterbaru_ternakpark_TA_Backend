<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PenResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'code'              => $this->code,
            'category'          => $this->category,
            'abk'               => $this->abk,
            'capacity'          => $this->capacity,
            'current_occupancy' => $this->current_occupancy,
            'age_days'          => $this->age_days,
            'status'            => $this->status,
            'livestocks'        => LivestockResource::collection($this->whenLoaded('livestocks')),
            'created_at'        => $this->created_at ? $this->created_at->toISOString() : null,
            'updated_at'        => $this->updated_at ? $this->updated_at->toISOString() : null,
        ];
    }
}
