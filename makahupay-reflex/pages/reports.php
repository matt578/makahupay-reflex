<?php
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
if (!valid_date((string)$from)) $from = date('Y-m-01');
if (!valid_date((string)$to)) $to = date('Y-m-d');
if (!is_admin()) {
    // Staff can view only the basic daily report.
    $from = $to = date('Y-m-d');
}
if ($from > $to) [$from, $to] = [$to, $from];

$q = db()->prepare("SELECT COUNT(*) cnt,
    COALESCE(SUM(total),0) revenue,
    COALESCE(SUM(CASE WHEN payment_method='cash' THEN total ELSE 0 END),0) cash,
    COALESCE(SUM(CASE WHEN payment_method='gcash' THEN total ELSE 0 END),0) gcash,
    COALESCE(SUM(CASE WHEN payment_method='bank_transfer' THEN total ELSE 0 END),0) bank,
    COALESCE(SUM(CASE WHEN payment_method='other' THEN total ELSE 0 END),0) other
    FROM transactions WHERE status='paid' AND DATE(created_at) BETWEEN ? AND ?");
$q->execute([$from, $to]);
$sales = $q->fetch();

$q = db()->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status='completed'");
$q->execute([$from, $to]);
$completed = (int)$q->fetchColumn();
$q = db()->prepare("SELECT COUNT(DISTINCT customer_id) FROM transactions WHERE status='paid' AND DATE(created_at) BETWEEN ? AND ? AND customer_id IS NOT NULL");
$q->execute([$from, $to]);
$uniqueCustomers = (int)$q->fetchColumn();

$expenses = 0.0;
if (is_admin()) {
    $q = db()->prepare('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date BETWEEN ? AND ?');
    $q->execute([$from, $to]);
    $expenses = (float)$q->fetchColumn();

    $q = db()->prepare("SELECT ti.description, SUM(ti.quantity) sessions, SUM(ti.line_total) revenue
        FROM transaction_items ti JOIN transactions t ON t.id=ti.transaction_id
        WHERE t.status='paid' AND DATE(t.created_at) BETWEEN ? AND ?
        GROUP BY ti.description ORDER BY sessions DESC LIMIT 10");
    $q->execute([$from, $to]);
    $serviceReport = $q->fetchAll();

    $q = db()->prepare("SELECT th.full_name,COALESCE(ss.sessions,0) sessions,COALESCE(tr.revenue,0) revenue
        FROM therapists th
        LEFT JOIN (SELECT therapist_id,COUNT(*) sessions FROM sessions
            WHERE DATE(session_date) BETWEEN ? AND ? GROUP BY therapist_id) ss ON ss.therapist_id=th.id
        LEFT JOIN (SELECT ti.therapist_id,SUM(ti.line_total) revenue FROM transaction_items ti
            JOIN transactions t ON t.id=ti.transaction_id
            WHERE t.status='paid' AND DATE(t.created_at) BETWEEN ? AND ? AND ti.therapist_id IS NOT NULL
            GROUP BY ti.therapist_id) tr ON tr.therapist_id=th.id
        ORDER BY sessions DESC,tr.revenue DESC");
    $q->execute([$from, $to, $from, $to]);
    $therapistReport = $q->fetchAll();

    $q = db()->prepare('SELECT COUNT(*) FROM customers WHERE DATE(created_at) BETWEEN ? AND ?');
    $q->execute([$from, $to]);
    $newCustomers = (int)$q->fetchColumn();

    $q = db()->prepare("SELECT c.full_name,COALESCE(v.visits,0) visits,COALESCE(sp.spent,0) spent
        FROM customers c
        LEFT JOIN (SELECT customer_id,COUNT(*) visits FROM appointments
            WHERE status='completed' AND appointment_date BETWEEN ? AND ? GROUP BY customer_id) v ON v.customer_id=c.id
        LEFT JOIN (SELECT customer_id,SUM(total) spent FROM transactions
            WHERE status='paid' AND DATE(created_at) BETWEEN ? AND ? AND customer_id IS NOT NULL GROUP BY customer_id) sp ON sp.customer_id=c.id
        WHERE COALESCE(v.visits,0)>0 OR COALESCE(sp.spent,0)>0
        ORDER BY visits DESC,spent DESC LIMIT 10");
    $q->execute([$from, $to, $from, $to]);
    $customerReport = $q->fetchAll();
}
?>
<div class="page-heading">
    <div>
        <div class="eyebrow">Business analytics</div>
        <h1>Reports</h1>
        <p><?=is_admin() ? 'Sales, expenses, service performance, therapist activity, and customer trends.' : 'Daily sales totals and payment-method summary.'?></p>
    </div>
    <div class="d-flex gap-2">
        <?php if (is_admin()): ?>
            <a class="btn btn-outline-secondary" href="<?=h(app_page_url('reports', ['from'=>$from, 'to'=>$to, 'export'=>'csv']))?>"><i class="bi bi-download me-1"></i>Export CSV</a>
        <?php endif; ?>
        <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print report</button>
    </div>
</div>

<?php if (is_admin()): ?>
<div class="card mb-3 no-print">
    <div class="card-body">
        <form class="row g-2 align-items-end">
            <input type="hidden" name="page" value="reports">
            <div class="col-md-4"><label class="form-label">From</label><input class="form-control" type="date" name="from" value="<?=h($from)?>"></div>
            <div class="col-md-4"><label class="form-label">To</label><input class="form-control" type="date" name="to" value="<?=h($to)?>"></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Run report</button></div>
            <div class="col-md-2"><a class="btn btn-light w-100" href="<?=h(app_page_url('reports'))?>">This month</a></div>
        </form>
        <div class="small-muted mt-2">Presets:
            <a href="<?=h(app_page_url('reports', ['from'=>date('Y-m-d'), 'to'=>date('Y-m-d')]))?>">Today</a> ·
            <a href="<?=h(app_page_url('reports', ['from'=>date('Y-m-d', strtotime('-1 day')), 'to'=>date('Y-m-d', strtotime('-1 day'))]))?>">Yesterday</a> ·
            <a href="<?=h(app_page_url('reports', ['from'=>date('Y-m-d', strtotime('monday this week')), 'to'=>date('Y-m-d')]))?>">This week</a>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card stat-card"><div class="stat-label">Total revenue</div><div class="stat-value text-success"><?=money($sales['revenue'])?></div><div class="stat-foot"><?=h($sales['cnt'])?> paid transactions</div></div></div>
    <?php if (is_admin()): ?>
        <div class="col-md-3"><div class="card stat-card"><div class="stat-label">Total expenses</div><div class="stat-value text-danger"><?=money($expenses)?></div><div class="stat-foot">Recorded in selected dates</div></div></div>
        <div class="col-md-3"><div class="card stat-card"><div class="stat-label">Net income</div><div class="stat-value"><?=money((float)$sales['revenue']-$expenses)?></div><div class="stat-foot">Revenue less recorded expenses</div></div></div>
    <?php endif; ?>
    <div class="col-md-3"><div class="card stat-card"><div class="stat-label">Completed sessions</div><div class="stat-value"><?=$completed?></div><div class="stat-foot"><?=$uniqueCustomers?> paying customers</div></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">Daily sales breakdown</div>
            <div class="card-body">
                <div class="d-flex justify-content-between py-2 border-bottom"><span>Cash</span><strong><?=money($sales['cash'])?></strong></div>
                <div class="d-flex justify-content-between py-2 border-bottom"><span>GCash</span><strong><?=money($sales['gcash'])?></strong></div>
                <div class="d-flex justify-content-between py-2 border-bottom"><span>Bank transfer</span><strong><?=money($sales['bank'])?></strong></div>
                <div class="d-flex justify-content-between py-2"><span>Other</span><strong><?=money($sales['other'])?></strong></div>
            </div>
        </div>
    </div>
    <?php if (is_admin()): ?>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">Customer growth</div>
                <div class="card-body">
                    <div class="display-5 fw-bold"><?=$newCustomers?></div>
                    <div class="text-muted">New customer registrations in selected period</div>
                    <div class="mt-3 d-flex justify-content-between"><span>Unique customers with paid transactions</span><strong><?=$uniqueCustomers?></strong></div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if (is_admin()): ?>
<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">Service performance</div>
            <div class="table-responsive"><table class="table"><thead><tr><th>Service</th><th>Qty / sessions</th><th>Revenue</th></tr></thead><tbody>
                <?php if (!$serviceReport) echo table_empty(3); foreach ($serviceReport as $r): ?>
                    <tr><td><?=h($r['description'])?></td><td><?=h($r['sessions'])?></td><td class="fw-bold"><?=money($r['revenue'])?></td></tr>
                <?php endforeach; ?>
            </tbody></table></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">Therapist performance</div>
            <div class="table-responsive"><table class="table"><thead><tr><th>Therapist</th><th>Sessions</th><th>Revenue</th></tr></thead><tbody>
                <?php if (!$therapistReport) echo table_empty(3); foreach ($therapistReport as $r): ?>
                    <tr><td><?=h($r['full_name'])?></td><td><?=h($r['sessions'])?></td><td><?=money($r['revenue'])?></td></tr>
                <?php endforeach; ?>
            </tbody></table></div>
        </div>
    </div>
</div>
<div class="card">
    <div class="card-header">Returning / frequent customers</div>
    <div class="table-responsive"><table class="table"><thead><tr><th>Customer</th><th>Completed visits</th><th>Amount spent</th></tr></thead><tbody>
        <?php if (!$customerReport) echo table_empty(3); foreach ($customerReport as $r): ?>
            <tr><td><?=h($r['full_name'])?></td><td><?=h($r['visits'])?></td><td class="fw-bold"><?=money($r['spent'])?></td></tr>
        <?php endforeach; ?>
    </tbody></table></div>
</div>
<?php endif; ?>
