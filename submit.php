<?php
// =============================================
//  submit.php — ثبت نسخه جدید در MySQL
// =============================================
require_once __DIR__ . '/config.php';

function redirect($url){ header("Location: $url"); exit; }

if($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');

$first_name  = trim($_POST['first_name']  ?? '');
$last_name   = trim($_POST['last_name']   ?? '');
$national_id = trim($_POST['national_id'] ?? '');
$phone       = trim($_POST['phone']       ?? '');
$doctor_name = trim($_POST['doctor_name'] ?? '');
$notes       = trim($_POST['notes']       ?? '');
$is_emergency = isset($_POST['is_emergency']) ? 1 : 0;

if(!$first_name||!$last_name||!$national_id||!$phone||!$doctor_name)
    redirect('index.php?error=لطفاً تمام فیلدهای ضروری را پر کنید');
if(!preg_match('/^\d{10}$/',$national_id))
    redirect('index.php?error=کد ملی باید ۱۰ رقم باشد');

// ---- آپلود فایل ----
if(!isset($_FILES['prescription_file'])||$_FILES['prescription_file']['error']!==UPLOAD_ERR_OK)
    redirect('index.php?error=لطفاً تصویر نسخه را بارگذاری کنید');

$upload_dir=__DIR__.'/uploads/';
if(!is_dir($upload_dir)) mkdir($upload_dir,0755,true);

$allowed=['image/jpeg','image/png','image/gif','image/webp','application/pdf'];
$finfo=finfo_open(FILEINFO_MIME_TYPE);
$mime=finfo_file($finfo,$_FILES['prescription_file']['tmp_name']);
finfo_close($finfo);

if(!in_array($mime,$allowed)) redirect('index.php?error=فرمت فایل مجاز نیست');
if($_FILES['prescription_file']['size']>10*1024*1024) redirect('index.php?error=حجم فایل بیش از ۱۰ مگابایت است');

$ext=strtolower(pathinfo($_FILES['prescription_file']['name'],PATHINFO_EXTENSION));
$filename=date('Ymd_His').'_'.uniqid().'.'.$ext;
if(!move_uploaded_file($_FILES['prescription_file']['tmp_name'],$upload_dir.$filename))
    redirect('index.php?error=خطا در ذخیره فایل');

// ---- ذخیره در دیتابیس ----
$pdo=getDB();
$id='rx_'.uniqid();
$stmt=$pdo->prepare("
    INSERT INTO prescriptions
        (id,first_name,last_name,national_id,phone,doctor_name,notes,file,status,is_emergency,emergency_status,submitted_at)
    VALUES
        (:id,:fn,:ln,:nid,:ph,:doc,:notes,:file,'pending',:emg,'pending',NOW())
");
$stmt->execute([
    ':id'   =>$id,
    ':fn'   =>$first_name,
    ':ln'   =>$last_name,
    ':nid'  =>$national_id,
    ':ph'   =>$phone,
    ':doc'  =>$doctor_name,
    ':notes'=>$notes,
    ':file' =>$filename,
    ':emg'  =>$is_emergency,
]);

redirect('index.php?success=1');
