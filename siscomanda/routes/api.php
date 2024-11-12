<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PrintController;

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


Route::controller(PrintController::class)->group(function (){
    Route::post('/pruebita','index');
    Route::post('/testingPrinterConnection','testingPrinterConnection')->name('printer_testing');
    Route::post('/ticketBoletadeVentaApi','ticketBoletadeVentaApi');
    Route::post('/ticketComandaApi','ticketComandaApi');
    Route::post('/ticketVentaSalon','ticketVentaSalon');
    Route::post('/ticketCierreApi','ticketCierreApi');
    Route::post('/ticketPaloteoApi','ticketPaloteoApi');
    Route::post('/ticketInventarioApi','ticketInventarioApi');
    Route::post('/ticketMovimientoApi','ticketMovimientoApi');
});