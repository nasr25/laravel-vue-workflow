<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Survey;
use App\Models\SurveyResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SurveyController extends Controller
{
    /**
     * Get the authenticated user's submitted survey responses
     */
    public function getMyResponses(Request $request)
    {
        $user = $request->user();

        $responses = SurveyResponse::where('user_id', $user->id)
            ->with([
                'survey',
                'request',
                'answers.question',
                'answers.selectedOption',
            ])
            ->orderBy('submitted_at', 'desc')
            ->get();

        return response()->json([
            'responses' => $responses,
        ]);
    }

    /**
     * Get all active surveys for the authenticated user
     */
    public function getActiveSurveys(Request $request)
    {
        $user = $request->user();

        $surveys = Survey::where('is_active', true)
            ->withCount('questions')
            ->get()
            ->map(function ($survey) use ($user) {
                $survey->has_responded = SurveyResponse::where('survey_id', $survey->id)
                    ->where('user_id', $user->id)
                    ->exists();
                return $survey;
            });

        return response()->json([
            'surveys' => $surveys
        ]);
    }

    /**
     * Get an active survey by trigger point (post_submission or post_completion)
     * Returns the survey + list of request_ids the user already responded for
     */
    public function getTriggerSurvey(Request $request, $triggerPoint)
    {
        if (!in_array($triggerPoint, ['post_submission', 'post_completion'])) {
            return response()->json(['message' => 'Invalid trigger point.'], 422);
        }

        $user = $request->user();

        $survey = Survey::where('is_active', true)
            ->where('trigger_point', $triggerPoint)
            ->with(['questions' => function ($query) {
                $query->where('is_active', true)->orderBy('order')->with('options');
            }])
            ->first();

        if (!$survey) {
            return response()->json(['survey' => null]);
        }

        // Get all request_ids this user has already responded for on this survey
        $respondedRequestIds = SurveyResponse::where('survey_id', $survey->id)
            ->where('user_id', $user->id)
            ->whereNotNull('request_id')
            ->pluck('request_id')
            ->toArray();

        return response()->json([
            'survey' => $survey,
            'responded_request_ids' => $respondedRequestIds
        ]);
    }

    /**
     * Get a single active survey with questions and options
     */
    public function getSurvey($id, Request $request)
    {
        $survey = Survey::where('is_active', true)
            ->with(['questions' => function ($query) {
                $query->where('is_active', true)->orderBy('order')->with('options');
            }])
            ->findOrFail($id);

        $requestId = $request->query('request_id');

        $query = SurveyResponse::where('survey_id', $survey->id)
            ->where('user_id', $request->user()->id);

        if ($requestId) {
            $query->where('request_id', $requestId);
        }

        $hasResponded = $query->exists();

        return response()->json([
            'survey' => $survey,
            'has_responded' => $hasResponded
        ]);
    }

    /**
     * Submit survey answers
     */
    public function submitSurvey($id, Request $request)
    {
        $user = $request->user();
        $survey = Survey::where('is_active', true)
            ->with(['questions' => function ($query) {
                $query->where('is_active', true)->with('options');
            }])
            ->findOrFail($id);

        $validated = $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|integer|exists:survey_questions,id',
            'answers.*.option_id' => 'nullable|integer|exists:survey_question_options,id',
            'answers.*.text_answer' => 'nullable|string',
            'request_id' => 'nullable|integer',
        ]);

        $requestId = $validated['request_id'] ?? null;

        // Check if already responded (per request_id if provided)
        $existsQuery = SurveyResponse::where('survey_id', $survey->id)
            ->where('user_id', $user->id);
        if ($requestId) {
            $existsQuery->where('request_id', $requestId);
        } else {
            $existsQuery->whereNull('request_id');
        }

        if ($existsQuery->exists()) {
            return response()->json([
                'message' => 'You have already submitted this survey.'
            ], 400);
        }

        // Validate required questions are answered
        $requiredQuestionIds = $survey->questions
            ->where('is_required', true)
            ->pluck('id')
            ->toArray();

        $answeredQuestionIds = collect($validated['answers'])->pluck('question_id')->toArray();

        foreach ($requiredQuestionIds as $reqId) {
            if (!in_array($reqId, $answeredQuestionIds)) {
                return response()->json([
                    'message' => 'All required questions must be answered.'
                ], 422);
            }

            // Check that required questions have actual answers
            $answer = collect($validated['answers'])->firstWhere('question_id', $reqId);
            $question = $survey->questions->firstWhere('id', $reqId);

            if ($question->question_type === 'text' && empty($answer['text_answer'])) {
                return response()->json([
                    'message' => 'All required questions must be answered.'
                ], 422);
            }

            if (in_array($question->question_type, ['multiple_choice', 'satisfaction']) && empty($answer['option_id'])) {
                return response()->json([
                    'message' => 'All required questions must be answered.'
                ], 422);
            }
        }

        $response = DB::transaction(function () use ($survey, $user, $validated, $requestId) {
            $response = SurveyResponse::create([
                'survey_id' => $survey->id,
                'user_id' => $user->id,
                'request_id' => $requestId,
                'submitted_at' => now(),
            ]);

            foreach ($validated['answers'] as $answerData) {
                $response->answers()->create([
                    'survey_question_id' => $answerData['question_id'],
                    'survey_question_option_id' => $answerData['option_id'] ?? null,
                    'text_answer' => $answerData['text_answer'] ?? null,
                ]);
            }

            return $response;
        });

        return response()->json([
            'message' => 'Survey submitted successfully',
            'response' => $response
        ], 201);
    }
}
