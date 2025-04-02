<?php
// tests/Setup/default/schema.php

return [
    'name'        => 'default',
    'description' => 'Default schema for testing AutoJoinEloquent package',
    'tables'      => [
        'users',
        'agents',
        'departments',
        'agent_department',
    ],
];
