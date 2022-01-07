<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\WeatherController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group([
    'middleware' => 'api',
    'prefix' => 'auth',
], function ($router) {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::get('/user-profile', [AuthController::class, 'userProfile']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'weather',
], function ($router) {
    Route::get('/help', [WeatherController::class, 'help']);
    Route::get('/current', [WeatherController::class, 'current']);
    Route::get('/today', [WeatherController::class, 'today']);
    Route::get('/two-day', [WeatherController::class, 'forecast2']);
    Route::get('/three-day', [WeatherController::class, 'forecast3']);
    Route::post('/set-location', [WeatherController::class, 'setLocation']);
    Route::post('/set-units', [WeatherController::class, 'setUnits']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'help',
], function ($router) {
    Route::get('/weather', [WeatherController::class, 'help']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'coffee',
], function ($router) {
    Route::post('/brew', [\App\Http\Controllers\CoffeeController::class, 'brew']);
});
