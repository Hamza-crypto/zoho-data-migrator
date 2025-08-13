<?php

use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/comment', function () {
    $ticketController = new TicketController();
    $ticketController->createTicketComments('1120359000002814001');
});
