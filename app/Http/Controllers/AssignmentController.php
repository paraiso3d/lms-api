<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentAttachment;
use App\Models\Submission;
use App\Models\SubmissionFile;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Str;

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
            'topic'         => 'nullable|string|max:255',
            'attachments.*' => 'nullable|file|max:50240',
        ]);

        $assignment = Assignment::create([
            'class_id'     => $request->class_id,
            'title'        => $request->title,
            'instructions' => $request->instructions,
            'max_points'   => $request->max_points,
            'due_date'     => $request->due_date,
            'topic'        => $request->topic,
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
        $assignments = Assignment::where('class_id', $classId)
            ->where('is_archived', 0)
            ->with([
                'attachments' => fn($q) => $q->where('is_archived', 0),
                'submissions' => fn($q) => $q->where('is_archived', 0)
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $assignments,
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

        $assignment->update($request->only([
            'title',
            'instructions',
            'max_points',
            'due_date',
            'topic',
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
        ]);

        $student = auth()->user();

        // Optional: block non-students
        if ($student->role->role_name !== 'student') {
            return response()->json([
                'success' => false,
                'message' => 'Only students can submit assignments.',
            ], 403);
        }

        $submission = Submission::create([
            'assignment_id' => $assignmentId,
            'student_id'    => $student->id,
            'status'        => 'submitted',
        ]);

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
            'message' => 'Submitted successfully!',
            'data'    => $submission->load('files'),
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
