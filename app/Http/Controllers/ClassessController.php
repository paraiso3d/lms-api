<?php

namespace App\Http\Controllers;

use App\Models\Classess as ClassModel;
use Illuminate\Http\Request;

class ClassessController extends Controller
{
    /**
     * List all active classes.
     */
    public function listActiveClasses()
    {
        $user = auth()->user(); // current logged-in user

        $query = ClassModel::where('is_archived', 0)
            ->with('teacher')
            ->orderBy('id', 'asc');

        // Role-based filtering
        if ($user->role_name === 'Teacher') {
            // show only classes the teacher created
            $query->where('teacher_id', $user->id);
        } elseif ($user->role_name === 'Student') {
            // show only classes where student is enrolled
            $query->whereHas('students', function ($q) use ($user) {
                $q->where('student_id', $user->id);
            });
        } elseif ($user->role_name !== 'Admin') {
            // everyone else: block entry — unless you want another role
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        $classes = $query->get();

        return response()->json([
            'success' => true,
            'data' => $classes,
        ]);
    }


    public function getClassPeople($classId)
    {
        $class = ClassModel::with([
            'teacher:id,first_name,last_name,email',
            'students:id,first_name,last_name,email'
        ])->find($classId);

        if (!$class) {
            return response()->json([
                'success' => false,
                'message' => 'Class not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'teacher' => $class->teacher ? [
                    'full_name' => $class->teacher->first_name . ' ' . $class->teacher->last_name,
                    'email'     => $class->teacher->email
                ] : null,

                'students' => $class->students->map(function ($student) {
                    return [
                        'full_name' => $student->first_name . ' ' . $student->last_name,
                        'email'     => $student->email
                    ];
                })
            ]
        ]);
    }



    /**
     * Create a new class.
     */
    public function createClass(Request $request)
    {
        $request->validate([
            'class_name' => 'required|string|max:150',
            'section'    => 'nullable|string|max:100',
            'subject'    => 'nullable|string|max:150',
            'room'       => 'nullable|string|max:50',
            'teacher_id' => 'required|exists:users,id',
            'description' => 'nullable|string',
        ]);

        // Auto-generate class code
        $classCode = $this->generateClassCode();

        // Ensure unique (rare, but let's play safe)
        while (ClassModel::where('class_code', $classCode)->exists()) {
            $classCode = $this->generateClassCode();
        }

        $class = ClassModel::create([
            'class_name'  => $request->class_name,
            'section'     => $request->section,
            'subject'     => $request->subject,
            'room'        => $request->room,
            'teacher_id'  => $request->teacher_id,
            'description' => $request->description,
            'class_code'  => $classCode,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Class created successfully.',
            'data' => $class,
        ], 201);
    }



    /**
     * Student joins a class using a class code.
     */
    public function joinClass(Request $request)
    {
        $request->validate([
            'class_code' => 'required|string|exists:classes,class_code',
        ]);

        // Authenticated student
        $student = auth()->user();

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        // Find the class
        $class = ClassModel::where('class_code', $request->class_code)
            ->where('is_archived', 0)
            ->first();

        if (!$class) {
            return response()->json([
                'success' => false,
                'message' => 'Class not found or is archived.',
            ], 404);
        }

        // Check if already joined
        if ($class->students()->where('student_id', $student->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You already joined this class.',
            ], 409);
        }

        // Attach student
        $class->students()->attach($student->id);

        return response()->json([
            'success' => true,
            'message' => 'Successfully joined the class.',
            'data' => $class->load(['teacher', 'students']),
        ]);
    }


    /**
     * Get class details by ID.
     */
    public function getClass($id)
    {
        $class = ClassModel::with(['teacher', 'students'])->find($id);

        if (!$class) {
            return response()->json([
                'success' => false,
                'message' => 'Class not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $class,
        ]);
    }

    /**
     * Update an existing class.
     */
    public function updateClass(Request $request, $id)
    {
        $class = ClassModel::find($id);

        if (!$class) {
            return response()->json([
                'success' => false,
                'message' => 'Class not found.',
            ], 404);
        }

        $request->validate([
            'class_name' => 'required|string|max:150',
            'section'    => 'nullable|string|max:100',
            'subject'    => 'nullable|string|max:150',
            'room'       => 'nullable|string|max:50',
            'class_code' => 'required|string|max:12|unique:classes,class_code,' . $id,
            'teacher_id' => 'required|exists:users,id',
            'description' => 'nullable|string',
        ]);

        $class->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Class updated successfully.',
            'data' => $class,
        ]);
    }

    /**
     * Archive (soft delete) a class.
     */
    public function archiveClass($id)
    {
        $class = ClassModel::find($id);

        if (!$class) {
            return response()->json([
                'success' => false,
                'message' => 'Class not found.',
            ], 404);
        }

        $class->update(['is_archived' => 1]);

        return response()->json([
            'success' => true,
            'message' => 'Class archived successfully.',
        ]);
    }

    /**
     * Restore an archived class.
     */
    public function restoreClass($id)
    {
        $class = ClassModel::find($id);

        if (!$class) {
            return response()->json([
                'success' => false,
                'message' => 'Class not found.',
            ], 404);
        }

        $class->update(['is_archived' => 0]);

        return response()->json([
            'success' => true,
            'message' => 'Class restored successfully.',
            'data' => $class,
        ]);
    }


    public function getClassGrades($classId)
    {
        $class = ClassModel::with([
            'students:id,first_name,last_name,email',
            'assignments.submissions' => fn($q) => $q->where('is_archived', 0),
            'quizzes.submissions'     => fn($q) => $q->where('is_archived', 0),
        ])->find($classId);

        if (!$class) {
            return response()->json([
                'success' => false,
                'message' => 'Class not found.',
            ], 404);
        }

        $students = $class->students->map(function ($student) use ($class) {
            $assignmentTotal = 0;
            $assignmentMax = 0;

            // Assignments
            foreach ($class->assignments as $assignment) {
                $submission = $assignment->submissions->firstWhere('student_id', $student->id);
                if ($submission) {
                    $assignmentTotal += $submission->grade ?? 0;
                }
                $assignmentMax += $assignment->max_points;
            }

            $quizTotal = 0;
            $quizMax = 0;

            // Quizzes
            foreach ($class->quizzes as $quiz) {
                $submission = $quiz->submissions->firstWhere('student_id', $student->id);
                if ($submission) {
                    $quizTotal += $submission->score ?? 0;
                }
                $quizMax += $quiz->total_points;
            }

            $overallPercentage = ($assignmentMax + $quizMax) > 0
                ? round((($assignmentTotal + $quizTotal) / ($assignmentMax + $quizMax)) * 100)
                : null;

            return [
                'student_name' => trim($student->first_name . ' ' . $student->last_name),
                'email'        => $student->email,
                'assignments'  => "{$assignmentTotal}/{$assignmentMax}",
                'quizzes'      => "{$quizTotal}/{$quizMax}",
                'average'      => $overallPercentage !== null ? "{$overallPercentage}%" : '-',
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'class_name' => $class->class_name,
                'grades' => $students,
            ]
        ]);
    }







    /**
     * HELPERS
     */
    private function generateClassCode()
    {
        $prefix = strtoupper(substr(uniqid(), -4)); // 4 random chars
        $numbers = rand(100, 999);

        return $prefix . $numbers; // Example: A3FQ582
    }
}
