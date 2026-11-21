<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
use App\Core\Database;
use App\Controllers\{AuthController,DashboardController,MasterController,PayrollController,ReportController,EmployeeController,EmployeeAccountController};
$path=app_request_path();
$method=$_SERVER['REQUEST_METHOD']??'GET';
if(!in_array($method,['GET','POST'],true)){http_response_code(405);header('Allow: GET, POST');exit;}
if($path==='/setup'){(new AuthController())->setup();exit;}
if($path==='/login'){(new AuthController())->login();exit;}
if($path==='/forgot-password'){(new AuthController())->forgotPassword();exit;}
if($path==='/reset-password'){(new AuthController())->resetPassword();exit;}
if(empty($_SESSION['user'])){if(expects_json())json_response(['error'=>'Silakan masuk kembali.'],401);redirect('/login');}
$q=Database::connection()->prepare('SELECT u.*,k.nip,k.nama AS employee_name FROM app_users u LEFT JOIN karyawan k ON k.id=u.employee_id WHERE u.id=?');$q->execute([$_SESSION['user']['id']]);$user=$q->fetch();
if(!$user || !$user['is_active'] || !in_array($user['role'],['superadmin','karyawan'],true) || (int)$user['session_version']!==(int)$_SESSION['user']['session_version'] || ($user['role']==='karyawan' && ($user['nip']===null || $user['username']!==$user['nip']))){$_SESSION=[];if(expects_json())json_response(['error'=>'Sesi berakhir. Silakan masuk kembali.'],401);redirect('/login');}
$_SESSION['user']=account_session($user);
unset($user);
if($path==='/logout' && $method==='POST'){(new AuthController())->logout();exit;}
if($_SESSION['user']['role']==='karyawan'){
    if($path==='/'){redirect(user_home());}
    if($path==='/karyawan/ubah-password'){(new EmployeeController())->password();exit;}
    if($path==='/karyawan/slip' && $method==='GET'){(new EmployeeController())->index();exit;}
    if($path==='/karyawan/slip/pdf' && $method==='GET'){(new EmployeeController())->pdf();exit;}
    if($path==='/karyawan/slip/detail' && $method==='GET'){(new EmployeeController())->detail();exit;}
    if(expects_json())json_response(['error'=>'Anda tidak memiliki akses ke halaman ini.'],403);
    http_response_code(403);$errorMessage='Halaman ini hanya dapat diakses superadmin.';require __DIR__.'/app/Views/error.php';exit;
}
if($path==='/akun-karyawan'){(new EmployeeAccountController())->index();exit;}
if($path==='/akun-karyawan/password' && $method==='POST'){(new EmployeeAccountController())->reveal();exit;}
if($path==='/profil'){(new AuthController())->profile();exit;}
if($path==='/'){(new DashboardController())->index();exit;}
if($path==='/migrasi'){(new DashboardController())->migration();exit;}
if($path==='/arsip'){(new DashboardController())->archive();exit;}
if($path==='/master/tunj_konsumsi'){(new MasterController())->consumption();exit;}
if(preg_match('~^/master/([a-z_]+)$~',$path,$m) && isset(App\Domain\MasterModules::all()[$m[1]])){(new MasterController())->index($m[1]);exit;}
if($path==='/transaksi'){(new PayrollController())->index();exit;}
if($path==='/transaksi/form'){(new PayrollController())->form();exit;}
if($path==='/api/karyawan'){(new PayrollController())->employees();exit;}
if($path==='/api/bagian'){(new PayrollController())->departments();exit;}
if($path==='/api/hitung' && $method==='POST'){(new PayrollController())->calculate();exit;}
if($path==='/laporan'){(new ReportController())->index();exit;}
http_response_code(404);$errorMessage='Halaman yang Anda cari tidak ditemukan.';require __DIR__.'/app/Views/error.php';
