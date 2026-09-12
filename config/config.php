<?php

declare(strict_types=1);

return [
    'app_name' => 'DNET Vendor Assessment',

    'base_url' => rtrim(
        getenv('APP_URL') ?: '',
        '/'
    ),

    'db_host' => getenv('DB_HOST') ?: 'assessment-db',
    'db_port' => getenv('DB_PORT') ?: '3306',
    'db_name' => getenv('DB_DATABASE') ?: 'assessment',
    'db_user' => getenv('DB_USERNAME') ?: 'assessment',
    'db_password' => getenv('DB_PASSWORD') ?: '',

    'upload_dir' => __DIR__ . '/../storage/uploads',

    'max_upload_mb' => 10,

    'session_name' => 'dnet_assessment',
];
