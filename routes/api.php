<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProviderController;
use App\Http\Controllers\ServiceRequestController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\DeliveryOrderController;
use App\Http\Controllers\ProviderLocationController;
use App\Http\Controllers\GeoController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\MpesaController;


Route::post('register', [AuthController::class, 'register']);
Route::post('login',    [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {


    // User ↔ Provider Profile
    Route::get('me',           [AuthController::class, 'me']);
    Route::patch('me',         [AuthController::class, 'updateProfile']);
    Route::post('logout',      [AuthController::class, 'logout']);

    // Providers
    Route::get('providers/{id}', [ProviderController::class, 'show']);
    Route::get('providers/nearby',  [ProviderController::class, 'nearby']);
    Route::post('providers/{id}/docs', [ProviderController::class, 'uploadDocs']);
    Route::patch('providers/{id}', [ProviderController::class, 'update']);
    Route::post('provider/location', [ProviderLocationController::class, 'updateLocation']);

    // Ride Requests
    Route::get('requests/{ride}', [ServiceRequestController::class, 'show']);
    Route::get('requests', [ServiceRequestController::class, 'index']);
    Route::post('requests',  [ServiceRequestController::class, 'requestRide']);
    Route::patch('requests/{ride}', [ServiceRequestController::class, 'updateStatus']);

    // Deliveries
    Route::get('deliveries',            [DeliveryOrderController::class,'index']);
    Route::post('deliveries',           [DeliveryOrderController::class,'create']);
    Route::patch('deliveries/{id}',     [DeliveryOrderController::class,'updateStatus']);

    // Ratings
    Route::post('ratings',      [RatingController::class, 'store']);

    // Notifications
    Route::get('notifications', [NotificationController::class,'index']);

 Route::get('wallet',              [WalletController::class,'overview']);       // balances
  Route::get('wallet/transactions', [WalletController::class,'transactions']);   // paginated
  Route::post('wallet/savings',     [WalletController::class,'modifySavings']);  // deposit/withdraw
  Route::post('wallet/loan',        [WalletController::class,'requestLoan']);    // new loan
  Route::post('wallet/loan/repay',  [WalletController::class,'repayLoan']);      // repayment
  Route::post('wallet/fund',       [MpesaController::class,'fund']);
  Route::post('mpesa/callback',    [MpesaController::class,'callback']);
});

    //public API endpoints
    Route::get('/reverse-geocode', [GeoController::class, 'reverse']);
