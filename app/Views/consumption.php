<?php $feedbackErrorInline = !empty($error); ?>
<div class="page-heading">
    <div><h1>Data Konsumsi<span class="heading-dot">.</span></h1></div>
</div>

<section class="surface" aria-labelledby="consumption-heading">
    <div class="panel-header"><h2 id="consumption-heading">Tarif konsumsi</h2></div>
    <form class="panel-body" method="post" action="<?=e(url('master/tunj_konsumsi'))?>">
        <?=csrf_field()?>
        <?php if ($error): ?><p role="alert" class="alert alert-danger"><?=e($error)?></p><?php endif; ?>
        <div class="mb-4" style="max-width: 24rem">
            <label class="form-label" for="master-rate">Tarif per hari masuk</label>
            <div class="input-group">
                <span class="input-group-text">Rp</span>
                <input id="master-rate" name="rate" class="form-control" type="text" inputmode="numeric"
                       pattern="[0-9]{1,18}" maxlength="18" value="<?=e($form['rate'])?>" required>
            </div>
        </div>
        <fieldset class="mb-4">
            <legend class="form-label">Bagian yang mendapat tunjangan</legend>
            <p class="muted-small">Centang bagian yang mendapat tarif ini. Bagian lain mendapat Rp0.</p>
            <?php if (!$departments): ?>
                <p>Belum ada bagian. <a href="<?=e(url('master/bagian', ['new' => 1]))?>">Tambah bagian</a></p>
            <?php endif; ?>
            <?php foreach ($departments as $department): ?>
                <label class="d-flex align-items-center gap-2 py-2" style="min-height: 44px">
                    <input class="form-check-input mt-0" type="checkbox" name="departments[]" value="<?=e($department['kd_bagian'])?>"
                           <?=in_array($department['kd_bagian'], $form['departments'], true) ? 'checked' : ''?>>
                    <span><?=e($department['kd_bagian'] . ' · ' . $department['nama_bagian'])?></span>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <button type="submit" class="btn btn-primary">Simpan tarif konsumsi</button>
    </form>
</section>

<?php if ($legacyRates): ?>
    <section class="surface mt-3" aria-labelledby="legacy-consumption-heading">
        <div class="panel-header"><h2 id="legacy-consumption-heading">Tarif yang sedang berlaku</h2></div>
        <p class="panel-body pt-0 mb-0">Tarif per bagian ini dipakai sampai kamu menyimpan pengaturan baru.</p>
        <div class="table-responsive">
            <table class="table app-table">
                <thead><tr><th scope="col">Bagian</th><th scope="col" class="text-end">Tarif per hari masuk</th></tr></thead>
                <tbody>
                <?php foreach ($legacyRates as $row): ?>
                    <tr>
                        <td><?=e($row['nama_bagian'] ?? $row['kd_bagian'])?></td>
                        <td class="text-end"><?=$row['jumlah'] === null ? 'Belum diatur' : e(money($row['jumlah']))?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
