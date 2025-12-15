<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExternalUserLookupService
{
    protected $apiUrl;
    protected $apiKey;
    protected $timeout;

    public function __construct()
    {
        $this->apiUrl = config('services.user_lookup.url', env('USER_LOOKUP_API_URL'));
        $this->apiKey = config('services.user_lookup.key', env('USER_LOOKUP_API_KEY'));
        $this->timeout = config('services.user_lookup.timeout', env('USER_LOOKUP_API_TIMEOUT', 10));
    }

    /**
     * Search for users by query string
     *
     * @param string $query Search query
     * @return array Array of user results
     */
    public function search(string $query): array
    {
        try {
            // Check if API is configured
            if (empty($this->apiUrl)) {
                Log::warning('External user lookup API URL not configured');
                return [];
            }

            // Make the API request
            $response = \Http::withOptions(['verify' => false])->withHeaders(['Accept-Language' => 'ar'])->get(env('LDAP_SEARCH_API'), [
                'search' => $query,
            ]);

            // Check if request was successful
            if ($response->successful()) {
                $data = $response->json();

                // Normalize the response format
                // Adjust this based on your actual API response structure
                if (isset($data['data'])) {
                    return $data['data'];
                } elseif (isset($data['users'])) {
                    return $data['users'];
                } elseif (isset($data['results'])) {
                    return $data['results'];
                }

                return $data;
            } else {
                Log::error('External user lookup API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [];
            }
        } catch (\Exception $e) {
            Log::error('External user lookup exception', [
                'message' => $e->getMessage(),
                'query' => $query,
            ]);
            return [];
        }
    }

    /**
     * Get user by ID/number
     *
     * @param string $id User ID or number
     * @return array|null User data or null if not found
     */
    public function getUserById(string $id): ?array
    {
        try {
            if (empty($this->apiUrl)) {
                return null;
            }

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                ])
                ->get($this->apiUrl . '/' . $id);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('External user lookup by ID exception', [
                'message' => $e->getMessage(),
                'id' => $id,
            ]);
            return null;
        }
    }

    /**
     * Check if the external API is configured and available
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiUrl) && !empty($this->apiKey);
    }
}
