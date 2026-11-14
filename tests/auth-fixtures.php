<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$database=$argv[1]??'';
if (!preg_match('/^penggajian_auth_test_[a-f0-9]{8}$/D',$database)) { fwrite(STDERR,"Nama database uji tidak valid.\n");exit(1); }
$_ENV['DB_DATABASE']=$database;
require dirname(__DIR__).'/app/bootstrap.php';
try {
    $server=App\Core\Database::connection(false);
    $command=$argv[2]??'create';
    if ($command==='create') {
        $q=$server->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name=?');$q->execute([$database]);
        if ($q->fetchColumn()) throw new RuntimeException('Database sudah ada; tidak ditimpa.');
        ob_start();require BASE_PATH.'/scripts/install.php';ob_end_clean();
        // Exercise upgrades from the old schema, then repeat the migration.
        $db=App\Core\Database::connection();
        $db->exec('ALTER TABLE app_users DROP COLUMN email');
        App\Services\AuthSchema::migrate($db);
        App\Services\AuthSchema::migrate($db);
        echo "Fixture siap.\n";exit;
    }
    $db=App\Core\Database::connection();
    $users=$db->query('SELECT username FROM app_users ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    if ($users!==[] && $users!==['qa_auth']) throw new RuntimeException('Identitas database uji tidak sesuai.');
    switch ($command) {
        case 'cleanup': $server->exec("DROP DATABASE `$database`");break;
        case 'limits': $db->exec('DELETE FROM password_reset_attempts');break;
        case 'expire': $db->exec('UPDATE password_reset_tokens SET expires_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE)');break;
        case 'status':
            echo json_encode(['user'=>$db->query('SELECT email,session_version FROM app_users')->fetch(),'tokens'=>$db->query('SELECT token_hash FROM password_reset_tokens')->fetchAll(),'audit'=>(int)$db->query("SELECT COUNT(*) FROM audit_log WHERE action='password_reset'")->fetchColumn()],JSON_THROW_ON_ERROR);break;
        default: throw new RuntimeException('Perintah tidak valid.');
    }
} catch(Throwable $error) { fwrite(STDERR,$error->getMessage().PHP_EOL);exit(1); }
