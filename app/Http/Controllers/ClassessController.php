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
        $classes = ClassModel::where('is_archived', 0)
            ->with('teacher') // eager load teacher
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $classes,
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
            'student_id' => 'required|exists:users,id'
        ]);

        // Find the class by code
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
        if ($class->students()->where('student_id', $request->student_id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Student already joined this class.',
            ], 409);
        }

        // Attach student to class
        $class->students()->attach($request->student_id);

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
