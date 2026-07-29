<?php

use Illuminate\Support\Facades\Route;
use Modules\AiModule\Http\Controllers\Web\Admin\BlogController;
use Modules\ZoneManagement\Http\Controllers\Web\Admin\ZoneController;

//New Route Mamun
Route::group(['prefix' => 'admin', 'as' => 'admin.', 'middleware' => 'admin'], function () {
    Route::group(['prefix' => 'ai', 'as' => 'ai.'], function () {
        Route::group(['prefix' => 'blog', 'as' => 'blog.'], function () {
            Route::controller(BlogController::class)->group(function(){
                Route::get('generate-title', 'generateTitle')->name('generate-title');
                Route::get('generate-description', 'generateDescription')->name('generate-description');
                Route::get('generate-seo', 'generateSeo')->name('generate-seo');
                Route::get('generate-title-suggestion', 'generateTitleSuggestion')->name('generate-title-suggestion');
                Route::post('generate-title-from-contents', 'generateTitleFromContents')->name('generate-title-from-contents');
            });
        });
    });
});
