<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserSettingsController extends Controller
{
    /**
     * Get user settings
     */
    public function getSettings(Request $request)
    {
        try {
            $user = Auth::user();

            $userSetting = UserSetting::where('user_id', $user->id)->first();

            if (!$userSetting) {
                // Return default settings
                return response()->json([
                    'success' => true,
                    'settings' => [
                        'email' => [
                            'request_created' => true,
                            'request_status_changed' => true,
                            'request_assigned' => true,
                            'request_approved' => true,
                            'request_rejected' => true,
                            'request_completed' => true
                        ],
                        'notification' => [
                            'request_created' => true,
                            'request_status_changed' => true,
                            'request_assigned' => true,
                            'request_approved' => true,
                            'request_rejected' => true,
                            'request_completed' => true
                        ]
                    ]
                ], 200);
            }

            return response()->json([
                'success' => true,
                'settings' => json_decode($userSetting->settings, true)
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Get user settings error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch settings'
            ], 500);
        }
    }

    /**
     * Save user settings
     */
    public function saveSettings(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'settings' => 'required|array',
                'settings.notification' => 'required|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();

            // Prepare settings with forced email notifications (always enabled)
            $settings = $request->settings;
            $settings['email'] = [
                'request_created' => true,
                'request_status_changed' => true,
                'request_assigned' => true,
                'request_approved' => true,
                'request_rejected' => true,
                'request_completed' => true
            ];

            $userSetting = UserSetting::updateOrCreate(
                ['user_id' => $user->id],
                ['settings' => json_encode($settings)]
            );

            return response()->json([
                'success' => true,
                'message' => 'Settings saved successfully',
                'settings' => json_decode($userSetting->settings, true)
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Save user settings error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save settings'
            ], 500);
        }
    }
}
