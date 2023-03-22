<?php

use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => '{query}',
], function () {
    Route::get('available', 'DomainsController@availability')->name('available');
    Route::get('transferable', 'DomainsController@transferable')->name('transferable');
    Route::get('whois/{?server}', 'DomainsController@whois')->name('whois');
    Route::get('whois', 'DomainsController@whois')->name('whois');
    Route::get('suggest', 'DomainsController@suggest')->name('suggest');
});
