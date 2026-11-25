<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Document storage
    |--------------------------------------------------------------------------
    |
    | This disk must remain private. Downloads will be served only after an
    | authorization policy has granted access to the authenticated user.
    |
    */
    'disk' => env('DOCUMENTS_DISK', 'documents'),

    'uploads' => [
        'max_size_kb' => (int) env('DOCUMENT_MAX_FILE_SIZE_KB', 20_480),
        'max_files_per_request' => (int) env('DOCUMENT_MAX_FILES_PER_REQUEST', 10),
        'extensions' => [
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'jpg', 'jpeg', 'png', 'zip',
        ],
        'mime_types' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'image/jpeg',
            'image/png',
            'application/zip',
            'application/x-zip-compressed',
        ],
        'mime_types_by_extension' => [
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword', 'application/vnd.ms-office', 'application/cdfv2', 'application/x-ole-storage'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/x-zip-compressed'],
            'xls' => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/cdfv2', 'application/x-ole-storage'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/x-zip-compressed'],
            'ppt' => ['application/vnd.ms-powerpoint', 'application/vnd.ms-office', 'application/cdfv2', 'application/x-ole-storage'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/x-zip-compressed'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'zip' => ['application/zip', 'application/x-zip-compressed'],
        ],
    ],

    'deadlines' => [
        'due_soon_days' => (int) env('DOCUMENT_DUE_SOON_DAYS', 3),
        'reminder_hour' => (int) env('DOCUMENT_DEADLINE_REMINDER_HOUR', 8),
    ],

    'workflow' => [
        'reason_max_length' => (int) env('DOCUMENT_DECISION_REASON_MAX_LENGTH', 2_000),
        'annulment_reason_max_length' => (int) env('DOCUMENT_ANNULMENT_REASON_MAX_LENGTH', 2_000),
        'code_digits' => (int) env('DOCUMENT_CODE_DIGITS', 4),
    ],

    'history' => [
        'per_page' => (int) env('DOCUMENT_HISTORY_PER_PAGE', 15),
    ],

    'search' => [
        'per_page' => (int) env('DOCUMENT_SEARCH_PER_PAGE', 15),
    ],

    'notifications' => [
        'per_page' => (int) env('DOCUMENT_NOTIFICATIONS_PER_PAGE', 15),
    ],
];
