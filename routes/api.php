<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ClassessController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\DiscussionController;
use App\Http\Controllers\AuthController;


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

//AUTH
Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

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
Route::get('/classes/{classId}/people', [ClassessController::class, 'getClassPeople']);
Route::get('/classes/{id}', [ClassessController::class, 'getClass']);
Route::post('create/classes', [ClassessController::class, 'createClass']);
Route::middleware('auth:sanctum')->post('join/classes', [ClassessController::class, 'joinClass']);
Route::post('update/classes/{id}', [ClassessController::class, 'updateClass']);
Route::post('archive/classes/{id}', [ClassessController::class, 'archiveClass']);

// ASSIGNMENTS
Route::post('create/assignments', [AssignmentController::class, 'createAssignment']);
Route::get('/classes/{class_id}/assignments', [AssignmentController::class, 'getAssignments']);
Route::get('/assignments/{id}', [AssignmentController::class, 'getAssignment']);
Route::post('update/assignments/{id}', [AssignmentController::class, 'updateAssignment']);
Route::delete('delete/assignments/{id}', [AssignmentController::class, 'deleteAssignment']);

// SUBMISSIONS
Route::post('/assignments/{assignment_id}/submit', [AssignmentController::class, 'submit']);
Route::post('/submissions/{submission_id}/grade', [AssignmentController::class, 'gradeSubmission']);


// QUIZZES
Route::post('/quizzes', [QuizController::class, 'createQuiz']);
Route::get('/quizzes/{quizId}', [QuizController::class, 'getQuiz']);
Route::post('/quizzes/{quizId}/questions', [QuizController::class, 'addQuestion']);
Route::post('/quizzes/{quizId}/submit', [QuizController::class, 'submitQuiz']);
Route::post('/quizzes/{quizId}/archive', [QuizController::class, 'archiveQuiz']);

// DISCUSSIONS
Route::post('/discussions', [DiscussionController::class, 'createDiscussion']);
Route::get('/classes/{classId}/discussions', [DiscussionController::class, 'getDiscussions']);
Route::get('/discussions/{id}', [DiscussionController::class, 'getDiscussion']);
Route::post('/discussions/{discussionId}/reply', [DiscussionController::class, 'addReply']);
Route::post('/discussions/{id}/archive', [DiscussionController::class, 'archiveDiscussion']);
