<?php
// Manual alternative to /install/: copy this file to config.php and fill it in.
// Generate app_key with:  php -r "echo base64_encode(random_bytes(32));"
// app_key encrypts customer contact details in the database – keep it secret and never lose it.
return [
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => '',
    'db_user' => '',
    'db_pass' => '',
    'base_url' => 'https://www.vasadomena.sk',
    'app_key' => '',
    'debug' => false,
];
