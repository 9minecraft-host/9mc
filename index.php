<?php
/**
 * 9 Minecraft Shop - All-in-One
 * فایل واحد: index.php
 * داده‌ها در فایل‌های JSON کنار همین فایل ذخیره می‌شوند
 * 
 * ساختار فایل‌ها روی هاست:
 *   index.php       ← همین فایل
 *   users.json      ← کاربران (خودکار ساخته می‌شود)
 *   orders.json     ← سفارشات
 *   tickets.json    ← تیکت‌ها
 *   payment.json    ← تنظیمات پرداخت
 *   receipts/       ← رسیدهای آپلود شده (خودکار)
 *   .htaccess       ← امنیت
 */

session_start();

// ─── مسیرها ───────────────────────────────────────────
define('BASE', __DIR__ . '/');
define('F_USERS',   BASE . 'users.json');
define('F_ORDERS',  BASE . 'orders.json');
define('F_TICKETS', BASE . 'tickets.json');
define('F_PAYMENT', BASE . 'payment.json');
define('RECEIPTS',  BASE . 'receipts/');

// ─── ساخت فایل‌های اولیه در اولین اجرا ───────────────
function initFiles() {
    if (!file_exists(F_USERS)) {
        writeJ(F_USERS, [
            ['id'=>1,'u'=>'admin','pw'=>'admin','fn'=>'ادمین','ln'=>'سیستم','email'=>'admin@9mc.ir','ph'=>'','pv'=>'تهران','rank'=>'LEGEND','coins'=>99999,'mc'=>'AdminMC','isAdmin'=>true,'lvl'=>'superadmin','banned'=>false,'created'=>date('Y-m-d H:i:s')],
            ['id'=>2,'u'=>'ali123','pw'=>'12345','fn'=>'علی','ln'=>'محمدی','email'=>'ali@test.com','ph'=>'09121234567','pv'=>'تهران','rank'=>'VIP','coins'=>4500,'mc'=>'AliMC','isAdmin'=>false,'lvl'=>'','banned'=>false,'created'=>date('Y-m-d H:i:s')]
        ]);
    }
    if (!file_exists(F_ORDERS))  writeJ(F_ORDERS, [['id'=>1001,'uid'=>2,'user'=>'ali123','item'=>'رنک VIP','price'=>25000,'ref'=>'1234567890','receipt'=>'','date'=>'2024-02-01 10:30:00','st'=>'done']]);
    if (!file_exists(F_TICKETS)) writeJ(F_TICKETS, []);
    if (!file_exists(F_PAYMENT)) writeJ(F_PAYMENT, ['card'=>'6219861012345678','owner'=>'محمد احمدی','bank'=>'بانک ملت','active'=>true]);
    if (!is_dir(RECEIPTS)) mkdir(RECEIPTS, 0755, true);
    // .htaccess برای امنیت
    $ht = BASE . '.htaccess';
    if (!file_exists($ht)) file_put_contents($ht, "Options -Indexes\n<FilesMatch \"\.(json)$\">\n Order Allow,Deny\n Deny from all\n</FilesMatch>\n");
}
initFiles();

// ─── توابع JSON ────────────────────────────────────────
function readJ($f) { return file_exists($f) ? (json_decode(file_get_contents($f),true)??[]) : []; }
function readJO($f) { $d=readJ($f); return is_array($d)&&!isset($d[0]) ? $d : $d; }
function writeJ($f,$d) { return file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)) !== false; }

// ─── پاسخ JSON ────────────────────────────────────────
function ok($d=[],$m='') { echo json_encode(['ok'=>true,'data'=>$d,'msg'=>$m],JSON_UNESCAPED_UNICODE); exit; }
function er($m,$c=400) { http_response_code($c); echo json_encode(['ok'=>false,'msg'=>$m],JSON_UNESCAPED_UNICODE); exit; }

// ─── کاربر جاری ───────────────────────────────────────
function cu() { if(empty($_SESSION['uid'])) er('لطفاً وارد شوید',401); return getUser($_SESSION['uid']); }
function ca() { $u=cu(); if(!$u['isAdmin']) er('دسترسی غیرمجاز',403); return $u; }
function getUser($id) { foreach(readJ(F_USERS) as $u) if($u['id']==$id) return $u; return null; }
function findIdx($arr,$id) { foreach($arr as $i=>$x) if($x['id']==$id) return $i; return -1; }
function safe($u) { unset($u['pw']); return $u; }

// ─── آیا درخواست API است؟ ─────────────────────────────
$isAPI = isset($_GET['a']) || isset($_POST['action']) || (
    !empty($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'],'application/json')
);

if ($isAPI) {
    header('Content-Type: application/json; charset=utf-8');
    $action = '';
    
    // آپلود رسید (multipart)
    if (!empty($_FILES['receipt'])) {
        $action = 'upload_receipt';
    } else {
        $raw = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = $raw['action'] ?? $_GET['a'] ?? $_POST['action'] ?? '';
        $d = $raw;
    }
    
    switch ($action) {
        case 'login':
            $u2=trim($d['u']??''); $pw=$d['pw']??'';
            if(!$u2||!$pw) er('نام کاربری و رمز الزامی است');
            $found=null; foreach(readJ(F_USERS) as $x) if($x['u']===$u2&&$x['pw']===$pw){$found=$x;break;}
            if(!$found) er('نام کاربری یا رمز اشتباه است');
            if(!empty($found['banned'])) er('حساب شما مسدود شده است');
            $_SESSION['uid']=$found['id']; ok(safe($found),'خوش آمدید');
            
        case 'register':
            $fn=trim($d['fn']??''); $ln=trim($d['ln']??''); $u2=strtolower(trim($d['u']??''));
            $e=trim($d['email']??''); $pw=$d['pw']??'';
            if(!$u2||!$pw||!$e) er('همه فیلدها الزامی است');
            if(strlen($pw)<6) er('رمز حداقل ۶ کاراکتر باشد');
            if(!filter_var($e,FILTER_VALIDATE_EMAIL)) er('ایمیل معتبر نیست');
            if(!preg_match('/^[a-z0-9_]+$/',$u2)) er('نام کاربری: فقط حرف، عدد، _');
            $us=readJ(F_USERS);
            foreach($us as $x) if($x['u']===$u2) er('این نام کاربری قبلاً ثبت شده');
            $ids=array_column($us,'id'); $nid=$ids?max($ids)+1:1;
            $nu=['id'=>$nid,'u'=>$u2,'pw'=>$pw,'fn'=>$fn,'ln'=>$ln,'email'=>$e,'ph'=>'','pv'=>'','rank'=>'—','coins'=>0,'mc'=>'','isAdmin'=>false,'lvl'=>'','banned'=>false,'created'=>date('Y-m-d H:i:s')];
            $us[]=$nu; writeJ(F_USERS,$us); $_SESSION['uid']=$nid; ok(safe($nu),'ثبت‌نام موفق');
            
        case 'logout': session_destroy(); ok([],'خروج موفق');
        case 'me': $u2=cu(); ok(safe($u2));
            
        case 'update_profile':
            $u2=cu(); $us=readJ(F_USERS); $i=findIdx($us,$u2['id']);
            $us[$i]['fn']=trim($d['fn']??$us[$i]['fn']); $us[$i]['ln']=trim($d['ln']??$us[$i]['ln']);
            $us[$i]['email']=trim($d['email']??$us[$i]['email']); $us[$i]['ph']=trim($d['ph']??$us[$i]['ph']);
            $us[$i]['pv']=trim($d['pv']??$us[$i]['pv']); writeJ(F_USERS,$us); ok(safe($us[$i]),'ذخیره شد');
            
        case 'change_pw':
            $u2=cu(); $us=readJ(F_USERS); $i=findIdx($us,$u2['id']);
            $old=$d['old']??''; $new=$d['new']??''; $cf=$d['cf']??'';
            if($us[$i]['pw']!==$old) er('رمز فعلی اشتباه است');
            if($new!==$cf) er('رمزهای جدید مطابقت ندارند');
            if(strlen($new)<6) er('رمز جدید حداقل ۶ کاراکتر');
            $us[$i]['pw']=$new; writeJ(F_USERS,$us); ok([],'رمز تغییر یافت');
            
        case 'link_mc':
            $u2=cu(); $mc=trim($d['mc']??''); if(!$mc) er('یوزرنیم الزامی است');
            $us=readJ(F_USERS); $i=findIdx($us,$u2['id']); $us[$i]['mc']=$mc; writeJ(F_USERS,$us); ok(['mc'=>$mc]);
            
        case 'unlink_mc':
            $u2=cu(); $us=readJ(F_USERS); $i=findIdx($us,$u2['id']); $us[$i]['mc']=''; writeJ(F_USERS,$us); ok([]);
            
        case 'get_payment_info':
            $ps=readJ(F_PAYMENT); if(empty($ps['active'])) er('پرداخت غیرفعال است');
            $c=$ps['card']??''; if(strlen($c)==16) $c=implode('-',str_split($c,4));
            ok(['card'=>$c,'owner'=>$ps['owner']??'','bank'=>$ps['bank']??'']);
            
        case 'upload_receipt':
            cu();
            $f=$_FILES['receipt']; $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
            if(!in_array($ext,['jpg','jpeg','png','webp','gif'])) er('فقط فایل تصویری مجاز است');
            if($f['size']>5*1024*1024) er('حجم فایل حداکثر ۵ مگابایت');
            $name=uniqid('r_',true).'.'.$ext;
            if(!move_uploaded_file($f['tmp_name'],RECEIPTS.$name)) er('خطا در آپلود');
            ok(['file'=>$name],'رسید آپلود شد');
            
        case 'create_order':
            $u2=cu(); $item=trim($d['item']??''); $price=intval($d['price']??0);
            $ref=trim($d['ref']??''); $receipt=trim($d['receipt']??'');
            if(!$item||!$price||!$ref) er('اطلاعات ناقص است');
            $ords=readJ(F_ORDERS); $ids=array_column($ords,'id'); $nid=$ids?max($ids)+1:1001;
            $ord=['id'=>$nid,'uid'=>$u2['id'],'user'=>$u2['u'],'item'=>$item,'price'=>$price,'ref'=>$ref,'receipt'=>$receipt,'date'=>date('Y-m-d H:i:s'),'st'=>'pending'];
            $ords[]=$ord; writeJ(F_ORDERS,$ords); ok($ord,'سفارش ثبت شد');
            
        case 'my_orders':
            $u2=cu(); $ords=readJ(F_ORDERS);
            $mine=array_values(array_filter($ords,fn($o)=>$o['uid']==$u2['id']));
            usort($mine,fn($a,$b)=>strcmp($b['date'],$a['date'])); ok($mine);
            
        case 'create_ticket':
            $u2=cu(); $cat=trim($d['cat']??''); $subj=trim($d['subj']??''); $body=trim($d['body']??'');
            if(!$cat||!$subj||!$body) er('همه فیلدها الزامی است');
            $tkts=readJ(F_TICKETS); $ids=array_column($tkts,'id'); $nid=$ids?max($ids)+1:1;
            $t=['id'=>$nid,'uid'=>$u2['id'],'uname'=>$u2['u'],'fn'=>$u2['fn'],'cat'=>$cat,'subj'=>$subj,'st'=>'open','date'=>date('Y-m-d H:i:s'),'msgs'=>[['from'=>'user','name'=>$u2['fn']?:$u2['u'],'text'=>$body,'time'=>date('Y-m-d H:i:s')]]];
            $tkts[]=$t; writeJ(F_TICKETS,$tkts); ok($t,'تیکت ارسال شد');
            
        case 'my_tickets':
            $u2=cu(); $tkts=readJ(F_TICKETS);
            $mine=array_values(array_filter($tkts,fn($t)=>$t['uid']==$u2['id']));
            usort($mine,fn($a,$b)=>strcmp($b['date'],$a['date'])); ok($mine);
            
        case 'get_ticket':
            $u2=cu(); $id=intval($d['id']??0); $tkts=readJ(F_TICKETS);
            foreach($tkts as $t) if($t['id']==$id){if($t['uid']!=$u2['id']&&!$u2['isAdmin']) er('دسترسی غیرمجاز',403); ok($t);}
            er('تیکت یافت نشد',404);
            
        case 'ticket_reply':
            $u2=cu(); $id=intval($d['id']??0); $text=trim($d['text']??'');
            if(!$id||!$text) er('اطلاعات ناقص');
            $tkts=readJ(F_TICKETS);
            foreach($tkts as &$t) if($t['id']==$id&&$t['uid']==$u2['id']){$t['msgs'][]=['from'=>'user','name'=>$u2['fn']?:$u2['u'],'text'=>$text,'time'=>date('Y-m-d H:i:s')];$t['st']='pending';writeJ(F_TICKETS,$tkts);ok($t);}
            er('تیکت یافت نشد');
            
        // ── ADMIN ──
        case 'admin_stats':
            ca(); $us=readJ(F_USERS); $ords=readJ(F_ORDERS); $tkts=readJ(F_TICKETS);
            ok(['users'=>count($us),'admins'=>count(array_filter($us,fn($u)=>$u['isAdmin'])),'mc_linked'=>count(array_filter($us,fn($u)=>!empty($u['mc']))),'orders'=>count($ords),'pending_orders'=>count(array_filter($ords,fn($o)=>$o['st']==='pending')),'open_tickets'=>count(array_filter($tkts,fn($t)=>$t['st']==='open')),'total_revenue'=>array_sum(array_column(array_filter($ords,fn($o)=>$o['st']==='done'),'price'))]);
            
        case 'admin_users':
            ca(); $q=strtolower(trim($d['q']??'')); $us=readJ(F_USERS);
            if($q) $us=array_values(array_filter($us,fn($u)=>str_contains(strtolower($u['u']),$q)||str_contains(strtolower($u['email']),$q)));
            ok(array_map('safe',$us));
            
        case 'admin_all_orders':
            ca(); $f=$d['filter']??'all'; $ords=readJ(F_ORDERS);
            if($f!=='all') $ords=array_values(array_filter($ords,fn($o)=>$o['st']===$f));
            usort($ords,fn($a,$b)=>strcmp($b['date'],$a['date'])); ok($ords);
            
        case 'admin_order_action':
            ca(); $id=intval($d['id']??0); $act=$d['act']??'';
            if(!in_array($act,['done','rejected'])) er('عملیات نامعتبر');
            $ords=readJ(F_ORDERS); $found=false;
            foreach($ords as &$o) if($o['id']==$id){$o['st']=$act;$found=true;break;}
            if(!$found) er('سفارش یافت نشد');
            writeJ(F_ORDERS,$ords); ok([],$act==='done'?'تأیید شد':'رد شد');
            
        case 'admin_all_tickets':
            ca(); $f=$d['filter']??'all'; $tkts=readJ(F_TICKETS);
            if($f!=='all') $tkts=array_values(array_filter($tkts,fn($t)=>$t['st']===$f));
            usort($tkts,fn($a,$b)=>strcmp($b['date'],$a['date'])); ok($tkts);
            
        case 'admin_ticket_reply':
            ca(); $id=intval($d['id']??0); $text=trim($d['text']??'');
            if(!$id||!$text) er('اطلاعات ناقص');
            $tkts=readJ(F_TICKETS);
            foreach($tkts as &$t) if($t['id']==$id){$t['msgs'][]=['from'=>'admin','name'=>'پشتیبانی 9MC','text'=>$text,'time'=>date('Y-m-d H:i:s')];$t['st']='open';writeJ(F_TICKETS,$tkts);ok($t);}
            er('تیکت یافت نشد');
            
        case 'admin_ticket_status':
            ca(); $id=intval($d['id']??0); $st=$d['st']??'';
            if(!in_array($st,['open','pending','closed'])) er('وضعیت نامعتبر');
            $tkts=readJ(F_TICKETS);
            foreach($tkts as &$t) if($t['id']==$id){$t['st']=$st;writeJ(F_TICKETS,$tkts);ok([]);}
            er('تیکت یافت نشد');
            
        case 'admin_set_rank':
            ca(); $uid=intval($d['uid']??0); $rank=trim($d['rank']??'');
            $us=readJ(F_USERS); $i=findIdx($us,$uid); if($i===-1) er('کاربر یافت نشد');
            $us[$i]['rank']=$rank; writeJ(F_USERS,$us); ok([],'رنک تغییر یافت');
            
        case 'admin_ban_user':
            ca(); $uid=intval($d['uid']??0); $ban=(bool)($d['ban']??true);
            $us=readJ(F_USERS); $i=findIdx($us,$uid); if($i===-1) er('کاربر یافت نشد');
            if(!empty($us[$i]['isAdmin'])) er('نمی‌توانید ادمین را بن کنید');
            $us[$i]['banned']=$ban; writeJ(F_USERS,$us); ok([],$ban?'بن شد':'رفع بن');
            
        case 'admin_pay_settings':
            ca(); $ps=['card'=>trim($d['card']??''),'owner'=>trim($d['owner']??''),'bank'=>trim($d['bank']??''),'active'=>(bool)($d['active']??false)];
            writeJ(F_PAYMENT,$ps); ok($ps,'ذخیره شد');
            
        case 'admin_add_admin':
            $au=ca(); if(($au['lvl']??'')!=='superadmin') er('نیاز به سوپر ادمین دارید');
            $u2=strtolower(trim($d['u']??'')); $e=trim($d['email']??''); $pw=$d['pw']??'';
            if(!$u2||!$pw||!$e) er('همه فیلدها الزامی است');
            if(strlen($pw)<6) er('رمز حداقل ۶ کاراکتر');
            $us=readJ(F_USERS); foreach($us as $x) if($x['u']===$u2) er('این نام کاربری وجود دارد');
            $ids=array_column($us,'id'); $nid=$ids?max($ids)+1:1;
            $nu=['id'=>$nid,'u'=>$u2,'pw'=>$pw,'fn'=>trim($d['fn']??''),'ln'=>trim($d['ln']??''),'email'=>$e,'ph'=>'','pv'=>'','rank'=>'—','coins'=>0,'mc'=>'','isAdmin'=>true,'lvl'=>$d['lv']??'support','banned'=>false,'created'=>date('Y-m-d H:i:s')];
            $us[]=$nu; writeJ(F_USERS,$us); ok(safe($nu),'ادمین اضافه شد');
            
        case 'admin_remove_admin':
            $au=ca(); if(($au['lvl']??'')!=='superadmin') er('نیاز به سوپر ادمین دارید');
            $uid=intval($d['uid']??0); if($uid===1) er('ادمین اصلی قابل حذف نیست');
            $us=readJ(F_USERS); $i=findIdx($us,$uid); if($i===-1) er('یافت نشد');
            $us[$i]['isAdmin']=false; $us[$i]['lvl']=''; writeJ(F_USERS,$us); ok([],'حذف شد');

        case 'receipt_img':
            ca(); $fn=basename($d['file']??'');
            if(!$fn||!file_exists(RECEIPTS.$fn)) er('فایل یافت نشد');
            $ext=strtolower(pathinfo($fn,PATHINFO_EXTENSION));
            $mime=['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','gif'=>'image/gif'];
            header('Content-Type: '.($mime[$ext]??'image/jpeg'));
            readfile(RECEIPTS.$fn); exit;
            
        default: er('عملیات نامعتبر');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>9 Minecraft - فروشگاه</title>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700;900&display=swap" rel="stylesheet">
<style>
:root{--g:#5BBF3A;--gd:#3d8a27;--gg:rgba(91,191,58,.2);--gold:#F5C842;--dia:#42D4F4;--pur:#c084fc;--red:#e74c3c;--bg:#0d0d0d;--card:#141414;--c2:#1a1a1a;--bor:#2a2a2a;--tx:#e8e8e8;--mu:#777;--mu2:#555;}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Vazirmatn',sans-serif;background:var(--bg);color:var(--tx);min-height:100vh;direction:rtl;}
body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(91,191,58,.025) 1px,transparent 1px),linear-gradient(90deg,rgba(91,191,58,.025) 1px,transparent 1px);background-size:32px 32px;pointer-events:none;z-index:0;}
/* HEADER */
header{position:relative;z-index:100;background:rgba(10,10,10,.97);border-bottom:2px solid var(--g);padding:0 1.5rem;display:flex;align-items:center;justify-content:space-between;height:64px;box-shadow:0 2px 30px var(--gg);}
.logo{display:flex;align-items:center;gap:10px;cursor:pointer;}
.logo-icon{width:34px;height:34px;display:grid;grid-template-columns:repeat(4,1fr);grid-template-rows:repeat(4,1fr);border:2px solid var(--gd);image-rendering:pixelated;}
.logo-icon span{display:block;}
.logo-icon span:nth-child(1){background:#6acf45;}.logo-icon span:nth-child(2){background:#5BBF3A;}.logo-icon span:nth-child(3){background:#4faa30;}.logo-icon span:nth-child(4){background:#6acf45;}
.logo-icon span:nth-child(5){background:#3d8a27;}.logo-icon span:nth-child(6){background:#8B6440;}.logo-icon span:nth-child(7){background:#7a5836;}.logo-icon span:nth-child(8){background:#3d8a27;}
.logo-icon span:nth-child(9){background:#8B6440;}.logo-icon span:nth-child(10){background:#7a5836;}.logo-icon span:nth-child(11){background:#8B6440;}.logo-icon span:nth-child(12){background:#7a5836;}
.logo-icon span:nth-child(13){background:#7a5836;}.logo-icon span:nth-child(14){background:#8B6440;}.logo-icon span:nth-child(15){background:#6a4828;}.logo-icon span:nth-child(16){background:#7a5836;}
.logo-text{font-size:1.3rem;font-weight:900;}.logo-text em{font-style:normal;color:var(--g);}
nav{display:flex;gap:1.2rem;align-items:center;}
nav a{color:var(--mu);text-decoration:none;font-size:.83rem;font-weight:500;cursor:pointer;transition:color .2s;}
nav a:hover,nav a.active{color:var(--g);}
.hdr{display:flex;align-items:center;gap:7px;}
.btn{border:none;padding:7px 14px;font-family:'Vazirmatn',sans-serif;font-weight:700;font-size:.78rem;cursor:pointer;transition:all .2s;}
.btn-g{background:var(--g);color:#fff;clip-path:polygon(0 0,calc(100% - 4px) 0,100% 4px,100% 100%,4px 100%,0 calc(100% - 4px));}
.btn-g:hover{background:var(--gd);}
.btn-o{background:transparent;color:var(--tx);border:1px solid var(--bor);}
.btn-o:hover{border-color:var(--g);color:var(--g);}
.btn-r{background:transparent;color:var(--red);border:1px solid rgba(231,76,60,.35);}
.btn-r:hover{border-color:var(--red);}
.btn-gold{background:var(--gold);color:#1a1a1a;clip-path:polygon(0 0,calc(100% - 4px) 0,100% 4px,100% 100%,4px 100%,0 calc(100% - 4px));}
.uchip{display:flex;align-items:center;gap:7px;background:var(--card);border:1px solid var(--bor);padding:4px 11px 4px 4px;cursor:pointer;transition:border-color .2s;}
.uchip:hover{border-color:var(--g);}
.uav{width:27px;height:27px;background:var(--g);display:flex;align-items:center;justify-content:center;font-weight:900;font-size:.75rem;color:#fff;}
/* PAGES */
.page{display:none;position:relative;z-index:5;}.page.active{display:block;}
/* HERO */
.hero{text-align:center;padding:4rem 2rem 3rem;}
.hbadge{display:inline-block;background:rgba(91,191,58,.1);border:1px solid var(--g);color:var(--g);font-size:.67rem;font-weight:700;padding:3px 12px;letter-spacing:2px;text-transform:uppercase;margin-bottom:1.2rem;}
.hero h1{font-size:clamp(1.8rem,5vw,3.4rem);font-weight:900;line-height:1.05;margin-bottom:.7rem;}
.hero h1 em{font-style:normal;color:var(--g);}
.hero p{color:var(--mu);font-size:.9rem;max-width:440px;margin:0 auto 1.6rem;line-height:1.7;}
.hip{display:inline-flex;align-items:center;gap:10px;background:var(--card);border:1px solid var(--bor);padding:8px 16px;font-size:.9rem;font-weight:700;color:var(--g);}
.hip span{color:var(--mu);font-size:.72rem;font-weight:400;}
.hbtns{display:flex;gap:9px;justify-content:center;margin-top:1.3rem;flex-wrap:wrap;}
.sbar{display:flex;justify-content:center;border-top:1px solid var(--bor);border-bottom:1px solid var(--bor);background:rgba(18,18,18,.9);margin-bottom:2rem;}
.sv{flex:1;max-width:175px;text-align:center;padding:1rem .7rem;border-left:1px solid var(--bor);}
.sv:last-child{border-right:1px solid var(--bor);}
.svv{font-size:1.3rem;font-weight:900;color:var(--g);display:block;line-height:1;margin-bottom:3px;}
.svl{font-size:.67rem;color:var(--mu);text-transform:uppercase;letter-spacing:1px;}
/* SHOP */
.wrap{max-width:1100px;margin:0 auto;padding:0 1.5rem 4rem;}
.notice{background:rgba(91,191,58,.06);border:1px solid rgba(91,191,58,.18);border-right:3px solid var(--g);padding:.85rem 1rem;margin-bottom:1.6rem;font-size:.77rem;color:var(--mu);display:flex;align-items:flex-start;gap:9px;line-height:1.7;}
.tabs{display:flex;gap:3px;margin-bottom:1.5rem;background:var(--card);border:1px solid var(--bor);padding:3px;width:fit-content;flex-wrap:wrap;}
.tab{padding:6px 14px;border:none;background:transparent;color:var(--mu);font-family:'Vazirmatn',sans-serif;font-size:.79rem;font-weight:600;cursor:pointer;transition:all .15s;}
.tab.active{background:var(--g);color:#fff;}
.tab:hover:not(.active){color:var(--tx);}
.tp{display:none;}.tp.active{display:block;}
.stl{font-size:1.1rem;font-weight:900;margin-bottom:1.1rem;display:flex;align-items:center;gap:9px;}
.stl::after{content:'';flex:1;height:1px;background:var(--bor);}
.dot{width:7px;height:7px;background:var(--g);flex-shrink:0;}
/* RANKS */
.ranks{display:grid;grid-template-columns:repeat(auto-fill,minmax(178px,1fr));gap:9px;margin-bottom:2rem;}
.rc{border:1px solid var(--bor);background:var(--card);padding:1.1rem .85rem;text-align:center;position:relative;transition:border-color .2s,transform .15s;cursor:pointer;}
.rc:hover{transform:translateY(-3px);}
.rc.pop{border-color:var(--gold);}
.rc.pop::before{content:'محبوب‌ترین';position:absolute;top:-10px;left:50%;transform:translateX(-50%);background:var(--gold);color:#1a1a1a;font-size:.58rem;font-weight:700;padding:2px 8px;white-space:nowrap;}
.rn{font-size:.95rem;font-weight:900;margin-bottom:3px;}
.r1 .rn{color:#999;}.r2 .rn{color:var(--g);}.r3 .rn{color:var(--gold);}.r4 .rn{color:var(--dia);}.r5 .rn{color:var(--pur);}
.rp{list-style:none;margin:.6rem 0;text-align:right;}
.rp li{font-size:.69rem;color:var(--mu);padding:2px 0;display:flex;align-items:center;gap:5px;}
.rp li::before{content:'▸';color:var(--g);flex-shrink:0;}
.rprice{font-size:.98rem;font-weight:900;color:var(--g);margin-top:.6rem;display:block;}
.rbuy{margin-top:8px;width:100%;background:transparent;border:1px solid var(--bor);color:var(--tx);padding:6px;font-family:'Vazirmatn',sans-serif;font-size:.73rem;font-weight:600;cursor:pointer;transition:all .2s;}
.rbuy:hover{background:var(--g);border-color:var(--g);color:#fff;}
.rc.pop .rbuy{background:var(--gold);border-color:var(--gold);color:#1a1a1a;}
/* PRODUCTS */
.prods{display:grid;grid-template-columns:repeat(auto-fill,minmax(205px,1fr));gap:1px;background:var(--bor);border:1px solid var(--bor);}
.pc{background:var(--card);padding:1.1rem;position:relative;transition:background .2s;cursor:pointer;display:flex;flex-direction:column;}
.pc:hover{background:var(--c2);}.pc:hover .bbt{opacity:1;transform:translateY(0);}
.badge{position:absolute;top:8px;left:8px;font-size:.56rem;font-weight:700;padding:2px 6px;letter-spacing:1px;text-transform:uppercase;}
.bh{background:#c0392b;color:#fff;}.bn{background:var(--g);color:#fff;}.bs{background:var(--gold);color:#1a1a1a;}.bl{background:var(--dia);color:#0a3d4a;}
.picon{width:48px;height:48px;margin:0 auto .65rem;display:flex;align-items:center;justify-content:center;font-size:2.2rem;line-height:1;}
.pname{font-size:.86rem;font-weight:700;text-align:center;margin-bottom:3px;}
.pdesc{font-size:.72rem;color:var(--mu);text-align:center;line-height:1.5;margin-bottom:.65rem;flex:1;}
.pprice{display:flex;align-items:center;justify-content:space-between;margin-top:auto;padding-top:.65rem;border-top:1px solid var(--bor);}
.pval{font-size:.92rem;font-weight:900;color:var(--g);}
.cur{font-size:.59rem;font-weight:400;color:var(--mu);margin-right:2px;}
.pold{font-size:.65rem;color:var(--mu);text-decoration:line-through;display:block;}
.bbt{background:var(--g);color:#fff;border:none;padding:5px 10px;font-family:'Vazirmatn',sans-serif;font-size:.71rem;font-weight:700;cursor:pointer;opacity:.7;transform:translateY(3px);transition:all .2s;clip-path:polygon(0 0,calc(100% - 4px) 0,100% 4px,100% 100%,4px 100%,0 calc(100% - 4px));}
.bbt:hover{background:var(--gd);}
/* MODALS */
.ov{display:none;position:fixed;inset:0;background:rgba(0,0,0,.88);z-index:999;align-items:center;justify-content:center;padding:1rem;}
.ov.open{display:flex;}
.modal{background:#111;border:1px solid var(--bor);border-top:2px solid var(--g);width:100%;max-width:410px;padding:1.8rem;position:relative;max-height:90vh;overflow-y:auto;}
.mcls{position:absolute;top:10px;left:10px;background:none;border:none;color:var(--mu);font-size:1.2rem;cursor:pointer;line-height:1;}
.mcls:hover{color:var(--tx);}
.modal h2{font-size:1.15rem;font-weight:900;margin-bottom:.3rem;}
.modal h2 em{font-style:normal;color:var(--g);}
.sub{font-size:.74rem;color:var(--mu);margin-bottom:1.2rem;}
.f{margin-bottom:.85rem;}
.f label{display:block;font-size:.69rem;color:var(--mu);margin-bottom:3px;font-weight:500;}
.f input,.f select,.f textarea{width:100%;background:#0a0a0a;border:1px solid var(--bor);color:var(--tx);padding:7px 10px;font-family:'Vazirmatn',sans-serif;font-size:.83rem;outline:none;transition:border-color .2s;}
.f input:focus,.f select:focus,.f textarea:focus{border-color:var(--g);}
.f textarea{resize:vertical;min-height:75px;}
.fr{display:flex;gap:8px;}.fr .f{flex:1;}
.aerr{background:rgba(231,76,60,.1);border:1px solid rgba(231,76,60,.3);color:var(--red);padding:6px 10px;font-size:.73rem;margin-bottom:.7rem;display:none;}
.aerr.show{display:block;}
.asw{font-size:.73rem;color:var(--mu);margin-top:.6rem;text-align:center;}
.asw a{color:var(--g);cursor:pointer;}
/* PAY MODAL */
.paycard{background:#0a0a0a;border:1px solid var(--bor);padding:1rem;margin:.75rem 0;text-align:center;}
.pcnum{font-size:1.2rem;font-weight:900;color:var(--gold);letter-spacing:4px;font-family:monospace;}
.pcname{font-size:.72rem;color:var(--mu);margin-top:3px;}
.pamount{font-size:1.25rem;font-weight:900;color:var(--g);text-align:center;margin:.65rem 0;}
.psteps{font-size:.75rem;color:var(--mu);line-height:2.1;padding-right:1rem;}
.psteps li{list-style:decimal;}
/* RECEIPT UPLOAD */
.rbox{border:2px dashed var(--bor);padding:1rem;text-align:center;cursor:pointer;margin-top:.7rem;transition:border-color .2s;position:relative;}
.rbox:hover,.rbox.drag{border-color:var(--g);}
.rbox input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}
.rbox-icon{font-size:1.6rem;margin-bottom:.4rem;}
.rbox-txt{font-size:.75rem;color:var(--mu);}
.rpreview{margin-top:.7rem;display:none;}
.rpreview img{max-width:100%;max-height:140px;border:1px solid var(--bor);}
.rupload-ok{background:rgba(91,191,58,.1);border:1px solid rgba(91,191,58,.2);color:var(--g);padding:5px 10px;font-size:.73rem;margin-top:.5rem;display:none;}
/* STAFF */
.staff-hero{background:linear-gradient(135deg,rgba(91,191,58,.08),rgba(192,132,252,.06));border:1px solid rgba(91,191,58,.15);padding:2.5rem 2rem;text-align:center;margin-bottom:1.8rem;}
.staff-hero h2{font-size:1.8rem;font-weight:900;margin-bottom:.7rem;}
.staff-hero h2 em{font-style:normal;color:var(--g);}
.staff-hero p{color:var(--mu);line-height:1.8;max-width:520px;margin:0 auto 1.8rem;}
.staff-apply{display:inline-flex;align-items:center;gap:9px;background:var(--g);color:#fff;font-family:'Vazirmatn',sans-serif;font-weight:700;font-size:.95rem;padding:13px 32px;text-decoration:none;clip-path:polygon(0 0,calc(100% - 6px) 0,100% 6px,100% 100%,6px 100%,0 calc(100% - 6px));transition:background .2s,transform .15s;}
.staff-apply:hover{background:var(--gd);transform:scale(1.03);}
.pos-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:10px;margin-bottom:1.8rem;}
.pos-card{background:var(--card);border:1px solid var(--bor);padding:1.2rem;transition:border-color .2s;}
.pos-card:hover{border-color:var(--g);}
.pos-icon{font-size:1.8rem;margin-bottom:.65rem;}
.pos-name{font-size:.95rem;font-weight:700;margin-bottom:.3rem;}
.pos-desc{font-size:.73rem;color:var(--mu);line-height:1.6;}
.req-grid{background:var(--card);border:1px solid var(--bor);padding:1.3rem;margin-bottom:1.8rem;}
.req-grid h3{font-size:.95rem;font-weight:700;margin-bottom:.9rem;color:var(--g);}
.req-list{list-style:none;display:grid;grid-template-columns:1fr 1fr;gap:.4rem;}
.req-list li{font-size:.75rem;color:var(--mu);display:flex;align-items:center;gap:6px;line-height:1.5;}
.req-list li::before{content:'✓';color:var(--g);font-weight:700;flex-shrink:0;}
/* DASHBOARD */
.dw{max-width:1100px;margin:0 auto;padding:1.6rem 1.5rem 3.5rem;}
.dhdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.6rem;flex-wrap:wrap;gap:.7rem;}
.dhdr h1{font-size:1.3rem;font-weight:900;}
.dhdr h1 em{font-style:normal;color:var(--g);}
.dgrid{display:grid;grid-template-columns:215px 1fr;gap:13px;align-items:start;}
.dsb{display:flex;flex-direction:column;gap:3px;}
.dni{display:flex;align-items:center;gap:8px;padding:7px 11px;background:var(--card);border:1px solid transparent;font-size:.79rem;font-weight:500;cursor:pointer;transition:all .15s;color:var(--mu);}
.dni:hover{border-color:var(--bor);color:var(--tx);}
.dni.active{border-color:var(--g);color:var(--g);background:rgba(91,191,58,.07);}
.dp{display:none;}.dp.active{display:block;}
.scards{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:8px;margin-bottom:1.2rem;}
.sc{background:var(--card);border:1px solid var(--bor);padding:.85rem;}
.scl{font-size:.64rem;color:var(--mu);text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;}
.scv{font-size:1.3rem;font-weight:900;color:var(--g);}
.cb{background:var(--card);border:1px solid var(--bor);padding:1.1rem;margin-bottom:10px;}
.cb h3{font-size:.88rem;font-weight:700;margin-bottom:.75rem;padding-bottom:.55rem;border-bottom:1px solid var(--bor);}
.ot{width:100%;border-collapse:collapse;font-size:.74rem;}
.ot th{text-align:right;padding:5px 8px;color:var(--mu);font-weight:600;border-bottom:1px solid var(--bor);font-size:.64rem;text-transform:uppercase;letter-spacing:.5px;}
.ot td{padding:6px 8px;border-bottom:1px solid #1e1e1e;color:var(--tx);}
.ot tr:last-child td{border-bottom:none;}
.ot tr:hover td{background:rgba(255,255,255,.02);}
.os{font-size:.64rem;padding:2px 6px;font-weight:600;}
.os.done{background:rgba(91,191,58,.1);color:var(--g);}
.os.pend{background:rgba(245,200,66,.1);color:var(--gold);}
.os.rej{background:rgba(231,76,60,.1);color:var(--red);}
.pr{display:grid;grid-template-columns:1fr 1fr;gap:11px;margin-bottom:.65rem;}
.ff{display:flex;flex-direction:column;gap:4px;}
.ff label{font-size:.68rem;color:var(--mu);font-weight:500;}
.ff input,.ff select{background:#0a0a0a;border:1px solid var(--bor);color:var(--tx);padding:7px 10px;font-family:'Vazirmatn',sans-serif;font-size:.81rem;outline:none;transition:border-color .2s;}
.ff input:focus,.ff select:focus{border-color:var(--g);}
.nb{display:none;background:rgba(91,191,58,.1);border:1px solid rgba(91,191,58,.3);color:var(--g);padding:6px 11px;margin-bottom:.65rem;font-size:.74rem;}
.nb.show{display:block;}
/* TICKETS */
.tlist{display:flex;flex-direction:column;gap:6px;}
.ti{background:var(--card);border:1px solid var(--bor);padding:.8rem;cursor:pointer;transition:border-color .2s;}
.ti:hover{border-color:var(--g);}
.tih{display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;}
.tit{font-size:.81rem;font-weight:700;}
.tim{font-size:.66rem;color:var(--mu);}
.tprev{font-size:.72rem;color:var(--mu);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.ts{font-size:.62rem;padding:2px 6px;font-weight:700;}
.ts.open{background:rgba(91,191,58,.1);color:var(--g);border:1px solid rgba(91,191,58,.3);}
.ts.pend{background:rgba(245,200,66,.1);color:var(--gold);border:1px solid rgba(245,200,66,.3);}
.ts.closed{background:rgba(255,255,255,.05);color:var(--mu);border:1px solid var(--bor);}
.tchat{display:flex;flex-direction:column;gap:7px;margin-top:.75rem;max-height:260px;overflow-y:auto;}
.msg{padding:7px 10px;max-width:82%;font-size:.77rem;line-height:1.55;}
.msg.user{background:rgba(91,191,58,.1);border:1px solid rgba(91,191,58,.2);align-self:flex-end;text-align:right;}
.msg.admin{background:var(--c2);border:1px solid var(--bor);align-self:flex-start;}
.mname{font-size:.62rem;color:var(--mu);margin-bottom:2px;font-weight:600;}
.treply{display:flex;gap:6px;margin-top:.75rem;}
.treply input{flex:1;background:#0a0a0a;border:1px solid var(--bor);color:var(--tx);padding:6px 9px;font-family:'Vazirmatn',sans-serif;font-size:.79rem;outline:none;}
.treply input:focus{border-color:var(--g);}
/* MC */
.mcav{width:55px;height:55px;background:var(--c2);border:2px solid var(--bor);display:flex;align-items:center;justify-content:center;font-size:1.5rem;}
.mclink{display:flex;align-items:center;gap:11px;flex-wrap:wrap;}
.mclf{display:flex;gap:7px;margin-top:.65rem;flex-wrap:wrap;}
.mclf input{flex:1;min-width:135px;background:#0a0a0a;border:1px solid var(--bor);color:var(--tx);padding:6px 9px;font-family:'Vazirmatn',sans-serif;font-size:.8rem;outline:none;}
.mclf input:focus{border-color:var(--g);}
.mcs{font-size:.68rem;padding:2px 8px;display:inline-block;font-weight:600;}
.mcs.on{background:rgba(91,191,58,.12);color:var(--g);border:1px solid rgba(91,191,58,.3);}
.mcs.off{background:rgba(231,76,60,.1);color:var(--red);border:1px solid rgba(231,76,60,.2);}
/* ADMIN */
.aw{max-width:1200px;margin:0 auto;padding:1.6rem 1.5rem 3.5rem;}
.atop{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.6rem;flex-wrap:wrap;gap:.7rem;}
.atop h1{font-size:1.25rem;font-weight:900;}
.atop h1 em{font-style:normal;color:var(--red);}
.abadge{background:rgba(231,76,60,.1);border:1px solid rgba(231,76,60,.3);color:var(--red);font-size:.65rem;font-weight:700;padding:2px 8px;letter-spacing:1px;}
.agrid{display:grid;grid-template-columns:195px 1fr;gap:13px;align-items:start;}
.ani{display:flex;align-items:center;gap:7px;padding:7px 11px;background:var(--card);border:1px solid transparent;font-size:.78rem;font-weight:500;cursor:pointer;transition:all .15s;color:var(--mu);margin-bottom:3px;}
.ani:hover{border-color:var(--bor);color:var(--tx);}
.ani.active{border-color:var(--red);color:var(--red);background:rgba(231,76,60,.06);}
.ap{display:none;}.ap.active{display:block;}
.ascards{display:grid;grid-template-columns:repeat(auto-fill,minmax(145px,1fr));gap:8px;margin-bottom:1.2rem;}
.asc{background:var(--card);border:1px solid var(--bor);padding:.85rem;}
.ascl{font-size:.63rem;color:var(--mu);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;}
.ascv{font-size:1.3rem;font-weight:900;}
.ag{color:var(--g);}.ago{color:var(--gold);}.ab{color:var(--dia);}.ar{color:var(--red);}
.at{width:100%;border-collapse:collapse;font-size:.73rem;}
.at th{text-align:right;padding:5px 8px;color:var(--mu);font-weight:600;border-bottom:1px solid var(--bor);font-size:.62rem;text-transform:uppercase;letter-spacing:.5px;}
.at td{padding:6px 8px;border-bottom:1px solid #1a1a1a;color:var(--tx);}
.at tr:hover td{background:rgba(255,255,255,.02);}
.at tr:last-child td{border-bottom:none;}
.ab2{background:none;border:1px solid var(--bor);color:var(--mu);font-family:'Vazirmatn',sans-serif;font-size:.65rem;padding:2px 7px;cursor:pointer;transition:all .15s;margin-left:2px;}
.ab2:hover{border-color:var(--g);color:var(--g);}
.ab2.d:hover{border-color:var(--red);color:var(--red);}
.ab2.gold:hover{border-color:var(--gold);color:var(--gold);}
.isrch{background:#0a0a0a;border:1px solid var(--bor);color:var(--tx);padding:5px 10px;font-family:'Vazirmatn',sans-serif;font-size:.77rem;outline:none;width:175px;}
.isrch:focus{border-color:var(--g);}
.tbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:.8rem;flex-wrap:wrap;gap:6px;}
.chart{display:flex;align-items:flex-end;gap:4px;height:90px;margin-top:.65rem;}
.cc{flex:1;display:flex;flex-direction:column;align-items:center;gap:3px;}
.cb2{width:100%;background:var(--g);opacity:.65;transition:opacity .2s;min-height:4px;}
.cc:hover .cb2{opacity:1;}
.cc span{font-size:.59rem;color:var(--mu);}
.odot{width:6px;height:6px;background:var(--g);display:inline-block;margin-left:5px;animation:blink 1.5s infinite;}
@keyframes blink{0%,100%{opacity:1;}50%{opacity:.3;}}
.tg{font-size:.65rem;padding:2px 6px;font-weight:600;}
.tg.g{background:rgba(91,191,58,.12);color:var(--g);}
.tg.r{background:rgba(231,76,60,.1);color:var(--red);}
.tg.o{background:rgba(245,200,66,.1);color:var(--gold);}
.tg.b{background:rgba(66,212,244,.1);color:var(--dia);}
.cdisp{background:#0a0a0a;border:1px solid var(--bor);padding:1.1rem;margin-bottom:.8rem;text-align:center;}
.cnum2{font-size:1.25rem;font-weight:900;color:var(--gold);letter-spacing:4px;font-family:monospace;}
.cown{font-size:.71rem;color:var(--mu);margin-top:4px;}
.psbadge{display:inline-flex;align-items:center;font-size:.72rem;font-weight:600;padding:2px 9px;}
.pson{background:rgba(91,191,58,.12);color:var(--g);border:1px solid rgba(91,191,58,.3);}
.psoff{background:rgba(231,76,60,.1);color:var(--red);border:1px solid rgba(231,76,60,.3);}
.pagrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(175px,1fr));gap:8px;}
.pac{background:var(--card);border:1px solid var(--bor);padding:.85rem;}
.pac h4{font-size:.79rem;font-weight:700;margin-bottom:3px;}
.pac p{font-size:.68rem;color:var(--mu);margin-bottom:4px;}
.pac .price{font-size:.85rem;font-weight:900;color:var(--g);margin-bottom:6px;}
/* RECEIPT VIEWER */
.rthumb{width:60px;height:40px;object-fit:cover;border:1px solid var(--bor);cursor:pointer;vertical-align:middle;}
/* AI CHAT BOT */
.aibot{position:fixed;bottom:1.5rem;left:1.5rem;z-index:900;}
.aibot-btn{width:52px;height:52px;background:var(--g);border:none;border-radius:0;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.5rem;clip-path:polygon(0 0,calc(100% - 6px) 0,100% 6px,100% 100%,6px 100%,0 calc(100% - 6px));box-shadow:0 4px 20px rgba(91,191,58,.4);transition:background .2s,transform .15s;}
.aibot-btn:hover{background:var(--gd);transform:scale(1.08);}
.aibot-notif{position:absolute;top:-4px;right:-4px;width:14px;height:14px;background:var(--red);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.55rem;color:#fff;font-weight:700;}
.aibot-win{position:absolute;bottom:62px;left:0;width:310px;background:#111;border:1px solid var(--bor);border-top:2px solid var(--g);display:none;flex-direction:column;box-shadow:0 8px 40px rgba(0,0,0,.6);}
.aibot-win.open{display:flex;}
.aibot-hdr{display:flex;align-items:center;justify-content:space-between;padding:.75rem 1rem;border-bottom:1px solid var(--bor);}
.aibot-hdr-l{display:flex;align-items:center;gap:8px;}
.aibot-av{width:30px;height:30px;background:var(--g);display:flex;align-items:center;justify-content:center;font-size:.9rem;}
.aibot-name{font-size:.82rem;font-weight:700;}
.aibot-status{font-size:.63rem;color:var(--g);}
.aibot-cls{background:none;border:none;color:var(--mu);font-size:1rem;cursor:pointer;}
.aibot-msgs{height:240px;overflow-y:auto;padding:.75rem;display:flex;flex-direction:column;gap:8px;}
.aibot-msg{padding:7px 10px;font-size:.77rem;line-height:1.55;max-width:88%;}
.aibot-msg.bot{background:var(--c2);border:1px solid var(--bor);align-self:flex-start;}
.aibot-msg.user{background:rgba(91,191,58,.1);border:1px solid rgba(91,191,58,.2);align-self:flex-end;text-align:right;}
.aibot-inp{display:flex;gap:6px;padding:.6rem;border-top:1px solid var(--bor);}
.aibot-inp input{flex:1;background:#0a0a0a;border:1px solid var(--bor);color:var(--tx);padding:6px 9px;font-family:'Vazirmatn',sans-serif;font-size:.79rem;outline:none;}
.aibot-inp input:focus{border-color:var(--g);}
.aibot-inp button{background:var(--g);color:#fff;border:none;padding:6px 11px;font-family:'Vazirmatn',sans-serif;font-size:.75rem;font-weight:700;cursor:pointer;}
.aibot-inp button:hover{background:var(--gd);}
.aibot-typing{display:flex;gap:4px;align-items:center;padding:8px 10px;}
.aibot-typing span{width:6px;height:6px;background:var(--mu);border-radius:50%;animation:typing .9s infinite;}
.aibot-typing span:nth-child(2){animation-delay:.2s;}
.aibot-typing span:nth-child(3){animation-delay:.4s;}
@keyframes typing{0%,80%,100%{opacity:.2;}40%{opacity:1;}}
.aibot-qs{display:flex;flex-wrap:wrap;gap:5px;padding:.5rem .75rem;border-bottom:1px solid var(--bor);}
.aibot-q{background:var(--card);border:1px solid var(--bor);color:var(--tx);font-family:'Vazirmatn',sans-serif;font-size:.68rem;padding:3px 8px;cursor:pointer;transition:all .15s;}
.aibot-q:hover{border-color:var(--g);color:var(--g);}
/* LOADER */
.loader{display:inline-block;width:14px;height:14px;border:2px solid rgba(91,191,58,.3);border-top-color:var(--g);border-radius:50%;animation:spin .7s linear infinite;vertical-align:middle;margin-left:5px;}
@keyframes spin{to{transform:rotate(360deg);}}
.empty-msg{text-align:center;color:var(--mu);padding:1.8rem;font-size:.8rem;}
footer{position:relative;z-index:5;border-top:1px solid var(--bor);text-align:center;padding:1.3rem;font-size:.71rem;color:var(--mu);}
footer strong{color:var(--g);}
@media(max-width:680px){nav{display:none;}.dgrid,.agrid{grid-template-columns:1fr;}.pr{grid-template-columns:1fr;}.fr{flex-direction:column;}.req-list{grid-template-columns:1fr;}.aibot{bottom:1rem;left:1rem;}.aibot-win{width:280px;bottom:58px;}}
</style>
</head>
<body>

<header>
  <div class="logo" onclick="goPage('shop')">
    <div class="logo-icon"><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div>
    <div class="logo-text"><em>9</em> Minecraft</div>
  </div>
  <nav>
    <a onclick="goPage('shop')" id="nv-shop" class="active">فروشگاه</a>
    <a onclick="goPage('dash')" id="nv-dash" style="display:none">داشبورد</a>
    <a onclick="goPage('support')" id="nv-sup">پشتیبانی</a>
    <a onclick="goPage('staff')" id="nv-staff">استاف شوید</a>
  </nav>
  <div class="hdr" id="hdr-g">
    <button class="btn btn-o" onclick="openM('login')">ورود</button>
    <button class="btn btn-g" onclick="openM('register')">ثبت‌نام</button>
  </div>
  <div class="hdr" id="hdr-u" style="display:none">
    <div class="uchip" onclick="goPage('dash')">
      <div class="uav" id="hdr-av">؟</div>
      <span style="font-size:.78rem;font-weight:600" id="hdr-un"></span>
    </div>
    <button class="btn btn-r" id="admin-btn" style="display:none" onclick="goPage('admin')">⚡ ادمین</button>
    <button class="btn btn-o" onclick="doLogout()">خروج</button>
  </div>
</header>

<!-- AUTH MODALS -->
<div class="ov" id="ov-auth" onclick="closeOv('ov-auth',event)">
  <div class="modal" id="m-login">
    <button class="mcls" onclick="closeAll()">✕</button>
    <h2>ورود به <em>9MC</em></h2><p class="sub">با اکانت سایت وارد شوید</p>
    <div class="aerr" id="lerr"></div>
    <div class="f"><label>نام کاربری</label><input id="lu" placeholder="username" onkeydown="if(event.key==='Enter')doLogin()"></div>
    <div class="f"><label>رمز عبور</label><input type="password" id="lp" placeholder="••••••••" onkeydown="if(event.key==='Enter')doLogin()"></div>
    <button class="btn btn-g" style="width:100%;padding:9px" id="login-btn" onclick="doLogin()">ورود</button>
    <div class="asw">حساب ندارید؟ <a onclick="openM('register')">ثبت‌نام</a></div>
  </div>
  <div class="modal" id="m-reg" style="display:none">
    <button class="mcls" onclick="closeAll()">✕</button>
    <h2>ثبت‌نام در <em>9MC</em></h2><p class="sub">یک اکانت رایگان بسازید</p>
    <div class="aerr" id="rerr"></div>
    <div class="fr"><div class="f"><label>نام</label><input id="rfn" placeholder="علی"></div><div class="f"><label>نام خانوادگی</label><input id="rln" placeholder="محمدی"></div></div>
    <div class="f"><label>نام کاربری</label><input id="ru" placeholder="فقط حرف انگلیسی، عدد، _"></div>
    <div class="f"><label>ایمیل</label><input type="email" id="re" placeholder="you@example.com"></div>
    <div class="f"><label>رمز عبور</label><input type="password" id="rp" placeholder="حداقل ۶ کاراکتر"></div>
    <button class="btn btn-g" style="width:100%;padding:9px" id="reg-btn" onclick="doRegister()">ساخت اکانت</button>
    <div class="asw">اکانت دارید؟ <a onclick="openM('login')">وارد شوید</a></div>
  </div>
</div>

<!-- PAYMENT MODAL -->
<div class="ov" id="ov-pay" onclick="closeOv('ov-pay',event)">
  <div class="modal" style="border-top-color:var(--gold);max-width:450px">
    <button class="mcls" onclick="closeAll()">✕</button>
    <h2 style="color:var(--gold)">💳 پرداخت کارت به کارت</h2>
    <p class="sub">مبلغ را واریز کرده و رسید را آپلود کنید</p>
    <div class="paycard">
      <div class="pcnum" id="pay-cnum">در حال بارگذاری...</div>
      <div class="pcname" id="pay-cname"></div>
      <div class="pcname" id="pay-cbk" style="font-size:.69rem;margin-top:2px"></div>
    </div>
    <div class="pamount">مبلغ: <span id="pay-amt">۰</span> تومان</div>
    <ol class="psteps"><li>مبلغ را به کارت بالا واریز کنید</li><li>کد پیگیری تراکنش را بگیرید</li><li>رسید یا اسکرین‌شات را آپلود کنید</li><li>کد پیگیری را وارد و تأیید کنید</li></ol>
    <div class="f" style="margin-top:.8rem"><label>شماره پیگیری / کد مرجع *</label><input id="pay-ref" placeholder="مثلاً: 987654321" dir="ltr" style="text-align:center"></div>
    <!-- RECEIPT UPLOAD -->
    <div style="margin-bottom:.8rem">
      <label style="font-size:.69rem;color:var(--mu);display:block;margin-bottom:4px;font-weight:500">آپلود رسید پرداخت (اختیاری اما توصیه می‌شود)</label>
      <div class="rbox" id="rbox" ondragover="this.classList.add('drag')" ondragleave="this.classList.remove('drag')" ondrop="handleDrop(event)">
        <input type="file" id="receipt-file" accept="image/*" onchange="handleReceiptFile(this)">
        <div class="rbox-icon">🖼️</div>
        <div class="rbox-txt">کلیک کنید یا تصویر را بکشید اینجا<br><span style="font-size:.65rem;color:var(--mu2)">JPG، PNG، WEBP — حداکثر ۵ مگابایت</span></div>
      </div>
      <div class="rpreview" id="rpreview"><img id="rimg" src="" alt="رسید"><div class="rupload-ok" id="rupload-ok">✓ رسید آپلود شد</div></div>
    </div>
    <div class="aerr" id="pay-err"></div>
    <button class="btn btn-gold" style="width:100%;padding:9px" id="pay-btn" onclick="confirmPay()">✓ تأیید پرداخت و ثبت سفارش</button>
    <div style="font-size:.69rem;color:var(--mu);margin-top:.65rem;text-align:center">پس از بررسی ادمین فعال می‌شود. معمولاً زیر ۳۰ دقیقه</div>
  </div>
</div>

<!-- TICKET MODAL -->
<div class="ov" id="ov-tkt" onclick="closeOv('ov-tkt',event)">
  <div class="modal" style="border-top-color:var(--dia)">
    <button class="mcls" onclick="closeAll()">✕</button>
    <h2 style="color:var(--dia)">🎧 تیکت جدید</h2><p class="sub">مشکل یا سوال خود را شرح دهید</p>
    <div class="aerr" id="tkt-err"></div>
    <div class="f"><label>موضوع</label><select id="tkt-cat"><option value="">انتخاب...</option><option>مشکل پرداخت</option><option>عدم دریافت رنک</option><option>مشکل فنی</option><option>رفع بن</option><option>سوال عمومی</option><option>سایر</option></select></div>
    <div class="f"><label>عنوان</label><input id="tkt-subj" placeholder="خلاصه مشکل"></div>
    <div class="f"><label>توضیحات</label><textarea id="tkt-body" placeholder="جزئیات..."></textarea></div>
    <button class="btn btn-g" style="width:100%;padding:9px" id="tkt-btn" onclick="submitTkt()">ارسال تیکت</button>
  </div>
</div>

<!-- AI CHATBOT -->
<div class="aibot">
  <div class="aibot-win" id="aibot-win">
    <div class="aibot-hdr">
      <div class="aibot-hdr-l">
        <div class="aibot-av">🤖</div>
        <div><div class="aibot-name">دستیار 9MC</div><div class="aibot-status">● آنلاین</div></div>
      </div>
      <button class="aibot-cls" onclick="toggleBot()">✕</button>
    </div>
    <div class="aibot-qs" id="aibot-qs">
      <button class="aibot-q" onclick="botQ('قیمت رنک VIP چقدره؟')">قیمت رنک‌ها</button>
      <button class="aibot-q" onclick="botQ('چطور پرداخت کنم؟')">پرداخت</button>
      <button class="aibot-q" onclick="botQ('آی‌پی سرور چیه؟')">آی‌پی سرور</button>
      <button class="aibot-q" onclick="botQ('چطور رنکم رو بگیرم؟')">دریافت رنک</button>
      <button class="aibot-q" onclick="botQ('چطور استاف بشم؟')">استاف شدن</button>
    </div>
    <div class="aibot-msgs" id="aibot-msgs"></div>
    <div class="aibot-inp">
      <input id="aibot-inp" placeholder="سوال بپرسید..." onkeydown="if(event.key==='Enter')botSend()">
      <button onclick="botSend()">ارسال</button>
    </div>
  </div>
  <button class="aibot-btn" onclick="toggleBot()" title="دستیار هوشمند">🤖<span class="aibot-notif">!</span></button>
</div>

<!-- ═══ SHOP ═══ -->
<div class="page active" id="page-shop">
  <section class="hero">
    <div class="hbadge">✦ فروشگاه رسمی سرور ✦</div>
    <h1>خرید آیتم‌های <em>9 Minecraft</em></h1>
    <p>رنک، کیت، کلید و آیتم‌های انحصاری برای تجربه‌ای بی‌نظیر</p>
    <div class="hip"><span>آی‌پی سرور:</span>9mc.ir</div>
    <div class="hbtns"><button class="btn btn-g" onclick="copyIP()">کپی آی‌پی</button><button class="btn btn-o" onclick="openM('register')">ثبت‌نام رایگان</button></div>
  </section>
  <div class="sbar">
    <div class="sv"><span class="svv">۱٬۲۴۸</span><span class="svl">آنلاین</span></div>
    <div class="sv"><span class="svv">+۴۵K</span><span class="svl">کاربر کل</span></div>
    <div class="sv"><span class="svv">۲۴/۷</span><span class="svl">آپتایم</span></div>
    <div class="sv"><span class="svv">۹۹.۸٪</span><span class="svl">آپتایم ماهانه</span></div>
  </div>
  <div class="wrap">
    <div class="notice">📢&nbsp; پس از تأیید پرداخت توسط ادمین، آیتم روی اکانت ماینکرفت شما اعمال می‌شود. یوزرنیم ماینکرفت خود را در داشبورد وارد کنید.</div>
    <div class="tabs">
      <button class="tab active" onclick="stab('ranks',this)">👑 رنک‌ها</button>
      <button class="tab" onclick="stab('items',this)">🎒 آیتم و کیت</button>
      <button class="tab" onclick="stab('keys',this)">🗝️ کریت</button>
    </div>
    <div class="tp active" id="tp-ranks">
      <div class="stl"><div class="dot"></div>رنک‌های VIP</div>
      <div class="ranks">
        <div class="rc r1"><div class="rn">[VIP]</div><ul class="rp"><li>پوسته‌های رنگی</li><li>کیت هفتگی VIP</li><li>x2 تجربه</li><li>۱۰۰۰ سکه</li></ul><span class="rprice">۲۵٬۰۰۰ تومان</span><button class="rbuy" onclick="openPay('رنک VIP',25000)">خرید</button></div>
        <div class="rc r2"><div class="rn">[VIP+]</div><ul class="rp"><li>همه مزایای VIP</li><li>کیت ویژه هفتگی</li><li>x3 تجربه</li><li>پرواز Lobby</li></ul><span class="rprice">۴۵٬۰۰۰ تومان</span><button class="rbuy" onclick="openPay('رنک VIP+',45000)">خرید</button></div>
        <div class="rc r3 pop"><div class="rn">[MVP]</div><ul class="rp"><li>همه مزایای VIP+</li><li>کمیک‌ها</li><li>x4 تجربه</li><li>۳ هوم اضافه</li><li>رنگ نام چت</li></ul><span class="rprice">۸۰٬۰۰۰ تومان</span><button class="rbuy" onclick="openPay('رنک MVP',80000)">خرید</button></div>
        <div class="rc r4"><div class="rn">[MVP+]</div><ul class="rp"><li>همه مزایای MVP</li><li>کیت MVP ماهانه</li><li>x5 تجربه</li><li>آیتم انحصاری</li><li>ذرات اختصاصی</li></ul><span class="rprice">۱۳۰٬۰۰۰ تومان</span><button class="rbuy" onclick="openPay('رنک MVP+',130000)">خرید</button></div>
        <div class="rc r5"><div class="rn">[LEGEND]</div><ul class="rp"><li>همه مزایای MVP+</li><li>تابلوی افتخار</li><li>x10 تجربه</li><li>کیت LEGEND</li><li>دسترسی آلفا</li><li>عنوان انحصاری</li></ul><span class="rprice">۲۲۰٬۰۰۰ تومان</span><button class="rbuy" onclick="openPay('رنک LEGEND',220000)">خرید</button></div>
      </div>
    </div>
    <div class="tp" id="tp-items">
      <div class="stl"><div class="dot"></div>آیتم‌ها و کیت‌ها</div>
      <div class="prods">
        <div class="pc"><span class="badge bh">پرفروش</span><div class="picon">🎒</div><div class="pname">کیت استارتر</div><div class="pdesc">ابزار فول آیرون، غذا و مواد اولیه</div><div class="pprice"><div><span class="pval"><span class="cur">تومان</span>۱۲٬۰۰۰</span></div><button class="bbt" onclick="openPay('کیت استارتر',12000)">خرید</button></div></div>
        <div class="pc"><span class="badge bn">جدید</span><div class="picon">⚔️</div><div class="pname">کیت نتراید</div><div class="pdesc">فول ست نتراید با انچانت حرفه‌ای</div><div class="pprice"><div><span class="pval"><span class="cur">تومان</span>۶۵٬۰۰۰</span></div><button class="bbt" onclick="openPay('کیت نتراید',65000)">خرید</button></div></div>
        <div class="pc"><span class="badge bs">تخفیف</span><div class="picon">💎</div><div class="pname">کیت الماس</div><div class="pdesc">ست کامل الماس با انچانت سطح بالا</div><div class="pprice"><div><span class="pold">۴۵٬۰۰۰</span><span class="pval"><span class="cur">تومان</span>۳۰٬۰۰۰</span></div><button class="bbt" onclick="openPay('کیت الماس',30000)">خرید</button></div></div>
        <div class="pc"><div class="picon">🔮</div><div class="pname">بسته الکسیر</div><div class="pdesc">۳۲ پوشن قدرت، سرعت و مقاومت</div><div class="pprice"><div><span class="pval"><span class="cur">تومان</span>۱۸٬۰۰۰</span></div><button class="bbt" onclick="openPay('بسته الکسیر',18000)">خرید</button></div></div>
        <div class="pc"><span class="badge bl">محدود</span><div class="picon">🏆</div><div class="pname">بسته سکه ×۵۰۰۰</div><div class="pdesc">۵۰۰۰ سکه درون‌سرور</div><div class="pprice"><div><span class="pval"><span class="cur">تومان</span>۲۵٬۰۰۰</span></div><button class="bbt" onclick="openPay('بسته سکه ×۵۰۰۰',25000)">خرید</button></div></div>
        <div class="pc"><div class="picon">⭐</div><div class="pname">بوستر x2 تجربه</div><div class="pdesc">دو برابر تجربه برای ۷۲ ساعت</div><div class="pprice"><div><span class="pval"><span class="cur">تومان</span>۲۰٬۰۰۰</span></div><button class="bbt" onclick="openPay('بوستر x2 تجربه',20000)">خرید</button></div></div>
      </div>
    </div>
    <div class="tp" id="tp-keys">
      <div class="stl"><div class="dot"></div>کلیدهای کریت</div>
      <div class="prods">
        <div class="pc"><div class="picon">🗝️</div><div class="pname">کلید کریت رایج</div><div class="pdesc">شانس آیتم رایج و ابزار</div><div class="pprice"><div><span class="pval"><span class="cur">تومان</span>۸٬۰۰۰</span></div><button class="bbt" onclick="openPay('کلید کریت رایج',8000)">خرید</button></div></div>
        <div class="pc"><span class="badge bh">پرفروش</span><div class="picon">🔑</div><div class="pname">کلید کریت نادر</div><div class="pdesc">شانس آیتم نادر و رنک تریال</div><div class="pprice"><div><span class="pval"><span class="cur">تومان</span>۱۵٬۰۰۰</span></div><button class="bbt" onclick="openPay('کلید کریت نادر',15000)">خرید</button></div></div>
        <div class="pc"><span class="badge bl">محدود</span><div class="picon">💠</div><div class="pname">کلید کریت افسانه‌ای</div><div class="pdesc">شانس ست نتراید و رنک VIP</div><div class="pprice"><div><span class="pval"><span class="cur">تومان</span>۳۵٬۰۰۰</span></div><button class="bbt" onclick="openPay('کلید کریت افسانه‌ای',35000)">خرید</button></div></div>
        <div class="pc"><span class="badge bn">جدید</span><div class="picon">🎁</div><div class="pname">بسته ۵ کلید رایج</div><div class="pdesc">۵ کلید رایج با ۱۵٪ تخفیف</div><div class="pprice"><div><span class="pold">۴۰٬۰۰۰</span><span class="pval"><span class="cur">تومان</span>۳۴٬۰۰۰</span></div><button class="bbt" onclick="openPay('بسته ۵ کلید',34000)">خرید</button></div></div>
      </div>
    </div>
  </div>
</div>

<!-- ═══ STAFF ═══ -->
<div class="page" id="page-staff">
  <div class="wrap" style="padding-top:2rem">
    <div class="staff-hero">
      <h2>به تیم <em>9 Minecraft</em> بپیوندید</h2>
      <p>آیا علاقه‌مند به مدیریت سرور، کمک به بازیکنان و رشد در یک تیم حرفه‌ای هستید؟ فرم استاف شدن را تکمیل کنید.</p>
      <a href="https://docs.google.com/forms/d/e/1FAIpQLScg_uW5qBTeP17cA4EzLwpBL5GPyYSN2AlfrYJDtWYzrHAU_Q/viewform?usp=header" target="_blank" class="staff-apply">📝 ثبت فرم درخواست استاف ←</a>
    </div>
    <div class="stl"><div class="dot"></div>موقعیت‌های شغلی</div>
    <div class="pos-grid">
      <div class="pos-card"><div class="pos-icon">🛡️</div><div class="pos-name">مادراتور</div><div class="pos-desc">مدیریت چت، رسیدگی به گزارش‌ها و حل تعارض بین بازیکنان</div></div>
      <div class="pos-card"><div class="pos-icon">🔧</div><div class="pos-name">ادمین فنی</div><div class="pos-desc">نگهداری سرور، رفع باگ و بهینه‌سازی عملکرد</div></div>
      <div class="pos-card"><div class="pos-icon">🎨</div><div class="pos-name">بیلدر</div><div class="pos-desc">طراحی و ساخت محیط‌های خلاقانه در سرور</div></div>
      <div class="pos-card"><div class="pos-icon">🎧</div><div class="pos-name">پشتیبانی</div><div class="pos-desc">پاسخ به تیکت‌ها و راهنمایی بازیکنان جدید</div></div>
      <div class="pos-card"><div class="pos-icon">📢</div><div class="pos-name">دیزاینر</div><div class="pos-desc">طراحی بنر و محتوای گرافیکی برای سرور</div></div>
      <div class="pos-card"><div class="pos-icon">🎬</div><div class="pos-name">یوتیوبر / استریمر</div><div class="pos-desc">تولید محتوا و معرفی سرور به مخاطبان گسترده</div></div>
    </div>
    <div class="req-grid">
      <h3>✅ شرایط عمومی</h3>
      <ul class="req-list">
        <li>حداقل ۱۴ سال سن</li><li>فعالیت ۱۰+ ساعت هفتگی</li>
        <li>توانایی کار تیمی</li><li>رفتار محترمانه</li>
        <li>عدم سابقه بن دائمی</li><li>آشنایی با قوانین سرور</li>
        <li>داشتن میکروفون</li><li>صبر و پشتکار</li>
      </ul>
    </div>
    <div style="text-align:center;padding:.5rem 0 2rem">
      <a href="https://docs.google.com/forms/d/e/1FAIpQLScg_uW5qBTeP17cA4EzLwpBL5GPyYSN2AlfrYJDtWYzrHAU_Q/viewform?usp=header" target="_blank" class="staff-apply">📝 ارسال فرم درخواست</a>
      <p style="color:var(--mu);font-size:.75rem;margin-top:.8rem">فرم در Google Forms باز می‌شود. پاسخ‌دهی تا ۷۲ ساعت</p>
    </div>
  </div>
</div>

<!-- ═══ SUPPORT ═══ -->
<div class="page" id="page-support">
  <div class="dw">
    <div class="dhdr"><h1>🎧 پشتیبانی <em>9MC</em></h1><button class="btn btn-g" onclick="openNewTkt()">+ تیکت جدید</button></div>
    <div class="scards" style="margin-bottom:1.3rem">
      <div class="sc"><div class="scl">پاسخ‌دهی</div><div class="scv" style="font-size:.85rem;margin-top:5px">زیر ۱ ساعت</div></div>
      <div class="sc"><div class="scl">ساعت کاری</div><div class="scv" style="font-size:.85rem;margin-top:5px">۸ صبح—۱۲ شب</div></div>
      <div class="sc"><div class="scl">تیکت‌های باز</div><div class="scv" id="sup-open">—</div></div>
    </div>
    <div class="cb"><h3>📋 تیکت‌های من</h3><div class="tlist" id="sup-tlist"><div class="empty-msg">برای مشاهده تیکت‌ها وارد شوید</div></div></div>
    <div class="cb" id="sup-detail" style="display:none">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:7px;margin-bottom:.75rem">
        <h3 id="sup-dtitle" style="border:none;padding:0;margin:0"></h3>
        <button class="btn btn-o" style="font-size:.72rem;padding:4px 9px" onclick="document.getElementById('sup-detail').style.display='none'">← برگشت</button>
      </div>
      <div class="tchat" id="sup-chat"></div>
      <div class="treply" id="sup-reply"><input id="sup-inp" placeholder="پیام..." onkeydown="if(event.key==='Enter')sendUserReply('sup')"><button class="btn btn-g" style="padding:6px 11px;font-size:.74rem" onclick="sendUserReply('sup')">ارسال</button></div>
    </div>
  </div>
</div>

<!-- ═══ DASHBOARD ═══ -->
<div class="page" id="page-dash">
  <div class="dw">
    <div class="dhdr"><h1>داشبورد <em id="dwel">کاربر</em></h1><button class="btn btn-o" onclick="goPage('shop')">← فروشگاه</button></div>
    <div class="dgrid">
      <div class="dsb">
        <div class="dni active" onclick="sdp('ov',this)">📊 خلاصه</div>
        <div class="dni" onclick="sdp('mc',this)">⛏️ ماینکرفت</div>
        <div class="dni" onclick="sdp('ord',this)">🛒 سفارشات</div>
        <div class="dni" onclick="sdp('tkt',this)">🎧 تیکت‌ها</div>
        <div class="dni" onclick="sdp('prf',this)">👤 پروفایل</div>
        <div class="dni" onclick="sdp('sec',this)">🔒 امنیت</div>
      </div>
      <div style="min-width:0">
        <div class="dp active" id="dp-ov">
          <div class="scards">
            <div class="sc"><div class="scl">سفارشات</div><div class="scv" id="d-oc">—</div></div>
            <div class="sc"><div class="scl">رنک فعلی</div><div class="scv" style="font-size:.82rem;margin-top:6px" id="d-rk">—</div></div>
            <div class="sc"><div class="scl">وضعیت MC</div><div class="scv" style="font-size:.77rem;margin-top:6px" id="d-mc">—</div></div>
            <div class="sc"><div class="scl">تیکت باز</div><div class="scv" id="d-tc">—</div></div>
          </div>
          <div class="cb"><h3>⚡ سفارشات اخیر</h3><div id="d-act" class="empty-msg">در حال بارگذاری...</div></div>
          <div class="cb"><h3>🎮 سرور</h3><div style="font-size:.78rem;line-height:2.3;color:var(--mu)"><div><span class="odot"></span>آنلاین — <strong style="color:var(--g)">۱٬۲۴۸</strong> بازیکن</div><div>آی‌پی: <strong style="color:var(--tx)">9mc.ir</strong></div><div>پورت: <strong style="color:var(--tx)">25565</strong></div><div>نسخه: <strong style="color:var(--tx)">1.20.x — 1.21.x</strong></div></div></div>
        </div>
        <div class="dp" id="dp-mc">
          <div class="cb"><h3>⛏️ اتصال اکانت ماینکرفت</h3>
            <div class="mclink">
              <div class="mcav" id="mc-av">🌍</div>
              <div><div style="font-size:.93rem;font-weight:700;margin-bottom:3px" id="mc-un">هنوز متصل نشده</div><span class="mcs off" id="mc-badge">⚠ متصل نیست</span><p style="font-size:.71rem;color:var(--mu);margin-top:6px;line-height:1.6">با اتصال اکانت، خریدها به‌صورت خودکار اعمال می‌شوند.</p></div>
            </div>
            <div class="mclf" id="mc-lf"><input id="mc-inp" placeholder="یوزرنیم ماینکرفت..."><button class="btn btn-g" style="padding:6px 12px" onclick="linkMC()">اتصال</button></div>
            <div id="mc-ua" style="display:none;margin-top:.65rem"><button class="btn btn-o" style="border-color:var(--red);color:var(--red);font-size:.76rem" onclick="unlinkMC()">قطع اتصال</button></div>
          </div>
        </div>
        <div class="dp" id="dp-ord">
          <div class="cb"><h3>🛒 تاریخچه سفارشات</h3>
            <table class="ot"><thead><tr><th>#</th><th>آیتم</th><th>مبلغ</th><th>پیگیری</th><th>رسید</th><th>تاریخ</th><th>وضعیت</th></tr></thead><tbody id="ord-tb"></tbody></table>
          </div>
        </div>
        <div class="dp" id="dp-tkt">
          <div class="cb"><h3>🎧 تیکت‌های من</h3>
            <div style="display:flex;justify-content:flex-end;margin-bottom:.75rem"><button class="btn btn-g" style="padding:5px 12px;font-size:.74rem" onclick="openNewTkt()">+ تیکت جدید</button></div>
            <div class="tlist" id="dash-tlist"></div>
            <div id="dash-tdetail" style="display:none;margin-top:.85rem">
              <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px;margin-bottom:.65rem">
                <strong id="dash-dtitle" style="font-size:.84rem"></strong>
                <button class="btn btn-o" style="font-size:.7rem;padding:3px 8px" onclick="document.getElementById('dash-tdetail').style.display='none'">← برگشت</button>
              </div>
              <div class="tchat" id="dash-chat"></div>
              <div class="treply"><input id="dash-inp" placeholder="پیام..." onkeydown="if(event.key==='Enter')sendUserReply('dash')"><button class="btn btn-g" style="padding:6px 10px;font-size:.72rem" onclick="sendUserReply('dash')">ارسال</button></div>
            </div>
          </div>
        </div>
        <div class="dp" id="dp-prf">
          <div class="cb"><h3>👤 ویرایش پروفایل</h3>
            <div class="nb" id="prf-nb">✓ ذخیره شد</div>
            <div class="pr"><div class="ff"><label>نام</label><input id="pf-fn"></div><div class="ff"><label>نام خانوادگی</label><input id="pf-ln"></div></div>
            <div class="pr"><div class="ff"><label>نام کاربری</label><input id="pf-u" readonly style="opacity:.5;cursor:not-allowed"></div><div class="ff"><label>ایمیل</label><input type="email" id="pf-e"></div></div>
            <div class="pr"><div class="ff"><label>شماره تماس</label><input type="tel" id="pf-ph" dir="ltr"></div><div class="ff"><label>استان</label><select id="pf-pv"><option value="">انتخاب...</option><option>تهران</option><option>اصفهان</option><option>مشهد</option><option>شیراز</option><option>تبریز</option><option>سایر</option></select></div></div>
            <button class="btn btn-g" style="padding:7px 18px;margin-top:.3rem" onclick="savePrf()">ذخیره</button>
          </div>
        </div>
        <div class="dp" id="dp-sec">
          <div class="cb"><h3>🔒 تغییر رمز عبور</h3>
            <div class="nb" id="sec-nb">✓ رمز تغییر یافت</div>
            <div class="ff" style="margin-bottom:8px"><label>رمز فعلی</label><input type="password" id="po"></div>
            <div class="ff" style="margin-bottom:8px"><label>رمز جدید</label><input type="password" id="pn"></div>
            <div class="ff" style="margin-bottom:11px"><label>تکرار رمز جدید</label><input type="password" id="pc2"></div>
            <div class="aerr" id="sec-err" style="margin-bottom:8px"></div>
            <button class="btn btn-g" style="padding:7px 18px" onclick="changePw()">تغییر رمز</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═══ ADMIN ═══ -->
<div class="page" id="page-admin">
  <div class="aw">
    <div class="atop">
      <div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap"><h1>پنل <em>ادمین</em></h1><span class="abadge">🔴 ادمین</span></div>
      <div style="display:flex;gap:6px;flex-wrap:wrap"><button class="btn btn-o" onclick="goPage('dash')">← داشبورد</button><button class="btn btn-o" onclick="goPage('shop')">← فروشگاه</button></div>
    </div>
    <div class="agrid">
      <div>
        <div class="ani active" onclick="sap('dash',this)">📊 داشبورد</div>
        <div class="ani" onclick="sap('users',this)">👥 کاربران</div>
        <div class="ani" onclick="sap('orders',this)">🛒 سفارشات</div>
        <div class="ani" onclick="sap('tickets',this)">🎧 تیکت‌ها</div>
        <div class="ani" onclick="sap('pay',this)">💳 تنظیم پرداخت</div>
        <div class="ani" onclick="sap('admins',this)">⚡ مدیران</div>
        <div class="ani" onclick="sap('staff',this)">📝 فرم‌های استاف</div>
        <div class="ani" onclick="sap('srv',this)">⚙️ سرور</div>
      </div>
      <div style="min-width:0">
        <div class="ap active" id="ap-dash">
          <div class="ascards">
            <div class="asc"><div class="ascl">کاربران</div><div class="ascv ab" id="a-users">—</div></div>
            <div class="asc"><div class="ascl">سفارشات</div><div class="ascv ago" id="a-orders">—</div></div>
            <div class="asc"><div class="ascl">تیکت باز</div><div class="ascv ar" id="a-tkts">—</div></div>
            <div class="asc"><div class="ascl">در انتظار</div><div class="ascv ago" id="a-pend">—</div></div>
            <div class="asc"><div class="ascl">MC متصل</div><div class="ascv ag" id="a-mc">—</div></div>
            <div class="asc"><div class="ascl">درآمد کل</div><div class="ascv ag" style="font-size:.95rem;margin-top:4px" id="a-rev">—</div></div>
          </div>
          <div class="cb"><h3>📈 فروش ۷ روز اخیر</h3><div class="chart" id="a-chart"></div></div>
          <div class="cb"><h3>📋 آخرین سفارشات</h3><table class="at"><thead><tr><th>#</th><th>کاربر</th><th>آیتم</th><th>مبلغ</th><th>رسید</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody id="a-rec"></tbody></table></div>
        </div>
        <div class="ap" id="ap-users">
          <div class="tbar"><input class="isrch" id="usrch" placeholder="جستجو..." oninput="loadAdminUsers()"><span style="font-size:.73rem;color:var(--mu)">مجموع: <strong id="ucnt">—</strong></span></div>
          <div class="cb" style="padding:0;overflow:auto"><table class="at"><thead><tr><th>#</th><th>کاربری</th><th>ایمیل</th><th>رنک</th><th>MC</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody id="a-utb"></tbody></table></div>
        </div>
        <div class="ap" id="ap-orders">
          <div class="tbar">
            <span style="font-weight:700;font-size:.82rem">سفارشات</span>
            <select id="ord-flt" onchange="loadAdminOrders()" style="background:#0a0a0a;border:1px solid var(--bor);color:var(--tx);padding:4px 8px;font-family:'Vazirmatn',sans-serif;font-size:.74rem;outline:none">
              <option value="all">همه</option><option value="pending">در انتظار</option><option value="done">تأیید شده</option><option value="rejected">رد شده</option>
            </select>
          </div>
          <div class="cb" style="padding:0;overflow:auto"><table class="at"><thead><tr><th>#</th><th>کاربر</th><th>آیتم</th><th>مبلغ</th><th>پیگیری</th><th>رسید</th><th>تاریخ</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody id="a-otb"></tbody></table></div>
        </div>
        <div class="ap" id="ap-tickets">
          <div class="tbar">
            <span style="font-weight:700;font-size:.82rem">تیکت‌ها</span>
            <select id="tkt-flt" onchange="loadAdminTickets()" style="background:#0a0a0a;border:1px solid var(--bor);color:var(--tx);padding:4px 8px;font-family:'Vazirmatn',sans-serif;font-size:.74rem;outline:none">
              <option value="all">همه</option><option value="open">باز</option><option value="pending">در انتظار</option><option value="closed">بسته</option>
            </select>
          </div>
          <div class="tlist" id="a-tlist"></div>
          <div class="cb" id="a-tdetail" style="display:none;margin-top:10px">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px;margin-bottom:.65rem">
              <strong id="a-dtitle" style="font-size:.84rem"></strong>
              <div style="display:flex;gap:4px;flex-wrap:wrap">
                <button class="ab2 gold" onclick="setTktSt('pending')">در انتظار</button>
                <button class="ab2" onclick="setTktSt('closed')">بستن</button>
                <button class="ab2" onclick="document.getElementById('a-tdetail').style.display='none'">← برگشت</button>
              </div>
            </div>
            <div class="tchat" id="a-chat"></div>
            <div class="treply"><input id="a-inp" placeholder="پاسخ ادمین..." onkeydown="if(event.key==='Enter')sendAdminReply()"><button class="btn" style="background:var(--red);color:#fff;padding:6px 10px;font-size:.72rem" onclick="sendAdminReply()">پاسخ</button></div>
          </div>
        </div>
        <div class="ap" id="ap-pay">
          <div class="cb"><h3>💳 تنظیم کارت پرداخت</h3>
            <div class="nb" id="pay-nb">✓ ذخیره شد</div>
            <div class="cdisp"><div class="cnum2" id="a-cnum">—</div><div class="cown" id="a-cown"></div><div class="cown" id="a-cbk" style="font-size:.68rem;margin-top:2px"></div></div>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:.9rem;flex-wrap:wrap">
              <span style="font-size:.73rem;color:var(--mu)">وضعیت:</span>
              <span class="psbadge" id="pay-sbadge">—</span>
              <button class="ab2" onclick="togglePayActive()">تغییر</button>
            </div>
            <div class="pr"><div class="ff"><label>شماره کارت (۱۶ رقم)</label><input id="a-card" maxlength="16" dir="ltr" placeholder="6219861012345678" oninput="prevCard()"></div><div class="ff"><label>نام صاحب کارت</label><input id="a-owner" placeholder="علی محمدی" oninput="prevCard()"></div></div>
            <div class="ff" style="margin-bottom:.9rem"><label>نام بانک</label><input id="a-bank" placeholder="بانک ملت..." oninput="prevCard()"></div>
            <button class="btn btn-g" style="padding:7px 18px" onclick="savePaySettings()">ذخیره</button>
          </div>
        </div>
        <div class="ap" id="ap-admins">
          <div class="cb"><h3>⚡ لیست مدیران</h3>
            <div class="nb" id="adm-nb">✓ ادمین اضافه شد</div>
            <table class="at" style="margin-bottom:1.2rem"><thead><tr><th>#</th><th>کاربری</th><th>ایمیل</th><th>سطح</th><th>عملیات</th></tr></thead><tbody id="adm-list"></tbody></table>
            <h3 style="font-size:.88rem;border-top:1px solid var(--bor);padding-top:.75rem;margin-bottom:.75rem">+ افزودن ادمین</h3>
            <div class="aerr" id="adm-err"></div>
            <div class="pr"><div class="ff"><label>نام</label><input id="na-fn" placeholder="نام"></div><div class="ff"><label>نام خانوادگی</label><input id="na-ln" placeholder="نام خانوادگی"></div></div>
            <div class="pr"><div class="ff"><label>نام کاربری</label><input id="na-u" placeholder="username"></div><div class="ff"><label>ایمیل</label><input type="email" id="na-e" placeholder="admin@9mc.ir"></div></div>
            <div class="pr"><div class="ff"><label>رمز عبور</label><input type="password" id="na-p" placeholder="حداقل ۶ کاراکتر"></div><div class="ff"><label>سطح</label><select id="na-lv"><option value="support">پشتیبانی</option><option value="moderator">مادراتور</option><option value="superadmin">سوپر ادمین</option></select></div></div>
            <button class="btn" style="background:var(--red);color:#fff;clip-path:polygon(0 0,calc(100% - 4px) 0,100% 4px,100% 100%,4px 100%,0 calc(100% - 4px));padding:7px 18px;margin-top:.3rem" onclick="addAdmin()">افزودن ادمین</button>
          </div>
        </div>
        <div class="ap" id="ap-staff">
          <div class="cb">
            <h3>📝 فرم‌های استاف</h3>
            <p style="font-size:.79rem;color:var(--mu);line-height:1.8;margin-bottom:1rem">پاسخ‌های فرم استاف در Google Forms ثبت می‌شوند. برای مشاهده:</p>
            <a href="https://docs.google.com/forms/d/e/1FAIpQLScg_uW5qBTeP17cA4EzLwpBL5GPyYSN2AlfrYJDtWYzrHAU_Q/viewform" target="_blank" class="btn btn-g" style="display:inline-block;text-decoration:none">مشاهده در Google Forms ←</a>
          </div>
        </div>
        <div class="ap" id="ap-srv">
          <div class="cb"><h3>⚙️ تنظیمات سرور</h3>
            <div class="nb" id="srv-nb">✓ ذخیره شد</div>
            <div class="pr"><div class="ff"><label>آی‌پی</label><input id="s-ip" value="9mc.ir" dir="ltr"></div><div class="ff"><label>پورت</label><input id="s-port" value="25565" dir="ltr"></div></div>
            <div class="pr"><div class="ff"><label>نسخه</label><input id="s-ver" value="1.20.x — 1.21.x"></div><div class="ff"><label>ظرفیت</label><input type="number" id="s-cap" value="2000"></div></div>
            <button class="btn btn-g" style="padding:7px 18px;margin-top:.3rem" onclick="showNb('srv-nb')">ذخیره</button>
          </div>
          <div class="cb"><h3>🔴 عملیات سرور</h3>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
              <button class="btn btn-o" onclick="alert('Restart ارسال شد ✓')">♻️ ری‌استارت</button>
              <button class="btn btn-o" style="border-color:var(--gold);color:var(--gold)" onclick="alert('بک‌آپ در حال ساخت...')">💾 بک‌آپ</button>
              <button class="btn btn-o" style="border-color:var(--red);color:var(--red)" onclick="if(confirm('مطمئنید؟'))alert('سرور خاموش شد')">⏻ خاموش</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<footer><strong>9 Minecraft</strong> &nbsp;·&nbsp; تمامی حقوق محفوظ است &nbsp;·&nbsp; ساخته شده با ❤️ برای جامعه ماینکرفت ایران</footer>

<script>
// ═══ CONFIG ═══
const API = 'index.php';

// ═══ API ═══
async function api(action, data={}) {
  try {
    const r = await fetch(API+'?a='+action, {
      method:'POST', credentials:'include',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({action, ...data})
    });
    return await r.json();
  } catch(e) { return {ok:false, msg:'خطا در اتصال: '+e.message}; }
}

// ═══ STATE ═══
let CU = null, payItem = {name:'',price:0}, uploadedReceipt = '', curTktId = null, admTktId = null;

// ═══ INIT ═══
(async()=>{ const r=await api('me'); if(r.ok){CU=r.data;afterLogin(false);} })();

// ═══ AUTH ═══
async function doLogin(){
  const u=document.getElementById('lu').value.trim(), p=document.getElementById('lp').value;
  setBL('login-btn',true);
  const r=await api('login',{u,pw:p});
  setBL('login-btn',false);
  if(!r.ok){showErr('lerr',r.msg);return;}
  CU=r.data; afterLogin(); closeAll();
}
async function doRegister(){
  setBL('reg-btn',true);
  const r=await api('register',{fn:document.getElementById('rfn').value.trim(),ln:document.getElementById('rln').value.trim(),u:document.getElementById('ru').value.trim(),email:document.getElementById('re').value.trim(),pw:document.getElementById('rp').value});
  setBL('reg-btn',false);
  if(!r.ok){showErr('rerr',r.msg);return;}
  CU=r.data; afterLogin(); closeAll();
}
async function doLogout(){
  await api('logout'); CU=null;
  document.getElementById('hdr-g').style.display='flex';
  document.getElementById('hdr-u').style.display='none';
  document.getElementById('nv-dash').style.display='none';
  document.getElementById('admin-btn').style.display='none';
  goPage('shop');
}
function afterLogin(nav=true){
  document.getElementById('hdr-g').style.display='none';
  document.getElementById('hdr-u').style.display='flex';
  document.getElementById('hdr-av').textContent=CU.fn?CU.fn[0]:CU.u[0];
  document.getElementById('hdr-un').textContent=CU.u;
  document.getElementById('nv-dash').style.display='';
  document.getElementById('admin-btn').style.display=CU.isAdmin?'':'none';
  if(nav) goPage('dash'); else loadDash();
}

// ═══ PAGES ═══
function goPage(p){
  document.querySelectorAll('.page').forEach(x=>x.classList.remove('active'));
  document.getElementById('page-'+p).classList.add('active');
  document.querySelectorAll('nav a').forEach(a=>a.classList.remove('active'));
  const m={shop:'nv-shop',dash:'nv-dash',support:'nv-sup',staff:'nv-staff'};
  if(m[p]) document.getElementById(m[p]).classList.add('active');
  if(p==='dash'&&CU) loadDash();
  if(p==='support') loadSupport();
  if(p==='admin') loadAdmin();
  window.scrollTo(0,0);
}
function stab(n,btn){
  document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
  document.querySelectorAll('.tp').forEach(p=>p.classList.remove('active'));
  btn.classList.add('active'); document.getElementById('tp-'+n).classList.add('active');
}

// ═══ PAYMENT ═══
async function openPay(name,price){
  if(!CU){openM('login');return;}
  payItem={name,price}; uploadedReceipt='';
  document.getElementById('pay-cnum').textContent='در حال بارگذاری...';
  document.getElementById('pay-ref').value='';
  document.getElementById('pay-err').classList.remove('show');
  document.getElementById('rpreview').style.display='none';
  document.getElementById('rupload-ok').style.display='none';
  document.getElementById('receipt-file').value='';
  document.getElementById('ov-pay').classList.add('open');
  const r=await api('get_payment_info');
  if(!r.ok){closeAll();alert(r.msg);return;}
  const ps=r.data;
  document.getElementById('pay-cnum').textContent=ps.card;
  document.getElementById('pay-cname').textContent='به نام: '+ps.owner;
  document.getElementById('pay-cbk').textContent=ps.bank;
  document.getElementById('pay-amt').textContent=price.toLocaleString('fa');
}
// RECEIPT UPLOAD
function handleReceiptFile(inp){
  const file=inp.files[0]; if(!file) return;
  const reader=new FileReader();
  reader.onload=e=>{ document.getElementById('rimg').src=e.target.result; document.getElementById('rpreview').style.display='block'; };
  reader.readAsDataURL(file);
  uploadReceipt(file);
}
function handleDrop(e){
  e.preventDefault(); document.getElementById('rbox').classList.remove('drag');
  const file=e.dataTransfer.files[0]; if(!file||!file.type.startsWith('image/'))return;
  const dt=new DataTransfer(); dt.items.add(file);
  document.getElementById('receipt-file').files=dt.files;
  handleReceiptFile(document.getElementById('receipt-file'));
}
async function uploadReceipt(file){
  const fd=new FormData(); fd.append('receipt',file);
  try{
    const r=await fetch(API,{method:'POST',credentials:'include',body:fd});
    const d=await r.json();
    if(d.ok){ uploadedReceipt=d.data.file; document.getElementById('rupload-ok').style.display='block'; }
    else alert('خطا در آپلود: '+d.msg);
  }catch(e){alert('خطا در آپلود');}
}
async function confirmPay(){
  const ref=document.getElementById('pay-ref').value.trim();
  if(!ref){showErr('pay-err','شماره پیگیری الزامی است');return;}
  setBL('pay-btn',true);
  const r=await api('create_order',{item:payItem.name,price:payItem.price,ref,receipt:uploadedReceipt});
  setBL('pay-btn',false);
  if(!r.ok){showErr('pay-err',r.msg);return;}
  closeAll();
  alert('✓ سفارش ثبت شد!\n\nپیگیری: '+ref+(uploadedReceipt?'\nرسید: آپلود شد ✓':'')+'\n\nپس از تأیید ادمین فعال می‌شود.');
  loadDash();
}

// ═══ DASHBOARD ═══
async function loadDash(){
  if(!CU)return;
  const r=await api('me'); if(r.ok) CU=r.data;
  document.getElementById('dwel').textContent=CU.fn||CU.u;
  document.getElementById('d-rk').textContent=CU.rank||'—';
  const mc=!!CU.mc;
  document.getElementById('d-mc').textContent=mc?CU.mc:'متصل نیست';
  document.getElementById('d-mc').style.color=mc?'var(--g)':'var(--red)';
  document.getElementById('mc-un').textContent=mc?CU.mc:'هنوز متصل نشده';
  document.getElementById('mc-av').textContent=mc?'⛏️':'🌍';
  const b=document.getElementById('mc-badge');b.textContent=mc?'✓ متصل':'⚠ متصل نیست';b.className='mcs '+(mc?'on':'off');
  document.getElementById('mc-lf').style.display=mc?'none':'flex';
  document.getElementById('mc-ua').style.display=mc?'block':'none';
  document.getElementById('pf-fn').value=CU.fn||''; document.getElementById('pf-ln').value=CU.ln||'';
  document.getElementById('pf-u').value=CU.u; document.getElementById('pf-e').value=CU.email||'';
  document.getElementById('pf-ph').value=CU.ph||''; document.getElementById('pf-pv').value=CU.pv||'';
  const or=await api('my_orders');
  if(or.ok){
    document.getElementById('d-oc').textContent=toFa(or.data.length);
    document.getElementById('ord-tb').innerHTML=or.data.length?or.data.map((o,i)=>`<tr><td>#${i+1}</td><td>${o.item}</td><td>${o.price.toLocaleString('fa')} ت</td><td style="direction:ltr;font-size:.69rem">${o.ref}</td><td>${o.receipt?`<img src="${API}?a=receipt_img&file=${encodeURIComponent(o.receipt)}" class="rthumb" onclick="window.open(this.src)" alt="رسید">`:'—'}</td><td style="font-size:.69rem">${o.date}</td><td><span class="os ${o.st==='done'?'done':o.st==='rejected'?'rej':'pend'}">${stLbl(o.st)}</span></td></tr>`).join(''):'<tr><td colspan="7" class="empty-msg">هنوز سفارشی ثبت نشده</td></tr>';
    document.getElementById('d-act').innerHTML=or.data.length?or.data.slice(0,4).map(o=>`<div style="font-size:.77rem;padding:4px 0;border-bottom:1px solid #1e1e1e;line-height:2">🛒 <strong>${o.item}</strong> — ${o.price.toLocaleString('fa')} ت <span class="os ${o.st==='done'?'done':o.st==='rejected'?'rej':'pend'}" style="margin-right:5px">${stLbl(o.st)}</span></div>`).join(''):'<div class="empty-msg">هنوز فعالیتی ثبت نشده</div>';
  }
  const tr=await api('my_tickets');
  if(tr.ok){ document.getElementById('d-tc').textContent=toFa(tr.data.filter(t=>t.st!=='closed').length); renderTktList(tr.data,'dash-tlist','openDashTkt'); }
}
function sdp(n,el){ document.querySelectorAll('.dni').forEach(x=>x.classList.remove('active')); document.querySelectorAll('.dp').forEach(x=>x.classList.remove('active')); el.classList.add('active'); document.getElementById('dp-'+n).classList.add('active'); }
function stLbl(s){return s==='done'?'✓ تأیید':s==='rejected'?'✕ رد شد':'⏳ در انتظار';}
async function linkMC(){ const mc=document.getElementById('mc-inp').value.trim(); if(!mc)return; const r=await api('link_mc',{mc}); if(r.ok){CU.mc=mc;loadDash();}else alert(r.msg); }
async function unlinkMC(){ if(!confirm('اتصال قطع شود؟'))return; const r=await api('unlink_mc'); if(r.ok){CU.mc='';loadDash();} }
async function savePrf(){ const r=await api('update_profile',{fn:document.getElementById('pf-fn').value,ln:document.getElementById('pf-ln').value,email:document.getElementById('pf-e').value,ph:document.getElementById('pf-ph').value,pv:document.getElementById('pf-pv').value}); if(r.ok){CU=r.data;document.getElementById('hdr-av').textContent=CU.fn?CU.fn[0]:CU.u[0];showNb('prf-nb');}else alert(r.msg); }
async function changePw(){ const old=document.getElementById('po').value,nw=document.getElementById('pn').value,cf=document.getElementById('pc2').value; if(nw!==cf){showErr('sec-err','رمزها مطابقت ندارند');return;} const r=await api('change_pw',{old,new:nw,cf}); if(r.ok){document.getElementById('sec-err').classList.remove('show');['po','pn','pc2'].forEach(id=>document.getElementById(id).value='');showNb('sec-nb');}else showErr('sec-err',r.msg); }

// ═══ TICKETS ═══
function openNewTkt(){if(!CU){openM('login');return;}document.getElementById('ov-tkt').classList.add('open');}
async function submitTkt(){
  const cat=document.getElementById('tkt-cat').value,subj=document.getElementById('tkt-subj').value.trim(),body=document.getElementById('tkt-body').value.trim();
  if(!cat||!subj||!body){showErr('tkt-err','همه فیلدها الزامی است');return;}
  setBL('tkt-btn',true); const r=await api('create_ticket',{cat,subj,body}); setBL('tkt-btn',false);
  if(!r.ok){showErr('tkt-err',r.msg);return;}
  closeAll(); loadDash(); loadSupport(); alert('✓ تیکت ارسال شد.');
}
function renderTktList(tkts,elId,fn){
  const el=document.getElementById(elId); if(!el)return;
  const cl={open:'open',pending:'pend',closed:'closed'},lb={open:'باز',pending:'در انتظار',closed:'بسته'};
  el.innerHTML=tkts.length?tkts.map(t=>{const last=t.msgs[t.msgs.length-1];return`<div class="ti" onclick="${fn}(${t.id})"><div class="tih"><span class="tit">${t.subj}</span><span class="ts ${cl[t.st]||'open'}">${lb[t.st]||t.st}</span></div><div class="tim">${t.cat} · ${t.date}</div><div class="tprev">${last?last.text:''}</div></div>`}).join(''):'<div class="empty-msg">تیکتی وجود ندارد</div>';
}
function renderChat(msgs,elId){ const el=document.getElementById(elId);if(!el)return; el.innerHTML=msgs.map(m=>`<div class="msg ${m.from}"><div class="mname">${m.name} · ${m.time}</div>${m.text}</div>`).join(''); el.scrollTop=el.scrollHeight; }
async function openDashTkt(id){ curTktId=id; const r=await api('get_ticket',{id});if(!r.ok)return;const t=r.data; document.getElementById('dash-tdetail').style.display='block'; document.getElementById('dash-dtitle').textContent=t.subj; renderChat(t.msgs,'dash-chat'); }
async function openSupTkt(id){ curTktId=id; const r=await api('get_ticket',{id});if(!r.ok)return;const t=r.data; document.getElementById('sup-detail').style.display='block'; document.getElementById('sup-dtitle').textContent=t.subj+' ('+t.cat+')'; document.getElementById('sup-reply').style.display=t.st==='closed'?'none':'flex'; renderChat(t.msgs,'sup-chat'); document.getElementById('sup-detail').scrollIntoView({behavior:'smooth'}); }
async function sendUserReply(src){ const inp=src==='sup'?'sup-inp':'dash-inp',chatId=src==='sup'?'sup-chat':'dash-chat'; const text=document.getElementById(inp).value.trim(); if(!text||!curTktId)return; const r=await api('ticket_reply',{id:curTktId,text}); if(r.ok){document.getElementById(inp).value='';renderChat(r.data.msgs,chatId);loadDash();if(src==='sup')loadSupport();} }
async function loadSupport(){ if(!CU){document.getElementById('sup-tlist').innerHTML='<div class="empty-msg">برای مشاهده تیکت‌ها وارد شوید</div>';document.getElementById('sup-open').textContent='—';return;} const r=await api('my_tickets');if(!r.ok)return; document.getElementById('sup-open').textContent=toFa(r.data.filter(t=>t.st!=='closed').length); renderTktList(r.data,'sup-tlist','openSupTkt'); }

// ═══ ADMIN ═══
async function loadAdmin(){
  const r=await api('admin_stats');if(!r.ok)return;const d=r.data;
  document.getElementById('a-users').textContent=toFa(d.users||0);
  document.getElementById('a-orders').textContent=toFa(d.orders||0);
  document.getElementById('a-tkts').textContent=toFa(d.open_tickets||0);
  document.getElementById('a-pend').textContent=toFa(d.pending_orders||0);
  document.getElementById('a-mc').textContent=toFa(d.mc_linked||0);
  document.getElementById('a-rev').textContent=(d.total_revenue||0).toLocaleString('fa')+' ت';
  const vals=[320,580,210,740,890,450,d.total_revenue?Math.floor(d.total_revenue/1000):1235],days=['ش','ی','د','س','چ','پ','ج'],mx=Math.max(...vals);
  document.getElementById('a-chart').innerHTML=vals.map((v,i)=>`<div class="cc"><div class="cb2" style="height:${Math.round(v/mx*100)}px" title="${v}"></div><span>${days[i]}</span></div>`).join('');
  await loadAdminOrders();await loadAdminUsers();await loadAdminTickets();await loadPayUI();await loadAdminList();
  const or2=await api('admin_all_orders',{filter:'all'});
  if(or2.ok){ const rec=or2.data.slice(0,6); document.getElementById('a-rec').innerHTML=rec.length?rec.map((o,i)=>`<tr><td>#${i+1}</td><td>${o.user}</td><td>${o.item}</td><td>${o.price.toLocaleString('fa')} ت</td><td>${o.receipt?`<img src="${API}?a=receipt_img&file=${encodeURIComponent(o.receipt)}" class="rthumb" onclick="window.open(this.src)" alt="رسید">`:'-'}</td><td><span class="tg ${o.st==='done'?'g':o.st==='rejected'?'r':'o'}">${stLbl(o.st)}</span></td><td>${o.st==='pending'?`<button class="ab2" onclick="orderAct(${o.id},'done')">✓</button><button class="ab2 d" onclick="orderAct(${o.id},'rejected')">✕</button>`:'—'}</td></tr>`).join(''):'<tr><td colspan="7" class="empty-msg">سفارشی ثبت نشده</td></tr>'; }
}
function sap(n,el){ document.querySelectorAll('.ani').forEach(x=>x.classList.remove('active')); document.querySelectorAll('.ap').forEach(x=>x.classList.remove('active')); el.classList.add('active'); document.getElementById('ap-'+n).classList.add('active'); }
async function loadAdminUsers(){ const q=document.getElementById('usrch')?.value||''; const r=await api('admin_users',{q});if(!r.ok)return; document.getElementById('ucnt').textContent=r.data.length; const lv={support:'پشتیبانی',moderator:'مادراتور',superadmin:'سوپر ادمین'}; document.getElementById('a-utb').innerHTML=r.data.length?r.data.map((u,i)=>`<tr><td>${i+1}</td><td><strong>${u.u}</strong>${u.isAdmin?` <span class="tg r">${lv[u.lvl]||'ادمین'}</span>`:''}</td><td style="color:var(--mu);font-size:.7rem">${u.email}</td><td><span class="tg ${u.rank!=='—'?'g':'r'}">${u.rank}</span></td><td>${u.mc?`<span class="tg b">${u.mc}</span>`:'—'}</td><td><span class="tg ${u.banned?'r':'g'}">${u.banned?'بن':'فعال'}</span></td><td><button class="ab2" onclick="setRank(${u.id})">رنک</button><button class="ab2 d" onclick="banUser(${u.id},${!u.banned})">${u.banned?'رفع بن':'بن'}</button></td></tr>`).join(''):'<tr><td colspan="7" class="empty-msg">یافت نشد</td></tr>'; }
async function setRank(uid){ const v=prompt('رنک جدید (VIP/VIP+/MVP/MVP+/LEGEND/—):');if(v===null)return; const r=await api('admin_set_rank',{uid,rank:v.trim()});if(r.ok)loadAdminUsers();else alert(r.msg); }
async function banUser(uid,ban){ if(!confirm(ban?'بن شود؟':'رفع بن شود؟'))return; const r=await api('admin_ban_user',{uid,ban});if(r.ok)loadAdminUsers();else alert(r.msg); }
async function loadAdminOrders(){ const f=document.getElementById('ord-flt')?.value||'all'; const r=await api('admin_all_orders',{filter:f});if(!r.ok)return; document.getElementById('a-otb').innerHTML=r.data.length?r.data.map((o,i)=>`<tr><td>#${i+1}</td><td>${o.user}</td><td>${o.item}</td><td>${o.price.toLocaleString('fa')} ت</td><td style="direction:ltr;font-size:.68rem">${o.ref||'—'}</td><td>${o.receipt?`<img src="${API}?a=receipt_img&file=${encodeURIComponent(o.receipt)}" class="rthumb" onclick="window.open(this.src)" alt="رسید">`:'-'}</td><td style="font-size:.68rem">${o.date}</td><td><span class="tg ${o.st==='done'?'g':o.st==='rejected'?'r':'o'}">${stLbl(o.st)}</span></td><td>${o.st==='pending'?`<button class="ab2" onclick="orderAct(${o.id},'done')">✓ تأیید</button><button class="ab2 d" onclick="orderAct(${o.id},'rejected')">✕ رد</button>`:'—'}</td></tr>`).join(''):'<tr><td colspan="9" class="empty-msg">یافت نشد</td></tr>'; }
async function orderAct(id,act){ const r=await api('admin_order_action',{id,act});if(r.ok)loadAdmin();else alert(r.msg); }
async function loadAdminTickets(){ const f=document.getElementById('tkt-flt')?.value||'all'; const r=await api('admin_all_tickets',{filter:f});if(!r.ok)return; const cl={open:'open',pending:'pend',closed:'closed'},lb={open:'باز',pending:'در انتظار',closed:'بسته'}; const el=document.getElementById('a-tlist'); el.innerHTML=r.data.length?r.data.map(t=>{const last=t.msgs[t.msgs.length-1];return`<div class="ti" onclick="openAdmTkt(${t.id})"><div class="tih"><span class="tit">${t.subj} <span style="color:var(--mu);font-size:.7rem">(${t.uname})</span></span><span class="ts ${cl[t.st]||'open'}">${lb[t.st]||t.st}</span></div><div class="tim">${t.cat} · ${t.date}</div><div class="tprev">${last?last.text:''}</div></div>`}).join(''):'<div class="empty-msg">تیکتی یافت نشد</div>'; }
async function openAdmTkt(id){ admTktId=id; const r=await api('get_ticket',{id});if(!r.ok)return;const t=r.data; document.getElementById('a-tdetail').style.display='block'; document.getElementById('a-dtitle').textContent=`[${t.cat}] ${t.subj} — ${t.uname}`; renderChat(t.msgs,'a-chat'); document.getElementById('a-tdetail').scrollIntoView({behavior:'smooth'}); }
async function sendAdminReply(){ const text=document.getElementById('a-inp').value.trim();if(!text||!admTktId)return; const r=await api('admin_ticket_reply',{id:admTktId,text});if(r.ok){document.getElementById('a-inp').value='';renderChat(r.data.msgs,'a-chat');loadAdminTickets();} }
async function setTktSt(st){ if(!admTktId)return; const r=await api('admin_ticket_status',{id:admTktId,st});if(r.ok){loadAdminTickets();if(st==='closed')document.getElementById('a-tdetail').style.display='none';} }
async function loadPayUI(){ const r=await api('get_payment_info'); if(r.ok){document.getElementById('a-cnum').textContent=r.data.card||'—';document.getElementById('a-cown').textContent=r.data.owner?'به نام: '+r.data.owner:'';document.getElementById('a-cbk').textContent=r.data.bank||'';document.getElementById('a-card').value=r.data.card?.replace(/-/g,'')||'';document.getElementById('a-owner').value=r.data.owner||'';document.getElementById('a-bank').value=r.data.bank||'';const b=document.getElementById('pay-sbadge');b.textContent='✓ فعال';b.className='psbadge pson';} }
function prevCard(){ const raw=document.getElementById('a-card').value,fmt=raw.length===16?raw.replace(/(\d{4})(\d{4})(\d{4})(\d{4})/,'$1-$2-$3-$4'):raw; document.getElementById('a-cnum').textContent=fmt||'—'; document.getElementById('a-cown').textContent='به نام: '+(document.getElementById('a-owner').value||'—'); document.getElementById('a-cbk').textContent=document.getElementById('a-bank').value||''; }
async function savePaySettings(){ const card=document.getElementById('a-card').value.trim(),owner=document.getElementById('a-owner').value.trim(),bank=document.getElementById('a-bank').value.trim(),active=document.getElementById('pay-sbadge').classList.contains('pson'); const r=await api('admin_pay_settings',{card,owner,bank,active});if(r.ok)showNb('pay-nb');else alert(r.msg); }
async function togglePayActive(){ const b=document.getElementById('pay-sbadge'),active=b.classList.contains('pson'),card=document.getElementById('a-card').value.trim(),owner=document.getElementById('a-owner').value.trim(),bank=document.getElementById('a-bank').value.trim(); const r=await api('admin_pay_settings',{card,owner,bank,active:!active});if(r.ok){b.textContent=!active?'✓ فعال':'✕ غیرفعال';b.className='psbadge '+(!active?'pson':'psoff');} }
async function loadAdminList(){ const r=await api('admin_users',{q:''});if(!r.ok)return; const lv={support:'پشتیبانی',moderator:'مادراتور',superadmin:'سوپر ادمین'}; const admins=r.data.filter(u=>u.isAdmin); document.getElementById('adm-list').innerHTML=admins.map((u,i)=>`<tr><td>${i+1}</td><td><strong>${u.u}</strong></td><td style="color:var(--mu);font-size:.71rem">${u.email}</td><td><span class="tg r">${lv[u.lvl]||'ادمین'}</span></td><td>${u.id===1?'<span style="color:var(--mu);font-size:.69rem">پیش‌فرض</span>':`<button class="ab2 d" onclick="removeAdmin(${u.id})">حذف</button>`}</td></tr>`).join(''); }
async function addAdmin(){ const r=await api('admin_add_admin',{fn:document.getElementById('na-fn').value.trim(),ln:document.getElementById('na-ln').value.trim(),u:document.getElementById('na-u').value.trim(),email:document.getElementById('na-e').value.trim(),pw:document.getElementById('na-p').value,lv:document.getElementById('na-lv').value}); if(r.ok){['na-fn','na-ln','na-u','na-e','na-p'].forEach(id=>document.getElementById(id).value='');showNb('adm-nb');loadAdminList();loadAdminUsers();}else showErr('adm-err',r.msg); }
async function removeAdmin(uid){ if(!confirm('حذف شود؟'))return; const r=await api('admin_remove_admin',{uid});if(r.ok){loadAdminList();loadAdminUsers();}else alert(r.msg); }

// ═══ AI CHATBOT ═══
const KB = [
  {k:['آی‌پی','ip','سرور'],a:'آی‌پی سرور 9 Minecraft:\n🌐 <strong>9mc.ir</strong>\nپورت پیش‌فرض: 25565\nنسخه: 1.20.x — 1.21.x'},
  {k:['vip','رنک','قیمت','خرید'],a:'رنک‌های سرور و قیمت‌ها:\n⭐ VIP — 25,000 تومان\n⭐ VIP+ — 45,000 تومان\n🏆 MVP — 80,000 تومان\n💎 MVP+ — 130,000 تومان\n👑 LEGEND — 220,000 تومان'},
  {k:['پرداخت','واریز','کارت'],a:'روش پرداخت کارت به کارت:\n1️⃣ محصول را انتخاب کنید\n2️⃣ مبلغ را به شماره کارت واریز کنید\n3️⃣ کد پیگیری و رسید را ثبت کنید\n4️⃣ پس از تأیید ادمین (زیر 30 دقیقه) فعال می‌شود'},
  {k:['رنک','دریافت','اعمال','نشد'],a:'برای دریافت رنک:\n1️⃣ از داشبورد، یوزرنیم ماینکرفت خود را وارد کنید\n2️⃣ پس از تأیید پرداخت، رنک اعمال می‌شود\n3️⃣ اگر مشکل داشتید تیکت ارسال کنید'},
  {k:['استاف','اپلای','تیم','عضو'],a:'برای استاف شدن:\n📝 صفحه «استاف شوید» را باز کنید\n✅ شرایط را بخوانید\n🔗 فرم Google Forms را پر کنید\nپاسخ‌دهی تا 72 ساعت'},
  {k:['تیکت','پشتیبانی','مشکل'],a:'برای ارتباط با پشتیبانی:\n🎧 صفحه «پشتیبانی» را باز کنید\n+ روی «تیکت جدید» بزنید\n✍️ موضوع و توضیحات را بنویسید\nپاسخ زیر 1 ساعت'},
  {k:['بن','اکانت','مسدود'],a:'اگر اکانت شما بن شده:\n🎧 یک تیکت با موضوع «رفع بن» ارسال کنید\n📝 دلیل را توضیح دهید\nتیم ما بررسی می‌کند'},
  {k:['کیت','آیتم','سلاح'],a:'کیت‌های موجود:\n🎒 کیت استارتر — 12,000 ت\n⚔️ کیت نتراید — 65,000 ت\n💎 کیت الماس — 30,000 ت\n🔮 بسته الکسیر — 18,000 ت'},
  {k:['کریت','کلید','جوایز'],a:'کلیدهای کریت:\n🗝️ کریت رایج — 8,000 ت\n🔑 کریت نادر — 15,000 ت\n💠 کریت افسانه‌ای — 35,000 ت\n🎁 بسته 5 کلید — 34,000 ت'},
  {k:['ساعت','کاری','پشتیبانی'],a:'ساعت کاری پشتیبانی:\n🕗 8 صبح تا 12 شب\n⚡ پاسخ تیکت: زیر 1 ساعت\n📞 هر روز هفته'},
];
let botOpen=false;
function toggleBot(){ botOpen=!botOpen; document.getElementById('aibot-win').classList.toggle('open',botOpen); if(botOpen){document.querySelector('.aibot-notif').style.display='none'; const msgs=document.getElementById('aibot-msgs'); if(!msgs.children.length) botMsg('سلام! 👋 من دستیار هوشمند 9MC هستم. می‌توانم به سوالات شما در مورد سرور، خرید، پرداخت و استاف شدن پاسخ دهم. چه کمکی می‌توانم بکنم؟');} }
function botMsg(text){
  const msgs=document.getElementById('aibot-msgs');
  const el=document.createElement('div'); el.className='aibot-msg bot'; el.innerHTML=text; msgs.appendChild(el); msgs.scrollTop=msgs.scrollHeight;
}
function botUserMsg(text){
  const msgs=document.getElementById('aibot-msgs');
  const el=document.createElement('div'); el.className='aibot-msg user'; el.textContent=text; msgs.appendChild(el); msgs.scrollTop=msgs.scrollHeight;
}
async function botSend(){
  const inp=document.getElementById('aibot-inp'),text=inp.value.trim();if(!text)return;
  inp.value=''; botUserMsg(text);
  // typing indicator
  const msgs=document.getElementById('aibot-msgs');
  const typing=document.createElement('div'); typing.className='aibot-typing'; typing.innerHTML='<span></span><span></span><span></span>'; msgs.appendChild(typing); msgs.scrollTop=msgs.scrollHeight;
  await new Promise(r=>setTimeout(r,600+Math.random()*800));
  typing.remove();
  const answer=findAnswer(text.toLowerCase());
  botMsg(answer);
}
function botQ(q){ if(!botOpen)toggleBot(); document.getElementById('aibot-inp').value=q; botSend(); }
function findAnswer(text){
  for(const item of KB){ if(item.k.some(k=>text.includes(k))) return item.a; }
  // اگر جواب نداشت از Anthropic API استفاده می‌کند
  callAI(text);
  return 'در حال فکر کردن... 🤔';
}
async function callAI(text){
  try{
    const r=await fetch('https://api.anthropic.com/v1/messages',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({
        model:'claude-sonnet-4-20250514',max_tokens:200,
        messages:[{role:'user',content:`تو دستیار پشتیبانی سرور ماینکرفت "9 Minecraft" هستی. سرور آی‌پی 9mc.ir دارد و رنک‌های VIP تا LEGEND می‌فروشد. پرداخت کارت‌به‌کارت است. کوتاه و مفید پاسخ بده به فارسی: ${text}`}]
      })
    });
    const d=await r.json();
    const ans=d.content?.[0]?.text;
    if(ans){ const msgs=document.getElementById('aibot-msgs'); const last=msgs.lastElementChild; if(last&&last.textContent.includes('در حال فکر کردن')) last.innerHTML=ans; else botMsg(ans); }
  }catch(e){
    const msgs=document.getElementById('aibot-msgs'); const last=msgs.lastElementChild;
    if(last&&last.textContent.includes('در حال فکر کردن')) last.innerHTML='متأسفم، الان نمی‌توانم پاسخ دهم. لطفاً تیکت ارسال کنید 🎧';
  }
}

// ═══ MODALS ═══
function openM(type){ document.getElementById('ov-auth').classList.add('open'); document.getElementById('m-login').style.display=type==='login'?'block':'none'; document.getElementById('m-reg').style.display=type==='register'?'block':'none'; document.getElementById('lerr').classList.remove('show'); document.getElementById('rerr').classList.remove('show'); }
function closeOv(id,e){if(e.target===document.getElementById(id))closeAll();}
function closeAll(){['ov-auth','ov-pay','ov-tkt'].forEach(id=>document.getElementById(id).classList.remove('open'));}
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeAll();});

// ═══ HELPERS ═══
function copyIP(){navigator.clipboard.writeText('9mc.ir').then(()=>alert('کپی شد: 9mc.ir')).catch(()=>alert('9mc.ir'));}
function toFa(n){return n.toString().replace(/\d/g,d=>'۰۱۲۳۴۵۶۷۸۹'[d]);}
function showErr(id,msg){const el=document.getElementById(id);el.textContent=msg;el.classList.add('show');}
function showNb(id){const el=document.getElementById(id);if(!el)return;el.classList.add('show');setTimeout(()=>el.classList.remove('show'),3000);}
function setBL(id,on){const el=document.getElementById(id);if(!el)return;if(on){el.dataset.orig=el.textContent;el.innerHTML=el.dataset.orig+'<span class="loader"></span>';el.disabled=true;}else{el.innerHTML=el.dataset.orig||el.textContent;el.disabled=false;}}
</script>
</body>
</html>
