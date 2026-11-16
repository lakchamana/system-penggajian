<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Controller,Database};
use App\Services\{EmployeeAccounts,EmployeePasswordVault,PayrollWorkflow};

final class EmployeeAccountController extends Controller {
    public function index(): void {
        $db=Database::connection();$error=null;
        if($_SERVER['REQUEST_METHOD']==='POST'){
            $this->validateCsrf();
            try {
                $db->beginTransaction();
                if(($_POST['action']??'')==='create'){
                    $id=(new EmployeeAccounts($db))->create(PayrollWorkflow::id($_POST['employee_id']??''));
                    $this->audit('create_employee_account','app_users',(string)$id);
                    $message='Akun karyawan berhasil dibuat. Gunakan password awal bersama dan ingatkan karyawan untuk menggantinya.';
                } elseif(($_POST['action']??'')==='set_active') {
                    $id=PayrollWorkflow::id($_POST['id']??'');$active=$_POST['is_active']??'';
                    if(!in_array($active,['0','1'],true))throw new \InvalidArgumentException('Status akun tidak valid.');
                    $q=$db->prepare("SELECT id,is_active FROM app_users WHERE id=? AND role='karyawan' FOR UPDATE");$q->execute([$id]);$account=$q->fetch();
                    if(!$account)throw new \InvalidArgumentException('Akun karyawan tidak ditemukan.');
                    $db->prepare('UPDATE app_users SET is_active=?,session_version=session_version+1 WHERE id=?')->execute([(int)$active,$id]);
                    $db->prepare('DELETE FROM password_reset_tokens WHERE user_id=?')->execute([$id]);
                    $this->audit('employee_account_status','app_users',(string)$id,['is_active'=>(int)$account['is_active']],['is_active'=>(int)$active]);
                    $message=$active==='1'?'Akun berhasil diaktifkan. Karyawan dapat masuk kembali.':'Akun dinonaktifkan. Semua sesi akun tersebut telah berakhir.';
                } else {throw new \InvalidArgumentException('Tindakan tidak valid.');}
                $db->commit();flash('success',$message);redirect('/akun-karyawan');
            } catch(\Throwable $exception){
                if($db->inTransaction())$db->rollBack();
                if($exception instanceof \PDOException)$error=$exception->getCode()==='23000'?'Karyawan atau nomor induk sudah memiliki akun.':'Akun belum berhasil disimpan. Silakan coba kembali.';
                elseif($exception instanceof \InvalidArgumentException || $exception instanceof \RuntimeException)$error=$exception->getMessage();
                else throw $exception;
            }
        }
        $search=trim((string)($_GET['q']??''));$params=[];$where="u.role='karyawan'";
        if($search!==''){$where.=' AND (k.nama LIKE ? OR k.nip LIKE ?)';$params=['%'.$search.'%','%'.$search.'%'];}
        $q=$db->prepare("SELECT COUNT(*) FROM app_users u JOIN karyawan k ON k.id=u.employee_id WHERE $where");$q->execute($params);$total=(int)$q->fetchColumn();
        $pages=max(1,(int)ceil($total/25));$page=min($pages,max(1,(int)($_GET['page']??1)));$offset=($page-1)*25;
        $q=$db->prepare("SELECT u.id,u.username,u.is_active,u.must_change_password,k.nama,k.nip,b.nama_bagian FROM app_users u JOIN karyawan k ON k.id=u.employee_id LEFT JOIN bagian b ON b.kd_bagian=k.kd_bagian WHERE $where ORDER BY k.nama,k.nip LIMIT 25 OFFSET $offset");$q->execute($params);$accounts=$q->fetchAll();
        $this->view('employee-accounts',compact('accounts','search','page','pages','total','error')+['title'=>'Akun Karyawan','section'=>'Pengaturan']);
    }

    public function reveal(): void {
        $this->validateCsrf();
        $id=PayrollWorkflow::id($_POST['id']??'');$db=Database::connection();
        $actor=(int)$_SESSION['user']['id'];
        $status=200;$data=[];
        try {
            $db->beginTransaction();
            $q=$db->prepare("SELECT session_version FROM app_users WHERE id=? AND role='superadmin' AND is_active=1 FOR UPDATE");$q->execute([$actor]);$admin=$q->fetch();
            if(!$admin || (int)$admin['session_version']!==(int)$_SESSION['user']['session_version']){
                $status=401;$data=['error'=>'Sesi berakhir. Silakan masuk kembali.'];
            } else {
                $q=$db->prepare("SELECT password_encrypted FROM app_users WHERE id=? AND role='karyawan' FOR UPDATE");$q->execute([$id]);$account=$q->fetch();
                if(!$account){$status=404;$data=['error'=>'Akun karyawan tidak ditemukan.'];}
                else {
                    $password=(new EmployeePasswordVault())->decrypt($id,$account['password_encrypted']);
                    $this->audit('view_employee_password','app_users',(string)$id);
                    $data=['password'=>$password];unset($password);
                }
            }
            $db->commit();
        } catch(\RuntimeException $exception){
            if($db->inTransaction())$db->rollBack();$status=503;$data=['error'=>'Password belum dapat dibuka. Periksa konfigurasi kunci enkripsi dan cadangannya.'];
        } finally {
            if($db->inTransaction())$db->rollBack();
        }
        json_response($data,$status);
    }
}
