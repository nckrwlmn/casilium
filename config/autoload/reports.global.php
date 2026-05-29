<?php

declare(strict_types=1);

return [
    // Company details used across all generated reports (SLA documents, etc.)
    // Override these in reports.local.php for your installation
    'reports' => [
        'company' => [
            'name'          => 'Example Company',
            'subtitle'      => 'Example Company Managed Services',
            'email'         => 'info@example.com',
            'address'       => [
                'address_line_1' => 'Example Company Limited',
                'address_line_2' => '123 Business Street',
                'city'           => 'City',
                'state'          => '',
                'postcode'       => 'AB1 2CD',
                'country'        => 'United Kingdom',
            ],
            'prepared_by'   => [
                'name' => 'John Smith',
                'role' => 'Director',
            ],
            'support_email' => 'support@example.com',
            'support_phone' => '+44 0000 000000',
            'logo_path'     => '',
            'version'       => '',
        ],
    ],
];
