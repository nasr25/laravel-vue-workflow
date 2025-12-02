<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'event_type' => 'request.created',
                'subject_en' => 'Request Created Successfully - #{request_id}',
                'subject_ar' => 'تم إنشاء الطلب بنجاح - #{request_id}',
                'body_en' => "Dear {user_name},\n\nYour request \"{request_title}\" has been created successfully.\n\nRequest ID: {request_id}\nStatus: {status}\nSubmitted on: {created_at}\n\nYou will receive updates as your request progresses through the workflow.\n\nBest regards,\nWorkflow Management System",
                'body_ar' => "عزيزي {user_name}،\n\nتم إنشاء طلبك \"{request_title}\" بنجاح.\n\nرقم الطلب: {request_id}\nالحالة: {status}\nتاريخ التقديم: {created_at}\n\nسوف تتلقى تحديثات حول تقدم طلبك في سير العمل.\n\nمع أطيب التحيات،\nنظام إدارة سير العمل",
                'description' => 'Sent when a user creates a new request',
                'is_active' => true
            ],
            [
                'event_type' => 'request.submitted',
                'subject_en' => 'Request Submitted for Review - #{request_id}',
                'subject_ar' => 'تم تقديم الطلب للمراجعة - #{request_id}',
                'body_en' => "Dear {user_name},\n\nYour request \"{request_title}\" has been submitted for review.\n\nRequest ID: {request_id}\nCurrent Status: {status}\nSubmitted on: {submitted_at}\n\nOur team will review your request and you will be notified of any updates.\n\nBest regards,\nWorkflow Management System",
                'body_ar' => "عزيزي {user_name}،\n\nتم تقديم طلبك \"{request_title}\" للمراجعة.\n\nرقم الطلب: {request_id}\nالحالة الحالية: {status}\nتاريخ التقديم: {submitted_at}\n\nسيقوم فريقنا بمراجعة طلبك وسيتم إخطارك بأي تحديثات.\n\nمع أطيب التحيات،\nنظام إدارة سير العمل",
                'description' => 'Sent when a draft request is submitted',
                'is_active' => true
            ],
            [
                'event_type' => 'request.path_assigned',
                'subject_en' => 'Workflow Path Assigned - #{request_id}',
                'subject_ar' => 'تم تعيين مسار العمل - #{request_id}',
                'body_en' => "Dear {user_name},\n\nA workflow path has been assigned to your request \"{request_title}\".\n\nRequest ID: {request_id}\nWorkflow Path: {workflow_path}\nAssigned by: {assigned_by}\nAssigned on: {assigned_at}\n\nYour request is now progressing through the workflow.\n\nBest regards,\nWorkflow Management System",
                'body_ar' => "عزيزي {user_name}،\n\nتم تعيين مسار عمل لطلبك \"{request_title}\".\n\nرقم الطلب: {request_id}\nمسار العمل: {workflow_path}\nتم التعيين بواسطة: {assigned_by}\nتاريخ التعيين: {assigned_at}\n\nطلبك الآن يتقدم في سير العمل.\n\nمع أطيب التحيات،\nنظام إدارة سير العمل",
                'description' => 'Sent when Department A assigns a workflow path',
                'is_active' => true
            ],
            [
                'event_type' => 'request.assigned_to_employee',
                'subject_en' => 'Request Assigned to You - #{request_id}',
                'subject_ar' => 'تم تعيين طلب لك - #{request_id}',
                'body_en' => "Dear {employee_name},\n\nA request \"{request_title}\" has been assigned to you.\n\nRequest ID: {request_id}\nRequested by: {user_name}\nDepartment: {department}\nAssigned by: {assigned_by}\nAssigned on: {assigned_at}\n\nPlease review and take appropriate action.\n\nBest regards,\nWorkflow Management System",
                'body_ar' => "عزيزي {employee_name}،\n\nتم تعيين طلب \"{request_title}\" لك.\n\nرقم الطلب: {request_id}\nمقدم الطلب: {user_name}\nالقسم: {department}\nتم التعيين بواسطة: {assigned_by}\nتاريخ التعيين: {assigned_at}\n\nيرجى المراجعة واتخاذ الإجراء المناسب.\n\nمع أطيب التحيات،\nنظام إدارة سير العمل",
                'description' => 'Sent when a request is assigned to an employee',
                'is_active' => true
            ],
            [
                'event_type' => 'request.moved_to_department',
                'subject_en' => 'Request Moved to New Department - #{request_id}',
                'subject_ar' => 'تم نقل الطلب إلى قسم جديد - #{request_id}',
                'body_en' => "Dear {user_name},\n\nYour request \"{request_title}\" has been moved to a new department.\n\nRequest ID: {request_id}\nNew Department: {department}\nMoved on: {moved_at}\nStatus: {status}\n\nYour request continues to progress through the workflow.\n\nBest regards,\nWorkflow Management System",
                'body_ar' => "عزيزي {user_name}،\n\nتم نقل طلبك \"{request_title}\" إلى قسم جديد.\n\nرقم الطلب: {request_id}\nالقسم الجديد: {department}\nتاريخ النقل: {moved_at}\nالحالة: {status}\n\nطلبك يستمر في التقدم عبر سير العمل.\n\nمع أطيب التحيات،\nنظام إدارة سير العمل",
                'description' => 'Sent when request moves to a different department',
                'is_active' => true
            ],
            [
                'event_type' => 'request.approved',
                'subject_en' => 'Request Approved - #{request_id}',
                'subject_ar' => 'تمت الموافقة على الطلب - #{request_id}',
                'body_en' => "Dear {user_name},\n\nGreat news! Your request \"{request_title}\" has been approved.\n\nRequest ID: {request_id}\nApproved by: {approved_by}\nApproved on: {approved_at}\nComments: {comments}\n\nYour request is now moving forward to the next stage.\n\nBest regards,\nWorkflow Management System",
                'body_ar' => "عزيزي {user_name}،\n\nأخبار رائعة! تمت الموافقة على طلبك \"{request_title}\".\n\nرقم الطلب: {request_id}\nتمت الموافقة بواسطة: {approved_by}\nتاريخ الموافقة: {approved_at}\nالتعليقات: {comments}\n\nطلبك الآن يتقدم إلى المرحلة التالية.\n\nمع أطيب التحيات،\nنظام إدارة سير العمل",
                'description' => 'Sent when a request is approved',
                'is_active' => true
            ],
            [
                'event_type' => 'request.rejected',
                'subject_en' => 'Request Rejected - #{request_id}',
                'subject_ar' => 'تم رفض الطلب - #{request_id}',
                'body_en' => "Dear {user_name},\n\nWe regret to inform you that your request \"{request_title}\" has been rejected.\n\nRequest ID: {request_id}\nRejected by: {rejected_by}\nRejected on: {rejected_at}\nReason: {reason}\nComments: {comments}\n\nIf you have questions, please contact the relevant department.\n\nBest regards,\nWorkflow Management System",
                'body_ar' => "عزيزي {user_name}،\n\nنأسف لإبلاغك بأنه تم رفض طلبك \"{request_title}\".\n\nرقم الطلب: {request_id}\nتم الرفض بواسطة: {rejected_by}\nتاريخ الرفض: {rejected_at}\nالسبب: {reason}\nالتعليقات: {comments}\n\nإذا كانت لديك أسئلة، يرجى الاتصال بالقسم المعني.\n\nمع أطيب التحيات،\nنظام إدارة سير العمل",
                'description' => 'Sent when a request is rejected',
                'is_active' => true
            ],
            [
                'event_type' => 'request.need_more_details',
                'subject_en' => 'More Information Required - #{request_id}',
                'subject_ar' => 'مطلوب معلومات إضافية - #{request_id}',
                'body_en' => "Dear {user_name},\n\nYour request \"{request_title}\" requires additional information.\n\nRequest ID: {request_id}\nRequested by: {requested_by}\nComments: {comments}\n\nPlease log in to the system and provide the requested information to continue processing your request.\n\nBest regards,\nWorkflow Management System",
                'body_ar' => "عزيزي {user_name}،\n\nطلبك \"{request_title}\" يتطلب معلومات إضافية.\n\nرقم الطلب: {request_id}\nمطلوب من قبل: {requested_by}\nالتعليقات: {comments}\n\nيرجى تسجيل الدخول إلى النظام وتقديم المعلومات المطلوبة لمتابعة معالجة طلبك.\n\nمع أطيب التحيات،\nنظام إدارة سير العمل",
                'description' => 'Sent when additional details are required from the user',
                'is_active' => true
            ],
            [
                'event_type' => 'request.completed',
                'subject_en' => 'Request Completed Successfully - #{request_id}',
                'subject_ar' => 'تم إنجاز الطلب بنجاح - #{request_id}',
                'body_en' => "Dear {user_name},\n\nYour request \"{request_title}\" has been completed successfully!\n\nRequest ID: {request_id}\nCompleted by: {completed_by}\nCompleted on: {completed_at}\nFinal Comments: {comments}\n\nThank you for using our workflow system.\n\nBest regards,\nWorkflow Management System",
                'body_ar' => "عزيزي {user_name}،\n\nتم إنجاز طلبك \"{request_title}\" بنجاح!\n\nرقم الطلب: {request_id}\nتم الإنجاز بواسطة: {completed_by}\nتاريخ الإنجاز: {completed_at}\nالتعليقات النهائية: {comments}\n\nشكراً لاستخدام نظام سير العمل لدينا.\n\nمع أطيب التحيات،\nنظام إدارة سير العمل",
                'description' => 'Sent when a request is marked as completed',
                'is_active' => true
            ],
            [
                'event_type' => 'request.returned',
                'subject_en' => 'Request Returned to Previous Department - #{request_id}',
                'subject_ar' => 'تم إرجاع الطلب إلى القسم السابق - #{request_id}',
                'body_en' => "Dear {user_name},\n\nYour request \"{request_title}\" has been returned to a previous department for review.\n\nRequest ID: {request_id}\nReturned to: {department}\nReturned by: {returned_by}\nReturned on: {returned_at}\nReason: {reason}\n\nThe request will be re-evaluated by the previous department.\n\nBest regards,\nWorkflow Management System",
                'body_ar' => "عزيزي {user_name}،\n\nتم إرجاع طلبك \"{request_title}\" إلى قسم سابق للمراجعة.\n\nرقم الطلب: {request_id}\nتم الإرجاع إلى: {department}\nتم الإرجاع بواسطة: {returned_by}\nتاريخ الإرجاع: {returned_at}\nالسبب: {reason}\n\nسيتم إعادة تقييم الطلب من قبل القسم السابق.\n\nمع أطيب التحيات،\nنظام إدارة سير العمل",
                'description' => 'Sent when a request is returned to a previous department',
                'is_active' => true
            ]
        ];

        foreach ($templates as $template) {
            \DB::table('email_templates')->insert(array_merge($template, [
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }
    }
}
