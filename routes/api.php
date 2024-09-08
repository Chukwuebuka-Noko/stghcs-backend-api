<?php

use App\Models\GigType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\LogController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Admin\GigsController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\ClientController;
use App\Http\Controllers\Api\User\UserGigController;
use App\Http\Controllers\Api\Admin\GigTypeController;
use App\Http\Controllers\Api\Admin\ProductController;
use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\LocationController;
use App\Http\Controllers\Api\User\TimeSheetController;
use App\Http\Controllers\Api\Admin\AssignGigController;
use App\Http\Controllers\Api\Admin\SchedulesController;
use App\Http\Controllers\Api\LeadershipBoardController;
use App\Http\Controllers\Api\ExportsController;
use App\Http\Controllers\Api\User\UserRewardController;
use App\Http\Controllers\Api\Admin\PermissionController;
use App\Http\Controllers\Api\Auth\ResetPasswordController;
use App\Http\Controllers\Api\Manager\ManagerGigController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Manager\ManagerTaskController;
use App\Http\Controllers\Api\Manager\ManagerUserController;
use App\Http\Controllers\Api\User\IncidentReportController;
use App\Http\Controllers\Api\User\ProgressReportController;
use App\Http\Controllers\Api\User\WeeklySignOffController;
use App\Http\Controllers\Api\Manager\ManagerClientController;
use App\Http\Controllers\Api\Manager\ManagerAssignGigController;
use App\Http\Controllers\Api\Manager\ManagerDashboardController;
use App\Http\Controllers\Api\Manager\ManagerTimeSheetController;
use App\Http\Controllers\Api\Manager\ManagerWeeklySignOffController;
use App\Http\Controllers\Api\Supervisor\SupervisorGigController;
use App\Http\Controllers\Api\Supervisor\SupervisorUserController;
use App\Http\Controllers\Api\Supervisor\SupervisorDswUserGigController;
use App\Http\Controllers\Api\Supervisor\SupervisorClientController;
use App\Http\Controllers\Api\Supervisor\SupervisorDswTimeSheetController;
use App\Http\Controllers\Api\Supervisor\SupervisorDswUserRewardController;
use App\Http\Controllers\Api\Supervisor\SupervisorAssignGigController;
use App\Http\Controllers\Api\Supervisor\SupervisorDashboardController;
use App\Http\Controllers\Api\Supervisor\SupervisorTimeSheetController;
use App\Http\Controllers\Api\Supervisor\SupervisorDswIncidentReportController;
use App\Http\Controllers\Api\Supervisor\SupervisorWeeklySignOffController;
use App\Http\Controllers\Api\Billing\BillingGigController;
use App\Http\Controllers\Api\Billing\BillingAssignGigController;
use App\Http\Controllers\Api\Billing\BillingDashboardController;
use App\Http\Controllers\Api\Billing\BillingUserController;
use App\Http\Controllers\Api\Billing\BillingClientController;
use App\Http\Controllers\Api\Billing\BillingTimeSheetController;
use App\Http\Controllers\Api\Billing\BillingTaskController;
use App\Http\Controllers\Api\Billing\BillingWeeklySignOffController;

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

Route::prefix('v1')->group(function () {
    Route::get('/', function () {
        return response()->json(["Backend Api is Running, Today`s date is ".date('Y-m-d H:i:s')]);
    });
    Route::middleware(['api'])->group(function () {
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
            Route::prefix('gig-type')->group(function() {
                Route::post('create-gig-type', [GigTypeController::class, 'store']);
                Route::put('update-gig-type', [GigTypeController::class, 'update']);
                Route::delete('delete-gig-type', [GigTypeController::class, 'destroy']);
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
                Route::put('assign-role-to-user', [UserController::class, 'assignRole']);
                Route::get('fetch-roles-to-user', [UserController::class, 'fetchRoles']);
            });
            Route::prefix('client')->group(function() {
                Route::get('all-clients', [ClientController::class, 'index']);
                Route::get('single-client', [ClientController::class, 'show']);
                Route::post('create-client', [ClientController::class, 'store']);
                Route::post('update-client', [ClientController::class, 'update']);
                Route::put('archive-unarchive-client', [ClientController::class, 'archive_unarchive']);
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
        Route::prefix('auth')->group(function () {
            Route::any('login', [AuthController::class, 'login'])->name('login');
            Route::post('/email-verification', [AuthController::class, 'email_verify']);
            Route::post('reset-temporary-password', [AuthController::class, 'reset_temporary'])->name('temporary.reset');
            Route::post('complete-setup', [AuthController::class, 'complete_setup']);
            Route::get('/check-token', [AuthController::class, 'checkToken']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('password/email', [ForgotPasswordController::class, 'sendOTP'])->middleware('throttle:3,1');
            Route::post('password/email/verify', [ForgotPasswordController::class, 'verifyOTP'])->middleware('throttle:3,1');
            Route::post('password/reset', [ResetPasswordController::class,'resetPassword'])->middleware('throttle:3,1')->name('password.reset');
            Route::post('change-password', [AuthController::class,'changePassword'])->middleware('throttle:3,1')->name('password.change');
        });
        //Authentication Route Ends
        
        Route::prefix('user')->middleware(['verify-token-user'])->group(function () {
            Route::get('gigs', [UserGigController::class, 'gigs']);
            Route::get('gigs-zip_code', [UserGigController::class, 'gigs_zip']);
            Route::get('gig', [UserGigController::class, 'gig']);
            Route::get('active-gigs', [UserGigController::class, 'active_gigs']);
            Route::put('accept-gig', [UserGigController::class, 'accept_gig']);
            Route::delete('decline-gig', [UserGigController::class, 'decline_gig']);
            Route::post('clock-in', [TimeSheetController::class, 'clock_in']);
            Route::post('clock-out', [TimeSheetController::class, 'clock_out']);
            Route::post('flag-clock-in', [TimeSheetController::class, 'flag_clock_in']);
            Route::post('flag-clock-out', [TimeSheetController::class, 'flag_clock_out']);
            Route::get('timesheets', [TimeSheetController::class, 'timesheet']);
            Route::get('single-timesheet', [TimeSheetController::class, 'single_timesheet_by_uniqueID']);
            Route::post('emergency-clock-in', [TimeSheetController::class, 'emergency_clock_in']);
            Route::post('emergency-clock-out', [TimeSheetController::class, 'emergency_clock_out']);
            Route::get('/weekly-logs', [TimeSheetController::class, 'weeklyLog']);
            Route::get('/reward-points', [UserRewardController::class, 'showPoints']);
            Route::get('/reward-points-history', [UserRewardController::class,'history']);
            Route::post('/report-incident', [IncidentReportController::class, 'report_incident']);
            Route::get('/reported-incident', [IncidentReportController::class, 'all_reported_incident']);
            Route::get('/single-reported-incident', [IncidentReportController::class, 'single_reported_incident']);
            Route::post('/progress-report', [ProgressReportController::class, 'progress_report']);
            Route::get('/reported-progress', [ProgressReportController::class, 'all_reported_progress']);
            Route::get('/single-reported-progress', [ProgressReportController::class, 'single_reported_progress']);
            Route::post('/sign-off', [WeeklySignOffController::class, 'sign_off']);
            Route::get('/all-sign-off', [WeeklySignOffController::class, 'all_sign_off']);
            Route::get('/single-sign-off', [WeeklySignOffController::class, 'single_sign_off']);
            
        });

        Route::prefix('manager')->middleware('verify-token')->group(function() {
            Route::prefix('user')->group(function() {
                Route::get('all-users', [ManagerUserController::class, 'index']);
                Route::get('paginate-user', [ManagerUserController::class, 'paginate']);
                Route::get('all-dsws', [ManagerUserController::class, 'allDSW']);
                Route::get('all-csps', [ManagerUserController::class, 'allCSP']);
                Route::get('all-supervisors', [ManagerUserController::class, 'allSupervisor']);
                Route::get('single-user', [ManagerUserController::class, 'show']);
                Route::post('create-user', [ManagerUserController::class, 'store']);
                Route::put('update-user', [ManagerUserController::class, 'update']);
                Route::delete('delete-user', [ManagerUserController::class, 'destroy']);
                Route::put('activate-deactivate-user', [ManagerUserController::class, 'activate_deactivate']);
                Route::get('role-list', [ManagerUserController::class, 'role_list']);
                Route::put('assign-role-to-user', [ManagerUserController::class, 'assignRole']);
                Route::get('fetch-roles-to-user', [ManagerUserController::class, 'fetchRoles']);
                Route::get('active-users', [ManagerDashboardController::class, 'activeUsers']);
                Route::get('inactive-users', [ManagerDashboardController::class, 'inactiveUsers']);
            });
            Route::prefix('client')->group(function() {
                Route::get('all-clients', [ManagerClientController::class, 'index']);
                Route::get('single-client', [ManagerClientController::class, 'show']);
                Route::get('search-client', [ManagerClientController::class, 'search']);
                Route::get('paginate-client', [ManagerClientController::class, 'paginate']);
                Route::post('create-client', [ManagerClientController::class, 'store']);
                Route::post('update-client', [ManagerClientController::class, 'update']);
                Route::put('archive-unarchive-client', [ManagerClientController::class, 'archive_unarchive']);
                Route::put('multiple-archive-unarchive-client', [ManagerClientController::class, 'multiple_archive_unarchive']);
                Route::delete('delete-client', [ManagerClientController::class, 'destroy']);
                Route::post('client-plan-of-care', [ManagerClientController::class, 'create_plan_of_care']);
                Route::get('client-plan-of-care', [ManagerClientController::class, 'plan_of_care']);
                Route::post('replace-plan-of-care', [ManagerClientController::class, 'replace_plan_of_care']);
            });
            Route::prefix('gig')->group(function() {
                Route::get('all-gigs', [ManagerGigController::class, 'index']);
                Route::get('single-gig', [ManagerGigController::class, 'show']);
                Route::get('paginate-gig', [ManagerGigController::class, 'paginate']);
                Route::post('create-gig', [ManagerGigController::class, 'store']);
                Route::put('update-gig', [ManagerGigController::class, 'update']);
                Route::post('end-gig', [ManagerGigController::class, 'destroy']);
                Route::post('complete-gig', [ManagerGigController::class, 'complete']);
                // Route::delete('delete-gig', [ManagerGigController::class, 'destroy']);
                Route::get('active-inactive-gigs', [ManagerDashboardController::class, 'active_inactiveGigs']);
            });
            Route::prefix('assign_gig')->group(function() {
                Route::get('all-assign_gigs', [ManagerAssignGigController::class, 'index']);
                Route::get('single-assign_gig', [ManagerAssignGigController::class, 'show']);
                Route::post('create-assign_gig', [ManagerAssignGigController::class, 'store']);
                Route::put('update-assign_gig', [ManagerAssignGigController::class, 'update']);
                Route::delete('delete-assign_gig', [ManagerAssignGigController::class, 'destroy']);
            });
            Route::prefix('timesheet')->group(function() {
                Route::get('all-timesheets', [ManagerTimeSheetController::class, 'timesheet']);
                Route::get('single-timesheet', [ManagerTimeSheetController::class, 'single_timesheet_by_uniqueID']);
                Route::get('/weekly-logs', [ManagerTimeSheetController::class, 'weeklyLog']);
                Route::get('/weekly', [ManagerTimeSheetController::class, 'weeklyLogNumber']);
                Route::get('flagged-timesheets', [ManagerDashboardController::class, 'flagged_activity']);
                Route::get('all-activities', [ManagerTimeSheetController::class, 'getActivities']);
            });
            Route::prefix('task')->group(function() {
                Route::get('all-tasks', [ManagerTaskController::class, 'index']);
                Route::get('single-task', [ManagerTaskController::class, 'show']);
                Route::put('/update-task', [ManagerTaskController::class, 'update']);
                Route::delete('/delete-task', [ManagerTaskController::class, 'destroy']);
                Route::post('/create-task', [ManagerTaskController::class, 'store']);
            });
            Route::prefix('remark')->group(function() {
                Route::post('/create-remark', [ManagerDashboardController::class, 'remark']);
            });
            Route::prefix('sign-off')->group(function() {
                Route::get('/all-sign-off', [ManagerWeeklySignOffController::class, 'all_sign_off']);
                Route::get('/single-sign-off', [ManagerWeeklySignOffController::class, 'single_sign_off']);
            });
            Route::get('dashboard',[ManagerDashboardController::class,'dashboard']);
            Route::get('/user-dashboard', [AuthController::class, 'profile']);
        });

        Route::prefix('supervisor')->middleware('verify-token')->group(function() {
            Route::get('dashboard',[SupervisorDashboardController::class,'dashboard']);
            Route::get('/user-dashboard', [AuthController::class, 'profile']);
            Route::prefix('user')->group(function() {
                Route::get('all-users', [SupervisorUserController::class, 'index']);
                Route::get('single-user', [SupervisorUserController::class, 'show']);
                Route::get('paginate-user', [SupervisorUserController::class, 'paginate']);
                Route::get('all-dsws', [SupervisorUserController::class, 'allDSW']);
                Route::get('all-csps', [SupervisorUserController::class, 'allCSP']);
                Route::post('create-user', [SupervisorUserController::class, 'store']);
                Route::put('update-user', [SupervisorUserController::class, 'update']);
                Route::delete('delete-user', [SupervisorUserController::class, 'destroy']);
                Route::put('assign-role-to-user', [SupervisorUserController::class, 'assignRole']);
                Route::get('fetch-roles-to-user', [SupervisorUserController::class, 'fetchRoles']);
                Route::get('active-users', [SupervisorDashboardController::class, 'activeUsers']);
                Route::get('inactive-users', [SupervisorDashboardController::class, 'inactiveUsers']);
                Route::post('/progress-report', [SupervisorDashboardController::class, 'progress_report']);
                Route::get('/reported-progress', [SupervisorDashboardController::class, 'all_reported_progress']);
                Route::get('/single-reported-progress', [SupervisorDashboardController::class, 'single_reported_progress']);
                Route::post('/sign-off', [SupervisorDashboardController::class, 'sign_off']);
                Route::get('/all-sign-off', [SupervisorDashboardController::class, 'all_sign_off']);
                Route::get('/single-sign-off', [SupervisorDashboardController::class, 'single_sign_off']);
            });
            Route::prefix('client')->group(function() {
                Route::get('all-clients', [SupervisorClientController::class, 'index']);
                Route::get('single-client', [SupervisorClientController::class, 'show']);
                Route::get('search-client', [SupervisorClientController::class, 'search']);
                Route::get('paginate-client', [SupervisorClientController::class, 'paginate']);
                Route::post('create-client', [SupervisorClientController::class, 'store']);
                Route::post('update-client', [SupervisorClientController::class, 'update']);
                Route::put('archive-unarchive-client', [SupervisorClientController::class, 'archive_unarchive']);
                Route::delete('delete-client', [SupervisorClientController::class, 'destroy']);
            });
            Route::prefix('gig')->group(function() {
                Route::get('all-gigs', [SupervisorGigController::class, 'index']);
                Route::get('single-gig', [SupervisorGigController::class, 'show']);
                Route::get('paginate-gig', [SupervisorGigController::class, 'paginate']);
                Route::post('create-gig', [SupervisorGigController::class, 'store']);
                Route::put('update-gig', [SupervisorGigController::class, 'update']);
                Route::delete('end-gig', [SupervisorGigController::class, 'destroy']);
                Route::put('complete-gig', [SupervisorGigController::class, 'complete']);
                //Route::delete('delete-gig', [SupervisorGigController::class, 'destroy']);
                Route::get('active-inactive-gigs', [SupervisorDashboardController::class, 'active_inactiveGigs']);
            });
            Route::prefix('assign_gig')->group(function() {
                Route::get('all-assign_gigs', [SupervisorAssignGigController::class, 'index']);
                Route::get('single-assign_gig', [SupervisorAssignGigController::class, 'show']);
                Route::post('create-assign_gig', [SupervisorAssignGigController::class, 'store']);
                Route::put('update-assign_gig', [SupervisorAssignGigController::class, 'update']);
                Route::delete('delete-assign_gig', [SupervisorAssignGigController::class, 'destroy']);
            });
            Route::prefix('timesheet')->group(function() {
                Route::get('all-timesheets', [SupervisorTimeSheetController::class, 'timesheet']);
                Route::get('single-timesheet', [SupervisorTimeSheetController::class, 'single_timesheet_by_uniqueID']);
                Route::get('/weekly-logs', [SupervisorTimeSheetController::class, 'weeklyLog']);
                Route::get('/weekly', [SupervisorTimeSheetController::class, 'weeklyLogNumber']);
                Route::get('flagged-timesheets', [SupervisorDashboardController::class, 'flagged_activity']);
                Route::get('all-activities', [SupervisorTimeSheetController::class, 'getActivities']);
            });
            Route::prefix('sign-off')->group(function() {
                Route::get('/all-supervisor-sign-off', [SupervisorWeeklySignOffController::class, 'all_sign_off']);
                Route::get('/single-supervisor-sign-off', [SupervisorWeeklySignOffController::class, 'single_sign_off']);
                Route::get('/all-sign-off', [SupervisorWeeklySignOffController::class, 'all_sign_off_others']);
                Route::get('/single-sign-off', [SupervisorWeeklySignOffController::class, 'single_sign_off_others']);
                Route::post('/supervisor-sign-off', [SupervisorWeeklySignOffController::class, 'sign_off']);
            });
            Route::get('gigs', [SupervisorDswUserGigController::class, 'gigs']);
            Route::get('gigs-zip_code', [SupervisorDswUserGigController::class, 'gigs_zip']);
            Route::get('gig', [SupervisorDswUserGigController::class, 'gig']);
            Route::get('active-gigs', [SupervisorDswUserGigController::class, 'active_gigs']);
            Route::put('accept-gig', [SupervisorDswUserGigController::class, 'accept_gig']);
            Route::delete('decline-gig', [SupervisorDswUserGigController::class, 'decline_gig']);
            Route::post('clock-in', [SupervisorDswTimeSheetController::class, 'clock_in']);
            Route::post('clock-out', [SupervisorDswTimeSheetController::class, 'clock_out']);
            Route::post('flag-clock-in', [SupervisorDswTimeSheetController::class, 'flag_clock_in']);
            Route::post('flag-clock-out', [SupervisorDswTimeSheetController::class, 'flag_clock_out']);
            Route::get('timesheets', [SupervisorDswTimeSheetController::class, 'timesheet']);
            Route::get('single-timesheet', [SupervisorDswTimeSheetController::class, 'single_timesheet_by_uniqueID']);
            Route::post('emergency-clock-in', [SupervisorDswTimeSheetController::class, 'emergency_clock_in']);
            Route::post('emergency-clock-out', [SupervisorDswTimeSheetController::class, 'emergency_clock_out']);
            Route::get('/weekly-logs', [SupervisorDswTimeSheetController::class, 'weeklyLog']);
            Route::get('/reward-points', [SupervisorDswUserRewardController::class, 'showPoints']);
            Route::get('/reward-points-history', [SupervisorDswUserRewardController::class,'history']);
            Route::post('/report-incident', [SupervisorDswIncidentReportController::class, 'report_incident']);
            Route::get('/reported-incident', [SupervisorDswIncidentReportController::class, 'all_reported_incident']);
            Route::get('/single-reported-incident', [SupervisorDswIncidentReportController::class, 'single_reported_incident']);
        });
        
        Route::prefix('billing')->middleware('verify-token')->group(function() {
            Route::prefix('user')->group(function() {
                Route::get('all-users', [BillingUserController::class, 'index']);
                Route::get('paginate-user', [BillingUserController::class, 'paginate']);
                Route::get('all-dsws', [BillingUserController::class, 'allDSW']);
                Route::get('all-csps', [BillingUserController::class, 'allCSP']);
                Route::get('all-supervisors', [BillingUserController::class, 'allSupervisor']);
                Route::get('single-user', [BillingUserController::class, 'show']);
                Route::get('role-list', [BillingUserController::class, 'role_list']);
                Route::get('fetch-roles-to-user', [BillingUserController::class, 'fetchRoles']);
                Route::get('active-users', [BillingDashboardController::class, 'activeUsers']);
                Route::get('inactive-users', [BillingDashboardController::class, 'inactiveUsers']);
            });
            Route::prefix('client')->group(function() {
                Route::get('all-clients', [BillingClientController::class, 'index']);
                Route::get('single-client', [BillingClientController::class, 'show']);
                Route::get('search-client', [BillingClientController::class, 'search']);
                Route::get('paginate-client', [BillingClientController::class, 'paginate']);
                Route::get('client-plan-of-care', [BillingClientController::class, 'plan_of_care']);
            });
            Route::prefix('gig')->group(function() {
                Route::get('all-gigs', [BillingGigController::class, 'index']);
                Route::get('single-gig', [BillingGigController::class, 'show']);
                Route::get('paginate-gig', [BillingGigController::class, 'paginate']);
                Route::get('active-inactive-gigs', [BillingDashboardController::class, 'active_inactiveGigs']);
            });
            Route::prefix('assign_gig')->group(function() {
                Route::get('all-assign_gigs', [BillingAssignGigController::class, 'index']);
                Route::get('single-assign_gig', [BillingAssignGigController::class, 'show']);
            });
            Route::prefix('timesheet')->group(function() {
                Route::get('all-timesheets', [BillingTimeSheetController::class, 'timesheet']);
                Route::get('single-timesheet', [BillingTimeSheetController::class, 'single_timesheet_by_uniqueID']);
                Route::get('/weekly-logs', [BillingTimeSheetController::class, 'weeklyLog']);
                Route::get('/weekly', [BillingTimeSheetController::class, 'weeklyLogNumber']);
                Route::get('flagged-timesheets', [BillingDashboardController::class, 'flagged_activity']);
                Route::get('all-activities', [BillingTimeSheetController::class, 'getActivities']);
                Route::get('/timesheet_activities', [BillingTimeSheetController::class, 'timesheet_activities']);
                Route::get('/user_timesheet_activities', [BillingTimeSheetController::class, 'user_timesheet_activities']);
            });
            Route::prefix('sign-off')->group(function() {
                Route::get('/all-sign-off', [BillingWeeklySignOffController::class, 'all_sign_off']);
                Route::get('/single-sign-off', [BillingWeeklySignOffController::class, 'single_sign_off']);
            });
            Route::prefix('task')->group(function() {
                Route::get('all-tasks', [BillingTaskController::class, 'index']);
                Route::get('single-task', [BillingTaskController::class, 'show']);
            });
            Route::get('dashboard',[BillingDashboardController::class,'dashboard']);
            Route::get('/user-dashboard', [AuthController::class, 'profile']);
        });
    });

    // Free Routes Starts
    Route::get('leadership-board', [LeadershipBoardController::class, 'leadershipboard']);
    Route::get('/export-timesheet', [ExportsController::class, 'exportTimesheet']);
    Route::get('/export-user-timesheet', [ExportsController::class, 'exportUserTimesheet']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::get('all-gig-types', [GigTypeController::class, 'index']);
    // Free Routes Ends
});
