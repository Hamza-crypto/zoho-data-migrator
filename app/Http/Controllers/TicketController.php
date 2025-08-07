<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Services\ZohoApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TicketController extends Controller
{
    public function searchTicketByTitle($title, $old_id)
    {
        $cache_key = 'zoho_ticket_' . $old_id;

        if (Cache::has($cache_key)) {
            return Cache::get($cache_key);
        }

        $existing = Ticket::where('old_ticket_id', $old_id)->first();
        if ($existing) {
            return $existing->zoho_ticket_id;
        }

        $zoho = new ZohoApiService();
        $encodedTitle = rawurlencode($title);
        $departmentId = '1120359000000006907';
        $limit = 1;

        $url = sprintf('tickets/search?departmentId=%s&subject=%s&limit=%d',
            $departmentId,
            $encodedTitle,
            $limit
        );

        $result = $zoho->makeRequest('get', $url, [], 'tickets');

        if (!empty($result['data']) && count($result['data']) > 0) {
            $found = $result['data'][0];

            Ticket::create([
                'title'           => $found['subject'],
                'old_ticket_id'  => $old_id,
                'zoho_ticket_id'  => $found['id'],
            ]);

            Cache::put($cache_key, $found['id'], now()->addDays(7));

            return $found['id'];
        } else {
            Log::info("No match for title: " . $title);
        }

    }
}
