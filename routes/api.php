<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

# This is the version 1 definition of all routes/endpoints
Route::group(['prefix' => 'v1'], function () {

    # This manages all the internal calls and implementation of the endpoints
    Route::group(['middleware' => ['validate.headers']], function () {

        Route::group(['prefix' => 'onboarding'], function () {
            Route::post('apply', [UssdController::class, 'notify']);
            Route::post('repay', [UssdController::class, 'testSessionCache']);
        });
    });
});
