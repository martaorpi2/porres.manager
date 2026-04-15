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
    Route::get('supplier/export/excel', 'SupplierCrudController@exportExcel')->name('supplier.export.excel');
    Route::get('supplier/export/pdf', 'SupplierCrudController@exportPdf')->name('supplier.export.pdf');
    Route::crud('supplier-rating', 'SupplierRatingCrudController');
    Route::crud('suppliers-heading', 'SuppliersHeadingCrudController');
    Route::crud('purchase-order', 'PurchaseOrderCrudController');
    Route::get('purchase-order/{id}/pdf', 'PurchaseOrderCrudController@generatePdf')->name('purchase-order.pdf');
    Route::crud('payment-order', 'PaymentOrderCrudController');
    Route::get('payment-order/{id}/pdf', 'PaymentOrderCrudController@generatePdf')->name('payment-order.pdf');
    Route::get('payment-order/{id}/anular', 'PaymentOrderCrudController@showAnularForm')->name('payment-order.anular');
    Route::post('payment-order/{id}/anular', 'PaymentOrderCrudController@anular')->name('payment-order.anular.store');
    Route::crud('sector', 'SectorCrudController');
    Route::crud('product', 'ProductCrudController');
    Route::get('product/export/excel', 'ProductCrudController@exportExcel')->name('product.export.excel');
    Route::get('product/export/pdf', 'ProductCrudController@exportPdf')->name('product.export.pdf');
    Route::crud('category', 'CategoryCrudController');
    Route::crud('location', 'LocationCrudController');
    Route::crud('stock-level', 'StockLevelCrudController');
    Route::crud('inventory-movement', 'InventoryMovementCrudController');
    Route::crud('application', 'ApplicationCrudController');
    Route::get('api/productos', 'ApplicationCrudController@getProductos')->name('api.productos');
    Route::get('api/productos-por-area', 'PurchaseRequestCrudController@getProductosByArea')->name('api.productos-por-area');
    Route::crud('request-detail', 'RequestDetailCrudController');
    // Antes del CRUD: ruta fija para no depender del orden de registro ni de backpack_url().
    Route::get('market-rate/{id}/uploaded-file/{index?}', 'MarketRateCrudController@showUploadedFile')->name('market-rate.uploaded-file');
    Route::crud('market-rate', 'MarketRateCrudController');
    Route::crud('quote-detail', 'QuoteDetailCrudController');
    Route::crud('reception', 'ReceptionCrudController');
    Route::get('reception/{id}/pdf', 'ReceptionCrudController@generatePdf')->name('reception.pdf');
    Route::crud('devolution', 'DevolutionCrudController');
    Route::get('devolution/{id}/pdf', 'DevolutionCrudController@generatePdf')->name('devolution.pdf');
    Route::crud('responsibility-area', 'ResponsibilityAreaCrudController');
    // Rutas fijas de solicitud de compra antes del CRUD (mismo criterio que market-rate: orden de registro).
    Route::get('purchase-request/{id}/comparative-excel', 'PurchaseRequestCrudController@generateComparativeExcel')->name('purchase-request.comparative-excel');
    Route::get('purchase-request/{id}/select-market-rate/{marketRateId}', 'PurchaseRequestCrudController@showSelectMarketRateForm')->name('purchase-request.show-select-market-rate');
    Route::post('purchase-request/{id}/select-market-rate/{marketRateId}', 'PurchaseRequestCrudController@storeMarketRateSelection')->name('purchase-request.store-market-rate-selection');
    Route::post('purchase-request/{id}/toggle-market-rate/{marketRateId}', 'PurchaseRequestCrudController@toggleMarketRateSelection')->name('purchase-request.toggle-market-rate');
    Route::get('purchase-request/{id}/suggest-supplier', 'PurchaseRequestCrudController@showSuggestSupplierForm')->name('purchase-request.suggest-supplier');
    Route::post('purchase-request/{id}/suggest-supplier', 'PurchaseRequestCrudController@storeSupplierSuggestion')->name('purchase-request.store-supplier-suggestion');
    Route::post('purchase-request/{id}/assign-quotations', 'PurchaseRequestCrudController@assignQuotations')->name('purchase-request.assign-quotations');
    Route::post('purchase-request/{id}/generate-purchase-order', 'PurchaseRequestCrudController@generatePurchaseOrder')->name('purchase-request.generate-purchase-order');
    Route::post('purchase-request/{id}/approve', 'PurchaseRequestCrudController@approvePurchaseRequest')->name('purchase-request.approve');
    Route::post('purchase-request/{id}/reject', 'PurchaseRequestCrudController@rejectPurchaseRequest')->name('purchase-request.reject');
    Route::post('purchase-request/{id}/mark-direct-purchase', 'PurchaseRequestCrudController@markAsDirectPurchase')->name('purchase-request.mark-direct-purchase');
    Route::post('purchase-request/{id}/request-direct-purchase-authorization', 'PurchaseRequestCrudController@requestDirectPurchaseAuthorization')->name('purchase-request.request-direct-purchase-authorization');
    Route::post('purchase-request/{id}/approve-direct-purchase', 'PurchaseRequestCrudController@approveDirectPurchase')->name('purchase-request.approve-direct-purchase');
    Route::post('purchase-request/{id}/reject-direct-purchase-authorization', 'PurchaseRequestCrudController@rejectDirectPurchaseAuthorization')->name('purchase-request.reject-direct-purchase-authorization');
    Route::get('api/purchase-request/{id}', 'PurchaseRequestCrudController@getPurchaseRequestData')->name('api.purchase-request.data');
    Route::crud('purchase-request', 'PurchaseRequestCrudController');
    Route::get('api/suppliers', 'PurchaseRequestCrudController@getSuppliers')->name('api.suppliers');
    Route::crud('general-request', 'GeneralRequestCrudController');
    Route::get('general-request-converted', 'GeneralRequestCrudController@showConverted')->name('general-request.converted');
    Route::post('general-request/{id}/approve-by-analyst', 'GeneralRequestCrudController@approveByAnalyst')->name('general-request.approve-by-analyst');
    Route::post('general-request/{id}/reject-by-analyst', 'GeneralRequestCrudController@rejectByAnalyst')->name('general-request.reject-by-analyst');
    
    // Product Assignment Routes
    Route::crud('product-assignment', 'ProductAssignmentController');
    Route::get('product-assignment/{generalRequest}/assign', 'ProductAssignmentController@showAssignment')->name('product-assignment.show-assignment');
    Route::post('product-assignment/{generalRequest}/assign', 'ProductAssignmentController@assign')->name('product-assignment.assign');
    Route::get('product-assignment/get-stock', 'ProductAssignmentController@getStock')->name('product-assignment.get-stock');
    
    // User Management
    Route::crud('user', 'UserCrudController');
    
    // Role Management
    Route::crud('role', 'RoleCrudController');
    
    // Permission Management
    Route::crud('permission', 'PermissionCrudController');
    
    // Dashboard custom route
    Route::get('dashboard', 'DashboardController@index')->name('dashboard');
    Route::crud('delivery', 'DeliveryCrudController');
    Route::get('delivery/{id}/pdf', 'DeliveryCrudController@generatePdf')->name('delivery.pdf');
}); // this should be the absolute last line of this file

/**
 * DO NOT ADD ANYTHING HERE.
 */
