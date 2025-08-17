<?php

namespace App\Console\Commands\Desk;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;


class JsonToCsvContactsAll extends Command
{
    protected $signature = 'contacts:all {folder} {outputFile}';
    protected $description = 'Convert contacts JSON to contacts.csv file';

    public function handle()
    {
        $folderPath = rtrim($this->argument('folder'), '/');
        $outputPath = $this->argument('outputFile');

        if (!is_dir($folderPath)) {
            $this->error("❌ Folder not found: {$folderPath}");
            return;
        }

        $headers = [
            'First Name', 'Last Name', 'Email', 'Account Name', 'Phone', 'Mobile',
            'Title', 'Contact Owner', 'Type', 'Street', 'City', 'State', 'Zip', 'Country',
            'Description', 'Secondary Email'
        ];

        $fp = fopen($outputPath, 'w');
        fputcsv($fp, $headers);

        // Loop through all JSON files
        $files = glob($folderPath . '/Tickets*.json');

        if (empty($files)) {
            $this->error("❌ No matching JSON files found in: {$folderPath}");
            return;
        }

        $processedEmails = [];

        foreach ($files as $file) {
            $this->info("📂 Processing: " . basename($file));
            $jsonData = json_decode(file_get_contents($file), true);

            if (!is_array($jsonData)) {
                $this->warn("⚠️ Skipping invalid JSON: {$file}");
                continue;
            }

            foreach ($jsonData as $ticketItem) {
                $ticket = $ticketItem['helpdesk_ticket'] ?? [];

                $requester = $ticket['requester'] ?? [];

                $email = strtolower(trim($requester['email'] ?? ''));

                if (empty($email) || isset($processedEmails[$email])) {
                    continue;
                }

                $processedEmails[$email] = true;

                // Split name into first and last name
                $nameParts = explode(' ', trim($requester['name'] ?? ''));
                $firstName = '';
                $lastName = '';

                if (count($nameParts) > 1) {
                    $firstName = $nameParts[0];
                    $lastName = implode(' ', array_slice($nameParts, 1));
                } elseif (count($nameParts) === 1) {
                    $lastName = $nameParts[0]; // required field
                }

                $address = $requester['address'] ?? '';

                $row = array(
                    $firstName,
                    $lastName,
                    $requester['email'] ?? '',
                    '', // Account Name
                    $requester['phone'] ?? '',
                    $requester['mobile'] ?? '',
                    $requester['job_title'] ?? '',
                    $ticket['responder_name'] ?? '',
                    '', // Type
                    $address,
                    '',
                    '',
                    '',
                    '',
                    '', // Description
                    ''  // Secondary Email
                );

                try {
                    fputcsv($fp, $row);
                }
                catch (\Exception $e) {
                    dd($e->getMessage(), $row , $ticket['id']);
                }

            }
        }

        fclose($fp);

        $this->info("✅ Merged CSV created: {$outputPath}");
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
