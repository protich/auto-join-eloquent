<?php
// Return seed data for the "users" table.
return [
    [
        'name'       => 'Peter Rotich',
        'phone'      => '666-666-6666',
        'email'      => 'peter@osticket.com',
        'password'   => bcrypt('secret'),
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ],
    [
        'name'       => 'Alice',
        'phone'      => '111-222-3333',
        'email'      => 'alice@example.com',
        'password'   => bcrypt('secret'),
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ],
    [
        'name'       => 'Bob',
        'phone'      => '444-555-6666',
        'email'      => 'bob@example.com',
        'password'   => bcrypt('secret'),
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ],
];
