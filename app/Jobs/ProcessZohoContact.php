<?php

namespace App\Jobs;

use App\Http\Controllers\ContactController;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessZohoContact implements ShouldQueue
{
    use Queueable;

    protected $requester;

    public function __construct(array $requester)
    {
        $this->requester = $requester;
    }


    public function handle(): void
    {
        $contactController = new ContactController();
        $contactController->handleContact($this->requester);
    }
}
