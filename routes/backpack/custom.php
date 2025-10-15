<?php

use Illuminate\Support\Facades\Route;

// --------------------------
// Custom Backpack Routes
// --------------------------
// This route file is loaded automatically by Backpack\CRUD.
// Routes you generate using Backpack\Generators will be placed here.

Route::group([
    'prefix' => config('backpack.base.route_prefix', 'admin'),
    /*'middleware' => array_merge(
        (array) config('backpack.base.web_middleware', 'web'),
        (array) config('backpack.base.middleware_key', 'admin')
    ),*/
    'middleware' => ['web', 'admin'],
    'namespace' => 'App\Http\Controllers\Admin',
], function () { // custom admin routes
    Route::crud('supplier', 'SupplierCrudController');
    Route::crud('suppliers-heading', 'SuppliersHeadingCrudController');
    Route::crud('purchase-order', 'PurchaseOrderCrudController');
    Route::get('purchase-order/{id}/pdf', 'PurchaseOrderCrudController@generatePdf')->name('purchase-order.pdf');
    Route::crud('payment-order', 'PaymentOrderCrudController');
    Route::get('payment-order/{id}/pdf', 'PaymentOrderCrudController@generatePdf')->name('payment-order.pdf');
    Route::crud('sector', 'SectorCrudController');
    Route::crud('product', 'ProductCrudController');
    Route::crud('category', 'CategoryCrudController');
    Route::crud('location', 'LocationCrudController');
    Route::crud('stock-level', 'StockLevelCrudController');
    Route::crud('inventory-movement', 'InventoryMovementCrudController');
    Route::crud('application', 'ApplicationCrudController');
    Route::get('api/productos', 'ApplicationCrudController@getProductos')->name('api.productos');
    Route::crud('request-detail', 'RequestDetailCrudController');
    Route::crud('market-rate', 'MarketRateCrudController');
    Route::crud('quote-detail', 'QuoteDetailCrudController');
    Route::crud('reception', 'ReceptionCrudController');
    Route::crud('devolution', 'DevolutionCrudController');
    Route::crud('responsibility-area', 'ResponsibilityAreaCrudController');
    Route::crud('purchase-request', 'PurchaseRequestCrudController');
    Route::get('purchase-request/{id}/comparative-excel', 'PurchaseRequestCrudController@generateComparativeExcel')->name('purchase-request.comparative-excel');
    Route::get('purchase-request/{id}/select-market-rate/{marketRateId}', 'PurchaseRequestCrudController@showSelectMarketRateForm')->name('purchase-request.show-select-market-rate');
    Route::post('purchase-request/{id}/select-market-rate/{marketRateId}', 'PurchaseRequestCrudController@storeMarketRateSelection')->name('purchase-request.store-market-rate-selection');
    Route::post('purchase-request/{id}/generate-purchase-order', 'PurchaseRequestCrudController@generatePurchaseOrder')->name('purchase-request.generate-purchase-order');
    Route::crud('general-request', 'GeneralRequestCrudController');
    Route::get('general-request-converted', 'GeneralRequestCrudController@showConverted')->name('general-request.converted');
}); // this should be the absolute last line of this file

/**
 * DO NOT ADD ANYTHING HERE.
 */
