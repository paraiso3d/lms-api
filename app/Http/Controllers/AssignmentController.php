<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentAttachment;
use App\Models\Submission;
use App\Models\SubmissionFile;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\SubmissionComment;

class AssignmentController extends Controller
{
    // ============================================================
    // CREATE ASSIGNMENT
    // ============================================================
    public function createAssignment(Request $request)
    {
        $request->validate([
            'class_id'      => 'required|exists:classes,id',
            'title'         => 'required|string|max:255',
            'instructions'  => 'nullable|string',
            'max_points'    => 'required|integer',
            'due_date'      => 'nullable|date',
            'topic_id' => 'nullable|exists:topics,id',
            'attachments.*' => 'nullable|file|max:50240',
        ]);

        $assignment = Assignment::create([
            'class_id'     => $request->class_id,
            'title'        => $request->title,
            'instructions' => $request->instructions,
            'max_points'   => $request->max_points,
            'due_date'     => $request->due_date,
            'topic_id'     => $request->topic_id,
            'created_by'   => auth()->id(),
        ]);

        // Save attachments using helper
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {

                $path = $this->saveFileToPublic($file, 'assignment');

                AssignmentAttachment::create([
                    'assignment_id' => $assignment->id,
                    'file_path'     => $path,
                    'file_type'     => $file->getClientOriginalExtension(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Assignment created successfully!',
            'data'    => $assignment->load('attachments'),
        ]);
    }

    // ============================================================
    // GET ALL ASSIGNMENTS FOR A CLASS
    // ============================================================
    public function getAssignments($classId)
    {
        $studentId = auth()->id();

        $assignments = Assignment::where('class_id', $classId)
            ->where('is_archived', 0)
            ->with([
                'topic:id,topic_name',

                'attachments' => function ($q) {
                    $q->where('is_archived', 0);
                },

                'submissions' => function ($q) use ($studentId) {
                    $q->where('is_archived', 0)
                        ->where('student_id', $studentId);
                }
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $assignments,
        ]);
    }

    public function getStudentAssignmentSubmission($assignmentId, $studentId)
    {
        $assignment = Assignment::with([
            'topic:id,topic_name',

            'teacher:id,first_name,last_name,email,avatar',

            'attachments' => function ($q) {
                $q->where('is_archived', 0);
            },

            'submissions' => function ($q) use ($studentId) {
                $q->where('student_id', $studentId)
                    ->where('is_archived', 0)
                    ->with([
                        'files' => function ($q) {
                            $q->where('is_archived', 0);
                        },
                        'student:id,first_name,last_name,email,avatar'
                    ]);
            }
        ])
            ->where('id', $assignmentId)
            ->where('is_archived', 0)
            ->first();

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found.',
            ], 404);
        }

        $submission = $assignment->submissions->first();

        return response()->json([
            'success' => true,
            'data' => [

                'teacher' => $assignment->teacher ? [
                    'id' => $assignment->teacher->id,
                    'first_name' => $assignment->teacher->first_name,
                    'last_name' => $assignment->teacher->last_name,
                    'email' => $assignment->teacher->email,
                    'avatar' => $assignment->teacher->avatar,
                ] : null,

                'student' => $submission?->student ? [
                    'id' => $submission->student->id,
                    'first_name' => $submission->student->first_name,
                    'last_name' => $submission->student->last_name,
                    'email' => $submission->student->email,
                    'avatar' => $submission->student->avatar,
                ] : null,

                'assignment' => [
                    'id' => $assignment->id,
                    'title' => $assignment->title,
                    'instructions' => $assignment->instructions,
                    'max_points' => $assignment->max_points,
                    'due_date' => $assignment->due_date,
                    'topic' => $assignment->topic,
                    'attachments' => $assignment->attachments,
                ],

                'submission_status' => $submission?->status ?? 'not_submitted',

                'submission' => $submission ? [
                    'id' => $submission->id,
                    'status' => $submission->status,
                    'grade' => $submission->grade,
                    'feedback' => $submission->feedback,
                    'private_comment' => $submission->private_comment,
                    'submitted_at' => $submission->submitted_at,
                    'files' => $submission->files,
                ] : null,
            ],
        ]);
    }



    // ============================================================
    // GET SINGLE ASSIGNMENT
    // ============================================================
    public function getAssignment($id)
    {
        $assignment = Assignment::where('id', $id)
            ->where('is_archived', 0)
            ->with([
                'attachments' => fn($q) => $q->where('is_archived', 0),
                'submissions.files' => fn($q) => $q->where('is_archived', 0)
            ])
            ->first();

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $assignment,
        ]);
    }

    // ============================================================
    // UPDATE ASSIGNMENT
    // ============================================================
    public function updateAssignment(Request $request, $id)
    {
        $assignment = Assignment::where('id', $id)
            ->where('is_archived', 0)
            ->first();

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found',
            ], 404);
        }

        $request->validate([
            'topic_id' => 'nullable|exists:topics,id',
        ]);

        $assignment->update($request->only([
            'title',
            'instructions',
            'max_points',
            'due_date',
            'topic_id',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Assignment updated!',
            'data'    => $assignment,
        ]);
    }

    // ============================================================
    // ARCHIVE ASSIGNMENT
    // ============================================================
    public function deleteAssignment($id)
    {
        $assignment = Assignment::find($id);

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Not found',
            ], 404);
        }

        $assignment->update(['is_archived' => 1]);

        AssignmentAttachment::where('assignment_id', $id)
            ->update(['is_archived' => 1]);

        Submission::where('assignment_id', $id)
            ->update(['is_archived' => 1]);

        SubmissionFile::whereIn(
            'submission_id',
            Submission::where('assignment_id', $id)->pluck('id')
        )->update(['is_archived' => 1]);

        return response()->json([
            'success' => true,
            'message' => 'Assignment archived successfully.',
        ]);
    }

    // ============================================================
    // SUBMIT ASSIGNMENT
    // ============================================================
    public function submit(Request $request, $assignmentId)
    {
        $request->validate([
            'files.*' => 'nullable|file|max:50240',
            'private_comment' => 'nullable|string|max:2000',
        ]);

        $student = auth()->user();

        if ($student->role->role_name !== 'student') {
            return response()->json([
                'success' => false,
                'message' => 'Only students can submit assignments.',
            ], 403);
        }

        // Check if the student already submitted this assignment
        $submission = Submission::where('assignment_id', $assignmentId)
            ->where('student_id', $student->id)
            ->where('is_archived', 0)
            ->first();

        if ($submission) {

            // Update existing submission
            $submission->update([
                'status'          => 'submitted',
                'private_comment' => $request->private_comment,
                'submitted_at'    => now(),
            ]);

            // Remove old files if replacing them
            SubmissionFile::where('submission_id', $submission->id)->delete();
        } else {

            // Create new submission
            $submission = Submission::create([
                'assignment_id'   => $assignmentId,
                'student_id'      => $student->id,
                'status'          => 'submitted',
                'private_comment' => $request->private_comment,
                'submitted_at'    => now(),
            ]);
        }

        // Upload new files
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {

                $path = $this->saveFileToPublic($file, 'submission');

                SubmissionFile::create([
                    'submission_id' => $submission->id,
                    'file_path'     => $path,
                    'file_type'     => $file->getClientOriginalExtension(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => $submission->wasRecentlyCreated
                ? 'Submitted successfully!'
                : 'Resubmitted successfully!',
            'data' => $submission->load('files'),
        ]);
    }


    // ============================================================
    // GRADE SUBMISSION
    // ============================================================
    public function gradeSubmission(Request $request, $submissionId)
    {
        $request->validate([
            'grade'    => 'required|integer',
            'feedback' => 'nullable|string',
        ]);

        $submission = Submission::where('id', $submissionId)
            ->where('is_archived', 0)
            ->first();

        if (!$submission) {
            return response()->json([
                'success' => false,
                'message' => 'Submission not found',
            ], 404);
        }

        $submission->update([
            'grade'    => $request->grade,
            'feedback' => $request->feedback,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Submission graded!',
            'data'    => $submission,
        ]);
    }



    public function getAssignmentDetails($id)
    {
        // load assignment, class -> teacher, attachments, submissions -> student
        $assignment = Assignment::with([
            'class.teacher:id,first_name,last_name,email',
            'attachments' => fn($q) => $q->where('is_archived', 0),
            'submissions' => fn($q) => $q->where('is_archived', 0)->orderBy('created_at', 'desc'),
            'submissions.student' => fn($q) => $q->select('id', 'first_name', 'last_name', 'email')
        ])->where('id', $id)
            ->where('is_archived', 0)
            ->first();

        if (! $assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found'
            ], 404);
        }

        // formatted meta
        $due = $assignment->due_date ? Carbon::parse($assignment->due_date) : null;
        $due_formatted = $due ? $due->format('M j, Y, g:i A') : null;

        // submission stats
        $total = $assignment->submissions->count();
        $graded = $assignment->submissions->whereNotNull('grade')->count();
        $not_graded = $total - $graded;

        // build submissions list
        $submissions = $assignment->submissions->map(function ($s) {
            $student = $s->student;
            return [
                'id'            => $s->id,
                'student_name'  => $student ? trim($student->first_name . ' ' . $student->last_name) : 'Unknown',
                'submitted_at'  => $s->created_at ? $s->created_at->format('n/j/Y') : null,
                'submitted_time' => $s->created_at ? $s->created_at->format('g:i A') : null,
                'grade'         => $s->grade !== null ? $s->grade : 'Not graded',
                'status'        => $s->status ?? 'unknown',
            ];
        });

        $response = [
            'success' => true,
            'data' => [
                'title'        => $assignment->title,
                'topic'        => $assignment->topic,
                'teacher' => $assignment->class && $assignment->class->teacher ? [
                    'name'  => trim($assignment->class->teacher->first_name . ' ' . $assignment->class->teacher->last_name),
                    'email' => $assignment->class->teacher->email,
                ] : null,
                'max_points'   => $assignment->max_points,
                'due_date'     => $due_formatted,
                'instructions' => $assignment->instructions,
                'attachments'  => $assignment->attachments->map(fn($a) => [
                    'id' => $a->id,
                    'file_path' => $a->file_path,
                    'file_type' => $a->file_type,
                    'url' => $a->file_path ? asset($a->file_path) : null
                ]),
                'submission_summary' => [
                    'total_submissions' => $total,
                    'graded' => $graded,
                    'not_graded' => $not_graded
                ],
                'submissions' => $submissions,
            ],
        ];

        return response()->json($response);
    }


    public function getAllSubmissions($assignmentId)
    {
        $assignment = Assignment::with([
            'class.teacher:id,first_name,last_name,email',
            'submissions' => fn($q) => $q->where('is_archived', 0)->orderBy('created_at', 'desc'),
            'submissions.student:id,first_name,last_name,email',
            'submissions.files' => fn($q) => $q->where('is_archived', 0)
        ])
            ->where('id', $assignmentId)
            ->where('is_archived', 0)
            ->first();

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found'
            ], 404);
        }

        // format submissions
        $submissions = $assignment->submissions->map(function ($s) {
            return [
                'id'            => $s->id,
                'student_name'  => trim($s->student->first_name . ' ' . $s->student->last_name),
                'submitted_date' => $s->created_at->format('n/j/Y'),
                'submitted_time' => $s->created_at->format('g:i:s A'),
                'grade'         => $s->grade !== null ? "{$s->grade}/100" : "Not graded",
                'numeric_grade' => $s->grade,
                'feedback'      => $s->feedback,
                'status'        => $s->status,
                'submission_text' => $s->submission_text ?? null,
                'files' => $s->files->map(fn($f) => [
                    'id' => $f->id,
                    'file_path' => $f->file_path,
                    'file_type' => $f->file_type,
                    'url' => asset($f->file_path)
                ])
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'assignment' => [
                    'id'        => $assignment->id,
                    'title'     => $assignment->title,
                    'topic'     => $assignment->topic,
                    'class_id'  => $assignment->class_id,
                    'teacher' => $assignment->class && $assignment->class->teacher ? [
                        'name'  => trim($assignment->class->teacher->first_name . ' ' . $assignment->class->teacher->last_name),
                        'email' => $assignment->class->teacher->email,
                    ] : null,
                    'total_submissions' => $assignment->submissions->count(),
                ],
                'submissions' => $submissions
            ]
        ]);
    }

    public function sendPrivateComment(Request $request, $submissionId)
    {
        $request->validate([
            'private_comment' => 'required|string|max:2000',
        ]);

        $submission = Submission::where('id', $submissionId)
            ->where('is_archived', 0)
            ->first();

        if (!$submission) {
            return response()->json([
                'success' => false,
                'message' => 'Submission not found.',
            ], 404);
        }

        $user = auth()->user();

        // Student can only edit their own submission
        if ($user->role->role_name === 'student' && $submission->student_id != $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        // Teachers are allowed automatically

        $submission->update([
            'private_comment' => $request->private_comment,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Private comment updated successfully.',
            'data' => $submission,
        ]);
    }

    public function sendComment(Request $request, $submissionId)
    {
        $request->validate([
            'comment' => 'required|string|max:2000',
        ]);

        $submission = Submission::where('id', $submissionId)
            ->where('is_archived', 0)
            ->first();

        if (!$submission) {
            return response()->json([
                'success' => false,
                'message' => 'Submission not found.',
            ], 404);
        }

        $user = auth()->user();

        // Student can only comment on their own submission
        if ($user->role->role_name === 'student' && $submission->student_id != $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $comment = SubmissionComment::create([
            'submission_id' => $submission->id,
            'user_id'       => $user->id,
            'comment'       => $request->comment,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Comment sent successfully.',
            'data' => $comment->load('user:id,first_name,last_name,avatar'),
        ]);
    }


    // ============================================================
    // FILE SAVER (PUBLIC)
    // ============================================================
    private function saveFileToPublic($file, $prefix)
    {
        $directory = public_path('lms_files');
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = $prefix . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $file->move($directory, $filename);

        return 'lms_files/' . $filename;
    }
}
