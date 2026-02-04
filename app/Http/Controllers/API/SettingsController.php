<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    /**
     * Get all settings
     */
    public function index()
    {
        $settings = Settings::all()->groupBy('group');

        return response()->json([
            'settings' => $settings
        ]);
    }

    /**
     * Get public settings (no authentication required)
     */
    public function getPublicSettings()
    {
        $publicKeys = [
            'site_name',
            'site_name_ar',
            'site_description',
            'site_description_ar',
            'logo',
            'favicon',
            'primary_color',
            'secondary_color'
        ];

        $settings = Settings::whereIn('key', $publicKeys)->get();

        $result = [];
        foreach ($settings as $setting) {
            $result[$setting->key] = Settings::castValue($setting->value, $setting->type);
        }

        return response()->json([
            'settings' => $result
        ]);
    }

    /**
     * Get a single setting by key
     */
    public function show($key)
    {
        $setting = Settings::where('key', $key)->first();

        if (!$setting) {
            return response()->json([
                'message' => 'Setting not found'
            ], 404);
        }

        return response()->json([
            'setting' => $setting
        ]);
    }

    /**
     * Update or create a setting
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'key' => 'required|string|max:255',
            'value' => 'nullable',
            'type' => 'required|in:text,image,number,boolean,json',
            'description' => 'nullable|string',
            'group' => 'nullable|string|max:255',
        ]);

        $setting = Settings::updateOrCreate(
            ['key' => $validated['key']],
            [
                'value' => $validated['value'],
                'type' => $validated['type'],
                'description' => $validated['description'] ?? null,
                'group' => $validated['group'] ?? 'general'
            ]
        );

        // Clear cache
        \Cache::forget("setting_{$validated['key']}");

        return response()->json([
            'message' => 'Setting saved successfully',
            'setting' => $setting
        ]);
    }

    /**
     * Update multiple settings at once
     */
    public function updateBulk(Request $request)
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'nullable',
        ]);

        foreach ($validated['settings'] as $settingData) {
            $setting = Settings::where('key', $settingData['key'])->first();

            if ($setting) {
                $setting->update(['value' => $settingData['value']]);
                \Cache::forget("setting_{$settingData['key']}");
            }
        }

        return response()->json([
            'message' => 'Settings updated successfully'
        ]);
    }

    /**
     * Upload logo or other images
     */
    public function uploadImage(Request $request)
    {
        $validated = $request->validate([
            'key' => 'required|string',
            'image' => 'required|file|mimes:png,jpg,jpeg,svg,webp|max:2048',
        ]);

        // Delete old image if exists
        $oldSetting = Settings::where('key', $validated['key'])->first();
        if ($oldSetting && $oldSetting->value) {
            Storage::disk('public')->delete($oldSetting->value);
        }

        // Store new image
        $path = $request->file('image')->store('settings', 'public');

        // Update setting
        $setting = Settings::updateOrCreate(
            ['key' => $validated['key']],
            [
                'value' => $path,
                'type' => 'image'
            ]
        );

        \Cache::forget("setting_{$validated['key']}");

        return response()->json([
            'message' => 'Image uploaded successfully',
            'setting' => $setting,
            'url' => Storage::url($path)
        ]);
    }

    /**
     * Delete a setting
     */
    public function destroy($key)
    {
        $setting = Settings::where('key', $key)->first();

        if (!$setting) {
            return response()->json([
                'message' => 'Setting not found'
            ], 404);
        }

        // Delete image file if it's an image type
        if ($setting->type === 'image' && $setting->value) {
            Storage::disk('public')->delete($setting->value);
        }

        $setting->delete();
        \Cache::forget("setting_{$key}");

        return response()->json([
            'message' => 'Setting deleted successfully'
        ]);
    }
}
