<?php
$currentPath=app_request_path();
$isEmployee=($_SESSION['user']['role']??'superadmin')==='karyawan';
$isEmployeeSlip=$isEmployee && $currentPath==='/karyawan/slip';
$isPortraitSlipReport=$currentPath==='/laporan' && ($isSlip??false);
$masterLinks=[
 'bagian'=>'Data Bagian',
 'tunj_harian'=>'Data Harian',
 'tunj_konsumsi'=>'Data Konsumsi',
 'tunj_infal'=>'Data Infal',
 'tunj_overtime'=>'Data Overtime',
 'tunj_jam'=>'Data Kelebihan Mengajar',
 'p_anak'=>'Potongan Anak',
 'p_bjbs'=>'Potongan BPJS',
 'p_lunak'=>'Potongan Pinjaman Lunak',
 'p_bank'=>'Potongan Pinjaman Bank',
 'p_lain'=>'Potongan Lain-lain',
];
$reportLinks=[
 'slip_karyawan'=>'Slip Gaji',
 'slip_bagian'=>'Slip Gaji per Bagian',
 'rekap_bagian'=>'Rekap Gaji per Bagian',
 'rekap_periode'=>'Rekap Gaji per Periode',
 'rekap_periode_bagian'=>'Rekap Gaji per Periode per Bagian',
 'absensi'=>'Rekap Absensi',
 'gapok'=>'Rekap Gapok',
];
$activeNavGroup=match(true){
 $currentPath==='/master/karyawan'||str_starts_with($currentPath,'/transaksi')=>'transaksi',
 $currentPath==='/laporan'=>'laporan',
 str_starts_with($currentPath,'/master/')=>'master',
 default=>null,
};
$navigationSection=$currentPath==='/'?'Dashboard':($activeNavGroup!==null?ucfirst($activeNavGroup):($section??'Penggajian'));
$selectedReport=(string)($_GET['type']??'slip_karyawan');
if(!isset($reportLinks[$selectedReport]))$selectedReport='slip_karyawan';
$navGroups=[
 'master'=>['Master','building',array_map(static fn($key,$label)=>['label'=>$label,'path'=>'master/'.$key,'query'=>[],'active'=>$currentPath==='/master/'.$key],array_keys($masterLinks),array_values($masterLinks))],
 'transaksi'=>['Transaksi','receipt',[
  ['label'=>'Karyawan','path'=>'master/karyawan','query'=>[],'active'=>$currentPath==='/master/karyawan'],
  ['label'=>'Penggajian','path'=>'transaksi','query'=>[],'active'=>str_starts_with($currentPath,'/transaksi')],
 ]],
 'laporan'=>['Laporan','chart',array_map(static fn($key,$label)=>['label'=>$label,'path'=>'laporan','query'=>['type'=>$key],'active'=>$currentPath==='/laporan'&&$selectedReport===$key],array_keys($reportLinks),array_values($reportLinks))],
];
$flashMessages=$_SESSION['flash']??[];unset($_SESSION['flash']);
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="app-base-path" content="<?=e(app_base_path())?>"><meta name="csrf-token" content="<?=e(csrf_token())?>"><meta name="theme-color" content="#0B6B43"><title><?=e($title??'Penggajian')?> · Mutiara Hikmah</title><link rel="icon" type="image/svg+xml" href="<?=e(url('public/assets/brand.svg'))?>"><link rel="stylesheet" href="<?=e(url('public/assets/vendor/bootstrap.min.css'))?>"><link rel="stylesheet" href="<?=e(url('public/assets/app.css',['v'=>(string)filemtime(BASE_PATH.'/public/assets/app.css')]))?>"><?php if($isEmployee || $currentPath==='/akun-karyawan'):?><link rel="stylesheet" href="<?=e(url('public/assets/employee.css',['v'=>(string)filemtime(BASE_PATH.'/public/assets/employee.css')]))?>"><?php endif;?><?php if($isEmployeeSlip || $isPortraitSlipReport):?><link rel="stylesheet" media="<?=$isEmployeeSlip?'all':'print'?>" href="<?=e(url('public/assets/employee-slip.css',['v'=>(string)filemtime(BASE_PATH.'/public/assets/employee-slip.css')]))?>"><?php endif;?></head><body>
<a class="skip-link" href="#main-content">Lewati navigasi</a>
<button class="sidebar-overlay" aria-label="Tutup navigasi" data-sidebar-close></button>
<aside class="sidebar" id="sidebar" aria-label="Navigasi utama">
 <a href="<?=e(url(user_home()))?>" class="brand"><span class="brand-mark"><?=icon('leaf')?></span><span>MUTIARA<span class="brand-sub">SISTEM PENGGAJIAN</span></span></a>
 <div class="nav-caption">RUANG KERJA</div>
 <nav class="main-nav" aria-label="Ruang kerja">
 <?php if($isEmployee):?>
 <a class="nav-item-link <?=str_starts_with($currentPath,'/karyawan/slip')?'active':''?>" href="<?=e(url('karyawan/slip'))?>" <?=str_starts_with($currentPath,'/karyawan/slip')?'aria-current="page"':''?>><span class="nav-icon"><?=icon('receipt')?></span><span>Slip Gaji Saya</span></a>
 <?php else:?>
 <a class="nav-item-link <?=$currentPath==='/'?'active':''?>" href="<?=e(url(''))?>" <?=$currentPath==='/'?'aria-current="page"':''?>><span class="nav-icon"><?=icon('grid')?></span><span>Dashboard</span><?php if($currentPath==='/'):?><span class="active-dot"></span><?php endif;?></a>
 <?php foreach($navGroups as $group=>[$label,$glyph,$links]):$activeGroup=$activeNavGroup===$group;?>
 <details class="nav-group" data-nav-group="<?=e($group)?>" <?=$activeGroup?'open':''?>><summary class="nav-item-link <?=$activeGroup?'group-active':''?>"><span class="nav-icon"><?=icon($glyph)?></span><span><?=e($label)?></span><?=icon('chevron-down','nav-chevron')?></summary><div class="nav-submenu">
 <?php foreach($links as $link):?><a href="<?=e(url($link['path'],$link['query']))?>" class="<?=$link['active']?'active':''?>" <?=$link['active']?'aria-current="page"':''?>><?=e($link['label'])?></a><?php endforeach;?>
 </div></details><?php endforeach;?>
 <?php endif;?></nav>
 <div class="nav-caption nav-caption-secondary">PENGATURAN</div>
 <nav class="main-nav" aria-label="Pengaturan">
 <?php if($isEmployee):?>
 <a class="nav-item-link <?=$currentPath==='/karyawan/ubah-password'?'active':''?>" href="<?=e(url('karyawan/ubah-password'))?>" <?=$currentPath==='/karyawan/ubah-password'?'aria-current="page"':''?>><span class="nav-icon"><?=icon('lock')?></span><span>Ubah Password</span></a>
 <?php else:?>
 <a class="nav-item-link <?=$currentPath==='/akun-karyawan'?'active':''?>" href="<?=e(url('akun-karyawan'))?>" <?=$currentPath==='/akun-karyawan'?'aria-current="page"':''?>><span class="nav-icon"><?=icon('users')?></span><span>Akun Karyawan</span></a>
 <a class="nav-item-link <?=$currentPath==='/migrasi'||$currentPath==='/arsip'?'active':''?>" href="<?=e(url('migrasi'))?>"><span class="nav-icon"><?=icon('database')?></span><span>Data & Migrasi</span></a>
 <a class="nav-item-link <?=$currentPath==='/profil'?'active':''?>" href="<?=e(url('profil'))?>"><span class="nav-icon"><?=icon('user')?></span><span>Profil Saya</span></a><?php endif;?></nav>
 <div class="sidebar-bottom"><?php if(!$isEmployee):?><div class="sidebar-note"><span class="note-icon"><?=icon('shield')?></span><strong><?=$isEmployee?'Penghasilan Anda,<br>mudah dilihat.':'Ruang kerja Anda,<br>lebih tertata.'?></strong><p><?=$isEmployee?'Lihat rincian slip dan<br>kelola password akun Anda.':'Kelola penghasilan dengan<br>mudah dan transparan.'?></p><a href="<?=e(url($isEmployee?'karyawan/ubah-password':'transaksi/form'))?>" class="btn btn-primary sidebar-start"><span><?=$isEmployee?'Ubah Password':'Mulai Transaksi'?></span><?=icon('arrow-right')?></a></div><?php endif;?><form action="<?=e(url('logout'))?>" method="post"><?=csrf_field()?><button class="logout-button" type="submit"><?=icon('logout')?> Keluar akun</button></form><div class="sidebar-version">Mutiara Hikmah <span>v1.0</span></div></div>
</aside>
<div class="sidebar-resizer" id="sidebar-resizer" role="separator" tabindex="0" aria-label="Atur lebar sidebar" aria-controls="sidebar" aria-orientation="vertical" aria-valuemin="260" aria-valuemax="400" aria-valuenow="260" aria-valuetext="260 piksel" title="Geser untuk mengatur lebar sidebar. Gunakan panah kiri/kanan, atau klik dua kali untuk ukuran awal." hidden><span aria-hidden="true"></span></div>
<div class="app-main"><header class="topbar"><div class="breadcrumb-area"><button class="mobile-menu icon-button" data-sidebar-toggle aria-label="Buka navigasi" aria-controls="sidebar" aria-expanded="false"><?=icon('menu')?></button><span>Ruang kerja</span><?=icon('chevron')?><strong><?=e($navigationSection)?></strong></div><div class="topbar-right"><span class="local-badge"><span></span> Aplikasi lokal</span><span class="topbar-divider"></span><div class="user-chip"><span class="avatar"><?=e(mb_strtoupper(mb_substr($_SESSION['user']['name']??'P',0,1)))?></span><span><strong><?=e($_SESSION['user']['name']??'Petugas')?></strong><small><?=$isEmployee?'Karyawan':'Superadmin'?></small></span></div></div></header>
<main id="main-content" class="main-content">
<?php foreach($flashMessages as $message):?><div class="alert alert-<?=e($message['type'])?> alert-dismissible fade show" role="status"><?=e($message['message'])?><button class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button></div><?php endforeach;?>
<?=$content?>
<?php if(!$isEmployeeSlip):?><footer class="page-footer"><span>© <?=date('Y')?> Yayasan Mutiara Hikmah</span><span>Dikelola dengan cermat, untuk setiap penghasilan.</span></footer><?php endif;?>
</main></div>
<script src="<?=e(url('public/assets/vendor/bootstrap.bundle.min.js'))?>" defer></script><?php if(!$isEmployee):?><script src="<?=e(url('public/assets/vendor/chart.umd.min.js'))?>" defer></script><?php endif;?><?php require __DIR__.'/feedback.php'; ?><script src="<?=e(url('public/assets/feedback.js',['v'=>(string)filemtime(BASE_PATH.'/public/assets/feedback.js')]))?>" defer></script><script src="<?=e(url('public/assets/app.js',['v'=>(string)filemtime(BASE_PATH.'/public/assets/app.js')]))?>" defer></script>
<script src="<?=e(url('public/assets/sidebar.js',['v'=>(string)filemtime(BASE_PATH.'/public/assets/sidebar.js')]))?>" defer></script>
<?php if(!$isEmployee && $currentPath==='/akun-karyawan'):?><script src="<?=e(url('public/assets/employee-accounts.js',['v'=>(string)filemtime(BASE_PATH.'/public/assets/employee-accounts.js')]))?>" defer></script><?php endif;?>
<?php if($isEmployee && $currentPath==='/karyawan/slip'):?><script src="<?=e(url('public/assets/employee-portal.js',['v'=>(string)filemtime(BASE_PATH.'/public/assets/employee-portal.js')]))?>" defer></script><?php endif;?>
</body></html>
