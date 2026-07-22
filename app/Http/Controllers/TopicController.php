<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use Illuminate\Http\Request;
use App\Models\Classess;


class TopicController extends Controller
{
    // ============================================================
    // CREATE TOPIC
    // ============================================================
    public function createTopic(Request $request)
    {
        $request->validate([
            'topic_name' => 'required|string|max:255|unique:topics,topic_name',
            'class_id'   => 'required|exists:classes,id',
        ]);

        // Create the topic
        $topic = Topic::create([
            'topic_name' => $request->topic_name,
        ]);

        // Assign the topic to the class
        $class = Classess::findOrFail($request->class_id);
        $class->topics()->attach($topic->id);

        return response()->json([
            'success' => true,
            'message' => 'Topic created and assigned to class successfully.',
            'data' => [
                'topic' => $topic,
                'class_id' => $class->id,
            ],
        ], 201);
    }
    // ============================================================
    // GET ALL TOPICS
    // ============================================================
    public function getTopics()
    {
        $topics = Topic::where('is_archived', 0)
            ->orderBy('topic_name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $topics,
        ]);
    }

    // ============================================================
    // GET SINGLE TOPIC
    // ============================================================
    public function getTopic($id)
    {
        $topic = Topic::where('id', $id)
            ->where('is_archived', 0)
            ->first();

        if (!$topic) {
            return response()->json([
                'success' => false,
                'message' => 'Topic not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $topic,
        ]);
    }

    // ============================================================
    // UPDATE TOPIC
    // ============================================================
    public function updateTopic(Request $request, $id)
    {
        $topic = Topic::where('id', $id)
            ->where('is_archived', 0)
            ->first();

        if (!$topic) {
            return response()->json([
                'success' => false,
                'message' => 'Topic not found.',
            ], 404);
        }

        $request->validate([
            'topic_name' => 'required|string|max:255|unique:topics,topic_name,' . $id,
        ]);

        $topic->update([
            'topic_name' => $request->topic_name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Topic updated successfully.',
            'data' => $topic,
        ]);
    }

    // ============================================================
    // ARCHIVE TOPIC
    // ============================================================
    public function deleteTopic($id)
    {
        $topic = Topic::find($id);

        if (!$topic) {
            return response()->json([
                'success' => false,
                'message' => 'Topic not found.',
            ], 404);
        }

        $topic->update([
            'is_archived' => 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Topic archived successfully.',
        ]);
    }

    public function dropdownTopics()
    {
        $topics = Topic::where('is_archived', 0)
            ->orderBy('topic_name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $topics,
        ]);
    }
}
