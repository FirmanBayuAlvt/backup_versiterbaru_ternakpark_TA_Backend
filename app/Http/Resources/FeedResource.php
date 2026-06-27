<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'current_stock' => (float) $this->current_stock,
            'price_per_kg' => (float) $this->price_per_kg,
            'unit' => $this->unit,
            'is_active' => $this->is_active,
            'is_stock_low' => $this->is_stock_low,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}