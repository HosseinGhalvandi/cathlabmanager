<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// =============================================
//  index.php — فرم ارسال نسخه + پیگیری
// =============================================

require_once __DIR__ . '/config.php';

$pdo = null;
try {
    $pdo = getDB();
} catch(Exception $e){
    die('<div style="font-family:sans-serif;direction:rtl;padding:30px;color:red">خطا در اتصال: '.$e->getMessage().'</div>');
}

$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

// ---- پیگیری ----
$track_result = null;
$track_error  = null;
$active_tab   = 'submit';

if (isset($_POST['search_type'])) {
    $active_tab  = 'track';
    $search_type = $_POST['search_type'];

    if ($search_type === 'name' && !empty($_POST['track_name'])) {
        $name_q = trim($_POST['track_name']);
        $patient = null; $track_stage = null;
        foreach (array('completed','admitted','prescriptions') as $tbl) {
            $order = ($tbl==='completed')?'completed_at':(($tbl==='admitted')?'admitted_at':'submitted_at');
            $s = $pdo->prepare("SELECT * FROM $tbl WHERE CONCAT(first_name,' ',last_name) LIKE ? ORDER BY $order DESC LIMIT 1");
            $s->execute(array('%'.$name_q.'%'));
            $row = $s->fetch();
            if ($row) {
                $patient     = $row;
                $track_stage = ($tbl==='prescriptions')?'queue':(($tbl==='admitted')?'admitted':'completed');
                break;
            }
        }
        if (!$patient) {
            $track_error = 'نامی با این مشخصات در سیستم یافت نشد.';
        } else {
            $doctor = $patient['doctor_name'];
            if ($track_stage === 'queue') {
                $q = $pdo->prepare("SELECT * FROM prescriptions WHERE doctor_name=? AND status IN ('pending','confirmed') ORDER BY submitted_at ASC");
                $q->execute(array($doctor)); $queue = $q->fetchAll();
                $position = null;
                foreach ($queue as $idx => $r) {
                    if ($r['id'] === $patient['id']) { $position = $idx + 1; break; }
                }
                $track_result = array('stage'=>'queue','patient'=>$patient,'doctor'=>$doctor,'position'=>$position,'queue_size'=>count($queue),'ahead'=>($position?$position-1:0));
            } elseif ($track_stage === 'admitted') {
                $track_result = array('stage'=>'admitted','patient'=>$patient,'doctor'=>$doctor);
            } else {
                $oc = isset($patient['outcome'])?$patient['outcome']:'—';
                $ocC = array('نرمال'=>array('#34d399','rgba(52,211,153,'),'PCI'=>array('#38bdf8','rgba(56,189,248,'),'درمان دارویی'=>array('#fbbf24','rgba(251,191,36,'),'مشاوره جراحی قلب باز'=>array('#f87171','rgba(248,113,113,'));
                $track_result = array('stage'=>'completed','patient'=>$patient,'doctor'=>$doctor,'oc'=>$oc,'oc_color'=>(isset($ocC[$oc])?$ocC[$oc][0]:'#94a3b8'),'oc_bg'=>(isset($ocC[$oc])?$ocC[$oc][1]:'rgba(148,163,184,'));
            }
        }

    } else {
        $nid = preg_replace('/[^\d]/','',trim(isset($_POST['track_national_id'])?$_POST['track_national_id']:''));
        if (strlen($nid) !== 10) {
            $track_error = 'کد ملی باید ۱۰ رقم باشد';
        } else {
            $patient = null; $track_stage = null;
            $s = $pdo->prepare("SELECT * FROM completed WHERE national_id=? ORDER BY completed_at DESC LIMIT 1");
            $s->execute(array($nid)); $row=$s->fetch(); if($row){$patient=$row;$track_stage='completed';}
            if (!$patient) {
                $s = $pdo->prepare("SELECT * FROM admitted WHERE national_id=? ORDER BY admitted_at DESC LIMIT 1");
                $s->execute(array($nid)); $row=$s->fetch(); if($row){$patient=$row;$track_stage='admitted';}
            }
            if (!$patient) {
                $s = $pdo->prepare("SELECT * FROM prescriptions WHERE national_id=? ORDER BY submitted_at DESC LIMIT 1");
                $s->execute(array($nid)); $row=$s->fetch(); if($row){$patient=$row;$track_stage='queue';}
            }
            if (!$patient) {
                $track_error = 'کد ملی در سیستم یافت نشد. لطفاً ابتدا نسخه خود را ارسال کنید.';
            } else {
                $doctor = $patient['doctor_name'];
                if ($track_stage === 'queue') {
                    $q = $pdo->prepare("SELECT * FROM prescriptions WHERE doctor_name=? AND status IN ('pending','confirmed') ORDER BY submitted_at ASC");
                    $q->execute(array($doctor)); $queue=$q->fetchAll();
                    $position=null;
                    foreach($queue as $idx=>$r){ if($r['national_id']===$nid){$position=$idx+1;break;} }
                    $track_result=array('stage'=>'queue','patient'=>$patient,'doctor'=>$doctor,'position'=>$position,'queue_size'=>count($queue),'ahead'=>($position?$position-1:0));
                } elseif ($track_stage === 'admitted') {
                    $track_result=array('stage'=>'admitted','patient'=>$patient,'doctor'=>$doctor);
                } else {
                    $oc=isset($patient['outcome'])?$patient['outcome']:'—';
                    $ocC=array('نرمال'=>array('#34d399','rgba(52,211,153,'),'PCI'=>array('#38bdf8','rgba(56,189,248,'),'درمان دارویی'=>array('#fbbf24','rgba(251,191,36,'),'مشاوره جراحی قلب باز'=>array('#f87171','rgba(248,113,113,'));
                    $track_result=array('stage'=>'completed','patient'=>$patient,'doctor'=>$doctor,'oc'=>$oc,'oc_color'=>(isset($ocC[$oc])?$ocC[$oc][0]:'#94a3b8'),'oc_bg'=>(isset($ocC[$oc])?$ocC[$oc][1]:'rgba(148,163,184,'));
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>نوبت دهی کت لب گلستان اهواز | اتاق عمل آنژیوگرافی بیمارستان گلستان</title>
<meta name="description" content="ارسال آنلاین نسخه و نوبت‌دهی اتاق عمل آنژیوگرافی و کت لب بیمارستان گلستان اهواز. پیگیری وضعیت نوبت با کد ملی.">
<meta name="keywords" content="نوبت دهی کت لب گلستان اهواز, نوبت دهی اتاق عمل انژیوگرافی گلستان اهواز, کت لب بیمارستان گلستان, آنژیوگرافی اهواز, اتاق عمل گلستان اهواز, ارسال نسخه آنلاین, نوبت عمل قلب اهواز">
<meta name="robots" content="index, follow">
<meta name="author" content="حسین قالوندی">
<link rel="canonical" href="https://www.catlab-golestan.ir/">
<meta property="og:title" content="نوبت دهی کت لب و آنژیوگرافی بیمارستان گلستان اهواز">
<meta property="og:description" content="ارسال آنلاین نسخه و پیگیری نوبت اتاق عمل آنژیوگرافی کت لب بیمارستان گلستان اهواز">
<meta property="og:type" content="website">
<meta property="og:locale" content="fa_IR">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root {
  --bg:#0b1120; --surface:#111827; --surface2:#1a2537;
  --border:rgba(99,179,237,0.15); --accent:#38bdf8; --accent2:#0ea5e9;
  --accent-glow:rgba(56,189,248,0.25); --text:#e2e8f0; --text-muted:#94a3b8;
  --success:#34d399; --warn:#fbbf24; --error:#f87171;
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Vazirmatn',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;direction:rtl;overflow-x:hidden}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse 80% 50% at 20% 10%,rgba(56,189,248,0.07) 0%,transparent 60%),radial-gradient(ellipse 60% 40% at 80% 90%,rgba(14,165,233,0.05) 0%,transparent 50%);pointer-events:none;z-index:0}
body::after{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(99,179,237,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(99,179,237,0.03) 1px,transparent 1px);background-size:40px 40px;pointer-events:none;z-index:0}

.container{position:relative;z-index:1;max-width:640px;margin:0 auto;padding:40px 20px 60px}

/* Header */
.header{text-align:center;margin-bottom:36px;animation:fadeDown .7s ease both}
.header-badge{display:inline-flex;align-items:center;gap:8px;background:rgba(56,189,248,0.1);border:1px solid var(--border);border-radius:100px;padding:6px 16px;font-size:12px;color:var(--accent);letter-spacing:.05em;margin-bottom:18px}
.header-badge::before{content:'';width:6px;height:6px;background:var(--accent);border-radius:50%;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(.7)}}
.header h1{font-size:clamp(22px,5vw,30px);font-weight:800;color:#fff;line-height:1.3;margin-bottom:10px}
.header h1 span{color:var(--accent)}
.header p{font-size:14px;color:var(--text-muted);line-height:1.8}

/* Counter */
.counter-wrap{display:inline-flex;align-items:center;gap:10px;background:rgba(56,189,248,0.06);border:1px solid var(--border);border-radius:12px;padding:10px 20px;margin-bottom:20px}
.counter-num{font-size:22px;font-weight:800;color:var(--accent);letter-spacing:.02em}
.counter-label{font-size:12px;color:var(--text-muted);line-height:1.5}
.counter-label strong{display:block;color:var(--text);font-size:13px}

/* Emergency */
.emergency-wrap{display:block;margin-bottom:16px;cursor:pointer}
.emergency-wrap input{display:none}
.emergency-box{
  display:flex;align-items:center;gap:14px;
  padding:14px 16px;
  background:rgba(248,113,113,0.05);
  border:1.5px dashed rgba(248,113,113,0.25);
  border-radius:12px;
  transition:all .2s;
}
.emergency-wrap:hover .emergency-box{border-color:rgba(248,113,113,0.5);background:rgba(248,113,113,0.08)}
.emergency-icon{font-size:22px;flex-shrink:0}
.emergency-text{flex:1}
.emergency-text strong{display:block;font-size:13px;font-weight:700;color:var(--text-muted);margin-bottom:2px;transition:color .2s}
.emergency-text small{font-size:11px;color:rgba(148,163,184,.5)}
.emergency-check-mark{
  width:22px;height:22px;border-radius:6px;
  border:1.5px solid rgba(248,113,113,0.3);
  display:flex;align-items:center;justify-content:center;
  color:transparent;flex-shrink:0;transition:all .2s;
}
.emergency-wrap input:checked + .emergency-box{
  border-style:solid;
  border-color:var(--error);
  background:rgba(248,113,113,0.1);
}
.emergency-wrap input:checked + .emergency-box .emergency-text strong{color:var(--error)}
.emergency-wrap input:checked + .emergency-box .emergency-check-mark{
  background:var(--error);border-color:var(--error);color:#fff;
}

/* Tabs */
.tabs{display:grid;grid-template-columns:1fr 1fr;gap:0;background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:5px;margin-bottom:24px;animation:fadeDown .6s ease .05s both}
.tab-btn{padding:11px 16px;border:none;background:transparent;color:var(--text-muted);font-family:'Vazirmatn',sans-serif;font-size:14px;font-weight:600;cursor:pointer;border-radius:12px;transition:all .22s;display:flex;align-items:center;justify-content:center;gap:8px}
.tab-btn.active{background:linear-gradient(135deg,var(--accent2),#2563eb);color:#fff;box-shadow:0 4px 16px rgba(14,165,233,.3)}
.tab-btn:not(.active):hover{color:var(--text);background:var(--surface2)}

/* Alert */
.alert{border-radius:12px;padding:14px 18px;margin-bottom:24px;font-size:14px;display:flex;align-items:center;gap:10px;animation:fadeDown .4s ease both}
.alert-success{background:rgba(52,211,153,0.1);border:1px solid rgba(52,211,153,0.3);color:var(--success)}
.alert-error{background:rgba(248,113,113,0.1);border:1px solid rgba(248,113,113,0.3);color:var(--error)}

/* Card */
.card{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:36px;animation:fadeUp .7s ease .1s both;box-shadow:0 0 40px rgba(0,0,0,.4),0 0 1px rgba(56,189,248,.1)}
.section-title{font-size:11px;font-weight:700;color:var(--accent);letter-spacing:.15em;text-transform:uppercase;margin-bottom:18px;padding-bottom:10px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
.form-grid.full{grid-template-columns:1fr}
@media(max-width:500px){.form-grid{grid-template-columns:1fr}}
.field{display:flex;flex-direction:column;gap:7px}
.field label{font-size:12px;font-weight:600;color:var(--text-muted);letter-spacing:.03em}
.field label span.req{color:var(--accent);margin-right:2px}
.field input,.field select,.field textarea{background:var(--surface2);border:1px solid var(--border);border-radius:10px;color:var(--text);font-family:'Vazirmatn',sans-serif;font-size:14px;padding:11px 14px;transition:border-color .2s,box-shadow .2s;outline:none;width:100%}
.field input:focus,.field select:focus,.field textarea:focus{border-color:var(--accent2);box-shadow:0 0 0 3px var(--accent-glow)}
.field input::placeholder,.field textarea::placeholder{color:rgba(148,163,184,.4);font-size:13px}
.field select option{background:var(--surface2)}
.field textarea{resize:vertical;min-height:80px}
.divider{height:1px;background:var(--border);margin:24px 0}

/* File upload */
.file-upload-area{border:2px dashed var(--border);border-radius:12px;padding:28px;text-align:center;cursor:pointer;transition:all .25s;background:var(--surface2);position:relative}
.file-upload-area:hover{border-color:var(--accent);background:rgba(56,189,248,.04)}
.file-upload-area.dragover{border-color:var(--accent);background:var(--accent-glow);transform:scale(1.01)}
.file-upload-area input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.file-upload-icon{width:40px;height:40px;margin:0 auto 10px;background:rgba(56,189,248,.1);border-radius:10px;display:flex;align-items:center;justify-content:center;color:var(--accent)}
.file-upload-text{font-size:14px;color:var(--text);margin-bottom:4px}
.file-upload-hint{font-size:12px;color:var(--text-muted)}
#file-preview{margin-top:10px;display:none;align-items:center;gap:8px;background:rgba(52,211,153,.08);border:1px solid rgba(52,211,153,.2);border-radius:8px;padding:8px 12px;font-size:13px;color:var(--success)}

/* Submit btn */
.btn-submit{width:100%;padding:14px;background:linear-gradient(135deg,var(--accent2),#2563eb);color:#fff;border:none;border-radius:12px;font-family:'Vazirmatn',sans-serif;font-size:15px;font-weight:700;cursor:pointer;transition:all .25s;letter-spacing:.02em;position:relative;overflow:hidden}
.btn-submit:hover{transform:translateY(-1px);box-shadow:0 8px 24px rgba(14,165,233,.35)}
.btn-submit:active{transform:translateY(0)}

/* Tracking tab */
.track-search{display:flex;gap:10px;margin-bottom:0;flex-wrap:wrap}
.track-search input{flex:1;min-width:0;background:var(--surface2);border:1px solid var(--border);border-radius:10px;color:var(--text);font-family:'Vazirmatn',sans-serif;font-size:15px;padding:13px 16px;outline:none;transition:border-color .2s,box-shadow .2s;letter-spacing:.08em}
.track-search input:focus{border-color:var(--accent2);box-shadow:0 0 0 3px var(--accent-glow)}
.track-search button{padding:13px 22px;background:linear-gradient(135deg,var(--accent2),#2563eb);color:#fff;border:none;border-radius:10px;font-family:'Vazirmatn',sans-serif;font-size:14px;font-weight:700;cursor:pointer;white-space:nowrap;transition:all .2s;width:100%}
.track-search button:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(14,165,233,.3)}
@media(min-width:420px){.track-search button{width:auto}}

/* Result card */
.result-card{margin-top:24px;animation:fadeUp .4s ease both}

.status-done .result-hero{background:linear-gradient(135deg,rgba(52,211,153,.12),rgba(52,211,153,.04));border-color:rgba(52,211,153,.3)}
.status-waiting .result-hero{background:linear-gradient(135deg,rgba(56,189,248,.1),rgba(56,189,248,.03));border-color:var(--border)}

.result-hero{border:1px solid var(--border);border-radius:16px;padding:28px 24px;text-align:center;margin-bottom:16px}
.result-icon{width:64px;height:64px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:28px}
.icon-done{background:rgba(52,211,153,.15)}
.icon-waiting{background:rgba(56,189,248,.12)}

.result-name{font-size:18px;font-weight:800;color:#fff;margin-bottom:4px}
.result-doctor{font-size:13px;color:var(--accent);margin-bottom:20px}

.position-ring{display:inline-flex;flex-direction:column;align-items:center;justify-content:center;width:100px;height:100px;border-radius:50%;border:3px solid var(--accent);background:rgba(56,189,248,.08);margin-bottom:12px}
.position-num{font-size:36px;font-weight:800;color:var(--accent);line-height:1}
.position-label{font-size:11px;color:var(--text-muted);margin-top:2px}

.ahead-text{font-size:14px;color:var(--text-muted);line-height:1.7}
.ahead-text strong{color:var(--text)}

.result-meta{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.meta-item{background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:12px 14px}
.meta-label{font-size:11px;color:var(--text-muted);margin-bottom:4px}
.meta-value{font-size:13px;font-weight:600;color:var(--text)}

.done-banner{display:flex;align-items:center;gap:12px;background:rgba(52,211,153,.1);border:1px solid rgba(52,211,153,.25);border-radius:12px;padding:16px 18px;margin-bottom:12px}
.done-banner-text{font-size:14px;color:var(--success);font-weight:600}
.done-banner-sub{font-size:12px;color:rgba(52,211,153,.7);margin-top:2px}
.done-note{background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:14px;font-size:13px;color:var(--text-muted);line-height:1.8}
.done-note strong{color:var(--text);display:block;margin-bottom:6px;font-size:12px}

.status-badge{display:inline-flex;align-items:center;gap:6px;padding:5px 14px;border-radius:100px;font-size:12px;font-weight:700}
.badge-pending{background:rgba(251,191,36,.12);color:var(--warn);border:1px solid rgba(251,191,36,.25)}
.badge-confirmed{background:rgba(56,189,248,.1);color:var(--accent);border:1px solid rgba(56,189,248,.2)}
.badge-done{background:rgba(52,211,153,.1);color:var(--success);border:1px solid rgba(52,211,153,.2)}

.queue-info{margin-top:10px;font-size:12px;color:var(--text-muted);text-align:center;padding:8px;background:var(--surface2);border-radius:8px}

.admin-link{text-align:center;margin-top:24px;font-size:12px;color:var(--text-muted)}
.admin-link a{color:var(--accent);text-decoration:none;transition:opacity .2s}
.admin-link a:hover{opacity:.75}

/* Tab panels */
.tab-panel{display:none}
.tab-panel.active{display:block}

@keyframes fadeDown{from{opacity:0;transform:translateY(-16px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
</style>
</head>
<body>
<div class="container">

  <div class="header">
    <div class="header-badge">اتاق عمل کت‌لب</div>
    <h1>سیستم <span>نسخه</span> آنلاین</h1>
    <p>ارسال نسخه یا پیگیری وضعیت خود را انتخاب کنید</p>
  </div>

  <!-- Tabs -->
  <div class="tabs">
    <button class="tab-btn <?= $active_tab==='submit'?'active':'' ?>" onclick="switchTab('submit')">
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
      ارسال نسخه
    </button>
    <button class="tab-btn <?= $active_tab==='track'?'active':'' ?>" onclick="switchTab('track')">
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
      پیگیری نوبت
    </button>
  </div>

  <!-- ==================== TAB: SUBMIT ==================== -->
  <div class="tab-panel <?= $active_tab==='submit'?'active':'' ?>" id="tab-submit">

    <?php if ($success === '1'): ?>
    <div class="alert alert-success">
      <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      نسخه شما با موفقیت ارسال شد. می‌توانید با کد ملی خود نوبت خود را پیگیری کنید.
    </div>
    <?php elseif ($error !== ''): ?>
    <div class="alert alert-error">
      <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <div class="card">
      <form action="submit.php" method="POST" enctype="multipart/form-data">

        <div class="section-title">
          <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
          مشخصات بیمار
        </div>

        <div class="form-grid">
          <div class="field">
            <label>نام <span class="req">*</span></label>
            <input type="text" name="first_name" placeholder="نام" required>
          </div>
          <div class="field">
            <label>نام خانوادگی <span class="req">*</span></label>
            <input type="text" name="last_name" placeholder="نام خانوادگی" required>
          </div>
        </div>

        <div class="form-grid">
          <div class="field">
            <label>کد ملی <span class="req">*</span></label>
            <input type="text" name="national_id" placeholder="۱۰ رقم" maxlength="10" pattern="\d{10}" required>
          </div>
          <div class="field">
            <label>شماره تماس <span class="req">*</span></label>
            <input type="tel" name="phone" placeholder="۰۹۱۲۳۴۵۶۷۸۹" required>
          </div>
        </div>

        <div class="divider"></div>

        <div class="section-title">
          <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
          اطلاعات پزشک و نسخه
        </div>

        <div class="form-grid full" style="margin-bottom:16px">
          <div class="field">
            <label>پزشک معالج <span class="req">*</span></label>
            <select name="doctor_name" required>
              <option value="" disabled selected>پزشک خود را انتخاب کنید...</option>
              <option value="دکتر کردونی">دکتر کردونی</option>
              <option value="دکتر محمدی">دکتر محمدی</option>
              <option value="دکتر تقی زاده">دکتر تقی زاده</option>
              <option value="دکتر حمید">دکتر حمید</option>
            </select>
          </div>
        </div>

        <div class="form-grid full" style="margin-bottom:16px">
          <div class="field">
            <label>توضیحات تکمیلی</label>
            <textarea name="notes" placeholder="در صورت نیاز توضیحات بیشتری اضافه کنید..."></textarea>
          </div>
        </div>

        <!-- Emergency checkbox -->
        <label class="emergency-wrap" id="emergency-wrap">
          <input type="checkbox" name="is_emergency" value="1" id="emergency-check" onchange="toggleEmergency(this)">
          <span class="emergency-box">
            <span class="emergency-icon">🚨</span>
            <span class="emergency-text">
              <strong>درخواست اورژانسی</strong>
              <small>در صورت تایید توسط پزشک معالج در لیست اورژانسی قرار می گیرد</small>
            </span>
            <span class="emergency-check-mark">
              <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
            </span>
          </span>
        </label>

        <div class="field">
          <label>تصویر نسخه <span class="req">*</span></label>
          <div class="file-upload-area" id="drop-area">
            <input type="file" name="prescription_file" id="file-input" accept="image/*,.pdf" required>
            <div class="file-upload-icon">
              <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
            </div>
            <div class="file-upload-text">تصویر یا PDF نسخه را اینجا رها کنید</div>
            <div class="file-upload-hint">JPG، PNG، PDF — حداکثر ۱۰ مگابایت</div>
          </div>
          <div id="file-preview">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"/></svg>
            <span id="file-name"></span>
          </div>
        </div>

        <div class="divider"></div>
        <button type="submit" class="btn-submit">ارسال نسخه</button>
      </form>
    </div>
  </div>

  <!-- ==================== TAB: TRACK ==================== -->
  <div class="tab-panel <?= $active_tab==='track'?'active':'' ?>" id="tab-track">
    <div class="card">
      <div class="section-title">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        پیگیری وضعیت نوبت
      </div>

      <form method="POST" action="index.php">
        <input type="hidden" name="search_type" value="nid">
        <div class="track-search">
          <input type="text" name="track_national_id" placeholder="کد ملی خود را وارد کنید" maxlength="10" inputmode="numeric"
            value="<?= isset($_POST['track_national_id']) ? htmlspecialchars($_POST['track_national_id']) : '' ?>">
          <button type="submit">جستجو</button>
        </div>
      </form>

      <div style="display:flex;align-items:center;gap:12px;margin:14px 0">
        <div style="flex:1;height:1px;background:var(--border)"></div>
        <span style="font-size:11px;color:var(--text-muted)">یا جستجو با نام</span>
        <div style="flex:1;height:1px;background:var(--border)"></div>
      </div>

      <form method="POST" action="index.php">
        <input type="hidden" name="search_type" value="name">
        <div class="track-search">
          <input type="text" name="track_name" placeholder="نام و نام خانوادگی" dir="rtl"
            value="<?= isset($_POST['track_name']) ? htmlspecialchars($_POST['track_name']) : '' ?>">
          <button type="submit">جستجو</button>
        </div>
      </form>

      <?php if ($track_error): ?>
      <div class="alert alert-error" style="margin-top:18px;margin-bottom:0">
        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <?= htmlspecialchars($track_error) ?>
      </div>

      <?php elseif ($track_result): ?>
      <?php $p = $track_result['patient']; $stage = $track_result['stage']; ?>

      <div class="result-card" style="margin-top:22px">

        <?php if ($stage === 'completed'): ?>
        <!-- ===== انجام شده (completed file) ===== -->
        <div class="done-banner" style="background:<?= $track_result['oc_bg'] ?>.1);border-color:<?= $track_result['oc_bg'] ?>.3);margin-bottom:14px">
          <div style="font-size:28px">✅</div>
          <div>
            <div class="done-banner-text" style="color:<?= $track_result['oc_color'] ?>">عمل شما انجام شده است</div>
            <div class="done-banner-sub">پرونده شما در سیستم بسته شده</div>
          </div>
        </div>

        <!-- نتیجه عمل -->
        <div style="background:<?= $track_result['oc_bg'] ?>.08);border:1px solid <?= $track_result['oc_bg'] ?>.25);border-radius:12px;padding:16px 18px;margin-bottom:14px;text-align:center">
          <div style="font-size:11px;color:var(--text-muted);margin-bottom:6px;letter-spacing:.05em">نتیجه عمل</div>
          <div style="font-size:20px;font-weight:800;color:<?= $track_result['oc_color'] ?>"><?= htmlspecialchars($track_result['oc']) ?></div>
        </div>

        <div class="result-meta">
          <div class="meta-item">
            <div class="meta-label">نام بیمار</div>
            <div class="meta-value"><?= htmlspecialchars($p['first_name'].' '.$p['last_name']) ?></div>
          </div>
          <div class="meta-item">
            <div class="meta-label">پزشک معالج</div>
            <div class="meta-value"><?= htmlspecialchars($p['doctor_name']) ?></div>
          </div>
          <div class="meta-item">
            <div class="meta-label">تاریخ ثبت نسخه</div>
            <div class="meta-value"><?= shamsiDate($p['submitted_at']) ?></div>
          </div>
          <div class="meta-item">
            <div class="meta-label">تاریخ انجام</div>
            <div class="meta-value"><?= shamsiDate($p['completed_at'] ?? '') ?></div>
          </div>
        </div>

        <?php elseif ($stage === 'admitted'): ?>
        <!-- ===== بستری شده ===== -->
        <div class="done-banner" style="background:rgba(124,58,237,.1);border-color:rgba(124,58,237,.3);margin-bottom:14px">
          <div style="font-size:28px">🏥</div>
          <div>
            <div class="done-banner-text" style="color:#a78bfa">بیمار در حال حاضر بستری است</div>
            <div class="done-banner-sub" style="color:rgba(167,139,250,.7)">در حال انجام اقدامات لازم</div>
          </div>
        </div>

        <div class="result-meta">
          <div class="meta-item">
            <div class="meta-label">نام بیمار</div>
            <div class="meta-value"><?= htmlspecialchars($p['first_name'].' '.$p['last_name']) ?></div>
          </div>
          <div class="meta-item">
            <div class="meta-label">پزشک معالج</div>
            <div class="meta-value"><?= htmlspecialchars($p['doctor_name']) ?></div>
          </div>
          <div class="meta-item">
            <div class="meta-label">تاریخ ثبت نسخه</div>
            <div class="meta-value"><?= shamsiDate($p['submitted_at']) ?></div>
          </div>
          <div class="meta-item">
            <div class="meta-label">ساعت بستری</div>
            <div class="meta-value"><?= isset($p['admitted_at']) ? substr($p['admitted_at'],11,5) : '—' ?></div>
          </div>
        </div>

        <?php else: ?>
        <!-- ===== در صف انتظار ===== -->
        <div class="result-hero">
          <div class="result-name"><?= htmlspecialchars($p['first_name'].' '.$p['last_name']) ?></div>
          <div class="result-doctor"><?= htmlspecialchars($track_result['doctor']) ?></div>

          <?php if ($track_result['position']): ?>
          <div class="position-ring">
            <div class="position-num"><?= $track_result['position'] ?></div>
            <div class="position-label">نوبت شما</div>
          </div>
          <div class="ahead-text">
            <?php if ($track_result['ahead'] === 0): ?>
              <strong>شما نفر اول لیست هستید</strong> 🎯<br>به زودی با شما تماس گرفته می‌شود
            <?php else: ?>
              <strong><?= $track_result['ahead'] ?> نفر</strong> جلوتر از شما در لیست <?= htmlspecialchars($track_result['doctor']) ?> هستند
            <?php endif; ?>
          </div>
          <?php endif; ?>

          <div style="margin-top:14px">
            <?php
            $badge = ['pending'=>['badge-pending','در انتظار بررسی'],'confirmed'=>['badge-confirmed','تأیید شده']];
            $bc = $badge[$p['status']] ?? ['badge-pending','نامشخص'];
            ?>
            <span class="status-badge <?= $bc[0] ?>"><?= $bc[1] ?></span>
          </div>
        </div>

        <div class="result-meta">
          <div class="meta-item">
            <div class="meta-label">تاریخ ثبت نسخه</div>
            <div class="meta-value"><?= shamsiDate($p['submitted_at']) ?></div>
          </div>
          <div class="meta-item">
            <div class="meta-label">کل لیست این پزشک</div>
            <div class="meta-value"><?= $track_result['queue_size'] ?> نفر</div>
          </div>
        </div>

        <?php if ($track_result['ahead'] > 0): ?>
        <div class="queue-info">برای اطلاعات بیشتر با اتاق عمل تماس بگیرید</div>
        <?php endif; ?>

        <?php endif; ?>
      </div><!-- /result-card -->
      <?php endif; // end track_result ?>

    </div>
  </div>

  <div class="admin-link">
    <a href="admin.php">ورود به پنل مدیریت</a>
  </div>

  <div style="text-align:center;margin-top:16px;font-size:12px;color:rgba(148,163,184,0.5)">
    طراحی و توسعه: <a href="tel:09168351210" style="color:rgba(56,189,248,0.6);text-decoration:none;transition:opacity .2s" onmouseover="this.style.opacity='.8'" onmouseout="this.style.opacity='1'">حسین قالوندی</a>
  </div>

</div>

<script>
function switchTab(tab) {
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + tab).classList.add('active');
  event.currentTarget.classList.add('active');
}

function toggleEmergency(cb){
  // just for visual — CSS handles the rest
}

// File upload
const dropArea = document.getElementById('drop-area');
const fileInput = document.getElementById('file-input');
const preview  = document.getElementById('file-preview');
const fileName = document.getElementById('file-name');

fileInput.addEventListener('change', () => {
  if (fileInput.files.length) { fileName.textContent = fileInput.files[0].name; preview.style.display = 'flex'; }
});
['dragenter','dragover'].forEach(e => dropArea.addEventListener(e, ev => { ev.preventDefault(); dropArea.classList.add('dragover'); }));
['dragleave','drop'].forEach(e => dropArea.addEventListener(e, ev => { ev.preventDefault(); dropArea.classList.remove('dragover'); }));
dropArea.addEventListener('drop', e => {
  fileInput.files = e.dataTransfer.files;
  if (fileInput.files.length) { fileName.textContent = fileInput.files[0].name; preview.style.display = 'flex'; }
});
</script>
</body>
</html>
