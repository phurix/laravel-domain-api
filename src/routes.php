<?php

use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => '{query}',
], function () {
    Route::get('available', 'Controller@availability')->name('available');
    Route::get('transferable', 'Controller@transferable')->name('transferable');
    Route::get('whois/{?server}', 'Controller@whois')->name('whois');
    Route::get('whois', 'Controller@whois')->name('whois');
    Route::get('suggest', 'Controller@suggest')->name('suggest');
});
