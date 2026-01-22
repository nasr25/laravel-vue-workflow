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
     * Get calendar events for a user
     * POST /api/calendar/events
     */
    public function getEvents(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        Log::info('CalendarController: getEvents called', [
            'username' => $validated['username'],
            'has_password' => !empty($validated['password'])
        ]);

        try {
            $options = [];
            
            if (isset($validated['start_date'])) {
                $options['startDate'] = date('c', strtotime($validated['start_date']));
            }
            
            if (isset($validated['end_date'])) {
                $options['endDate'] = date('c', strtotime($validated['end_date']));
            }

            Log::info('CalendarController: Calling calendarService', [
                'username' => $validated['username'],
                'options' => $options
            ]);

            $events = $this->calendarService->getCalendarEvents(
                $validated['username'],
                $validated['password'],
                $options
            );

            Log::info('CalendarController: Successfully retrieved events', [
                'username' => $validated['username'],
                'events_count' => count($events)
            ]);

            return response()->json([
                'success' => true,
                'data' => $events,
                'count' => count($events)
            ]);

        } catch (\Exception $e) {
            Log::error('CalendarController: Failed to get events', [
                'username' => $validated['username'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all calendar events for a user (with pagination)
     * POST /api/calendar/all-events
     */
    public function getAllEvents(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        try {
            $events = $this->calendarService->getAllCalendarEvents(
                $validated['username'],
                $validated['password']
            );

            return response()->json([
                'success' => true,
                'data' => $events,
                'count' => count($events)
            ]);

        } catch (\Exception $e) {
            Log::error('CalendarController: Failed to get all events', [
                'username' => $validated['username'],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific event
     * POST /api/calendar/event
     */
    public function getEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'item_id' => 'required|string',
            'change_key' => 'required|string',
        ]);

        try {
            $event = $this->calendarService->getEvent(
                $validated['username'],
                $validated['password'],
                $validated['item_id'],
                $validated['change_key']
            );

            if (!$event) {
                return response()->json([
                    'success' => false,
                    'message' => 'Event not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $event
            ]);

        } catch (\Exception $e) {
            Log::error('CalendarController: Failed to get event', [
                'username' => $validated['username'],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new event
     * POST /api/calendar/create-event
     */
    public function createEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'subject' => 'required|string',
            'start' => 'required|date',
            'end' => 'required|date|after:start',
            'location' => 'nullable|string',
            'body' => 'nullable|string',
            'is_all_day' => 'nullable|boolean',
        ]);

        try {
            $eventData = [
                'subject' => $validated['subject'],
                'start' => date('c', strtotime($validated['start'])),
                'end' => date('c', strtotime($validated['end'])),
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
            Log::error('CalendarController: Failed to create event', [
                'username' => $validated['username'],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an event
     * POST /api/calendar/update-event
     */
    public function updateEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'item_id' => 'required|string',
            'change_key' => 'required|string',
            'subject' => 'nullable|string',
            'start' => 'nullable|date',
            'end' => 'nullable|date',
            'location' => 'nullable|string',
            'body' => 'nullable|string',
        ]);

        try {
            $eventData = [];

            if (isset($validated['subject'])) {
                $eventData['subject'] = $validated['subject'];
            }

            if (isset($validated['start'])) {
                $eventData['start'] = date('c', strtotime($validated['start']));
            }

            if (isset($validated['end'])) {
                $eventData['end'] = date('c', strtotime($validated['end']));
            }

            if (isset($validated['location'])) {
                $eventData['location'] = $validated['location'];
            }

            if (isset($validated['body'])) {
                $eventData['body'] = $validated['body'];
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
            Log::error('CalendarController: Failed to update event', [
                'username' => $validated['username'],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an event
     * POST /api/calendar/delete-event
     */
    public function deleteEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'required|string',
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
            Log::error('CalendarController: Failed to delete event', [
                'username' => $validated['username'],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
use App\Http\Controllers\CalendarController;

Route::prefix('calendar')->group(function () {
    // Get events
    Route::post('events', [CalendarController::class, 'getEvents']);
    Route::post('all-events', [CalendarController::class, 'getAllEvents']);
    Route::post('event', [CalendarController::class, 'getEvent']);
    
    // Create, update, delete
    Route::post('create-event', [CalendarController::class, 'createEvent']);
    Route::post('update-event', [CalendarController::class, 'updateEvent']);
    Route::post('delete-event', [CalendarController::class, 'deleteEvent']);
});