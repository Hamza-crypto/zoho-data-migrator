<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Ticket;
use App\Services\ZohoApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TicketController extends Controller
{
    public function searchTicketByTitle( $ticket)
    {
        $old_id = $ticket['id'];
        $title = $ticket['subject'];
        $cache_key = 'zoho_ticket_' . $old_id;

        if (Cache::has($cache_key)) {
            return Cache::get($cache_key);
        }

        $existing = Ticket::where('old_ticket_id', $old_id)->first();
        if ($existing) {
            return;
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
                'title'          => $found['subject'],
                'old_ticket_id'  => $old_id,
                'zoho_ticket_id'  => $found['id'],
                'created_at'      => Carbon::parse($found['createdTime']),
                'updated_at'      => Carbon::parse($found['modifiedTime']),
            ]);

            Cache::put($cache_key, $found['id'], now()->addDays(7));

            return $found['id'];
        } else {
            Log::info("No match for title: " . $title);
            $this->createTicket($ticket, $old_id);
        }

    }

    public function createTicket($data, $old_id)
    {
        $existingContact = Contact::where('old_contact_id', $data['requester']['id'])->first();
        $payload = [
            'subject'       => $data['subject'] ?? 'No Subject',
            'description'   => $data['description'] ?? '',
            'departmentId'  => '1120359000000006907',
            'contactId'     => $existingContact->zoho_contact_id,
            'email'         =>  $existingContact->email,
            'phone'         =>  $existingContact->phone,
            'priority'      => $data['priority_name'] ?? 'Medium',
            'status'        => $data['status_name'] ?? 'Draft',
            'channel'       => $data['source_name'] ?? 'Web',
            'classification'=> $data['ticket_type'] ?? null,
            'assigneeId'    => '1120359000002469001'
        ];

        $payload = array_filter($payload, function ($value) {
            return $value !== null && $value !== '';
        });

        $zoho = new ZohoApiService();
        $response = $zoho->makeRequest('post', 'tickets', $payload, 'tickets');

        Ticket::create([
            'title'          => $response['subject'],
            'old_ticket_id'  => $old_id,
            'zoho_ticket_id'  => $response['id'],
            'created_at'      => Carbon::parse($response['createdTime']),
            'updated_at'      => Carbon::parse($response['modifiedTime']),
        ]);

        $this->createTicketComments($response['id'], $data['notes'] , $zoho);
        return $response;
    }


    public function createTicketComments($ticket_id, $comments, $zohoController)
    {
        foreach ($comments as $comment) {
            $payload = [
                'isPublic' =>  "true",
                'content'  => $comment['body'],
            ];

            $zohoController->makeRequest('post', 'tickets/' . $ticket_id . '/comments', $payload, 'comments/' . $ticket_id);
        }
    }

}
