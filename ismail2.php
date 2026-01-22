<?php

namespace App\Http\Controllers;

use App\Services\ExchangeCalendarService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CalendarController extends Controller
{
    private $calendarService;

    public function __construct(ExchangeCalendarService $calendarService)
    {
        $this->calendarService = $calendarService;
    }

    /**
     * Quick test endpoint - GET /api/calendar/test
     */
    public function quickTest(Request $request): JsonResponse
    {
        // Service account credentials (should be in .env in production)
        $serviceUsername = $request->input('service_username') ?? env('EXCHANGE_SERVICE_USERNAME');
        $servicePassword = $request->input('service_password') ?? env('EXCHANGE_SERVICE_PASSWORD');
        
        // Target user email
        $targetEmail = $request->input('target_email') ?? $request->input('username');

        if (!$serviceUsername || !$servicePassword) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide service account credentials',
                'example' => 'GET /api/calendar/test?service_username=service@domain.com&service_password=pass&target_email=user@domain.com'
            ], 400);
        }

        if (!$targetEmail) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide target_email parameter',
                'example' => 'GET /api/calendar/test?service_username=service@domain.com&service_password=pass&target_email=user@domain.com'
            ], 400);
        }

        Log::info('Quick Test: Starting', [
            'service_account' => $serviceUsername,
            'target_user' => $targetEmail
        ]);

        try {
            $startDate = gmdate('Y-m-d\T00:00:00\Z', strtotime('today'));
            $endDate = gmdate('Y-m-d\T23:59:59\Z', strtotime('+7 days'));
            
            Log::info('Quick Test: Using dates', [
                'start' => $startDate,
                'end' => $endDate
            ]);
            
            $events = $this->calendarService->getCalendarEvents(
                $serviceUsername,
                $servicePassword,
                $targetEmail,
                [
                    'startDate' => $startDate,
                    'endDate' => $endDate
                ]
            );

            return response()->json([
                'success' => true,
                'message' => '✓ Connection successful!',
                'data' => [
                    'server' => config('services.exchange.server'),
                    'service_account' => $serviceUsername,
                    'target_user' => $targetEmail,
                    'events_count' => count($events),
                    'events' => array_slice($events, 0, 5) // First 5 events
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Quick Test: Failed', [
                'service_account' => $serviceUsername,
                'target_user' => $targetEmail,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '✗ Connection failed',
                'error' => $e->getMessage(),
                'troubleshooting' => [
                    'Check service account credentials',
                    'Verify service account has impersonation rights',
                    'Check target email is correct',
                    'Check logs: storage/logs/laravel.log'
                ]
            ], 500);
        }
    }

    /**
     * Get calendar events - POST /api/calendar/events
     */
    public function getEvents(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'required|email',
            'password' => 'required|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        try {
            $options = [];
            
            if (isset($validated['start_date'])) {
                $options['startDate'] = date('Y-m-d\TH:i:s\Z', strtotime($validated['start_date']));
            }
            
            if (isset($validated['end_date'])) {
                $options['endDate'] = date('Y-m-d\TH:i:s\Z', strtotime($validated['end_date']));
            }

            $events = $this->calendarService->getCalendarEvents(
                $validated['username'],
                $validated['password'],
                $options
            );

            return response()->json([
                'success' => true,
                'data' => $events,
                'count' => count($events)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create event - POST /api/calendar/create
     */
    public function createEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'required|email',
            'password' => 'required|string',
            'subject' => 'required|string|max:255',
            'start' => 'required|date',
            'end' => 'required|date|after:start',
            'location' => 'nullable|string|max:255',
            'body' => 'nullable|string',
            'is_all_day' => 'nullable|boolean',
        ]);

        try {
            $eventData = [
                'subject' => $validated['subject'],
                'start' => date('Y-m-d\TH:i:s\Z', strtotime($validated['start'])),
                'end' => date('Y-m-d\TH:i:s\Z', strtotime($validated['end'])),
            ];

            if (isset($validated['location'])) {
                $eventData['location'] = $validated['location'];
            }

            if (isset($validated['body'])) {
                $eventData['body'] = $validated['body'];
            }

            if (isset($validated['is_all_day'])) {
                $eventData['is_all_day'] = $validated['is_all_day'];
            }

            $event = $this->calendarService->createEvent(
                $validated['username'],
                $validated['password'],
                $eventData
            );

            return response()->json([
                'success' => true,
                'message' => 'Event created successfully',
                'data' => $event
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update event - POST /api/calendar/update
     */
    public function updateEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'required|email',
            'password' => 'required|string',
            'item_id' => 'required|string',
            'change_key' => 'required|string',
            'subject' => 'nullable|string|max:255',
            'start' => 'nullable|date',
            'end' => 'nullable|date',
            'location' => 'nullable|string|max:255',
        ]);

        try {
            $eventData = [];

            if (isset($validated['subject'])) {
                $eventData['subject'] = $validated['subject'];
            }

            if (isset($validated['start'])) {
                $eventData['start'] = date('Y-m-d\TH:i:s\Z', strtotime($validated['start']));
            }

            if (isset($validated['end'])) {
                $eventData['end'] = date('Y-m-d\TH:i:s\Z', strtotime($validated['end']));
            }

            if (isset($validated['location'])) {
                $eventData['location'] = $validated['location'];
            }

            $event = $this->calendarService->updateEvent(
                $validated['username'],
                $validated['password'],
                $validated['item_id'],
                $validated['change_key'],
                $eventData
            );

            return response()->json([
                'success' => true,
                'message' => 'Event updated successfully',
                'data' => $event
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete event - POST /api/calendar/delete
     */
    public function deleteEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'required|email',
            'password' => 'required|string',
            'item_id' => 'required|string',
            'change_key' => 'required|string',
        ]);

        try {
            $this->calendarService->deleteEvent(
                $validated['username'],
                $validated['password'],
                $validated['item_id'],
                $validated['change_key']
            );

            return response()->json([
                'success' => true,
                'message' => 'Event deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}