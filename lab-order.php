<?php
// =============================================
//  lab-order.php — ثبت نسخه + پرداخت زرین‌پال + سیستم بازاریابی
// =============================================
session_start();

// ===== REFERRAL =====
if(!empty($_GET['ref'])){
    $_SESSION['ref_code'] = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['ref']);
}
$ref_code = $_SESSION['ref_code'] ?? '';

define('ZP_MERCHANT', '59160a38-3aa9-419d-97c4-46e2b56ef058');
define('ZP_AMOUNT',   357000);
define('ZP_CURRENCY','IRT');
define('ZP_REQUEST_URL',  'https://api.zarinpal.com/pg/v4/payment/request.json');
define('ZP_VERIFY_URL',   'https://api.zarinpal.com/pg/v4/payment/verify.json');
define('ZP_GATE_URL',     'https://www.zarinpal.com/pg/StartPay/');

$CALLBACK_URL = 'http://megasec.ir/lab-order.php?step=verify';

$db_file       = __DIR__ . '/data/lab_orders.json';
$pending_file  = __DIR__ . '/data/lab_pending.json';
$refs_file     = __DIR__ . '/data/marketers.json'; // لیست بازاریاب‌ها

// ===== HELPERS =====
function loadData($f){ if(!file_exists($f)) return []; return json_decode(file_get_contents($f),true)?:[]; }
function saveData($f,$d){ if(!is_dir(dirname($f))) mkdir(dirname($f),0755,true); file_put_contents($f,json_encode($d,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)); }

function toJalali($gy,$gm,$gd){
    $gdi=[31,28,31,30,31,30,31,31,30,31,30,31];
    $jdi=[31,31,31,31,31,31,30,30,30,30,30,29];
    $gy2=($gm>2)?$gy+1:$gy;
    $d=355666+(365*$gy)+(int)(($gy2+3)/4)-(int)(($gy2+99)/100)+(int)(($gy2+399)/400);
    for($i=0;$i<$gm-1;$i++) $d+=$gdi[$i];
    $d+=$gd;
    $jy=-1595+(33*(int)($d/12053));$d%=12053;
    $jy+=4*(int)($d/1461);$d%=1461;
    if($d>365){$jy+=(int)(($d-1)/365);$d=($d-1)%365;}
    $jm=0;for($i=0;$i<11;$i++){if($d<$jdi[$i]){$jm=$i+1;break;}$d-=$jdi[$i];}
    if($jm===0)$jm=12;
    return [$jy,$jm,$d+1];
}
function shamsiNow(){
    $dp=explode('-',date('Y-m-d'));
    [$jy,$jm,$jd]=toJalali((int)$dp[0],(int)$dp[1],(int)$dp[2]);
    $mn=['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
    return $jd.' '.$mn[$jm-1].' '.$jy.' — '.date('H:i');
}

// ===== DISPLAY COUNTER =====
function getDisplayCount($orders_file){
    $boost_file = __DIR__ . '/data/counter_boost.json';
    $today = date('Y-m-d');
    $real = count(loadData($orders_file));
    $data = [];
    if(file_exists($boost_file))
        $data = json_decode(file_get_contents($boost_file), true) ?: [];
    if(!isset($data['last_date']) || $data['last_date'] !== $today){
        $data['cumulative'] = ($data['cumulative'] ?? 0) + rand(5, 10);
        $data['last_date']  = $today;
        if(!is_dir(dirname($boost_file))) mkdir(dirname($boost_file), 0755, true);
        file_put_contents($boost_file, json_encode($data));
    }
    return $real + ($data['cumulative'] ?? 0);
}

function zpRequest($url, $data){
    $ch = curl_init($url);
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json','Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if($err) return null;
    return json_decode($res, true);
}

// ===== STEP: VERIFY (callback from zarinpal) =====
if(isset($_GET['step']) && $_GET['step']==='verify'){
    $authority = $_GET['Authority'] ?? '';
    $zpStatus  = $_GET['Status']    ?? '';

    $pending = loadData($pending_file);
    $order   = null;
    foreach($pending as $p){
        if($p['authority']===$authority){ $order=$p; break; }
    }

    if(!$order || $zpStatus!=='OK'){
        $page = 'failed';
        $fail_msg = $zpStatus==='NOK' ? 'پرداخت توسط کاربر لغو شد' : 'خطا در بازگشت از درگاه';
    } else {
        // Verify with zarinpal
        $res = zpRequest(ZP_VERIFY_URL,[
            'merchant_id' => ZP_MERCHANT,
            'amount'      => ZP_AMOUNT * 10, // تبدیل به ریال برای API
            'authority'   => $authority,
        ]);

        $code = $res['data']['code'] ?? -1;

        if($code===100 || $code===101){
            // موفق
            $order['ref_id']     = $res['data']['ref_id'] ?? '';
            $order['status']     = 'paid';
            $order['paid_at']    = date('Y-m-d H:i:s');
            $order['jalali_date']= shamsiNow();

            // ذخیره در سفارش‌های نهایی
            $orders = loadData($db_file);
            $orders[] = $order;
            saveData($db_file, $orders);

            // حذف از pending
            $newPending = array_values(array_filter($pending, fn($p)=>$p['authority']!==$authority));
            saveData($pending_file, $newPending);

            // آپدیت آمار بازاریاب
            if(!empty($order['ref_code'])){
                $refs = loadData($refs_file);
                $found = false;
                foreach($refs as &$r){
                    if($r['code'] === $order['ref_code']){
                        $r['sales']  = ($r['sales']  ?? 0) + 1;
                        $r['amount'] = ($r['amount'] ?? 0) + ZP_AMOUNT;
                        $r['last_sale'] = date('Y-m-d H:i:s');
                        $found = true; break;
                    }
                }
                unset($r);
                if(!$found){
                    $refs[] = [
                        'code'      => $order['ref_code'],
                        'name'      => $order['ref_code'],
                        'sales'     => 1,
                        'amount'    => ZP_AMOUNT,
                        'last_sale' => date('Y-m-d H:i:s'),
                        'created_at'=> date('Y-m-d H:i:s'),
                    ];
                }
                saveData($refs_file, $refs);
            }

            $page = 'success';
            $success_order = $order;
        } else {
            $page = 'failed';
            $zp_errors = [
                -9  => 'اطلاعات ارسالی ناقص است',
                -10 => 'IP یا مرچنت معتبر نیست',
                -11 => 'مرچنت فعال نیست',
                -12 => 'تلاش بیش از حد مجاز',
                -15 => 'ترمینال غیرفعال است',
                -21 => 'هیچ نوع عملیات مالی برای این تراکنش یافت نشد',
                -22 => 'تراکنش نامعتبر است',
                -33 => 'مبلغ تراکنش با مبلغ پرداخت شده مطابقت ندارد',
                -34 => 'سقف تقسیم تراکنش از حد مجاز بیشتر است',
                -40 => 'اجازه دسترسی به متد داده شده وجود ندارد',
                -41 => 'اطلاعات ارسال شده مربوط به AdditionalData غیرمعتبر است',
                -42 => 'مدت زمان معتبر طول عمر شناسه پرداخت بین ۳۰ دقیقه تا ۴۵ روز',
                -54 => 'درخواست مورد نظر آرشیو شده است',
                101 => 'تراکنش قبلاً تأیید شده است',
            ];
            $fail_msg = $zp_errors[$code] ?? 'خطای ناشناخته (کد: '.$code.')';
        }
    }

// ===== STEP: REQUEST PAYMENT =====
} elseif(isset($_POST['submit_order'])){
    $first_name  = trim($_POST['first_name']  ?? '');
    $last_name   = trim($_POST['last_name']   ?? '');
    $national_id = preg_replace('/\D/','',$_POST['national_id'] ?? '');
    $phone       = trim($_POST['phone']       ?? '');
    $insurance   = trim($_POST['insurance']   ?? '');
    $tests       = (array)($_POST['tests']    ?? []);
    $notes       = trim($_POST['notes']       ?? '');

    $allowed_insurance = ['تامین اجتماعی','خدمات درمانی','بیمه سلامت'];
    $allowed_tests     = ['CBC','BUN CR NA K FBS','SGOT SGPT ALK P','U/A','U/C','HBA1C'];

    $errors = [];
    if(!$first_name)              $errors[] = 'نام الزامی است';
    if(!$last_name)               $errors[] = 'نام خانوادگی الزامی است';
    if(strlen($national_id)!==10) $errors[] = 'کد ملی باید ۱۰ رقم باشد';
    if(!$phone)                   $errors[] = 'شماره موبایل الزامی است';
    if(!in_array($insurance,$allowed_insurance,true)) $errors[] = 'نوع بیمه را انتخاب کنید';
    $validTests = array_values(array_filter($tests, fn($t)=>in_array($t,$allowed_tests,true)));
    if(empty($validTests))        $errors[] = 'حداقل یک آزمایش انتخاب کنید';

    if(empty($errors)){
        $orderId = 'lab_'.uniqid();
        $desc    = 'نسخه آزمایشگاه — '.$first_name.' '.$last_name.' — '.implode('، ',$validTests);

        // درخواست پرداخت از زرین‌پال
        $res = zpRequest(ZP_REQUEST_URL,[
            'merchant_id'  => ZP_MERCHANT,
            'amount'       => ZP_AMOUNT * 10, // ریال
            'currency'     => ZP_CURRENCY === 'IRT' ? 'IRR' : ZP_CURRENCY,
            'callback_url' => $CALLBACK_URL,
            'description'  => $desc,
            'metadata'     => ['mobile'=>$phone,'order_id'=>$orderId],
        ]);

        $code      = $res['data']['code']      ?? -1;
        $authority = $res['data']['authority'] ?? '';

        if($code===100 && $authority){
            // ذخیره موقت سفارش در pending
            $order = [
                'id'          => $orderId,
                'authority'   => $authority,
                'first_name'  => $first_name,
                'last_name'   => $last_name,
                'national_id' => $national_id,
                'phone'       => $phone,
                'insurance'   => $insurance,
                'tests'       => $validTests,
                'notes'       => $notes,
                'amount'      => ZP_AMOUNT,
                'status'      => 'pending',
                'ref_code'    => $ref_code,
                'created_at'  => date('Y-m-d H:i:s'),
            ];
            $pending = loadData($pending_file);
            $pending[] = $order;
            saveData($pending_file, $pending);

            // ریدایرکت به درگاه
            header('Location: '.ZP_GATE_URL.$authority);
            exit;
        } else {
            $zp_req_errors = [
                -9  => 'اطلاعات ارسالی ناقص است',
                -10 => 'IP یا مرچنت معتبر نیست',
                -11 => 'مرچنت فعال نیست',
                -34 => 'مبلغ از حد مجاز بیشتر است',
                -40 => 'اجازه دسترسی وجود ندارد',
            ];
            $errors[] = 'خطا در اتصال به درگاه پرداخت: '.($zp_req_errors[$code] ?? 'کد '.$code);
            $page = 'form';
        }
    } else {
        $page = 'form';
    }
} else {
    $page = 'form';
}

$tests_list = [
    'CBC'             => ['label'=>'CBC',                    'desc'=>'شمارش کامل خون',   'icon'=>'🩸'],
    'BUN CR NA K FBS' => ['label'=>'BUN / CR / NA / K / FBS','desc'=>'بیوشیمی پایه',    'icon'=>'🧪'],
    'SGOT SGPT ALK P' => ['label'=>'SGOT / SGPT / ALK-P',   'desc'=>'آنزیم‌های کبدی',  'icon'=>'🫀'],
    'U/A'             => ['label'=>'U/A',                    'desc'=>'آنالیز ادرار',     'icon'=>'💧'],
    'U/C'             => ['label'=>'U/C',                    'desc'=>'کشت ادرار',        'icon'=>'🔬'],
    'HBA1C'           => ['label'=>'HBA1C',                  'desc'=>'قند خون سه‌ماهه','icon'=>'📊'],
];
$display_count = getDisplayCount($db_file);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ثبت نسخه آزمایشگاه | کت‌لب</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#060d1a;--surface:#0d1829;--surface2:#142035;--surface3:#1a2a42;
  --border:rgba(56,189,248,0.12);--border2:rgba(56,189,248,0.22);
  --accent:#38bdf8;--accent2:#0ea5e9;--accent3:#0284c7;
  --gold:#f59e0b;--gold2:#fbbf24;
  --success:#10b981;--error:#f43f5e;
  --text:#e2e8f0;--muted:#64748b;--muted2:#94a3b8;
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Vazirmatn',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;direction:rtl;overflow-x:hidden}
.bg-layer{position:fixed;inset:0;z-index:0;pointer-events:none}
.bg-layer::before{content:'';position:absolute;width:600px;height:600px;top:-150px;right:-100px;background:radial-gradient(circle,rgba(56,189,248,0.07) 0%,transparent 70%);border-radius:50%}
.bg-layer::after{content:'';position:absolute;width:500px;height:500px;bottom:-100px;left:-80px;background:radial-gradient(circle,rgba(245,158,11,0.05) 0%,transparent 70%);border-radius:50%}
.grid-bg{position:fixed;inset:0;z-index:0;pointer-events:none;background-image:linear-gradient(rgba(56,189,248,.025) 1px,transparent 1px),linear-gradient(90deg,rgba(56,189,248,.025) 1px,transparent 1px);background-size:50px 50px}
.container{position:relative;z-index:1;max-width:680px;margin:0 auto;padding:48px 20px 80px}

/* Header */
.header{text-align:center;margin-bottom:44px;animation:fadeDown .7s ease both}
.logo-wrap{display:inline-flex;align-items:center;gap:10px;background:linear-gradient(135deg,rgba(56,189,248,.1),rgba(14,165,233,.05));border:1px solid var(--border2);border-radius:100px;padding:8px 20px;margin-bottom:24px;font-size:13px;color:var(--accent);font-weight:600}
.logo-dot{width:8px;height:8px;background:var(--accent);border-radius:50%;box-shadow:0 0 8px var(--accent);animation:pulse 2s ease infinite}
.header h1{font-size:clamp(24px,5vw,34px);font-weight:900;line-height:1.2;margin-bottom:12px;background:linear-gradient(135deg,#fff 30%,var(--accent) 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.header p{font-size:14px;color:var(--muted2);line-height:1.9}
.counter-wrap{display:inline-flex;align-items:center;gap:10px;background:rgba(56,189,248,0.07);border:1px solid rgba(56,189,248,0.18);border-radius:12px;padding:10px 20px;margin-top:18px}
.counter-num{font-size:22px;font-weight:800;color:var(--accent)}
.counter-label{font-size:12px;color:var(--muted2);text-align:right}

/* Price badge */
.price-hero{background:linear-gradient(135deg,rgba(245,158,11,.1),rgba(245,158,11,.04));border:1px solid rgba(245,158,11,.25);border-radius:16px;padding:16px 24px;display:flex;align-items:center;justify-content:space-between;margin-bottom:32px;animation:fadeDown .6s ease .1s both}
.price-label{font-size:13px;color:var(--muted2)}
.price-amount{font-size:26px;font-weight:900;color:var(--gold2);letter-spacing:.02em}
.price-unit{font-size:13px;color:var(--gold);font-weight:600;margin-right:4px}

/* Cards */
.form-card{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:32px;margin-bottom:20px;animation:fadeUp .6s ease .15s both;position:relative;overflow:hidden}
.form-card::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(56,189,248,.02) 0%,transparent 60%);pointer-events:none}
.card-header{display:flex;align-items:center;gap:12px;margin-bottom:24px;padding-bottom:16px;border-bottom:1px solid var(--border)}
.card-num{width:28px;height:28px;background:linear-gradient(135deg,var(--accent2),var(--accent3));border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;color:#fff;flex-shrink:0}
.card-title{font-size:14px;font-weight:700;color:var(--text)}
.card-subtitle{font-size:12px;color:var(--muted2);margin-top:1px}

/* Fields */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
@media(max-width:480px){.form-grid{grid-template-columns:1fr}}
.field{display:flex;flex-direction:column;gap:7px}
.field label{font-size:11px;font-weight:700;color:var(--muted2);letter-spacing:.08em;text-transform:uppercase}
.field input,.field textarea{background:var(--surface2);border:1px solid var(--border);border-radius:10px;color:var(--text);font-family:'Vazirmatn',sans-serif;font-size:14px;padding:12px 15px;outline:none;transition:all .2s;width:100%}
.field input:focus,.field textarea:focus{border-color:var(--accent2);box-shadow:0 0 0 3px rgba(14,165,233,.15);background:var(--surface3)}
.field input::placeholder,.field textarea::placeholder{color:rgba(100,116,139,.6);font-size:13px}
.field textarea{resize:vertical;min-height:90px}

/* Insurance */
.insurance-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
@media(max-width:480px){.insurance-grid{grid-template-columns:1fr}}
.ins-option input[type=radio]{display:none}
.ins-label{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;padding:14px 10px;background:var(--surface2);border:1.5px solid var(--border);border-radius:12px;cursor:pointer;transition:all .2s;text-align:center}
.ins-label:hover{border-color:var(--border2);background:var(--surface3)}
.ins-icon{font-size:22px}
.ins-name{font-size:12px;font-weight:700;color:var(--muted2);transition:color .2s}
.ins-option input:checked+.ins-label{border-color:var(--accent);background:rgba(56,189,248,.08);box-shadow:0 0 0 1px var(--accent) inset}
.ins-option input:checked+.ins-label .ins-name{color:var(--accent)}

/* Tests */
.tests-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
@media(max-width:480px){.tests-grid{grid-template-columns:1fr}}
.test-option input[type=checkbox]{display:none}
.test-label{display:flex;align-items:center;gap:12px;padding:14px 16px;background:var(--surface2);border:1.5px solid var(--border);border-radius:12px;cursor:pointer;transition:all .2s}
.test-label:hover{border-color:var(--border2);background:var(--surface3)}
.test-icon{font-size:20px;flex-shrink:0}
.test-info{flex:1;min-width:0}
.test-name{font-size:13px;font-weight:700;color:var(--text);line-height:1.3}
.test-desc{font-size:11px;color:var(--muted);margin-top:2px}
.test-check{width:18px;height:18px;border-radius:5px;border:1.5px solid var(--border2);flex-shrink:0;display:flex;align-items:center;justify-content:center;transition:all .2s}
.test-option input:checked+.test-label{border-color:var(--accent);background:rgba(56,189,248,.07)}
.test-option input:checked+.test-label .test-check{background:var(--accent2);border-color:var(--accent2)}
.test-option input:checked+.test-label .test-check::after{content:'✓';font-size:11px;color:#fff;font-weight:800}

/* Summary */
.order-summary{background:var(--surface2);border:1px solid var(--border);border-radius:12px;padding:16px;margin-top:14px;font-size:13px;color:var(--muted2);min-height:44px;transition:all .3s}
.summary-tests{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}
.summary-badge{background:rgba(56,189,248,.1);border:1px solid rgba(56,189,248,.2);color:var(--accent);border-radius:6px;padding:3px 10px;font-size:11px;font-weight:600}

/* Errors */
.error-box{background:rgba(244,63,94,.08);border:1px solid rgba(244,63,94,.25);border-radius:12px;padding:14px 18px;margin-bottom:20px;font-size:13px;color:#fb7185;animation:shake .4s ease}
.error-box ul{padding-right:16px;margin-top:6px;line-height:2}

/* Buttons */
.btn-pay{width:100%;padding:18px;background:linear-gradient(135deg,#f59e0b,#d97706);color:#1a0a00;border:none;border-radius:14px;font-family:'Vazirmatn',sans-serif;font-size:16px;font-weight:800;cursor:pointer;transition:all .25s;display:flex;align-items:center;justify-content:center;gap:10px;position:relative;overflow:hidden;margin-top:8px}
.btn-pay::before{content:'';position:absolute;top:0;left:-100%;width:100%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.15),transparent);transition:left .5s}
.btn-pay:hover{transform:translateY(-2px);box-shadow:0 12px 30px rgba(245,158,11,.4)}
.btn-pay:hover::before{left:100%}
.btn-pay:active{transform:translateY(0)}
.btn-amount{background:rgba(0,0,0,.15);border-radius:8px;padding:4px 12px;font-size:14px}

/* Zarinpal badge */
.zp-badge{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:14px;font-size:12px;color:var(--muted);background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:10px}
.zp-badge img{height:22px;opacity:.7}

/* Success screen */
.success-wrap{text-align:center;animation:fadeUp .6s ease both}
.success-icon{width:90px;height:90px;background:linear-gradient(135deg,rgba(16,185,129,.15),rgba(16,185,129,.05));border:2px solid rgba(16,185,129,.3);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:38px;margin:0 auto 24px;animation:scaleIn .5s ease both}
.success-title{font-size:24px;font-weight:900;color:var(--success);margin-bottom:8px}
.success-sub{font-size:14px;color:var(--muted2);margin-bottom:32px;line-height:1.8}
.ref-badge{display:inline-block;background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.25);color:var(--success);border-radius:8px;padding:6px 16px;font-size:13px;font-weight:700;margin-top:6px;letter-spacing:.05em}

/* Failed screen */
.failed-wrap{text-align:center;animation:fadeUp .6s ease both}
.failed-icon{width:90px;height:90px;background:rgba(244,63,94,.1);border:2px solid rgba(244,63,94,.3);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:38px;margin:0 auto 24px;animation:scaleIn .5s ease both}
.failed-title{font-size:22px;font-weight:900;color:var(--error);margin-bottom:8px}
.failed-msg{font-size:14px;color:var(--muted2);margin-bottom:28px;line-height:1.8;background:rgba(244,63,94,.07);border:1px solid rgba(244,63,94,.2);border-radius:10px;padding:14px}

/* Receipt */
.receipt{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:24px;text-align:right;margin-bottom:24px}
.receipt-title{font-size:12px;font-weight:700;color:var(--muted);letter-spacing:.1em;text-transform:uppercase;margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px}
.receipt-title::before{content:'';width:3px;height:12px;background:var(--gold);border-radius:2px}
.receipt-row{display:flex;justify-content:space-between;align-items:flex-start;padding:9px 0;border-bottom:1px solid rgba(56,189,248,.06);font-size:13px}
.receipt-row:last-child{border-bottom:none}
.receipt-key{color:var(--muted2)}
.receipt-val{font-weight:600;color:var(--text);text-align:left;max-width:65%}
.receipt-tests{display:flex;flex-wrap:wrap;gap:5px;justify-content:flex-start}
.receipt-total{display:flex;justify-content:space-between;align-items:center;background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);border-radius:10px;padding:14px 16px;margin-top:14px;font-size:15px;font-weight:800}
.receipt-total .key{color:var(--gold)}
.receipt-total .val{color:var(--gold2);font-size:18px}
.btn-new{display:inline-flex;align-items:center;gap:8px;background:var(--surface);border:1px solid var(--border);color:var(--muted2);border-radius:10px;padding:11px 22px;font-family:'Vazirmatn',sans-serif;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;transition:all .2s;margin:6px}
.btn-new:hover{border-color:var(--accent);color:var(--accent)}
.footer-note{text-align:center;margin-top:32px;font-size:12px;color:rgba(100,116,139,.5)}
.footer-note a{color:rgba(56,189,248,.5);text-decoration:none}
.footer-note a:hover{color:var(--accent)}

@keyframes fadeDown{from{opacity:0;transform:translateY(-18px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeUp{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:translateY(0)}}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(.7)}}
@keyframes shake{0%,100%{transform:translateX(0)}20%,60%{transform:translateX(-5px)}40%,80%{transform:translateX(5px)}}
@keyframes scaleIn{from{opacity:0;transform:scale(.5)}to{opacity:1;transform:scale(1)}}
</style>
</head>
<body>
<div class="bg-layer"></div>
<div class="grid-bg"></div>
<div class="container">

  <div class="header">
    <div class="logo-wrap"><span class="logo-dot"></span>آزمایشگاه کت‌لب</div>
    <h1>ثبت نسخه<br>پس از پرداخت</h1>
    <p>مشخصات را وارد کنید، آزمایش‌ها را انتخاب کنید<br>و پرداخت آنلاین را انجام دهید</p>
    <div class="counter-wrap">
      <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="color:var(--accent);flex-shrink:0"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
      <div>
        <div class="counter-num"><?= number_format($display_count) ?></div>
        <div class="counter-label">نسخه تاکنون ثبت شده</div>
      </div>
    </div>
  </div>

<?php if(isset($page) && $page==='success' && isset($success_order)): ?>
<!-- ===== SUCCESS ===== -->
<div class="success-wrap">
  <div class="success-icon">✅</div>
  <div class="success-title">پرداخت موفق!</div>
  <div class="success-sub">
    سفارش شما با موفقیت ثبت شد<br>
    <span class="ref-badge">کد پیگیری: <?= htmlspecialchars($success_order['ref_id'] ?? $success_order['id']) ?></span>
  </div>
  <div class="receipt">
    <div class="receipt-title">رسید پرداخت</div>
    <div class="receipt-row"><span class="receipt-key">نام بیمار</span><span class="receipt-val"><?= htmlspecialchars($success_order['first_name'].' '.$success_order['last_name']) ?></span></div>
    <div class="receipt-row"><span class="receipt-key">کد ملی</span><span class="receipt-val" style="font-family:monospace"><?= htmlspecialchars($success_order['national_id']) ?></span></div>
    <div class="receipt-row"><span class="receipt-key">موبایل</span><span class="receipt-val"><?= htmlspecialchars($success_order['phone']) ?></span></div>
    <div class="receipt-row"><span class="receipt-key">نوع بیمه</span><span class="receipt-val"><?= htmlspecialchars($success_order['insurance']) ?></span></div>
    <div class="receipt-row">
      <span class="receipt-key">آزمایش‌ها</span>
      <span class="receipt-val"><div class="receipt-tests"><?php foreach($success_order['tests'] as $t): ?><span class="summary-badge"><?= htmlspecialchars($t) ?></span><?php endforeach; ?></div></span>
    </div>
    <?php if(!empty($success_order['notes'])): ?>
    <div class="receipt-row"><span class="receipt-key">توضیحات</span><span class="receipt-val"><?= htmlspecialchars($success_order['notes']) ?></span></div>
    <?php endif; ?>
    <div class="receipt-row"><span class="receipt-key">تاریخ پرداخت</span><span class="receipt-val"><?= htmlspecialchars($success_order['jalali_date'] ?? shamsiNow()) ?></span></div>
    <?php if(!empty($success_order['ref_id'])): ?>
    <div class="receipt-row"><span class="receipt-key">شماره مرجع</span><span class="receipt-val" style="font-family:monospace;color:var(--success)"><?= htmlspecialchars($success_order['ref_id']) ?></span></div>
    <?php endif; ?>
    <div class="receipt-total"><span class="key">مبلغ پرداخت شده</span><span class="val"><?= number_format(ZP_AMOUNT) ?> تومان</span></div>
  </div>
  <a href="lab-order.php" class="btn-new">
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
    ثبت سفارش جدید
  </a>
</div>

<?php elseif(isset($page) && $page==='failed'): ?>
<!-- ===== FAILED ===== -->
<div class="failed-wrap">
  <div class="failed-icon">❌</div>
  <div class="failed-title">پرداخت ناموفق</div>
  <div class="failed-msg"><?= htmlspecialchars($fail_msg ?? 'خطایی رخ داد') ?></div>
  <a href="lab-order.php" class="btn-new">
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
    تلاش مجدد
  </a>
</div>

<?php else: ?>
<!-- ===== FORM ===== -->
<div class="price-hero">
  <div><div class="price-label">مبلغ قابل پرداخت</div></div>
  <div><span class="price-amount"><?= number_format(ZP_AMOUNT) ?></span><span class="price-unit">تومان</span></div>
</div>

<?php if(!empty($errors)): ?>
<div class="error-box">
  <strong>لطفاً موارد زیر را اصلاح کنید:</strong>
  <ul><?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="POST" action="lab-order.php">

  <!-- Card 1: مشخصات -->
  <div class="form-card">
    <div class="card-header">
      <div class="card-num">۱</div>
      <div><div class="card-title">مشخصات بیمار</div><div class="card-subtitle">اطلاعات هویتی را با دقت وارد کنید</div></div>
    </div>
    <div class="form-grid">
      <div class="field"><label>نام</label><input type="text" name="first_name" placeholder="نام" value="<?= htmlspecialchars($_POST['first_name']??'') ?>" required></div>
      <div class="field"><label>نام خانوادگی</label><input type="text" name="last_name" placeholder="نام خانوادگی" value="<?= htmlspecialchars($_POST['last_name']??'') ?>" required></div>
    </div>
    <div class="form-grid">
      <div class="field"><label>کد ملی</label><input type="text" name="national_id" placeholder="۱۰ رقم" maxlength="10" inputmode="numeric" value="<?= htmlspecialchars($_POST['national_id']??'') ?>" required></div>
      <div class="field"><label>شماره موبایل</label><input type="tel" name="phone" placeholder="۰۹۱۲۳۴۵۶۷۸۹" value="<?= htmlspecialchars($_POST['phone']??'') ?>" required></div>
    </div>
  </div>

  <!-- Card 2: بیمه -->
  <div class="form-card">
    <div class="card-header">
      <div class="card-num">۲</div>
      <div><div class="card-title">نوع بیمه</div><div class="card-subtitle">یکی از بیمه‌های درمانی را انتخاب کنید</div></div>
    </div>
    <div class="insurance-grid">
      <?php foreach([['val'=>'تامین اجتماعی','icon'=>'🏭'],['val'=>'خدمات درمانی','icon'=>'🏥'],['val'=>'بیمه سلامت','icon'=>'💚']] as $ins): ?>
      <label class="ins-option">
        <input type="radio" name="insurance" value="<?= $ins['val'] ?>" <?= ($_POST['insurance']??'')===$ins['val']?'checked':'' ?> required>
        <span class="ins-label"><span class="ins-icon"><?= $ins['icon'] ?></span><span class="ins-name"><?= $ins['val'] ?></span></span>
      </label>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Card 3: آزمایش‌ها -->
  <div class="form-card">
    <div class="card-header">
      <div class="card-num">۳</div>
      <div><div class="card-title">نوع آزمایش / چکاپ</div><div class="card-subtitle">یک یا چند آزمایش انتخاب کنید</div></div>
    </div>
    <div class="tests-grid">
      <?php $sel=(array)($_POST['tests']??[]); foreach($tests_list as $val=>$t): ?>
      <label class="test-option">
        <input type="checkbox" name="tests[]" value="<?= $val ?>" <?= in_array($val,$sel,true)?'checked':'' ?>>
        <span class="test-label">
          <span class="test-icon"><?= $t['icon'] ?></span>
          <span class="test-info"><span class="test-name"><?= $t['label'] ?></span><span class="test-desc"><?= $t['desc'] ?></span></span>
          <span class="test-check"></span>
        </span>
      </label>
      <?php endforeach; ?>
    </div>
    <div class="order-summary" id="summary"><span style="color:var(--muted)">آزمایش‌های انتخابی اینجا نمایش داده می‌شوند...</span></div>
  </div>

  <!-- Card 4: توضیحات -->
  <div class="form-card">
    <div class="card-header">
      <div class="card-num">۴</div>
      <div><div class="card-title">توضیحات تکمیلی</div><div class="card-subtitle">اختیاری</div></div>
    </div>
    <div class="field">
      <textarea name="notes" placeholder="مثلاً: ناشتا بودن، داروهای مصرفی..."><?= htmlspecialchars($_POST['notes']??'') ?></textarea>
    </div>
  </div>

  <button type="submit" name="submit_order" class="btn-pay">
    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
    پرداخت از طریق زرین‌پال
    <span class="btn-amount"><?= number_format(ZP_AMOUNT) ?> تومان</span>
  </button>
  <div class="zp-badge">🔒 پرداخت امن از طریق درگاه زرین‌پال</div>

</form>
<?php endif; ?>

  <div class="footer-note">طراحی و توسعه: <a href="tel:09168351210">حسین قالوندی</a></div>

  <div style="text-align:center;margin-top:20px">
    <a referrerpolicy='origin' target='_blank' href='https://trustseal.enamad.ir/?id=712959&Code=FYjF8usa4Vya51E5SVyBTTjhFLhs8vGJ'>
      <img referrerpolicy='origin' src='https://trustseal.enamad.ir/logo.aspx?id=712959&Code=FYjF8usa4Vya51E5SVyBTTjhFLhs8vGJ' alt='نماد اعتماد الکترونیک' style='cursor:pointer;height:80px;opacity:.85;transition:opacity .2s' onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='.85'" code='FYjF8usa4Vya51E5SVyBTTjhFLhs8vGJ'>
    </a>
  </div>
</div>

<script>
const checkboxes=document.querySelectorAll('input[name="tests[]"]');
const summary=document.getElementById('summary');
function updateSummary(){
  const checked=[...checkboxes].filter(c=>c.checked);
  if(!checked.length){summary.innerHTML='<span style="color:var(--muted)">آزمایش‌های انتخابی اینجا نمایش داده می‌شوند...</span>';return;}
  const labels=checked.map(c=>`<span class="summary-badge">${c.closest('label').querySelector('.test-name').textContent}</span>`);
  summary.innerHTML=`<div style="font-size:12px;color:var(--muted2);margin-bottom:8px">${checked.length} آزمایش انتخاب شده</div><div class="summary-tests">${labels.join('')}</div>`;
}
if(checkboxes.length){ checkboxes.forEach(c=>c.addEventListener('change',updateSummary)); updateSummary(); }
</script>
</body>
</html>
