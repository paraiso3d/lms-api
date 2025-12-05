<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\QuizOption;
use App\Models\QuizSubmission;
use App\Models\QuizAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuizController extends Controller
{
    // ============================================================
    // CREATE QUIZ
    // ============================================================
    public function createQuiz(Request $request)
    {
        $request->validate([
            'class_id'    => 'required|exists:classes,id',
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'total_points' => 'required|integer',
            'time_limit'  => 'nullable|integer',
            'due_date'    => 'nullable|date',
        ]);

        $quiz = Quiz::create([
            'class_id'     => $request->class_id,
            'title'        => $request->title,
            'description'  => $request->description,
            'total_points' => $request->total_points,
            'time_limit'   => $request->time_limit,
            'due_date'     => $request->due_date,
            'created_by'   => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Quiz created successfully!',
            'data'    => $quiz
        ]);
    }

    // ============================================================
    // ADD QUESTION + OPTIONS
    // ============================================================
    public function addQuestion(Request $request, $quizId)
    {
        $request->validate([
            'question_text' => 'required|string',
            'points'        => 'required|integer',
            'options'       => 'required|array|min:2', // at least 2 options
            'options.*.option_text' => 'required|string',
            'options.*.is_correct'  => 'required|boolean',
        ]);

        $quiz = Quiz::where('id', $quizId)->where('is_archived', 0)->first();

        if (!$quiz) {
            return response()->json([
                'success' => false,
                'message' => 'Quiz not found'
            ], 404);
        }

        $question = QuizQuestion::create([
            'quiz_id'       => $quizId,
            'question_text' => $request->question_text,
            'points'        => $request->points,
            'question_type' => 'multiple_choice',
        ]);

        foreach ($request->options as $opt) {
            QuizOption::create([
                'question_id' => $question->id,
                'option_text' => $opt['option_text'],
                'is_correct'  => $opt['is_correct'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Question added successfully',
            'data'    => $question->load('options')
        ]);
    }

    // ============================================================
    // GET QUIZ WITH QUESTIONS & OPTIONS
    // ============================================================
    public function getQuiz($quizId)
    {
        $quiz = Quiz::where('id', $quizId)
            ->where('is_archived', 0)
            ->with(['questions.options'])
            ->first();

        if (!$quiz) {
            return response()->json([
                'success' => false,
                'message' => 'Quiz not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $quiz
        ]);
    }

    // ============================================================
    // SUBMIT QUIZ (STUDENT)
    // ============================================================
    public function submitQuiz(Request $request, $quizId)
    {
        $request->validate([
            'student_id' => 'required|exists:users,id',
            'answers'    => 'required|array',
            'answers.*.question_id' => 'required|exists:quiz_questions,id',
            'answers.*.selected_option_id' => 'nullable|exists:quiz_options,id',
        ]);

        $quiz = Quiz::where('id', $quizId)->where('is_archived', 0)->first();
        if (!$quiz) {
            return response()->json([
                'success' => false,
                'message' => 'Quiz not found'
            ], 404);
        }

        $submission = QuizSubmission::create([
            'quiz_id'    => $quizId,
            'student_id' => $request->student_id,
        ]);

        $totalScore = 0;

        foreach ($request->answers as $ans) {
            $option = QuizOption::find($ans['selected_option_id'] ?? 0);
            $isCorrect = $option ? $option->is_correct : 0;
            $pointsAwarded = $isCorrect ? QuizQuestion::find($ans['question_id'])->points : 0;
            $totalScore += $pointsAwarded;

            QuizAnswer::create([
                'submission_id'      => $submission->id,
                'question_id'        => $ans['question_id'],
                'selected_option_id' => $ans['selected_option_id'] ?? null,
                'is_correct'         => $isCorrect,
            ]);
        }

        $submission->update(['score' => $totalScore]);

        return response()->json([
            'success' => true,
            'message' => 'Quiz submitted successfully!',
            'data'    => $submission->load('answers.selectedOption')
        ]);
    }

    // ============================================================
    // ARCHIVE QUIZ
    // ============================================================
    public function archiveQuiz($quizId)
    {
        $quiz = Quiz::find($quizId);

        if (!$quiz) {
            return response()->json([
                'success' => false,
                'message' => 'Quiz not found'
            ], 404);
        }

        $quiz->update(['is_archived' => 1]);
        QuizQuestion::where('quiz_id', $quizId)->update(['is_archived' => 1]);
        QuizOption::whereIn(
            'question_id',
            QuizQuestion::where('quiz_id', $quizId)->pluck('id')
        )->update(['is_archived' => 1]);

        QuizSubmission::where('quiz_id', $quizId)->update(['is_archived' => 1]);
        QuizAnswer::whereIn(
            'submission_id',
            QuizSubmission::where('quiz_id', $quizId)->pluck('id')
        )->update(['is_archived' => 1]);

        return response()->json([
            'success' => true,
            'message' => 'Quiz archived successfully'
        ]);
    }
    // ============================================================
    // QUIZ RESULTS
    // ============================================================

    public function getQuizResults($quizId)
    {
        $quiz = Quiz::with([
            'submissions' => fn($q) => $q->where('is_archived', 0)->orderBy('created_at', 'desc'),
            'submissions.student:id,first_name,last_name,email'
        ])
            ->where('id', $quizId)
            ->where('is_archived', 0)
            ->first();

        if (!$quiz) {
            return response()->json([
                'success' => false,
                'message' => 'Quiz not found'
            ], 404);
        }

        $totalPoints = $quiz->total_points;

        $completedCount = $quiz->submissions->count();

        // calculate stats
        $averageScore = $completedCount > 0
            ? round($quiz->submissions->avg('score'), 2)
            : 0;

        $averagePercentage = $completedCount > 0
            ? round(($averageScore / $totalPoints) * 100)
            : 0;

        // students passing = >= 50% (you can adjust)
        $passRate = $completedCount > 0
            ? round(($quiz->submissions->filter(fn($s) => $s->score >= ($totalPoints / 2))->count() / $completedCount) * 100)
            : 0;

        // results list
        $results = $quiz->submissions->map(function ($s) use ($totalPoints) {
            return [
                'student_name' => trim($s->student->first_name . ' ' . $s->student->last_name),
                'email'        => $s->student->email,
                'status'       => 'Completed',
                'score'        => "{$s->score}/{$totalPoints}",
                'percentage'   => round(($s->score / $totalPoints) * 100),
                'submitted'    => $s->created_at->format('M j, g:i A'),
                'submission_id' => $s->id,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'quiz' => [
                    'title'          => $quiz->title,
                    'description'    => $quiz->description,
                    'total_points'   => $totalPoints,
                    'completed'      => $completedCount,
                    'average_score'  => "{$averageScore}/{$totalPoints}",
                    'average_percent' => $averagePercentage,
                    'pass_rate'      => $passRate,
                ],
                'results' => $results
            ]
        ]);
    }
}
