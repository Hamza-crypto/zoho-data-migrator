<?php

namespace App\Jobs;

use App\Http\Controllers\ContactController;
use App\Http\Controllers\TicketController;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessZohoTicket implements ShouldQueue
{
    use Queueable;

    protected $ticket;
    public function __construct(array $ticket)
    {
        $this->ticket = $ticket;
    }

    public function handle(): void
    {
        $ticketController = new TicketController();
        $ticketController->searchTicketByTitle($this->ticket);
    }
}
