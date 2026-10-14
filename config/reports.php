<?php

return [
    'per_page' => (int) env('REPORTS_PER_PAGE', 25),
    'export_max_rows' => (int) env('REPORT_EXPORT_MAX_ROWS', 5_000),
];
