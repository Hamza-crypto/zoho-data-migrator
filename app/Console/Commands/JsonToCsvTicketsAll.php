<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;


class JsonToCsvTicketsAll extends Command
{
    protected $signature = 'json:tocsv-all {folder} {outputFile}';
    protected $description = 'Convert tickets JSON to Tickets.csv file';

    public function handle()
    {
        $folderPath = rtrim($this->argument('folder'), '/');
        $outputPath = $this->argument('outputFile');

        if (!is_dir($folderPath)) {
            $this->error("❌ Folder not found: {$folderPath}");
            return;
        }

        // CSV Headers
        $headers = [
            'Ticker Id', 'Contact Name', 'Email', 'Subject', 'Description',
            'Status', 'Ticket Owner', 'Created Time', 'Modified Time',
            'Resolution', 'Due Date', 'Priority', 'Category',
            'Ticket Closed Time', 'Classification'
        ];

        $fp = fopen($outputPath, 'w');
        fputcsv($fp, $headers);

        // Loop through all JSON files
        $files = glob($folderPath . '/Tickets*.json');

        if (empty($files)) {
            $this->error("❌ No matching JSON files found in: {$folderPath}");
            return;
        }

        foreach ($files as $file) {
            $this->info("📂 Processing: " . basename($file));
            $jsonData = json_decode(file_get_contents($file), true);

            if (!is_array($jsonData)) {
                $this->warn("⚠️ Skipping invalid JSON: {$file}");
                continue;
            }

            foreach ($jsonData as $ticketItem) {
                $ticket = $ticketItem['helpdesk_ticket'];
                $a = [
                    sprintf("%s", $ticket['id']),
                    $ticket['requester']['name'] ?? '',
                    $ticket['requester']['email'] ?? '',
                    $ticket['subject'] ?? '',
                    $ticket['description'] ?? '',
                    $ticket['status_name'] ?? '',
                    $ticket['requester']['name'] ?? '', // Ticket Owner same as requester
                    $this->formatDate($ticket['created_at'] ?? ''),
                    $this->formatDate($ticket['updated_at'] ?? ''),
                    '', // Resolution empty
                    $this->formatDate($ticket['due_by'] ?? ''),
                    $ticket['priority_name'] ?? '',
                    '', // Category empty

                    $this->formatDate($ticket['ticket_states']['closed_at'] ?? ''),
                    $ticket['ticket_type'] ?? '',
                ];

                fputcsv($fp, $a);
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
