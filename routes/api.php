<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\JobApplicationController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PublicDiscoveryController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\SmsManagementController;
use App\Http\Controllers\Api\WorkerController;
use Illuminate\Support\Facades\Route;

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('reset-password', [AuthController::class, 'resetPassword']);
Route::post('forgot-password-sms', [AuthController::class, 'forgotPasswordSms'])->middleware('throttle:5,1');
Route::post('reset-password-sms', [AuthController::class, 'resetPasswordSms'])->middleware('throttle:5,1');
Route::get('public/discovery', [PublicDiscoveryController::class, 'index'])->middleware('throttle:60,1');
Route::get('public/job-seekers/{profile}/thumbnail', [PublicDiscoveryController::class, 'thumbnail'])
    ->name('public.job-seeker-thumbnail')
    ->middleware('throttle:120,1');
Route::get('subscription-packages', [SubscriptionController::class, 'packages']);
Route::get('catalogs', [CatalogController::class, 'index']);
Route::get('locations/uganda', [CatalogController::class, 'locations']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('user', [AuthController::class, 'me']);
    Route::post('phone-verification/send', [AuthController::class, 'sendPhoneVerification'])->middleware('throttle:3,1');
    Route::post('phone-verification/verify', [AuthController::class, 'verifyPhone'])->middleware('throttle:5,1');
    Route::get('profile-file/{field}', [ProfileController::class, 'file']);

    Route::middleware('permission:view_notifications')->group(function () {
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::patch('notifications/{notification}/read', [NotificationController::class, 'markRead']);
    });

    Route::middleware(['role:job_seeker', 'phone.verified'])->group(function () {
        Route::post('job-seeker/profile', [ProfileController::class, 'submitJobSeeker'])->middleware('permission:manage_job_seeker_profile');
        Route::middleware('permission:manage_own_subscription')->group(function () {
            Route::get('subscriptions/current', [SubscriptionController::class, 'current']);
            Route::post('subscriptions', [SubscriptionController::class, 'subscribe']);
            Route::post('subscriptions/upgrade', [SubscriptionController::class, 'upgrade']);
        });
        Route::middleware('permission:browse_jobs')->group(function () {
            Route::get('jobs', [JobController::class, 'index']);
            Route::get('jobs/matching', [JobController::class, 'matching']);
            Route::get('jobs/{job}', [JobController::class, 'show']);
        });
        Route::post('jobs/{job}/apply', [JobApplicationController::class, 'apply'])->middleware('permission:apply_for_jobs');
        Route::get('my-applications', [JobApplicationController::class, 'myApplications'])->middleware('permission:view_own_applications');
    });

    Route::middleware(['role:employer', 'phone.verified'])->group(function () {
        Route::post('employer/profile', [ProfileController::class, 'submitEmployer'])->middleware('permission:manage_employer_profile');
        Route::middleware('permission:manage_employer_jobs')->group(function () {
            Route::post('employer/jobs', [JobController::class, 'store']);
            Route::get('employer/jobs', [JobController::class, 'employerJobs']);
        });
        Route::get('employer/applications', [JobApplicationController::class, 'employerApplications'])->middleware('permission:view_employer_applications');
        Route::patch('employer/applications/{application}/status', [JobApplicationController::class, 'updateStatus'])->middleware('permission:update_employer_application_status');
        Route::middleware('employer.approved')->group(function () {
            Route::middleware('permission:browse_workers')->group(function () {
                Route::get('workers', [WorkerController::class, 'index']);
                Route::get('workers/{worker}', [WorkerController::class, 'show']);
            });
            Route::middleware('permission:manage_worker_orders')->group(function () {
                Route::post('worker-orders', [WorkerController::class, 'storeOrder']);
                Route::get('worker-orders', [WorkerController::class, 'orders']);
            });
        });
    });

    Route::middleware(['role:admin', 'permission:access_admin'])->prefix('admin')->group(function () {
        Route::get('stats', [AdminController::class, 'stats'])->middleware('permission:view_dashboard');
        Route::get('users', [AdminController::class, 'users'])->middleware('permission:manage_users,view_users');
        Route::post('users', [AdminController::class, 'storeUser'])->middleware('permission:manage_users,create_users');
        Route::patch('users/{user}/suspend', [AdminController::class, 'suspendUser'])->middleware('permission:manage_users,suspend_users');

        Route::get('roles', [AdminController::class, 'roles'])->middleware('permission:manage_roles,view_roles,create_roles,edit_roles,create_users');
        Route::post('roles', [AdminController::class, 'storeRole'])->middleware('permission:manage_roles,create_roles');
        Route::patch('roles/{role}', [AdminController::class, 'updateRole'])->middleware('permission:manage_roles,edit_roles');

        Route::prefix('sms')->group(function () {
            Route::get('rate', [SmsManagementController::class, 'rate'])->middleware('permission:manage_sms,check_sms_balance,top_up_sms');
            Route::get('balance', [SmsManagementController::class, 'balance'])->middleware('permission:manage_sms,check_sms_balance');
            Route::post('payments', [SmsManagementController::class, 'storePayment'])->middleware('permission:manage_sms,top_up_sms');
            Route::get('payments', [SmsManagementController::class, 'payments'])->middleware('permission:manage_sms,see_sms_payments');
            Route::patch('payments/{payment}/refresh', [SmsManagementController::class, 'refreshPayment'])->middleware('permission:manage_sms,process_sms_payments');
            Route::post('payments/{payment}/distribute', [SmsManagementController::class, 'distribute'])->middleware('permission:manage_sms,process_sms_payments');
            Route::get('topups', [SmsManagementController::class, 'topups'])->middleware('permission:manage_sms,see_sms_topups');
        });

        Route::get('catalogs', [CatalogController::class, 'adminIndex'])->middleware('permission:manage_catalogs,view_catalogs');
        Route::post('catalogs/job-categories', [CatalogController::class, 'storeJobCategory'])->middleware('permission:manage_catalogs,create_catalogs');
        Route::post('catalogs/languages', [CatalogController::class, 'storeLanguage'])->middleware('permission:manage_catalogs,create_catalogs');
        Route::post('catalogs/religions', [CatalogController::class, 'storeReligion'])->middleware('permission:manage_catalogs,create_catalogs');
        Route::post('catalogs/locations', [CatalogController::class, 'storeLocation'])->middleware('permission:manage_catalogs,create_catalogs');

        Route::middleware('permission:approve_job_seekers')->group(function () {
            Route::get('job-seeker-profiles/pending', [AdminController::class, 'pendingJobSeekers']);
            Route::get('job-seeker-profiles/{profile}', [AdminController::class, 'showJobSeeker']);
            Route::patch('job-seeker-profiles/{profile}/decision', [AdminController::class, 'decideJobSeeker']);
        });

        Route::middleware('permission:approve_employers')->group(function () {
            Route::get('employer-profiles/pending', [AdminController::class, 'pendingEmployers']);
            Route::get('employer-profiles/{profile}', [AdminController::class, 'showEmployer']);
            Route::patch('employer-profiles/{profile}/decision', [AdminController::class, 'decideEmployer']);
        });

        Route::get('jobs/pending', [AdminController::class, 'pendingJobs'])->middleware('permission:approve_jobs,view_pending_jobs');
        Route::post('jobs', [JobController::class, 'adminStore'])->middleware('permission:upload_jobs');
        Route::get('jobs', [AdminController::class, 'jobs'])->middleware('permission:search_jobs,approve_jobs');
        Route::get('jobs/{job}', [AdminController::class, 'showJob'])->middleware('permission:search_jobs,approve_jobs');
        Route::patch('jobs/{job}/decision', [AdminController::class, 'decideJob'])->middleware('permission:approve_jobs');
        Route::get('files/{type}/{id}/{field}', [AdminController::class, 'file'])->middleware('permission:approve_job_seekers,approve_employers');

        Route::get('applications/pending', [AdminController::class, 'pendingApplications'])->middleware('permission:approve_applications');
        Route::patch('applications/{application}/decision', [AdminController::class, 'decideApplication'])->middleware('permission:approve_applications');

        Route::get('worker-orders/pending', [AdminController::class, 'pendingWorkerOrders'])->middleware('permission:approve_worker_orders');
        Route::patch('worker-orders/{order}/decision', [AdminController::class, 'decideWorkerOrder'])->middleware('permission:approve_worker_orders');

        Route::get('subscription-packages', [AdminController::class, 'subscriptionPackages'])->middleware('permission:manage_subscription_packages,view_subscription_packages_admin');
        Route::post('subscription-packages', [AdminController::class, 'storeSubscriptionPackage'])->middleware('permission:manage_subscription_packages,create_subscription_packages');
        Route::patch('subscription-packages/{package}', [AdminController::class, 'updateSubscriptionPackage'])->middleware('permission:manage_subscription_packages,edit_subscription_packages');
    });
});
