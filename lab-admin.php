<?php
session_start();
define('LAB_ADMIN_PASS', 'Avina12044--');

$orders_file  = dirname(__FILE__) . '/data/lab_orders.json';
$pending_file = dirname(__FILE__) . '/data/lab_pending.json';
$refs_file    = dirname(__FILE__) . '/data/marketers.json';

function loadJson($f){
    if(!file_exists($f)) return array();
    $d = json_decode(file_get_contents($f), true);
    return is_array($d) ? $d : array();
}
function saveJson($f, $d){
    $dir = dirname($f);
    if(!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($f, json_encode($d, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
}
function toJalali($gy,$gm,$gd){
    $gdi=array(31,28,31,30,31,30,31,31,30,31,30,31);
    $jdi=array(31,31,31,31,31,31,30,30,30,30,30,29);
    $gy2=($gm>2)?$gy+1:$gy;
    $d=355666+(365*$gy)+(int)(($gy2+3)/4)-(int)(($gy2+99)/100)+(int)(($gy2+399)/400);
    for($i=0;$i<$gm-1;$i++) $d+=$gdi[$i];
    $d+=$gd;
    $jy=-1595+(33*(int)($d/12053)); $d%=12053;
    $jy+=4*(int)($d/1461); $d%=1461;
    if($d>365){$jy+=(int)(($d-1)/365);$d=($d-1)%365;}
    $jm=0;
    for($i=0;$i<11;$i++){
        if($d<$jdi[$i]){$jm=$i+1;break;}
        $d-=$jdi[$i];
    }
    if($jm===0) $jm=12;
    return array($jy,$jm,$d+1);
}
function toShamsi($dt){
    if(!$dt) return '-';
    $parts = explode(' ', $dt);
    $dp = explode('-', $parts[0]);
    if(count($dp)<3) return $dt;
    $j = toJalali((int)$dp[0],(int)$dp[1],(int)$dp[2]);
    $mn=array('فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند');
    $time = isset($parts[1]) ? ' - '.substr($parts[1],0,5) : '';
    return $j[2].' '.$mn[$j[1]-1].' '.$j[0].$time;
}

// ---- Auth ----
if(isset($_POST['logout'])){ $_SESSION=array(); session_destroy(); header('Location: lab-admin.php'); exit; }
if(isset($_POST['password'])){
    if($_POST['password']===LAB_ADMIN_PASS) $_SESSION['lab_admin']=true;
    else $login_error='رمز عبور اشتباه است';
}
$logged_in = !empty($_SESSION['lab_admin']);

// ---- Load ----
$orders  = $logged_in ? array_reverse(loadJson($orders_file))  : array();
$pending = $logged_in ? array_reverse(loadJson($pending_file)) : array();
$refs    = $logged_in ? loadJson($refs_file) : array();

// ---- Actions ----
if($logged_in){
    if(isset($_POST['add_marketer']) && isset($_POST['m_name']) && trim($_POST['m_name'])!==''){
        $name = trim($_POST['m_name']);
        $code = isset($_POST['m_code']) ? trim($_POST['m_code']) : '';
        if($code===''){
            $slug = strtolower(str_replace(' ','-',$name));
            $slug = preg_replace('/[^a-z0-9\-]/','', $slug);
            if($slug==='') $slug='ref';
            $code = $slug.'-'.rand(100,999);
        } else {
            $code = preg_replace('/[^a-zA-Z0-9_\-]/','', $code);
        }
        $exists = false;
        foreach($refs as $r){ if($r['code']===$code){ $exists=true; break; } }
        if(!$exists){
            $refs[] = array(
                'code'       => $code,
                'name'       => $name,
                'sales'      => 0,
                'amount'     => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'last_sale'  => '',
            );
            saveJson($refs_file, $refs);
        }
        header('Location: lab-admin.php?tab=marketers'); exit;
    }
    if(isset($_POST['del_marketer']) && isset($_POST['m_code'])){
        $code = trim($_POST['m_code']);
        $newRefs = array();
        foreach($refs as $r){ if($r['code']!==$code) $newRefs[]=$r; }
        saveJson($refs_file, $newRefs);
        header('Location: lab-admin.php?tab=marketers'); exit;
    }
}

// ---- Stats ----
$failed_list = array();
foreach($pending as $p){
    if(!empty($p['created_at']) && (time()-strtotime($p['created_at']))>7200)
        $failed_list[]=$p;
}
$active_pending = array();
foreach($pending as $p){
    if(empty($p['created_at']) || (time()-strtotime($p['created_at']))<=7200)
        $active_pending[]=$p;
}

$total_paid    = count($orders);
$total_amount  = 0;
foreach($orders as $o){ $total_amount += isset($o['amount']) ? (int)$o['amount'] : 0; }
$total_pending   = count($active_pending);
$total_failed    = count($failed_list);
$total_marketers = count($refs);

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'paid';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>پنل مدیریت آزمایشگاه</title>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--bg:#0b1120;--s:#111827;--s2:#1a2537;--bd:rgba(99,179,237,0.15);--ac:#38bdf8;--ac2:#0ea5e9;--tx:#e2e8f0;--mu:#94a3b8;--ok:#34d399;--wn:#fbbf24;--er:#f87171;--gd:#f59e0b}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Vazirmatn',sans-serif;background:var(--bg);color:var(--tx);min-height:100vh;direction:rtl}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse 80% 50% at 20% 10%,rgba(56,189,248,.05) 0%,transparent 60%);pointer-events:none;z-index:0}

.login-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;position:relative;z-index:1}
.login-card{background:var(--s);border:1px solid var(--bd);border-radius:20px;padding:40px;width:100%;max-width:380px;text-align:center}
.login-card h2{font-size:22px;font-weight:800;margin-bottom:4px}
.login-card p{font-size:13px;color:var(--mu);margin-bottom:24px}
.login-card input[type=password]{width:100%;background:var(--s2);border:1px solid var(--bd);border-radius:10px;color:var(--tx);font-family:'Vazirmatn',sans-serif;font-size:14px;padding:12px 14px;outline:none;text-align:center;letter-spacing:.1em;margin-bottom:12px}
.err{color:var(--er);font-size:13px;margin-bottom:12px}

.wrap{position:relative;z-index:1;max-width:1100px;margin:0 auto;padding:28px 16px 60px}
.topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:10px}
.topbar h1{font-size:20px;font-weight:800}
.topbar h1 span{color:var(--gd)}
.topbar-r{display:flex;gap:8px;align-items:center}
a.back{font-size:13px;color:var(--mu);text-decoration:none}
a.back:hover{color:var(--ac)}

.btn{padding:8px 16px;border-radius:8px;border:none;font-family:'Vazirmatn',sans-serif;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s}
.btn-p{background:linear-gradient(135deg,var(--ac2),#2563eb);color:#fff;width:100%}
.btn-p:hover{transform:translateY(-1px)}
.btn-g{background:rgba(148,163,184,.1);color:var(--mu)}
.btn-g:hover{background:rgba(148,163,184,.2)}
.btn-d{background:rgba(248,113,113,.12);color:var(--er)}
.btn-d:hover{background:rgba(248,113,113,.22)}
.btn-add{background:linear-gradient(135deg,var(--ac2),#2563eb);color:#fff;border:none;border-radius:8px;padding:9px 18px;font-family:'Vazirmatn',sans-serif;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap}
.btn-add:hover{transform:translateY(-1px);box-shadow:0 4px 14px rgba(14,165,233,.3)}

.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:24px}
.sc{background:var(--s);border:1px solid var(--bd);border-radius:14px;padding:18px}
.sc-icon{font-size:22px;margin-bottom:8px}
.sc-val{font-size:26px;font-weight:800;margin-bottom:2px}
.sc-lbl{font-size:11px;color:var(--mu)}

.tabs{display:flex;gap:3px;flex-wrap:wrap;margin-bottom:20px;background:var(--s);border:1px solid var(--bd);border-radius:12px;padding:4px}
.tab-a{display:flex;align-items:center;gap:6px;padding:8px 14px;border:none;background:transparent;color:var(--mu);font-family:'Vazirmatn',sans-serif;font-size:12px;font-weight:600;cursor:pointer;border-radius:9px;transition:all .2s;text-decoration:none;white-space:nowrap}
.tab-a:hover{color:var(--tx);background:var(--s2)}
.tab-a.t-ok{background:linear-gradient(135deg,#059669,#047857);color:#fff}
.tab-a.t-wn{background:linear-gradient(135deg,#d97706,#b45309);color:#fff}
.tab-a.t-er{background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff}
.tab-a.t-pu{background:linear-gradient(135deg,#7c3aed,#6d28d9);color:#fff}
.tc{border-radius:100px;padding:1px 6px;font-size:10px;background:rgba(255,255,255,.15)}
.tab-a:not([class*=t-ok]):not([class*=t-wn]):not([class*=t-er]):not([class*=t-pu]) .tc{background:rgba(148,163,184,.1);color:var(--mu)}

.panel{display:none}
.panel.on{display:block}

.sb{display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;align-items:center}
.sb input{background:var(--s);border:1px solid var(--bd);border-radius:8px;color:var(--tx);font-family:'Vazirmatn',sans-serif;font-size:13px;padding:7px 12px;outline:none;flex:1;min-width:180px}
.sb input:focus{border-color:var(--ac2)}
.cbadge{background:rgba(56,189,248,.1);border:1px solid var(--bd);color:var(--ac);font-size:12px;padding:4px 10px;border-radius:100px;font-weight:600;white-space:nowrap}

.tw{overflow-x:auto;background:var(--s);border:1px solid var(--bd);border-radius:14px}
table{width:100%;border-collapse:collapse;min-width:620px}
thead{background:var(--s2)}
th{font-size:10px;font-weight:700;color:var(--mu);letter-spacing:.07em;padding:10px 12px;text-align:right;white-space:nowrap}
td{padding:10px 12px;font-size:12px;border-top:1px solid var(--bd);vertical-align:middle}
tr:hover td{background:rgba(56,189,248,.02)}
.bold{font-weight:600}
.mono{font-family:monospace;font-size:11px;letter-spacing:.04em}
.dc{color:var(--mu);font-size:11px}
.ac{color:var(--gd);font-weight:700}

.badge{display:inline-block;padding:2px 9px;border-radius:100px;font-size:10px;font-weight:600}
.b-ok{background:rgba(52,211,153,.1);color:var(--ok);border:1px solid rgba(52,211,153,.2)}
.b-pn{background:rgba(251,191,36,.1);color:var(--wn);border:1px solid rgba(251,191,36,.2)}
.b-er{background:rgba(248,113,113,.1);color:var(--er);border:1px solid rgba(248,113,113,.2)}

.tags{display:flex;flex-wrap:wrap;gap:3px}
.tag{background:rgba(56,189,248,.08);border:1px solid rgba(56,189,248,.15);color:var(--ac);border-radius:4px;padding:2px 6px;font-size:10px;font-weight:600;white-space:nowrap}

.empty{text-align:center;padding:40px 20px;color:var(--mu)}

/* Marketer */
.add-form{background:var(--s);border:1px solid var(--bd);border-radius:14px;padding:18px;margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end}
.add-form .fi{display:flex;flex-direction:column;gap:5px;flex:1;min-width:150px}
.add-form label{font-size:11px;font-weight:700;color:var(--mu);letter-spacing:.07em}
.add-form input{background:var(--s2);border:1px solid var(--bd);border-radius:8px;color:var(--tx);font-family:'Vazirmatn',sans-serif;font-size:13px;padding:9px 12px;outline:none;width:100%}
.add-form input:focus{border-color:var(--ac2)}

.mc{background:var(--s);border:1px solid var(--bd);border-radius:14px;padding:18px;margin-bottom:10px;display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap}
.mc-name{font-size:15px;font-weight:700;margin-bottom:3px}
.mc-code{font-size:11px;color:var(--mu);font-family:monospace;margin-bottom:10px}
.mc-stats{display:flex;gap:16px;margin-bottom:10px}
.mc-sv{font-size:20px;font-weight:800;color:var(--ac)}
.mc-sl{font-size:11px;color:var(--mu)}
.link-box{background:var(--s2);border:1px solid var(--bd);border-radius:8px;padding:8px 12px;font-size:11px;color:var(--mu);font-family:monospace;word-break:break-all;cursor:pointer;transition:border-color .2s;margin-top:4px}
.link-box:hover{border-color:var(--ac);color:var(--tx)}
.link-copied{border-color:var(--ok)!important;color:var(--ok)!important}

@media(max-width:500px){.stats{grid-template-columns:1fr 1fr}}
</style>
</head>
<body>
<?php if(!$logged_in): ?>
<div class="login-wrap">
  <div class="login-card">
    <h2>پنل آزمایشگاه</h2>
    <p>مدیریت سفارش‌های آزمایشگاه کت‌لب</p>
    <?php if(isset($login_error)): ?><div class="err"><?php echo htmlspecialchars($login_error); ?></div><?php endif; ?>
    <form method="POST">
      <input type="password" name="password" placeholder="رمز عبور" autofocus>
      <button type="submit" class="btn btn-p">ورود</button>
    </form>
  </div>
</div>
<?php else: ?>
<div class="wrap">
  <div class="topbar">
    <h1>پنل <span>آزمایشگاه</span></h1>
    <div class="topbar-r">
      <a href="lab-order.php" class="back">← صفحه ثبت سفارش</a>
      <form method="POST" style="display:inline">
        <button type="submit" name="logout" value="1" class="btn btn-g">خروج</button>
      </form>
    </div>
  </div>

  <div class="stats">
    <div class="sc"><div class="sc-icon">✅</div><div class="sc-val" style="color:var(--ok)"><?php echo $total_paid; ?></div><div class="sc-lbl">پرداخت موفق</div></div>
    <div class="sc"><div class="sc-icon">💰</div><div class="sc-val" style="color:var(--gd)"><?php echo number_format($total_amount); ?></div><div class="sc-lbl">جمع دریافتی (تومان)</div></div>
    <div class="sc"><div class="sc-icon">⏳</div><div class="sc-val" style="color:var(--ac)"><?php echo $total_pending; ?></div><div class="sc-lbl">در انتظار</div></div>
    <div class="sc"><div class="sc-icon">❌</div><div class="sc-val" style="color:var(--er)"><?php echo $total_failed; ?></div><div class="sc-lbl">ناموفق</div></div>
    <div class="sc"><div class="sc-icon">🔗</div><div class="sc-val" style="color:#a78bfa"><?php echo $total_marketers; ?></div><div class="sc-lbl">بازاریاب</div></div>
  </div>

  <div class="tabs">
    <a href="?tab=paid"      class="tab-a <?php echo $tab==='paid'     ?'t-ok':''; ?>">✅ موفق <span class="tc"><?php echo $total_paid; ?></span></a>
    <a href="?tab=pending"   class="tab-a <?php echo $tab==='pending'  ?'t-wn':''; ?>">⏳ در انتظار <span class="tc"><?php echo $total_pending; ?></span></a>
    <a href="?tab=failed"    class="tab-a <?php echo $tab==='failed'   ?'t-er':''; ?>">❌ ناموفق <span class="tc"><?php echo $total_failed; ?></span></a>
    <a href="?tab=marketers" class="tab-a <?php echo $tab==='marketers'?'t-pu':''; ?>">🔗 بازاریاب‌ها <span class="tc"><?php echo $total_marketers; ?></span></a>
  </div>

  <!-- PAID -->
  <div class="panel <?php echo $tab==='paid'?'on':''; ?>" id="p-paid">
    <div class="sb">
      <input type="text" placeholder="جستجو در نام، کد ملی، شماره مرجع..." oninput="search('t-paid',this.value,'c-paid')">
      <span class="cbadge" id="c-paid"><?php echo $total_paid; ?> سفارش</span>
    </div>
    <div class="tw">
    <?php if(empty($orders)): ?>
    <div class="empty">هنوز سفارشی ثبت نشده</div>
    <?php else: ?>
    <table id="t-paid">
      <thead><tr><th>#</th><th>نام بیمار</th><th>کد ملی</th><th>موبایل</th><th>بیمه</th><th>آزمایش‌ها</th><th>مبلغ</th><th>مرجع</th><th>بازاریاب</th><th>تاریخ</th><th>وضعیت</th></tr></thead>
      <tbody>
      <?php $i=1; foreach($orders as $o): ?>
      <tr>
        <td style="color:var(--mu)"><?php echo $i++; ?></td>
        <td class="bold"><?php echo htmlspecialchars(isset($o['first_name'])?$o['first_name'].' '.$o['last_name']:''); ?></td>
        <td class="mono"><?php echo htmlspecialchars(isset($o['national_id'])?$o['national_id']:''); ?></td>
        <td><?php echo htmlspecialchars(isset($o['phone'])?$o['phone']:''); ?></td>
        <td><?php echo htmlspecialchars(isset($o['insurance'])?$o['insurance']:''); ?></td>
        <td><div class="tags"><?php if(isset($o['tests'])) foreach((array)$o['tests'] as $t) echo '<span class="tag">'.htmlspecialchars($t).'</span>'; ?></div></td>
        <td class="ac"><?php echo number_format(isset($o['amount'])?(int)$o['amount']:0); ?> ت</td>
        <td class="mono" style="color:var(--ok);font-size:10px"><?php echo htmlspecialchars(isset($o['ref_id'])?$o['ref_id']:'—'); ?></td>
        <td><?php echo isset($o['ref_code'])&&$o['ref_code']!=='' ? '<span class="badge b-pn">'.htmlspecialchars($o['ref_code']).'</span>' : '<span style="color:var(--mu)">مستقیم</span>'; ?></td>
        <td class="dc"><?php echo toShamsi(isset($o['paid_at'])?$o['paid_at']:(isset($o['created_at'])?$o['created_at']:'')); ?></td>
        <td><span class="badge b-ok">موفق</span></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
    </div>
  </div>

  <!-- PENDING -->
  <div class="panel <?php echo $tab==='pending'?'on':''; ?>" id="p-pending">
    <div class="sb">
      <input type="text" placeholder="جستجو..." oninput="search('t-pending',this.value,'c-pending')">
      <span class="cbadge" id="c-pending"><?php echo $total_pending; ?> سفارش</span>
    </div>
    <div class="tw">
    <?php if(empty($active_pending)): ?>
    <div class="empty">هیچ سفارش در انتظاری وجود ندارد</div>
    <?php else: ?>
    <table id="t-pending">
      <thead><tr><th>#</th><th>نام بیمار</th><th>کد ملی</th><th>موبایل</th><th>بیمه</th><th>آزمایش‌ها</th><th>مبلغ</th><th>بازاریاب</th><th>تاریخ</th><th>وضعیت</th></tr></thead>
      <tbody>
      <?php $i=1; foreach($active_pending as $o): ?>
      <tr>
        <td style="color:var(--mu)"><?php echo $i++; ?></td>
        <td class="bold"><?php echo htmlspecialchars(isset($o['first_name'])?$o['first_name'].' '.$o['last_name']:''); ?></td>
        <td class="mono"><?php echo htmlspecialchars(isset($o['national_id'])?$o['national_id']:''); ?></td>
        <td><?php echo htmlspecialchars(isset($o['phone'])?$o['phone']:''); ?></td>
        <td><?php echo htmlspecialchars(isset($o['insurance'])?$o['insurance']:''); ?></td>
        <td><div class="tags"><?php if(isset($o['tests'])) foreach((array)$o['tests'] as $t) echo '<span class="tag">'.htmlspecialchars($t).'</span>'; ?></div></td>
        <td class="ac"><?php echo number_format(isset($o['amount'])?(int)$o['amount']:0); ?> ت</td>
        <td><?php echo isset($o['ref_code'])&&$o['ref_code']!=='' ? '<span class="badge b-pn">'.htmlspecialchars($o['ref_code']).'</span>' : '<span style="color:var(--mu)">مستقیم</span>'; ?></td>
        <td class="dc"><?php echo toShamsi(isset($o['created_at'])?$o['created_at']:''); ?></td>
        <td><span class="badge b-pn">در انتظار</span></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
    </div>
  </div>

  <!-- FAILED -->
  <div class="panel <?php echo $tab==='failed'?'on':''; ?>" id="p-failed">
    <div class="sb">
      <input type="text" placeholder="جستجو..." oninput="search('t-failed',this.value,'c-failed')">
      <span class="cbadge" id="c-failed"><?php echo $total_failed; ?> سفارش</span>
    </div>
    <div class="tw">
    <?php if(empty($failed_list)): ?>
    <div class="empty">هیچ پرداخت ناموفقی ثبت نشده</div>
    <?php else: ?>
    <table id="t-failed">
      <thead><tr><th>#</th><th>نام بیمار</th><th>کد ملی</th><th>موبایل</th><th>بیمه</th><th>آزمایش‌ها</th><th>مبلغ</th><th>بازاریاب</th><th>تاریخ</th><th>وضعیت</th></tr></thead>
      <tbody>
      <?php $i=1; foreach($failed_list as $o): ?>
      <tr>
        <td style="color:var(--mu)"><?php echo $i++; ?></td>
        <td class="bold"><?php echo htmlspecialchars(isset($o['first_name'])?$o['first_name'].' '.$o['last_name']:''); ?></td>
        <td class="mono"><?php echo htmlspecialchars(isset($o['national_id'])?$o['national_id']:''); ?></td>
        <td><?php echo htmlspecialchars(isset($o['phone'])?$o['phone']:''); ?></td>
        <td><?php echo htmlspecialchars(isset($o['insurance'])?$o['insurance']:''); ?></td>
        <td><div class="tags"><?php if(isset($o['tests'])) foreach((array)$o['tests'] as $t) echo '<span class="tag">'.htmlspecialchars($t).'</span>'; ?></div></td>
        <td class="ac"><?php echo number_format(isset($o['amount'])?(int)$o['amount']:0); ?> ت</td>
        <td><?php echo isset($o['ref_code'])&&$o['ref_code']!=='' ? '<span class="badge b-pn">'.htmlspecialchars($o['ref_code']).'</span>' : '<span style="color:var(--mu)">مستقیم</span>'; ?></td>
        <td class="dc"><?php echo toShamsi(isset($o['created_at'])?$o['created_at']:''); ?></td>
        <td><span class="badge b-er">ناموفق</span></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
    </div>
  </div>

  <!-- MARKETERS -->
  <div class="panel <?php echo $tab==='marketers'?'on':''; ?>" id="p-marketers">

    <form method="POST" action="?tab=marketers" class="add-form">
      <div class="fi">
        <label>نام بازاریاب</label>
        <input type="text" name="m_name" placeholder="مثلاً: علی محمدی" required>
      </div>
      <div class="fi">
        <label>کد اختصاصی (اختیاری)</label>
        <input type="text" name="m_code" placeholder="مثلاً: ali123 — خودکار می‌سازه">
      </div>
      <button type="submit" name="add_marketer" class="btn-add">+ افزودن</button>
    </form>

    <?php if(empty($refs)): ?>
    <div class="empty" style="background:var(--s);border:1px solid var(--bd);border-radius:14px;padding:40px">هنوز بازاریابی ثبت نشده</div>
    <?php else:
      usort($refs, function($a,$b){
        $sa = isset($a['sales']) ? (int)$a['sales'] : 0;
        $sb = isset($b['sales']) ? (int)$b['sales'] : 0;
        return $sb - $sa;
      });
      $site = 'http://megasec.ir/lab-order.php';
      foreach($refs as $ref):
        $link = $site.'?ref='.urlencode($ref['code']);
        $sales  = isset($ref['sales'])  ? (int)$ref['sales']  : 0;
        $amount = isset($ref['amount']) ? (int)$ref['amount'] : 0;
    ?>
    <div class="mc">
      <div style="flex:1;min-width:0">
        <div class="mc-name"><?php echo htmlspecialchars($ref['name']); ?></div>
        <div class="mc-code">کد: <?php echo htmlspecialchars($ref['code']); ?></div>
        <div class="mc-stats">
          <div><div class="mc-sv"><?php echo $sales; ?></div><div class="mc-sl">فروش</div></div>
          <div><div class="mc-sv" style="color:var(--gd)"><?php echo number_format($amount); ?></div><div class="mc-sl">تومان</div></div>
          <div><div class="mc-sv" style="font-size:12px;color:var(--mu)"><?php echo !empty($ref['last_sale']) ? toShamsi($ref['last_sale']) : '—'; ?></div><div class="mc-sl">آخرین فروش</div></div>
        </div>
        <div class="link-box" onclick="copyLink(this,'<?php echo htmlspecialchars($link, ENT_QUOTES); ?>')" title="کلیک برای کپی">
          🔗 <?php echo htmlspecialchars($link); ?>
        </div>
      </div>
      <form method="POST" action="?tab=marketers" onsubmit="return confirm('حذف شود؟')">
        <input type="hidden" name="m_code" value="<?php echo htmlspecialchars($ref['code']); ?>">
        <button type="submit" name="del_marketer" class="btn btn-d">حذف</button>
      </form>
    </div>
    <?php endforeach; endif; ?>

  </div>

</div>
<script>
function search(tid, q, cid){
  var rows = document.querySelectorAll('#'+tid+' tbody tr');
  var n=0, ql=q.toLowerCase();
  for(var i=0;i<rows.length;i++){
    var show = !ql || rows[i].textContent.toLowerCase().indexOf(ql)>=0;
    rows[i].style.display = show?'':'none';
    if(show) n++;
  }
  var el=document.getElementById(cid);
  if(el) el.textContent=n+' سفارش';
}
function copyLink(el, url){
  if(navigator.clipboard){
    navigator.clipboard.writeText(url).then(function(){
      el.classList.add('link-copied');
      var orig=el.innerHTML;
      el.innerHTML='✅ لینک کپی شد!';
      setTimeout(function(){el.innerHTML=orig;el.classList.remove('link-copied');},2000);
    });
  } else {
    prompt('لینک بازاریاب:',url);
  }
}
</script>
<?php endif; ?>
</body>
</html>
