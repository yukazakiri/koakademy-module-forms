<?php

declare(strict_types=1);

return [
    'admin_prefix' => 'administrators/forms',
    'public_prefix' => 'forms',
    'uploads_disk' => env('FORMS_UPLOADS_DISK', 'local'),
    'max_upload_kilobytes' => (int) env('FORMS_MAX_UPLOAD_KILOBYTES', 10240),
    'submission_throttle' => env('FORMS_SUBMISSION_THROTTLE', 'forms'),
    'default_status' => 'draft',
    'default_access_mode' => 'authenticated',
    'default_identity_type' => 'email',
];
