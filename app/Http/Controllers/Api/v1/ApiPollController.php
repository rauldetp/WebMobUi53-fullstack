<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Poll;
use App\Models\PollVote;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ApiPollController extends Controller
{
    /**
     * Store a newly created poll for the authenticated user.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'                  => 'nullable|string|max:255',
            'question'               => 'required|string|max:500',
            'allow_multiple_choices' => 'boolean',
            'allow_vote_change'      => 'boolean',
            'results_public'         => 'boolean',
            'duration'               => 'nullable|integer|min:1',
        ]);

        do {
            $token = Str::random(32);
        } while (Poll::where('secret_token', $token)->exists());

        $poll = new Poll();
        $poll->title                  = $validated['title'] ?? null;
        $poll->question               = $validated['question'];
        $poll->allow_multiple_choices = $validated['allow_multiple_choices'] ?? false;
        $poll->allow_vote_change      = $validated['allow_vote_change'] ?? false;
        $poll->results_public         = $validated['results_public'] ?? false;
        $poll->duration               = $validated['duration'] ?? null;
        $poll->is_draft               = true;
        $poll->secret_token           = $token;
        $poll->user()->associate($request->user());
        $poll->save();

        return response()->json($poll, 201);
    }

    /**
     * Update the specified poll owned by the authenticated user.
     */
    public function update(Request $request, int $id)
    {
        $poll = Poll::where('id', $id)->where('user_id', $request->user()->id)->first();

        if (!$poll) {
            return response()->json(['message' => 'Poll not found.'], 404);
        }

        $validated = $request->validate([
            'title'                  => 'nullable|string|max:255',
            'question'               => 'sometimes|required|string|max:500',
            'allow_multiple_choices' => 'boolean',
            'allow_vote_change'      => 'boolean',
            'results_public'         => 'boolean',
            'duration'               => 'nullable|integer|min:1',
        ]);

        $poll->fill($validated);
        $poll->save();

        return response()->json($poll, 200);
    }

    /**
     * Start the specified poll owned by the authenticated user.
     */
    public function start(Request $request, int $id)
    {
        $poll = Poll::where('id', $id)->where('user_id', $request->user()->id)->first();

        if (!$poll) {
            return response()->json(['message' => 'Poll not found.'], 404);
        }

        if (!$poll->is_draft) {
            return response()->json(['message' => 'Poll is already started.'], 422);
        }

        $poll->is_draft   = false;
        $poll->started_at = Carbon::now();

        if ($poll->duration) {
            $poll->ends_at = Carbon::now()->addSeconds($poll->duration);
        }

        $poll->save();

        return response()->json($poll, 200);
    }

    /**
     * Display a listing of the authenticated user's polls.
     */
    public function index(Request $request)
    {
        $polls = $request->user()->polls()->with('options')->orderBy('created_at', 'desc')->get();

        return $polls;
    }

    /**
     * Display the specified poll by its secret token.
     * Vote counts are included only if results are public or the requester is the owner.
     */
    public function show(Request $request, string $token)
    {
        $poll = Poll::with('options')->where('secret_token', $token)->first();

        if (!$poll) {
            return response()->json(['message' => 'Poll not found.'], 404);
        }

        $user = $request->user();
        $isOwner = $user && $user->id === $poll->user_id;
        $showResults = $poll->results_public || $isOwner;

        if ($showResults) {
            $poll->loadCount('votes');
            $poll->options->each(function ($option) {
                $option->loadCount('votes');
            });
        }

        return response()->json([
            'poll'         => $poll,
            'show_results' => $showResults,
        ]);
    }

    /**
     * Submit a vote for the specified poll.
     */
    public function vote(Request $request, string $token)
    {
        $poll = Poll::with('options')->where('secret_token', $token)->first();

        if (!$poll) {
            return response()->json(['message' => 'Poll not found.'], 404);
        }

        if ($poll->is_draft) {
            return response()->json(['message' => 'Poll is not started yet.'], 422);
        }

        if ($poll->ends_at && Carbon::now()->isAfter($poll->ends_at)) {
            return response()->json(['message' => 'Poll is closed.'], 422);
        }

        $validated = $request->validate([
            'option_ids'   => 'required|array|min:1',
            'option_ids.*' => 'integer',
        ]);

        $optionIds = $validated['option_ids'];

        if (!$poll->allow_multiple_choices && count($optionIds) > 1) {
            return response()->json(['message' => 'This poll only allows a single choice.'], 422);
        }

        $validOptionIds = $poll->options->pluck('id')->toArray();
        foreach ($optionIds as $optionId) {
            if (!in_array($optionId, $validOptionIds)) {
                return response()->json(['message' => 'Invalid option.'], 422);
            }
        }

        $userId = $request->user()->id;

        $existingVotes = PollVote::where('poll_id', $poll->id)
            ->where('user_id', $userId)
            ->pluck('poll_option_id')
            ->toArray();

        if (!$poll->allow_multiple_choices && count($existingVotes) > 0) {
            return response()->json(['message' => 'You have already voted on this poll.'], 422);
        }

        foreach ($optionIds as $optionId) {
            if (in_array($optionId, $existingVotes)) {
                continue;
            }
            $vote = new PollVote();
            $vote->poll()->associate($poll);
            $vote->user()->associate($request->user());
            $vote->poll_option_id = $optionId;
            $vote->save();
        }

        return response()->json(['message' => 'Vote submitted.'], 201);
    }

    /**
     * Remove the specified poll.
     */
    public function remove(Request $request, int $id)
    {
        $poll = Poll::where('id', $id)->where('user_id', $request->user()->id)->first();

        if (!$poll) {
            return response()->json(['message' => 'Poll not found.'], 404);
        }

        $poll->delete();

        return response()->json(['message' => 'success'], 200);
    }
}
