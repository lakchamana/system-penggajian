<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/app/bootstrap.php';

use App\Core\Database;
use App\Domain\PayrollFields;
use App\Services\{EmployeeAccounts,PayrollCalculator};

$db=Database::connection();
$lock=$db->query("SELECT GET_LOCK('mh_employee_portal_dummy_v1',5)")->fetchColumn();
if(!$lock)throw new RuntimeException('Seeder sedang dijalankan. Coba kembali.');
try {
    $db->beginTransaction();
    $q=$db->prepare('SELECT setting_value FROM app_settings WHERE setting_key=? FOR UPDATE');$q->execute(['employee_portal_dummy_v1']);$marker=$q->fetchColumn();
    $ids=$marker?json_decode($marker,true,512,JSON_THROW_ON_ERROR):[];
    if($marker && (!is_array($ids) || count($ids)!==3))throw new RuntimeException('Penanda dummy tidak sesuai. Data tidak diubah.');
    $q=$db->query("SELECT * FROM bagian WHERE kd_bagian='DMY' FOR UPDATE");$department=$q->fetch();
    if($department && $department['nama_bagian']!=='Bagian Dummy')throw new RuntimeException('Kode DMY sudah dipakai bagian lain. Data tidak diubah.');
    if(!$department){if($marker)throw new RuntimeException('Bagian dummy berubah. Data tidak diubah.');$db->exec("INSERT INTO bagian(kd_bagian,nama_bagian) VALUES('DMY','Bagian Dummy')");}
    $created=[];
    for($i=1;$i<=3;$i++){
        $nip='DUMMY00'.$i;$name='Dummy Karyawan '.$i;$salary=(string)(2500000+$i*500000);
        $q=$db->prepare('SELECT * FROM karyawan WHERE nip=? FOR UPDATE');$q->execute([$nip]);$employee=$q->fetch();
        if(!$marker){
            $q=$db->prepare('SELECT id FROM app_users WHERE username=?');$q->execute([$nip]);
            if($employee || $q->fetchColumn())throw new RuntimeException('Identitas dummy sudah digunakan. Seeder tidak menimpa data yang ada.');
            $db->prepare("INSERT INTO karyawan(nip,nama,kd_bagian,gapok,t_jabatan,t_harian,t_lembur) VALUES(?,?,'DMY',?,0,0,0)")->execute([$nip,$name,$salary]);
            $employeeId=(int)$db->lastInsertId();$accountId=(new EmployeeAccounts($db))->create($employeeId);
            $input=array_fill_keys(array_keys(PayrollFields::numeric()),'0');
            $input=[...$input,'nip'=>$nip,'bulan'=>'10','tahun'=>'2026','gapok'=>$salary,'hefektif'=>'22','hmasuk'=>'22'];
            $calculation=(new PayrollCalculator())->evaluate($input,false);
            $values=['nip'=>$nip,'bulan'=>'10','tahun'=>'2026',...$calculation['values'],'source'=>'dummy','employee_name'=>$name,'department_code'=>'DMY','department_name'=>'Bagian Dummy','calculation_version'=>$calculation['calculation_version'],'calculation_snapshot'=>json_encode($calculation['snapshot'],JSON_THROW_ON_ERROR)];
            $columns=implode('`,`',array_keys($values));$marks=implode(',',array_fill(0,count($values),'?'));
            $db->prepare("INSERT INTO penghasilan(`$columns`) VALUES($marks)")->execute(array_values($values));
            $created[]=['employee_id'=>$employeeId,'account_id'=>$accountId,'payroll_id'=>(int)$db->lastInsertId()];
        } else {
            $expected=$ids[$i-1];
            if(!$employee || (int)$employee['id']!==(int)$expected['employee_id'] || $employee['nama']!==$name || $employee['kd_bagian']!=='DMY' || (string)$employee['gapok']!==$salary)throw new RuntimeException('Data karyawan dummy berubah. Seeder dihentikan tanpa menimpa data.');
            $q=$db->prepare('SELECT id,role,employee_id FROM app_users WHERE username=? FOR UPDATE');$q->execute([$nip]);$account=$q->fetch();
            if(!$account || (int)$account['id']!==(int)$expected['account_id'] || $account['role']!=='karyawan' || (int)$account['employee_id']!==(int)$employee['id'])throw new RuntimeException('Relasi akun dummy berubah. Data tidak diubah.');
            $q=$db->prepare('SELECT * FROM penghasilan WHERE id=? FOR UPDATE');$q->execute([$expected['payroll_id']]);$payroll=$q->fetch();
            if(!$payroll || $payroll['nip']!==$nip || (int)$payroll['bulan']!==10 || (int)$payroll['tahun']!==2026 || $payroll['source']!=='dummy' || $payroll['employee_name']!==$name || $payroll['department_code']!=='DMY' || $payroll['department_name']!=='Bagian Dummy')throw new RuntimeException('Slip dummy berubah. Data tidak diubah.');
            foreach(PayrollFields::numeric() as $field=>$label){$expectedValue=in_array($field,['gapok','tpenghasilan','tbersih'],true)?$salary:(in_array($field,['hefektif','hmasuk'],true)?'22':'0');if((string)$payroll[$field]!==$expectedValue)throw new RuntimeException('Nominal slip dummy berubah. Data tidak diubah.');}
            foreach(['t_jabatan','t_harian','t_lembur'] as $field)if((string)$employee[$field]!=='0')throw new RuntimeException('Master dummy berubah. Data tidak diubah.');
        }
    }
    if(!$marker)$db->prepare('INSERT INTO app_settings(setting_key,setting_value) VALUES(?,?)')->execute(['employee_portal_dummy_v1',json_encode($created,JSON_THROW_ON_ERROR)]);
    $db->commit();echo $marker?"Tiga akun dummy sudah tersedia; password dan data dipertahankan.\n":"Tiga karyawan, akun, dan slip Oktober 2026 berhasil dibuat.\n";
} catch(Throwable $exception){if($db->inTransaction())$db->rollBack();throw $exception;}
finally {$db->query("SELECT RELEASE_LOCK('mh_employee_portal_dummy_v1')");}
