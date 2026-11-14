<?php
$mode = $mode ?? ($setup ? 'setup' : 'login');
$descriptions = [
    'setup' => 'Buat akun pengelola pertama untuk memulai. Tidak ada kata sandi bawaan.',
    'login' => 'Masuk ke akun Anda untuk melanjutkan pekerjaan.',
    'forgot' => 'Masukkan email yang terdaftar pada akun Anda. Kami akan mengirim tautan untuk mengatur ulang kata sandi.',
    'reset' => 'Buat kata sandi baru sepanjang 12–72 karakter untuk akun Anda.',
];
$action = match ($mode) { 'forgot' => 'forgot-password', 'reset' => 'reset-password', default => $mode };
$submit = match ($mode) { 'setup' => 'Buat akun pengelola', 'forgot' => 'Kirim tautan pengaturan ulang', 'reset' => 'Simpan kata sandi baru', default => 'Masuk ke ruang kerja' };
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="app-base-path" content="<?=e(app_base_path())?>">
    <title><?=e($title)?> · Mutiara Hikmah</title>
    <link rel="icon" href="<?=e(url('public/assets/brand.svg'))?>">
    <link rel="stylesheet" href="<?=e(url('public/assets/vendor/bootstrap.min.css'))?>">
    <link rel="stylesheet" href="<?=e(url('public/assets/app.css',['v'=>(string)filemtime(BASE_PATH.'/public/assets/app.css')]))?>">
</head>
<body class="auth-body">
<main class="auth-shell">
    <section class="auth-story">
        <a href="<?=e(url(''))?>" class="brand light"><span class="brand-mark"><?=icon('leaf')?></span><span>MUTIARA<span class="brand-sub">SISTEM PENGGAJIAN</span></span></a>
        <div class="auth-story-content"><span class="auth-kicker">LEBIH TERTATA. LEBIH BERMAKNA.</span><h1>Setiap kontribusi,<br>layak dikelola<br><em>dengan baik.</em></h1><p>Satu ruang kerja untuk mengelola data karyawan, penghasilan, dan laporan Yayasan Mutiara Hikmah.</p><div class="auth-feature"><?=icon('shield')?><span>Ruang kerja lokal untuk data penggajian Anda.</span></div></div>
        <div class="auth-story-footer">Yayasan Mutiara Hikmah <span>Penggajian, dalam genggaman.</span></div>
    </section>
    <section class="auth-form-panel"><div class="auth-form-inner">
        <span class="auth-form-icon"><?=icon($setup?'leaf':'lock')?></span>
        <div class="eyebrow"><?=$setup?'LANGKAH PERTAMA':'RUANG KERJA PENGGAJIAN'?></div>
        <h2><?=e($title)?><span class="heading-dot">.</span></h2>
        <p class="auth-description"><?=e($descriptions[$mode])?></p>
        <?php $flashMessages=$_SESSION['flash']??[];unset($_SESSION['flash']);foreach($flashMessages as $f):?>
            <div class="alert alert-<?=e($f['type'])?>" role="status"><?=e($f['message'])?></div>
        <?php endforeach;?>
        <?php if($error):?><div class="alert alert-danger" role="alert"><?=e($error)?></div><?php endif;?>
        <?php if($mode!=='reset' || $valid):?>
        <form method="post" action="<?=e(url($action))?>" class="auth-form">
            <?=csrf_field()?>
            <?php if($setup):?>
                <div class="mb-4"><label class="form-label" for="name">Nama lengkap</label><input class="form-control" id="name" name="name" maxlength="150" required autocomplete="name" value="<?=e($_POST['name']??'')?>"></div>
            <?php endif;?>
            <?php if($setup || $mode==='forgot'):?>
                <div class="mb-4"><label class="form-label" for="email">Email pemulihan</label><input class="form-control" type="email" id="email" name="email" maxlength="254" required autocomplete="email" value="<?=e($_POST['email']??'')?>" <?=$mode==='forgot'?'autofocus':''?>></div>
            <?php endif;?>
            <?php if($setup || $mode==='login'):?>
                <div class="mb-4"><label class="form-label" for="username">ID pengguna</label><input class="form-control" id="username" name="username" required autocomplete="username" maxlength="50" autofocus value="<?=e($_POST['username']??'')?>" placeholder="Masukkan ID pengguna"><?php if($setup):?><div class="form-text">3–50 karakter: huruf, angka, titik, garis bawah, atau tanda hubung.</div><?php endif;?></div>
            <?php endif;?>
            <?php if($mode!=='forgot'):?>
                <?php if($mode==='reset'):?><input type="hidden" name="token" value="<?=e($token)?>"><?php endif;?>
                <div class="mb-4"><label class="form-label" for="password"><?=$mode==='reset'?'Kata sandi baru':'Kata sandi'?></label><div class="password-input"><input class="form-control" type="password" id="password" name="password" required <?=$mode!=='login'?'minlength="12" maxlength="72"':''?> autocomplete="<?=$mode==='login'?'current-password':'new-password'?>" placeholder="<?=$mode==='login'?'Masukkan kata sandi':'Minimal 12 karakter'?>"><button type="button" data-password-toggle="password" aria-label="Tampilkan kata sandi">Lihat</button></div></div>
            <?php endif;?>
            <?php if($setup || $mode==='reset'):?>
                <div class="mb-4"><label class="form-label" for="confirmation">Konfirmasi kata sandi</label><input class="form-control" type="password" id="confirmation" name="password_confirmation" required minlength="12" maxlength="72" autocomplete="new-password"></div>
            <?php endif;?>
            <button class="btn btn-primary w-100 auth-submit" type="submit"><?=e($submit)?> <?=icon('arrow-right')?></button>
        </form>
        <?php endif;?>
        <p class="auth-help"><?=icon('info')?>
            <?php if($setup):?>Simpan ID pengguna, email, dan kata sandi Anda dengan baik.
            <?php elseif($mode==='login'):?><a href="<?=e(url('forgot-password'))?>">Lupa kata sandi?</a>
            <?php elseif($mode==='reset' && !$valid):?><a href="<?=e(url('forgot-password'))?>">Minta tautan baru</a>
            <?php else:?><a href="<?=e(url('login'))?>">Kembali ke halaman masuk</a><?php endif;?>
        </p>
        <?php if($mode==='reset' && !$valid):?><p class="auth-help"><a href="<?=e(url('login'))?>">Kembali ke halaman masuk</a></p><?php endif;?>
        <div class="auth-copyright">© <?=date('Y')?> Yayasan Mutiara Hikmah</div>
    </div></section>
</main>
<?php require __DIR__.'/feedback.php'; ?>
<script src="<?=e(url('public/assets/vendor/bootstrap.bundle.min.js'))?>" defer></script>
<script src="<?=e(url('public/assets/feedback.js',['v'=>(string)filemtime(BASE_PATH.'/public/assets/feedback.js')]))?>" defer></script>
<script src="<?=e(url('public/assets/app.js',['v'=>(string)filemtime(BASE_PATH.'/public/assets/app.js')]))?>" defer></script>
</body></html>
