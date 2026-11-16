<div class="page-heading"><div><div class="eyebrow">AKUN KARYAWAN</div><h1>Ubah Password<span class="heading-dot">.</span></h1><p>Perbarui password untuk akun Anda.</p></div></div>
<?php if($error):?><div class="alert alert-danger" role="alert"><?=e($error)?></div><?php endif;?>
<div class="employee-password-grid">
<section class="surface"><div class="panel-header"><div><h2>Password akun</h2><p>Gunakan 8–72 karakter yang berbeda dari password awal.</p></div><span class="panel-icon"><?=icon('lock')?></span></div>
<form class="panel-body" action="<?=e(url('karyawan/ubah-password'))?>" method="post"><?=csrf_field()?>
<?php foreach([['current_password','Kata sandi saat ini','current-password'],['new_password','Kata sandi baru','new-password'],['password_confirmation','Konfirmasi kata sandi baru','new-password']] as [$field,$label,$autocomplete]):?>
<div class="mb-4"><label class="form-label" for="employee-<?=e($field)?>"><?=e($label)?></label><div class="password-input"><input class="form-control" id="employee-<?=e($field)?>" name="<?=e($field)?>" type="password" required <?=$field==='current_password'?'':'minlength="8" maxlength="72"'?> autocomplete="<?=e($autocomplete)?>"><button type="button" data-password-toggle="employee-<?=e($field)?>" aria-label="Tampilkan kata sandi">Lihat</button></div></div>
<?php endforeach;?>
<button class="btn btn-primary employee-save" type="submit"><?=icon('save')?> Simpan Password Baru</button>
</form></section>
<aside class="surface employee-account-info"><div class="panel-header"><h2>Akun Anda</h2><?=icon('user')?></div><div class="panel-body"><dl class="employee-info-list"><div><dt>Nama</dt><dd><?=e($employee['nama'])?></dd></div><div><dt>Nomor induk</dt><dd><?=e($employee['nip'])?></dd></div><div><dt>Bagian</dt><dd><?=e($employee['nama_bagian']??$employee['kd_bagian'])?></dd></div></dl><div class="employee-help"><?=icon('info')?><p>Lupa password? Hubungi superadmin untuk memperoleh password akun Anda.</p></div></div></aside>
</div>
