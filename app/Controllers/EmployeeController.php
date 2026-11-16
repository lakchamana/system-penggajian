<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Controller,Database};
use App\Domain\{PayrollPeriod,PayrollValidationException,PrintLayout};
use App\Services\{EmployeeAccounts,EmployeeSlipPdf,PayrollRepository,PayrollWorkflow};

final class EmployeeController extends Controller {
    private function employee(): array {
        $q=Database::connection()->prepare('SELECT k.*,b.nama_bagian FROM karyawan k LEFT JOIN bagian b ON b.kd_bagian=k.kd_bagian WHERE k.id=?');
        $q->execute([$_SESSION['user']['employee_id']]);
        return $q->fetch();
    }

    private function selectSql(): string {
        $name=PayrollRepository::employeeNameSql();$code=PayrollRepository::departmentCodeSql();$department=PayrollRepository::departmentNameSql();
        return "SELECT p.*,$name AS nama,$department AS nama_bagian FROM penghasilan p LEFT JOIN karyawan k ON k.nip=p.nip LEFT JOIN bagian b ON b.kd_bagian=$code WHERE p.nip=?";
    }

    public function index(): void {
        $db=Database::connection();$nip=$_SESSION['user']['nip'];
        $currentYear=(int)date('Y');$month=(int)date('n');$year=$currentYear;
        $error=null;$rows=[];
        try {
            $selectedMonth=PayrollPeriod::value($_GET['bulan']??$month,'Bulan',1,12);
            $selectedYear=PayrollPeriod::value($_GET['tahun']??$year,'Tahun',1900,9999);
            $month=$selectedMonth;$year=$selectedYear;
        } catch(PayrollValidationException $exception){http_response_code(422);$error=$exception->getMessage();}
        if(!$error){
            $q=$db->prepare($this->selectSql().' AND CAST(p.bulan AS UNSIGNED)=? AND CAST(p.tahun AS UNSIGNED)=? ORDER BY p.id');$q->execute([$nip,$month,$year]);$rows=$q->fetchAll();
        }
        $q=$db->prepare('SELECT DISTINCT tahun FROM penghasilan WHERE nip=? ORDER BY tahun DESC');$q->execute([$nip]);$years=$q->fetchAll(\PDO::FETCH_COLUMN);
        $years=array_filter($years,static fn($value)=>ctype_digit((string)$value) && (int)$value>=1900 && (int)$value<=9999);
        $years=array_unique([...$years,(string)$currentYear,(string)$year]);rsort($years,SORT_NUMERIC);
        $remind=!empty($_SESSION['user']['must_change_password']) && empty($_SESSION['employee_password_reminder_shown']);
        if($remind)$_SESSION['employee_password_reminder_shown']=true;
        $this->view('employee-slips',compact('month','year','years','rows','error','remind')+['title'=>'Slip Gaji Saya','section'=>'Karyawan','print'=>PrintLayout::metadata('')]);
    }

    public function detail(): void {
        $id=PayrollWorkflow::id($_GET['id']??'');
        $q=Database::connection()->prepare($this->selectSql().' AND p.id=?');$q->execute([$_SESSION['user']['nip'],$id]);$row=$q->fetch();
        if(!$row){http_response_code(404);$this->view('error',['errorMessage'=>'Slip gaji tidak ditemukan.'],false);return;}
        redirect('/karyawan/slip?'.http_build_query(['bulan'=>(int)$row['bulan'],'tahun'=>(int)$row['tahun']]));
    }

    public function pdf(): void {
        $month=PayrollPeriod::value($_GET['bulan']??'','Bulan',1,12);
        $year=PayrollPeriod::value($_GET['tahun']??'','Tahun',1900,9999);
        $q=Database::connection()->prepare($this->selectSql().' AND CAST(p.bulan AS UNSIGNED)=? AND CAST(p.tahun AS UNSIGNED)=? ORDER BY p.id');
        $q->execute([$_SESSION['user']['nip'],$month,$year]);$rows=$q->fetchAll();
        if(!$rows){http_response_code(404);$this->view('error',['errorMessage'=>'Slip gaji pada periode ini belum tersedia.'],false);return;}
        $document=(new EmployeeSlipPdf())->render($rows);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="slip-gaji-'.$year.'-'.str_pad((string)$month,2,'0',STR_PAD_LEFT).'.pdf"');
        header('Cache-Control: no-store, private');
        header('Content-Length: '.strlen($document));
        echo $document;
    }

    public function password(): void {
        $error=null;$employee=$this->employee();
        if($_SERVER['REQUEST_METHOD']==='POST'){
            $this->validateCsrf();
            try {
                $version=(new EmployeeAccounts(Database::connection()))->changePassword((int)$_SESSION['user']['id'],(int)$_SESSION['user']['session_version'],(string)($_POST['current_password']??''),(string)($_POST['new_password']??''),(string)($_POST['password_confirmation']??''));
                $_SESSION['user']['session_version']=$version;$_SESSION['user']['must_change_password']=false;
                session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));
                $_SESSION['employee_password_reminder_shown']=true;
                flash('success','Kata sandi berhasil diubah.');redirect('/karyawan/slip');
            } catch(\InvalidArgumentException|\RuntimeException $exception){$error=$exception instanceof \PDOException?'Kata sandi belum berhasil disimpan. Silakan coba kembali.':$exception->getMessage();}
        }
        $this->view('employee-password',['title'=>'Ubah Password','section'=>'Karyawan','employee'=>$employee,'error'=>$error]);
    }
}
