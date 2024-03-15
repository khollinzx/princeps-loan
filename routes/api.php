<?php

use App\Http\Controllers\Auth\AuthUserController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\UserLoanController;
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

    Route::get('welcome', [Controller::class, 'welcome']);
    # This manages all the internal calls and implementation of the endpoints
    Route::group(['middleware' => ['validate.headers']], function () {

        Route::group(['prefix' => 'onboarding'], function () {
            Route::post('login', [AuthUserController::class, 'login']);
            Route::post('register', [AuthUserController::class, 'register']);
        });

        Route::group(['middleware' => ['manage.access']], function () {
            Route::group(['middleware' => ['auth:api']], function () {
                Route::group(['prefix' => 'users'], function () {
                    Route::get('details', [UserLoanController::class, 'getUserDetails']);
                    Route::post('apply', [UserLoanController::class, 'applyForLoan']);
                    Route::post('repay', [UserLoanController::class, 'repayLoan']);
                });
            });
        });
    });
});
