<?php
declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/app/Core/view_helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') Actions::handle();
$page = preg_replace('/[^a-z_]/','', (string)($_GET['page'] ?? 'dashboard')) ?: 'dashboard';
$publicPages=['login','forgot','reset'];
if (!in_array($page,$publicPages,true)) { require_login(); Permissions::require($page); }

if ($page==='reports' && ($_GET['export'] ?? '') === 'csv') {
    require_roles(['Administrator']);
    $from=(string)($_GET['from']??date('Y-m-01'));$to=(string)($_GET['to']??date('Y-m-d'));
    if(!is_admin()){$from=$to=date('Y-m-d');}
    if(!valid_date($from)||!valid_date($to)||$from>$to){http_response_code(400);exit('Invalid date range.');}
    header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="makahupay-report-'.$from.'-'.$to.'.csv"');
    $out=fopen('php://output','w');fputcsv($out,['Makahupay Report',$from.' to '.$to]);fputcsv($out,[]);
    $q=db()->prepare("SELECT transaction_code,DATE(created_at) transaction_date,customer_id,total,discount,payment_method,status FROM transactions WHERE DATE(created_at) BETWEEN ? AND ? ORDER BY created_at");$q->execute([$from,$to]);fputcsv($out,['Transaction number','Date','Customer ID','Total','Discount','Payment method','Status']);while($r=$q->fetch(PDO::FETCH_NUM))fputcsv($out,$r);
    $q=db()->prepare("SELECT ti.description,SUM(ti.quantity),SUM(ti.line_total) FROM transaction_items ti JOIN transactions t ON t.id=ti.transaction_id WHERE t.status='paid' AND DATE(t.created_at) BETWEEN ? AND ? GROUP BY ti.description");$q->execute([$from,$to]);fputcsv($out,[]);fputcsv($out,['Service','Quantity sold','Revenue']);while($r=$q->fetch(PDO::FETCH_NUM))fputcsv($out,$r);
    $q=db()->prepare('SELECT expense_code,expense_date,category,description,amount,payment_method FROM expenses WHERE expense_date BETWEEN ? AND ? ORDER BY expense_date');$q->execute([$from,$to]);fputcsv($out,[]);fputcsv($out,['Expense ID','Date','Category','Description','Amount','Payment method']);while($r=$q->fetch(PDO::FETCH_NUM))fputcsv($out,$r);
    fclose($out);exit;
}

if ($page==='backup') {
    require_roles(['Administrator']);
    $tables=db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $preferred=['roles','therapists','users','customers','services','appointments','transactions','sessions','transaction_items','payments','inventory','inventory_movements','expenses','activity_logs','system_settings','password_resets','login_attempts'];
    $ordered=[];foreach($preferred as $name)if(in_array($name,$tables,true))$ordered[]=$name;foreach($tables as $name)if(!in_array($name,$ordered,true))$ordered[]=$name;
    $filename='makahupay-backup-'.date('Ymd-His').'.sql';
    header('Content-Type: application/sql; charset=utf-8'); header('Content-Disposition: attachment; filename="'.$filename.'"');
    echo "SET FOREIGN_KEY_CHECKS=0;\n";
    foreach($ordered as $table) echo "DROP TABLE IF EXISTS `{$table}`;\n";
    foreach($ordered as $table){$create=db()->query('SHOW CREATE TABLE `'.$table.'`')->fetch();echo "\n-- Table: {$table}\n".$create['Create Table'].";\n";}
    foreach($ordered as $table){
        $rows=db()->query('SELECT * FROM `'.$table.'`');
        while($row=$rows->fetch(PDO::FETCH_ASSOC)){
            $cols=array_map(fn($c)=>'`'.str_replace('`','``',$c).'`',array_keys($row));
            $vals=array_map(fn($v)=>$v===null?'NULL':db()->quote((string)$v),array_values($row));
            echo 'INSERT INTO `'.$table.'` ('.implode(',',$cols).') VALUES ('.implode(',',$vals).");\n";
        }
    }
    echo "SET FOREIGN_KEY_CHECKS=1;\n"; exit;
}

if (in_array($page,$publicPages,true)) {
    include dirname(__DIR__).'/pages/'.$page.'.php'; exit;
}

$pageFiles=['dashboard','appointments','walkins','customers','services','therapists','pos','transactions','inventory','expenses','reports','users','logs','settings','profile','receipt','session'];
if (!in_array($page,$pageFiles,true)) { http_response_code(404); $page='dashboard'; flash('danger','Page not found.'); }
include dirname(__DIR__).'/includes/header.php';
include dirname(__DIR__).'/includes/sidebar.php';
echo '<main class="main-content"><div class="content-wrap">';
include dirname(__DIR__).'/pages/'.$page.'.php';
echo '</div></main>';
include dirname(__DIR__).'/includes/footer.php';
