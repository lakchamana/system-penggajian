<?php if($error):?><div class="alert alert-danger no-print" role="alert"><?=e($error)?></div><?php endif;?>
<section class="surface no-print" aria-label="Periode slip gaji">
<form class="filter-bar employee-filters" method="get" action="<?=e(url('karyawan/slip'))?>">
 <div class="field"><label class="form-label" for="slip-month">Bulan</label><select class="form-select" name="bulan" id="slip-month" data-auto-submit required><?php for($m=1;$m<=12;$m++):?><option value="<?=$m?>"<?=selected($month,$m)?>><?=month_name($m)?></option><?php endfor;?></select></div>
 <div class="field"><label class="form-label" for="slip-year">Tahun</label><select class="form-select" name="tahun" id="slip-year" data-auto-submit required><?php foreach($years as $option):?><option value="<?=e($option)?>"<?=selected($year,$option)?>><?=e($option)?></option><?php endforeach;?></select></div>
 <div class="employee-slip-actions">
  <button class="btn btn-light" type="button" data-print <?=!$rows?'disabled':''?>><?=icon('print')?> Cetak</button>
  <?php if($rows):?><a class="btn btn-primary" href="<?=e(url('karyawan/slip/pdf',['bulan'=>$month,'tahun'=>$year]))?>" download><?=icon('download')?> Unduh PDF</a><?php else:?><button class="btn btn-primary" type="button" disabled><?=icon('download')?> Unduh PDF</button><?php endif;?>
 </div>
</form>
</section>
<?php if($rows):?>
<style media="print">@page{size:A4 portrait;margin:12mm}</style>
<div class="employee-print-document" data-employee-print aria-label="Slip gaji <?=e(month_name($month))?> <?=$year?>"><?php foreach($rows as $row):require __DIR__.'/employee-slip-print.php';endforeach;?></div>
<?php else:?>
<section class="employee-slip-placeholder no-print" aria-label="Slip belum tersedia">
 <div class="employee-skeleton-heading" aria-hidden="true"><span></span><span></span></div>
 <p role="status"><?= $error?'Pilih bulan dan tahun yang valid.':'Slip gaji '.e(month_name($month)).' '.e($year).' belum tersedia.' ?></p>
 <div class="employee-skeleton-lines" aria-hidden="true"><?php for($i=0;$i<8;$i++):?><div><span></span><span></span></div><?php endfor;?></div>
</section>
<?php endif;?>
<script type="application/json" id="employee-portal-state"><?=json_encode(['remind'=>$remind,'passwordUrl'=>url('karyawan/ubah-password')],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?></script>
