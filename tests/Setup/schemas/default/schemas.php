<?php
// default schema config
return [
    'name'        => 'default',
    'description' => 'Default schema for testing AutoJoinEloquent package',
    'factory'     => 'DefaultSchemaFactory',
    'tables'      => [
        'users',
        'agents',
        'departments',
        'agent_department',
    ],
];
