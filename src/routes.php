<?php

use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => '{query}',
], function () {
    Route::get('available', 'DomainsController@availability');
    Route::get('transferable', 'DomainsController@transferable');
    Route::get('whois', 'DomainsController@whois');
    Route::get('whois/{?server}', 'DomainsController@whois');
    Route::get('suggest', 'DomainsController@suggest');
});
