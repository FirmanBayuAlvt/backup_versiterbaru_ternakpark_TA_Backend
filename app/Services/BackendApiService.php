<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;

class BackendApiService
{
    protected $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.backend.base_url');
        Log::info('BackendApiService initialized', ['base_url' => $this->baseUrl]);
    }

    protected function request($method, $endpoint, $data = [])
    {
        try {
            $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');
            $http = Http::timeout(60);

            if (session()->has('token')) {
                $http = $http->withToken(session('token'));
            }

            $response = $http->$method($url, $data);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error("Backend API error", [
                'method' => $method,
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return [
                'success' => false,
                'message' => 'Backend API error: ' . $response->status()
            ];
        } catch (\Exception $e) {
            Log::error("Backend API exception", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'message' => 'Tidak dapat terhubung ke server backend: ' . $e->getMessage()
            ];
        }
    }

    // ==================== LIVESTOCKS ====================
    public function getLivestocks($params = [])
    {
        return $this->request('get', '/livestocks', $params);
    }

    public function getLivestockDetail($id)
    {
        return $this->request('get', "/livestocks/{$id}");
    }

    public function createLivestock($data)
    {
        if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
            return $this->uploadFileRequest('post', '/livestocks', $data, 'image');
        }
        return $this->request('post', '/livestocks', $data);
    }

    public function updateLivestock($id, $data)
    {
        if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
            return $this->uploadFileRequest('post', "/livestocks/{$id}", $data, 'image', '_method=PUT');
        }
        // Jika tidak ada file, kirim sebagai PUT dengan data JSON
        return $this->request('put', "/livestocks/{$id}", $data);
    }

    public function recordWeight($id, $data)
    {
        return $this->request('post', "/livestocks/{$id}/record-weight", $data);
    }

    public function importLivestocks($file)
    {
        return $this->uploadFileRequest('post', '/livestocks/import', ['file' => $file], 'file');
    }

    // ==================== PENS ====================
    public function getPens($params = [])
    {
        return $this->request('get', '/pens', $params);
    }

    public function getPenDetail($id)
    {
        return $this->request('get', "/pens/{$id}");
    }

    public function getPenAnalytics($id)
    {
        return $this->request('get', "/pens/{$id}/analytics");
    }

    public function createPen($data)
    {
        return $this->request('post', '/pens', $data);
    }

    public function importPens($file)
    {
        return $this->uploadFileRequest('post', '/pens/import', ['file' => $file], 'file');
    }

    // ==================== FEEDS ====================
    public function getFeeds($params = [])
    {
        return $this->request('get', '/feeds', $params);
    }

    public function getFeedStock()
    {
        return $this->request('get', '/feeds/stock/summary');
    }

    public function getFeedRequirements()
    {
        return $this->request('get', '/feeds/requirements');
    }

    public function recordFeeding($data)
    {
        return $this->request('post', '/feeds/record-feeding', $data);
    }

    public function updateFeedStock($data)
    {
        return $this->request('post', '/feeds/update-stock', $data);
    }

    public function createFeed($data)
    {
        return $this->request('post', '/feeds', $data);
    }

    public function importFeeds($file)
    {
        return $this->uploadFileRequest('post', '/feeds/import', ['file' => $file], 'file');
    }

    // ==================== PREDICTIONS ====================
    public function getPredictions($params = [])
    {
        return $this->request('get', '/predictions', $params);
    }

    public function getPredictionHistory($params = [])
    {
        return $this->request('get', '/predictions/history', $params);
    }

    public function getCorrelationData()
    {
        return $this->request('get', '/predictions/correlation');
    }

    public function createPrediction($data)
    {
        return $this->request('post', '/predictions', $data);
    }

    // ==================== DASHBOARD ====================
    public function getDashboardOverview()
    {
        return $this->request('get', '/dashboard/overview');
    }

    public function getDashboardPenAnalytics()
    {
        return $this->request('get', '/dashboard/pen-analytics');
    }

    // ==================== REPORTS ====================
    public function getReportSummary()
    {
        return $this->request('get', '/reports/summary');
    }

    public function getReportPerformance()
    {
        return $this->request('get', '/reports/performance');
    }

    public function getReportGrowth()
    {
        return $this->request('get', '/reports/growth');
    }

    public function getReportFinancial()
    {
        return $this->request('get', '/reports/financial');
    }

    // ==================== HELPER UPLOAD ====================
    protected function uploadFileRequest($method, $endpoint, $data, $fileKey, $extraFields = [])
    {
        try {
            $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');
            $http = Http::timeout(60);

            if (session()->has('token')) {
                $http = $http->withToken(session('token'));
            }

            // Siapkan file
            $file = $data[$fileKey] ?? null;
            unset($data[$fileKey]);

            // Tambahkan field ekstra (misal _method=PUT)
            foreach ($extraFields as $key => $value) {
                $data[$key] = $value;
            }

            $http = $http->attach($fileKey, file_get_contents($file->getRealPath()), $file->getClientOriginalName());

            foreach ($data as $key => $value) {
                $http = $http->addString($key, $value);
            }

            $response = $http->post($url);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error("Backend API upload error", [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return ['success' => false, 'message' => 'Upload gagal: ' . $response->status()];
        } catch (\Exception $e) {
            Log::error("Backend API upload exception", ['message' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Koneksi error: ' . $e->getMessage()];
        }
    }
}
