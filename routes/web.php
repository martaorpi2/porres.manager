<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Admin\MarketRateCrudController;

Route::get('/', function () {
    return redirect('/admin');
});

/*Route::get('/login', function () {
    return 'Login aquí';
});*/
Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('login', [LoginController::class, 'login']);

// Ruta para generar PDF de cotizaciones
Route::get('admin/market-rate/{id}/pdf', [MarketRateCrudController::class, 'generatePdf'])->name('market-rate.pdf');

// Archivos adjuntos de cotización (nombre de ruta usado en purchase-request show; registrado en web.php para que exista con el stack de rutas de la app)
Route::get(config('backpack.base.route_prefix', 'admin').'/market-rate/{id}/uploaded-file/{index?}', [MarketRateCrudController::class, 'showUploadedFile'])
    ->middleware(['admin'])
    ->name('market-rate.uploaded-file');

// Ruta API para obtener productos (para el selector dinámico)
Route::get('admin/api/products', function() {
    $products = \App\Models\Product::select('id', 'name', 'description', 'unit_measurement')->get();
    return response()->json($products);
})->middleware('auth');