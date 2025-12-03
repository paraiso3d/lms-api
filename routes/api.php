<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ClassessController;


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

//ROLES
Route::get('/roles', [RoleController::class, 'listActiveRoles']);
Route::get('/roles/{id}', [RoleController::class, 'getRole']);
Route::post('create/roles', [RoleController::class, 'createRole']);
Route::post('update/roles/{id}', [RoleController::class, 'updateRole']);
Route::post('archive/roles/{id}', [RoleController::class, 'archiveRole']);
Route::post('restore/roles/{id}', [RoleController::class, 'restoreRole']);


//USERS
Route::get('/users', [UserController::class, 'listActiveUsers']);
Route::get('/users/{id}', [UserController::class, 'getUser']);
Route::post('create/users', [UserController::class, 'createUser']);
Route::post('update/users/{id}', [UserController::class, 'updateUser']);
Route::post('archive/users/{id}', [UserController::class, 'archiveUser']);


//CLASSES
Route::get('/classes', [ClassessController::class, 'listActiveClasses']);
Route::get('/classes/{id}', [ClassessController::class, 'getClass']);
Route::post('create/classes', [ClassessController::class, 'createClass']);
Route::post('update/classes/{id}', [ClassessController::class, 'updateClass']);
Route::post('archive/classes/{id}', [ClassessController::class, 'archiveClass']);
