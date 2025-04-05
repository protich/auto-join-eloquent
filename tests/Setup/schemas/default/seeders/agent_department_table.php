<?php
// Return seed data for the "agent_department" pivot table.
return [
    [
        'agent_id'      => 1,
        'department_id' => 1,
        'assigned_at'   => date('Y-m-d H:i:s'),
    ],
    [
        'agent_id'      => 1,
        'department_id' => 2,
        'assigned_at'   => date('Y-m-d H:i:s'),
    ],
    [
        'agent_id'      => 2,
        'department_id' => 1,
        'assigned_at'   => date('Y-m-d H:i:s'),
    ],
    [
        'agent_id'      => 2,
        'department_id' => 2,
        'assigned_at'   => date('Y-m-d H:i:s'),
    ],
    [
        'agent_id'      => 3,
        'department_id' => 2,
        'assigned_at'   => date('Y-m-d H:i:s'),
    ],
];
