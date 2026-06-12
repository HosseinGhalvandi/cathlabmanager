<?php
// =============================================
//  config.php — اتصال MySQL
// =============================================
if(defined('_CONFIG_LOADED')) return;
define('_CONFIG_LOADED', true);

define('DB_HOST', 'localhost');
define('DB_NAME', 'your_db_name');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_password');

function getDB(){
    static $pdo = null;
    if($pdo !== null) return $pdo;
    $pdo = new PDO(
        'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
        DB_USER, DB_PASS,
        array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        )
    );
    return $pdo;
}

if(!function_exists('toJalali')){
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
    for($i=0;$i<11;$i++){if($d<$jdi[$i]){$jm=$i+1;break;}$d-=$jdi[$i];}
    if($jm===0)$jm=12;
    return array($jy,$jm,$d+1);
}
}

if(!function_exists('shamsiDate')){
function shamsiDate($dt){
    if(!$dt) return '—';
    $p=explode(' ',$dt); $dp=explode('-',$p[0]);
    if(count($dp)<3) return $dt;
    $j=toJalali((int)$dp[0],(int)$dp[1],(int)$dp[2]);
    $mn=array('فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند');
    $time=isset($p[1])?' — '.substr($p[1],0,5):'';
    return $j[2].' '.$mn[$j[1]-1].' '.$j[0].$time;
}
}

// ساخت جداول فقط یک بار
function createTablesIfNeeded(){
    $pdo=getDB();
    $pdo->exec("CREATE TABLE IF NOT EXISTS prescriptions (
        id               VARCHAR(40)  PRIMARY KEY,
        first_name       VARCHAR(100) NOT NULL DEFAULT '',
        last_name        VARCHAR(100) NOT NULL DEFAULT '',
        national_id      VARCHAR(10)  NOT NULL DEFAULT '',
        phone            VARCHAR(20)  NOT NULL DEFAULT '',
        doctor_name      VARCHAR(100) NOT NULL DEFAULT '',
        notes            TEXT,
        file             VARCHAR(255) NOT NULL DEFAULT '',
        status           VARCHAR(20)  DEFAULT 'pending',
        is_emergency     TINYINT(1)   DEFAULT 0,
        emergency_status VARCHAR(20)  DEFAULT 'pending',
        result_note      TEXT,
        done_at          DATETIME     DEFAULT NULL,
        returned_at      DATETIME     DEFAULT NULL,
        submitted_at     DATETIME     DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS admitted (
        id           VARCHAR(40)  PRIMARY KEY,
        first_name   VARCHAR(100) NOT NULL DEFAULT '',
        last_name    VARCHAR(100) NOT NULL DEFAULT '',
        national_id  VARCHAR(10)  NOT NULL DEFAULT '',
        phone        VARCHAR(20)  NOT NULL DEFAULT '',
        doctor_name  VARCHAR(100) NOT NULL DEFAULT '',
        notes        TEXT,
        file         VARCHAR(255) NOT NULL DEFAULT '',
        status       VARCHAR(20)  DEFAULT 'pending',
        admitted_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
        submitted_at DATETIME     DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS completed (
        id           VARCHAR(40)  PRIMARY KEY,
        first_name   VARCHAR(100) NOT NULL DEFAULT '',
        last_name    VARCHAR(100) NOT NULL DEFAULT '',
        national_id  VARCHAR(10)  NOT NULL DEFAULT '',
        phone        VARCHAR(20)  NOT NULL DEFAULT '',
        doctor_name  VARCHAR(100) NOT NULL DEFAULT '',
        notes        TEXT,
        file         VARCHAR(255) NOT NULL DEFAULT '',
        status       VARCHAR(20)  DEFAULT 'pending',
        outcome      VARCHAR(100) DEFAULT NULL,
        admitted_at  DATETIME     DEFAULT NULL,
        completed_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
        submitted_at DATETIME     DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // اضافه کردن ستون‌های جدید با ignore خطا
    try { $pdo->exec("ALTER TABLE prescriptions ADD COLUMN is_emergency TINYINT(1) DEFAULT 0"); } catch(PDOException $e){}
    try { $pdo->exec("ALTER TABLE prescriptions ADD COLUMN emergency_status VARCHAR(20) DEFAULT 'pending'"); } catch(PDOException $e){}
}

createTablesIfNeeded();

if(!function_exists('dbAll')){
function dbAll($pdo,$sql,$p=array()){$s=$pdo->prepare($sql);$s->execute($p);return $s->fetchAll();}
}
if(!function_exists('dbRow')){
function dbRow($pdo,$sql,$p=array()){$s=$pdo->prepare($sql);$s->execute($p);return $s->fetch();}
}
if(!function_exists('dbExec')){
function dbExec($pdo,$sql,$p=array()){$s=$pdo->prepare($sql);$s->execute($p);return $s;}
}
