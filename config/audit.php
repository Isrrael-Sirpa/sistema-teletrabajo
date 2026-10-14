<?php

return [
    'per_page' => max(10, min(100, (int) env('AUDIT_LOGS_PER_PAGE', 25))),
];
