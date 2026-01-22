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
        // Get credentials from query params for easy testing
        $username = $request->input('username');
        $password = $request->input('password');

        if (!$username || !$password) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide username and password',
                'example' => 'GET /api/calendar/test?username=user@domain.com&password=yourpass'
            ], 400);
        }

        Log::info('Quick Test: Starting', ['username' => $username]);

        try {
            // Use proper date format for Exchange - both should be at start of day or end of day
            $startDate = gmdate('Y-m-d\T00:00:00\Z', strtotime('today'));
            $endDate = gmdate('Y-m-d\T23:59:59\Z', strtotime('+7 days'));
            
            Log::info('Quick Test: Using dates', [
                'start' => $startDate,
                'end' => $endDate
            ]);
            
            // Try to get events
            $events = $this->calendarService->getCalendarEvents(
                $username,
                $password,
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
                    'username' => $username,
                    'events_count' => count($events),
                    'events' => array_slice($events, 0, 3) // First 3 events
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Quick Test: Failed', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '✗ Connection failed',
                'error' => $e->getMessage(),
                'troubleshooting' => [
                    'Check your username format (use email: user@domain.com)',
                    'Verify password is correct',
                    'Check server URL in .env: ' . config('services.exchange.server'),
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