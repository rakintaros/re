
<?php
// กำหนดค่าการเชื่อมต่อฐานข้อมูล
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'equipment_system');

// ฟังก์ชันเชื่อมต่อฐานข้อมูล
function connectDB() {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }
    
    // ตั้งค่า charset เป็น utf8mb4
    mysqli_set_charset($conn, "utf8mb4");
    
    return $conn;
}

// ฟังก์ชันป้องกัน SQL Injection
function escape($conn, $data) {
    return mysqli_real_escape_string($conn, trim($data));
}

// ฟังก์ชันแปลงวันที่เป็นรูปแบบไทย
function formatDateThai($date) {
    if (empty($date) || $date == '0000-00-00' || $date == '0000-00-00 00:00:00') {
        return '-';
    }
    
    $thai_months = array(
        1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
        5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
        9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
    );
    
    $timestamp = strtotime($date);
    $day = date('d', $timestamp);
    $month = (int)date('m', $timestamp);
    $year = date('Y', $timestamp) + 543; // แปลงเป็น พ.ศ.
    $time = date('H:i', $timestamp);
    
    if ($time == '00:00') {
        return $day . ' ' . $thai_months[$month] . ' ' . $year;
    } else {
        return $day . ' ' . $thai_months[$month] . ' ' . $year . ' ' . $time . ' น.';
    }
}

// ฟังก์ชันสร้างรหัสอัตโนมัติ
function generateCode($prefix, $conn, $table, $column) {
    $year = date('Y');
    $month = date('m');
    
    $sql = "SELECT MAX(SUBSTRING($column, -4)) as max_num 
            FROM $table 
            WHERE $column LIKE '$prefix$year$month%'";
    
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    
    $next_num = ($row['max_num'] ? intval($row['max_num']) : 0) + 1;
    $code = $prefix . $year . $month . str_pad($next_num, 4, '0', STR_PAD_LEFT);
    
    return $code;
}

// ฟังก์ชันส่งการแจ้งเตือน Telegram
function sendTelegramNotification($message) {
    $conn = connectDB();
    $sql = "SELECT * FROM telegram_settings WHERE is_active = TRUE LIMIT 1";
    $result = mysqli_query($conn, $sql);
    
    if ($row = mysqli_fetch_assoc($result)) {
        $bot_token = $row['bot_token'];
        $chat_id = $row['chat_id'];
        
        if (!empty($bot_token) && !empty($chat_id)) {
            $url = "https://api.telegram.org/bot$bot_token/sendMessage";
            $data = array(
                'chat_id' => $chat_id,
                'text' => $message,
                'parse_mode' => 'HTML'
            );
            
            $options = array(
                'http' => array(
                    'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method' => 'POST',
                    'content' => http_build_query($data),
                ),
            );
            
            $context = stream_context_create($options);
            $result = @file_get_contents($url, false, $context);
        }
    }
    
    mysqli_close($conn);
}

// ฟังก์ชันตรวจสอบการ login
function checkLogin() {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}

// ฟังก์ชันตรวจสอบสิทธิ์
function checkRole($allowed_roles) {
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        header("Location: unauthorized.php");
        exit();
    }
}

// ฟังก์ชันแปลง Status
function getStatusBadge($status) {
    $badges = array(
        'normal' => '<span class="badge bg-success">ปกติ</span>',
        'repairing' => '<span class="badge bg-warning">กำลังซ่อม</span>',
        'damaged' => '<span class="badge bg-danger">ชำรุด</span>',
        'retired' => '<span class="badge bg-secondary">เลิกใช้งาน</span>',
        'pending' => '<span class="badge bg-info">รอดำเนินการ</span>',
        'assigned' => '<span class="badge bg-primary">มอบหมายแล้ว</span>',
        'in_progress' => '<span class="badge bg-warning">กำลังซ่อม</span>',
        'completed' => '<span class="badge bg-success">เสร็จสิ้น</span>',
        'cancelled' => '<span class="badge bg-danger">ยกเลิก</span>'
    );
    
    return isset($badges[$status]) ? $badges[$status] : $status;
}

// ฟังก์ชันแปลงระดับความเร่งด่วน
function getUrgencyBadge($urgency) {
    $badges = array(
        'low' => '<span class="badge bg-success">ต่ำ</span>',
        'medium' => '<span class="badge bg-warning">ปานกลาง</span>',
        'high' => '<span class="badge bg-danger">สูง</span>',
        'urgent' => '<span class="badge bg-danger">เร่งด่วน</span>'
    );
    
    return isset($badges[$urgency]) ? $badges[$urgency] : $urgency;
}
?>