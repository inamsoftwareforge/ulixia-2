<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\ServiceRequestController;
use App\Http\Controllers\JobListController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\ServiceProviderController;
use App\Http\Controllers\Admin\MissionAdminController;

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
// routes/web.php
Route::get('/categories', [CategoryController::class, 'fetchMainCategories']);
Route::post('/provider/save-categories', [ServiceProviderController::class, 'updateProviderCategories']);
Route::get('/categories/{parentId}/subcategories', [CategoryController::class, 'fetchSubCategories']);
Route::get('/categories/{parentId}/children', [CategoryController::class, 'fetchChildCategories']);
Route::get('/providers/map', [MapController::class, 'getProviders']);

//Servic Provider Action 
Route::post('/provider/jobs/start', [JobListController::class, 'startMission']);
Route::post('/provider/jobs/resolve', [JobListController::class, 'resolveMission']);
Route::post('/provider/jobs/confirm-delivery', [JobListController::class, 'confirmDelivery']);


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/world-map', [UserManagementController::class, 'getProviders'])->name('w-map-view');

    
// Account information routes

//Cancel Mission
Route::post('/mission/cancel', [ServiceRequestController::class, 'cancelMissionRequest']);
Route::post('/mission/cancel/by-provider', [ServiceRequestController::class, 'providerCancelMisssion']);

//Get Filtered Transactions
Route::get('transactions/filter', [TransactionController::class, 'filterTransactions']);

// Admin World Map View
Route::get('/admin/world-map', [UserManagementController::class, 'adminWorldMap'])->name('admin.w-map-view');

// Toggle provider visibility (AJAX)
Route::post('/admin/provider/{id}/toggle-visibility', [UserManagementController::class, 'toggleProviderVisibility'])->name('admin.provider.toggle-visibility');
Route::post('/admin/provider/{id}/update-coords', [UserManagementController::class, 'updateProviderCoords'])->name('admin.provider.update-coords');
Route::post('/admin/provider/{id}/toggle-pin', [\App\Http\Controllers\Admin\UserManagementController::class, 'toggleProviderPin'])->name('admin.provider.toggle-pin');
Route::patch('/admin/users/{id}/edit-profile', [UserManagementController::class, 'editUserProfile'])->name('admin.users.edit-profile');
Route::patch('/admin/providers/{id}/edit-profile', [UserManagementController::class, 'editProviderProfile'])->name('admin.providers.edit-profile');

Route::get('/admin/missions', [MissionAdminController::class, 'apiList']);
Route::get('/admin/missions/{id}', [MissionAdminController::class, 'apiShow']);
