<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/app/bootstrap.php';

// Run explicitly on each installation; never generate keys during web requests.
$file=BASE_PATH.'/.env';
$handle=fopen($file,'c+');
if(!$handle || !flock($handle,LOCK_EX)) throw new RuntimeException('Konfigurasi privat tidak dapat dibuka.');
try {
    $contents=stream_get_contents($handle);$append=[];
    $key=(string)($_ENV['EMPLOYEE_PASSWORD_KEY']??'');
    if($key===''){
        $db=App\Core\Database::connection();
        $columns=$db->query('SHOW COLUMNS FROM app_users')->fetchAll(PDO::FETCH_COLUMN);
        if(in_array('password_encrypted',$columns,true) && $db->query('SELECT COUNT(*) FROM app_users WHERE password_encrypted IS NOT NULL')->fetchColumn()) throw new RuntimeException('Password terenkripsi sudah tersedia. Pulihkan kunci dari cadangan; kunci baru tidak dibuat.');
        if(preg_match('/^\s*EMPLOYEE_PASSWORD_KEY\s*=/m',$contents)) throw new RuntimeException('Konfigurasi kunci kosong. Periksa atau pulihkan EMPLOYEE_PASSWORD_KEY sebelum melanjutkan.');
        $key=base64_encode(random_bytes(32));$append[]='EMPLOYEE_PASSWORD_KEY='.$key;
    }
    if(strlen(base64_decode($key,true)?:'')!==32)throw new RuntimeException('Kunci password harus berupa 32 byte acak dalam base64.');
    if(!isset($_ENV['EMPLOYEE_INITIAL_PASSWORD'])){
        if(preg_match('/^\s*EMPLOYEE_INITIAL_PASSWORD\s*=/m',$contents))throw new RuntimeException('Periksa konfigurasi password awal.');
        $append[]='EMPLOYEE_INITIAL_PASSWORD="Mutiara@2026!"';
    } else App\Services\EmployeeAccounts::initialPassword();
    if($append){fseek($handle,0,SEEK_END);$written=fwrite($handle,"\n# Portal karyawan: cadangkan kunci ini bersama database secara privat.\n".implode("\n",$append)."\n");if($written===false)throw new RuntimeException('Konfigurasi gagal ditulis.');fflush($handle);}
    @chmod($file,0600);
    echo "Konfigurasi portal karyawan siap. Kunci yang sudah ada dipertahankan.\n";
} finally {flock($handle,LOCK_UN);fclose($handle);}
