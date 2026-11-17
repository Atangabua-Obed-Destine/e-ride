<?php

use Illuminate\Support\Facades\Route;
use Modules\AssistantManagement\Http\Controllers\Web\Admin\AssistantAdminController;

Route::group(['prefix' => 'admin/assistant', 'as' => 'admin.assistant.', 'middleware' => 'admin'], function () {
    Route::get('/', [AssistantAdminController::class, 'index'])->name('index');
    Route::post('settings', [AssistantAdminController::class, 'settings'])->name('settings');
    Route::post('test/{provider}', [AssistantAdminController::class, 'test'])->name('test');
    Route::post('entry/{entry?}', [AssistantAdminController::class, 'saveEntry'])->name('entry.save');
    Route::delete('entry/{entry}', [AssistantAdminController::class, 'deleteEntry'])->name('entry.delete');
});
