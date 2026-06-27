<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MLService
{
    protected $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.ml_service.url', env('ML_SERVICE_URL', 'http://localhost:8002'));
    }

    public function predict(array $features): ?array
    {
        try {
            $response = Http::timeout(30)->post($this->baseUrl . '/predict', $features);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('ML service error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;

        } catch (\Exception $e) {
            Log::error('ML service connection failed: ' . $e->getMessage());
            return null;
        }
    }
}