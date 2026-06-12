<?php
session_start();

// ===== CONFIG =====
define('ADMIN_PASS',  'Avina12044--');
define('VIEWER_PASS', 'bastari1234');
$DOCTOR_PASSWORDS = [
    'دکتر کردونی'   => 'drkardooni',
    'دکتر محمدی'    => 'drmohammadi',
    'دکتر تقی زاده' => 'drtaghizadeh',
    'دکتر حمید'     => 'drhamid',
];

$upload_dir = 'uploads/';

require_once __DIR__ . '/config.php';
$pdo = getDB();

// ===== DB HELPERS =====
function dbAll($pdo, $sql, $params=[]){
    $s=$pdo->prepare($sql); $s->execute($params); return $s->fetchAll();
}
function dbRow($pdo, $sql, $params=[]){
    $s=$pdo->prepare($sql); $s->execute($params); return $s->fetch();
}
function dbExec($pdo, $sql, $params=[]){
    $s=$pdo->prepare($sql); $s->execute($params); return $s;
}
function statusLabel($s){ return ['pending'=>'در انتظار','confirmed'=>'تأیید شده','done'=>'انجام شده'][$s]??$s; }
function statusClass($s){ return ['pending'=>'badge-pending','confirmed'=>'badge-confirmed','done'=>'badge-done'][$s]??''; }
function todayGregorian(){ return date('Y-m-d'); }

function toJalali($gy,$gm,$gd){
    $gdi=[31,28,31,30,31,30,31,31,30,31,30,31];
    $jdi=[31,31,31,31,31,31,30,30,30,30,30,29];
    $gy2=($gm>2)?$gy+1:$gy;
    $d=355666+(365*$gy)+(int)(($gy2+3)/4)-(int)(($gy2+99)/100)+(int)(($gy2+399)/400);
    for($i=0;$i<$gm-1;$i++) $d+=$gdi[$i];
    $d+=$gd;
    $jy=-1595+(33*(int)($d/12053)); $d%=12053;
    $jy+=4*(int)($d/1461); $d%=1461;
    if($d>365){$jy+=(int)(($d-1)/365);$d=($d-1)%365;}
    $jm=0; for($i=0;$i<11;$i++){if($d<$jdi[$i]){$jm=$i+1;break;}$d-=$jdi[$i];}
    if($jm===0)$jm=12;
    return [$jy,$jm,$d+1];
}
function shamsiDate($dt){
    if(!$dt) return '—';
    $p=explode(' ',$dt); $dp=explode('-',$p[0]);
    if(count($dp)<3) return $dt;
    [$jy,$jm,$jd]=toJalali((int)$dp[0],(int)$dp[1],(int)$dp[2]);
    $mn=['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
    return $jd.' '.$mn[$jm-1].' '.$jy;
}

// ===== AUTH =====
if(isset($_POST['logout'])){ $_SESSION=[];session_destroy();header('Location: admin.php');exit; }
if(isset($_POST['password'])){
    $pw=$_POST['password'];
    if($pw===ADMIN_PASS){
        $_SESSION['admin']=true;$_SESSION['role']='admin';$_SESSION['doctor_filter']=null;
    } elseif($pw===VIEWER_PASS){
        $_SESSION['admin']=true;$_SESSION['role']='viewer';$_SESSION['doctor_filter']=null;
    } else {
        $found=false;
        foreach($DOCTOR_PASSWORDS as $doc=>$dpw){
            if($pw===$dpw){$_SESSION['admin']=true;$_SESSION['role']='doctor';$_SESSION['doctor_filter']=$doc;$found=true;break;}
        }
        if(!$found) $login_error='رمز عبور اشتباه است';
    }
}
$logged_in    =!empty($_SESSION['admin']);
$is_admin     =$logged_in&&($_SESSION['role']??'')==='admin';
$is_viewer    =$logged_in&&($_SESSION['role']??'')==='viewer';
$is_doctor    =$logged_in&&($_SESSION['role']??'')==='doctor';
$doctor_filter=$_SESSION['doctor_filter']??null;

// ===== ACTIONS =====
if($logged_in && !$is_viewer){

    // Emergency: approve → move to emergency tab
    if(isset($_POST['approve_emergency'])&&!empty($_POST['record_id'])){
        $rid=trim($_POST['record_id']);
        $cond=$is_admin?"id=?":"id=? AND doctor_name=?";
        $params=$is_admin?['approved',$rid]:['approved',$rid,$doctor_filter];
        dbExec($pdo,"UPDATE prescriptions SET emergency_status=? WHERE $cond",$params);
        header('Location: admin.php?tab=emergency');exit;
    }

    // Emergency: reject → stays in normal list
    if(isset($_POST['reject_emergency'])&&!empty($_POST['record_id'])){
        $rid=trim($_POST['record_id']);
        $cond=$is_admin?"id=?":"id=? AND doctor_name=?";
        $params=$is_admin?['rejected',$rid]:['rejected',$rid,$doctor_filter];
        dbExec($pdo,"UPDATE prescriptions SET emergency_status=?,is_emergency=0 WHERE $cond",$params);
        header('Location: admin.php?tab=patients');exit;
    }

    // Admit multiple patients → move to admitted table
    if(isset($_POST['admit_patients'])&&!empty($_POST['admit_ids'])){
        $ids=(array)$_POST['admit_ids'];
        $now=date('Y-m-d H:i:s');
        foreach($ids as $rid){
            $rid=trim($rid);
            $r=dbRow($pdo,"SELECT * FROM prescriptions WHERE id=?",[$rid]);
            if(!$r) continue;
            if(!$is_admin&&$r['doctor_name']!==$doctor_filter) continue;
            dbExec($pdo,"INSERT IGNORE INTO admitted
                (id,first_name,last_name,national_id,phone,doctor_name,notes,file,status,submitted_at,admitted_at)
                VALUES(?,?,?,?,?,?,?,?,?,?,?)",
                [$r['id'],$r['first_name'],$r['last_name'],$r['national_id'],
                 $r['phone'],$r['doctor_name'],$r['notes']??'',$r['file'],
                 $r['status'],$r['submitted_at'],$now]);
            dbExec($pdo,"DELETE FROM prescriptions WHERE id=?",[$rid]);
        }
        header('Location: admin.php?tab=admitted');exit;
    }

    // Discharge (delete) from admitted
    if(isset($_POST['discharge_admitted'])&&!empty($_POST['admit_record_id'])){
        dbExec($pdo,"DELETE FROM admitted WHERE id=?",[trim($_POST['admit_record_id'])]);
        header('Location: admin.php?tab=admitted');exit;
    }

    // Return admitted → back to waiting queue
    if(isset($_POST['return_to_queue'])&&!empty($_POST['admit_record_id'])){
        $rid=trim($_POST['admit_record_id']);
        $r=dbRow($pdo,"SELECT * FROM admitted WHERE id=?",[$rid]);
        if($r){
            dbExec($pdo,"INSERT IGNORE INTO prescriptions
                (id,first_name,last_name,national_id,phone,doctor_name,notes,file,status,submitted_at,returned_at)
                VALUES(?,?,?,?,?,?,?,?,?,?,?)",
                [$r['id'],$r['first_name'],$r['last_name'],$r['national_id'],
                 $r['phone'],$r['doctor_name'],$r['notes']??'',$r['file'],
                 'pending',$r['submitted_at'],date('Y-m-d H:i:s')]);
            dbExec($pdo,"DELETE FROM admitted WHERE id=?",[$rid]);
        }
        header('Location: admin.php?tab=patients');exit;
    }

    // Mark admitted → completed with outcome
    if(isset($_POST['complete_admitted'])&&!empty($_POST['admit_record_id'])&&!empty($_POST['outcome'])){
        $rid=trim($_POST['admit_record_id']);
        $outcome=trim($_POST['outcome']);
        $valid=['نرمال','PCI','درمان دارویی','مشاوره جراحی قلب باز'];
        if(in_array($outcome,$valid,true)){
            $r=dbRow($pdo,"SELECT * FROM admitted WHERE id=?",[$rid]);
            if($r){
                dbExec($pdo,"INSERT IGNORE INTO completed
                    (id,first_name,last_name,national_id,phone,doctor_name,notes,file,status,outcome,admitted_at,completed_at,submitted_at)
                    VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    [$r['id'],$r['first_name'],$r['last_name'],$r['national_id'],
                     $r['phone'],$r['doctor_name'],$r['notes']??'',$r['file'],
                     $r['status'],$outcome,$r['admitted_at'],
                     date('Y-m-d H:i:s'),$r['submitted_at']]);
                dbExec($pdo,"DELETE FROM admitted WHERE id=?",[$rid]);
            }
        }
        header('Location: admin.php?tab=completed');exit;
    }

    // Save result note
    if(isset($_POST['save_note'])&&!empty($_POST['record_id'])){
        $rid=trim($_POST['record_id']); $note=trim($_POST['result_note']??'');
        if($is_admin)
            dbExec($pdo,"UPDATE prescriptions SET status='done',result_note=?,done_at=NOW() WHERE id=?",[$note,$rid]);
        else
            dbExec($pdo,"UPDATE prescriptions SET status='done',result_note=?,done_at=NOW() WHERE id=? AND doctor_name=?",[$note,$rid,$doctor_filter]);
        header('Location: admin.php');exit;
    }

    // Update status
    if(isset($_POST['update_status'])&&!empty($_POST['record_id'])){
        $rid=trim($_POST['record_id']); $status=trim($_POST['new_status']??'');
        if(in_array($status,['pending','confirmed','done'])){
            if($is_admin)
                dbExec($pdo,"UPDATE prescriptions SET status=? WHERE id=?",[$status,$rid]);
            else
                dbExec($pdo,"UPDATE prescriptions SET status=? WHERE id=? AND doctor_name=?",[$status,$rid,$doctor_filter]);
        }
        header('Location: admin.php');exit;
    }

    // Delete record (admin only)
    if($is_admin&&isset($_POST['delete_record'])&&!empty($_POST['record_id'])){
        dbExec($pdo,"DELETE FROM prescriptions WHERE id=?",[trim($_POST['record_id'])]);
        header('Location: admin.php');exit;
    }
}

// ===== LOAD DATA =====
$get_doctor=$_GET['doctor']??'';
$get_status=$_GET['status']??'';
$active_tab=$is_viewer?'admitted':($_GET['tab']??'patients');
$today_str=todayGregorian();

// Prescriptions
$where='1=1'; $params=[];
if(!$is_admin&&$doctor_filter){ $where.=' AND doctor_name=?'; $params[]=$doctor_filter; }
if($get_doctor&&$is_admin)    { $where.=' AND doctor_name=?'; $params[]=$get_doctor; }
if($get_status)               { $where.=' AND status=?';      $params[]=$get_status; }
$all_data =dbAll($pdo,"SELECT * FROM prescriptions WHERE 1=1".(!$is_admin&&$doctor_filter?" AND doctor_name='".addslashes($doctor_filter)."'":'')." ORDER BY submitted_at ASC",[]);
$filtered =dbAll($pdo,"SELECT * FROM prescriptions WHERE $where ORDER BY submitted_at ASC",$params);

// Emergency: بیماران اورژانسی تایید شده
$emg_where='is_emergency=1 AND emergency_status=?'; $emg_params=['approved'];
if(!$is_admin&&$doctor_filter){ $emg_where.=' AND doctor_name=?'; $emg_params[]=$doctor_filter; }
$emergency_approved=dbAll($pdo,"SELECT * FROM prescriptions WHERE $emg_where ORDER BY submitted_at DESC",$emg_params);

// Emergency: در انتظار تایید
$emg_pending_where='is_emergency=1 AND emergency_status=?'; $emg_pending_params=['pending'];
if(!$is_admin&&$doctor_filter){ $emg_pending_where.=' AND doctor_name=?'; $emg_pending_params[]=$doctor_filter; }
$emergency_pending=dbAll($pdo,"SELECT * FROM prescriptions WHERE $emg_pending_where ORDER BY submitted_at DESC",$emg_pending_params);

// Admitted
$adm_where='1=1'; $adm_params=[];
if(!$is_admin&&$doctor_filter){ $adm_where.=' AND doctor_name=?'; $adm_params[]=$doctor_filter; }
$all_admitted  =dbAll($pdo,"SELECT * FROM admitted WHERE $adm_where ORDER BY admitted_at ASC",$adm_params);
$admitted_today=dbAll($pdo,"SELECT * FROM admitted WHERE DATE(admitted_at)=CURDATE()".(!$is_admin&&$doctor_filter?" AND doctor_name='$doctor_filter'":"")." ORDER BY admitted_at ASC",[]);

// Group admitted by date
$admitted_by_date=[];
foreach($all_admitted as $r){
    $day=substr($r['admitted_at']??'',0,10)?:$today_str;
    $admitted_by_date[$day][]=$r;
}
krsort($admitted_by_date);

// Completed
$cmp_where='1=1'; $cmp_params=[];
if(!$is_admin&&$doctor_filter){ $cmp_where.=' AND doctor_name=?'; $cmp_params[]=$doctor_filter; }
$all_completed=dbAll($pdo,"SELECT * FROM completed WHERE $cmp_where ORDER BY completed_at ASC",$cmp_params);
$completed_by_date=[];
foreach($all_completed as $r){
    $day=substr($r['completed_at']??'',0,10)?:$today_str;
    $completed_by_date[$day][]=$r;
}
krsort($completed_by_date);

// Stats for admin — از هر ۳ جدول
$all_doctors_list = ['دکتر کردونی','دکتر محمدی','دکتر تقی زاده','دکتر حمید'];
$by_doctor = [];
foreach($all_doctors_list as $doc){
    $p  = dbRow($pdo,"SELECT COUNT(*) as c FROM prescriptions WHERE doctor_name=?",[$doc]);
    $ad = dbRow($pdo,"SELECT COUNT(*) as c FROM admitted    WHERE doctor_name=?",[$doc]);
    $co = dbRow($pdo,"SELECT COUNT(*) as c FROM completed   WHERE doctor_name=?",[$doc]);
    $pn = dbRow($pdo,"SELECT COUNT(*) as c FROM prescriptions WHERE doctor_name=? AND status IN ('pending','confirmed')",[$doc]);
    $cf = dbRow($pdo,"SELECT COUNT(*) as c FROM prescriptions WHERE doctor_name=? AND status='confirmed'",[$doc]);
    $dn = dbRow($pdo,"SELECT COUNT(*) as c FROM prescriptions WHERE doctor_name=? AND status='done'",[$doc]);
    $by_doctor[$doc] = [
        'total'     => (int)$p['c'] + (int)$ad['c'] + (int)$co['c'],
        'pending'   => (int)$pn['c'],
        'confirmed' => (int)$cf['c'],
        'done'      => (int)$dn['c'] + (int)$co['c'],
        'admitted'  => (int)$ad['c'],
    ];
}
$doctors_list=array_keys($by_doctor);
$all_doctors_chart=['دکتر کردونی','دکتر محمدی','دکتر تقی زاده','دکتر حمید'];
$chart_pending  =array_map(fn($d)=>$by_doctor[$d]['pending']  ??0,$all_doctors_chart);
$chart_confirmed=array_map(fn($d)=>$by_doctor[$d]['confirmed']??0,$all_doctors_chart);
$chart_done     =array_map(fn($d)=>$by_doctor[$d]['done']     ??0,$all_doctors_chart);
$doctors_list   = $all_doctors_chart;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>پنل مدیریت | اتاق عمل کت‌لب</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
:root{
  --bg:#0b1120;--surface:#111827;--surface2:#1a2537;
  --border:rgba(99,179,237,0.15);--accent:#38bdf8;--accent2:#0ea5e9;
  --text:#e2e8f0;--muted:#94a3b8;
  --success:#34d399;--warn:#fbbf24;--error:#f87171;
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Vazirmatn',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;direction:rtl}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse 80% 50% at 20% 10%,rgba(56,189,248,.06) 0%,transparent 60%);pointer-events:none;z-index:0}

/* Login */
.login-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;position:relative;z-index:1}
.login-card{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:40px;width:100%;max-width:400px;text-align:center}
.login-card h2{font-size:22px;font-weight:800;margin-bottom:4px}
.login-card .sub{font-size:13px;color:var(--muted);margin-bottom:24px}
.login-hint{background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:12px 14px;text-align:right;margin-bottom:20px;font-size:12px;line-height:2;color:var(--muted)}
.login-hint strong{color:var(--text);font-weight:600}
.login-card input[type=password]{width:100%;background:var(--surface2);border:1px solid var(--border);border-radius:10px;color:var(--text);font-family:'Vazirmatn',sans-serif;font-size:14px;padding:12px 14px;outline:none;text-align:center;letter-spacing:.15em;margin-bottom:12px}
.login-card input[type=password]:focus{border-color:var(--accent2)}

/* Buttons */
.btn{padding:11px 20px;border-radius:10px;border:none;font-family:'Vazirmatn',sans-serif;font-size:14px;font-weight:600;cursor:pointer;transition:all .2s}
.btn-primary{background:linear-gradient(135deg,var(--accent2),#2563eb);color:#fff;width:100%}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(14,165,233,.3)}
.btn-sm{padding:5px 12px;font-size:12px}
.btn-ghost{background:rgba(148,163,184,.1);color:var(--muted)}
.btn-ghost:hover{background:rgba(148,163,184,.2);color:var(--text)}
.btn-danger{background:rgba(248,113,113,.12);color:var(--error)}
.btn-danger:hover{background:rgba(248,113,113,.22)}
.btn-success{background:linear-gradient(135deg,#059669,#047857);color:#fff}
.btn-success:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(5,150,105,.3)}
.btn-admit{background:linear-gradient(135deg,#7c3aed,#6d28d9);color:#fff}
.btn-admit:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(124,58,237,.3)}
.btn-admit:disabled{opacity:.4;cursor:not-allowed;transform:none;box-shadow:none}
.err-msg{color:var(--error);font-size:13px;margin-bottom:14px}

/* Layout */
.wrapper{position:relative;z-index:1;max-width:1200px;margin:0 auto;padding:28px 20px 60px}
.topbar{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:14px}
.topbar-title{font-size:20px;font-weight:800}
.topbar-title span{color:var(--accent)}
.doctor-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(56,189,248,.1);border:1px solid var(--border);border-radius:100px;padding:4px 12px;font-size:12px;color:var(--accent);margin-top:6px}
.topbar-right{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.back-link{font-size:13px;color:var(--muted);text-decoration:none;transition:color .2s}
.back-link:hover{color:var(--accent)}

/* Main Tabs */
.main-tabs{display:flex;gap:4px;flex-wrap:wrap;margin-bottom:24px;background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:5px}
.main-tab{display:flex;align-items:center;gap:7px;padding:9px 16px;border:none;background:transparent;color:var(--muted);font-family:'Vazirmatn',sans-serif;font-size:13px;font-weight:600;cursor:pointer;border-radius:10px;transition:all .2s;white-space:nowrap}
.main-tab.active{background:linear-gradient(135deg,var(--accent2),#2563eb);color:#fff;box-shadow:0 3px 12px rgba(14,165,233,.3)}
.main-tab.tab-admitted.active{background:linear-gradient(135deg,#7c3aed,#6d28d9);box-shadow:0 3px 12px rgba(124,58,237,.3)}
.main-tab.tab-completed.active{background:linear-gradient(135deg,#059669,#047857);box-shadow:0 3px 12px rgba(5,150,105,.3)}
.main-tab.tab-emergency.active{background:linear-gradient(135deg,#dc2626,#b91c1c);box-shadow:0 3px 12px rgba(220,38,38,.3)}
.badge-emergency{background:rgba(248,113,113,.12);color:var(--error);border:1px solid rgba(248,113,113,.3);display:inline-flex;align-items:center;gap:4px}
.badge-emg-pending{background:rgba(251,191,36,.1);color:var(--warn);border:1px solid rgba(251,191,36,.25)}
.emg-section-title{font-size:12px;font-weight:700;color:var(--error);letter-spacing:.08em;margin:20px 0 12px;display:flex;align-items:center;gap:8px}
.emg-section-title::before{content:'';display:inline-block;width:3px;height:14px;background:var(--error);border-radius:2px}
.emg-pending-title{color:var(--warn)}
.emg-pending-title::before{background:var(--warn)}
.main-tab:not(.active):hover{color:var(--text);background:var(--surface2)}
.tab-count{background:rgba(255,255,255,.15);border-radius:100px;padding:1px 7px;font-size:11px}
.main-tab:not(.active) .tab-count{background:rgba(148,163,184,.1);color:var(--muted)}

/* Tab panels */
.tab-panel{display:none}
.tab-panel.active{display:block}

/* Stats cards */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;margin-bottom:24px}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px;cursor:pointer;transition:border-color .2s}
.stat-card:hover{border-color:var(--accent)}
.stat-doctor{font-size:13px;font-weight:700;margin-bottom:4px}
.stat-total{font-size:26px;font-weight:800;color:var(--accent);margin-bottom:8px}
.stat-badges{display:flex;gap:6px;flex-wrap:wrap}

/* Chart */
.chart-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:22px 24px;margin-bottom:24px}
.chart-title{font-size:13px;font-weight:700;color:var(--muted);margin-bottom:18px;display:flex;align-items:center;gap:8px}
.chart-title::before{content:'';width:3px;height:14px;background:var(--accent);border-radius:2px}
.chart-wrap{position:relative;height:220px}

/* Filters */
.filters{display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;align-items:center}
.filters select{background:var(--surface);border:1px solid var(--border);border-radius:8px;color:var(--text);font-family:'Vazirmatn',sans-serif;font-size:13px;padding:8px 12px;outline:none}
.filters select:focus{border-color:var(--accent2)}
.filters select option{background:var(--surface2)}
.count-badge{background:rgba(56,189,248,.1);border:1px solid var(--border);color:var(--accent);font-size:12px;padding:4px 10px;border-radius:100px;font-weight:600}

/* Admit toolbar */
.admit-toolbar{display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap}
.selected-label{font-size:13px;color:var(--warn);font-weight:600;display:none}

/* Table */
.table-wrap{overflow-x:auto;background:var(--surface);border:1px solid var(--border);border-radius:16px}
table{width:100%;border-collapse:collapse;min-width:660px}
thead{background:var(--surface2)}
th{font-size:11px;font-weight:700;color:var(--muted);letter-spacing:.07em;padding:11px 14px;text-align:right;white-space:nowrap}
td{padding:11px 14px;font-size:13px;border-top:1px solid var(--border);vertical-align:middle}
tr:hover td{background:rgba(56,189,248,.025)}
tr.selected-row td{background:rgba(124,58,237,.08)!important}
.name-cell{font-weight:600}
.doctor-cell{color:var(--accent);font-weight:500}
.date-cell{color:var(--muted);font-size:12px}
.actions{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
input[type=checkbox].admit-check{width:16px;height:16px;accent-color:#7c3aed;cursor:pointer}

/* Badges */
.badge{display:inline-block;padding:3px 10px;border-radius:100px;font-size:11px;font-weight:600}
.badge-pending{background:rgba(251,191,36,.1);color:var(--warn);border:1px solid rgba(251,191,36,.25)}
.badge-confirmed{background:rgba(56,189,248,.1);color:var(--accent);border:1px solid rgba(56,189,248,.2)}
.badge-done{background:rgba(52,211,153,.1);color:var(--success);border:1px solid rgba(52,211,153,.2)}
.file-link{color:var(--accent);text-decoration:none;font-size:12px;display:inline-flex;align-items:center;gap:4px}
.file-link:hover{text-decoration:underline}
.result-note-display{font-size:11px;color:var(--success);margin-top:3px;max-width:150px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;cursor:help}

/* Admitted header */
.admitted-header{display:flex;align-items:center;gap:12px;margin-bottom:18px;flex-wrap:wrap}
.admitted-date-badge{display:inline-flex;align-items:center;gap:8px;background:rgba(124,58,237,.1);border:1px solid rgba(124,58,237,.25);color:#a78bfa;border-radius:100px;padding:6px 16px;font-size:13px;font-weight:600}

/* Sub-tabs */
.sub-tabs{display:flex;gap:4px;margin-bottom:20px;background:var(--surface2);border:1px solid var(--border);border-radius:12px;padding:4px;width:fit-content}
.sub-tab{padding:8px 16px;border:none;background:transparent;color:var(--muted);font-family:'Vazirmatn',sans-serif;font-size:13px;font-weight:600;cursor:pointer;border-radius:8px;transition:all .2s;display:flex;align-items:center;gap:7px;white-space:nowrap}
.sub-tab.active{background:var(--surface);color:var(--text);box-shadow:0 1px 4px rgba(0,0,0,.3)}
.sub-tab .stc{background:rgba(56,189,248,.15);color:var(--accent);border-radius:100px;padding:1px 7px;font-size:11px}
.sub-tab:not(.active) .stc{background:rgba(148,163,184,.1);color:var(--muted)}
.sub-panel{display:none}
.sub-panel.active{display:block}

/* History accordion */
.history-day{margin-bottom:10px;border:1px solid var(--border);border-radius:14px;overflow:hidden}
.history-day-header{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;cursor:pointer;background:var(--surface);transition:background .15s;user-select:none}
.history-day-header:hover{background:var(--surface2)}
.history-day-info{display:flex;flex-direction:column;gap:6px}
.history-day-date{font-size:14px;font-weight:700;display:flex;align-items:center;gap:8px}
.history-day-meta{display:flex;gap:6px;flex-wrap:wrap;align-items:center}
.today-dot{width:8px;height:8px;background:var(--accent);border-radius:50%;display:inline-block;animation:pulse 2s infinite}
.doc-mini-badge{background:rgba(56,189,248,.08);border:1px solid var(--border);color:var(--muted);font-size:11px;padding:2px 8px;border-radius:100px}
.outcome-mini-badge{border-radius:100px;font-size:11px;padding:2px 8px}
.chevron{font-size:12px;color:var(--muted);transition:transform .25s;flex-shrink:0}
.history-day-body{display:none}
.history-day-body.open{display:block}

/* Outcome modal options */
.outcome-options{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:4px}
.outcome-option input[type=radio]{display:none}
.outcome-label{display:block;width:100%;padding:13px 10px;border-radius:10px;border:2px solid var(--border);font-size:13px;font-weight:600;text-align:center;cursor:pointer;transition:all .2s;background:var(--surface2);color:var(--muted)}
.outcome-option input:checked+.outcome-label.oc-normal{border-color:#34d399;background:rgba(52,211,153,.1);color:#34d399}
.outcome-option input:checked+.outcome-label.oc-pci{border-color:#38bdf8;background:rgba(56,189,248,.1);color:#38bdf8}
.outcome-option input:checked+.outcome-label.oc-med{border-color:#fbbf24;background:rgba(251,191,36,.1);color:#fbbf24}
.outcome-option input:checked+.outcome-label.oc-surg{border-color:#f87171;background:rgba(248,113,113,.1);color:#f87171}
.outcome-label:hover{border-color:rgba(148,163,184,.4);color:var(--text)}

/* Modal */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);z-index:100;align-items:center;justify-content:center;padding:20px}
.modal-overlay.open{display:flex}
.modal{background:var(--surface);border:1px solid var(--border);border-radius:18px;padding:28px;width:100%;max-width:460px;animation:fadeUp .25s ease both}
.modal h3{font-size:16px;font-weight:700;margin-bottom:6px}
.modal-sub{font-size:13px;color:var(--muted);margin-bottom:18px}
.modal textarea{width:100%;background:var(--surface2);border:1px solid var(--border);border-radius:10px;color:var(--text);font-family:'Vazirmatn',sans-serif;font-size:14px;padding:12px 14px;outline:none;resize:vertical;min-height:100px;margin-bottom:16px}
.modal textarea:focus{border-color:var(--accent2)}
.modal-actions{display:flex;gap:10px;justify-content:flex-end}

.empty{text-align:center;padding:48px 20px;color:var(--muted)}
.empty svg{display:block;margin:0 auto 14px;opacity:.3}

@media(max-width:600px){
  .stats-grid{grid-template-columns:1fr 1fr}
  .main-tabs{gap:3px}
  .main-tab{padding:8px 10px;font-size:12px}
  .sub-tabs{width:100%}
  .sub-tab{flex:1;justify-content:center}
  .outcome-options{grid-template-columns:1fr}
}
@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(.7)}}
</style>
</head>
<body>

<?php if(!$logged_in): ?>
<!-- ===== LOGIN ===== -->
<div class="login-wrap">
  <div class="login-card">
    <h2>پنل مدیریت</h2>
    <p class="sub">اتاق عمل کت‌لب</p>
    <div class="login-hint">
      <strong>مدیر کل:</strong> رمز مدیریت<br>
      <strong>بیننده بستری:</strong> رمز بستری<br>
      <strong>پزشکان:</strong> رمز اختصاصی
    </div>
    <?php if(!empty($login_error)): ?>
      <div class="err-msg"><?=htmlspecialchars($login_error)?></div>
    <?php endif; ?>
    <form method="POST">
      <input type="password" name="password" placeholder="رمز عبور" autofocus>
      <button type="submit" class="btn btn-primary">ورود</button>
    </form>
  </div>
</div>

<?php else: ?>
<!-- ===== PANEL ===== -->
<div class="wrapper">

  <!-- Topbar -->
  <div class="topbar">
    <div>
      <div class="topbar-title">پنل مدیریت <span>کت‌لب</span></div>
      <?php if($is_viewer): ?>
        <div class="doctor-badge">👁 دسترسی مشاهده</div>
      <?php elseif($doctor_filter): ?>
        <div class="doctor-badge"><?=htmlspecialchars($doctor_filter)?></div>
      <?php endif; ?>
    </div>
    <div class="topbar-right">
      <a href="index.php" class="back-link">← ارسال نسخه</a>
      <form method="POST" style="display:inline">
        <button type="submit" name="logout" value="1" class="btn btn-ghost btn-sm">خروج</button>
      </form>
    </div>
  </div>

  <!-- Stats (admin only) -->
  <?php if($is_admin): ?>
  <div class="stats-grid">
    <?php foreach($by_doctor as $doc=>$st): ?>
    <div class="stat-card" onclick="location.href='?doctor=<?=urlencode($doc)?>'">
      <div class="stat-doctor"><?=htmlspecialchars($doc)?></div>
      <div class="stat-total"><?=$st['total']?> بیمار</div>
      <div class="stat-badges">
        <?php if($st['pending'])   echo "<span class='badge badge-pending'>{$st['pending']} در انتظار</span>"; ?>
        <?php if($st['confirmed']) echo "<span class='badge badge-confirmed'>{$st['confirmed']} تأیید</span>"; ?>
        <?php if($st['admitted'])  echo "<span class='badge' style='background:rgba(124,58,237,.1);color:#a78bfa;border:1px solid rgba(124,58,237,.2)'>{$st['admitted']} بستری</span>"; ?>
        <?php if($st['done'])      echo "<span class='badge badge-done'>{$st['done']} انجام</span>"; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="chart-card">
    <div class="chart-title">نمودار مقایسه‌ای بیماران پزشکان</div>
    <div class="chart-wrap"><canvas id="doctorChart"></canvas></div>
  </div>
  <?php endif; ?>

  <!-- Main Tabs -->
  <div class="main-tabs">
    <?php if(!$is_viewer): ?>
    <button class="main-tab <?=$active_tab==='patients'?'active':''?>" onclick="switchTab('patients',this)">
      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
      لیست بیماران
      <span class="tab-count"><?=count($all_data)?></span>
    </button>
    <?php endif; ?>
    <button class="main-tab tab-admitted <?=$active_tab==='admitted'?'active':''?>" onclick="switchTab('admitted',this)">
      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/></svg>
      بستری امروز
      <span class="tab-count"><?=count($admitted_today)?></span>
    </button>
    <button class="main-tab tab-completed <?=$active_tab==='completed'?'active':''?>" onclick="switchTab('completed',this)">
      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      انجام شده‌ها
      <span class="tab-count"><?=count($all_completed)?></span>
    </button>
    <button class="main-tab tab-emergency <?=$active_tab==='emergency'?'active':''?>" onclick="switchTab('emergency',this)">
      🚨 اورژانسی
      <span class="tab-count"><?=count($emergency_approved)+count($emergency_pending)?></span>
    </button>
  </div>

  <!-- ===== TAB: PATIENTS ===== -->
  <?php if(!$is_viewer): ?>
  <div class="tab-panel <?=$active_tab==='patients'?'active':''?>" id="tab-patients">

    <!-- Filters -->
    <div class="filters">
      <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <?php if($is_admin): ?>
        <select name="doctor" onchange="this.form.submit()">
          <option value="">همه پزشکان</option>
          <?php foreach($doctors_list as $d): ?>
          <option value="<?=htmlspecialchars($d)?>" <?=$get_doctor===$d?'selected':''?>>
            <?=htmlspecialchars($d)?>
          </option>
          <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <select name="status" onchange="this.form.submit()">
          <option value="">همه وضعیت‌ها</option>
          <option value="pending"   <?=$get_status==='pending'  ?'selected':''?>>در انتظار</option>
          <option value="confirmed" <?=$get_status==='confirmed'?'selected':''?>>تأیید شده</option>
          <option value="done"      <?=$get_status==='done'     ?'selected':''?>>انجام شده</option>
        </select>
        <?php if($get_doctor||$get_status): ?>
          <a href="admin.php" class="btn btn-ghost btn-sm">پاک</a>
        <?php endif; ?>
      </form>
      <span class="count-badge"><?=count($filtered)?> نسخه</span>
    </div>

    <!-- Admit toolbar -->
    <form method="POST" action="admin.php" id="admit-form">
    <div class="admit-toolbar">
      <button type="submit" name="admit_patients" value="1" class="btn btn-admit btn-sm" id="admit-btn" disabled>
        🏥 بستری کردن انتخاب شده
      </button>
      <span class="selected-label" id="sel-label"><span id="sel-num">0</span> نفر انتخاب شده</span>
    </div>

    <div class="table-wrap">
      <?php if(empty($filtered)): ?>
      <div class="empty">
        <svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        هیچ نسخه‌ای یافت نشد
      </div>
      <?php else: ?>
      <table id="patients-table">
        <thead><tr>
          <th><input type="checkbox" id="check-all"></th>
          <th>#</th><th>نام بیمار</th><th>کد ملی</th><th>تماس</th>
          <?php if($is_admin): ?><th>پزشک معالج</th><?php endif; ?>
          <th>نسخه</th><th>تاریخ ثبت</th><th>وضعیت</th><th>عملیات</th>
        </tr></thead>
        <tbody>
        <?php $i=1; foreach(array_reverse(array_values($filtered)) as $r): ?>
        <tr>
          <td><input type="checkbox" class="admit-check" name="admit_ids[]" value="<?=$r['id']?>" <?=$r['status']==='done'?'disabled':''?>></td>
          <td style="color:var(--muted)"><?=$i++?></td>
          <td class="name-cell"><?=htmlspecialchars($r['first_name'].' '.$r['last_name'])?></td>
          <td style="font-family:monospace;font-size:12px"><?=htmlspecialchars($r['national_id'])?></td>
          <td><?=htmlspecialchars($r['phone'])?></td>
          <?php if($is_admin): ?><td class="doctor-cell"><?=htmlspecialchars($r['doctor_name'])?></td><?php endif; ?>
          <td><a href="<?=$upload_dir.htmlspecialchars($r['file'])?>" target="_blank" class="file-link">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
            مشاهده</a></td>
          <td class="date-cell"><?=shamsiDate($r['submitted_at'])?></td>
          <td>
            <span class="badge <?=statusClass($r['status'])?>"><?=statusLabel($r['status'])?></span>
            <?php if(!empty($r['is_emergency'])): ?>
            <?php if($r['emergency_status']==='pending'): ?>
            <span class="badge badge-emg-pending" style="margin-top:3px;display:inline-flex">⏳ اورژانس</span>
            <?php elseif($r['emergency_status']==='approved'): ?>
            <span class="badge badge-emergency" style="margin-top:3px;display:inline-flex">🚨 تایید اورژانس</span>
            <?php endif; ?>
            <?php endif; ?>
            <?php if($r['status']==='done'&&!empty($r['result_note'])): ?>
            <div class="result-note-display" title="<?=htmlspecialchars($r['result_note'])?>"><?=htmlspecialchars(mb_substr($r['result_note'],0,28))?>...</div>
            <?php endif; ?>
          </td>
          <td><div class="actions">
            <?php if($r['status']!=='done'): ?>
            <form method="POST" action="admin.php" style="display:inline">
              <input type="hidden" name="record_id" value="<?=$r['id']?>">
              <input type="hidden" name="update_status" value="1">
              <select name="new_status" class="btn btn-ghost btn-sm" style="padding:4px 8px;font-size:12px"
                data-id="<?=$r['id']?>" data-name="<?=htmlspecialchars($r['first_name'].' '.$r['last_name'],ENT_QUOTES)?>"
                data-note="<?=htmlspecialchars($r['result_note']??'',ENT_QUOTES)?>" data-current="<?=$r['status']?>"
                onchange="handleStatusChange(this)">
                <option value="pending"   <?=$r['status']==='pending'  ?'selected':''?>>در انتظار</option>
                <option value="confirmed" <?=$r['status']==='confirmed'?'selected':''?>>تأیید شده</option>
                <option value="done">انجام شده</option>
              </select>
            </form>
            <?php else: ?>
            <button type="button" class="btn btn-ghost btn-sm"
              onclick="openDoneModal('<?=$r['id']?>','<?=htmlspecialchars($r['first_name'].' '.$r['last_name'],ENT_QUOTES)?>','<?=htmlspecialchars($r['result_note']??'',ENT_QUOTES)?>')">
              ویرایش نتیجه
            </button>
            <?php endif; ?>
            <?php if($is_admin): ?>
            <form method="POST" action="admin.php" style="display:inline" onsubmit="return confirm('حذف شود؟')">
              <input type="hidden" name="record_id" value="<?=$r['id']?>">
              <button type="submit" name="delete_record" value="1" class="btn btn-danger btn-sm">حذف</button>
            </form>
            <?php endif; ?>
          </div></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
    </form>
  </div>
  <?php endif; ?>

  <!-- ===== TAB: ADMITTED ===== -->
  <div class="tab-panel <?=$active_tab==='admitted'?'active':''?>" id="tab-admitted">

    <?php $sub=$_GET['sub']??'today'; ?>
    <div class="sub-tabs">
      <button class="sub-tab <?=$sub==='today'?'active':''?>" onclick="switchSub('today',this)">
        🏥 بستری امروز <span class="stc"><?=count($admitted_today)?></span>
      </button>
      <button class="sub-tab <?=$sub==='history'?'active':''?>" onclick="switchSub('history',this)">
        📅 تاریخچه <span class="stc"><?=count($all_admitted)?></span>
      </button>
    </div>

    <!-- Sub: TODAY -->
    <div id="sub-today" class="sub-panel <?=$sub==='today'?'active':''?>">
      <div class="admitted-header">
        <div class="admitted-date-badge">امروز — <?=shamsiDate($today_str)?></div>
        <span class="count-badge"><?=count($admitted_today)?> بیمار</span>
      </div>
      <div class="table-wrap">
        <?php if(empty($admitted_today)): ?>
        <div class="empty">
          <svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16"/></svg>
          هنوز بیماری بستری نشده
        </div>
        <?php else: ?>
        <table>
          <thead><tr>
            <th>#</th><th>نام بیمار</th><th>کد ملی</th><th>تماس</th>
            <?php if($is_admin): ?><th>پزشک</th><?php endif; ?>
            <th>نسخه</th><th>ساعت بستری</th>
            <?php if(!$is_viewer): ?><th>عملیات</th><?php endif; ?>
          </tr></thead>
          <tbody>
          <?php $i=1; foreach($admitted_today as $r): ?>
          <tr>
            <td style="color:var(--muted)"><?=$i++?></td>
            <td class="name-cell"><?=htmlspecialchars($r['first_name'].' '.$r['last_name'])?></td>
            <td style="font-family:monospace;font-size:12px"><?=htmlspecialchars($r['national_id'])?></td>
            <td><?=htmlspecialchars($r['phone'])?></td>
            <?php if($is_admin): ?><td class="doctor-cell"><?=htmlspecialchars($r['doctor_name'])?></td><?php endif; ?>
            <td><a href="<?=$upload_dir.htmlspecialchars($r['file'])?>" target="_blank" class="file-link">
              <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
              مشاهده</a></td>
            <td class="date-cell"><?=isset($r['admitted_at'])?substr($r['admitted_at'],11,5):'—'?></td>
            <?php if(!$is_viewer): ?>
            <td><div class="actions">
              <button type="button" class="btn btn-success btn-sm"
                onclick="openOutcomeModal('<?=$r['id']?>','<?=htmlspecialchars($r['first_name'].' '.$r['last_name'],ENT_QUOTES)?>')">
                ✓ انجام شده
              </button>
              <form method="POST" action="admin.php" style="display:inline" onsubmit="return confirm('بیمار به لیست انتظار بازگردد؟')">
                <input type="hidden" name="admit_record_id" value="<?=$r['id']?>">
                <button type="submit" name="return_to_queue" value="1" class="btn btn-ghost btn-sm" style="color:var(--warn)">↩ بازگشت</button>
              </form>
              <form method="POST" action="admin.php" style="display:inline" onsubmit="return confirm('از لیست حذف شود؟')">
                <input type="hidden" name="admit_record_id" value="<?=$r['id']?>">
                <button type="submit" name="discharge_admitted" value="1" class="btn btn-danger btn-sm">حذف</button>
              </form>
            </div></td>
            <?php endif; ?>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>

    <!-- Sub: HISTORY -->
    <div id="sub-history" class="sub-panel <?=$sub==='history'?'active':''?>">
      <?php if(empty($admitted_by_date)): ?>
      <div class="empty" style="background:var(--surface);border:1px solid var(--border);border-radius:16px">
        <svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        هیچ سابقه‌ای ثبت نشده
      </div>
      <?php else: ?>
      <?php foreach($admitted_by_date as $day=>$recs): ?>
      <?php
        $isToday=($day===$today_str);
        $lbl=shamsiDate($day);
        $dcs=[];$cnt=count($recs);
        foreach($recs as $rec){ $dcs[$rec['doctor_name']]=($dcs[$rec['doctor_name']]??0)+1; }
        $hid='ah-'.md5($day);
      ?>
      <div class="history-day">
        <div class="history-day-header" onclick="toggleAccordion('<?=$hid?>')">
          <div class="history-day-info">
            <div class="history-day-date">
              <?php if($isToday): ?><span class="today-dot"></span><?php endif; ?>
              <?=$lbl?><?php if($isToday): ?><span style="font-size:11px;color:var(--accent);margin-right:6px">(امروز)</span><?php endif; ?>
            </div>
            <div class="history-day-meta">
              <span class="count-badge" style="font-size:11px"><?=$cnt?> بیمار</span>
              <?php foreach($dcs as $dn=>$dc): ?>
              <span class="doc-mini-badge"><?=htmlspecialchars($dn)?> (<?=$dc?>)</span>
              <?php endforeach; ?>
            </div>
          </div>
          <span class="chevron" id="ch-<?=$hid?>">▼</span>
        </div>
        <div class="history-day-body <?=$isToday?'open':''?>" id="<?=$hid?>">
          <div class="table-wrap" style="border-radius:0 0 12px 12px;border-top:none">
            <table>
              <thead><tr>
                <th>#</th><th>نام بیمار</th><th>کد ملی</th><th>تماس</th>
                <?php if($is_admin): ?><th>پزشک</th><?php endif; ?>
                <th>نسخه</th><th>ساعت بستری</th>
                <?php if(!$is_viewer): ?><th>عملیات</th><?php endif; ?>
              </tr></thead>
              <tbody>
              <?php $j=1; foreach($recs as $rec): ?>
              <tr>
                <td style="color:var(--muted)"><?=$j++?></td>
                <td class="name-cell"><?=htmlspecialchars($rec['first_name'].' '.$rec['last_name'])?></td>
                <td style="font-family:monospace;font-size:12px"><?=htmlspecialchars($rec['national_id'])?></td>
                <td><?=htmlspecialchars($rec['phone'])?></td>
                <?php if($is_admin): ?><td class="doctor-cell"><?=htmlspecialchars($rec['doctor_name'])?></td><?php endif; ?>
                <td><a href="<?=$upload_dir.htmlspecialchars($rec['file'])?>" target="_blank" class="file-link">
                  <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                  مشاهده</a></td>
                <td class="date-cell"><?=isset($rec['admitted_at'])?substr($rec['admitted_at'],11,5):'—'?></td>
                <?php if(!$is_viewer): ?>
                <td><div class="actions">
                  <button type="button" class="btn btn-success btn-sm"
                    onclick="openOutcomeModal('<?=$rec['id']?>','<?=htmlspecialchars($rec['first_name'].' '.$rec['last_name'],ENT_QUOTES)?>')">
                    ✓ انجام شده
                  </button>
                  <form method="POST" action="admin.php" style="display:inline" onsubmit="return confirm('بیمار به لیست انتظار بازگردد؟')">
                    <input type="hidden" name="admit_record_id" value="<?=$rec['id']?>">
                    <button type="submit" name="return_to_queue" value="1" class="btn btn-ghost btn-sm" style="color:var(--warn)">↩ بازگشت</button>
                  </form>
                </div></td>
                <?php endif; ?>
              </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div><!-- /tab-admitted -->

  <!-- ===== TAB: COMPLETED ===== -->
  <div class="tab-panel <?=$active_tab==='completed'?'active':''?>" id="tab-completed">

    <div class="admitted-header">
      <div class="admitted-date-badge" style="background:rgba(52,211,153,.1);border-color:rgba(52,211,153,.25);color:var(--success)">
        ✅ انجام شده‌ها
      </div>
      <span class="count-badge"><?=count($all_completed)?> بیمار</span>
    </div>

    <?php if(empty($completed_by_date)): ?>
    <div class="empty" style="background:var(--surface);border:1px solid var(--border);border-radius:16px">
      <svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      هنوز بیماری ثبت نشده
    </div>
    <?php else: ?>
    <?php
    $ocColors=['نرمال'=>['#34d399','rgba(52,211,153,'],'PCI'=>['#38bdf8','rgba(56,189,248,'],
               'درمان دارویی'=>['#fbbf24','rgba(251,191,36,'],'مشاوره جراحی قلب باز'=>['#f87171','rgba(248,113,113,']];
    ?>
    <?php foreach($completed_by_date as $day=>$recs): ?>
    <?php
      $isToday=($day===$today_str);
      $lbl=shamsiDate($day);
      $ocs=[];
      foreach($recs as $rec){ $oc=$rec['outcome']??'—'; $ocs[$oc]=($ocs[$oc]??0)+1; }
      $hid='ch-'.md5($day);
    ?>
    <div class="history-day">
      <div class="history-day-header" onclick="toggleAccordion('<?=$hid?>')">
        <div class="history-day-info">
          <div class="history-day-date">
            <?php if($isToday): ?><span class="today-dot" style="background:var(--success)"></span><?php endif; ?>
            <?=$lbl?><?php if($isToday): ?><span style="font-size:11px;color:var(--success);margin-right:6px">(امروز)</span><?php endif; ?>
          </div>
          <div class="history-day-meta">
            <span class="count-badge" style="font-size:11px"><?=count($recs)?> بیمار</span>
            <?php foreach($ocs as $oc=>$nt):
              $col=($ocColors[$oc]??['#94a3b8','rgba(148,163,184,'])[0];
              $bg =($ocColors[$oc]??['#94a3b8','rgba(148,163,184,'])[1];
            ?>
            <span class="outcome-mini-badge" style="background:<?=$bg?>.12);color:<?=$col?>;border:1px solid <?=$bg?>.25)">
              <?=htmlspecialchars($oc)?> (<?=$nt?>)
            </span>
            <?php endforeach; ?>
          </div>
        </div>
        <span class="chevron" id="ch-<?=$hid?>">▼</span>
      </div>
      <div class="history-day-body <?=$isToday?'open':''?>" id="<?=$hid?>">
        <div class="table-wrap" style="border-radius:0 0 12px 12px;border-top:none">
          <table>
            <thead><tr>
              <th>#</th><th>نام بیمار</th><th>کد ملی</th><th>تماس</th>
              <?php if($is_admin): ?><th>پزشک</th><?php endif; ?>
              <th>نسخه</th><th>بستری</th><th>نتیجه</th><th>انجام</th>
            </tr></thead>
            <tbody>
            <?php $j=1; foreach($recs as $rec):
              $oc=$rec['outcome']??'—';
              $col=($ocColors[$oc]??['#94a3b8','rgba(148,163,184,'])[0];
              $bg =($ocColors[$oc]??['#94a3b8','rgba(148,163,184,'])[1];
            ?>
            <tr>
              <td style="color:var(--muted)"><?=$j++?></td>
              <td class="name-cell"><?=htmlspecialchars($rec['first_name'].' '.$rec['last_name'])?></td>
              <td style="font-family:monospace;font-size:12px"><?=htmlspecialchars($rec['national_id'])?></td>
              <td><?=htmlspecialchars($rec['phone'])?></td>
              <?php if($is_admin): ?><td class="doctor-cell"><?=htmlspecialchars($rec['doctor_name'])?></td><?php endif; ?>
              <td><a href="<?=$upload_dir.htmlspecialchars($rec['file'])?>" target="_blank" class="file-link">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                مشاهده</a></td>
              <td class="date-cell"><?=isset($rec['admitted_at'])?substr($rec['admitted_at'],11,5):'—'?></td>
              <td><span class="badge" style="background:<?=$bg?>.12);color:<?=$col?>;border:1px solid <?=$bg?>.25)"><?=htmlspecialchars($oc)?></span></td>
              <td class="date-cell"><?=isset($rec['completed_at'])?substr($rec['completed_at'],11,5):'—'?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

  </div><!-- /tab-completed -->

  <!-- ===== TAB: EMERGENCY ===== -->
  <div class="tab-panel <?=$active_tab==='emergency'?'active':''?>" id="tab-emergency">

    <?php if(empty($emergency_pending)&&empty($emergency_approved)): ?>
    <div class="empty" style="background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:48px">
      <svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
      هیچ درخواست اورژانسی وجود ندارد
    </div>
    <?php else: ?>

    <?php if(!empty($emergency_pending)): ?>
    <div class="emg-section-title emg-pending-title">⏳ در انتظار تایید (<?=count($emergency_pending)?> نفر)</div>
    <div class="table-wrap" style="margin-bottom:20px">
      <table>
        <thead><tr>
          <th>#</th><th>نام بیمار</th><th>کد ملی</th><th>تماس</th>
          <?php if($is_admin): ?><th>پزشک</th><?php endif; ?>
          <th>نسخه</th><th>تاریخ ثبت</th><th>عملیات</th>
        </tr></thead>
        <tbody>
        <?php $i=1; foreach($emergency_pending as $r): ?>
        <tr style="background:rgba(251,191,36,0.04)">
          <td style="color:var(--muted)"><?=$i++?></td>
          <td class="name-cell"><?=htmlspecialchars($r['first_name'].' '.$r['last_name'])?></td>
          <td style="font-family:monospace;font-size:12px"><?=htmlspecialchars($r['national_id'])?></td>
          <td><?=htmlspecialchars($r['phone'])?></td>
          <?php if($is_admin): ?><td class="doctor-cell"><?=htmlspecialchars($r['doctor_name'])?></td><?php endif; ?>
          <td><a href="<?=$upload_dir.htmlspecialchars($r['file'])?>" target="_blank" class="file-link">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
            مشاهده</a></td>
          <td class="date-cell"><?=shamsiDate($r['submitted_at'])?></td>
          <td><div class="actions">
            <form method="POST" action="admin.php" style="display:inline" onsubmit="return confirm('اورژانس تایید شود؟')">
              <input type="hidden" name="record_id" value="<?=$r['id']?>">
              <button type="submit" name="approve_emergency" value="1" class="btn btn-success btn-sm">✓ تایید اورژانس</button>
            </form>
            <form method="POST" action="admin.php" style="display:inline" onsubmit="return confirm('رد شود و به لیست عادی برود؟')">
              <input type="hidden" name="record_id" value="<?=$r['id']?>">
              <button type="submit" name="reject_emergency" value="1" class="btn btn-danger btn-sm">✗ رد</button>
            </form>
          </div></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php if(!empty($emergency_approved)): ?>
    <div class="emg-section-title">🚨 اورژانسی‌های تایید شده (<?=count($emergency_approved)?> نفر)</div>
    <div class="table-wrap">
      <table>
        <thead><tr>
          <th>#</th><th>نام بیمار</th><th>کد ملی</th><th>تماس</th>
          <?php if($is_admin): ?><th>پزشک</th><?php endif; ?>
          <th>نسخه</th><th>وضعیت</th><th>تاریخ ثبت</th><th>عملیات</th>
        </tr></thead>
        <tbody>
        <?php $i=1; foreach($emergency_approved as $r): ?>
        <tr style="background:rgba(248,113,113,0.04)">
          <td style="color:var(--muted)"><?=$i++?></td>
          <td class="name-cell"><?=htmlspecialchars($r['first_name'].' '.$r['last_name'])?></td>
          <td style="font-family:monospace;font-size:12px"><?=htmlspecialchars($r['national_id'])?></td>
          <td><?=htmlspecialchars($r['phone'])?></td>
          <?php if($is_admin): ?><td class="doctor-cell"><?=htmlspecialchars($r['doctor_name'])?></td><?php endif; ?>
          <td><a href="<?=$upload_dir.htmlspecialchars($r['file'])?>" target="_blank" class="file-link">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
            مشاهده</a></td>
          <td><span class="badge <?=statusClass($r['status'])?>"><?=statusLabel($r['status'])?></span></td>
          <td class="date-cell"><?=shamsiDate($r['submitted_at'])?></td>
          <td><div class="actions">
            <!-- بستری کردن -->
            <form method="POST" action="admin.php" style="display:inline">
              <input type="hidden" name="admit_ids[]" value="<?=$r['id']?>">
              <button type="submit" name="admit_patients" value="1" class="btn btn-admit btn-sm">🏥 بستری</button>
            </form>
          </div></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php endif; ?>
  </div><!-- /tab-emergency -->

</div><!-- /wrapper -->

<!-- Note Modal -->
<div class="modal-overlay" id="note-modal">
  <div class="modal">
    <h3>ثبت نتیجه عمل</h3>
    <div class="modal-sub" id="nm-name">بیمار: —</div>
    <form method="POST" action="admin.php">
      <input type="hidden" name="record_id" id="nm-id">
      <input type="hidden" name="save_note" value="1">
      <textarea name="result_note" id="nm-text" placeholder="نتیجه عمل را بنویسید..."></textarea>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost btn-sm" onclick="closeNoteModal()">انصراف</button>
        <button type="submit" class="btn btn-success btn-sm">✓ ذخیره</button>
      </div>
    </form>
  </div>
</div>

<!-- Outcome Modal -->
<div class="modal-overlay" id="outcome-modal">
  <div class="modal">
    <h3>ثبت نتیجه بستری</h3>
    <div class="modal-sub" id="om-name">بیمار: —</div>
    <form method="POST" action="admin.php">
      <input type="hidden" name="admit_record_id" id="om-id">
      <input type="hidden" name="complete_admitted" value="1">
      <div class="outcome-options">
        <label class="outcome-option">
          <input type="radio" name="outcome" value="نرمال" required>
          <span class="outcome-label oc-normal">🟢 نرمال</span>
        </label>
        <label class="outcome-option">
          <input type="radio" name="outcome" value="PCI">
          <span class="outcome-label oc-pci">🔵 PCI</span>
        </label>
        <label class="outcome-option">
          <input type="radio" name="outcome" value="درمان دارویی">
          <span class="outcome-label oc-med">🟡 درمان دارویی</span>
        </label>
        <label class="outcome-option">
          <input type="radio" name="outcome" value="مشاوره جراحی قلب باز">
          <span class="outcome-label oc-surg">🔴 مشاوره جراحی قلب باز</span>
        </label>
      </div>
      <div class="modal-actions" style="margin-top:20px">
        <button type="button" class="btn btn-ghost btn-sm" onclick="closeOutcomeModal()">انصراف</button>
        <button type="submit" class="btn btn-success btn-sm">✓ ثبت و انتقال به انجام شده‌ها</button>
      </div>
    </form>
  </div>
</div>

<script>
// ---- Tab switching ----
function switchTab(tab, el) {
  document.querySelectorAll('.tab-panel').forEach(p=>p.classList.remove('active'));
  document.querySelectorAll('.main-tab').forEach(b=>b.classList.remove('active'));
  document.getElementById('tab-'+tab).classList.add('active');
  if(el) el.classList.add('active');
  history.replaceState(null,'','?tab='+tab);
}

// ---- Sub-tab ----
function switchSub(sub, el) {
  document.querySelectorAll('.sub-panel').forEach(p=>p.classList.remove('active'));
  document.querySelectorAll('.sub-tab').forEach(b=>b.classList.remove('active'));
  document.getElementById('sub-'+sub).classList.add('active');
  if(el) el.classList.add('active');
}

// ---- Accordion ----
function toggleAccordion(id) {
  const body = document.getElementById(id);
  const chevron = document.getElementById('ch-'+id);
  if(!body) return;
  const open = body.classList.toggle('open');
  if(chevron) chevron.style.transform = open ? 'rotate(180deg)' : '';
}

// ---- Checkboxes ----
const checkAll = document.getElementById('check-all');
const admitBtn = document.getElementById('admit-btn');
const selLabel = document.getElementById('sel-label');
const selNum   = document.getElementById('sel-num');
function updateAdmit() {
  const checked = document.querySelectorAll('.admit-check:checked');
  const n = checked.length;
  if(admitBtn) admitBtn.disabled = n===0;
  if(selLabel) selLabel.style.display = n>0?'inline':'none';
  if(selNum)   selNum.textContent = n;
  document.querySelectorAll('#patients-table tbody tr').forEach(tr=>{
    const cb = tr.querySelector('.admit-check');
    tr.classList.toggle('selected-row', cb&&cb.checked);
  });
}
if(checkAll) checkAll.addEventListener('change', function(){
  document.querySelectorAll('.admit-check:not(:disabled)').forEach(cb=>cb.checked=this.checked);
  updateAdmit();
});
document.querySelectorAll('.admit-check').forEach(cb=>cb.addEventListener('change',updateAdmit));
document.getElementById('admit-form')?.addEventListener('submit', e=>{
  if(e.submitter?.name==='admit_patients'){
    const n=document.querySelectorAll('.admit-check:checked').length;
    if(!confirm(n+' بیمار بستری شوند؟')) e.preventDefault();
  }
});

// ---- Note modal ----
function openDoneModal(id,name,note){
  document.getElementById('nm-id').value=id;
  document.getElementById('nm-name').textContent='بیمار: '+name;
  document.getElementById('nm-text').value=note||'';
  document.getElementById('note-modal').classList.add('open');
  setTimeout(()=>document.getElementById('nm-text').focus(),150);
}
function closeNoteModal(){ document.getElementById('note-modal').classList.remove('open'); }
document.getElementById('note-modal').addEventListener('click',e=>{if(e.target===document.getElementById('note-modal'))closeNoteModal();});

function handleStatusChange(sel){
  if(sel.value==='done'){
    sel.value=sel.dataset.current;
    openDoneModal(sel.dataset.id,sel.dataset.name,sel.dataset.note);
  } else { sel.closest('form').submit(); }
}

// ---- Outcome modal ----
function openOutcomeModal(id,name){
  document.getElementById('om-id').value=id;
  document.getElementById('om-name').textContent='بیمار: '+name;
  document.querySelectorAll('#outcome-modal input[type=radio]').forEach(r=>r.checked=false);
  document.getElementById('outcome-modal').classList.add('open');
}
function closeOutcomeModal(){ document.getElementById('outcome-modal').classList.remove('open'); }
document.getElementById('outcome-modal').addEventListener('click',e=>{if(e.target===document.getElementById('outcome-modal'))closeOutcomeModal();});
document.addEventListener('keydown',e=>{ if(e.key==='Escape'){ closeNoteModal(); closeOutcomeModal(); }});

<?php if($is_admin): ?>
Chart.defaults.font.family='Vazirmatn, sans-serif';
new Chart(document.getElementById('doctorChart').getContext('2d'),{
  type:'bar',
  data:{
    labels:<?=json_encode($all_doctors_chart,JSON_UNESCAPED_UNICODE)?>,
    datasets:[
      {label:'در انتظار',data:<?=json_encode($chart_pending)?>,backgroundColor:'rgba(251,191,36,.75)',borderColor:'rgba(251,191,36,1)',borderWidth:1,borderRadius:6},
      {label:'تأیید شده',data:<?=json_encode($chart_confirmed)?>,backgroundColor:'rgba(56,189,248,.75)',borderColor:'rgba(56,189,248,1)',borderWidth:1,borderRadius:6},
      {label:'انجام شده',data:<?=json_encode($chart_done)?>,backgroundColor:'rgba(52,211,153,.75)',borderColor:'rgba(52,211,153,1)',borderWidth:1,borderRadius:6}
    ]
  },
  options:{
    responsive:true,maintainAspectRatio:false,
    plugins:{
      legend:{position:'top',align:'end',labels:{color:'#94a3b8',font:{size:12},boxWidth:12,padding:16}},
      tooltip:{rtl:true,titleFont:{family:'Vazirmatn'},bodyFont:{family:'Vazirmatn'},callbacks:{label:c=>' '+c.dataset.label+': '+c.parsed.y+' بیمار'}}
    },
    scales:{
      x:{ticks:{color:'#94a3b8',font:{size:12}},grid:{color:'rgba(99,179,237,.07)'}},
      y:{beginAtZero:true,ticks:{color:'#94a3b8',precision:0,font:{size:12}},grid:{color:'rgba(99,179,237,.08)'}}
    }
  }
});
<?php endif; ?>
</script>
</body>
</html>
<?php endif; ?>
