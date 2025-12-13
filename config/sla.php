<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SLA Configuration (Service Level Agreement)
    |--------------------------------------------------------------------------
    |
    | Configure the number of days before sending reminder emails to managers
    | for overdue requests at different stages of the workflow.
    |
    */

    // Days for Department A to review and assign path to new requests
    'dept_a_review_days' => env('SLA_DEPT_A_REVIEW_DAYS', 3),

    // Days for department manager to review and assign to employee
    'dept_manager_review_days' => env('SLA_DEPT_MANAGER_REVIEW_DAYS', 5),

    // Days for employee to complete work
    'employee_work_days' => env('SLA_EMPLOYEE_WORK_DAYS', 7),

    // Days for Department A final validation
    'final_validation_days' => env('SLA_FINAL_VALIDATION_DAYS', 2),
];
