<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\JobsController;
use App\Http\Controllers\Api\ApiAccountController;

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

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

/*
|--------------------------------------------------------------------------
/ protected routes
|--------------------------------------------------------------------------
 */
Route::group(['middleware' => ['auth:sanctum']], function () {

    Route::get('jobs', [JobsController::class, 'indexApi']);
    Route::post('save-job', [ApiAccountController::class, 'saveJob']);
    Route::get('my-jobs', [ApiAccountController::class, 'myJobs']);
    Route::put('edit-job/{jobId}', [ApiAccountController::class, 'updateJob']);
    Route::delete('delete-job', [ApiAccountController::class, 'deleteJob']);
    Route::get('my-jobs-applications', [ApiAccountController::class, 'myJobApplications']);
    Route::post('remove-job-application', [ApiAccountController::class, 'removeJobs']);
    // Change Password
    Route::post('change-password', [ApiAccountController::class, 'changePassword']);
    // log out
    Route::post('logout', [ApiAccountController::class, 'logout'])->middleware('auth:sanctum');
});

/*
|--------------------------------------------------------------------------
/ public routes
|--------------------------------------------------------------------------
*/
Route::post('registration', [ApiAccountController::class, 'processRegistration']);
Route::post('login', [ApiAccountController::class, 'login']);
