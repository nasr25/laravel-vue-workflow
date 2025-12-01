<?php

namespace Database\Seeders;

use App\Models\Settings;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // General Settings
            [
                'key' => 'site_name',
                'value' => 'Workflow Management System',
                'type' => 'text',
                'description' => 'Website name (English)',
                'group' => 'general'
            ],
            [
                'key' => 'site_name_ar',
                'value' => 'نظام إدارة سير العمل',
                'type' => 'text',
                'description' => 'Website name (Arabic)',
                'group' => 'general'
            ],
            [
                'key' => 'site_description',
                'value' => 'A comprehensive workflow management system for handling requests and approvals',
                'type' => 'text',
                'description' => 'Website description',
                'group' => 'general'
            ],
            [
                'key' => 'site_description_ar',
                'value' => 'نظام شامل لإدارة سير العمل للتعامل مع الطلبات والموافقات',
                'type' => 'text',
                'description' => 'Website description (Arabic)',
                'group' => 'general'
            ],
            [
                'key' => 'contact_email',
                'value' => 'info@workflow.com',
                'type' => 'text',
                'description' => 'Contact email address',
                'group' => 'general'
            ],
            [
                'key' => 'contact_phone',
                'value' => '+966 50 000 0000',
                'type' => 'text',
                'description' => 'Contact phone number',
                'group' => 'general'
            ],

            // Appearance Settings
            [
                'key' => 'logo',
                'value' => null,
                'type' => 'image',
                'description' => 'Website logo',
                'group' => 'appearance'
            ],
            [
                'key' => 'favicon',
                'value' => null,
                'type' => 'image',
                'description' => 'Website favicon',
                'group' => 'appearance'
            ],
            [
                'key' => 'primary_color',
                'value' => '#008844',
                'type' => 'text',
                'description' => 'Primary color (sidebar, header)',
                'group' => 'appearance'
            ],
            [
                'key' => 'secondary_color',
                'value' => '#0066cc',
                'type' => 'text',
                'description' => 'Secondary color (buttons, links)',
                'group' => 'appearance'
            ],
            [
                'key' => 'accent_color',
                'value' => '#f59e0b',
                'type' => 'text',
                'description' => 'Accent color (highlights, warnings)',
                'group' => 'appearance'
            ],

            // System Settings
            [
                'key' => 'max_file_uploads',
                'value' => '5',
                'type' => 'number',
                'description' => 'Maximum number of files per request',
                'group' => 'system'
            ],
            [
                'key' => 'max_file_size',
                'value' => '10240',
                'type' => 'number',
                'description' => 'Maximum file size in KB (10240 KB = 10 MB)',
                'group' => 'system'
            ],
            [
                'key' => 'allowed_file_types',
                'value' => json_encode(['pdf', 'jpg', 'jpeg', 'png']),
                'type' => 'json',
                'description' => 'Allowed file upload types',
                'group' => 'system'
            ],
            [
                'key' => 'request_min_description_length',
                'value' => '25',
                'type' => 'number',
                'description' => 'Minimum description length for requests',
                'group' => 'system'
            ],
            [
                'key' => 'enable_notifications',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable email notifications',
                'group' => 'system'
            ],
            [
                'key' => 'notification_email',
                'value' => 'notifications@workflow.com',
                'type' => 'text',
                'description' => 'Email address for system notifications',
                'group' => 'system'
            ],

            // Features Settings
            [
                'key' => 'enable_evaluations',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable request evaluations',
                'group' => 'features'
            ],
            [
                'key' => 'enable_path_evaluations',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable workflow path evaluations',
                'group' => 'features'
            ],
            [
                'key' => 'enable_request_comments',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable comments on requests',
                'group' => 'features'
            ],
            [
                'key' => 'enable_request_attachments',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable file attachments on requests',
                'group' => 'features'
            ],

            // Footer Settings
            [
                'key' => 'footer_text',
                'value' => '© 2025 Workflow Management System. All rights reserved.',
                'type' => 'text',
                'description' => 'Footer copyright text',
                'group' => 'footer'
            ],
            [
                'key' => 'footer_text_ar',
                'value' => '© 2025 نظام إدارة سير العمل. جميع الحقوق محفوظة.',
                'type' => 'text',
                'description' => 'Footer copyright text (Arabic)',
                'group' => 'footer'
            ],
            [
                'key' => 'show_footer_links',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Show footer navigation links',
                'group' => 'footer'
            ],
        ];

        foreach ($settings as $setting) {
            Settings::updateOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'type' => $setting['type'],
                    'description' => $setting['description'],
                    'group' => $setting['group']
                ]
            );
        }

        $this->command->info('Settings seeded successfully!');
    }
}
