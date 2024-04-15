<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\LogController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Admin\GigsController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\ClientController;
use App\Http\Controllers\Api\Admin\ProductController;
use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\LocationController;
use App\Http\Controllers\Api\Admin\AssignGigController;
use App\Http\Controllers\Api\Admin\SchedulesController;
use App\Http\Controllers\Api\Admin\PermissionController;
use App\Http\Controllers\Api\Auth\ResetPasswordController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;

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

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

// Route::prefix('v1')->group(function () {
//     

//     Route::prefix('admin')->group(function() {
//         Route::prefix('role')->group(function() {
//             Route::get('all-roles', [RoleController::class, 'view']);
//         });
//     });
// });

Route::prefix('v1')->group(function () {
    Route::middleware('api')->group(function () {
        Route::prefix('log')->group(function(){
            Route::get('all-log', [LogController::class, 'logs']);
        });
        Route::prefix('admin')->middleware('auth:api')->group(function() {
            Route::prefix('role')->group(function() {
                Route::get('all-roles', [RoleController::class, 'index']);
                Route::post('create-role', [RoleController::class, 'store']);
                Route::put('update-role', [RoleController::class, 'update']);
                Route::delete('delete-role', [RoleController::class, 'destroy']);
                Route::put('give-permissions', [RoleController::class, 'givePermissionToRole']);
            });
            Route::prefix('permission')->group(function() {
                Route::get('all-permissions', [PermissionController::class, 'index']);
                Route::post('create-permission', [PermissionController::class, 'store']);
                Route::put('update-permission', [PermissionController::class, 'update']);
                Route::delete('delete-permission', [PermissionController::class, 'destroy']);
            });
            Route::prefix('location')->group(function() {
                Route::get('all-locations', [LocationController::class, 'index']);
                Route::post('create-location', [LocationController::class, 'store']);
                Route::put('update-location', [LocationController::class, 'update']);
                Route::delete('delete-location', [LocationController::class, 'destroy']);
            });
            Route::prefix('user')->group(function() {
                Route::get('all-users', [UserController::class, 'index']);
                Route::get('single-user', [UserController::class, 'show']);
                Route::post('create-user', [UserController::class, 'store']);
                Route::put('update-user', [UserController::class, 'update']);
                Route::delete('delete-user', [UserController::class, 'destroy']);
                Route::post('reset-temporary-password', [UserController::class, 'reset_temporary'])->name('temporary.reset');
                Route::put('assign-role-to-user', [UserController::class, 'assignRole']);
                Route::get('fetch-roles-to-user', [UserController::class, 'fetchRoles']);
            });
            Route::prefix('client')->group(function() {
                Route::get('all-clients', [ClientController::class, 'index']);
                Route::get('single-client', [ClientController::class, 'show']);
                Route::post('create-client', [ClientController::class, 'store']);
                Route::post('update-client', [ClientController::class, 'update']);
                Route::delete('delete-client', [ClientController::class, 'destroy']);
            });
            Route::prefix('gig')->group(function() {
                Route::get('all-gigs', [GigsController::class, 'index']);
                Route::get('single-gig', [GigsController::class, 'show']);
                Route::post('create-gig', [GigsController::class, 'store']);
                Route::put('update-gig', [GigsController::class, 'update']);
                Route::delete('delete-gig', [GigsController::class, 'destroy']);
            });
            Route::prefix('schedule')->group(function() {
                Route::get('all-schedules', [SchedulesController::class, 'index']);
                Route::get('single-schedule', [SchedulesController::class, 'show']);
                Route::post('create-schedule', [SchedulesController::class, 'store']);
                Route::put('update-schedule', [SchedulesController::class, 'update']);
                Route::delete('delete-schedule', [SchedulesController::class, 'destroy']);
            });
            Route::prefix('assign_gig')->group(function() {
                Route::get('all-assign_gigs', [AssignGigController::class, 'index']);
                Route::get('single-assign_gig', [AssignGigController::class, 'show']);
                Route::post('create-assign_gig', [AssignGigController::class, 'store']);
                Route::put('update-assign_gig', [AssignGigController::class, 'update']);
                Route::delete('delete-assign_gig', [AssignGigController::class, 'destroy']);
            });
            Route::prefix('category')->group(function() {
                Route::get('all-categories', [CategoryController::class, 'index']);
                Route::get('single-category', [CategoryController::class, 'show']);
                Route::post('create-category', [CategoryController::class, 'store']);
                Route::put('update-category', [CategoryController::class, 'update']);
                Route::delete('delete-category', [CategoryController::class, 'destroy']);
            });
            Route::prefix('product')->group(function() {
                Route::get('all-products', [ProductController::class, 'index']);
                Route::get('single-product', [ProductController::class, 'show']);
                Route::post('create-product', [ProductController::class, 'store']);
                Route::put('update-product', [ProductController::class, 'update']);
                Route::delete('delete-product', [ProductController::class, 'destroy']);
            });
        });
        //Authentication Route
        Route::prefix('user')->group(function () {
            Route::any('login', [AuthController::class, 'login'])->name('login');
            Route::get('/email-verification', [AuthController::class, 'email_verify']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/profile', [AuthController::class, 'profile']);
            Route::post('password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->middleware('throttle:3,1');
            Route::post('password/reset', [ResetPasswordController::class,'reset'])->middleware('throttle:3,1')->name('password.reset');
            Route::put('change-password', [AuthController::class,'changePassword'])->middleware('throttle:3,1')->name('password.change');
        });
        //Authentication Route Ends
        
        
    });
});
