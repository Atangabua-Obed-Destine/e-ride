<?php

use Illuminate\Support\Facades\Route;
use Modules\BlogManagement\Http\Controllers\Web\Admin\BlogCategoryController;
use Modules\BlogManagement\Http\Controllers\Web\Admin\BlogController;
use Modules\BlogManagement\Http\Controllers\Web\Admin\BlogDraftController;
use Modules\BlogManagement\Http\Controllers\Web\Admin\BlogSettingController;

//New Route
Route::group(['prefix' => 'admin', 'as' => 'admin.', 'middleware' => 'admin'], function () {
    Route::group(['prefix' => 'blog', 'as' => 'blog.'], function () {
        Route::controller(BlogSettingController::class)->group(function () {
            Route::get('update-settings', 'updateSettings')->name('update-settings');
            Route::get('/', 'blogPage')->name('index');
            Route::post('update-intro', 'updateBlogPageIntro')->name('update-intro');

            Route::group(['prefix' => 'app-download-setup', 'as' => 'app-download-setup.'], function () {
                Route::get('/', 'appDownloadSetup')->name('index');
                Route::post('update-app-contents', 'updateAppContents')->name('update-app-contents');
            });
            Route::group(['prefix' => 'priority-setup', 'as' => 'priority-setup.'], function () {
                Route::get('/', 'prioritySetup')->name('index');
                Route::post('update', 'updatePrioritySetup')->name('update');
            });
        });
        Route::controller(BlogController::class)->group(function () {
            Route::get('create', 'create')->name('create');
            Route::post('store', 'store')->name('store');
            Route::get('edit/{id}', 'edit')->name('edit');
            Route::put('update/{id}', 'update')->name('update');
            Route::get('status', 'status')->name('status');
            Route::delete('destroy/{id}', 'destroy')->name('destroy');
            Route::get('export', 'export')->name('export');
            Route::get('active-categories', 'activeCategories')->name('active-categories');
            Route::post('upload-summernote-image', 'uploadSummernoteImage')->name('upload-summernote-image');
            Route::post('delete-summernote-image', 'deleteSummernoteImage')->name('delete-summernote-image');
        });
        Route::controller(BlogDraftController::class)->group(function () {
            Route::group(['prefix' => 'draft', 'as' => 'draft.'], function () {
                Route::get('edit/{id}', 'edit')->name('edit');
                Route::put('update/{id}', 'update')->name('update');
            });
        });
        Route::controller(BlogCategoryController::class)->group(function () {
            Route::group(['prefix' => 'category', 'as' => 'category.'], function () {
                Route::get('index', 'index')->name('index');
                Route::post('store', 'store')->name('store');
                Route::delete('destroy/{id}', 'destroy')->name('destroy');
                Route::get('{id}/status', 'status')->name('status');
            });
        });
    });
});
