<?php

namespace App\Console\Commands\CRM\Accounts;

use App\Http\Controllers\TicketController;
use App\Jobs\ProcessZohoContact;
use App\Jobs\ProcessZohoTicket;
use App\Models\Article;
use App\Models\CategoryMapping;
use App\Services\ZohoApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ImportAccountsFromFreshCRM extends Command
{
    protected $signature = 'accounts:flag-new {filePath} {--output=zoho_accounts.csv}';
    protected $description = 'Check all accounts from Fresh CRM file, insert/update into local DB, and generate Zoho CSV.';

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
        if (!$fpZoho) {
            $this->error("❌ Unable to open output file: $outputFile");
            return;
        }

        // Zoho CSV headers
        $zohoHeaders = [
            'Account Owner.id','Account Owner','Account Name','Phone','Parent Account.id','Website','Account Type',
            'Created By.id','Created By','Modified By.id','Modified By','Created Time','Modified Time',
            'Billing Street','Billing City','Billing State','Billing Code','Billing Country',
            'Locked','Last contacted time','Last contacted mode','Last activity type','Last activity date',
            'Starting Grade','Ending Grade','FRESH_CRM_ID'
        ];
        fputcsv($fpZoho, $zohoHeaders);

        // Cache existing Fresh CRM IDs to avoid duplicates
        $existingFreshIds = DB::table('accounts')
            ->pluck('fresh_crm_id')
            ->filter()
            ->map(fn($id) => trim(strtolower($id)))
            ->toArray();
        $existingFreshIdsSet = array_flip($existingFreshIds);

        // Cache existing Names (case-insensitive)
        $existingNames = DB::table('accounts')
            ->pluck('name')
            ->filter()
            ->map(fn($n) => trim(strtolower($n)))
            ->toArray();
        $existingNamesSet = array_flip($existingNames);

        $header = fgetcsv($handle); // Read header row
        $newRecords = [];
        $updateIds = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rowData = array_combine($header, $row);

            if (empty($rowData['Id']) || empty($rowData['Name']) || empty($rowData['Parent account id'])) {
                continue; // skip invalid rows
            }

            $freshId = $rowData['Id'] ? strtolower(trim($rowData['Id'])) : null;
            $nameNormalized = strtolower(trim($rowData['Name']));

            if ($freshId && isset($existingFreshIdsSet[$freshId])) {
                // Found by Fresh CRM ID
                $updateIds[] = $freshId;
            } elseif (isset($existingNamesSet[$nameNormalized])) {
                // Found by Account Name
                $updateNames[] = $nameNormalized;
            } else {
                // New record
                $newRecords[] = [
                    'fresh_crm_id'        => $rowData['Id'],
                    'fresh_crm_parent_id' => $rowData['Parent account id'] ?? null,
                    'name'                => $rowData['Name'],
                    'website'             => $rowData['Website'] ?? null,
                    'phone'               => $rowData['Phone'] ?? null,
                    'is_new'              => 1
                ];

                // Build Zoho row for CSV
                $zohoRow = [
                    'zcrm_6739882000000560001', // Account Owner.id (replace with dynamic later if needed)
                    'Rob Kenney', // Account Owner
                    $rowData['Name'], // Account Name
                    $rowData['Phone'] ?? '',
                    '', // Parent Account.id → will be filled later in child import
                    $rowData['Website'] ?? '',
                    $rowData['Account Type'] ?? '',

                    'zcrm_6739882000000560001', // Created By.id
                    'Rob Kenney', // Created By
                    'zcrm_6739882000000560001', // Modified By.id
                    'Rob Kenney', // Modified By
                    $this->formatDate($rowData['Created Time'] ?? null),
                    $this->formatDate($rowData['Modified Time'] ?? null),

                    $rowData['Billing Street'] ?? '',
                    $rowData['Billing City'] ?? '',
                    $rowData['Billing State'] ?? '',
                    $rowData['Billing Code'] ?? '',
                    $rowData['Billing Country'] ?? '',

                    'FALSE', // Locked
                    $this->formatDate($rowData['Last contacted time'] ?? null),
                    $rowData['Last contacted mode'] ?? '',
                    $rowData['Last activity type'] ?? '',
                    $this->formatDate($rowData['Last activity date'] ?? null),

                    $rowData['Starting Grade'] ?? '',
                    $rowData['Ending Grade'] ?? '',
                    'fc-' . $rowData['Id'], // Fresh CRM ID
                ];

                fputcsv($fpZoho, $zohoRow);
            }
        }

        fclose($handle);
        fclose($fpZoho);

        // Update old records
        if (!empty($updateIds)) {
            DB::table('accounts')
                ->whereIn(DB::raw('LOWER(TRIM(fresh_crm_id))'), $updateIds)
                ->update([ 'updated_at' => now()]);
        }

        // Update old records
        if (!empty($updateNames)) {
            DB::table('accounts')
                ->whereIn(DB::raw('LOWER(TRIM(name))'), $updateNames)
                ->update([ 'updated_at' => now()]);
        }

        // Insert new records
        if (!empty($newRecords)) {
            DB::table('accounts')->insert($newRecords);
        }

        $this->info("✅ Inserted " . count($newRecords) . " new accounts.");
        $this->info("🔄 Updated " . count($updateIds) + count($updateNames) . " existing accounts.");
        $this->info("📂 Exported Zoho CSV to: $outputFile");
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
}

