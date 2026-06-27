<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PredictionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'livestock' => new LivestockResource($this->whenLoaded('livestock')),
            'prediction_days' => $this->prediction_days,
            'predicted_gain' => (float) $this->predicted_gain,
            'confidence' => (float) $this->confidence,
            'interval' => [
                'lower' => (float) $this->interval_lower,
                'upper' => (float) $this->interval_upper,
            ],
            'recommendations' => $this->recommendations,
            'input_features' => $this->input_features,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
