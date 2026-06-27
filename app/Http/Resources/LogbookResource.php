<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LogbookResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'livestock_id'            => $this->livestock_id,
            'ear_tag'                 => optional($this->livestock)->ear_tag,
            'event_date'              => $this->event_date?->toDateTimeString(),
            'formatted_event_date'    => $this->formatted_event_date,
            'event_type'              => $this->event_type,
            'description'             => $this->description,
            'handling'                => $this->handling,
            'new_tag'                 => $this->new_tag,
            'new_pen_id'              => $this->new_pen_id,
            'new_pen_name'            => optional($this->newPen)->name,
            'new_pen_category'        => $this->new_pen_category,
            'officer_name'            => $this->officer_name,
            'pregnancy_date'          => $this->pregnancy_date?->toDateString(),
            'formatted_pregnancy_date'=> $this->formatted_pregnancy_date,
            'created_at'              => $this->created_at?->toISOString(),
            'updated_at'              => $this->updated_at?->toISOString(),
        ];
    }
}