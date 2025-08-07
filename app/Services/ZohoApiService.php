<?php

namespace App\Services;

use App\Models\ZohoLog;
use Illuminate\Support\Facades\Http;

class ZohoApiService
{
    public $accessToken;

    public function __construct()
    {
        $this->accessToken = $this->getAccessToken();
    }

    public function getAccessToken()
    {
        return env('ZOHO_ACCESS_TOKEN');
    }

    public function makeRequest($method, $url, $data = [], $module = null, $internalId = null)
    {
        $url = sprintf('%s/%s', env('ZOHO_BASE_URL'), $url);

        $request = Http::withHeaders([
            'Authorization' => 'Zoho-oauthtoken ' . $this->accessToken,
            'Content-Type' => 'application/json',
        ]);

        if (!empty($data)) {
            dd($data);
            $response = $request->$method($url, $data);
        } else {
            $response = $request->$method($url);
        }

        dump('New request made ' . $url);
        $success = $response->successful();
        $responseBody = $response->json();

        // Get Zoho ID if present
        $zohoId = $responseBody['id'] ?? null;

        // Save the log
        ZohoLog::class::create([
            'module' => $module,
            'internal_id' => $internalId,
            'zoho_record_id' => $zohoId,
            'payload' => $data,
            'response' => $responseBody,
            'success' => $success,
        ]);

        return $responseBody;
    }

    public function createRecord($module, $data, $internalId = null)
    {
        $url = sprintf('%s/%s', env('ZOHO_BASE_URL'), $module);
        return $this->makeRequest('post', $url, $data, $module, $internalId);
    }


}
