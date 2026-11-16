<?php
use App\Domain\PrintLayout;
$amountClass=static function(mixed $value):string{$length=strlen(PrintLayout::amount($value));return $length>20?'employee-amount-extra-long':($length>16?'employee-amount-long':'');};
$quantityFields=['ulembur'=>'lembur','uinfal'=>'infal','uovertime'=>'overtime'];
?>
<article class="employee-print-slip" aria-label="Slip gaji <?=e($row['nama'])?>" data-slip-nip="<?=e($row['nip'])?>">
 <div class="employee-legacy-body">
 <h2 class="employee-legacy-title">SLIP GAJI KARYAWAN YAYASAN MUTIARA HIKMAH</h2>
 <table class="employee-legacy-identity"><colgroup><col style="width:30mm"><col style="width:3mm"><col></colgroup><tbody>
  <tr><td>NIP</td><td>:</td><td><?=e($row['nip'])?></td></tr>
  <tr><td>Nama</td><td>:</td><td><?=e($row['nama'])?></td></tr>
  <tr><td>Bagian</td><td>:</td><td><?=e($row['nama_bagian'])?></td></tr>
 </tbody></table>
 <table class="employee-legacy-attendance"><tbody><tr>
  <td>Jumlah Hari Efektif : <span data-slip-quantity="hefektif"><?=e($row['hefektif'])?></span></td>
  <td>Masuk Kerja : <span data-slip-quantity="hmasuk"><?=e($row['hmasuk'])?></span></td>
  <td>Bulan : <?=e(month_name((int)$row['bulan']).' '.$row['tahun'])?></td>
 </tr></tbody></table>
 <p class="employee-legacy-absence">Izin / alpha : <span data-slip-quantity="hijin"><?=e($row['hijin'])?></span><span class="employee-absence-gap">Sakit : <span data-slip-quantity="halpha"><?=e($row['halpha'])?></span></span></p>
 <div class="employee-legacy-columns">
 <?php foreach([['Penghasilan',PrintLayout::INCOME,'tpenghasilan','Jumlah Kotor'],['Potongan',PrintLayout::DEDUCTIONS,'tpotongan','Total Potongan']] as $groupIndex=>[$heading,$fields,$totalField,$totalLabel]):?>
  <?php if($groupIndex):?><div class="employee-legacy-gutter"></div><?php endif;?>
  <div class="employee-legacy-column"><table class="employee-legacy-components">
   <colgroup><col class="employee-label-col"><col class="employee-colon-col"><col class="employee-currency-col"><col class="employee-amount-col"></colgroup>
   <thead><tr><th colspan="4" scope="colgroup"><?=e($heading)?></th></tr></thead>
   <tbody><?php foreach($fields as $field=>$label):?><tr>
    <td><?=e($label)?><?php if(isset($quantityFields[$field])):$quantity=$quantityFields[$field];?><span class="employee-legacy-quantity" data-slip-quantity="<?=e($quantity)?>"><?=e($row[$quantity])?></span><?php endif;?></td><td>:</td><td>Rp.</td><td class="employee-legacy-amount <?=$amountClass($row[$field])?>" data-slip-field="<?=e($field)?>"><?=e(PrintLayout::amount($row[$field]))?></td>
   </tr><?php endforeach;?><?php if($groupIndex):?><tr class="employee-legacy-spacer" aria-hidden="true"><td colspan="4">&nbsp;</td></tr><?php endif;?></tbody>
   <tfoot>
    <tr><td><?=e($totalLabel)?></td><td>:</td><td>Rp.</td><td class="employee-legacy-amount <?=$amountClass($row[$totalField])?>" data-slip-field="<?=e($totalField)?>"><?=e(PrintLayout::amount($row[$totalField]))?></td></tr>
    <?php if($groupIndex):?><tr class="employee-legacy-net"><td>Gaji Bersih</td><td>:</td><td>Rp.</td><td class="employee-legacy-amount <?=$amountClass($row['tbersih'])?>" data-slip-field="tbersih"><?=e(PrintLayout::amount($row['tbersih']))?></td></tr><?php else:?><tr class="employee-legacy-spacer" aria-hidden="true"><td colspan="4">&nbsp;</td></tr><?php endif;?>
   </tfoot>
  </table></div>
 <?php endforeach;?>
 </div>
 <table class="employee-legacy-signatures"><tbody>
  <tr><td></td><td class="employee-legacy-issued"><?=e($print['location'])?>, <time data-print-timestamp><?=e($print['timestamp'])?></time></td></tr>
  <tr><td>Accounting</td><td>Penerima</td></tr>
  <tr><td class="employee-legacy-signature-space"></td><td></td></tr>
  <tr><td><?=e($print['officer'])?></td><td><?=e($row['nama'])?></td></tr>
 </tbody></table>
 </div>
</article>
