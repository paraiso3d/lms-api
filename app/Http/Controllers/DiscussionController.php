<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Discussion;
use App\Models\DiscussionReply;

class DiscussionController extends Controller
{
    // ============================================================
    // CREATE NEW DISCUSSION
    // ============================================================
    public function createDiscussion(Request $request)
    {
        $request->validate([
            'class_id'   => 'required|exists:classes,id',
            'user_id'    => 'required|exists:users,id',
            'title'      => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $discussion = Discussion::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Discussion created successfully',
            'data'    => $discussion
        ]);
    }

    // ============================================================
    // LIST ALL DISCUSSIONS FOR A CLASS
    // ============================================================
    public function getDiscussions($classId)
    {
        $discussions = Discussion::where('class_id', $classId)
            ->where('is_archived', 0)
            ->with(['user', 'replies.user'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $discussions
        ]);
    }

    // ============================================================
    // GET SINGLE DISCUSSION
    // ============================================================
    public function getDiscussion($id)
    {
        $discussion = Discussion::where('id', $id)
            ->where('is_archived', 0)
            ->with(['user', 'replies.user'])
            ->first();

        if (!$discussion) {
            return response()->json([
                'success' => false,
                'message' => 'Discussion not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $discussion
        ]);
    }

    // ============================================================
    // ADD REPLY TO DISCUSSION
    // ============================================================
    public function addReply(Request $request, $discussionId)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'reply'   => 'required|string',
        ]);

        $discussion = Discussion::where('id', $discussionId)
            ->where('is_archived', 0)
            ->first();

        if (!$discussion) {
            return response()->json([
                'success' => false,
                'message' => 'Discussion not found'
            ], 404);
        }

        $reply = DiscussionReply::create([
            'discussion_id' => $discussionId,
            'user_id'       => $request->user_id,
            'reply'         => $request->reply
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Reply added successfully',
            'data'    => $reply
        ]);
    }

    // ============================================================
    // ARCHIVE DISCUSSION
    // ============================================================
    public function archiveDiscussion($id)
    {
        $discussion = Discussion::find($id);

        if (!$discussion) {
            return response()->json([
                'success' => false,
                'message' => 'Discussion not found'
            ], 404);
        }

        $discussion->update(['is_archived' => 1]);
        DiscussionReply::where('discussion_id', $id)->update(['is_archived' => 1]);

        return response()->json([
            'success' => true,
            'message' => 'Discussion archived successfully'
        ]);
    }
}
