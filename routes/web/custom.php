<?php

/*
|--------------------------------------------------------------------------
| CUSTOM ROUTES
|--------------------------------------------------------------------------
|
| This file contains custom routes added for local/organization-specific
| modifications to Snipe-IT. These routes are separate from the core
| Snipe-IT routes to:
|
| 1. Avoid merge conflicts during version upgrades
| 2. Make it clear which routes are custom additions
| 3. Make it easier to maintain
|
| NOTE: This file is loaded by RouteServiceProvider and should be excluded
| from version control updates to the core application.
|
*/

use App\Http\Controllers\MtlDocumentController;

Route::group(['middleware' => 'auth'], function () {


    /*
    |--------------------------------------------------------------------------
    | MTL Document Generation Routes
    |--------------------------------------------------------------------------
    |
    | Routes for generating MTL (Material Transfer List) documents in Latvian.
    | These documents track the transfer of assets between users.
    |
    */

    // Generate MTL document for giving away items (Izsniegšanas lapa)
    Route::get('/users/{userId}/mtl/giving', [MtlDocumentController::class, 'generateGiving'])
        ->name('users.mtl.giving');

    // Generate MTL document for receiving items (Saņemšanas lapa)
    Route::get('/users/{userId}/mtl/receiving', [MtlDocumentController::class, 'generateReceiving'])
        ->name('users.mtl.receiving');

});
