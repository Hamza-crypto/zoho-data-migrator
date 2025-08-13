<?php

namespace App\Console\Commands;

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

class ImportTicketsFromJson extends Command
{
    protected $signature = 'import:tickets {filePath}';
    protected $description = 'Import all tickets from a JSON file and log them in the tickets table';

    public function handle()
    {
        $filePath = $this->argument('filePath');

        if (!File::exists($filePath)) {
            $this->error("File not found at path: $filePath");
            return;
        }

        $jsonData = json_decode(File::get($filePath), true);

        if (!is_array($jsonData)) {
            $this->error("Invalid JSON structure");
            return;
        }

        $importedCount = 0;

        foreach ($jsonData as $ticketItem) {
            $ticket = $ticketItem['helpdesk_ticket'];
            ProcessZohoTicket::dispatch($ticket);
        }

        $this->info("✅ Imported $importedCount articles successfully.");
    }
}

