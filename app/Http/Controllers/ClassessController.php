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
            'class_code' => 'required|string|max:12|unique:classes,class_code',
            'teacher_id' => 'required|exists:users,id',
            'description' => 'nullable|string',
        ]);

        $class = ClassModel::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Class created successfully.',
            'data' => $class,
        ], 201);
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
}
