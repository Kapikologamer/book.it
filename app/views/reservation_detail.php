<div class="reservation-detail"><span class="status status-<?= e($r['status']) ?>"><?= e(status_label($r['status'])) ?></span><dl>
<div><dt>Firma</dt><dd><?= e($r['company_name']) ?></dd></div><div><dt>Pracownik</dt><dd><?= e($r['employee_name']) ?></dd></div>
<div><dt>Termin</dt><dd><?= e($r['reservation_date']) ?>, <?= e(substr($r['start_time'],0,5)) ?>–<?= e(substr($r['end_time'],0,5)) ?></dd></div>
<div><dt>Cena przy rezerwacji</dt><dd><?= e(money_pl($r['price_snapshot'])) ?></dd></div>
<?php if(str_starts_with($route,'employee/')): ?><div><dt>Klient</dt><dd><?= e($r['client_name']) ?> · <?= e($r['client_phone']) ?></dd></div><?php endif ?>
<?php if($r['comment']!==''): ?><div><dt>Komentarz</dt><dd><?= nl2br(e($r['comment'])) ?></dd></div><?php endif ?></dl>
<h2>Historia</h2><ol class="history-list"><?php foreach($r['history'] as $h): ?><li><strong><?= e($h['actor_name']) ?></strong> · <?= e($h['created_at']) ?><span><?php if($h['action']==='created'): ?>Utworzono rezerwację<?php elseif($h['action']==='status'): ?><?= e(status_label($h['old_status'])) ?> → <?= e(status_label($h['new_status'])) ?><?php else: ?>Przełożono z <?= e($h['old_date'].' '.substr($h['old_start_time'],0,5)) ?> na <?= e($h['new_date'].' '.substr($h['new_start_time'],0,5)) ?><?php endif ?></span></li><?php endforeach ?></ol></div>
