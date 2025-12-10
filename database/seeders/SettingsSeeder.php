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
                'description_ar' => 'اسم الموقع (بالإنجليزية)',
                'group' => 'general'
            ],
            [
                'key' => 'site_name_ar',
                'value' => 'نظام إدارة سير العمل',
                'type' => 'text',
                'description' => 'Website name (Arabic)',
                'description_ar' => 'اسم الموقع (بالعربية)',
                'group' => 'general'
            ],
            [
                'key' => 'site_description',
                'value' => 'A comprehensive workflow management system for handling requests and approvals',
                'type' => 'text',
                'description' => 'Website description (English)',
                'description_ar' => 'وصف الموقع (بالإنجليزية)',
                'group' => 'general'
            ],
            [
                'key' => 'site_description_ar',
                'value' => 'نظام شامل لإدارة سير العمل للتعامل مع الطلبات والموافقات',
                'type' => 'text',
                'description' => 'Website description (Arabic)',
                'description_ar' => 'وصف الموقع (بالعربية)',
                'group' => 'general'
            ],
            [
                'key' => 'contact_email',
                'value' => 'info@workflow.com',
                'type' => 'text',
                'description' => 'Contact email address',
                'description_ar' => 'البريد الإلكتروني للتواصل',
                'group' => 'general'
            ],
            [
                'key' => 'contact_phone',
                'value' => '+966 50 000 0000',
                'type' => 'text',
                'description' => 'Contact phone number',
                'description_ar' => 'رقم الهاتف للتواصل',
                'group' => 'general'
            ],

            // Appearance Settings
            [
                'key' => 'logo',
                'value' => null,
                'type' => 'image',
                'description' => 'Website logo',
                'description_ar' => 'شعار الموقع',
                'group' => 'appearance'
            ],
            [
                'key' => 'favicon',
                'value' => null,
                'type' => 'image',
                'description' => 'Website favicon',
                'description_ar' => 'أيقونة الموقع المفضلة',
                'group' => 'appearance'
            ],
            [
                'key' => 'primary_color',
                'value' => '#008844',
                'type' => 'text',
                'description' => 'Primary color (sidebar, header)',
                'description_ar' => 'اللون الأساسي (الشريط الجانبي، الرأس)',
                'group' => 'appearance'
            ],
            [
                'key' => 'secondary_color',
                'value' => '#0066cc',
                'type' => 'text',
                'description' => 'Secondary color (buttons, links)',
                'description_ar' => 'اللون الثانوي (الأزرار، الروابط)',
                'group' => 'appearance'
            ],
            [
                'key' => 'accent_color',
                'value' => '#f59e0b',
                'type' => 'text',
                'description' => 'Accent color (highlights, warnings)',
                'description_ar' => 'اللون التكميلي (التحديدات، التحذيرات)',
                'group' => 'appearance'
            ],

            // System Settings
            [
                'key' => 'max_file_uploads',
                'value' => '5',
                'type' => 'number',
                'description' => 'Maximum number of files per request',
                'description_ar' => 'الحد الأقصى لعدد الملفات لكل طلب',
                'group' => 'system'
            ],
            [
                'key' => 'max_file_size',
                'value' => '10240',
                'type' => 'number',
                'description' => 'Maximum file size in KB (10240 KB = 10 MB)',
                'description_ar' => 'الحد الأقصى لحجم الملف بالكيلوبايت (10240 كيلوبايت = 10 ميجابايت)',
                'group' => 'system'
            ],
            [
                'key' => 'allowed_file_types',
                'value' => json_encode(['pdf', 'jpg', 'jpeg', 'png']),
                'type' => 'json',
                'description' => 'Allowed file upload types',
                'description_ar' => 'أنواع الملفات المسموح برفعها',
                'group' => 'system'
            ],
            [
                'key' => 'request_min_description_length',
                'value' => '25',
                'type' => 'number',
                'description' => 'Minimum description length for requests',
                'description_ar' => 'الحد الأدنى لطول الوصف للطلبات',
                'group' => 'system'
            ],
            [
                'key' => 'enable_notifications',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable email notifications',
                'description_ar' => 'تفعيل إشعارات البريد الإلكتروني',
                'group' => 'system'
            ],
            [
                'key' => 'notification_email',
                'value' => 'notifications@workflow.com',
                'type' => 'text',
                'description' => 'Email address for system notifications',
                'description_ar' => 'البريد الإلكتروني لإشعارات النظام',
                'group' => 'system'
            ],

            // Features Settings
            [
                'key' => 'enable_evaluations',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable request evaluations',
                'description_ar' => 'تفعيل تقييمات الطلبات',
                'group' => 'features'
            ],
            [
                'key' => 'enable_path_evaluations',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable workflow path evaluations',
                'description_ar' => 'تفعيل تقييمات مسار سير العمل',
                'group' => 'features'
            ],
            [
                'key' => 'enable_request_comments',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable comments on requests',
                'description_ar' => 'تفعيل التعليقات على الطلبات',
                'group' => 'features'
            ],
            [
                'key' => 'enable_request_attachments',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable file attachments on requests',
                'description_ar' => 'تفعيل المرفقات على الطلبات',
                'group' => 'features'
            ],

            // Footer Settings
            [
                'key' => 'footer_text',
                'value' => '© 2025 Workflow Management System. All rights reserved.',
                'type' => 'text',
                'description' => 'Footer copyright text (English)',
                'description_ar' => 'نص حقوق النشر بالتذييل (بالإنجليزية)',
                'group' => 'footer'
            ],
            [
                'key' => 'footer_text_ar',
                'value' => '© 2025 نظام إدارة سير العمل. جميع الحقوق محفوظة.',
                'type' => 'text',
                'description' => 'Footer copyright text (Arabic)',
                'description_ar' => 'نص حقوق النشر بالتذييل (بالعربية)',
                'group' => 'footer'
            ],
            [
                'key' => 'show_footer_links',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Show footer navigation links',
                'description_ar' => 'إظهار روابط التنقل بالتذييل',
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
                    'description_ar' => $setting['description_ar'] ?? null,
                    'group' => $setting['group']
                ]
            );
        }

        $this->command->info('Settings seeded successfully!');
    }
}
