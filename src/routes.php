<?php

use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => '{query}',
], function () {
    Route::get('available', 'Controller@availability')->name('available');
    Route::get('transferable', 'Controller@transferable')->name('transferable');
    Route::get('whois/{?server}', 'Controller@whois')->name('whois');
    Route::get('whois', 'Controller@whois')->name('whois');
    Route::get('rdap/{?server}', 'Controller@rdap')->name('rdap');
    Route::get('rdap', 'Controller@rdap')->name('rdap');
    Route::get('lookup', 'Controller@lookup')->name('lookup');
    Route::get('suggest', 'Controller@suggest')->name('suggest');
});
