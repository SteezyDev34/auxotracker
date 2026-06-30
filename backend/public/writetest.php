<?php
$file = '/var/www/html/storage/logs/laravel.log';
$whoami = shell_exec('whoami');
$result = file_put_contents($file, 'test-apache', FILE_APPEND);
echo json_encode(['user' => trim($whoami), 'path' => $file, 'written' => $result]);
