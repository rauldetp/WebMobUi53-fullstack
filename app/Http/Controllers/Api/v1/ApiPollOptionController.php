<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Poll;
use App\Models\PollOption;
use Illuminate\Http\Request;

class ApiPollOptionController extends Controller
{
    /**
     * Store a new option for the specified poll.
     */
    public function store(Request $request, int $pollId)
    {
        $poll = Poll::where('id', $pollId)->where('user_id', $request->user()->id)->first();

        if (!$poll) {
            return response()->json(['message' => 'Poll not found.'], 404);
        }

        $validated = $request->validate([
            'label' => 'required|string|max:255',
        ]);

        $option = new PollOption();
        $option->label = $validated['label'];
        $option->poll()->associate($poll);
        $option->save();

        return response()->json($option, 201);
    }

    /**
     * Update the specified option of the authenticated user's poll.
     */
    public function update(Request $request, int $pollId, int $optionId)
    {
        $poll = Poll::where('id', $pollId)->where('user_id', $request->user()->id)->first();

        if (!$poll) {
            return response()->json(['message' => 'Poll not found.'], 404);
        }

        $option = PollOption::where('id', $optionId)->where('poll_id', $pollId)->first();

        if (!$option) {
            return response()->json(['message' => 'Option not found.'], 404);
        }

        $validated = $request->validate([
            'label' => 'required|string|max:255',
        ]);

        $option->label = $validated['label'];
        $option->save();

        return response()->json($option, 200);
    }

    /**
     * Remove the specified option from the authenticated user's poll.
     */
    public function destroy(Request $request, int $pollId, int $optionId)
    {
        $poll = Poll::where('id', $pollId)->where('user_id', $request->user()->id)->first();

        if (!$poll) {
            return response()->json(['message' => 'Poll not found.'], 404);
        }

        $option = PollOption::where('id', $optionId)->where('poll_id', $pollId)->first();

        if (!$option) {
            return response()->json(['message' => 'Option not found.'], 404);
        }

        $option->delete();

        return response()->json(['message' => 'success'], 200);
    }
}
