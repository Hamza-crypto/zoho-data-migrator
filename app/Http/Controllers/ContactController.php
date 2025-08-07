<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Services\ZohoApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ContactController extends Controller
{
    public function handleContact(array $requester)
    {
        $email = $requester['email'] ?? null;
        $phone = $requester['phone'] ?? null;
        $old_id = $requester['id'] ?? null;

        // Check cache first
        $cacheKey = $email ? "zoho_contact_email_$email" : ($phone ? "zoho_contact_phone_" . preg_replace('/\D/', '', $phone) : null);

        if ($cacheKey && Cache::has($cacheKey)) {
            return;
        }

        $contact = Contact::where('old_contact_id', $old_id)
            ->first();

        if ($contact) {
            \Log::info("Contact found in DB for: $cacheKey");
            Cache::put($cacheKey, $contact->toArray(), now()->addDays(7));
            return;
        }

        $contact = null;
        if ($email) {
            $contact = $this->searchZohoContact('email', $email);
        }

        // If not found by email, search by phone
        if (!$contact && $phone) {
            $contact = $this->searchZohoContact('phone', $phone);
        }

        // If found, store and return
        if ($contact) {
            $this->storeContactLocally($contact, $email, $phone, $old_id);
            Cache::put($cacheKey, $contact, now()->addDays(7));
            return $contact;
        }

        // Not found, so create contact
        $contact = $this->createZohoContact($requester);

        if ($contact) {
            $this->storeContactLocally($contact, $email, $phone);
            Cache::put($cacheKey, $contact, now()->addDays(7));
        }

        return $contact;
    }

    public function searchZohoContact($field, $value)
    {
        $zoho = new ZohoApiService();
        $value = rawurlencode($value);

        $url = "contacts/search?$field=$value&limit=1";

        $response = $zoho->makeRequest('get', $url, [], 'contacts');

        return $response['data'][0] ?? null;
    }

    public function createZohoContact(array $requester)
    {
        $zoho = new ZohoApiService();
        $nameParts = explode(' ', $requester['name'] ?? 'No Name');
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';

        $payload = [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $requester['email'] ?? null,
            'phone' => $requester['phone'] ?? null,
            'title' => $requester['job_title'] ?? null,
            'mobile' => $requester['mobile'] ?? null,
        ];

        $response = $zoho->makeRequest('post', 'contacts', $payload);

        return $response ?? null;
    }

    public function storeContactLocally(array $contact, $email = null, $phone = null, $old_id = null)
    {
        Contact::updateOrCreate(
            ['zoho_contact_id' => $contact['id']],
            [
                'email' => $email ?? $contact['email'] ?? null,
                'phone' => $phone ?? $contact['phone'] ?? null,
                'old_contact_id' => $old_id,
            ]
        );
    }

}
