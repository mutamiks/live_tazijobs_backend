<?php

return [
    'roles' => [
        'admin' => ['*'],

        'job_seeker' => [
            'manage_job_seeker_profile',
            'view_subscription_packages',
            'manage_own_subscription',
            'browse_jobs',
            'apply_for_jobs',
            'view_own_applications',
            'view_notifications',
        ],

        'employer' => [
            'manage_employer_profile',
            'manage_employer_jobs',
            'view_employer_applications',
            'update_employer_application_status',
            'browse_workers',
            'manage_worker_orders',
            'view_notifications',
        ],
    ],
];
