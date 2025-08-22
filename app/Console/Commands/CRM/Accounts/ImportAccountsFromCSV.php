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

class ImportAccountsFromCSV extends Command
{
    protected $signature = 'import:accounts {filePath}';
    protected $description = 'Import all accounts from a JSON file and log them in the accounts table';

    public function handle()
    {
        DB::table('accounts')->truncate();

        $filePath = $this->argument('filePath');

        if (!File::exists($filePath)) {
            $this->error("File not found at path: $filePath");
            return;
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            $this->error("Unable to open file.");
            return;
        }

        $header = fgetcsv($handle); // Read header row
        $batch = [];
        $chunkSize = 1000;
        $totalInserted = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $rowData = array_combine($header, $row);

            if(empty($rowData['Account Name'])) continue;

            $batch[] = [
                'zoho_id'       => $rowData['Record Id'] ?? null,
                'zoho_parent_id'      => $rowData['Parent Account.id'] ?? null,
                'fresh_crm_id'       => trim($rowData['Fresh CRM ID']) !== '' ? trim($rowData['Fresh CRM ID']) : null,
                'name'       => $rowData['Account Name'] ?? null,
                'phone'      => $rowData['Phone'] ?? null,
                'type'      => $rowData['Account Type'] ?? null,
                'website'       => $rowData['Website'] ?? null,
                'street'       => $rowData['Billing Street'] ?? null,
                'city'       => $rowData['Billing City'] ?? null,
                'state'       => $rowData['Billing State'] ?? null,
                'zip'       => $rowData['Billing Code'] ?? null,
                'country'       => $rowData['Billing Country'] ?? null,
                'starting_grade' => !empty($rowData['Starting Grade']) ? (int)$rowData['Starting Grade'] : 0,
                'ending_grade'   => !empty($rowData['Ending Grade'])   ? (int)$rowData['Ending Grade']   : 0,
            ];

            if (count($batch) >= $chunkSize) {
                DB::table('accounts')->insert($batch);
                $totalInserted += count($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            DB::table('accounts')->insert($batch);
            $totalInserted += count($batch);
        }

        fclose($handle);

        $this->info("✅ Imported {$totalInserted} rows into table.");
    }
}

