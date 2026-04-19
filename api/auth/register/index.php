<?php
declare(strict_types=1);
$_SERVER['REQUEST_URI'] = preg_replace('#/index\.php$#', '', $_SERVER['REQUEST_URI'] ?? '') ?: '/';
require dirname(__DIR__, 2) . '/index.php';
