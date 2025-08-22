<?php

namespace App\Console\Commands\CRM\Contacts;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportContactsFromCSV extends Command
{
    protected $signature = 'import:contacts2 {filePath}';
    protected $description = 'Import all contacts from a JSON file and log them in the contacts table';

    public function handle()
    {
        DB::table('contacts')->truncate();

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
        $chunkSize = 5000;
        $totalInserted = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $rowData = array_combine($header, $row);

            $batch[] = [
                'zoho_id'       => $rowData['Record Id'] ?? null,
                'first_name'      => $rowData['First Name'] ?? null,
                'last_name'      => $rowData['Last Name'] ?? null,
                'email'       => $rowData['Email'] ?? null,
                'account_id'       => $rowData['Account Name.id'] ?? null,
                'account_name'       => $rowData['Account Name'] ?? null,
                'phone'       => $rowData['Phone'] ?? null,
                'mobile'       => $rowData['Mobile'] ?? null,
                'title'       => $rowData['Title'] ?? null,
            ];

            if (count($batch) >= $chunkSize) {
                DB::table('contacts')->insert($batch);
                $totalInserted += count($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            DB::table('contacts')->insert($batch);
            $totalInserted += count($batch);
        }

        fclose($handle);

        $this->info("✅ Imported {$totalInserted} rows into table.");
    }
}

