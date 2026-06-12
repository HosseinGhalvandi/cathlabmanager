<?php
// =============================================
//  queue.php — نمایش صف انتظار هر پزشک
// =============================================

$db_file       = __DIR__ . '/data/prescriptions.json';
$admitted_file = __DIR__ . '/data/admitted.json';

$doctors = array(
    'kardooni'   => array('name'=>'دکتر کردونی',    'key'=>'دکتر کردونی',   'color'=>'#38bdf8','glow'=>'rgba(56,189,248,'),
    'mohammadi'  => array('name'=>'دکتر محمدی',     'key'=>'دکتر محمدی',    'color'=>'#34d399','glow'=>'rgba(52,211,153,'),
    'taghizadeh' => array('name'=>'دکتر تقی زاده',  'key'=>'دکتر تقی زاده', 'color'=>'#f59e0b','glow'=>'rgba(245,158,11,'),
    'hamid'      => array('name'=>'دکتر حمید',      'key'=>'دکتر حمید',     'color'=>'#f87171','glow'=>'rgba(248,113,113,'),
);

$slug = isset($_GET['dr']) ? preg_replace('/[^a-z]/','',$_GET['dr']) : '';
$doctor = isset($doctors[$slug]) ? $doctors[$slug] : null;

function loadJson($f){
    if(!file_exists($f)) return array();
    $d = json_decode(file_get_contents($f), true);
    return is_array($d) ? $d : array();
}

function getQueue($db_file, $admitted_file, $doctor_key){
    // pending/confirmed in prescriptions
    $all = loadJson($db_file);
    $count = 0;
    foreach($all as $r){
        if($r['doctor_name']===$doctor_key && in_array($r['status'], array('pending','confirmed')))
            $count++;
    }
    return $count;
}

if($doctor){
    $queue_count = getQueue($db_file, $admitted_file, $doctor['key']);
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $doctor ? htmlspecialchars($doctor['name']) : 'صف انتظار کت‌لب'; ?> | کت‌لب</title>
<meta name="description" content="نمایش صف انتظار پزشکان کت‌لب بیمارستان گلستان اهواز">
<meta name="theme-color" content="#060e1a">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="کت‌لب">
<link rel="apple-touch-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%23060e1a'/><text y='.9em' font-size='80' x='10'>🏥</text></svg>">
<link rel="manifest" href="queue-manifest.json">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;700;900&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
html,body{height:100%}
body{
  font-family:'Vazirmatn',sans-serif;
  background:#060e1a;
  color:#e2e8f0;
  direction:rtl;
  min-height:100vh;
  overflow-x:hidden;
}

/* Animated background */
.bg{
  position:fixed;inset:0;z-index:0;
  background:
    radial-gradient(ellipse 60% 60% at 50% 0%, rgba(56,189,248,0.06) 0%, transparent 70%),
    radial-gradient(ellipse 40% 40% at 80% 80%, rgba(14,165,233,0.04) 0%, transparent 60%);
}
.grid{
  position:fixed;inset:0;z-index:0;
  background-image:
    linear-gradient(rgba(56,189,248,0.03) 1px, transparent 1px),
    linear-gradient(90deg, rgba(56,189,248,0.03) 1px, transparent 1px);
  background-size:60px 60px;
}

/* === HOME: doctor selection === */
.home{
  position:relative;z-index:1;
  min-height:100vh;
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  padding:40px 20px;
}
.home-header{text-align:center;margin-bottom:52px}
.home-label{
  display:inline-flex;align-items:center;gap:8px;
  border:1px solid rgba(56,189,248,0.2);
  border-radius:100px;padding:6px 18px;
  font-size:12px;color:#38bdf8;letter-spacing:.08em;
  margin-bottom:22px;
  background:rgba(56,189,248,0.06);
}
.pulse-dot{width:6px;height:6px;background:#38bdf8;border-radius:50%;animation:pulse 2s infinite}
.home-title{font-size:clamp(28px,6vw,48px);font-weight:900;line-height:1.2;margin-bottom:14px}
.home-title em{font-style:normal;color:#38bdf8}
.home-sub{font-size:15px;color:#64748b;line-height:1.8}

.doctors-grid{
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(240px,1fr));
  gap:16px;
  width:100%;max-width:860px;
}

.doc-card{
  position:relative;
  background:rgba(17,24,39,0.8);
  border:1px solid rgba(255,255,255,0.06);
  border-radius:20px;
  padding:32px 24px;
  text-decoration:none;
  color:inherit;
  display:flex;flex-direction:column;align-items:center;
  text-align:center;
  transition:all .3s cubic-bezier(.34,1.56,.64,1);
  overflow:hidden;
  backdrop-filter:blur(10px);
}
.doc-card::before{
  content:'';position:absolute;inset:0;
  background:linear-gradient(135deg,rgba(255,255,255,0.03) 0%,transparent 60%);
  opacity:0;transition:opacity .3s;
}
.doc-card:hover{
  transform:translateY(-6px) scale(1.02);
  border-color:var(--c);
  box-shadow:0 20px 48px var(--gs)0.2);
}
.doc-card:hover::before{opacity:1}

.doc-avatar{
  width:72px;height:72px;border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  font-size:28px;margin-bottom:16px;
  background:var(--gs)0.1);
  border:2px solid var(--gs)0.2);
  transition:all .3s;
}
.doc-card:hover .doc-avatar{
  background:var(--gs)0.18);
  border-color:var(--c);
  box-shadow:0 0 20px var(--gs)0.3);
}
.doc-name{font-size:17px;font-weight:700;margin-bottom:6px;color:#fff}
.doc-dept{font-size:12px;color:#64748b;margin-bottom:18px}
.doc-btn{
  display:inline-flex;align-items:center;gap:6px;
  font-size:12px;font-weight:600;
  color:var(--c);
  border:1px solid var(--gs)0.25);
  border-radius:100px;padding:6px 16px;
  transition:all .25s;
  background:var(--gs)0.06);
}
.doc-card:hover .doc-btn{
  background:var(--c);color:#060e1a;
  border-color:var(--c);
}

/* === QUEUE PAGE === */
.qpage{
  position:relative;z-index:1;
  min-height:100vh;
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  padding:40px 20px;
  text-align:center;
}

.back-btn{
  position:fixed;top:24px;right:24px;z-index:10;
  display:inline-flex;align-items:center;gap:6px;
  background:rgba(17,24,39,0.8);
  border:1px solid rgba(255,255,255,0.08);
  border-radius:100px;padding:8px 16px;
  font-size:12px;color:#94a3b8;
  text-decoration:none;transition:all .2s;
  backdrop-filter:blur(10px);
}
.back-btn:hover{color:#e2e8f0;border-color:rgba(255,255,255,0.15)}

.doc-badge{
  display:inline-flex;align-items:center;gap:8px;
  padding:8px 20px;border-radius:100px;
  font-size:13px;font-weight:600;
  margin-bottom:28px;
  animation:fadeDown .6s ease both;
}

.counter-wrap{
  position:relative;
  margin-bottom:36px;
  animation:scaleIn .7s cubic-bezier(.34,1.56,.64,1) .1s both;
}
.counter-ring{
  width:220px;height:220px;
  border-radius:50%;
  display:flex;flex-direction:column;
  align-items:center;justify-content:center;
  margin:0 auto;
  position:relative;
}
.counter-ring::before{
  content:'';
  position:absolute;inset:-3px;
  border-radius:50%;
  background:conic-gradient(var(--c) 0deg, rgba(255,255,255,0.05) 0deg);
  animation:spin 8s linear infinite;
}
.counter-ring::after{
  content:'';
  position:absolute;inset:3px;
  border-radius:50%;
  background:#060e1a;
}
.counter-inner{
  position:relative;z-index:1;
  display:flex;flex-direction:column;
  align-items:center;justify-content:center;
}
.counter-num{
  font-size:88px;font-weight:900;line-height:1;
  color:var(--c);
  text-shadow:0 0 40px var(--gs)0.5);
  animation:countUp .8s ease .3s both;
}
.counter-label{font-size:13px;color:#64748b;margin-top:4px}

.status-msg{
  font-size:22px;font-weight:700;color:#fff;
  margin-bottom:12px;
  animation:fadeUp .6s ease .2s both;
}
.status-sub{
  font-size:14px;color:#64748b;line-height:1.8;
  max-width:360px;margin:0 auto 36px;
  animation:fadeUp .6s ease .3s both;
}

.refresh-btn{
  display:inline-flex;align-items:center;gap:8px;
  background:rgba(56,189,248,0.08);
  border:1px solid rgba(56,189,248,0.2);
  color:#38bdf8;border-radius:12px;
  padding:12px 24px;font-family:'Vazirmatn',sans-serif;
  font-size:13px;font-weight:600;cursor:pointer;
  transition:all .2s;text-decoration:none;
  animation:fadeUp .6s ease .4s both;
}
.refresh-btn:hover{background:rgba(56,189,248,0.15);transform:translateY(-1px)}

.auto-refresh{
  margin-top:16px;font-size:11px;color:#334155;
  animation:fadeUp .6s ease .5s both;
}
.auto-refresh span{color:#475569}

/* Glow effect on big numbers */
.zero-state .counter-num{color:#1e293b;text-shadow:none}
.zero-state .counter-ring::before{background:conic-gradient(#1e293b 0deg,rgba(255,255,255,0.02) 0deg)}

@keyframes fadeDown{from{opacity:0;transform:translateY(-16px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
@keyframes scaleIn{from{opacity:0;transform:scale(.6)}to{opacity:1;transform:scale(1)}}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.6)}}
@keyframes spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
@keyframes countUp{from{opacity:0;transform:scale(.5) translateY(20px)}to{opacity:1;transform:scale(1) translateY(0)}}

/* Install button */
.install-bar{
  position:fixed;bottom:24px;left:50%;transform:translateX(-50%);
  z-index:100;display:none;
  animation:slideUp .5s cubic-bezier(.34,1.56,.64,1) both;
}
.install-btn{
  display:flex;align-items:center;gap:10px;
  background:rgba(17,24,39,0.95);
  border:1px solid rgba(56,189,248,0.3);
  border-radius:100px;
  padding:12px 22px;
  font-family:'Vazirmatn',sans-serif;
  font-size:13px;font-weight:700;
  color:#e2e8f0;cursor:pointer;
  backdrop-filter:blur(16px);
  box-shadow:0 8px 32px rgba(0,0,0,0.5),0 0 0 1px rgba(56,189,248,0.1);
  transition:all .2s;white-space:nowrap;
  -webkit-tap-highlight-color:transparent;
}
.install-btn:hover{border-color:rgba(56,189,248,0.6);box-shadow:0 8px 32px rgba(0,0,0,0.5),0 0 20px rgba(56,189,248,0.15)}
.install-btn:active{transform:scale(.97)}
.install-icon{font-size:20px}
.install-arrow{font-size:18px;animation:bounce 1.5s infinite}

/* iOS guide modal */
.ios-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:200;align-items:flex-end;justify-content:center;padding:0}
.ios-overlay.show{display:flex}
.ios-modal{
  background:#1a2537;
  border:1px solid rgba(56,189,248,0.2);
  border-radius:24px 24px 0 0;
  padding:28px 24px 40px;
  width:100%;max-width:480px;
  text-align:center;
  animation:slideUp .35s ease both;
}
.ios-modal h3{font-size:17px;font-weight:800;margin-bottom:8px}
.ios-modal p{font-size:13px;color:#94a3b8;margin-bottom:22px;line-height:1.7}
.ios-steps{display:flex;flex-direction:column;gap:12px;margin-bottom:24px;text-align:right}
.ios-step{display:flex;align-items:center;gap:12px;background:rgba(56,189,248,0.05);border:1px solid rgba(56,189,248,0.1);border-radius:12px;padding:12px 14px}
.ios-step-num{width:26px;height:26px;background:rgba(56,189,248,0.15);color:#38bdf8;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;flex-shrink:0}
.ios-step-text{font-size:13px;color:#e2e8f0;line-height:1.5}
.ios-step-text em{color:#38bdf8;font-style:normal;font-weight:700}
.ios-close{width:100%;padding:13px;background:linear-gradient(135deg,#0ea5e9,#2563eb);color:#fff;border:none;border-radius:12px;font-family:'Vazirmatn',sans-serif;font-size:14px;font-weight:700;cursor:pointer}

@keyframes slideUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
@keyframes bounce{0%,100%{transform:translateY(0)}50%{transform:translateY(-4px)}}
</style>
</head>
<body>
<div class="bg"></div>
<div class="grid"></div>

<?php if(!$doctor): ?>
<!-- ===== HOME: انتخاب پزشک ===== -->
<div class="home">
  <div class="home-header">
    <div class="home-label"><span class="pulse-dot"></span>اتاق عمل کت‌لب</div>
    <h1 class="home-title">صف انتظار<br><em>پزشکان</em></h1>
    <p class="home-sub">پزشک مورد نظر را انتخاب کنید<br>تا تعداد بیماران در انتظار نمایش داده شود</p>
  </div>

  <div class="doctors-grid">
    <?php foreach($doctors as $slug=>$doc): ?>
    <?php
      $cnt = getQueue($db_file, $admitted_file, $doc['key']);
    ?>
    <a href="?dr=<?php echo $slug; ?>"
       class="doc-card"
       style="--c:<?php echo $doc['color']; ?>;--gs:<?php echo $doc['glow']; ?>">
      <div class="doc-avatar">👨‍⚕️</div>
      <div class="doc-name"><?php echo htmlspecialchars($doc['name']); ?></div>
      <div class="doc-dept">کاردیولوژی — کت‌لب</div>
      <div class="doc-btn">
        <span style="font-size:18px;font-weight:900;color:<?php echo $doc['color']; ?>"><?php echo $cnt; ?></span>
        نفر در انتظار
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<?php else: ?>
<!-- ===== QUEUE PAGE ===== -->
<a href="queue.php" class="back-btn">
  <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
  بازگشت
</a>

<div class="qpage <?php echo $queue_count===0?'zero-state':''; ?>"
     style="--c:<?php echo $doctor['color']; ?>;--gs:<?php echo $doctor['glow']; ?>">

  <div class="doc-badge"
       style="background:<?php echo $doctor['glow']; ?>0.1);border:1px solid <?php echo $doctor['glow']; ?>0.25);color:<?php echo $doctor['color']; ?>">
    👨‍⚕️ <?php echo htmlspecialchars($doctor['name']); ?>
  </div>

  <div class="counter-wrap">
    <div class="counter-ring">
      <div class="counter-inner">
        <div class="counter-num"><?php echo $queue_count; ?></div>
        <div class="counter-label">بیمار</div>
      </div>
    </div>
  </div>

  <?php if($queue_count === 0): ?>
  <div class="status-msg">لیست انتظار خالی است</div>
  <div class="status-sub">در حال حاضر هیچ بیماری در صف انتظار <?php echo htmlspecialchars($doctor['name']); ?> قرار ندارد</div>
  <?php elseif($queue_count <= 3): ?>
  <div class="status-msg">صف کوتاه است</div>
  <div class="status-sub"><?php echo $queue_count; ?> بیمار در صف انتظار <?php echo htmlspecialchars($doctor['name']); ?> قرار دارند</div>
  <?php elseif($queue_count <= 7): ?>
  <div class="status-msg">صف متوسط</div>
  <div class="status-sub"><?php echo $queue_count; ?> بیمار در صف انتظار هستند</div>
  <?php else: ?>
  <div class="status-msg">صف شلوغ است</div>
  <div class="status-sub"><?php echo $queue_count; ?> بیمار در انتظار نوبت هستند</div>
  <?php endif; ?>

  <a href="?dr=<?php echo $slug; ?>" class="refresh-btn">
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
    بروزرسانی
  </a>
  <div class="auto-refresh">بروزرسانی خودکار هر <span id="countdown">30</span> ثانیه</div>
</div>

<script>
var t=30;
var el=document.getElementById('countdown');
setInterval(function(){
  t--;
  if(el) el.textContent=t;
  if(t<=0){ window.location.reload(); }
},1000);
</script>

<?php endif; ?>
<!-- Install Bar -->
<div class="install-bar" id="install-bar">
  <button class="install-btn" id="install-btn" onclick="handleInstall()">
    <span class="install-icon">📲</span>
    <span>افزودن به صفحه اصلی</span>
    <span class="install-arrow">↑</span>
  </button>
</div>

<!-- iOS Guide Modal -->
<div class="ios-overlay" id="ios-overlay" onclick="closeIOS()">
  <div class="ios-modal" onclick="event.stopPropagation()">
    <h3>📲 افزودن به صفحه اصلی iPhone</h3>
    <p>برای نصب این صفحه به عنوان اپ، مراحل زیر را دنبال کنید:</p>
    <div class="ios-steps">
      <div class="ios-step">
        <div class="ios-step-num">۱</div>
        <div class="ios-step-text">در Safari دکمه <em>اشتراک‌گذاری</em> (□↑) را در پایین مرورگر بزنید</div>
      </div>
      <div class="ios-step">
        <div class="ios-step-num">۲</div>
        <div class="ios-step-text">گزینه <em>«Add to Home Screen»</em> را انتخاب کنید</div>
      </div>
      <div class="ios-step">
        <div class="ios-step-num">۳</div>
        <div class="ios-step-text">روی <em>«Add»</em> در گوشه بالا راست بزنید</div>
      </div>
    </div>
    <button class="ios-close" onclick="closeIOS()">متوجه شدم</button>
  </div>
</div>

<script>
var deferredPrompt = null;
var isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent);
var isAndroid = /android/i.test(navigator.userAgent);
var isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;

// نمایش دکمه فقط روی موبایل و اگه هنوز نصب نشده
if(!isStandalone && (isIOS || isAndroid)){
  document.getElementById('install-bar').style.display = 'flex';
}

// برای Android: دریافت رویداد beforeinstallprompt
window.addEventListener('beforeinstallprompt', function(e){
  e.preventDefault();
  deferredPrompt = e;
  document.getElementById('install-bar').style.display = 'flex';
});

function handleInstall(){
  if(deferredPrompt){
    // Android: نمایش دیالوگ نصب
    deferredPrompt.prompt();
    deferredPrompt.userChoice.then(function(result){
      deferredPrompt = null;
      if(result.outcome === 'accepted'){
        document.getElementById('install-bar').style.display = 'none';
      }
    });
  } else if(isIOS){
    // iOS: نمایش راهنما
    document.getElementById('ios-overlay').classList.add('show');
  }
}

function closeIOS(){
  document.getElementById('ios-overlay').classList.remove('show');
}

// پنهان کردن بعد از نصب
window.addEventListener('appinstalled', function(){
  document.getElementById('install-bar').style.display = 'none';
});
</script>
</body>
</html>
