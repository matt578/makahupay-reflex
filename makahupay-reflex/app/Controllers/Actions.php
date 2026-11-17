<?php
declare(strict_types=1);

final class Actions {
    public static function handle(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
        verify_csrf();
        $action = (string)($_POST['action'] ?? '');
        try {
            switch ($action) {
                case 'login':
                    if (Auth::attempt(post_string('email',190,true), (string)($_POST['password'] ?? ''))) redirect(app_url('index.php'));
                    flash('danger','Invalid email or password, or the account is inactive.'); redirect(app_url('index.php?page=login'));
                case 'logout': require_login(); Auth::logout(); redirect(app_url('index.php?page=login'));
                case 'save_customer': self::saveCustomer(); break;
                case 'archive_customer': require_roles(['Administrator','Staff']); db()->prepare("UPDATE customers SET status='archived' WHERE id=?")->execute([post_int('id',1)]); log_activity('customer_archive','Customer archived'); flash('success','Customer archived.'); break;
                case 'restore_customer': require_roles(['Administrator','Staff']); db()->prepare("UPDATE customers SET status='active' WHERE id=?")->execute([post_int('id',1)]); log_activity('customer_restore','Customer restored'); flash('success','Customer restored.'); break;
                case 'save_service': self::saveService(); break;
                case 'toggle_service': require_roles(['Administrator']); db()->prepare("UPDATE services SET status=IF(status='active','archived','active') WHERE id=?")->execute([post_int('id',1)]); log_activity('service_update','Service status changed'); flash('success','Service status updated.'); break;
                case 'save_therapist': self::saveTherapist(); break;
                case 'save_appointment': self::saveAppointment(); break;
                case 'appointment_status': self::appointmentStatus(); break;
                case 'walkin': self::createWalkin(); break;
                case 'checkout': self::checkout(); break;
                case 'save_session': self::saveSession(); break;
                case 'save_inventory': self::saveInventory(); break;
                case 'inventory_movement': self::inventoryMovement(); break;
                case 'save_expense': self::saveExpense(); break;
                case 'delete_expense': require_roles(['Administrator']); db()->prepare('DELETE FROM expenses WHERE id=?')->execute([post_int('id',1)]); log_activity('expense_delete','Expense deleted'); flash('success','Expense deleted.'); break;
                case 'save_user': self::saveUser(); break;
                case 'save_settings': self::saveSettings(); break;
                case 'update_profile': self::updateProfile(); break;
                case 'request_reset': self::requestReset(); break;
                case 'perform_reset': self::performReset(); break;
                case 'restore_database': self::restoreDatabase(); break;
                default: throw new InvalidArgumentException('Unknown action.');
            }
        } catch (Throwable $e) {
            error_log('[Makahupay action ' . $action . '] ' . $e->getMessage());
            flash('danger', $e instanceof InvalidArgumentException ? $e->getMessage() : 'The action could not be completed. Please check the information and try again.');
        }
        $return = (string)($_POST['_return'] ?? $_SERVER['HTTP_REFERER'] ?? app_url('index.php'));
        $base = app_url('index.php'); $baseHost = parse_url($base, PHP_URL_HOST); $returnHost = parse_url($return, PHP_URL_HOST);
        $safeRelative = str_starts_with($return, '/') && !str_starts_with($return, '//');
        $safeSameHost = $returnHost !== null && $baseHost !== null && hash_equals((string)$baseHost, (string)$returnHost);
        if (!$safeRelative && !$safeSameHost) $return = $base;
        redirect($return);
    }

    private static function saveCustomer(): void {
        require_roles(['Administrator','Staff']);
        $id = (int)($_POST['id'] ?? 0);
        $email=mb_strtolower(post_string('email',190));if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Enter a valid customer email address.');
        $data = [post_string('full_name',160,true),post_string('phone',40,true),$email,post_string('address',2000),self::nullableDate('date_of_birth'),post_string('gender',30),post_string('emergency_contact',160),post_string('notes',3000)];
        if ($id) {
            $data[] = $id;
            db()->prepare('UPDATE customers SET full_name=?,phone=?,email=?,address=?,date_of_birth=?,gender=?,emergency_contact=?,notes=? WHERE id=?')->execute($data);
            log_activity('customer_update','Customer record updated'); flash('success','Customer updated.');
        } else {
            array_unshift($data,create_code('CUS'));
            db()->prepare('INSERT INTO customers (customer_code,full_name,phone,email,address,date_of_birth,gender,emergency_contact,notes) VALUES (?,?,?,?,?,?,?,?,?)')->execute($data);
            log_activity('customer_create','New customer registered'); flash('success','Customer registered.');
        }
    }
    private static function saveService(): void {
        require_roles(['Administrator']);
        $id=(int)($_POST['id']??0); $name=post_string('name',140,true); $duration=post_int('duration_minutes',1); $price=post_money('price');
        $description=post_string('description',3000); $status=($_POST['status']??'active')==='archived'?'archived':'active';
        if ($id) db()->prepare('UPDATE services SET name=?,description=?,duration_minutes=?,price=?,status=? WHERE id=?')->execute([$name,$description,$duration,$price,$status,$id]);
        else db()->prepare('INSERT INTO services (service_code,name,description,duration_minutes,price,status) VALUES (?,?,?,?,?,?)')->execute([create_code('SVC'),$name,$description,$duration,$price,$status]);
        log_activity('service_modify',($id?'Updated':'Created').' service '.$name); flash('success','Service saved.');
    }
    private static function saveTherapist(): void {
        require_roles(['Administrator']); $id=(int)($_POST['id']??0);
        $oldImage=null;if($id){$oq=db()->prepare('SELECT profile_image FROM therapists WHERE id=?');$oq->execute([$id]);$oldImage=$oq->fetchColumn()?:null;} $image=self::uploadImage('profile_image',$oldImage);
        $data=[post_string('full_name',160,true),post_string('phone',40),post_string('address',2000),post_string('position',80),post_string('specialization',190),($_POST['status']??'active')==='inactive'?'inactive':'active',in_array($_POST['availability_status']??'', ['available','busy','on_break','off_duty','inactive'],true)?$_POST['availability_status']:'available',self::nullableDate('hire_date'),post_string('notes',3000),$image];
        if ($id) { $data[]=$id; db()->prepare('UPDATE therapists SET full_name=?,phone=?,address=?,position=?,specialization=?,status=?,availability_status=?,hire_date=?,notes=?,profile_image=? WHERE id=?')->execute($data); }
        else { array_unshift($data,create_code('EMP')); db()->prepare('INSERT INTO therapists (employee_code,full_name,phone,address,position,specialization,status,availability_status,hire_date,notes,profile_image) VALUES (?,?,?,?,?,?,?,?,?,?,?)')->execute($data); }
        log_activity('therapist_modify',($id?'Updated':'Added').' therapist'); flash('success','Therapist profile saved.');
    }
    private static function saveAppointment(): void {
        require_roles(['Administrator','Staff']);
        $id=(int)($_POST['id']??0); if($id){$old=db()->prepare('SELECT status,payment_status,therapist_id FROM appointments WHERE id=?');$old->execute([$id]);$oldRow=$old->fetch();if(!$oldRow)throw new InvalidArgumentException('Appointment not found.');if(in_array($oldRow['status'],['in_session','completed','cancelled','no_show'],true)||$oldRow['payment_status']==='paid')throw new InvalidArgumentException('In-session, completed, cancelled, no-show, or paid appointments cannot be edited.');} $customer=post_int('customer_id',1); $service=post_int('service_id',1); $therapist=post_int('therapist_id',1);
        $date=post_string('appointment_date',10,true); $start=post_string('start_time',5,true); $notes=post_string('notes',3000);
        if (!valid_date($date) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$start)) throw new InvalidArgumentException('Enter a valid appointment date and start time.');
        $svc=db()->prepare("SELECT duration_minutes FROM services WHERE id=? AND status='active'"); $svc->execute([$service]); $duration=$svc->fetchColumn(); if (!$duration) throw new InvalidArgumentException('Select an active service.');
        $ther=db()->prepare("SELECT id,availability_status FROM therapists WHERE id=? AND status='active'"); $ther->execute([$therapist]); $therRow=$ther->fetch(); if (!$therRow) throw new InvalidArgumentException('Select an active therapist.'); if($therRow['availability_status']!=='available' && (!$id || (int)$oldRow['therapist_id']!==$therapist)) throw new InvalidArgumentException('Select a therapist marked as available.');
        $customerCheck=db()->prepare("SELECT id FROM customers WHERE id=? AND status='active'"); $customerCheck->execute([$customer]); if (!$customerCheck->fetchColumn()) throw new InvalidArgumentException('Select an active customer.');
        $startAt=new DateTimeImmutable($date.' '.$start);$now=new DateTimeImmutable();if($startAt<$now)throw new InvalidArgumentException('Appointments cannot be created in the past.');$notice=(int)setting('booking_notice_hours','0'); if($notice>0 && $startAt < $now->modify('+'.$notice.' hours')) throw new InvalidArgumentException('This appointment does not meet the minimum booking notice.');
        $interval=max(1,(int)setting('appointment_interval_minutes','30'));$minutesSinceMidnight=((int)substr($start,0,2))*60+(int)substr($start,3,2);if($minutesSinceMidnight%$interval!==0)throw new InvalidArgumentException('Choose a start time that matches the configured '.$interval.'-minute booking interval.');
        $endAt=$startAt->modify('+'.(int)$duration.' minutes'); if($endAt->format('Y-m-d')!==$date)throw new InvalidArgumentException('This session would end on the next day. Choose an earlier start time.'); $end=$endAt->format('H:i:s');
        db()->beginTransaction();
        try {
            $lock=db()->prepare("SELECT id,availability_status FROM therapists WHERE id=? AND status='active' FOR UPDATE");$lock->execute([$therapist]);$lockedTherapist=$lock->fetch();if(!$lockedTherapist)throw new InvalidArgumentException('Selected therapist is no longer active.');if($lockedTherapist['availability_status']!=='available' && (!$id || (int)$oldRow['therapist_id']!==$therapist))throw new InvalidArgumentException('Selected therapist is no longer available.');
            $sql="SELECT COUNT(*) FROM appointments WHERE therapist_id=? AND appointment_date=? AND status IN ('pending','confirmed','checked_in','in_session') AND start_time < ? AND end_time > ?"; $params=[$therapist,$date,$end,$start.':00'];
            if ($id) { $sql.=' AND id<>?'; $params[]=$id; }
            $q=db()->prepare($sql); $q->execute($params); if ((int)$q->fetchColumn()>0) throw new InvalidArgumentException('Therapist is already booked during this time. Choose another time or therapist.');
            if ($id) { $q=db()->prepare('UPDATE appointments SET customer_id=?,service_id=?,therapist_id=?,appointment_date=?,start_time=?,end_time=?,notes=? WHERE id=?'); $q->execute([$customer,$service,$therapist,$date,$start.':00',$end,$notes,$id]); }
            else { $q=db()->prepare("INSERT INTO appointments (appointment_code,customer_id,service_id,therapist_id,appointment_date,start_time,end_time,status,notes,created_by) VALUES (?,?,?,?,?,?,?,'pending',?,?)"); $q->execute([create_code('APT'),$customer,$service,$therapist,$date,$start.':00',$end,$notes,current_user_id()]); }
            db()->commit();
        } catch(Throwable $e) { if(db()->inTransaction())db()->rollBack(); throw $e; }
        log_activity('appointment_create',($id?'Updated':'Created').' appointment'); flash('success','Appointment saved. End time: '.$endAt->format('g:i A').'.');
    }
    private static function appointmentStatus(): void {
        require_roles(['Administrator','Staff','Therapist']); $id=post_int('id',1); $status=(string)($_POST['status']??'');
        $allowed=['pending','confirmed','checked_in','in_session','completed','cancelled','no_show']; if (!in_array($status,$allowed,true)) throw new InvalidArgumentException('Invalid appointment status.');
        $q=db()->prepare('SELECT a.*,s.duration_minutes FROM appointments a JOIN services s ON s.id=a.service_id WHERE a.id=?'); $q->execute([$id]); $appt=$q->fetch(); if (!$appt) throw new InvalidArgumentException('Appointment not found.');
        if (is_therapist() && (int)$appt['therapist_id'] !== (int)(current_user()['therapist_id']??0)) throw new RuntimeException('This appointment is not assigned to your account.');
        if (is_therapist() && !in_array($status,['in_session','completed'],true)) throw new RuntimeException('Therapists may only start or complete their assigned sessions.');
        $transitions=['pending'=>['confirmed','cancelled','no_show'],'confirmed'=>['checked_in','cancelled','no_show'],'checked_in'=>['in_session','cancelled','no_show'],'in_session'=>['completed'],'completed'=>[],'cancelled'=>[],'no_show'=>[]];
        if (!in_array($status,$transitions[$appt['status']]??[],true)) throw new InvalidArgumentException('That status change is not allowed from the current appointment status.');
        db()->prepare('UPDATE appointments SET status=? WHERE id=?')->execute([$status,$id]);
        if ($status==='completed') {
            $exists=db()->prepare('SELECT id FROM sessions WHERE appointment_id=?'); $exists->execute([$id]);
            if (!$exists->fetchColumn()) db()->prepare('INSERT IGNORE INTO sessions (appointment_id,customer_id,service_id,therapist_id,session_date,duration_minutes,recorded_by) VALUES (?,?,?,?,?,?,?)')->execute([$id,$appt['customer_id'],$appt['service_id'],$appt['therapist_id'],$appt['appointment_date'].' '.$appt['start_time'],$appt['duration_minutes'],current_user_id()]);
        }
        log_activity('appointment_status','Appointment '.$appt['appointment_code'].' status changed to '.$status); flash('success','Appointment status updated.');
    }
    private static function createWalkin(): void {
        require_roles(['Administrator','Staff']);
        $customer=post_int('customer_id',1); $service=post_int('service_id',1); $therapist=post_int('therapist_id',1); $date=date('Y-m-d');
        $svc=db()->prepare("SELECT duration_minutes FROM services WHERE id=? AND status='active'"); $svc->execute([$service]); $duration=$svc->fetchColumn(); if (!$duration) throw new InvalidArgumentException('Choose an active service.');
        $ther=db()->prepare("SELECT id FROM therapists WHERE id=? AND status='active' AND availability_status='available'"); $ther->execute([$therapist]); if (!$ther->fetchColumn()) throw new InvalidArgumentException('Choose a therapist marked as available.');
        $cu=db()->prepare("SELECT id FROM customers WHERE id=? AND status='active'"); $cu->execute([$customer]); if (!$cu->fetchColumn()) throw new InvalidArgumentException('Choose an active customer.');
        $start=new DateTimeImmutable(); $end=$start->modify('+'.(int)$duration.' minutes'); if($end->format('Y-m-d')!==$date)throw new InvalidArgumentException('The walk-in session would end on the next day. Please create it before closing time.'); $s=$start->format('H:i:s'); $e=$end->format('H:i:s');
        db()->beginTransaction();try{$lock=db()->prepare("SELECT id FROM therapists WHERE id=? AND status='active' AND availability_status='available' FOR UPDATE");$lock->execute([$therapist]);if(!$lock->fetchColumn())throw new InvalidArgumentException('Therapist is no longer available.');$q=db()->prepare("SELECT COUNT(*) FROM appointments WHERE therapist_id=? AND appointment_date=? AND status IN ('pending','confirmed','checked_in','in_session') AND start_time < ? AND end_time > ?"); $q->execute([$therapist,$date,$e,$s]); if ((int)$q->fetchColumn()) throw new InvalidArgumentException('Therapist is already assigned to another session right now.');db()->prepare("INSERT INTO appointments (appointment_code,customer_id,service_id,therapist_id,appointment_date,start_time,end_time,status,source,created_by) VALUES (?,?,?,?,?,?,?,'checked_in','walk_in',?)")->execute([create_code('WALK'),$customer,$service,$therapist,$date,$s,$e,current_user_id()]);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
        log_activity('walkin_create','Walk-in customer checked in'); flash('success','Walk-in added to the queue.');
    }
    private static function checkout(): void {
        require_roles(['Administrator','Staff']);
        $service=post_int('service_id',1); $customerRaw=trim((string)($_POST['customer_id']??'')); $customer=$customerRaw!==''?(int)$customerRaw:null; $appointmentRaw=trim((string)($_POST['appointment_id']??'')); $appointment=$appointmentRaw!==''?(int)$appointmentRaw:null; $therapistRaw=trim((string)($_POST['therapist_id']??'')); $therapist=$therapistRaw!==''?(int)$therapistRaw:null;
        $qty=post_int('quantity',1); if ($qty>50) throw new InvalidArgumentException('Quantity cannot exceed 50.'); $discount=post_money('discount'); $received=post_money('amount_received');
        $method=$_POST['payment_method']??'cash'; if (!in_array($method,['cash','gcash','bank_transfer','other'],true)) throw new InvalidArgumentException('Select a valid payment method.');
        $q=db()->prepare("SELECT id,name,price FROM services WHERE id=? AND status='active'"); $q->execute([$service]); $svc=$q->fetch(); if (!$svc) throw new InvalidArgumentException('Select an active service.');
        if ($customer) { $q=db()->prepare("SELECT id FROM customers WHERE id=? AND status='active'"); $q->execute([$customer]); if (!$q->fetchColumn()) throw new InvalidArgumentException('Customer not found.'); }
        if ($appointment) { $q=db()->prepare('SELECT a.customer_id,a.service_id,a.therapist_id,s.duration_minutes,a.status,a.payment_status FROM appointments a JOIN services s ON s.id=a.service_id WHERE a.id=?'); $q->execute([$appointment]); $a=$q->fetch(); if (!$a) throw new InvalidArgumentException('Appointment not found.'); if(!in_array($a['status'],['checked_in','in_session','completed'],true))throw new InvalidArgumentException('Only a checked-in or completed appointment can be checked out.');if($a['payment_status']==='paid')throw new InvalidArgumentException('This appointment has already been paid.'); if (!$customer) $customer=(int)$a['customer_id']; elseif ((int)$a['customer_id']!==$customer) throw new InvalidArgumentException('The selected customer does not match the appointment.'); if ((int)$a['service_id']!==$service) throw new InvalidArgumentException('The selected service does not match the appointment.'); $therapist=(int)$a['therapist_id']; }
        if ($therapist) { $q=db()->prepare("SELECT id,availability_status FROM therapists WHERE id=? AND status='active'"); $q->execute([$therapist]); $selectedTherapist=$q->fetch(); if (!$selectedTherapist) throw new InvalidArgumentException('Select an active therapist.'); if(!$appointment && !in_array($selectedTherapist['availability_status'],['available','busy'],true)) throw new InvalidArgumentException('Select a therapist who is available or currently handling a session.'); }
        $subtotal=round((float)$svc['price']*$qty,2); if ($discount>$subtotal) throw new InvalidArgumentException('Discount cannot exceed the subtotal.'); $total=round($subtotal-$discount,2);
        if ($received<$total) throw new InvalidArgumentException('Amount received is less than the total amount due.');
        db()->beginTransaction();
        try {
            if ($appointment) {
                $lock=db()->prepare('SELECT a.customer_id,a.service_id,a.therapist_id,s.duration_minutes,a.status,a.payment_status FROM appointments a JOIN services s ON s.id=a.service_id WHERE a.id=? FOR UPDATE');
                $lock->execute([$appointment]); $locked=$lock->fetch();
                if (!$locked) throw new InvalidArgumentException('Appointment no longer exists.');
                if (!in_array($locked['status'],['checked_in','in_session','completed'],true) || $locked['payment_status']==='paid') throw new InvalidArgumentException('This appointment is no longer available for checkout.');
                if ((int)$locked['customer_id'] !== (int)$customer || (int)$locked['service_id'] !== $service) throw new InvalidArgumentException('Appointment details changed. Refresh the POS and try again.');
                $therapist=(int)$locked['therapist_id']; $a=$locked;
            }
            $code=create_code('TXN'); $change=round($received-$total,2);
            db()->prepare('INSERT INTO transactions (transaction_code,customer_id,appointment_id,cashier_id,subtotal,discount,total,amount_received,change_amount,payment_method,status) VALUES (?,?,?,?,?,?,?,?,?,?,\'paid\')')->execute([$code,$customer,$appointment,current_user_id(),$subtotal,$discount,$total,$received,$change,$method]);
            $txId=(int)db()->lastInsertId();
            db()->prepare('INSERT INTO transaction_items (transaction_id,service_id,therapist_id,description,quantity,unit_price,line_total) VALUES (?,?,?,?,?,?,?)')->execute([$txId,$service,$therapist,$svc['name'],$qty,$svc['price'],$subtotal]);
            db()->prepare('INSERT INTO payments (transaction_id,amount,payment_method,received_by) VALUES (?,?,?,?)')->execute([$txId,$total,$method,current_user_id()]);
            if ($appointment) { db()->prepare("UPDATE appointments SET payment_status='paid',status='completed' WHERE id=?")->execute([$appointment]); $exists=db()->prepare('SELECT id FROM sessions WHERE appointment_id=?'); $exists->execute([$appointment]); if (!$exists->fetchColumn()) db()->prepare('INSERT IGNORE INTO sessions (appointment_id,customer_id,service_id,therapist_id,session_date,duration_minutes,recorded_by) VALUES (?,?,?,?,?,?,?)')->execute([$appointment,$customer,$service,$therapist,date('Y-m-d H:i:s'),(int)($a['duration_minutes']??60),current_user_id()]); } elseif ($customer && $therapist) { db()->prepare('INSERT INTO sessions (customer_id,service_id,therapist_id,session_date,duration_minutes,recorded_by) VALUES (?,?,?,?,?,?)')->execute([$customer,$service,$therapist,date('Y-m-d H:i:s'),(int)db()->query('SELECT duration_minutes FROM services WHERE id='.(int)$service)->fetchColumn(),current_user_id()]); }
            db()->commit(); log_activity('payment','Payment received: '.$code.' / '.money($total)); flash('success','Payment completed. Receipt is ready to print.'); $_SESSION['last_receipt_id']=$txId;
        } catch (Throwable $e) { db()->rollBack(); throw $e; }
    }
    private static function saveSession(): void {
        require_roles(['Administrator','Staff','Therapist']); $id=post_int('id',1); $q=db()->prepare('SELECT * FROM sessions WHERE id=?'); $q->execute([$id]); $s=$q->fetch(); if (!$s) throw new InvalidArgumentException('Session not found.');
        if (is_therapist() && (int)$s['therapist_id'] !== (int)(current_user()['therapist_id']??0)) throw new RuntimeException('You can only update your own sessions.');
        db()->prepare('UPDATE sessions SET customer_concerns=?,session_notes=?,recommendations=?,follow_up_notes=?,recorded_by=? WHERE id=?')->execute([post_string('customer_concerns',3000),post_string('session_notes',4000),post_string('recommendations',3000),post_string('follow_up_notes',3000),current_user_id(),$id]);
        log_activity('session_note','Session notes updated'); flash('success','Session notes saved.');
    }
    private static function saveInventory(): void {
        require_roles(['Administrator','Staff']); $id=(int)($_POST['id']??0); $data=[post_string('item_name',160,true),post_string('category',90),post_string('unit',40,true),post_money('current_stock'),post_money('minimum_stock'),post_money('cost'),post_string('supplier',160),self::nullableDate('expiration_date'),($_POST['status']??'active')==='inactive'?'inactive':'active'];
        if ($id) { $data[]=$id; db()->prepare('UPDATE inventory SET item_name=?,category=?,unit=?,current_stock=?,minimum_stock=?,cost=?,supplier=?,expiration_date=?,status=? WHERE id=?')->execute($data); }
        else { db()->prepare('INSERT INTO inventory (item_name,category,unit,current_stock,minimum_stock,cost,supplier,expiration_date,status) VALUES (?,?,?,?,?,?,?,?,?)')->execute($data); }
        log_activity('inventory_modify',($id?'Updated':'Added').' inventory item'); flash('success','Inventory item saved.');
    }
    private static function inventoryMovement(): void {
        require_roles(['Administrator','Staff']); $id=post_int('inventory_id',1); $type=$_POST['movement_type']??'stock_in'; if (!in_array($type,['stock_in','stock_out','adjustment'],true)) throw new InvalidArgumentException('Invalid stock movement.'); $qty=post_money('quantity',$type==='adjustment'); $reason=post_string('reason',190);
        db()->beginTransaction(); try { $q=db()->prepare('SELECT current_stock FROM inventory WHERE id=? FOR UPDATE'); $q->execute([$id]); $stock=$q->fetchColumn(); if ($stock===false) throw new InvalidArgumentException('Item not found.'); $new=$type==='stock_in'?(float)$stock+$qty:($type==='stock_out'?(float)$stock-$qty:$qty); if ($new<0) throw new InvalidArgumentException('Stock cannot become negative.'); db()->prepare('UPDATE inventory SET current_stock=? WHERE id=?')->execute([$new,$id]); db()->prepare('INSERT INTO inventory_movements (inventory_id,movement_type,quantity,reason,user_id) VALUES (?,?,?,?,?)')->execute([$id,$type,$qty,$reason,current_user_id()]); db()->commit(); } catch(Throwable $e){db()->rollBack();throw $e;}
        log_activity('inventory_movement','Inventory stock changed'); flash('success','Stock updated.');
    }
    private static function saveExpense(): void {
        require_roles(['Administrator']); $id=(int)($_POST['id']??0); $category=$_POST['category']??'other'; $categories=['supplies','utilities','rent','salaries','maintenance','marketing','other']; if (!in_array($category,$categories,true)) throw new InvalidArgumentException('Invalid expense category.'); $date=post_string('expense_date',10,true); if (!valid_date($date)) throw new InvalidArgumentException('Invalid expense date.'); $method=$_POST['payment_method']??'cash'; if (!in_array($method,['cash','gcash','bank_transfer','other'],true)) throw new InvalidArgumentException('Invalid payment method.'); $data=[$category,post_string('description',220,true),post_money('amount',false),$date,$method,post_string('notes',2000)];
        if ($id) { $data[]=$id; db()->prepare('UPDATE expenses SET category=?,description=?,amount=?,expense_date=?,payment_method=?,notes=? WHERE id=?')->execute($data); }
        else { array_unshift($data,create_code('EXP')); array_splice($data,6,0,[current_user_id()]); db()->prepare('INSERT INTO expenses (expense_code,category,description,amount,expense_date,payment_method,recorded_by,notes) VALUES (?,?,?,?,?,?,?,?)')->execute($data); }
        log_activity('expense_create',($id?'Updated':'Recorded').' expense'); flash('success','Expense saved.');
    }
    private static function saveUser(): void {
        require_roles(['Administrator']); $id=(int)($_POST['id']??0); $name=post_string('name',140,true); $email=mb_strtolower(post_string('email',190,true)); if (!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Enter a valid email address.'); $roleId=post_int('role_id',1); if ($roleId>3) throw new InvalidArgumentException('Invalid role.'); $therRaw=trim((string)($_POST['therapist_id']??'')); $ther=$therRaw!==''?(int)$therRaw:null; $status=($_POST['status']??'active')==='inactive'?'inactive':'active';
        if ($roleId===3 && !$ther) throw new InvalidArgumentException('Link a therapist profile to a Therapist account.');
        if ($id) { $password=trim((string)($_POST['password']??'')); if ($password!=='') { if (strlen($password)<12) throw new InvalidArgumentException('Password must be at least 12 characters.'); db()->prepare('UPDATE users SET name=?,email=?,role_id=?,therapist_id=?,status=?,password_hash=? WHERE id=?')->execute([$name,$email,$roleId,$ther,$status,password_hash($password,PASSWORD_DEFAULT),$id]); } else db()->prepare('UPDATE users SET name=?,email=?,role_id=?,therapist_id=?,status=? WHERE id=?')->execute([$name,$email,$roleId,$ther,$status,$id]); }
        else { $password=(string)($_POST['password']??''); if (strlen($password)<12) throw new InvalidArgumentException('Initial password must be at least 12 characters.'); db()->prepare('INSERT INTO users (name,email,role_id,therapist_id,status,password_hash) VALUES (?,?,?,?,?,?)')->execute([$name,$email,$roleId,$ther,$status,password_hash($password,PASSWORD_DEFAULT)]); }
        log_activity('user_modify',($id?'Updated':'Created').' user '.$email); flash('success','User account saved.');
    }
    private static function saveSettings(): void {
        require_roles(['Administrator']);
        $oldLogo=setting('business_logo');$logo=self::uploadImage('business_logo',$oldLogo!==''?$oldLogo:null);
        $interval=post_int('appointment_interval_minutes',5);if($interval>180)throw new InvalidArgumentException('Appointment interval must be 180 minutes or less.');
        $notice=post_int('booking_notice_hours',0);if($notice>720)throw new InvalidArgumentException('Booking notice cannot exceed 720 hours.');
        $businessEmail=mb_strtolower(post_string('business_email',190));if($businessEmail!==''&&!filter_var($businessEmail,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Enter a valid business email address.');
        $keys=['business_name'=>post_string('business_name',160,true),'business_address'=>post_string('business_address',1000),'contact_number'=>post_string('contact_number',40),'business_email'=>$businessEmail,'receipt_footer'=>post_string('receipt_footer',250),'business_hours'=>post_string('business_hours',100),'currency'=>'PHP','appointment_interval_minutes'=>(string)$interval,'booking_notice_hours'=>(string)$notice,'business_logo'=>$logo??''];
        foreach($keys as $key=>$value) db()->prepare('INSERT INTO system_settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute([$key,$value]);
        log_activity('settings_update','Business settings updated'); flash('success','Business settings saved.');
    }
    private static function updateProfile(): void {
        require_login(); $name=post_string('name',140,true); $password=(string)($_POST['password']??''); db()->prepare('UPDATE users SET name=?'.($password!==''?',password_hash=?':'').' WHERE id=?')->execute($password!==''?(strlen($password)>=12?[$name,password_hash($password,PASSWORD_DEFAULT),current_user_id()]:throw new InvalidArgumentException('Password must be at least 12 characters.')):[$name,current_user_id()]); $_SESSION['user']['name']=$name; log_activity('profile_update','User profile updated'); flash('success','Profile updated.');
    }
    private static function requestReset(): void {
        $email=mb_strtolower(post_string('email',190,true)); $q=db()->prepare("SELECT id,email FROM users WHERE email=? AND status='active'"); $q->execute([$email]); $u=$q->fetch();
        if ($u) { $token=bin2hex(random_bytes(32)); db()->prepare('INSERT INTO password_resets (user_id,token_hash,expires_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE))')->execute([$u['id'],hash('sha256',$token)]); $link=app_url('index.php?page=reset&token='.urlencode($token)); $from=getenv('MAIL_FROM')?:'no-reply@localhost'; $headers='From: '.$from."\r\n".'Content-Type: text/plain; charset=UTF-8'; @mail($u['email'],'Makahupay password reset',"Use this link within 30 minutes to reset your password:\n\n{$link}\n\nIf you did not request this, ignore this email.",$headers); }
        flash('success','When an active account matches that email, a reset link will be sent.'); redirect(app_url('index.php?page=forgot'));
    }
    private static function performReset(): void {
        $token=(string)($_POST['token']??''); $password=(string)($_POST['password']??''); if (!preg_match('/^[a-f0-9]{64}$/',$token)) throw new InvalidArgumentException('Invalid or expired reset link.'); if (strlen($password)<12) throw new InvalidArgumentException('Password must be at least 12 characters.');
        $q=db()->prepare('SELECT id,user_id FROM password_resets WHERE token_hash=? AND used_at IS NULL AND expires_at>NOW() ORDER BY id DESC LIMIT 1'); $q->execute([hash('sha256',$token)]); $reset=$q->fetch(); if (!$reset) throw new InvalidArgumentException('Invalid or expired reset link.');
        db()->beginTransaction(); try { db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$reset['user_id']]); db()->prepare('UPDATE password_resets SET used_at=NOW() WHERE id=?')->execute([$reset['id']]); db()->commit(); } catch(Throwable $e){db()->rollBack();throw $e;}
        flash('success','Password changed. You can now sign in.'); redirect(app_url('index.php?page=login'));
    }

    private static function restoreDatabase(): void {
        require_roles(['Administrator']);
        if (($_POST['confirm_restore'] ?? '') !== 'RESTORE') throw new InvalidArgumentException('Type RESTORE exactly to confirm the database replacement.');
        $file=$_FILES['backup_file']??null;
        if (!$file || ($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) throw new InvalidArgumentException('Choose a valid SQL backup file.');
        if (($file['size']??0)>30*1024*1024) throw new InvalidArgumentException('Backup file must be 30 MB or smaller.');
        $sql=file_get_contents($file['tmp_name']); if ($sql===false || trim($sql)==='') throw new InvalidArgumentException('The backup file is empty.');
        $allowed=['roles','users','customers','therapists','services','appointments','sessions','transactions','transaction_items','payments','inventory','inventory_movements','expenses','activity_logs','system_settings','password_resets','login_attempts'];
        $statements=[];$buffer='';$quote=null;$escape=false;$length=strlen($sql);
        for($i=0;$i<$length;$i++){
            $ch=$sql[$i];
            if($quote!==null){$buffer.=$ch;if($escape){$escape=false;continue;}if($ch==='\\'&&$quote!== '`'){$escape=true;continue;}if($ch===$quote){if($quote==='\''&&($sql[$i+1]??'')==="'"){$buffer.=$sql[++$i];continue;}$quote=null;}continue;}
            if($ch==="'"||$ch==='"'||$ch==='`'){$quote=$ch;$buffer.=$ch;continue;}
            if($ch===';'){$piece=trim($buffer);if($piece!=='')$statements[]=$piece;$buffer='';continue;}
            $buffer.=$ch;
        }
        if(trim($buffer)!=='')$statements[]=trim($buffer);
        if(count($statements)<5) throw new InvalidArgumentException('This does not look like a Makahupay SQL backup.');
        $pdo=db();$pdo->exec('SET FOREIGN_KEY_CHECKS=0');$count=0;
        try {
            foreach($statements as $statement){
                $statement=preg_replace('/^\s*--.*$/m','',$statement);$statement=trim($statement);if($statement==='')continue;
                if(preg_match('/^SET\s+FOREIGN_KEY_CHECKS\s*=\s*[01]$/i',$statement)){$pdo->exec($statement);continue;}
                if(preg_match('/^(?:DROP\s+TABLE\s+IF\s+EXISTS|CREATE\s+TABLE|INSERT\s+INTO)\s+`([a-z_]+)`/i',$statement,$m) && in_array($m[1],$allowed,true)){$pdo->exec($statement);$count++;continue;}
                throw new InvalidArgumentException('Backup contains a statement that is not allowed for restore.');
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        } catch(Throwable $e){ try{$pdo->exec('SET FOREIGN_KEY_CHECKS=1');}catch(Throwable){} throw $e; }
        log_activity('database_restore','Database restored from uploaded backup ('.$count.' statements)');
        $_SESSION=[]; session_regenerate_id(true);
        flash('success','Database restored. Please sign in again using the restored user accounts.');
        redirect(app_url('index.php?page=login'));
    }


    private static function uploadImage(string $field, ?string $oldPath = null): ?string {
        if (!isset($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return $oldPath;
        $file=$_FILES[$field]; if (($file['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) throw new InvalidArgumentException('Image upload failed.');
        if (($file['size']??0)>2*1024*1024) throw new InvalidArgumentException('Image must be 2 MB or smaller.');
        $info=@getimagesize($file['tmp_name']); if (!$info) throw new InvalidArgumentException('Upload a valid image file.');
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$ext=match($mime){'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp',default=>null};
        if (!$ext) throw new InvalidArgumentException('Only JPG, PNG, or WebP images are allowed.');
        $directory=dirname(__DIR__,2).'/public/uploads';if(!is_dir($directory)&&!mkdir($directory,0750,true)&&!is_dir($directory))throw new RuntimeException('Upload directory is unavailable.');
        $filename=bin2hex(random_bytes(16)).'.'.$ext;$destination=$directory.'/'.$filename;
        if(!move_uploaded_file($file['tmp_name'],$destination))throw new RuntimeException('Unable to save uploaded image.');
        return 'uploads/'.$filename;
    }

    private static function nullableDate(string $key): ?string { $v=trim((string)($_POST[$key]??'')); if ($v==='') return null; if (!valid_date($v)) throw new InvalidArgumentException("Invalid {$key}."); return $v; }
}
