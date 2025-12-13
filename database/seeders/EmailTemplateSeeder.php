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
            // User Templates
            [
                'event_type' => 'request.created',
                'recipient_type' => 'user',
                'subject_en' => 'Request Created Successfully - #{request_id}',
                'subject_ar' => 'تم إنشاء الطلب بنجاح - #{request_id}',
                'body_en' => "Dear {user_name},\n\nYour request \"{request_title}\" has been created successfully.\n\nRequest ID: {request_id}\nStatus: {status}\nSubmitted on: {created_at}\n\nYou will receive updates as your request progresses through the workflow.\n\nBest regards,\nWorkflow Management System",
                'body_ar' => "عزيزي {user_name}،\n\nتم إنشاء طلبك \"{request_title}\" بنجاح.\n\nرقم الطلب: {request_id}\nالحالة: {status}\nتاريخ التقديم: {created_at}\n\nسوف تتلقى تحديثات حول تقدم طلبك في سير العمل.\n\nمع أطيب التحيات،\nنظام إدارة سير العمل",
                'description' => 'Sent when a user creates a new request',
                'is_active' => true
            ],
            [
                'event_type' => 'request.submitted',
                'recipient_type' => 'user',
                'subject_en' => 'Request Submitted for Review - #{request_id}',
                'subject_ar' => 'تم تقديم الطلب للمراجعة - #{request_id}',
                'body_en' => "Dear {user_name},\n\nYour request \"{request_title}\" has been submitted for review.\n\nRequest ID: {request_id}\nCurrent Status: {status}\nSubmitted on: {submitted_at}\n\nOur team will review your request and you will be notified of any updates.\n\nBest regards,\nWorkflow Management System",
                'body_ar' => "عزيزي {user_name}،\n\nتم تقديم طلبك \"{request_title}\" للمراجعة.\n\nرقم الطلب: {request_id}\nالحالة الحالية: {status}\nتاريخ التقديم: {submitted_at}\n\nسيقوم فريقنا بمراجعة طلبك وسيتم إخطارك بأي تحديثات.\n\nمع أطيب التحيات،\nنظام إدارة سير العمل",
                'description' => 'Sent when a draft request is submitted',
                'is_active' => true
            ],
            [
                'event_type' => 'request.path_assigned',
                'recipient_type' => 'user',
                'subject_en' => 'Workflow Path Assigned - #{request_id}',
                'subject_ar' => 'تم تعيين مسار العمل - #{request_id}',
                'body_en' => "Dear {user_name},\n\nA workflow path has been assigned to your request \"{request_title}\".\n\nRequest ID: {request_id}\nWorkflow Path: {workflow_path}\nAssigned by: {assigned_by}\nAssigned on: {assigned_at}\n\nYour request is now progressing through the workflow.\n\nBest regards,\nWorkflow Management System",
                'body_ar' => "عزيزي {user_name}،\n\nتم تعيين مسار عمل لطلبك \"{request_title}\".\n\nرقم الطلب: {request_id}\nمسار العمل: {workflow_path}\nتم التعيين بواسطة: {assigned_by}\nتاريخ التعيين: {assigned_at}\n\nطلبك الآن يتقدم في سير العمل.\n\nمع أطيب التحيات،\nنظام إدارة سير العمل",
                'description' => 'Sent when Department A assigns a workflow path',
                'is_active' => true
            ],
            [
                'event_type' => 'request.assigned_to_employee',
                'recipient_type' => 'user',
                'subject_en' => 'Request Assigned to You - #{request_id}',
                'subject_ar' => 'تم تعيين طلب لك - #{request_id}',
                'body_en' => "Dear {employee_name},\n\nA request \"{request_title}\" has been assigned to you.\n\nRequest ID: {request_id}\nRequested by: {user_name}\nDepartment: {department}\nAssigned by: {assigned_by}\nAssigned on: {assigned_at}\n\nPlease review and take appropriate action.\n\nBest regards,\nWorkflow Management System",
                'body_ar' => "عزيزي {employee_name}،\n\nتم تعيين طلب \"{request_title}\" لك.\n\nرقم الطلب: {request_id}\nمقدم الطلب: {user_name}\nالقسم: {department}\nتم التعيين بواسطة: {assigned_by}\nتاريخ التعيين: {assigned_at}\n\nيرجى المراجعة واتخاذ الإجراء المناسب.\n\nمع أطيب التحيات،\nنظام إدارة سير العمل",
                'description' => 'Sent when a request is assigned to an employee',
                'is_active' => true
            ],
            [
                'event_type' => 'request.moved_to_department',
                'recipient_type' => 'user',
                'subject_en' => 'Request Moved to New Department - #{request_id}',
                'subject_ar' => 'تم نقل الطلب إلى قسم جديد - #{request_id}',
                'body_en' => "Dear {user_name},\n\nYour request \"{request_title}\" has been moved to a new department.\n\nRequest ID: {request_id}\nNew Department: {department}\nMoved on: {moved_at}\nStatus: {status}\n\nYour request continues to progress through the workflow.\n\nBest regards,\nWorkflow Management System",
                'body_ar' => "عزيزي {user_name}،\n\nتم نقل طلبك \"{request_title}\" إلى قسم جديد.\n\nرقم الطلب: {request_id}\nالقسم الجديد: {department}\nتاريخ النقل: {moved_at}\nالحالة: {status}\n\nطلبك يستمر في التقدم عبر سير العمل.\n\nمع أطيب التحيات،\nنظام إدارة سير العمل",
                'description' => 'Sent when request moves to a different department',
                'is_active' => true
            ],
            [
                'event_type' => 'request.approved',
                'recipient_type' => 'user',
                'subject_en' => 'Request Approved - #{request_id}',
                'subject_ar' => 'تمت الموافقة على الطلب - #{request_id}',
                'body_en' => "Dear {user_name},\n\nGreat news! Your request \"{request_title}\" has been approved.\n\nRequest ID: {request_id}\nApproved by: {approved_by}\nApproved on: {approved_at}\nComments: {comments}\n\nYour request is now moving forward to the next stage.\n\nBest regards,\nWorkflow Management System",
                'body_ar' => "عزيزي {user_name}،\n\nأخبار رائعة! تمت الموافقة على طلبك \"{request_title}\".\n\nرقم الطلب: {request_id}\nتمت الموافقة بواسطة: {approved_by}\nتاريخ الموافقة: {approved_at}\nالتعليقات: {comments}\n\nطلبك الآن يتقدم إلى المرحلة التالية.\n\nمع أطيب التحيات،\nنظام إدارة سير العمل",
                'description' => 'Sent when a request is approved',
                'is_active' => true
            ],
            [
                'event_type' => 'request.rejected',
                'recipient_type' => 'user',
                'subject_en' => 'Request Rejected - #{request_id}',
                'subject_ar' => 'تم رفض الطلب - #{request_id}',
                'body_en' => "Dear {user_name},\n\nWe regret to inform you that your request \"{request_title}\" has been rejected.\n\nRequest ID: {request_id}\nRejected by: {rejected_by}\nRejected on: {rejected_at}\nReason: {reason}\nComments: {comments}\n\nIf you have questions, please contact the relevant department.\n\nBest regards,\nWorkflow Management System",
                'body_ar' => "عزيزي {user_name}،\n\nنأسف لإبلاغك بأنه تم رفض طلبك \"{request_title}\".\n\nرقم الطلب: {request_id}\nتم الرفض بواسطة: {rejected_by}\nتاريخ الرفض: {rejected_at}\nالسبب: {reason}\nالتعليقات: {comments}\n\nإذا كانت لديك أسئلة، يرجى الاتصال بالقسم المعني.\n\nمع أطيب التحيات،\nنظام إدارة سير العمل",
                'description' => 'Sent when a request is rejected',
                'is_active' => true
            ],
            [
                'event_type' => 'request.need_more_details',
                'recipient_type' => 'user',
                'subject_en' => 'More Information Required - #{request_id}',
                'subject_ar' => 'مطلوب معلومات إضافية - #{request_id}',
                'body_en' => "Dear {user_name},\n\nYour request \"{request_title}\" requires additional information.\n\nRequest ID: {request_id}\nRequested by: {requested_by}\nComments: {comments}\n\nPlease log in to the system and provide the requested information to continue processing your request.\n\nBest regards,\nWorkflow Management System",
                'body_ar' => "عزيزي {user_name}،\n\nطلبك \"{request_title}\" يتطلب معلومات إضافية.\n\nرقم الطلب: {request_id}\nمطلوب من قبل: {requested_by}\nالتعليقات: {comments}\n\nيرجى تسجيل الدخول إلى النظام وتقديم المعلومات المطلوبة لمتابعة معالجة طلبك.\n\nمع أطيب التحيات،\nنظام إدارة سير العمل",
                'description' => 'Sent when additional details are required from the user',
                'is_active' => true
            ],
            [
                'event_type' => 'request.completed',
                'recipient_type' => 'user',
                'subject_en' => 'Request Completed Successfully - #{request_id}',
                'subject_ar' => 'تم إنجاز الطلب بنجاح - #{request_id}',
                'body_en' => "Dear {user_name},\n\nYour request \"{request_title}\" has been completed successfully!\n\nRequest ID: {request_id}\nCompleted by: {completed_by}\nCompleted on: {completed_at}\nFinal Comments: {comments}\n\nThank you for using our workflow system.\n\nBest regards,\nWorkflow Management System",
                'body_ar' => "عزيزي {user_name}،\n\nتم إنجاز طلبك \"{request_title}\" بنجاح!\n\nرقم الطلب: {request_id}\nتم الإنجاز بواسطة: {completed_by}\nتاريخ الإنجاز: {completed_at}\nالتعليقات النهائية: {comments}\n\nشكراً لاستخدام نظام سير العمل لدينا.\n\nمع أطيب التحيات،\nنظام إدارة سير العمل",
                'description' => 'Sent when a request is marked as completed',
                'is_active' => true
            ],
            [
                'event_type' => 'request.returned',
                'recipient_type' => 'user',
                'subject_en' => 'Request Returned to Previous Department - #{request_id}',
                'subject_ar' => 'تم إرجاع الطلب إلى القسم السابق - #{request_id}',
                'body_en' => "Dear {user_name},\n\nYour request \"{request_title}\" has been returned to a previous department for review.\n\nRequest ID: {request_id}\nReturned to: {department}\nReturned by: {returned_by}\nReturned on: {returned_at}\nReason: {reason}\n\nThe request will be re-evaluated by the previous department.\n\nBest regards,\nWorkflow Management System",
                'body_ar' => "عزيزي {user_name}،\n\nتم إرجاع طلبك \"{request_title}\" إلى قسم سابق للمراجعة.\n\nرقم الطلب: {request_id}\nتم الإرجاع إلى: {department}\nتم الإرجاع بواسطة: {returned_by}\nتاريخ الإرجاع: {returned_at}\nالسبب: {reason}\n\nسيتم إعادة تقييم الطلب من قبل القسم السابق.\n\nمع أطيب التحيات،\nنظام إدارة سير العمل",
                'description' => 'Sent when a request is returned to a previous department',
                'is_active' => true
            ],

            // Admin Templates
            [
                'event_type' => 'admin.request_assigned',
                'recipient_type' => 'admin',
                'subject_en' => 'New Request Requires Admin Review - #{request_id}',
                'subject_ar' => 'طلب جديد يتطلب مراجعة المسؤول - #{request_id}',
                'body_en' => "Dear Admin,\n\nA new request \"{request_title}\" has been assigned for your review.\n\nRequest ID: {request_id}\nSubmitted by: {user_name}\nDepartment: {department}\nSubmitted on: {submitted_at}\nPriority: {priority}\n\nPlease log in to the system to review and take appropriate action.\n\nBest regards,\nWorkflow Management System",
                'body_ar' => "عزيزي المسؤول،\n\nتم تعيين طلب جديد \"{request_title}\" لمراجعتك.\n\nرقم الطلب: {request_id}\nمقدم من: {user_name}\nالقسم: {department}\nتاريخ التقديم: {submitted_at}\nالأولوية: {priority}\n\nيرجى تسجيل الدخول إلى النظام للمراجعة واتخاذ الإجراء المناسب.\n\nمع أطيب التحيات،\nنظام إدارة سير العمل",
                'description' => 'Sent to admin when a request needs admin review',
                'is_active' => true
            ],
            [
                'event_type' => 'admin.workflow_path_needed',
                'recipient_type' => 'admin',
                'subject_en' => 'Workflow Path Assignment Required - #{request_id}',
                'subject_ar' => 'مطلوب تعيين مسار العمل - #{request_id}',
                'body_en' => "Dear Admin,\n\nRequest \"{request_title}\" is pending workflow path assignment.\n\nRequest ID: {request_id}\nSubmitted by: {user_name}\nRequest Type: {request_type}\nSubmitted on: {submitted_at}\n\nPlease assign an appropriate workflow path to proceed with this request.\n\nBest regards,\nWorkflow Management System",
                'body_ar' => "عزيزي المسؤول،\n\nالطلب \"{request_title}\" في انتظار تعيين مسار العمل.\n\nرقم الطلب: {request_id}\nمقدم من: {user_name}\nنوع الطلب: {request_type}\nتاريخ التقديم: {submitted_at}\n\nيرجى تعيين مسار عمل مناسب لمتابعة هذا الطلب.\n\nمع أطيب التحيات،\nنظام إدارة سير العمل",
                'description' => 'Sent to admin when Department A needs to assign workflow path',
                'is_active' => true
            ],

            // Manager Templates
            [
                'event_type' => 'manager.request_assigned',
                'recipient_type' => 'manager',
                'subject_en' => 'Request Assigned to Your Department - #{request_id}',
                'subject_ar' => 'تم تعيين طلب لقسمك - #{request_id}',
                'body_en' => "Dear Manager,\n\nA new request \"{request_title}\" has been assigned to your department.\n\nRequest ID: {request_id}\nSubmitted by: {user_name}\nDepartment: {department}\nAssigned on: {assigned_at}\nCurrent Status: {status}\n\nPlease review and assign to appropriate team members.\n\nBest regards,\nWorkflow Management System",
                'body_ar' => "عزيزي المدير،\n\nتم تعيين طلب جديد \"{request_title}\" لقسمك.\n\nرقم الطلب: {request_id}\nمقدم من: {user_name}\nالقسم: {department}\nتاريخ التعيين: {assigned_at}\nالحالة الحالية: {status}\n\nيرجى المراجعة والتعيين لأعضاء الفريق المناسبين.\n\nمع أطيب التحيات،\nنظام إدارة سير العمل",
                'description' => 'Sent to manager when a request is assigned to their department',
                'is_active' => true
            ],
            [
                'event_type' => 'manager.employee_completed_work',
                'recipient_type' => 'manager',
                'subject_en' => 'Employee Completed Work on Request - #{request_id}',
                'subject_ar' => 'أكمل الموظف العمل على الطلب - #{request_id}',
                'body_en' => "Dear Manager,\n\nAn employee has completed work on request \"{request_title}\".\n\nRequest ID: {request_id}\nEmployee: {employee_name}\nCompleted on: {completed_at}\nComments: {comments}\n\nPlease review the completed work and approve or request changes.\n\nBest regards,\nWorkflow Management System",
                'body_ar' => "عزيزي المدير،\n\nأكمل موظف العمل على الطلب \"{request_title}\".\n\nرقم الطلب: {request_id}\nالموظف: {employee_name}\nتاريخ الإكمال: {completed_at}\nالتعليقات: {comments}\n\nيرجى مراجعة العمل المكتمل والموافقة أو طلب التغييرات.\n\nمع أطيب التحيات،\nنظام إدارة سير العمل",
                'description' => 'Sent to manager when an employee completes their work',
                'is_active' => true
            ],
            [
                'event_type' => 'manager.approval_needed',
                'recipient_type' => 'manager',
                'subject_en' => 'Manager Approval Required - #{request_id}',
                'subject_ar' => 'مطلوب موافقة المدير - #{request_id}',
                'body_en' => "Dear Manager,\n\nRequest \"{request_title}\" requires your approval.\n\nRequest ID: {request_id}\nSubmitted by: {user_name}\nProcessed by: {employee_name}\nDepartment: {department}\nStatus: {status}\n\nPlease review and provide your approval decision.\n\nBest regards,\nWorkflow Management System",
                'body_ar' => "عزيزي المدير،\n\nالطلب \"{request_title}\" يتطلب موافقتك.\n\nرقم الطلب: {request_id}\nمقدم من: {user_name}\nتمت المعالجة بواسطة: {employee_name}\nالقسم: {department}\nالحالة: {status}\n\nيرجى المراجعة وتقديم قرار الموافقة الخاص بك.\n\nمع أطيب التحيات،\nنظام إدارة سير العمل",
                'description' => 'Sent to manager when approval is needed',
                'is_active' => true
            ],

            // SLA Reminder Templates
            [
                'event_type' => 'sla.dept_a_assign_path',
                'recipient_type' => 'admin',
                'subject_en' => 'SLA Reminder: Workflow Path Assignment Overdue - #{request_id}',
                'subject_ar' => 'تذكير SLA: تأخر تعيين مسار العمل - #{request_id}',
                'body_en' => "Dear {manager_name},\n\nThis is an SLA reminder for request \"{request_title}\".\n\nRequest ID: {request_id}\nSubmitted by: {user_name}\nSubmitted on: {submitted_at}\nDays waiting: {days_waiting}\nSLA threshold: {sla_days} days\nAction required: Assign workflow path\n\nThis request has been waiting for workflow path assignment and is now overdue according to our SLA policy. Please take action as soon as possible to maintain service quality.\n\nBest regards,\nWorkflow Management System",
                'body_ar' => "عزيزي {manager_name}،\n\nهذا تذكير SLA للطلب \"{request_title}\".\n\nرقم الطلب: {request_id}\nمقدم من: {user_name}\nتاريخ التقديم: {submitted_at}\nأيام الانتظار: {days_waiting}\nحد SLA: {sla_days} أيام\nالإجراء المطلوب: تعيين مسار العمل\n\nهذا الطلب في انتظار تعيين مسار العمل وأصبح متأخراً وفقاً لسياسة SLA الخاصة بنا. يرجى اتخاذ إجراء في أقرب وقت ممكن للحفاظ على جودة الخدمة.\n\nمع أطيب التحيات،\nنظام إدارة سير العمل",
                'description' => 'Sent to Department A managers when request is overdue for workflow path assignment',
                'is_active' => true
            ],
            [
                'event_type' => 'sla.manager_assign_employee',
                'recipient_type' => 'manager',
                'subject_en' => 'SLA Reminder: Employee Assignment Overdue - #{request_id}',
                'subject_ar' => 'تذكير SLA: تأخر تعيين الموظف - #{request_id}',
                'body_en' => "Dear {manager_name},\n\nThis is an SLA reminder for request \"{request_title}\" in {department}.\n\nRequest ID: {request_id}\nSubmitted by: {user_name}\nCurrent status: Waiting for employee assignment\nDays waiting: {days_waiting}\nSLA threshold: {sla_days} days\nAction required: Assign employee to work on request\n\nThis request has been waiting for employee assignment and is now overdue. Please assign an appropriate team member to handle this request.\n\nBest regards,\nWorkflow Management System",
                'body_ar' => "عزيزي {manager_name}،\n\nهذا تذكير SLA للطلب \"{request_title}\" في {department}.\n\nرقم الطلب: {request_id}\nمقدم من: {user_name}\nالحالة الحالية: في انتظار تعيين الموظف\nأيام الانتظار: {days_waiting}\nحد SLA: {sla_days} أيام\nالإجراء المطلوب: تعيين موظف للعمل على الطلب\n\nهذا الطلب في انتظار تعيين موظف وأصبح متأخراً. يرجى تعيين عضو مناسب من الفريق لمعالجة هذا الطلب.\n\nمع أطيب التحيات،\nنظام إدارة سير العمل",
                'description' => 'Sent to department managers when employee assignment is overdue',
                'is_active' => true
            ],
            [
                'event_type' => 'sla.employee_work_overdue',
                'recipient_type' => 'manager',
                'subject_en' => 'SLA Reminder: Employee Work Overdue - #{request_id}',
                'subject_ar' => 'تذكير SLA: تأخر عمل الموظف - #{request_id}',
                'body_en' => "Dear {manager_name},\n\nThis is an SLA reminder for request \"{request_title}\" in {department}.\n\nRequest ID: {request_id}\nSubmitted by: {user_name}\nAssigned to: {employee_name}\nAssigned on: {assigned_at}\nDays in progress: {days_waiting}\nSLA threshold: {sla_days} days\nAction required: Follow up with assigned employee\n\nThe assigned employee has been working on this request for longer than the SLA allows. Please follow up with {employee_name} to ensure timely completion.\n\nBest regards,\nWorkflow Management System",
                'body_ar' => "عزيزي {manager_name}،\n\nهذا تذكير SLA للطلب \"{request_title}\" في {department}.\n\nرقم الطلب: {request_id}\nمقدم من: {user_name}\nمعين لـ: {employee_name}\nتاريخ التعيين: {assigned_at}\nأيام العمل: {days_waiting}\nحد SLA: {sla_days} أيام\nالإجراء المطلوب: المتابعة مع الموظف المعين\n\nالموظف المعين يعمل على هذا الطلب لفترة أطول من المسموح به في SLA. يرجى المتابعة مع {employee_name} لضمان الإنجاز في الوقت المناسب.\n\nمع أطيب التحيات،\nنظام إدارة سير العمل",
                'description' => 'Sent to department managers when employee work is taking too long',
                'is_active' => true
            ],
            [
                'event_type' => 'sla.final_validation_overdue',
                'recipient_type' => 'admin',
                'subject_en' => 'SLA Reminder: Final Validation Overdue - #{request_id}',
                'subject_ar' => 'تذكير SLA: تأخر التحقق النهائي - #{request_id}',
                'body_en' => "Dear {manager_name},\n\nThis is an SLA reminder for request \"{request_title}\".\n\nRequest ID: {request_id}\nSubmitted by: {user_name}\nReturned for validation on: {returned_at}\nDays waiting: {days_waiting}\nSLA threshold: {sla_days} days\nAction required: Complete final validation\n\nThis request has been returned for final validation and is now overdue. Please review and complete the validation process (approve, reject, or return to previous department).\n\nBest regards,\nWorkflow Management System",
                'body_ar' => "عزيزي {manager_name}،\n\nهذا تذكير SLA للطلب \"{request_title}\".\n\nرقم الطلب: {request_id}\nمقدم من: {user_name}\nتاريخ الإرجاع للتحقق: {returned_at}\nأيام الانتظار: {days_waiting}\nحد SLA: {sla_days} أيام\nالإجراء المطلوب: إكمال التحقق النهائي\n\nتم إرجاع هذا الطلب للتحقق النهائي وأصبح متأخراً. يرجى المراجعة وإكمال عملية التحقق (الموافقة، الرفض، أو الإرجاع إلى القسم السابق).\n\nمع أطيب التحيات،\nنظام إدارة سير العمل",
                'description' => 'Sent to Department A managers when final validation is overdue',
                'is_active' => true
            ]
        ];

        foreach ($templates as $template) {
            \App\Models\EmailTemplate::updateOrCreate(
                [
                    'event_type' => $template['event_type'],
                    'recipient_type' => $template['recipient_type']
                ],
                $template
            );
        }
    }
}
