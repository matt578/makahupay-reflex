<?php
$flashes = take_flashes();
$biz = setting('business_name', 'Makahupay Reflex & Massage');
$notifications = [];
$therapistId = is_therapist() ? (int)(current_user()['therapist_id'] ?? 0) : null;
$addNotification = static function (string $label, int $count, string $url, string $icon) use (&$notifications): void {
    if ($count > 0) $notifications[] = ['label'=>$label, 'count'=>$count, 'url'=>$url, 'icon'=>$icon];
};
$todayAppointmentsSql = "SELECT COUNT(*) FROM appointments WHERE appointment_date=CURDATE()" . ($therapistId ? ' AND therapist_id=?' : '');
$q = db()->prepare($todayAppointmentsSql); $q->execute($therapistId ? [$therapistId] : []);
$addNotification('Appointments today', (int)$q->fetchColumn(), app_page_url('appointments'), 'calendar3');
$upcomingSql = "SELECT COUNT(*) FROM appointments WHERE appointment_date=CURDATE() AND start_time>=CURTIME() AND status IN ('pending','confirmed')" . ($therapistId ? ' AND therapist_id=?' : '');
$q = db()->prepare($upcomingSql); $q->execute($therapistId ? [$therapistId] : []);
$addNotification('Upcoming appointments', (int)$q->fetchColumn(), app_page_url('appointments'), 'clock');
$cancelledSql = "SELECT COUNT(*) FROM appointments WHERE appointment_date=CURDATE() AND status='cancelled'" . ($therapistId ? ' AND therapist_id=?' : '');
$q = db()->prepare($cancelledSql); $q->execute($therapistId ? [$therapistId] : []);
$addNotification('Cancelled today', (int)$q->fetchColumn(), app_page_url('appointments', ['status'=>'cancelled']), 'calendar-x');
$pendingPaymentSql = "SELECT COUNT(*) FROM appointments WHERE appointment_date=CURDATE() AND status IN ('checked_in','in_session','completed') AND payment_status<>'paid'" . ($therapistId ? ' AND therapist_id=?' : '');
$q = db()->prepare($pendingPaymentSql); $q->execute($therapistId ? [$therapistId] : []);
if (is_staff()) $addNotification('Pending payments', (int)$q->fetchColumn(), app_page_url('appointments'), 'cash-coin');
if (is_staff()) {
    $q = db()->query("SELECT COUNT(*) FROM inventory WHERE status='active' AND current_stock<=minimum_stock");
    $addNotification('Low-stock supplies', (int)$q->fetchColumn(), app_page_url('inventory'), 'boxes');
    $q = db()->query('SELECT COUNT(*) FROM customers WHERE DATE(created_at)=CURDATE()');
    $addNotification('New customers today', (int)$q->fetchColumn(), app_page_url('customers'), 'person-plus');
}
$notificationCount = array_sum(array_column($notifications, 'count'));
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?=h(csrf_token())?>"><title><?=h($biz)?> · <?=h(ucwords(str_replace('_',' ',$page)))?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?=h(app_url('assets/css/app.css'))?>" rel="stylesheet">
</head><body>
<div class="app-shell">
<header class="topbar"><button class="btn btn-light d-lg-none" id="sidebarToggle" aria-label="Open menu"><i class="bi bi-list"></i></button><a class="brand-top" href="<?=h(app_url('index.php'))?>"><?php if(setting('business_logo')):?><span class="brand-mark logo-mark"><img src="<?=h(app_url(setting('business_logo')))?>" alt="Business logo"></span><?php else:?><span class="brand-mark"><i class="bi bi-flower1"></i></span><?php endif;?><span><?=h($biz)?></span></a><div class="topbar-right"><span class="today-label d-none d-md-inline"><?=date('D, M j, Y')?></span><div class="dropdown"><button class="btn btn-light position-relative" data-bs-toggle="dropdown" aria-label="Notifications"><i class="bi bi-bell"></i><?php if($notificationCount>0):?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger"><?=h($notificationCount)?></span><?php endif;?></button><div class="dropdown-menu dropdown-menu-end p-2 notification-menu"><div class="fw-bold px-2 py-1">Notifications</div><?php if(!$notifications):?><div class="small text-muted px-2 py-2">You’re all caught up.</div><?php else: foreach($notifications as $notification):?><a class="dropdown-item rounded d-flex align-items-center gap-2 py-2" href="<?=h($notification['url'])?>"><i class="bi bi-<?=h($notification['icon'])?> text-success"></i><span class="flex-grow-1"><?=h($notification['label'])?></span><span class="badge text-bg-light"><?=h($notification['count'])?></span></a><?php endforeach;endif;?></div></div><div class="dropdown"><button class="profile-trigger" data-bs-toggle="dropdown"><span class="avatar-sm"><?=h(mb_strtoupper(mb_substr(current_user()['name']??'U',0,1)))?></span><span class="d-none d-sm-inline"><?=h(current_user()['name']??'User')?></span><i class="bi bi-chevron-down small"></i></button><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="<?=h(app_page_url('profile'))?>"><i class="bi bi-person me-2"></i>My profile</a></li><li><hr class="dropdown-divider"></li><li><form method="post" class="px-2"><?=csrf_field()?><input type="hidden" name="action" value="logout"><button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Logout</button></form></li></ul></div></div></header>
<?php foreach($flashes as $flash): ?><div class="toast-area"><div class="alert alert-<?=h($flash['type'])?> alert-dismissible fade show shadow-sm" role="alert"><?=h($flash['message'])?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div></div><?php endforeach; ?>
