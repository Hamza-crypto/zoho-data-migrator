<?php

namespace App\Console\Commands\CRM\Contacts;

use App\Models\Account;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class ImportContactsFromFreshCRM extends Command
{
    protected $signature = 'contacts:flag-new {filePath} {--output=zoho_contacts.csv}';
    protected $description = 'Check all contacts from Fresh CRM file, insert/update into local DB, and generate Zoho CSV.';

    private $accountMap = [];

    public function handle()
    {
        $filePath = $this->argument('filePath');
        $outputFile = $this->option('output');

        if (!File::exists($filePath)) {
            $this->error("❌ File not found at path: $filePath");
            return;
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            $this->error("❌ Unable to open file.");
            return;
        }

        if (!$outputFile) {
            $this->error("❌ Please provide --output=/path/to/output.csv");
            return;
        }

        // Open Zoho CSV file for writing
        $fpZoho = fopen($outputFile, 'w');
        $fpZohoManuall = fopen('storage/app/zoho_import_manuall.csv', 'w');
        if (!$fpZoho) {
            $this->error("❌ Unable to open output file: $outputFile");
            return;
        }

        // Zoho CSV headers
        $zohoHeaders = [
            'Contact Owner.id','Contact Owner','First Name','Last Name','Account Name.id','Account Name',
            'Email', 'Title','Phone','Mobile',
            'Created By.id','Created By','Modified By.id','Modified By','Created Time','Modified Time','Contact Name',
            'Mailing Street','Mailing City','Mailing State','Mailing Zip','Mailing Country',
            'Email Opt Out','Salutation',
            'Last Activity Time', 'Locked', 'Last contacted time','Last contacted mode','Last activity type',
            'Last activity date', 'Updated by','Prefix','Suffix','Addressee', 'Fresh CRM ID'
        ];

        fputcsv($fpZoho, $zohoHeaders);
        fputcsv($fpZohoManuall, $zohoHeaders);

        $existingContacts = DB::table('contacts')
            ->select( 'email', 'first_name', 'last_name', 'account_name')
            ->get();

        $existingByEmail = [];
        $existingByCompositeName = [];
        $existingByAccount = [];

        foreach ($existingContacts as $contact) {
            if ($contact->email) {
                $existingByEmail[strtolower(trim($contact->email))] = true;
            }

            if ($contact->account_name) {
                $existingByAccount[trim($contact->account_name)] = true;
            }

            // Build composite key: first+last+account
            $compositeName = strtolower(trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? '') ));
            if ($compositeName) {
                $existingByCompositeName[$compositeName] = true;
            }
        }

        $header = fgetcsv($handle); // Read header row
        $newRecords = [];
        $updateIds = [];

        $email_match = 0;
        $name_match = 0;
        $account_match = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $rowData = array_combine($header, $row);

            //if(empty($rowData['Email'])) continue;
            //if($rowData['Id'] != '28148387097') continue;

            $email = !empty($rowData['Email']) ? strtolower(trim($rowData['Email'])) : null;
            $first = strtolower(trim($rowData['First name'] ?? ''));
            $last = strtolower(trim($rowData['Last name'] ?? ''));
            $account = strtolower(trim($rowData['Account'] ?? ''));

            $compositeName = trim($first . ' ' . $last);

            if ($email && isset($existingByEmail[$email])) {
                // ✅ Match by Email
                $updateRecordsByEmail[] = $email;
                $email_match++;
            } elseif ($compositeName && isset($existingByCompositeName[$compositeName])) {
                // ✅ Match by (First + Last + Account)
                $updateRecordsByName[] = $compositeName;
                $name_match++;
            } elseif ($account && isset($existingByAccount[$account])) {
                $updateRecordsByAccount[] = $account;
                $account_match++;
            } else {
                // New record
                $newRecords[] = [
                    'first_name'          => empty($rowData['First name']) ? null : $rowData['First name'],
                    'last_name'           => empty($rowData['Last name']) ? null : $rowData['Last name'],
                    'email'               => empty($rowData['Email']) ? null : $rowData['Email'],
                    'is_new'              => 1
                ];

                $zoho_acc_id = $this->getZohoAccountId($rowData);

                $zohoRow = [
                    'zcrm_6739882000000560001',
                    'Rob Kenney',
                    $first,
                    $last,
                    $zoho_acc_id ?: ($rowData['Account id'] ?? ''), //Account id
                    $rowData['Account'] ?? '', //Account Name
                    $rowData['Email'] ?? '',
                    $rowData['Job title'] ?? '',
                    $rowData['Work phone'] ?? '',
                    $rowData['Mobile'] ?? '',
                    'zcrm_6739882000000560001','Rob Kenney',
                    'zcrm_6739882000000560001','Rob Kenney',
                    $this->formatDate($rowData['Created at'] ),
                    $this->formatDate($rowData['Updated at']),
                    sprintf('%s %s', $first, $last),
                    $rowData['Address'] ?? '',
                    $rowData['City'] ?? '',
                    $rowData['State'] ?? '',
                    $rowData['Zipcode'] ?? '',
                    $rowData['Country'] ?? '',
                    'FALSE',
                    $rowData['Prefix'] ?? '',
                    $this->formatDate($rowData['Last activity date'] ?? null),
                    'FALSE',
                    $this->formatDate($rowData['Last contacted time'] ?? null),
                    $rowData['Last contacted mode'] ?? '',
                    $rowData['Last activity type'] ?? '',
                    $this->formatDate($rowData['Last activity date'] ?? null),
                    'Rob Kenney',
                    $rowData['Prefix'] ?? '',
                    $rowData['Suffix'] ?? '',
                    $rowData['Addressee'] ?? '',
                    $rowData['Id'] ? 'fc-' . $rowData['Id'] : '',
                ];

                // Build Zoho row for CSV

                if (!$zoho_acc_id) {
                    fputcsv($fpZohoManuall, $zohoRow);
                } else {
                    fputcsv($fpZoho, $zohoRow);
                }
            }
        }

        fclose($handle);
        fclose($fpZoho);
        fclose($fpZohoManuall);

        // Update old records
        if (!empty($updateRecordsByEmail)) {
//            DB::table('contacts')
//                ->whereIn('email', $updateRecordsByEmail)
//                ->update([ 'updated_at' => now()]);
        }

        if (!empty($updateRecordsByName)) {
            foreach ($updateRecordsByName as $fullName) {
                $parts = explode(' ', $fullName, 2);
                $first = $parts[0] ?? '';
                $last  = $parts[1] ?? '';

//                DB::table('contacts')
//                    ->whereRaw('LOWER(TRIM(first_name)) = ? AND LOWER(TRIM(last_name)) = ?', [$first, $last])
//                    ->update(['updated_at' => now()]);
            }
        }

        if (!empty($updateRecordsByAccount)) {
//            DB::table('contacts')
//                ->whereIn('account_name', $updateRecordsByAccount)
//                ->update([ 'updated_at' => now()]);
        }

        // Insert new records
        if (!empty($newRecords)) {
//            DB::table('contacts')->insert($newRecords);
        }

        $this->info("✅ Inserted " . count($newRecords) . " new contacts.");
        $this->info("📂 Exported Zoho CSV to: $outputFile");
        $this->info("📂 Email Match: $email_match");
        $this->info("📂 Name Match: $name_match");
        $this->info("📂 Account Match: $account_match");
    }

    private function formatDate($date)
    {
        if (!$date) {
            return '';
        }

        try {
            return Carbon::parse($date)->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return $date;
        }
    }

    private function getZohoAccountId(array $rowData)
    {
        $freshAccountId   = $rowData['Account Id'] ?? null;
        $freshAccountName = $rowData['Account'] ?? null;

        if (!$freshAccountId && !$freshAccountName) {
            return null; // no way to map
        }

        // Step 1: Find Fresh CRM account entry
        $freshAccount = null;
        if ($freshAccountId) {
            $freshAccount = DB::table('fresh_accounts')
                ->where('fresh_id', $freshAccountId)
                ->first();
        }


        // If not found by ID, fallback to name
        if (!$freshAccount && $freshAccountName) {
            $freshAccount = DB::table('fresh_accounts')
                ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($freshAccountName))])
                ->first();

        }

        if (!$freshAccount) {
            return null; // can't map without account info
        }

        // Extract fields from fresh account
        $name    = strtolower(trim($freshAccount->name));
        $phone   = $freshAccount->phone ? preg_replace('/\D/', '', $freshAccount->phone) : null;
        $website = $this->normalizeWebsite($freshAccount->website);
        $zipcode = $freshAccount->zipcode ? trim($freshAccount->zipcode) : null;

        // Step 2: Progressive mapping rules
        // Rule 1: Match by account name only
        $matches = DB::table('accounts')
            ->whereRaw('LOWER(TRIM(name)) = ?', [$name])
            ->pluck('zoho_id');

        if ($matches->count() === 1) {
            return $matches->first();
        }

        // Rule 2: Match by account name + zipcode
        if ($zipcode) {
            $matches = DB::table('accounts')
                ->whereRaw('LOWER(TRIM(name)) = ?', [$name])
                ->where('zipcode', $zipcode)
                ->pluck('zoho_id');

            if ($matches->count() === 1) {
                return $matches->first();
            }
        }

        // Rule 3: Match by account name + phone
        if ($phone) {
            $matches = DB::table('accounts')
                ->whereRaw('LOWER(TRIM(name)) = ?', [$name])
                ->whereRaw('REPLACE(phone, " ", "") = ?', [$phone])
                ->pluck('zoho_id');
            if ($matches->count() === 1) {
                return $matches->first();
            }
        }

        // Rule 4: Match by account name + website
        if ($website) {
            $matches = DB::table('accounts')
                ->whereRaw('LOWER(TRIM(REPLACE(REPLACE(REPLACE(website, "http://", ""), "https://", ""), "www.", ""))) = ?', [$website])
                ->pluck('zoho_id');

            if ($matches->count() === 2) {
                return $matches->first();
            }
        }

        // No unique match → ambiguous or missing
        return null;
    }

    private function normalizeWebsite(?string $website): ?string
    {
        if (!$website) return null;

        $website = strtolower(trim($website));

        // Remove protocol
        $website = preg_replace('#^https?://#', '', $website);

        // Remove www.
        $website = preg_replace('#^www\.#', '', $website);

        // Remove trailing slash
        $website = rtrim($website, '/');

        return $website;
    }
}

