<?php
declare(strict_types=1);
function status_badge(string $status): string {
    $map=['pending'=>'warning','confirmed'=>'info','checked_in'=>'primary','in_session'=>'success','completed'=>'success','cancelled'=>'danger','no_show'=>'secondary','paid'=>'success','unpaid'=>'warning','partial'=>'warning','refunded'=>'secondary','active'=>'success','archived'=>'secondary','inactive'=>'secondary','available'=>'success','busy'=>'danger','on_break'=>'warning','off_duty'=>'secondary','walk_in'=>'primary'];
    $class=$map[$status]??'secondary'; return '<span class="badge text-bg-'.$class.'">'.h(ucwords(str_replace('_',' ',$status))).'</span>';
}
function select_options(array $rows,string $valueKey,string $labelKey, string|int|null $selectedValue=null): string { $html=''; foreach($rows as $r) $html.='<option value="'.h($r[$valueKey]).'" '.selected($selectedValue,$r[$valueKey]).'>'.h($r[$labelKey]).'</option>'; return $html; }
function post_form_start(string $action,string $return=''): string { return '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="'.h($action).'">'.($return!==''?'<input type="hidden" name="_return" value="'.h($return).'">':''); }
function table_empty(int $colspan,string $message='No records found.'): string { return '<tr><td colspan="'.$colspan.'" class="text-center py-4 text-muted">'.h($message).'</td></tr>'; }
function app_page_url(string $page,array $params=[]): string { return app_url('index.php?'.http_build_query(['page'=>$page]+$params)); }
