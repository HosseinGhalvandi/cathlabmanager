<?php
// =============================================
//  submit-manual.php — ارسال نسخه با تاریخ دستی
// =============================================
session_start();

define('MANUAL_PASS', 'Avina12044--');

$upload_dir = __DIR__ . '/uploads/';

function getManualDB(){
    static $pdo = null;
    if($pdo) return $pdo;
    try {
        $pdo = new PDO(
            'mysql:host=localhost;dbname=h348806_cathlab;charset=utf8mb4',
            'h348806_cathlab',
            'Avina12044--',
            array(
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            )
        );
    } catch(PDOException $e){
        die('<div style="font-family:sans-serif;direction:rtl;padding:30px;color:#f87171;background:#0b1120">خطا در اتصال به دیتابیس: '.$e->getMessage().'</div>');
    }
    return $pdo;
}

// ---- Auth ----
if(isset($_POST['logout'])){ $_SESSION=array(); session_destroy(); header('Location: submit-manual.php'); exit; }
if(isset($_POST['password'])){
    if($_POST['password']===MANUAL_PASS) $_SESSION['manual_auth']=true;
    else $login_error='رمز عبور اشتباه است';
}
$logged_in = !empty($_SESSION['manual_auth']);

$success = false;
$errors  = array();

// ---- Handle submit ----
if($logged_in && isset($_POST['submit_rx'])){
    $first_name  = trim(isset($_POST['first_name'])  ? $_POST['first_name']  : '');
    $last_name   = trim(isset($_POST['last_name'])   ? $_POST['last_name']   : '');
    $national_id = preg_replace('/\D/','',isset($_POST['national_id'])  ? $_POST['national_id']  : '');
    $phone       = trim(isset($_POST['phone'])       ? $_POST['phone']       : '');
    $doctor_name = trim(isset($_POST['doctor_name']) ? $_POST['doctor_name'] : '');
    $notes       = trim(isset($_POST['notes'])       ? $_POST['notes']       : '');
    $jalali_date = trim(isset($_POST['jalali_date']) ? $_POST['jalali_date'] : '');

    // تبدیل تاریخ شمسی به میلادی
    $submitted_at = date('Y-m-d H:i:s');
    if($jalali_date){
        // فرمت ورودی: YYYY/MM/DD شمسی
        $parts = preg_split('/[\/\-]/', $jalali_date);
        if(count($parts)===3){
            $jy=(int)$parts[0]; $jm=(int)$parts[1]; $jd=(int)$parts[2];
            // جلالی به میلادی
            $jy-=979; $jm-=1; $jd-=1;
            $j_day_no=365*$jy+(int)($jy/4)*8+$jm*30+(int)($jm/6)+$jd;
            $i_day_no=$j_day_no+79;
            $i_year=1600+(int)($i_day_no/36524.25);
            $i_day_no=(int)($i_day_no-36524.25*(int)($i_year-1600));
            $i_leap=($i_year%4==0&&($i_year%100!=0||$i_year%400==0))?1:0;
            $i_year+=(int)($i_day_no/365.25);
            $i_day_no-=(int)(365.25*(int)($i_year-1600));
            if($i_day_no>=366){$i_leap=0;$i_year++;$i_day_no--;}
            $mdays=array(31,28+$i_leap,31,30,31,30,31,31,30,31,30,31);
            $i_month=0;
            foreach($mdays as $k=>$v){
                if($i_day_no<$v){$i_month=$k+1;break;}
                $i_day_no-=$v;
            }
            $i_day=$i_day_no+1;
            $submitted_at=sprintf('%04d-%02d-%02d %s',$i_year,$i_month,$i_day,date('H:i:s'));
        }
    }

    $allowed_doctors = array('دکتر کردونی','دکتر محمدی','دکتر تقی زاده','دکتر حمید');

    if(!$first_name)                              $errors[]='نام الزامی است';
    if(!$last_name)                               $errors[]='نام خانوادگی الزامی است';
    if(strlen($national_id)!==10)                 $errors[]='کد ملی باید ۱۰ رقم باشد';
    if(!$phone)                                   $errors[]='شماره تماس الزامی است';
    if(!in_array($doctor_name,$allowed_doctors))  $errors[]='پزشک معالج را انتخاب کنید';
    if(!$jalali_date)                             $errors[]='تاریخ ثبت نسخه را انتخاب کنید';

    // File upload (optional in manual mode)
    $filename = 'no-file.jpg';
    if(isset($_FILES['prescription_file']) && $_FILES['prescription_file']['error']===UPLOAD_ERR_OK){
        if(!is_dir($upload_dir)) mkdir($upload_dir,0755,true);
        $allowed=array('image/jpeg','image/png','image/gif','image/webp','application/pdf');
        $finfo=finfo_open(FILEINFO_MIME_TYPE);
        $mime=finfo_file($finfo,$_FILES['prescription_file']['tmp_name']);
        finfo_close($finfo);
        if(!in_array($mime,$allowed)) $errors[]='فرمت فایل مجاز نیست';
        elseif($_FILES['prescription_file']['size']>10*1024*1024) $errors[]='حجم فایل بیش از ۱۰ مگابایت';
        else{
            $ext=strtolower(pathinfo($_FILES['prescription_file']['name'],PATHINFO_EXTENSION));
            $filename='manual_'.date('Ymd_His').'_'.uniqid().'.'.$ext;
            if(!move_uploaded_file($_FILES['prescription_file']['tmp_name'],$upload_dir.$filename))
                $errors[]='خطا در آپلود فایل';
        }
    }

    if(empty($errors)){
        $pdo = getManualDB();
        $id = 'rx_manual_'.uniqid();
        $stmt = $pdo->prepare("
            INSERT INTO prescriptions
                (id, first_name, last_name, national_id, phone, doctor_name, notes, file, status, submitted_at, returned_at)
            VALUES
                (:id, :fn, :ln, :nid, :ph, :doc, :notes, :file, 'pending', :submitted_at, NULL)
        ");
        $stmt->execute(array(
            ':id'           => $id,
            ':fn'           => $first_name,
            ':ln'           => $last_name,
            ':nid'          => $national_id,
            ':ph'           => $phone,
            ':doc'          => $doctor_name,
            ':notes'        => $notes,
            ':file'         => $filename,
            ':submitted_at' => $submitted_at,
        ));
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ثبت نسخه دستی | کت‌لب</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--bg:#0b1120;--s:#111827;--s2:#1a2537;--bd:rgba(99,179,237,.15);--ac:#38bdf8;--ac2:#0ea5e9;--tx:#e2e8f0;--mu:#94a3b8;--ok:#34d399;--er:#f87171;--glow:rgba(56,189,248,.25)}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Vazirmatn',sans-serif;background:var(--bg);color:var(--tx);min-height:100vh;direction:rtl}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse 70% 50% at 20% 10%,rgba(56,189,248,.06) 0%,transparent 60%);pointer-events:none;z-index:0}
body::after{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(99,179,237,.025) 1px,transparent 1px),linear-gradient(90deg,rgba(99,179,237,.025) 1px,transparent 1px);background-size:40px 40px;pointer-events:none;z-index:0}

.wrap{position:relative;z-index:1;max-width:640px;margin:0 auto;padding:40px 20px 60px}

/* Login */
.login-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;position:relative;z-index:1}
.login-card{background:var(--s);border:1px solid var(--bd);border-radius:20px;padding:40px;width:100%;max-width:380px;text-align:center}
.login-card h2{font-size:22px;font-weight:800;margin-bottom:4px}
.login-card p{font-size:13px;color:var(--mu);margin-bottom:28px}
.pw-input{width:100%;background:var(--s2);border:1px solid var(--bd);border-radius:10px;color:var(--tx);font-family:'Vazirmatn',sans-serif;font-size:14px;padding:12px 14px;outline:none;text-align:center;letter-spacing:.1em;margin-bottom:12px}
.pw-input:focus{border-color:var(--ac2)}
.err-msg{color:var(--er);font-size:13px;margin-bottom:12px}

/* Header */
.header{text-align:center;margin-bottom:32px;animation:fadeDown .6s ease both}
.badge{display:inline-flex;align-items:center;gap:8px;background:rgba(56,189,248,.08);border:1px solid var(--bd);border-radius:100px;padding:6px 16px;font-size:12px;color:var(--ac);letter-spacing:.05em;margin-bottom:16px}
.badge::before{content:'';width:6px;height:6px;background:var(--ac);border-radius:50%}
.header h1{font-size:clamp(20px,5vw,28px);font-weight:800;margin-bottom:8px}
.header h1 span{color:var(--ac)}
.header p{font-size:13px;color:var(--mu)}

.topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}
.topbar a{font-size:12px;color:var(--mu);text-decoration:none;transition:color .2s}
.topbar a:hover{color:var(--ac)}

/* Card */
.card{background:var(--s);border:1px solid var(--bd);border-radius:18px;padding:28px;margin-bottom:16px;animation:fadeUp .6s ease .1s both}
.sec-title{font-size:11px;font-weight:700;color:var(--ac);letter-spacing:.12em;text-transform:uppercase;margin-bottom:18px;padding-bottom:10px;border-bottom:1px solid var(--bd);display:flex;align-items:center;gap:8px}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
@media(max-width:480px){.grid2{grid-template-columns:1fr}}
.field{display:flex;flex-direction:column;gap:6px}
.field label{font-size:11px;font-weight:700;color:var(--mu);letter-spacing:.05em}
.field label em{color:var(--ac);font-style:normal;margin-right:2px}
.field input,.field select,.field textarea{background:var(--s2);border:1px solid var(--bd);border-radius:10px;color:var(--tx);font-family:'Vazirmatn',sans-serif;font-size:14px;padding:11px 14px;outline:none;transition:all .2s;width:100%}
.field input:focus,.field select:focus,.field textarea:focus{border-color:var(--ac2);box-shadow:0 0 0 3px var(--glow)}
.field input::placeholder{color:rgba(148,163,184,.4);font-size:13px}
.field select option{background:var(--s2)}
.field textarea{resize:vertical;min-height:80px}

/* Date picker */
.date-field{position:relative}
.date-input-wrap{position:relative;display:flex;align-items:center}
.date-input-wrap input{cursor:pointer;padding-left:44px}
.cal-icon{position:absolute;left:14px;color:var(--ac);pointer-events:none}
.date-display{font-size:12px;color:var(--ok);margin-top:5px;min-height:18px;font-weight:600}

/* Jalali Calendar */
.cal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:200;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px)}
.cal-overlay.open{display:flex}
.cal-box{background:var(--s);border:1px solid var(--bd);border-radius:18px;padding:20px;width:100%;max-width:340px;animation:fadeUp .25s ease both}
.cal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
.cal-title{font-size:15px;font-weight:800}
.cal-nav{background:var(--s2);border:1px solid var(--bd);border-radius:8px;width:34px;height:34px;display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--tx);font-size:16px;transition:all .2s}
.cal-nav:hover{border-color:var(--ac);color:var(--ac)}
.cal-selects{display:flex;gap:8px;margin-bottom:16px}
.cal-selects select{background:var(--s2);border:1px solid var(--bd);border-radius:8px;color:var(--tx);font-family:'Vazirmatn',sans-serif;font-size:13px;padding:7px 10px;outline:none;flex:1}
.cal-selects select:focus{border-color:var(--ac2)}
.cal-days-header{display:grid;grid-template-columns:repeat(7,1fr);gap:4px;margin-bottom:8px}
.cal-days-header span{text-align:center;font-size:11px;color:var(--mu);font-weight:700;padding:4px 0}
.cal-days{display:grid;grid-template-columns:repeat(7,1fr);gap:4px}
.cal-day{
  aspect-ratio:1;display:flex;align-items:center;justify-content:center;
  border-radius:8px;font-size:13px;cursor:pointer;
  transition:all .15s;border:1px solid transparent;
  background:transparent;color:var(--tx);
}
.cal-day:hover{background:var(--s2);border-color:var(--bd)}
.cal-day.today{border-color:rgba(56,189,248,.3);color:var(--ac)}
.cal-day.selected{background:var(--ac2);color:#fff;font-weight:700;box-shadow:0 0 12px rgba(14,165,233,.4)}
.cal-day.empty{cursor:default;pointer-events:none}
.cal-day.other-month{color:#334155}
.cal-footer{display:flex;gap:8px;margin-top:16px}
.cal-cancel{flex:1;padding:10px;background:var(--s2);border:1px solid var(--bd);border-radius:10px;color:var(--mu);font-family:'Vazirmatn',sans-serif;font-size:13px;font-weight:600;cursor:pointer}
.cal-confirm{flex:2;padding:10px;background:linear-gradient(135deg,var(--ac2),#2563eb);color:#fff;border:none;border-radius:10px;font-family:'Vazirmatn',sans-serif;font-size:13px;font-weight:700;cursor:pointer}
.cal-confirm:hover{transform:translateY(-1px);box-shadow:0 4px 14px rgba(14,165,233,.3)}

/* File upload */
.file-area{border:2px dashed var(--bd);border-radius:12px;padding:24px;text-align:center;cursor:pointer;transition:all .25s;background:var(--s2);position:relative}
.file-area:hover{border-color:var(--ac);background:rgba(56,189,248,.04)}
.file-area input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.file-icon{width:38px;height:38px;margin:0 auto 8px;background:rgba(56,189,248,.1);border-radius:10px;display:flex;align-items:center;justify-content:center;color:var(--ac)}
.file-text{font-size:13px;margin-bottom:3px}
.file-hint{font-size:11px;color:var(--mu)}
.file-preview{margin-top:8px;display:none;align-items:center;gap:8px;background:rgba(52,211,153,.07);border:1px solid rgba(52,211,153,.2);border-radius:8px;padding:7px 12px;font-size:12px;color:var(--ok)}

/* Alerts */
.alert{border-radius:12px;padding:13px 16px;margin-bottom:20px;font-size:13px;display:flex;align-items:center;gap:10px;animation:fadeDown .4s ease both}
.alert-ok{background:rgba(52,211,153,.08);border:1px solid rgba(52,211,153,.25);color:var(--ok)}
.alert-er{background:rgba(248,113,113,.08);border:1px solid rgba(248,113,113,.25);color:var(--er)}
.alert-er ul{margin-top:6px;padding-right:14px;line-height:2}

/* Submit */
.btn-submit{width:100%;padding:14px;background:linear-gradient(135deg,var(--ac2),#2563eb);color:#fff;border:none;border-radius:12px;font-family:'Vazirmatn',sans-serif;font-size:15px;font-weight:700;cursor:pointer;transition:all .25s}
.btn-submit:hover{transform:translateY(-1px);box-shadow:0 8px 24px rgba(14,165,233,.35)}

.btn{padding:8px 16px;border-radius:8px;border:none;font-family:'Vazirmatn',sans-serif;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s}
.btn-g{background:rgba(148,163,184,.1);color:var(--mu)}
.btn-g:hover{background:rgba(148,163,184,.2);color:var(--tx)}

@keyframes fadeDown{from{opacity:0;transform:translateY(-14px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
</style>
</head>
<body>

<?php if(!$logged_in): ?>
<div class="login-wrap">
  <div class="login-card">
    <h2>ثبت نسخه دستی</h2>
    <p>اتاق عمل کت‌لب — دسترسی محدود</p>
    <?php if(isset($login_error)): ?><div class="err-msg"><?php echo htmlspecialchars($login_error); ?></div><?php endif; ?>
    <form method="POST">
      <input type="password" name="password" class="pw-input" placeholder="رمز عبور" autofocus>
      <button type="submit" class="btn" style="background:linear-gradient(135deg,var(--ac2),#2563eb);color:#fff;width:100%;padding:12px">ورود</button>
    </form>
  </div>
</div>

<?php else: ?>
<div class="wrap">

  <div class="topbar">
    <a href="admin.php">← پنل مدیریت</a>
    <form method="POST" style="display:inline">
      <button type="submit" name="logout" value="1" class="btn btn-g">خروج</button>
    </form>
  </div>

  <div class="header">
    <div class="badge">ثبت دستی</div>
    <h1>ثبت نسخه <span>با تاریخ دستی</span></h1>
    <p>تاریخ ثبت نسخه را خودتان از تقویم شمسی انتخاب کنید</p>
  </div>

  <?php if($success): ?>
  <div class="alert alert-ok">
    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    نسخه با موفقیت ثبت شد.
  </div>
  <?php endif; ?>

  <?php if(!empty($errors)): ?>
  <div class="alert alert-er">
    <div>
      <strong>خطا:</strong>
      <ul><?php foreach($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul>
    </div>
  </div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data">

    <!-- مشخصات بیمار -->
    <div class="card">
      <div class="sec-title">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        مشخصات بیمار
      </div>
      <div class="grid2">
        <div class="field">
          <label>نام <em>*</em></label>
          <input type="text" name="first_name" placeholder="نام" value="<?php echo isset($_POST['first_name'])&&!$success?htmlspecialchars($_POST['first_name']):''; ?>" required>
        </div>
        <div class="field">
          <label>نام خانوادگی <em>*</em></label>
          <input type="text" name="last_name" placeholder="نام خانوادگی" value="<?php echo isset($_POST['last_name'])&&!$success?htmlspecialchars($_POST['last_name']):''; ?>" required>
        </div>
      </div>
      <div class="grid2">
        <div class="field">
          <label>کد ملی <em>*</em></label>
          <input type="text" name="national_id" placeholder="۱۰ رقم" maxlength="10" value="<?php echo isset($_POST['national_id'])&&!$success?htmlspecialchars($_POST['national_id']):''; ?>" required>
        </div>
        <div class="field">
          <label>شماره تماس <em>*</em></label>
          <input type="tel" name="phone" placeholder="۰۹۱۲۳۴۵۶۷۸۹" value="<?php echo isset($_POST['phone'])&&!$success?htmlspecialchars($_POST['phone']):''; ?>" required>
        </div>
      </div>
    </div>

    <!-- پزشک و تاریخ -->
    <div class="card">
      <div class="sec-title">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        پزشک و تاریخ ثبت
      </div>
      <div class="grid2" style="margin-bottom:16px">
        <div class="field">
          <label>پزشک معالج <em>*</em></label>
          <select name="doctor_name" required>
            <option value="">انتخاب کنید...</option>
            <?php foreach(array('دکتر کردونی','دکتر محمدی','دکتر تقی زاده','دکتر حمید') as $d): ?>
            <option value="<?php echo $d; ?>" <?php echo (isset($_POST['doctor_name'])&&$_POST['doctor_name']===$d&&!$success)?'selected':''; ?>>
              <?php echo $d; ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>تاریخ ثبت نسخه <em>*</em></label>
          <div class="date-field">
            <div class="date-input-wrap">
              <input type="text" id="date-display" placeholder="انتخاب از تقویم..." readonly onclick="openCal()" value="<?php echo isset($_POST['jalali_date'])&&!$success?htmlspecialchars($_POST['jalali_date']):''; ?>">
              <input type="hidden" name="jalali_date" id="jalali_date" value="<?php echo isset($_POST['jalali_date'])&&!$success?htmlspecialchars($_POST['jalali_date']):''; ?>">
              <svg class="cal-icon" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <div class="date-display" id="date-shamsi"></div>
          </div>
        </div>
      </div>

      <div class="field">
        <label>توضیحات</label>
        <textarea name="notes" placeholder="توضیحات اضافه..."><?php echo isset($_POST['notes'])&&!$success?htmlspecialchars($_POST['notes']):''; ?></textarea>
      </div>
    </div>

    <!-- آپلود نسخه -->
    <div class="card">
      <div class="sec-title">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
        تصویر نسخه (اختیاری)
      </div>
      <div class="file-area" id="drop-area">
        <input type="file" name="prescription_file" id="file-input" accept="image/*,.pdf">
        <div class="file-icon">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
        </div>
        <div class="file-text">تصویر یا PDF را اینجا رها کنید</div>
        <div class="file-hint">JPG، PNG، PDF — حداکثر ۱۰ مگابایت</div>
      </div>
      <div class="file-preview" id="file-preview">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"/></svg>
        <span id="file-name"></span>
      </div>
    </div>

    <button type="submit" name="submit_rx" class="btn-submit">ثبت نسخه</button>
  </form>

</div><!-- /wrap -->

<!-- Jalali Calendar -->
<div class="cal-overlay" id="cal-overlay" onclick="closeCal()">
  <div class="cal-box" onclick="event.stopPropagation()">
    <div class="cal-header">
      <button class="cal-nav" onclick="changeMonth(-1)">&#8249;</button>
      <div class="cal-title" id="cal-title">—</div>
      <button class="cal-nav" onclick="changeMonth(1)">&#8250;</button>
    </div>
    <div class="cal-selects">
      <select id="cal-month" onchange="renderCal()">
        <?php
        $mnames=array('فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند');
        foreach($mnames as $i=>$mn) echo '<option value="'.($i+1).'">'.($i+1).' — '.$mn.'</option>';
        ?>
      </select>
      <select id="cal-year" onchange="renderCal()">
        <?php for($y=1390;$y<=1420;$y++) echo '<option value="'.$y.'">'.$y.'</option>'; ?>
      </select>
    </div>
    <div class="cal-days-header">
      <span>ش</span><span>ی</span><span>د</span><span>س</span><span>چ</span><span>پ</span><span>ج</span>
    </div>
    <div class="cal-days" id="cal-days"></div>
    <div class="cal-footer">
      <button type="button" class="cal-cancel" onclick="closeCal()">انصراف</button>
      <button type="button" class="cal-confirm" onclick="confirmDate()">تأیید تاریخ</button>
    </div>
  </div>
</div>

<script>
// ---- File upload ----
var fileInput=document.getElementById('file-input');
var preview=document.getElementById('file-preview');
var fname=document.getElementById('file-name');
if(fileInput){
  fileInput.addEventListener('change',function(){
    if(fileInput.files.length){ fname.textContent=fileInput.files[0].name; preview.style.display='flex'; }
  });
}

// ---- Jalali Calendar ----
var selYear=0, selMonth=0, selDay=0;
var curYear=0, curMonth=0;
var todayJ=getTodayJalali();

function getTodayJalali(){
  var now=new Date();
  return gToJ(now.getFullYear(),now.getMonth()+1,now.getDate());
}

function gToJ(gy,gm,gd){
  var g=[31,28,31,30,31,30,31,31,30,31,30,31];
  var leap=(gy%4==0&&(gy%100!=0||gy%400==0));
  if(gm>2&&leap) gd++;
  var gDays=0;
  for(var i=0;i<gm-1;i++) gDays+=g[i];
  gDays+=gd;
  var gYear=gy-1600; var gDay=gDays;
  var jDayNo=gYear*365+(int(gYear/4))-int(gYear/100)+int(gYear/400);
  jDayNo+=gDay-1; jDayNo-=78;
  var jYear=979+33*int(jDayNo/12053); jDayNo%=12053;
  jYear+=4*int(jDayNo/1461); jDayNo%=1461;
  if(jDayNo>=366){jYear+=int((jDayNo-1)/365);jDayNo=(jDayNo-1)%365;}
  var jm=0; var jdi=[31,31,31,31,31,31,30,30,30,30,30,29];
  for(var i=0;i<11;i++){if(jDayNo<jdi[i]){jm=i+1;break;}jDayNo-=jdi[i];}
  if(!jm) jm=12;
  return [jYear,jm,jDayNo+1];
}
function int(n){ return Math.floor(n); }

function getDaysInJMonth(y,m){
  if(m<=6) return 31;
  if(m<=11) return 30;
  // اسفند: سال کبیسه جلالی
  return ((y-474)%2820+474+38)*682%2816<682 ? 30 : 29;
}

function getJWeekDay(jy,jm,jd){
  // تبدیل جلالی به میلادی ساده برای روز هفته
  var d=new Date(jToG(jy,jm,jd));
  var w=d.getDay(); // 0=sun
  // شنبه=0 در تقویم فارسی
  return (w+1)%7; // sat=0,sun=1,...,fri=6
}

function jToG(jy,jm,jd){
  jy-=979; jm-=1; jd-=1;
  var j_d=365*jy+int(jy/33)*8+int((jy%33+3)/4)+jm*30+int(jm/6)+jd;
  var g_d=j_d+79;
  var g_year=1600+400*int(g_d/146097);
  g_d=g_d%146097;
  var leap=true;
  if(g_d>=36525){g_d--;g_year+=100*int(g_d/36524);g_d=g_d%36524;if(g_d>=365)g_d++;else leap=false;}
  g_year+=4*int(g_d/1461); g_d=g_d%1461;
  if(g_d>=366){leap=false;g_d--;g_year+=int(g_d/365);g_d=g_d%365;}
  var gdi=[31,29,31,30,31,30,31,31,30,31,30,31];
  if(!leap) gdi[1]=28;
  var g_month=1;
  for(var i=0;i<12;i++){if(g_d<gdi[i]){g_month=i+1;break;}g_d-=gdi[i];}
  return g_year+'-'+pad(g_month)+'-'+pad(g_d+1);
}
function pad(n){ return n<10?'0'+n:n; }

var jMNames=['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];

function openCal(){
  var existing=document.getElementById('jalali_date').value;
  if(existing){
    var p=existing.split('/');
    if(p.length===3){curYear=parseInt(p[0]);curMonth=parseInt(p[1]);selYear=curYear;selMonth=curMonth;selDay=parseInt(p[2]);}
  } else {
    curYear=todayJ[0]; curMonth=todayJ[1];
    selYear=0; selMonth=0; selDay=0;
  }
  document.getElementById('cal-year').value=curYear;
  document.getElementById('cal-month').value=curMonth;
  renderCal();
  document.getElementById('cal-overlay').classList.add('open');
}
function closeCal(){ document.getElementById('cal-overlay').classList.remove('open'); }

function changeMonth(d){
  curMonth+=d;
  if(curMonth>12){curMonth=1;curYear++;}
  if(curMonth<1){curMonth=12;curYear--;}
  document.getElementById('cal-year').value=curYear;
  document.getElementById('cal-month').value=curMonth;
  renderCal();
}

function renderCal(){
  curYear=parseInt(document.getElementById('cal-year').value);
  curMonth=parseInt(document.getElementById('cal-month').value);
  document.getElementById('cal-title').textContent=jMNames[curMonth-1]+' '+curYear;
  var days=getDaysInJMonth(curYear,curMonth);
  var startWd=getJWeekDay(curYear,curMonth,1);
  var html='';
  for(var i=0;i<startWd;i++) html+='<div class="cal-day empty"></div>';
  for(var d=1;d<=days;d++){
    var cls='cal-day';
    if(d===todayJ[2]&&curMonth===todayJ[1]&&curYear===todayJ[0]) cls+=' today';
    if(d===selDay&&curMonth===selMonth&&curYear===selYear) cls+=' selected';
    html+='<div class="'+cls+'" onclick="selectDay('+d+')">'+d+'</div>';
  }
  document.getElementById('cal-days').innerHTML=html;
}

function selectDay(d){
  selDay=d; selMonth=curMonth; selYear=curYear;
  renderCal();
}

function confirmDate(){
  if(!selDay){ closeCal(); return; }
  var str=selYear+'/'+pad(selMonth)+'/'+pad(selDay);
  document.getElementById('jalali_date').value=str;
  document.getElementById('date-display').value=str;
  document.getElementById('date-shamsi').textContent=selDay+' '+jMNames[selMonth-1]+' '+selYear;
  closeCal();
}

// Init date display if value exists
(function(){
  var v=document.getElementById('jalali_date').value;
  if(v){
    var p=v.split('/');
    if(p.length===3)
      document.getElementById('date-shamsi').textContent=parseInt(p[2])+' '+jMNames[parseInt(p[1])-1]+' '+p[0];
  }
})();
</script>
<?php endif; ?>
</body>
</html>
