<?php

declare(strict_types=1);

function db(): PDO { global $pdo; return $pdo; }
function url(string $path=''): string { return SITEURL . ltrim($path,'/'); }
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function redirect(string $path): never { header('Location: '.(preg_match('#^https?://#',$path)?$path:url($path))); exit; }
function safe_back(string $default='foods.php'): never { $ref=(string)($_SERVER['HTTP_REFERER']??''); if($ref!==''){ $site=parse_url(SITEURL); $r=parse_url($ref); if(($r['host']??'')===($site['host']??'') && ($r['port']??null)===($site['port']??null)){ header('Location: '.$ref); exit; } } redirect($default); }
function flash(string $type,string $message): void { $_SESSION['flash'][]=['type'=>$type,'message'=>$message]; }
function render_flashes(): void { $messages=$_SESSION['flash']??[]; unset($_SESSION['flash']); foreach($messages as $f){$t=in_array($f['type'],['success','error','warning','info'],true)?$f['type']:'info'; echo '<div class="alert alert-'.e($t).'">'.e($f['message']).'</div>';}}
function csrf_token(): string { if(empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(32)); return $_SESSION['csrf_token']; }
function csrf_field(): string { return '<input type="hidden" name="csrf_token" value="'.e(csrf_token()).'">'; }
function verify_csrf(): void { $token=$_POST['csrf_token']??''; if(!is_string($token)||!hash_equals($_SESSION['csrf_token']??'',$token)){http_response_code(419);exit('Invalid or expired form token. Please go back and try again.');} }
function is_post(): bool { return ($_SERVER['REQUEST_METHOD']??'GET')==='POST'; }

function auth_user(): ?array {
    if(empty($_SESSION['auth']['user_id'])||($_SESSION['auth']['role']??'')!=='customer') return null;
    $stmt=db()->prepare('SELECT id,user_name,full_name,phone_number,email,address,image_name,account_status,created_at FROM tbl_user WHERE id=? LIMIT 1');
    $stmt->execute([(int)$_SESSION['auth']['user_id']]); $u=$stmt->fetch();
    if(!$u||$u['account_status']!=='Active'){ unset($_SESSION['auth']); return null; }
    return $u;
}
function auth_admin(): ?array { if(empty($_SESSION['auth']['admin_id'])||($_SESSION['auth']['role']??'')!=='admin') return null; $s=db()->prepare('SELECT id,full_name,username FROM tbl_admin WHERE id=? LIMIT 1');$s->execute([(int)$_SESSION['auth']['admin_id']]);return $s->fetch()?:null; }
function require_login(): array { $u=auth_user(); if(!$u){flash('warning','Please log in to continue.');redirect('login.php');} return $u; }
function require_admin(): array { $a=auth_admin(); if(!$a){flash('warning','Please log in as an administrator.');redirect('admin/login.php');} return $a; }
function login_customer(array $u): void { session_regenerate_id(true);$_SESSION['auth']=['role'=>'customer','user_id'=>(int)$u['id']]; }
function login_admin(array $a): void { session_regenerate_id(true);$_SESSION['auth']=['role'=>'admin','admin_id'=>(int)$a['id']]; }
function logout_auth(): void { unset($_SESSION['auth']);session_regenerate_id(true); }
function verify_password_and_upgrade(string $plain,string $stored,string $table,int $id): bool { if(password_verify($plain,$stored)){if(password_needs_rehash($stored,PASSWORD_DEFAULT)){db()->prepare("UPDATE {$table} SET password=? WHERE id=?")->execute([password_hash($plain,PASSWORD_DEFAULT),$id]);}return true;} if(preg_match('/^[a-f0-9]{32}$/i',$stored)&&hash_equals(strtolower($stored),md5($plain))){db()->prepare("UPDATE {$table} SET password=? WHERE id=?")->execute([password_hash($plain,PASSWORD_DEFAULT),$id]);return true;}return false; }

function upload_image(array $file,string $folder,?string $oldName=null): ?string {
    if(($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return $oldName;
    if(($file['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK) throw new RuntimeException('Image upload failed.');
    if(($file['size']??0)>3*1024*1024) throw new RuntimeException('Image must be smaller than 3 MB.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if(!isset($allowed[$mime])) throw new RuntimeException('Only JPG, PNG and WEBP images are allowed.');
    $dir=dirname(__DIR__).'/images/'.trim($folder,'/'); if(!is_dir($dir)&&!mkdir($dir,0775,true)&&!is_dir($dir)) throw new RuntimeException('Upload directory could not be created.');
    $name=bin2hex(random_bytes(12)).'.'.$allowed[$mime]; if(!move_uploaded_file($file['tmp_name'],$dir.'/'.$name)) throw new RuntimeException('Unable to save uploaded image.');
    if($oldName&&!str_contains($oldName,'..')){$old=$dir.'/'.basename($oldName);if(is_file($old)&&basename($oldName)!=='default_profile.webp')@unlink($old);} return $name;
}
function money(float|int|string $amount): string { return 'Tk '.number_format((float)$amount,2); }
function status_class(string $status): string { return match($status){'Delivered','Paid','Active'=>'status-success','Cancelled','Failed','Blocked'=>'status-danger','Preparing','On Delivery','Submitted'=>'status-warning',default=>'status-info'}; }
function cart_count(): int { $u=auth_user();if(!$u)return 0;$s=db()->prepare('SELECT COALESCE(SUM(quantity),0) FROM tbl_cart WHERE user_id=?');$s->execute([$u['id']]);return (int)$s->fetchColumn(); }
function wishlist_count(): int { $u=auth_user();if(!$u)return 0;$s=db()->prepare('SELECT COUNT(*) FROM tbl_wishlist WHERE user_id=?');$s->execute([$u['id']]);return (int)$s->fetchColumn(); }
function is_wishlisted(int $foodId): bool { $u=auth_user();if(!$u)return false;$s=db()->prepare('SELECT 1 FROM tbl_wishlist WHERE user_id=? AND food_id=?');$s->execute([$u['id'],$foodId]);return (bool)$s->fetchColumn(); }
function food_image(?string $n): string { return $n?url('images/foods/'.rawurlencode($n)):url('images/bg.jpg'); }
function category_image(?string $n): string { return $n?url('images/Category/'.rawurlencode($n)):url('images/bg.jpg'); }
function user_image(?string $n): string { return $n?url('images/users/'.rawurlencode($n)):url('images/users/default_profile.webp'); }
function payment_label(string $m): string { return match($m){'bkash'=>'bKash','nagad'=>'Nagad','sslcommerz'=>'SSLCOMMERZ',default=>'Cash on Delivery'}; }
function payment_number(string $m): string { return $m==='bkash'?env_value('BKASH_NUMBER',''):($m==='nagad'?env_value('NAGAD_NUMBER',''):''); }
function mobile_payment_enabled(string $m): bool { $n=preg_replace('/\s+/','',payment_number($m)); return in_array($m,['bkash','nagad'],true) && (bool)preg_match('/^\+?[0-9]{10,15}$/',$n); }
function delivery_fee(float $subtotal): float { return $subtotal>=1000?0.0:35.0; }
function rating_stars(float $rating): string { $n=max(0,min(5,(int)round($rating))); return str_repeat('★',$n).str_repeat('☆',5-$n); }
function valid_order_statuses(): array { return ['Ordered','Preparing','On Delivery','Delivered','Cancelled']; }
function order_progress(string $status): int { return match($status){'Preparing'=>2,'On Delivery'=>3,'Delivered'=>4,'Cancelled'=>0,default=>1}; }
function can_cancel_order(array $order): bool { return in_array($order['status'],['Ordered','Preparing'],true) && ($order['payment_status']??'Pending')==='Pending'; }
function get_default_address(int $userId): ?array { $s=db()->prepare('SELECT * FROM tbl_user_address WHERE user_id=? ORDER BY is_default DESC,id DESC LIMIT 1');$s->execute([$userId]);return $s->fetch()?:null; }
function coupon_discount(array $coupon,float $subtotal): float { if($coupon['discount_type']==='Percentage'){$d=$subtotal*((float)$coupon['discount_value']/100);if($coupon['max_discount']!==null)$d=min($d,(float)$coupon['max_discount']);return round($d,2);}return min($subtotal,(float)$coupon['discount_value']); }
function validate_coupon(string $code,float $subtotal,int $userId): array {
    $code=strtoupper(trim($code)); if($code==='') return [null,''];
    $s=db()->prepare("SELECT * FROM tbl_coupon WHERE code=? AND active='Yes' AND starts_at<=NOW() AND ends_at>=NOW() LIMIT 1");$s->execute([$code]);$c=$s->fetch();
    if(!$c)return [null,'Coupon is invalid or expired.']; if($subtotal<(float)$c['min_order'])return [null,'Minimum order for this coupon is '.money($c['min_order']).'.'];
    if($c['usage_limit']!==null&&(int)$c['used_count']>=(int)$c['usage_limit'])return [null,'This coupon has reached its usage limit.'];
    $u=db()->prepare('SELECT COUNT(*) FROM tbl_coupon_usage WHERE coupon_id=? AND user_id=?');$u->execute([$c['id'],$userId]);if((int)$u->fetchColumn()>0)return [null,'You have already used this coupon.']; return [$c,''];
}


function sslcommerz_enabled(): bool { return strtolower(env_value('SSLCOMMERZ_ENABLED','false'))==='true' && env_value('SSLCOMMERZ_STORE_ID')!=='' && env_value('SSLCOMMERZ_STORE_PASSWORD')!==''; }
function sslcommerz_base(): string { return strtolower(env_value('SSLCOMMERZ_SANDBOX','true'))==='true' ? 'https://sandbox.sslcommerz.com' : 'https://securepay.sslcommerz.com'; }
function http_form_post(string $url,array $fields): array {
    if(!function_exists('curl_init')) throw new RuntimeException('PHP cURL extension is required for online gateway payment.');
    $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($fields),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>true]);$body=curl_exec($ch);$error=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);if($body===false||$code<200||$code>=300)throw new RuntimeException('Payment gateway connection failed'.($error?': '.$error:'.'));$data=json_decode((string)$body,true);if(!is_array($data))throw new RuntimeException('Payment gateway returned an invalid response.');return $data;
}
function http_json_get(string $url): array {
    if(!function_exists('curl_init')) throw new RuntimeException('PHP cURL extension is required for online gateway payment.');$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>true]);$body=curl_exec($ch);$error=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);if($body===false||$code<200||$code>=300)throw new RuntimeException('Payment validation failed'.($error?': '.$error:'.'));$data=json_decode((string)$body,true);if(!is_array($data))throw new RuntimeException('Payment validation returned an invalid response.');return $data;
}
function sslcommerz_create_session(array $order,array $user): array {
    if(!sslcommerz_enabled()) throw new RuntimeException('SSLCOMMERZ is not configured.');
    $tranId='SHU'.$order['id'].'_'.bin2hex(random_bytes(5));
    $data=http_form_post(sslcommerz_base().'/gwprocess/v4/api.php',[
        'store_id'=>env_value('SSLCOMMERZ_STORE_ID'),'store_passwd'=>env_value('SSLCOMMERZ_STORE_PASSWORD'),'total_amount'=>number_format((float)$order['total'],2,'.',''),'currency'=>'BDT','tran_id'=>$tranId,
        'success_url'=>url('sslcommerz-callback.php?mode=success'),'fail_url'=>url('sslcommerz-callback.php?mode=fail'),'cancel_url'=>url('sslcommerz-callback.php?mode=cancel'),'ipn_url'=>url('sslcommerz-callback.php?mode=ipn'),
        'cus_name'=>$user['full_name'],'cus_email'=>$user['email'],'cus_add1'=>$order['delivery_address'],'cus_city'=>'Dhaka','cus_postcode'=>'1000','cus_country'=>'Bangladesh','cus_phone'=>$order['phone_number'],
        'shipping_method'=>'NO','product_name'=>'Restaurant food order','product_category'=>'Food','product_profile'=>'general','value_a'=>(string)$order['id']
    ]);
    if(($data['status']??'')!=='SUCCESS'||empty($data['GatewayPageURL'])) throw new RuntimeException('Unable to create SSLCOMMERZ payment session.');
    return ['gateway_url'=>(string)$data['GatewayPageURL'],'tran_id'=>$tranId,'sessionkey'=>(string)($data['sessionkey']??'')];
}
function sslcommerz_validate(string $valId): array {
    $query=http_build_query(['val_id'=>$valId,'store_id'=>env_value('SSLCOMMERZ_STORE_ID'),'store_passwd'=>env_value('SSLCOMMERZ_STORE_PASSWORD'),'format'=>'json']);
    return http_json_get(sslcommerz_base().'/validator/api/validationserverAPI.php?'.$query);
}
function restore_order_reservations(int $orderId,string $paymentStatus='Failed',string $reason='Online payment was not completed.'): void {
    db()->beginTransaction();try{$s=db()->prepare('SELECT * FROM tbl_order WHERE id=? FOR UPDATE');$s->execute([$orderId]);$o=$s->fetch();if(!$o||$o['status']==='Cancelled'||$o['payment_status']==='Paid'){db()->commit();return;}$s=db()->prepare('SELECT food_id,quantity FROM tbl_order_item WHERE order_id=?');$s->execute([$orderId]);foreach($s->fetchAll() as $i){if($i['food_id'])db()->prepare('UPDATE tbl_food SET stock_qty=stock_qty+? WHERE id=?')->execute([$i['quantity'],$i['food_id']]);}$s=db()->prepare('SELECT coupon_id FROM tbl_coupon_usage WHERE order_id=?');$s->execute([$orderId]);if($cid=$s->fetchColumn()){db()->prepare('DELETE FROM tbl_coupon_usage WHERE order_id=?')->execute([$orderId]);db()->prepare('UPDATE tbl_coupon SET used_count=GREATEST(used_count-1,0) WHERE id=?')->execute([$cid]);}db()->prepare("UPDATE tbl_order SET status='Cancelled',payment_status=?,cancel_reason=?,cancelled_at=NOW() WHERE id=?")->execute([$paymentStatus,$reason,$orderId]);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
