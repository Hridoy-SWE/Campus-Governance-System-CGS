<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_bootstrap.php';

admin_logout();
admin_redirect_absolute(MAIN_SITE_URL);
