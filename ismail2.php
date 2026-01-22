<?php

namespace App\Http\Controllers;

use App\Services\OutlookCalendarService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CalendarController extends Controller
{
    private $calendarService;

    public function __construct(OutlookCalendarService $calendarService)
    {
        $this->calendarService = $calendarService;
    }

    /**
     * Get calendar events for a user
     * GET /api/calendar/events/{userEmail}
     */
    public function getEvents(Request $request, string $userEmail): JsonResponse
    {
        Log::info('CalendarController: getEvents called', [
            'user_email' => $userEmail,
            'request_params' => $request->all()
        ]);

        try {
            $options = [];
            
            if ($request->has('start_date')) {
                $options['startDate'] = $request->input('start_date');
            }
            
            if ($request->has('end_date')) {
                $options['endDate'] = $request->input('end_date');
            }
            
            if ($request->has('limit')) {
                $options['limit'] = $request->input('limit');
            }

            // Get password from request (in production, get from secure storage)
            $password = $request->input('password');
            
            if (!$password) {
                Log::warning('CalendarController: No password provided');
                return response()->json([
                    'success' => false,
                    'message' => 'Password is required'
                ], 400);
            }

            Log::info('CalendarController: Calling calendarService', [
                'user_email' => $userEmail,
                'options' => $options
            ]);

            $events = $this->calendarService->getCalendarEvents($userEmail, $password, $options);

            Log::info('CalendarController: Successfully retrieved events', [
                'user_email' => $userEmail,
                'events_count' => count($events)
            ]);

            return response()->json([
                'success' => true,
                'data' => $events,
                'count' => count($events)
            ]);

        } catch (\Exception $e) {
            Log::error('CalendarController: Failed to get events', [
                'user_email' => $userEmail,
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
     * Get today's events
     * GET /api/calendar/today/{userEmail}
     */
    public function getTodayEvents(string $userEmail): JsonResponse
    {
        try {
            $events = $this->calendarService->getTodayEvents($userEmail);

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
     * Get upcoming events
     * GET /api/calendar/upcoming/{userEmail}
     */
    public function getUpcomingEvents(Request $request, string $userEmail): JsonResponse
    {
        try {
            $days = $request->input('days', 7);
            $events = $this->calendarService->getUpcomingEvents($userEmail, $days);

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
     * Get a specific event
     * GET /api/calendar/events/{userEmail}/{eventId}
     */
    public function getEvent(string $userEmail, string $eventId): JsonResponse
    {
        try {
            $event = $this->calendarService->getEvent($userEmail, $eventId);

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
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new event
     * POST /api/calendar/events/{userEmail}
     */
    public function createEvent(Request $request, string $userEmail): JsonResponse
    {
        $validated = $request->validate([
            'subject' => 'required|string',
            'start_date_time' => 'required|date',
            'end_date_time' => 'required|date|after:start_date_time',
            'location' => 'nullable|string',
            'body' => 'nullable|string',
            'time_zone' => 'nullable|string',
            'is_all_day' => 'nullable|boolean',
            'attendees' => 'nullable|array',
            'attendees.*.email' => 'required|email'
        ]);

        try {
            $eventData = [
                'subject' => $validated['subject'],
                'start' => [
                    'dateTime' => $validated['start_date_time'],
                    'timeZone' => $validated['time_zone'] ?? 'UTC'
                ],
                'end' => [
                    'dateTime' => $validated['end_date_time'],
                    'timeZone' => $validated['time_zone'] ?? 'UTC'
                ]
            ];

            if (isset($validated['location'])) {
                $eventData['location'] = [
                    'displayName' => $validated['location']
                ];
            }

            if (isset($validated['body'])) {
                $eventData['body'] = [
                    'contentType' => 'HTML',
                    'content' => $validated['body']
                ];
            }

            if (isset($validated['is_all_day'])) {
                $eventData['isAllDay'] = $validated['is_all_day'];
            }

            if (isset($validated['attendees'])) {
                $eventData['attendees'] = array_map(function ($attendee) {
                    return [
                        'emailAddress' => [
                            'address' => $attendee['email'],
                            'name' => $attendee['name'] ?? ''
                        ],
                        'type' => 'required'
                    ];
                }, $validated['attendees']);
            }

            $event = $this->calendarService->createEvent($userEmail, $eventData);

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
     * Update an event
     * PUT /api/calendar/events/{userEmail}/{eventId}
     */
    public function updateEvent(Request $request, string $userEmail, string $eventId): JsonResponse
    {
        $validated = $request->validate([
            'subject' => 'nullable|string',
            'start_date_time' => 'nullable|date',
            'end_date_time' => 'nullable|date',
            'location' => 'nullable|string',
            'body' => 'nullable|string',
            'time_zone' => 'nullable|string'
        ]);

        try {
            $eventData = [];

            if (isset($validated['subject'])) {
                $eventData['subject'] = $validated['subject'];
            }

            if (isset($validated['start_date_time'])) {
                $eventData['start'] = [
                    'dateTime' => $validated['start_date_time'],
                    'timeZone' => $validated['time_zone'] ?? 'UTC'
                ];
            }

            if (isset($validated['end_date_time'])) {
                $eventData['end'] = [
                    'dateTime' => $validated['end_date_time'],
                    'timeZone' => $validated['time_zone'] ?? 'UTC'
                ];
            }

            if (isset($validated['location'])) {
                $eventData['location'] = [
                    'displayName' => $validated['location']
                ];
            }

            if (isset($validated['body'])) {
                $eventData['body'] = [
                    'contentType' => 'HTML',
                    'content' => $validated['body']
                ];
            }

            $event = $this->calendarService->updateEvent($userEmail, $eventId, $eventData);

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
     * Delete an event
     * DELETE /api/calendar/events/{userEmail}/{eventId}
     */
    public function deleteEvent(string $userEmail, string $eventId): JsonResponse
    {
        try {
            $this->calendarService->deleteEvent($userEmail, $eventId);

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

use App\Services\ExchangeCalendarService;
use Illuminate\Support\Facades\Log;

Route::get('/test-exchange-auth', function() {
    // Test different username formats
    $testCases = [
        [
            'username' => 'DOMAIN\username',  // Replace with actual
            'password' => 'password',          // Replace with actual
            'description' => 'Domain\Username format'
        ],
        [
            'username' => 'username@domain.local',
            'password' => 'password',
            'description' => 'UPN format'
        ],
        [
            'username' => 'username@company.com',
            'password' => 'password',
            'description' => 'Email format'
        ],
    ];

    $results = [];
    
    foreach ($testCases as $index => $test) {
        Log::info("========== TEST CASE {$index}: {$test['description']} ==========");
        
        try {
            $service = new ExchangeCalendarService();
            $events = $service->getCalendarEvents(
                $test['username'],
                $test['password'],
                [
                    'startDate' => date('c'),
                    'endDate' => date('c', strtotime('+7 days'))
                ]
            );
            
            $results[] = [
                'format' => $test['description'],
                'username' => $test['username'],
                'status' => 'SUCCESS',
                'events_count' => count($events)
            ];
            
            Log::info("TEST SUCCESS", [
                'format' => $test['description'],
                'events_count' => count($events)
            ]);
            
        } catch (\Exception $e) {
            $results[] = [
                'format' => $test['description'],
                'username' => $test['username'],
                'status' => 'FAILED',
                'error' => $e->getMessage()
            ];
            
            Log::error("TEST FAILED", [
                'format' => $test['description'],
                'error' => $e->getMessage()
            ]);
        }
        
        Log::info("========== END TEST CASE {$index} ==========");
    }
    
    return response()->json([
        'message' => 'Check logs at storage/logs/laravel.log',
        'results' => $results
    ]);
});