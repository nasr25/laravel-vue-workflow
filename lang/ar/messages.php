<?php

return [
    // Unauthorized messages
    'unauthorized' => 'غير مصرح.',
    'unauthorized_action' => 'غير مصرح. ليس لديك إذن لتنفيذ هذا الإجراء.',
    'unauthorized_view_pending' => 'غير مصرح. ليس لديك إذن لعرض الطلبات المعلقة.',
    'unauthorized_view_requests' => 'غير مصرح. ليس لديك إذن لعرض الطلبات.',
    'unauthorized_view_details' => 'غير مصرح. ليس لديك إذن لعرض تفاصيل الطلب.',

    // Error messages
    'department_a_not_found' => 'القسم أ غير موجود',
    'no_steps_found' => 'لم يتم العثور على خطوات في مسار العمل هذا',
    'no_previous_department' => 'لم يتم العثور على قسم سابق للعودة إليه',

    // Success messages
    'request_assigned_to_path' => 'تم تعيين الطلب إلى مسار العمل بنجاح',
    'request_rejected' => 'تم رفض الطلب بنجاح',
    'more_details_requested' => 'تم طلب مزيد من التفاصيل من المستخدم',
    'request_completed' => 'تم إكمال الطلب بنجاح',
    'request_returned_to_previous' => 'تمت إعادة الطلب إلى القسم السابق للمراجعة',
    'evaluation_submitted' => 'تم تقديم التقييم بنجاح',

    // Status translations
    'status' => [
        'draft' => 'مسودة',
        'pending' => 'قيد الانتظار',
        'in_review' => 'قيد المراجعة',
        'in_progress' => 'قيد التنفيذ',
        'need_more_details' => 'يحتاج مزيد من التفاصيل',
        'approved' => 'موافق عليه',
        'rejected' => 'مرفوض',
        'completed' => 'مكتمل',
    ],
];
