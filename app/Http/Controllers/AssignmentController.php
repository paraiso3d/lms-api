<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentAttachment;
use App\Models\Submission;
use App\Models\SubmissionFile;
use Illuminate\Http\Request;

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
            'attachments.*' => 'nullable|file|max:50240', // 50MB
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

        // Save attachments
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('assignment_attachments');

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
            ->with(['attachments' => function ($q) {
                $q->where('is_archived', 0);
            }, 'submissions' => function ($q) {
                $q->where('is_archived', 0);
            }])
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
                'attachments' => function ($q) {
                    $q->where('is_archived', 0);
                },
                'submissions.files' => function ($q) {
                    $q->where('is_archived', 0);
                }
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
    // ARCHIVE ASSIGNMENT (SOFT DELETE)
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

        // Archive assignment
        $assignment->update(['is_archived' => 1]);

        // Archive attachments
        AssignmentAttachment::where('assignment_id', $id)
            ->update(['is_archived' => 1]);

        // Archive submissions
        Submission::where('assignment_id', $id)
            ->update(['is_archived' => 1]);

        // Archive submission files
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
            'student_id' => 'required|exists:users,id',
            'files.*'    => 'nullable|file|max:50240',
        ]);

        $submission = Submission::create([
            'assignment_id' => $assignmentId,
            'student_id'    => $request->student_id,
            'status'        => 'submitted',
        ]);

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('submission_files');

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
}
