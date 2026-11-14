<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/app/bootstrap.php';
App\Services\AuthSchema::migrate(App\Core\Database::connection());
echo "Metadata akun dan pemulihan kata sandi siap; akun lama dipertahankan.\n";
