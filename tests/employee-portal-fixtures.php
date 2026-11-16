<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$database=$argv[1]??'';$command=$argv[2]??'create';
if(!preg_match('/^penggajian_employee_test_[a-f0-9]{8}$/D',$database)){fwrite(STDERR,"Nama database uji tidak valid.\n");exit(1);}
$_ENV['DB_DATABASE']=$database;
$_ENV['EMPLOYEE_PASSWORD_KEY']=base64_encode(hash('sha256','employee-portal-synthetic-test-key',true));
$_ENV['EMPLOYEE_INITIAL_PASSWORD']='Mutiara@2026!';
require dirname(__DIR__).'/app/bootstrap.php';
use App\Core\Database;
use App\Services\{AuthSchema,EmployeeAccounts,EmployeePasswordVault};
try {
    $server=Database::connection(false);
    if($command==='create'){
        $q=$server->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name=?');$q->execute([$database]);
        if($q->fetchColumn())throw new RuntimeException('Database uji sudah ada; tidak ditimpa.');
        $server->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$db=Database::connection();
        // Start with the old account schema and an existing administrator.
        $db->exec('CREATE TABLE app_users(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,username VARCHAR(50) NOT NULL UNIQUE,name VARCHAR(150) NOT NULL,password_hash VARCHAR(255) NOT NULL,session_version INT UNSIGNED NOT NULL DEFAULT 1,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
        $db->prepare('INSERT INTO app_users(username,name,password_hash) VALUES(?,?,?)')->execute(['qa_employee_admin','QA Superadmin',password_hash('Qa!Portal-2026-Admin',PASSWORD_DEFAULT)]);
        ob_start();require BASE_PATH.'/scripts/install.php';require BASE_PATH.'/scripts/seed-employee-portal.php';require BASE_PATH.'/scripts/seed-employee-portal.php';ob_end_clean();
        AuthSchema::migrate($db);
        if($db->query("SELECT role FROM app_users WHERE username='qa_employee_admin'")->fetchColumn()!=='superadmin')throw new RuntimeException('Migrasi akun lama gagal.');
        $db->exec("INSERT INTO app_settings(setting_key,setting_value) VALUES('employee_portal_test_fixture','1')");
        $db->exec("INSERT INTO karyawan(nip,nama,kd_bagian,gapok) VALUES('00042','QA Karyawan Baru','DMY',3500000)");
        // A later payroll belonging to another account must not select the first account's period.
        $db->exec("INSERT INTO penghasilan(nip,bulan,tahun,gapok,tpenghasilan,tpotongan,tbersih,hefektif,hmasuk) VALUES('DUMMY002','11','2026',3500000,3500000,0,3500000,22,22)");
    } else {
        $db=Database::connection();
        if($db->query("SELECT setting_value FROM app_settings WHERE setting_key='employee_portal_test_fixture'")->fetchColumn()!=='1')throw new RuntimeException('Identitas database uji tidak sesuai.');
    }
    switch($command){
        case 'create':
        case 'status':
            $fingerprints=[];
            foreach($db->query('SELECT * FROM app_users ORDER BY id')->fetchAll() as $account)$fingerprints[$account['id']]=hash('sha256',json_encode($account,JSON_THROW_ON_ERROR));
            echo json_encode(['current_month'=>(int)date('n'),'current_year'=>(int)date('Y'),'accounts'=>$db->query('SELECT id,username,role,employee_id,is_active,must_change_password,session_version FROM app_users ORDER BY id')->fetchAll(),'account_fingerprints'=>$fingerprints,'employees'=>$db->query('SELECT * FROM karyawan ORDER BY id')->fetchAll(),'creation_audit'=>$db->query("SELECT action,entity,record_key FROM audit_log WHERE action='create_employee_account' OR (action='create' AND entity='karyawan') ORDER BY id")->fetchAll(),'slips'=>$db->query('SELECT id,nip,bulan,tahun FROM penghasilan ORDER BY id')->fetchAll(),'new_employee_id'=>$db->query("SELECT id FROM karyawan WHERE nip='00042'")->fetchColumn(),'reveal_audit'=>(int)$db->query("SELECT COUNT(*) FROM audit_log WHERE action='view_employee_password'")->fetchColumn()],JSON_THROW_ON_ERROR);break;
        case 'cleanup':$server->exec("DROP DATABASE `$database`");break;
        case 'username_collision':
            $db->prepare("INSERT INTO app_users(username,name,password_hash,role) VALUES('QACOLLISION','QA Collision',?,'superadmin')")->execute([password_hash('Qa!Synthetic-Collision',PASSWORD_DEFAULT)]);break;
        case 'fail_account_audit':
            $db->exec("CREATE TRIGGER qa_fail_account_audit BEFORE INSERT ON audit_log FOR EACH ROW BEGIN IF NEW.action='create_employee_account' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic account audit failure'; END IF; END");break;
        case 'restore_account_audit':$db->exec('DROP TRIGGER qa_fail_account_audit');break;
        case 'invalidate_admin':$db->exec("UPDATE app_users SET session_version=session_version+1 WHERE username='qa_employee_admin'");break;
        case 'extremes':
            $numbers=array_fill_keys(array_keys(App\Domain\PayrollFields::numeric()),'0');
            foreach(App\Domain\PayrollFields::INCOME as $field=>$label)$numbers[$field]='12345678901234567';
            foreach(App\Domain\PayrollFields::DEDUCTIONS as $field=>$label)$numbers[$field]='1000000000000001';
            $numbers['gapok']='700000000000000000';$numbers['hefektif']='22';$numbers['hmasuk']='22';
            $numbers=(new App\Services\PayrollCalculator())->totals($numbers);
            $name=str_repeat('Nama Karyawan Sangat Panjang ',5);$department=str_repeat('Bagian Pengujian ',5);
            $record=['nip'=>'DUMMY001','bulan'=>'01','tahun'=>'2027',...$numbers,'source'=>'manual','employee_name'=>$name,'department_code'=>'DMY','department_name'=>$department];
            $columns=implode('`,`',array_keys($record));$marks=implode(',',array_fill(0,count($record),'?'));
            $db->prepare("INSERT INTO penghasilan(`$columns`) VALUES($marks)")->execute(array_values($record));
            echo json_encode(['id'=>(int)$db->lastInsertId(),'name'=>$name,'department'=>$department,'numbers'=>$numbers],JSON_THROW_ON_ERROR);break;
        case 'seed':ob_start();require BASE_PATH.'/scripts/seed-employee-portal.php';ob_end_clean();echo 'Seeder idempoten.';break;
        case 'corrupt':
            $value=$db->query("SELECT password_encrypted FROM app_users WHERE username='DUMMY001'")->fetchColumn();
            $db->prepare("INSERT INTO app_settings(setting_key,setting_value) VALUES('qa_encrypted_backup',?)")->execute([$value]);
            $db->exec("UPDATE app_users SET password_encrypted='corrupted' WHERE username='DUMMY001'");break;
        case 'restore':
            $value=$db->query("SELECT setting_value FROM app_settings WHERE setting_key='qa_encrypted_backup'")->fetchColumn();
            $db->prepare("UPDATE app_users SET password_encrypted=? WHERE username='DUMMY001'")->execute([$value]);$db->exec("DELETE FROM app_settings WHERE setting_key='qa_encrypted_backup'");break;
        case 'unit':
            $vault=new EmployeePasswordVault();$key=$_ENV['EMPLOYEE_PASSWORD_KEY'];$first=$vault->encrypt(2,'Qa!Employee-Unit-Password');$second=$vault->encrypt(2,'Qa!Employee-Unit-Password');
            if($first===$second || $vault->decrypt(2,$first)!=='Qa!Employee-Unit-Password')throw new RuntimeException('Enkripsi atau nonce gagal.');
            $mustFail=static function(callable $fn):void{try{$fn();}catch(RuntimeException $e){return;}throw new LogicException('Operasi seharusnya ditolak.');};
            $mustFail(fn()=>$vault->decrypt(3,$first));$mustFail(fn()=>$vault->decrypt(2,'bad-envelope'));
            $_ENV['EMPLOYEE_PASSWORD_KEY']=base64_encode(random_bytes(32));$mustFail(fn()=>$vault->decrypt(2,$first));
            $_ENV['EMPLOYEE_PASSWORD_KEY']='';$mustFail(fn()=>$vault->encrypt(2,'Qa!Employee-Unit-Password'));
            $before=$db->query("SELECT * FROM app_users WHERE username='DUMMY001'")->fetch();
            $service=new EmployeeAccounts($db);$mustFail(fn()=>$service->changePassword((int)$before['id'],1,'Mutiara@2026!','Qa!Employee-Unit-New','Qa!Employee-Unit-New'));
            if($db->query("SELECT * FROM app_users WHERE username='DUMMY001'")->fetch()!==$before)throw new RuntimeException('Kegagalan kunci mengubah akun.');
            $newEmployee=(int)$db->query("SELECT id FROM karyawan WHERE nip='00042'")->fetchColumn();$mustFail(fn()=>$service->create($newEmployee));
            if($db->query("SELECT COUNT(*) FROM app_users WHERE username='00042'")->fetchColumn())throw new RuntimeException('Kegagalan kunci meninggalkan akun parsial.');
            $_ENV['EMPLOYEE_PASSWORD_KEY']=$key;
            $token=str_repeat('a',64);
            $db->prepare('INSERT INTO password_reset_tokens(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE))')->execute([$before['id'],hash('sha256',$token)]);
            $reset=new App\Services\PasswordReset($db,new App\Services\SmtpMailer());
            if($reset->valid($token) || $reset->reset($token,'Qa!Should-Not-Reset'))throw new RuntimeException('Token email tidak boleh memulihkan akun karyawan.');
            $db->prepare('DELETE FROM password_reset_tokens WHERE user_id=?')->execute([$before['id']]);
            // A collision must roll back the entire seed, never overwrite existing data.
            $db->exec("UPDATE karyawan SET nama='QA Konflik' WHERE nip='DUMMY003'");
            ob_start();$mustFail(static function(){require BASE_PATH.'/scripts/seed-employee-portal.php';});ob_end_clean();
            $db->exec("UPDATE karyawan SET nama='Dummy Karyawan 3' WHERE nip='DUMMY003'");
            if((int)$db->query('SELECT COUNT(*) FROM app_users')->fetchColumn()!==4)throw new RuntimeException('Seeder benturan mengubah akun.');
            echo 'Migrasi lama, enkripsi, isolasi identitas, kegagalan kunci atomik, penolakan reset email karyawan, dan benturan seeder lolos.';break;
        default:throw new RuntimeException('Perintah fixture tidak dikenal.');
    }
}catch(Throwable $exception){fwrite(STDERR,$exception->getMessage().PHP_EOL);exit(1);}
