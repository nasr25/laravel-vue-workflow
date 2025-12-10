<?php

return [
    // Unauthorized messages
    'unauthorized' => 'Unauthorized.',
    'unauthorized_action' => 'Unauthorized. You do not have permission to perform this action.',
    'unauthorized_view_pending' => 'Unauthorized. You do not have permission to view pending requests.',
    'unauthorized_view_requests' => 'Unauthorized. You do not have permission to view requests.',
    'unauthorized_view_details' => 'Unauthorized. You do not have permission to view request details.',

    // Error messages
    'department_a_not_found' => 'Department A not found',
    'no_steps_found' => 'No steps found in this workflow path',
    'no_previous_department' => 'No previous department found to return to',

    // Success messages
    'request_assigned_to_path' => 'Request assigned to workflow path successfully',
    'request_rejected' => 'Request rejected successfully',
    'more_details_requested' => 'More details requested from user',
    'request_completed' => 'Request completed successfully',
    'request_returned_to_previous' => 'Request returned to previous department for revision',
    'evaluation_submitted' => 'Evaluation submitted successfully',

    // Status translations
    'status' => [
        'draft' => 'Draft',
        'pending' => 'Pending',
        'in_review' => 'In Review',
        'in_progress' => 'In Progress',
        'need_more_details' => 'Need More Details',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'completed' => 'Completed',
    ],
];
