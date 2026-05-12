<?php
session_start();

// 1. ตั้งค่า Driver ให้ส่งแค่ Error จริงๆ ไม่เอา Warning (แก้ปัญหา IM006 ตรงจุดที่สุด)
sqlsrv_configure("WarningsReturnAsErrors", 0);

$host = 'localhost\sqlexpress'; 
$user = 'sqlconn';
$password = 'conn2548';
$database = 'rp_repair_system';

// 2. ปรับ Option ให้เหลือเท่าที่จำเป็น
$connectionInfo = [
    "Database" => $database,
    "UID"      => $user,
    "PWD"      => $password,
    "CharacterSet" => "UTF-8",
    "Encrypt" => 0,
    "TrustServerCertificate" => 1
];

// 3. ทำการเชื่อมต่อ
$conn = sqlsrv_connect($host, $connectionInfo);

// 4. ตรวจสอบแบบใจดี (ถ้ายังได้ false อีก ให้ลองวิธีที่ 2 ด้านล่าง)
if ($conn === false) {
    echo "เชื่อมต่อไม่ได้จริงๆ ครับ:<br />";
    print_r(sqlsrv_errors());
    die();
}

// ทดสอบดึงข้อมูลสั้นๆ
$stmt = sqlsrv_query($conn, "SELECT 1");
if ($stmt) {
    // ถ้ามาถึงตรงนี้ได้ คือสำเร็จ 100% ครับ!
}

// ทดสอบ Query ดูว่ารอดไหม
$tsql = "SELECT @@VERSION as SQL_VERSION";
$getVer = sqlsrv_query($conn, $tsql);
if ($getVer === false) {
    die(print_r(sqlsrv_errors(), true));
}

// ฟังก์ชันสำหรับการแปลงวันที่เป็นรูปแบบไทย
function thai_date($datetime, $format = 'j F Y เวลา H:i น.') {
    if (!$datetime) return "";

    $thai_month_arr = [
        "0"  => "",
        "1"  => "มกราคม",
        "2"  => "กุมภาพันธ์",
        "3"  => "มีนาคม",
        "4"  => "เมษายน",
        "5"  => "พฤษภาคม",
        "6"  => "มิถุนายน",
        "7"  => "กรกฎาคม",
        "8"  => "สิงหาคม",
        "9"  => "กันยายน",
        "10" => "ตุลาคม",
        "11" => "พฤศจิกายน",
        "12" => "ธันวาคม"
    ];

    // รองรับทั้ง string และ DateTime object (sqlsrv คืน DateTime บางกรณี)
    if ($datetime instanceof DateTime) {
        $timestamp = $datetime->getTimestamp();
    } else {
        $timestamp = strtotime($datetime);
    }

    $thai_date_return  = date("j", $timestamp);
    $thai_date_return .= " " . $thai_month_arr[date("n", $timestamp)];
    $thai_date_return .= " " . (date("Y", $timestamp) + 543);

    if (strpos($format, 'H:i') !== false) {
        $thai_date_return .= " เวลา " . date("H:i", $timestamp) . " น.";
    }

    return $thai_date_return;
}

// ฟังก์ชันสำหรับกรองข้อมูลที่รับมาจากฟอร์ม
// หมายเหตุ: sqlsrv ใช้ Prepared Statements แทน escape string
// ฟังก์ชันนี้ใช้สำหรับ sanitize ทั่วไปเท่านั้น (ไม่ใช่สำหรับ SQL)
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// ฟังก์ชันสำหรับส่งการแจ้งเตือนผ่าน Telegram
function send_telegram_notification($message) {
    global $conn;

    // ดึงข้อมูลการตั้งค่า Telegram ด้วย Prepared Statement
    $query  = "SELECT setting_value FROM settings WHERE setting_name = ?";

    $stmt   = sqlsrv_query($conn, $query, ['telegram_bot_token']);
    $token  = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)['setting_value'] : '';
    if ($stmt) sqlsrv_free_stmt($stmt);

    $stmt     = sqlsrv_query($conn, $query, ['telegram_chat_id']);
    $chat_id  = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)['setting_value'] : '';
    if ($stmt) sqlsrv_free_stmt($stmt);

    $stmt                 = sqlsrv_query($conn, $query, ['notification_enabled']);
    $notification_enabled = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)['setting_value'] : '';
    if ($stmt) sqlsrv_free_stmt($stmt);

    // ตรวจสอบว่าเปิดใช้งานการแจ้งเตือนหรือไม่
    if ($notification_enabled !== 'true' || empty($token) || empty($chat_id)) {
        return false;
    }

    // ส่งข้อความผ่าน Telegram Bot API
    $url  = "https://api.telegram.org/bot" . $token . "/sendMessage";
    $data = [
        'chat_id'    => $chat_id,
        'text'       => $message,
        'parse_mode' => 'HTML'
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $response = curl_exec($ch);
    curl_close($ch);

    return ($response !== false);
}

// ฟังก์ชันสำหรับบันทึกประวัติการอัพเดท
function add_request_history($request_id, $user_id, $status, $remark = '') {
    global $conn;

    $remark = clean_input($remark);

    // ใช้ Prepared Statement แทน string interpolation
    $query  = "INSERT INTO request_history (request_id, user_id, status, remark) 
               VALUES (?, ?, ?, ?)";
    $params = [$request_id, $user_id, $status, $remark];
    $stmt   = sqlsrv_query($conn, $query, $params);

    if ($stmt === false) {
        return false;
    }
    sqlsrv_free_stmt($stmt);
    return true;
}

// ฟังก์ชันแสดงข้อความแจ้งเตือน
function show_alert($message, $type = 'success') {
    return "<div class='alert alert-$type alert-dismissible fade show' role='alert'>
                $message
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
            </div>";
}