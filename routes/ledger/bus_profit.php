<?php

// Profit by bus, trip or route (E-Ride, bus books), under Accounting Reports.
// Required inside the /accounting prefix and the ledger. name group of routes/ledger.php.

use Illuminate\Support\Facades\Route;

Route::get('accounting-reports/bus-profit', 'App\\Http\\Controllers\\Ledger\\BusProfitController@index')->name('bus-profit.index');
