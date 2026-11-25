<?php

// Counter tills (E-Ride, bus books): each counter agent's cash, counted through the review.
// Required inside the /accounting prefix and the ledger. name group of routes/ledger.php.

use Illuminate\Support\Facades\Route;

Route::get('tills', 'App\\Http\\Controllers\\Ledger\\TillController@index')->name('tills.index');
Route::post('tills/count', 'App\\Http\\Controllers\\Ledger\\TillController@count')->name('tills.count')->middleware('ledger.review');
Route::post('tills/{id}/void', 'App\\Http\\Controllers\\Ledger\\TillController@void')->name('tills.void')->middleware('ledger.review')->whereNumber('id');
