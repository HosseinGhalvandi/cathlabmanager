<?php
// =============================================
//  queue-all.php — نمایش کل صف انتظار کت‌لب
// =============================================

$db_file = __DIR__ . '/data/prescriptions.json';

$doctors = array(
    'دکتر کردونی'   => array('color'=>'#38bdf8','glow'=>'rgba(56,189,248,','slug'=>'kardooni'),
    'دکتر محمدی'    => array('color'=>'#34d399','glow'=>'rgba(52,211,153,','slug'=>'mohammadi'),
    'دکتر تقی زاده' => array('color'=>'#f59e0b','glow'=>'rgba(245,158,11,','slug'=>'taghizadeh'),
    'دکتر حمید'     => array('color'=>'#f87171','glow'=>'rgba(248,113,113,','slug'=>'hamid'),
);

function loadJson($f){
    if(!file_exists($f)) return array();
    $d = json_decode(file_get_contents($f), true);
    return is_array($d) ? $d : array();
}

$all = loadJson($db_file);

// Count per doctor
$stats = array();
$total = 0;
foreach($doctors as $name => $info){
    $count = 0;
    foreach($all as $r){
        if($r['doctor_name']===$name && in_array($r['status'], array('pending','confirmed')))
            $count++;
    }
    $stats[$name] = $count;
    $total += $count;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>صف انتظار کت‌لب | بیمارستان گلستان</title>
<meta name="theme-color" content="#060e1a">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="کت‌لب">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;700;900&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{
  font-family:'Vazirmatn',sans-serif;
  background:#060e1a;
  color:#e2e8f0;
  direction:rtl;
  min-height:100vh;
  overflow-x:hidden;
}
.bg{position:fixed;inset:0;z-index:0;background:radial-gradient(ellipse 70% 50% at 50% 0%,rgba(56,189,248,.06) 0%,transparent 70%)}
.grid{position:fixed;inset:0;z-index:0;background-image:linear-gradient(rgba(56,189,248,.025) 1px,transparent 1px),linear-gradient(90deg,rgba(56,189,248,.025) 1px,transparent 1px);background-size:60px 60px}

.wrap{position:relative;z-index:1;max-width:720px;margin:0 auto;padding:40px 20px 100px}

/* Header */
.header{text-align:center;margin-bottom:44px;animation:fadeDown .7s ease both}
.label{display:inline-flex;align-items:center;gap:8px;border:1px solid rgba(56,189,248,.2);border-radius:100px;padding:6px 18px;font-size:12px;color:#38bdf8;letter-spacing:.08em;margin-bottom:20px;background:rgba(56,189,248,.06)}
.pdot{width:6px;height:6px;background:#38bdf8;border-radius:50%;animation:pulse 2s infinite}
.header h1{font-size:clamp(26px,6vw,42px);font-weight:900;line-height:1.2;margin-bottom:10px}
.header h1 em{font-style:normal;background:linear-gradient(135deg,#38bdf8,#34d399);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.header p{font-size:14px;color:#64748b}

/* Total counter */
.total-card{
  background:rgba(17,24,39,.8);
  border:1px solid rgba(56,189,248,.15);
  border-radius:24px;
  padding:36px 24px;
  text-align:center;
  margin-bottom:24px;
  position:relative;overflow:hidden;
  animation:fadeUp .7s ease .1s both;
  backdrop-filter:blur(10px);
}
.total-card::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(56,189,248,.04) 0%,transparent 60%);pointer-events:none}
.total-label{font-size:13px;color:#64748b;margin-bottom:8px;letter-spacing:.05em}
.total-num{
  font-size:96px;font-weight:900;line-height:1;
  background:linear-gradient(135deg,#38bdf8,#34d399);
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
  text-shadow:none;
  animation:countUp .8s cubic-bezier(.34,1.56,.64,1) .2s both;
}
.total-sub{font-size:14px;color:#64748b;margin-top:8px}

/* Doctor cards */
.doctors{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:24px}
@media(max-width:420px){.doctors{grid-template-columns:1fr}}

.dc{
  background:rgba(17,24,39,.7);
  border:1px solid rgba(255,255,255,.06);
  border-radius:18px;
  padding:22px 18px;
  display:flex;align-items:center;gap:16px;
  animation:fadeUp .6s ease both;
  backdrop-filter:blur(8px);
  transition:all .3s;
  text-decoration:none;color:inherit;
}
.dc:hover{transform:translateY(-3px);border-color:var(--c);box-shadow:0 12px 32px var(--gs)0.15)}
.dc-circle{
  width:60px;height:60px;border-radius:50%;
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  background:var(--gs)0.1);
  border:2px solid var(--gs)0.2);
  flex-shrink:0;transition:all .3s;
}
.dc:hover .dc-circle{box-shadow:0 0 16px var(--gs)0.35)}
.dc-num{font-size:24px;font-weight:900;color:var(--c);line-height:1}
.dc-unit{font-size:10px;color:var(--gs)0.8);margin-top:1px}
.dc-info{flex:1;min-width:0}
.dc-name{font-size:14px;font-weight:700;margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.dc-bar-wrap{height:5px;background:rgba(255,255,255,.06);border-radius:100px;overflow:hidden}
.dc-bar{height:100%;border-radius:100px;background:var(--c);transition:width 1s cubic-bezier(.34,1.56,.64,1)}
.dc-pct{font-size:10px;color:#475569;margin-top:4px}

/* Divider */
.divider{display:flex;align-items:center;gap:12px;margin-bottom:24px;color:#1e293b;font-size:11px}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:rgba(255,255,255,.04)}

/* Last refresh */
.refresh-info{
  text-align:center;margin-bottom:16px;
  animation:fadeUp .6s ease .4s both;
}
.refresh-btn{
  display:inline-flex;align-items:center;gap:8px;
  background:rgba(56,189,248,.07);
  border:1px solid rgba(56,189,248,.18);
  color:#38bdf8;border-radius:12px;
  padding:11px 22px;font-family:'Vazirmatn',sans-serif;
  font-size:13px;font-weight:600;cursor:pointer;
  transition:all .2s;text-decoration:none;
}
.refresh-btn:hover{background:rgba(56,189,248,.13);transform:translateY(-1px)}
.countdown{font-size:12px;color:#334155;margin-top:10px}
.countdown span{color:#475569;font-weight:600}

.back-link{display:block;text-align:center;margin-top:20px;font-size:13px;color:#334155;text-decoration:none;transition:color .2s}
.back-link:hover{color:#64748b}

/* Install */
.install-bar{position:fixed;bottom:24px;left:50%;transform:translateX(-50%);z-index:100;display:none;animation:slideUp .5s cubic-bezier(.34,1.56,.64,1) both}
.install-btn{display:flex;align-items:center;gap:10px;background:rgba(17,24,39,.95);border:1px solid rgba(56,189,248,.3);border-radius:100px;padding:12px 22px;font-family:'Vazirmatn',sans-serif;font-size:13px;font-weight:700;color:#e2e8f0;cursor:pointer;backdrop-filter:blur(16px);box-shadow:0 8px 32px rgba(0,0,0,.5);transition:all .2s;white-space:nowrap}
.install-btn:hover{border-color:rgba(56,189,248,.6)}
.ios-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:200;align-items:flex-end;justify-content:center}
.ios-overlay.show{display:flex}
.ios-modal{background:#1a2537;border:1px solid rgba(56,189,248,.2);border-radius:24px 24px 0 0;padding:28px 24px 40px;width:100%;max-width:480px;text-align:center;animation:slideUp .35s ease both}
.ios-modal h3{font-size:17px;font-weight:800;margin-bottom:8px}
.ios-modal p{font-size:13px;color:#94a3b8;margin-bottom:22px;line-height:1.7}
.ios-steps{display:flex;flex-direction:column;gap:10px;margin-bottom:22px;text-align:right}
.ios-step{display:flex;align-items:center;gap:12px;background:rgba(56,189,248,.05);border:1px solid rgba(56,189,248,.1);border-radius:12px;padding:11px 14px}
.ios-sn{width:24px;height:24px;background:rgba(56,189,248,.15);color:#38bdf8;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0}
.ios-st{font-size:12px;color:#e2e8f0;line-height:1.5}
.ios-st em{color:#38bdf8;font-style:normal;font-weight:700}
.ios-close{width:100%;padding:13px;background:linear-gradient(135deg,#0ea5e9,#2563eb);color:#fff;border:none;border-radius:12px;font-family:'Vazirmatn',sans-serif;font-size:14px;font-weight:700;cursor:pointer}

@keyframes fadeDown{from{opacity:0;transform:translateY(-16px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.6)}}
@keyframes countUp{from{opacity:0;transform:scale(.5)}to{opacity:1;transform:scale(1)}}
@keyframes slideUp{from{opacity:0;transform:translate(-50%,20px)}to{opacity:1;transform:translate(-50%,0)}}
</style>
</head>
<body>
<div class="bg"></div>
<div class="grid"></div>

<div class="wrap">

  <div class="header">
    <div class="label"><span class="pdot"></span>اتاق عمل کت‌لب</div>
    <h1>مجموع صف<br><em>انتظار کت‌لب</em></h1>
    <p>بیمارستان گلستان اهواز</p>
  </div>

  <!-- Total -->
  <div class="total-card">
    <div class="total-label">مجموع بیماران در انتظار</div>
    <div class="total-num"><?php echo $total; ?></div>
    <div class="total-sub">
      <?php if($total===0): ?>هیچ بیماری در صف انتظار نیست
      <?php elseif($total<=5): ?>صف کوتاه است
      <?php elseif($total<=15): ?>صف متوسط است
      <?php else: ?>صف شلوغ است
      <?php endif; ?>
    </div>
  </div>

  <div class="divider">به تفکیک پزشک</div>

  <!-- Doctors -->
  <div class="doctors">
  <?php
  $delay = 2;
  foreach($doctors as $name => $info):
    $cnt = $stats[$name];
    $pct = $total > 0 ? round(($cnt/$total)*100) : 0;
  ?>
  <a href="queue.php?dr=<?php echo $info['slug']; ?>"
     class="dc"
     style="--c:<?php echo $info['color']; ?>;--gs:<?php echo $info['glow']; ?>;animation-delay:<?php echo ($delay*0.08); ?>s">
    <div class="dc-circle">
      <div class="dc-num"><?php echo $cnt; ?></div>
      <div class="dc-unit">نفر</div>
    </div>
    <div class="dc-info">
      <div class="dc-name"><?php echo htmlspecialchars($name); ?></div>
      <div class="dc-bar-wrap">
        <div class="dc-bar" style="width:<?php echo $pct; ?>%"></div>
      </div>
      <div class="dc-pct">
        <?php echo $pct; ?>٪ از کل
        <?php if($cnt===$total&&$total>0): ?> • بیشترین<?php endif; ?>
      </div>
    </div>
  </a>
  <?php $delay++; endforeach; ?>
  </div>

  <!-- Refresh -->
  <div class="refresh-info">
    <a href="queue-all.php" class="refresh-btn">
      <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
      بروزرسانی
    </a>
    <div class="countdown">بروزرسانی خودکار هر <span id="cd">30</span> ثانیه</div>
  </div>

  <a href="queue.php" class="back-link">← صفحه پزشکان</a>

</div>

<!-- Install Bar -->
<div class="install-bar" id="install-bar">
  <button class="install-btn" id="install-btn" onclick="handleInstall()">
    📲 افزودن به صفحه اصلی
  </button>
</div>

<!-- iOS Modal -->
<div class="ios-overlay" id="ios-overlay" onclick="closeIOS()">
  <div class="ios-modal" onclick="event.stopPropagation()">
    <h3>📲 افزودن به صفحه اصلی iPhone</h3>
    <p>در Safari مراحل زیر را انجام دهید:</p>
    <div class="ios-steps">
      <div class="ios-step"><div class="ios-sn">۱</div><div class="ios-st">دکمه <em>اشتراک‌گذاری</em> (□↑) را در پایین بزنید</div></div>
      <div class="ios-step"><div class="ios-sn">۲</div><div class="ios-st">گزینه <em>«Add to Home Screen»</em> را انتخاب کنید</div></div>
      <div class="ios-step"><div class="ios-sn">۳</div><div class="ios-st">روی <em>«Add»</em> بزنید</div></div>
    </div>
    <button class="ios-close" onclick="closeIOS()">متوجه شدم</button>
  </div>
</div>

<script>
// Countdown
var t=30, el=document.getElementById('cd');
setInterval(function(){ t--; if(el) el.textContent=t; if(t<=0) window.location.reload(); },1000);

// Install
var deferredPrompt=null;
var isIOS=/iphone|ipad|ipod/i.test(navigator.userAgent);
var isAndroid=/android/i.test(navigator.userAgent);
var isStandalone=window.matchMedia('(display-mode: standalone)').matches||window.navigator.standalone;
if(!isStandalone&&(isIOS||isAndroid)) document.getElementById('install-bar').style.display='flex';
window.addEventListener('beforeinstallprompt',function(e){ e.preventDefault(); deferredPrompt=e; document.getElementById('install-bar').style.display='flex'; });
function handleInstall(){
  if(deferredPrompt){ deferredPrompt.prompt(); deferredPrompt.userChoice.then(function(r){ deferredPrompt=null; if(r.outcome==='accepted') document.getElementById('install-bar').style.display='none'; }); }
  else if(isIOS) document.getElementById('ios-overlay').classList.add('show');
}
function closeIOS(){ document.getElementById('ios-overlay').classList.remove('show'); }
window.addEventListener('appinstalled',function(){ document.getElementById('install-bar').style.display='none'; });
</script>
</body>
</html>
