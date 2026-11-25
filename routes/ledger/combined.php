<?php

// Combined statements of a bus company (E-Ride): head office and branches side by side, and their sum.
// Required inside the /accounting prefix and the ledger. name group of routes/ledger.php.

use Illuminate\Support\Facades\Route;

Route::get('combined', 'App\\Http\\Controllers\\Ledger\\CombinedController@index')->name('combined.index');
