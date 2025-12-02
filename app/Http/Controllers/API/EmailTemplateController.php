<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Services\EmailNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmailTemplateController extends Controller
{
    protected $emailService;

    public function __construct(EmailNotificationService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Get all email templates
     */
    public function index()
    {
        $templates = EmailTemplate::orderBy('event_type')->get();

        // Add available placeholders for each template
        $templates = $templates->map(function ($template) {
            $template->available_placeholders = $this->emailService->getAvailablePlaceholders($template->event_type);
            return $template;
        });

        return response()->json([
            'templates' => $templates
        ]);
    }

    /**
     * Get a single email template
     */
    public function show($id)
    {
        $template = EmailTemplate::findOrFail($id);
        $template->available_placeholders = $this->emailService->getAvailablePlaceholders($template->event_type);

        return response()->json([
            'template' => $template
        ]);
    }

    /**
     * Update an email template
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'subject_en' => 'required|string|max:255',
            'subject_ar' => 'required|string|max:255',
            'body_en' => 'required|string',
            'body_ar' => 'required|string',
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $template = EmailTemplate::findOrFail($id);
        $template->update($request->only([
            'subject_en',
            'subject_ar',
            'body_en',
            'body_ar',
            'description',
            'is_active'
        ]));

        return response()->json([
            'message' => 'Email template updated successfully',
            'template' => $template
        ]);
    }

    /**
     * Toggle template active status
     */
    public function toggleStatus($id)
    {
        $template = EmailTemplate::findOrFail($id);
        $template->is_active = !$template->is_active;
        $template->save();

        return response()->json([
            'message' => 'Template status updated',
            'template' => $template
        ]);
    }

    /**
     * Test email template by sending a test email
     */
    public function sendTest(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'recipient_email' => 'required|email',
            'test_data' => 'array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $template = EmailTemplate::findOrFail($id);

        // Create a test user object
        $testUser = new \App\Models\User();
        $testUser->email = $request->recipient_email;
        $testUser->name = 'Test User';

        // Default test data
        $testData = array_merge([
            'user_name' => 'Test User',
            'request_id' => '12345',
            'request_title' => 'Test Request Title',
            'status' => 'Pending',
            'created_at' => now()->format('Y-m-d H:i:s'),
            'comments' => 'This is a test email',
            'language' => $request->language ?? 'en'
        ], $request->test_data ?? []);

        $success = $this->emailService->sendNotification(
            $template->event_type,
            $testUser,
            $testData
        );

        if ($success) {
            return response()->json([
                'message' => 'Test email sent successfully'
            ]);
        }

        return response()->json([
            'message' => 'Failed to send test email'
        ], 500);
    }

    /**
     * Get email configuration status
     */
    public function getEmailConfig()
    {
        $configured = !empty(config('mail.mailers.smtp.host'));

        return response()->json([
            'configured' => $configured,
            'driver' => config('mail.default'),
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name')
        ]);
    }
}
