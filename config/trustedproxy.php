<?php

$proxies = array_values(array_filter(array_map(
    static fn (string $proxy): string => trim($proxy),
    explode(',', (string) env('TRUSTED_PROXIES', '')),
)));

return [
    /*
    | Only explicit proxy IP addresses or CIDR ranges belong here. Never use
    | "*" in production because the resolved client IP is written to audits.
    */
    'proxies' => $proxies,
];
