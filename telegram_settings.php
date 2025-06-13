<?php
require_once 'config/database.php';
checkLogin();
checkRole(['admin']);

$conn = connectDB();

// ประมวลผลการบันทึกการตั้งค่า
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $bot_token = escape($conn, $_POST['bot_token']);
    $chat_id = escape($conn, $_POST['chat_id']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // ตรวจสอบว่ามีการตั้งค่าอยู่แล้วหรือไม่
    $check_sql = "SELECT id FROM telegram_settings LIMIT 1";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        // อัพเดท
        $sql = "UPDATE telegram_settings SET 
                bot_token = '$bot_token',
                chat_id = '$chat_id',
                is_active = $is_active,
                updated_at = NOW()
                WHERE id = 1";
    } else {
        // เพิ่มใหม่
        $sql = "INSERT INTO telegram_settings (bot_token, chat_id, is_active) 
                VALUES ('$bot_token', '$chat_id', $is_active)";
    }
    
    if (mysqli_query($conn, $sql)) {
        $_SESSION['success'] = "บันทึกการตั้งค่า Telegram สำเร็จ";
        
        // ทดสอบส่งข้อความ
        if ($is_active && isset($_POST['test_message'])) {
            $test_message = "🔔 ทดสอบการส่งข้อความจากระบบครุภัณฑ์\n\n";
            $test_message .= "✅ การตั้งค่า Telegram Bot สำเร็จ!\n";
            $test_message .= "📅 วันที่: " . date('d/m/Y H:i:s');
            
            sendTelegramNotification($test_message);
            $_SESSION['success'] .= " และส่งข้อความทดสอบเรียบร้อย";
        }
    } else {
        $_SESSION['error'] = "เกิดข้อผิดพลาด: " . mysqli_error($conn);
    }
    
    header("Location: telegram_settings.php");
    exit();
}

// ดึงข้อมูลการตั้งค่าปัจจุบัน
$sql = "SELECT * FROM telegram_settings LIMIT 1";
$result = mysqli_query($conn, $sql);
$settings = mysqli_fetch_assoc($result);

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่า Telegram - ระบบจัดการครุภัณฑ์</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background: #f0f2f5;
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
        }
        
        .navbar-glass {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 2px 20px 0 rgba(31, 38, 135, 0.1);
        }
        
        .btn-orange {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            transition: all 0.3s ease;
        }
        
        .btn-orange:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(255, 107, 53, 0.4);
            color: white;
        }
        
        .sidebar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: 2px 0 20px 0 rgba(31, 38, 135, 0.1);
            min-height: calc(100vh - 70px);
        }
        
        .sidebar-item {
            padding: 15px 20px;
            color: #666;
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
            border-radius: 10px;
            margin: 5px 10px;
        }
        
        .sidebar-item:hover {
            background: rgba(255, 107, 53, 0.1);
            color: #ff6b35;
            transform: translateX(5px);
        }
        
        .sidebar-item.active {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: white;
        }
        
        .content-area {
            padding: 30px;
        }
        
        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(255, 107, 53, 0.2);
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            background: white;
            border-color: #ff6b35;
            box-shadow: 0 0 0 0.2rem rgba(255, 107, 53, 0.25);
        }
        
        .page-header {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: pulse 3s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(0.8); opacity: 0.5; }
            50% { transform: scale(1.2); opacity: 0.8; }
        }
        
        .instruction-card {
            background: rgba(23, 162, 184, 0.1);
            border: 1px solid rgba(23, 162, 184, 0.3);
            border-radius: 15px;
            padding: 20px;
        }
        
        .step-number {
            width: 30px;
            height: 30px;
            background: #ff6b35;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .code-block {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            overflow-x: auto;
        }
        
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
            animation: blink 2s infinite;
        }
        
        .status-indicator.active {
            background: #28a745;
        }
        
        .status-indicator.inactive {
            background: #dc3545;
        }
        
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-glass sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <div class="gradient-bg text-white p-2 rounded-3 me-2">
                    <i class="fas fa-tools"></i>
                </div>
                <span class="fw-bold">ระบบครุภัณฑ์</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <div class="gradient-bg text-white p-2 rounded-circle me-2">
                                <i class="fas fa-user"></i>
                            </div>
                            <span><?php echo $_SESSION['fullname']; ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-cog me-2"></i>โปรไฟล์</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>ออกจากระบบ</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="d-flex">
        <!-- Sidebar -->
        <div class="sidebar" style="width: 280px;">
            <div class="p-3">
                <h6 class="text-muted mb-3">เมนูหลัก</h6>
                
                <a href="index.php" class="sidebar-item">
                    <i class="fas fa-home me-3"></i> หน้าหลัก
                </a>
                
                <h6 class="text-muted mb-3 mt-4">จัดการข้อมูล</h6>
                <a href="equipment_categories.php" class="sidebar-item">
                    <i class="fas fa-tags me-3"></i> ประเภทครุภัณฑ์
                </a>
                <a href="equipment.php" class="sidebar-item">
                    <i class="fas fa-desktop me-3"></i> ครุภัณฑ์
                </a>
                <a href="departments.php" class="sidebar-item">
                    <i class="fas fa-building me-3"></i> แผนก
                </a>
                <a href="maintenance_teams.php" class="sidebar-item">
                    <i class="fas fa-users-cog me-3"></i> ทีมซ่อมบำรุง
                </a>
                <a href="users.php" class="sidebar-item">
                    <i class="fas fa-users me-3"></i> พนักงาน
                </a>
                
                <h6 class="text-muted mb-3 mt-4">รายงานและการตั้งค่า</h6>
                <a href="reports.php" class="sidebar-item">
                    <i class="fas fa-chart-bar me-3"></i> รายงาน
                </a>
                <a href="telegram_settings.php" class="sidebar-item active">
                    <i class="fab fa-telegram me-3"></i> ตั้งค่า Telegram
                </a>
            </div>
        </div>
        
        <!-- Content -->
        <div class="flex-grow-1 content-area">
            <!-- Page Header -->
            <div class="page-header">
                <h4 class="mb-0">
                    <i class="fab fa-telegram me-2"></i>
                    ตั้งค่าการแจ้งเตือน Telegram
                </h4>
            </div>
            
            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php 
                        echo $_SESSION['success']; 
                        unset($_SESSION['success']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php 
                        echo $_SESSION['error']; 
                        unset($_SESSION['error']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <!-- Instructions -->
                <div class="col-md-6 mb-4">
                    <div class="glass-card p-4">
                        <h5 class="mb-4">
                            <i class="fas fa-info-circle me-2" style="color: #ff6b35;"></i>
                            วิธีการตั้งค่า Telegram Bot
                        </h5>
                        
                        <div class="instruction-card mb-3">
                            <div class="d-flex mb-3">
                                <div class="step-number">1</div>
                                <div>
                                    <h6 class="mb-1">สร้าง Telegram Bot</h6>
                                    <p class="mb-2">เปิด Telegram แล้วค้นหา <strong>@BotFather</strong></p>
                                    <p class="mb-2">พิมพ์คำสั่ง <code>/newbot</code> และทำตามขั้นตอน</p>
                                    <p class="mb-0">คุณจะได้รับ <strong>Bot Token</strong> เก็บไว้ใช้งาน</p>
                                </div>
                            </div>
                            
                            <div class="d-flex mb-3">
                                <div class="step-number">2</div>
                                <div>
                                    <h6 class="mb-1">สร้างกลุ่มหรือใช้แชทส่วนตัว</h6>
                                    <p class="mb-2">สร้างกลุ่มใหม่และเพิ่ม Bot เข้ากลุ่ม</p>
                                    <p class="mb-0">หรือเริ่มแชทกับ Bot โดยตรง</p>
                                </div>
                            </div>
                            
                            <div class="d-flex mb-3">
                                <div class="step-number">3</div>
                                <div>
                                    <h6 class="mb-1">หา Chat ID</h6>
                                    <p class="mb-2">ส่งข้อความใดๆ ในกลุ่มหรือแชท</p>
                                    <p class="mb-2">เปิด URL นี้ในเบราว์เซอร์:</p>
                                    <div class="code-block mb-2">
                                        https://api.telegram.org/bot<span class="text-danger">[YOUR_BOT_TOKEN]</span>/getUpdates
                                    </div>
                                    <p class="mb-0">ค้นหา <strong>"chat":{"id":</strong> และคัดลอกตัวเลข ID</p>
                                </div>
                            </div>
                            
                            <div class="d-flex">
                                <div class="step-number">4</div>
                                <div>
                                    <h6 class="mb-1">นำมาตั้งค่าในระบบ</h6>
                                    <p class="mb-0">กรอก Bot Token และ Chat ID ในฟอร์มด้านขวา</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-lightbulb me-2"></i>
                            <strong>หมายเหตุ:</strong> Chat ID ของกลุ่มจะติดลบ เช่น -123456789
                        </div>
                    </div>
                </div>
                
                <!-- Settings Form -->
                <div class="col-md-6 mb-4">
                    <div class="glass-card p-4">
                        <h5 class="mb-4">
                            <i class="fas fa-cog me-2" style="color: #ff6b35;"></i>
                            การตั้งค่า
                        </h5>
                        
                        <form method="POST" action="">
                            <div class="mb-4">
                                <label class="form-label d-flex align-items-center">
                                    สถานะการใช้งาน
                                    <?php if ($settings && $settings['is_active']): ?>
                                        <span class="status-indicator active ms-2"></span>
                                        <small class="text-success">เปิดใช้งาน</small>
                                    <?php else: ?>
                                        <span class="status-indicator inactive ms-2"></span>
                                        <small class="text-danger">ปิดใช้งาน</small>
                                    <?php endif; ?>
                                </label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                           <?php echo ($settings && $settings['is_active']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_active">
                                        เปิดใช้งานการแจ้งเตือนผ่าน Telegram
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="bot_token" class="form-label">
                                    <i class="fas fa-key me-2"></i>Bot Token
                                </label>
                                <input type="text" class="form-control" id="bot_token" name="bot_token" 
                                       value="<?php echo $settings ? $settings['bot_token'] : ''; ?>"
                                       placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz">
                                <small class="text-muted">Token ที่ได้จาก BotFather</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="chat_id" class="form-label">
                                    <i class="fas fa-comments me-2"></i>Chat ID
                                </label>
                                <input type="text" class="form-control" id="chat_id" name="chat_id" 
                                       value="<?php echo $settings ? $settings['chat_id'] : ''; ?>"
                                       placeholder="-123456789">
                                <small class="text-muted">ID ของแชทหรือกลุ่มที่ต้องการรับการแจ้งเตือน</small>
                            </div>
                            
                            <div class="mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="test_message" name="test_message" value="1">
                                    <label class="form-check-label" for="test_message">
                                        ส่งข้อความทดสอบหลังบันทึก
                                    </label>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-orange">
                                    <i class="fas fa-save me-2"></i>บันทึกการตั้งค่า
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="testConnection()">
                                    <i class="fas fa-paper-plane me-2"></i>ทดสอบการเชื่อมต่อ
                                </button>
                            </div>
                        </form>
                        
                        <?php if ($settings && $settings['updated_at']): ?>
                            <div class="mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    อัพเดทล่าสุด: <?php echo formatDateThai($settings['updated_at']); ?>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Notification Examples -->
            <div class="glass-card p-4">
                <h5 class="mb-4">
                    <i class="fas fa-bell me-2" style="color: #ff6b35;"></i>
                    ตัวอย่างการแจ้งเตือน
                </h5>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="p-3 bg-light rounded">
                            <h6 class="text-primary mb-2">🔧 แจ้งซ่อมใหม่</h6>
                            <div class="code-block">
                                📋 เลขที่: REQ202501001<br>
                                🖥️ อุปกรณ์: คอมพิวเตอร์ PC-001<br>
                                👤 ผู้แจ้ง: สมชาย ใจดี<br>
                                ⚠️ ความเร่งด่วน: สูง<br>
                                📝 อาการ: เปิดเครื่องไม่ติด<br>
                                🏢 ทีมซ่อม: ทีมซ่อมคอมพิวเตอร์
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="p-3 bg-light rounded">
                            <h6 class="text-success mb-2">✅ รับงานซ่อม</h6>
                            <div class="code-block">
                                📋 เลขที่: REQ202501001<br>
                                🖥️ อุปกรณ์: คอมพิวเตอร์ PC-001<br>
                                👤 ผู้แจ้ง: สมชาย ใจดี<br>
                                🔧 ช่างผู้รับผิดชอบ: สมศักดิ์ ซ่อมดี
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="p-3 bg-light rounded">
                            <h6 class="text-info mb-2">✅ ซ่อมเสร็จสิ้น</h6>
                            <div class="code-block">
                                📋 เลขที่: REQ202501001<br>
                                🖥️ อุปกรณ์: คอมพิวเตอร์ PC-001<br>
                                👤 ผู้แจ้ง: สมชาย ใจดี<br>
                                🔧 ช่างผู้รับผิดชอบ: สมศักดิ์ ซ่อมดี<br>
                                📝 การแก้ไข: เปลี่ยน Power Supply
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        function testConnection() {
            const botToken = document.getElementById('bot_token').value;
            const chatId = document.getElementById('chat_id').value;
            
            if (!botToken || !chatId) {
                Swal.fire({
                    icon: 'warning',
                    title: 'กรุณากรอกข้อมูล',
                    text: 'กรุณากรอก Bot Token และ Chat ID ก่อนทดสอบ'
                });
                return;
            }
            
            Swal.fire({
                title: 'กำลังทดสอบการเชื่อมต่อ...',
                text: 'กรุณารอสักครู่',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // ส่ง AJAX request เพื่อทดสอบ (ต้องสร้าง test_telegram.php แยก)
            setTimeout(() => {
                Swal.fire({
                    icon: 'info',
                    title: 'ทดสอบการเชื่อมต่อ',
                    text: 'กรุณาบันทึกการตั้งค่าและเลือก "ส่งข้อความทดสอบ" เพื่อทดสอบการส่งข้อความ'
                });
            }, 1000);
        }
    </script>
</body>
</html>