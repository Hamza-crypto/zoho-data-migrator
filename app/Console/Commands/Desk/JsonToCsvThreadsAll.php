<?php

namespace App\Console\Commands\Desk;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;


class JsonToCsvThreadsAll extends Command
{
    protected $signature = 'threads:all {folder} {outputFile}';
    protected $description = 'Convert threads JSON to threads.csv file';

    public function handle()
    {
        $folderPath = rtrim($this->argument('folder'), '/');
        $outputPath = $this->argument('outputFile');

        if (!is_dir($folderPath)) {
            $this->error("❌ Folder not found: {$folderPath}");
            return;
        }

        $headers = [
            'ticker id', 'from', 'To', 'send time', 'Cc',
            'description', 'Bcc', 'IsPublic'
        ];

        $fp = fopen($outputPath, 'w');
        fputcsv($fp, $headers);

        $files = glob($folderPath . '/Tickets*.json');

        if (empty($files)) {
            $this->error("❌ No matching JSON files found in: {$folderPath}");
            return;
        }

        $counter = 0;
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

                foreach ($ticket['notes'] as $note) {

                    if($note['user_id'] === $requester['id']) {
                        $from = $requester['email'];
                        $to = 'rkenney@arktest.org';
                    }
                    else{
                        $from = 'rkenney@arktest.org';
                        $to = $requester['email'];
                    }

                    $isPublic = $note['private'] === true ? 'False' : 'True';

                    $row = [
                        $ticket['id'],
                        $from,
                        $to,
                        $this->formatDate($note['created_at']),
                        '',
                        $note['body'] ?? '',
                        '',
                        $isPublic
                    ];

                    fputcsv($fp, $row);
                }
                $counter++;
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
