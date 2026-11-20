<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Controller,Database};
use App\Domain\MasterModules;
use App\Services\{ConsumptionPolicy,EmployeeAccounts,PayrollRepository};
final class MasterController extends Controller {
    public function index(string $module): void {
        $meta=MasterModules::get($module);$table=$meta['table'];$db=Database::connection();$error=null;$edit=null;
        if($_SERVER['REQUEST_METHOD']==='POST') {
            $this->validateCsrf();$id=max(0,(int)($_POST['id']??0));$submittedId=$id;$action=$_POST['action']??'save';
            try {
                $db->beginTransaction();
                if($action==='delete') {
                    $before=$this->find($meta,$id);if(!$before)throw new \InvalidArgumentException('Data tidak ditemukan.');
                    if($module==='bagian') {
                        $consumption = ConsumptionPolicy::load($db, true);
                        if (in_array($before['kd_bagian'], $consumption['departments'] ?? [], true)) {
                            throw new \InvalidArgumentException('Bagian ini masih menerima tunjangan konsumsi. Hapus pilihannya di Data Konsumsi dulu.');
                        }
                        foreach(['karyawan','tunj_harian','tunj_konsumsi','tunj_lembur','tunj_infal','tunj_overtime'] as $t){$q=$db->prepare("SELECT COUNT(*) FROM `$t` WHERE kd_bagian=?");$q->execute([$before['kd_bagian']]);if($q->fetchColumn())throw new \InvalidArgumentException('Bagian masih digunakan. Perbarui data terkait sebelum menghapus.');}
                    } elseif($module==='karyawan') {
                        $q=$db->prepare('SELECT id FROM app_users WHERE employee_id=?');$q->execute([$id]);if($q->fetchColumn())throw new \InvalidArgumentException('Karyawan masih terhubung ke akun. Nonaktifkan akses akun; riwayat karyawan tetap disimpan.');
                        foreach(['penghasilan','tunj_jam','p_anak','p_bjbs','p_bank','p_lunak','p_lain','tabungan'] as $t){$q=$db->prepare("SELECT COUNT(*) FROM `$t` WHERE nip=?");$q->execute([$before['nip']]);if($q->fetchColumn())throw new \InvalidArgumentException('Karyawan masih memiliki transaksi atau komponen gaji. Data tidak dihapus.');}
                    }
                    $db->prepare("DELETE FROM `$table` WHERE id=?")->execute([$id]);$this->audit('delete',$module,(string)$id,$before);$db->commit();flash('success','Data berhasil dihapus.');redirect('/master/'.$module);
                }
                $data=[];
                foreach($meta['fields'] as $field=>$label) {
                    $v=trim((string)($_POST[$field]??''));
                    if(MasterModules::monetary($field)) {if(!preg_match('/^\d{1,18}$/',$v))throw new \InvalidArgumentException("$label harus berupa bilangan bulat positif atau nol, maksimal 18 digit.");$v=bcadd($v,'0',0);}
                    else {if($v==='')throw new \InvalidArgumentException("$label wajib diisi.");$max=match($field){'nip'=>15,'kd_bagian'=>5,'nama'=>150,default=>100};if(mb_strlen($v)>$max)throw new \InvalidArgumentException("$label maksimal $max karakter.");}
                    $data[$field]=$v;
                }
                if($module!=='bagian' && isset($data['kd_bagian'])) {$q=$db->prepare('SELECT COUNT(*) FROM bagian WHERE kd_bagian=?');$q->execute([$data['kd_bagian']]);if(!$q->fetchColumn())throw new \InvalidArgumentException('Bagian tidak ditemukan.');}
                if($module!=='karyawan' && isset($data['nip'])) {$q=$db->prepare('SELECT COUNT(*) FROM karyawan WHERE nip=?');$q->execute([$data['nip']]);if(!$q->fetchColumn())throw new \InvalidArgumentException('Karyawan tidak ditemukan.');}
                $columns=array_keys($meta['fields']);$set=implode(',',array_map(static fn($f)=>"`$f`=?",$columns));$cols=implode('`,`',$columns);$marks=implode(',',array_fill(0,count($columns),'?'));
                if($id){$before=$this->find($meta,$id);if(!$before)throw new \InvalidArgumentException('Data tidak ditemukan.');if($before[$meta['key']]!==$data[$meta['key']])throw new \InvalidArgumentException('Identitas data tidak dapat diganti saat mengubah data.');$q=$db->prepare("UPDATE `$table` SET $set WHERE id=?");$q->execute([...array_values($data),$id]);}
                else {$before=null;$q=$db->prepare("INSERT INTO `$table` (`$cols`) VALUES($marks)");$q->execute(array_values($data));$id=(int)$db->lastInsertId();}
                $createdAccount=$module==='karyawan' && $before===null;
                if($createdAccount){
                    $accountId=(new EmployeeAccounts($db))->create($id);
                    $this->audit('create_employee_account','app_users',(string)$accountId);
                }
                $this->audit($before?'update':'create',$module,(string)$id,$before,$data);$db->commit();
                flash('success',$createdAccount?'Karyawan dan akun sudah dibuat. Lihat kata sandi awal di menu Akun Karyawan.':'Data berhasil disimpan.');redirect('/master/'.$module);
            } catch(\Throwable $e){
                if($db->inTransaction())$db->rollBack();
                if($e instanceof \PDOException)$error=$e->getCode()==='23000'?'Kode atau NIP sudah digunakan pada modul ini.':'Data belum berhasil disimpan. Periksa isian lalu coba kembali.';
                elseif($e instanceof \InvalidArgumentException)$error=$e->getMessage();
                elseif ($e instanceof \RuntimeException && $module === 'karyawan' && $submittedId === 0 && $action === 'save') {
                    error_log(sprintf('Pembuatan akun karyawan gagal di %s:%d. Periksa konfigurasi kata sandi dan enkripsi portal.', basename($e->getFile()), $e->getLine()));
                    http_response_code(503);
                    $error = 'Karyawan belum tersimpan. Hubungi pengelola aplikasi.';
                }
                else throw $e;
                $edit=[...$_POST,'id'=>$submittedId];
            }
        }
        $editId=filter_var($_GET['edit']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!$edit && $editId)$edit=$this->find($meta,$editId);
        $search=trim((string)($_GET['q']??''));$page=max(1,(int)($_GET['page']??1));$offset=($page-1)*25;
        $join=$module==='karyawan'?' LEFT JOIN bagian b ON b.kd_bagian=m.kd_bagian':($module==='bagian'?'':($meta['key']==='nip'?' LEFT JOIN karyawan k ON k.nip=m.nip':' LEFT JOIN bagian b ON b.kd_bagian=m.kd_bagian'));
        $extra=$module==='bagian'?'':($meta['key']==='nip' && $module!=='karyawan'?',k.nama AS reference_name':',b.nama_bagian AS reference_name');
        $searchCols=['m.'.$meta['key']];if($module==='bagian')$searchCols[]='m.nama_bagian';elseif($module==='karyawan')$searchCols[]='m.nama';else $searchCols[]=($meta['key']==='nip'?'k.nama':'b.nama_bagian');
        $where=$search!==''?' WHERE ('.implode(' OR ',array_map(static fn($c)=>"$c LIKE ?",$searchCols)).')':'';$params=$search!==''?array_fill(0,count($searchCols),'%'.$search.'%'):[];
        $q=$db->prepare("SELECT COUNT(*) FROM `$table` m $join $where");$q->execute($params);$total=(int)$q->fetchColumn();
        $q=$db->prepare("SELECT m.* $extra FROM `$table` m $join $where ORDER BY m.`{$meta['key']}` LIMIT 25 OFFSET $offset");$q->execute($params);
        $suggestionName=match(true){$module==='bagian'=>'m.nama_bagian',$module==='karyawan'=>'m.nama',$meta['key']==='nip'=>'k.nama',default=>'b.nama_bagian'};
        $suggestionJoin=$join;$suggestionDepartment='';
        if($meta['key']==='nip'){
            $employeeAlias=$module==='karyawan'?'m':'k';$departmentAlias=$module==='karyawan'?'b':'employee_bagian';
            if($module!=='karyawan')$suggestionJoin.=' LEFT JOIN bagian employee_bagian ON employee_bagian.kd_bagian=k.kd_bagian';
            $suggestionDepartment=",$employeeAlias.kd_bagian,$departmentAlias.nama_bagian";
        }
        $searchOptions=$db->query("SELECT m.`{$meta['key']}` AS code,$suggestionName AS name $suggestionDepartment FROM `$table` m $suggestionJoin ORDER BY $suggestionName,m.`{$meta['key']}`")->fetchAll();
        $this->view('master',['title'=>$meta['title'],'section'=>ucfirst($meta['group']),'module'=>$module,'meta'=>$meta,'rows'=>$q->fetchAll(),'total'=>$total,'page'=>$page,'search'=>$search,'edit'=>$edit,'error'=>$error,'searchOptions'=>$searchOptions,'showForm'=>$edit || isset($_GET['new']) || $error,'departments'=>$db->query('SELECT kd_bagian,nama_bagian FROM bagian ORDER BY kd_bagian')->fetchAll(),'employees'=>(new PayrollRepository())->employeeOptions()]);
    }
    public function consumption(): void
    {
        $db = Database::connection();
        $departmentQuery = 'SELECT kd_bagian,nama_bagian FROM bagian ORDER BY kd_bagian';
        $error = null;
        $submitted = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();
            $rate = $_POST['rate'] ?? null;
            $selected = $_POST['departments'] ?? [];
            $submitted = [
                'rate' => is_string($rate) ? $rate : '',
                'departments' => array_values(array_filter((array)$selected, 'is_string')),
            ];
            $db->beginTransaction();
            try {
                $codes = $db->query($departmentQuery . ' FOR UPDATE')->fetchAll(\PDO::FETCH_COLUMN);
                if (!is_string($rate) || !preg_match('/^[0-9]{1,18}$/D', $rate)) {
                    throw new \InvalidArgumentException('Masukkan tarif konsumsi dalam rupiah, maksimal 18 digit.');
                }
                if (!is_array($selected) || array_filter($selected, 'is_string') !== $selected || array_diff($selected, $codes)) {
                    throw new \InvalidArgumentException('Pilih bagian yang tersedia di daftar.');
                }
                $next = [
                    'rate' => bcadd($rate, '0', 0),
                    'departments' => array_values(array_intersect($codes, $selected)),
                ];
                $previous = ConsumptionPolicy::load($db, true);
                if ($previous !== $next) {
                    ConsumptionPolicy::save($db, $next);
                    $this->audit('update', 'app_settings', ConsumptionPolicy::KEY, $previous, $next);
                }
                $db->commit();
                flash('success', 'Tarif konsumsi sudah disimpan.');
                redirect('/master/tunj_konsumsi');
            } catch (\Throwable $exception) {
                if ($db->inTransaction()) $db->rollBack();
                if (!$exception instanceof \InvalidArgumentException) throw $exception;
                $error = $exception->getMessage();
            }
        }

        $policy = ConsumptionPolicy::load($db);
        $this->view('consumption', [
            'title' => 'Data Konsumsi',
            'section' => 'Master',
            'error' => $error,
            'form' => $submitted ?? $policy ?? ['rate' => '', 'departments' => []],
            'departments' => $db->query($departmentQuery)->fetchAll(),
            'legacyRates' => $policy === null
                ? $db->query('SELECT t.kd_bagian,b.nama_bagian,t.jumlah FROM tunj_konsumsi t LEFT JOIN bagian b ON b.kd_bagian=t.kd_bagian ORDER BY t.kd_bagian')->fetchAll()
                : [],
        ]);
    }

    private function find(array $meta,int $id): array|false {if(!isset($meta['table']))throw new \InvalidArgumentException('Modul tidak valid.');$table=$meta['table'];$q=Database::connection()->prepare("SELECT * FROM `$table` WHERE id=? FOR UPDATE");$q->execute([$id]);return $q->fetch();}
}
