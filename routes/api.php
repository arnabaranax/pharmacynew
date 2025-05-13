<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\StudentController;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::post('/authenticate', [AuthController::class, 'authenticate']);
Route::post('/validate-security-code', [AuthController::class, 'validateSecurityCode']);
Route::get('/check-status/{user_id}', [StudentController::class, 'checkRedirect']);
Route::prefix('student')->group(function () {
    Route::post('/student-info-update', [StudentController::class, 'studentInfoUpdate']);
    Route::get('/download-receipt/{from_num}', [StudentController::class, 'downloadReceipt']);
    Route::get('/student-details/{from_num}', [StudentController::class, 'studentdetails']);
});

