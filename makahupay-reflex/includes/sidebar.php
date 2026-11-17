<?php
$nav=[
 ['label'=>'Overview','items'=>[['dashboard','Dashboard','speedometer2']]],
 ['label'=>'Front desk','items'=>[['appointments','Appointments','calendar3'],['walkins','Walk-in queue','person-walking'],['customers','Customers','people'],['pos','Point of sale','cash-coin'],['transactions','Transactions','receipt']]],
 ['label'=>'Management','items'=>[['services','Services','spa'],['therapists','Therapists & staff','person-badge'],['inventory','Inventory','boxes'],['expenses','Expenses','wallet2'],['reports','Reports','bar-chart-line']]],
 ['label'=>'Administration','items'=>[['users','User accounts','shield-lock'],['logs','Activity logs','journal-text'],['settings','Business settings','gear']]],
];
?>
<aside class="sidebar" id="sidebar"><div class="sidebar-inner"><?php foreach($nav as $group): ?><div class="nav-group"><div class="nav-caption"><?=h($group['label'])?></div><?php foreach($group['items'] as [$slug,$label,$icon]): if (!Permissions::can($slug)) continue; ?><a class="nav-link-custom <?=$page===$slug?'active':''?>" href="<?=h(app_page_url($slug))?>"><i class="bi bi-<?=h($icon)?>"></i><span><?=h($label)?></span></a><?php endforeach; ?></div><?php endforeach; ?><div class="sidebar-footer"><div class="wellness-note"><i class="bi bi-heart-pulse"></i><span>Care that restores balance.</span></div><small>© <?=date('Y')?> Makahupay</small></div></div></aside>
