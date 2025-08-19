<?php

namespace App\Console\Commands\CRM\Accounts;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportAllAccountsFromFreshCRM extends Command
{
    protected $signature = 'accounts:import-all';
    protected $description = 'Import Fresh CRM accounts into local DB (deduplicated + super fast).';

    public function handle()
    {
        $folderPath = storage_path('app/Accounts');
        $files = glob($folderPath . '/Accounts_FRESH_CRM_*.csv');

        foreach ($files as $file) {
            $this->info("📂 Processing: " . basename($file));

            $rowsToInsert = [];
            $header = null;

            if (($handle = fopen($file, 'r')) !== false) {
                while (($row = fgetcsv($handle, 4000, ',')) !== false) {
                    if (!$header) {
                        $header = $row; // first row = header
                        continue;
                    }

                    $data = array_combine($header, $row);

                    $rowsToInsert[] = [
                        'fresh_id'    => $data['Id'] ?? null,
                        'name'        => $data['Name'] ?? null,
                        'parent_id'   => $data['Parent account id'] ?? null,
                        'parent_name' => $data['Parent account'] ?? null,
                        'phone'       => $data['Phone'] ?? null,
                        'website'     => $data['Website'] ?? null,
                        'zipcode'     => $data['Zipcode'] ?? null,
                    ];

                    // 🚀 Flush every 2000 rows instead of keeping all in memory
                    if (count($rowsToInsert) >= 2000) {
                        $this->insertBatch($rowsToInsert);
                        $rowsToInsert = [];
                    }
                }
                fclose($handle);
            }

            // Flush leftovers
            if (!empty($rowsToInsert)) {
                $this->insertBatch($rowsToInsert);
            }
        }
    }

    /**
     * Insert or update batch into DB with deduplication.
     */
    private function insertBatch(array $rows)
    {
        // Remove duplicates inside current batch (same fresh_id in file)
        $rows = collect($rows)->unique('fresh_id')->values()->all();

        // 🚀 Use UPSERT (dedupe against DB by fresh_id)
        DB::table('fresh_accounts')->upsert(
            $rows,
            ['fresh_id'], // unique key
            ['name','parent_id','parent_name','phone','website','zipcode'] // update if exists
        );

        $this->info("✅ Processed batch of " . count($rows) . " rows");
    }
}
