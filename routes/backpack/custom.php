<?php

use Illuminate\Support\Facades\Route;

// --------------------------
// Custom Backpack Routes
// --------------------------
// This route file is loaded automatically by Backpack\CRUD.
// Routes you generate using Backpack\Generators will be placed here.

Route::group([
    'prefix' => config('backpack.base.route_prefix', 'admin'),
    'middleware' => array_merge(
        (array) config('backpack.base.web_middleware', 'web'),
        (array) config('backpack.base.middleware_key', 'admin')
    ),
    'namespace' => 'App\Http\Controllers\Admin',
], function () { // custom admin routes
    Route::crud('patient', 'PatientCrudController');
    Route::crud('analysis-order', 'AnalysisOrderCrudController');
    Route::crud('determination', 'DeterminationCrudController');
    Route::crud('doctor', 'DoctorCrudController');
    Route::crud('result', 'ResultCrudController');
    Route::crud('sample', 'SampleCrudController');
    Route::crud('social-work', 'SocialWorkCrudController');
}); // this should be the absolute last line of this file

/**
 * DO NOT ADD ANYTHING HERE.
 */
