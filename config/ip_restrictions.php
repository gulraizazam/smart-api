<?php

/*
 * IP restriction rules by role.
 * Roles listed here will be denied access from any IP not in their allowed list.
 * Managed here instead of hardcoded in CheckIpRestriction middleware.
 */

$restrictedIps = [
    '203.215.176.205',
    '203.215.176.206',
    '203.215.181.201',
    '203.215.181.206',
    '119.30.71.34',
    '119.30.71.36',
    '103.8.112.42',
    '103.8.112.107',
    '103.8.112.43',
    '139.135.36.214',
];

return [
    'restricted_roles' => ['CSR', 'CSR Supervisor', 'Quality Assurance'],
    'allowed_ips'      => $restrictedIps,
];
