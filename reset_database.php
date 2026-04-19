<?php
declare(strict_types=1);
require_once __DIR__ . '/api/bootstrap.php';
@unlink(CGS_DB_PATH);
@unlink(CGS_DB_PATH . '-shm');
@unlink(CGS_DB_PATH . '-wal');
cgs_db();
echo "Database reset complete: " . CGS_DB_PATH . PHP_EOL;
