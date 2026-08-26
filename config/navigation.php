<?php

declare(strict_types=1);

return [
    'admin' => [
        [
            'id' => 'admin-forms',
            'title' => 'Online Forms',
            'link' => '/administrators/forms',
            'inertiaPage' => 'Forms/Index',
            'section' => 'student_services',
            'icon' => 'clipboard_check',
            'requiredPermission' => [
                'forms.view',
                'ViewAny:Form',
            ],
        ],
    ],
];
