<?php

namespace App\Console\Commands;

use App\Http\Controllers\ContactController;
use App\Http\Controllers\TicketController;
use App\Jobs\ProcessZohoContact;
use App\Models\Article;
use App\Models\CategoryMapping;
use App\Services\ZohoApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ImportContactsFromJson extends Command
{
    protected $signature = 'import:contacts {filePath}';
    protected $description = 'Import all contacts from a JSON file and log them in the contacts table';

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

        $contactController = new ContactController();

        foreach ($jsonData as $ticketItem) {
            $requester  = $ticketItem['helpdesk_ticket']['requester'];

            if ($requester) {
                ProcessZohoContact::dispatch($requester);
            }
        }

        $this->info("✅ Imported $importedCount articles successfully.");
    }
}

