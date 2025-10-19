<?php

/*
|--------------------------------------------------------------------------
| CUSTOM ROUTES - LOCAL MODIFICATIONS
|--------------------------------------------------------------------------
|
| This file contains custom routes added for local/organization-specific
| modifications to Snipe-IT. These routes are separate from the core
| Snipe-IT routes to:
|
| 1. Avoid merge conflicts during version upgrades
| 2. Make it clear which routes are custom additions
| 3. Make it easier to maintain and document custom features
|
| NOTE: This file is loaded by RouteServiceProvider and should be excluded
| from version control updates to the core application.
|
*/

use App\Http\Controllers\MtlDocumentController;

/*
|--------------------------------------------------------------------------
| MTL Document Generation Routes
|--------------------------------------------------------------------------
|
| Routes for generating MTL (Material Transfer List) documents in Latvian.
| These documents track the transfer of assets between users.
|
*/

Route::group(['middleware' => 'auth'], function () {

    // Generate MTL document for a user
    // GET /users/{userId}/mtl
    Route::get('/users/{userId}/mtl', [MtlDocumentController::class, 'generate'])
        ->name('users.mtl');

});
