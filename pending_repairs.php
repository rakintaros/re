<?php
require_once 'config/database.php';
checkLogin();
checkRole(['technician']);

$conn = connectDB();
$user_id = $_SESSION['user_id'];
$team_id = $_SESSION['maintenance_team_id'];

// ประมวลผลการรับงาน
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'accept') {
        $repair_id = (int)$_POST['repair_id'];
        
        // ตรวจสอบว่ายังไม่มีคนรับงาน
        $check_sql = "SELECT technician_id FROM repair_requests WHERE id = $repair_id AND technician_id IS NULL";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) > 0) {
            // รับงาน
            $sql = "UPDATE repair_requests SET 
                    technician_id = $user_id, 
                    status = 'assigned',
                    repair_start_date = NOW() 
                    WHERE id = $repair_id";
            
            if (mysqli_query($conn, $sql)) {
                // บันทึกประวัติ
                $sql_history = "INSERT INTO repair_history (repair_request_id, action, description, created_by) 
                                VALUES ($repair_id, 'รับงานซ่อม', 'ช่าง {$_SESSION['fullname']} รับงานซ่อม', $user_id)";
                mysqli_query($conn, $sql_history);
                
                // ส่งการแจ้งเตือน Telegram
                $sql_info = "SELECT r.request_code, e.name as equipment_name, u.fullname as requester_name 
                           FROM repair_requests r 
                           LEFT JOIN equipment e ON r.equipment_id = e.id 
                           LEFT JOIN users u ON r.user_id = u.id 
                           WHERE r.id = $repair_id";
                $result_info = mysqli_query($conn, $sql_info);
                $info = mysqli_fetch_assoc($result_info);
                
                $message = "✅ <b>รับงานซ่อม</b>\n\n";
                $message .= "📋 เลขที่: {$info['request_code']}\n";
                $message .= "🖥️ อุปกรณ์: {$info['equipment_name']}\n";
                $message .= "👤 ผู้แจ้ง: {$info['requester_name']}\n";
                $message .= "🔧 ช่างผู้รับผิดชอบ: {$_SESSION['fullname']}";
                
                sendTelegramNotification($message);
                
                $_SESSION['success'] = "รับงานซ่อมสำเร็จ";
            } else {
                $_SESSION['error'] = "เกิดข้อผิดพลาด: " . mysqli_error($conn);
            }
        } else {
            $_SESSION['error'] = "มีช่างท่านอื่นรับงานนี้แล้ว";
        }
        
        header("Location: pending_repairs.php");
        exit();
    }
}

// ดึงข้อมูลงานที่รอรับ
$sql = "SELECT r.*, e.name as equipment_name, e.code as equipment_code, e.location,
        u.fullname as requester_name, d.name as department_name
        FROM repair_requests r 
        LEFT JOIN equipment e ON r.equipment_id = e.id 
        LEFT JOIN users u ON r.user_id = u.id 
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE r.maintenance_team_id = $team_id AND r.status = 'pending' 
        ORDER BY 
            CASE r.urgency 
                WHEN 'urgent' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                WHEN 'low' THEN 4 
            END,
            r.created_at ASC";
$result = mysqli_query($conn, $sql);

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รอรับเรื่องซ่อม - ระบบจัดการครุภัณฑ์</title>
    
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
            transition: all 0.3s ease;
        }
        
        .glass-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px 0 rgba(31, 38, 135, 0.25);
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
        
        .badge {
            padding: 6px 12px;
            font-weight: 500;
            border-radius: 20px;
        }
        
        .repair-card {
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
        }
        
        .repair-card.urgent {
            border-left-color: #dc3545;
        }
        
        .repair-card.high {
            border-left-color: #fd7e14;
        }
        
        .repair-card.medium {
            border-left-color: #ffc107;
        }
        
        .repair-card.low {
            border-left-color: #28a745;
        }
        
        .modal-content {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .info-item {
            display: flex;
            align-items: start;
            margin-bottom: 10px;
        }
        
        .info-item i {
            width: 20px;
            color: #ff6b35;
            margin-right: 10px;
            margin-top: 3px;
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
                
                <a href="pending_repairs.php" class="sidebar-item active">
                    <i class="fas fa-exclamation-circle me-3"></i> รอรับเรื่อง
                </a>
                <a href="my_assignments.php" class="sidebar-item">
                    <i class="fas fa-tasks me-3"></i> งานของฉัน
                </a>
            </div>
        </div>
        
        <!-- Content -->
        <div class="flex-grow-1 content-area">
            <!-- Page Header -->
            <div class="page-header">
                <h4 class="mb-0">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    งานรอรับเรื่อง
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
            
            <!-- Main Content -->
            <div class="row">
                <?php if (mysqli_num_rows($result) == 0): ?>
                    <div class="col-12">
                        <div class="glass-card p-5 text-center">
                            <i class="fas fa-clipboard-check fa-4x text-muted mb-3"></i>
                            <h5 class="text-muted">ไม่มีงานรอรับเรื่อง</h5>
                            <p class="text-muted">ขณะนี้ไม่มีงานซ่อมใหม่สำหรับทีมของคุณ</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <div class="col-md-6 mb-4">
                            <div class="glass-card repair-card <?php echo $row['urgency']; ?> p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="mb-1">
                                            <span class="badge bg-secondary"><?php echo $row['request_code']; ?></span>
                                        </h5>
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo formatDateThai($row['created_at']); ?>
                                        </small>
                                    </div>
                                    <div>
                                        <?php echo getUrgencyBadge($row['urgency']); ?>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <i class="fas fa-desktop"></i>
                                    <div>
                                        <strong><?php echo $row['equipment_name']; ?></strong>
                                        <small class="text-muted">(<?php echo $row['equipment_code']; ?>)</small>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <i class="fas fa-user"></i>
                                    <div>
                                        <?php echo $row['requester_name']; ?>
                                        <small class="text-muted">(<?php echo $row['department_name']; ?>)</small>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <div><?php echo $row['location'] ?: 'ไม่ระบุ'; ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <div>
                                        <?php 
                                        $problem = $row['problem_description'];
                                        echo mb_strlen($problem) > 100 ? mb_substr($problem, 0, 100) . '...' : $problem;
                                        ?>
                                    </div>
                                </div>
                                
                                <div class="mt-3 d-flex gap-2">
                                    <button class="btn btn-info btn-sm text-white" 
                                            onclick="viewDetail(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                        <i class="fas fa-eye me-1"></i>ดูรายละเอียด
                                    </button>
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="action" value="accept">
                                        <input type="hidden" name="repair_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" class="btn btn-orange btn-sm" 
                                                onclick="return confirm('ยืนยันการรับงานซ่อมนี้?')">
                                            <i class="fas fa-check me-1"></i>รับงาน
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Detail Modal -->
    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-info-circle me-2" style="color: #ff6b35;"></i>
                        รายละเอียดการแจ้งซ่อม
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td width="40%"><strong>เลขที่แจ้งซ่อม:</strong></td>
                                    <td><span id="detail_code" class="badge bg-secondary"></span></td>
                                </tr>
                                <tr>
                                    <td><strong>วันที่แจ้ง:</strong></td>
                                    <td id="detail_date"></td>
                                </tr>
                                <tr>
                                    <td><strong>ความเร่งด่วน:</strong></td>
                                    <td id="detail_urgency"></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td width="40%"><strong>ผู้แจ้ง:</strong></td>
                                    <td id="detail_requester"></td>
                                </tr>
                                <tr>
                                    <td><strong>แผนก:</strong></td>
                                    <td id="detail_department"></td>
                                </tr>
                                <tr>
                                    <td><strong>ที่ตั้งอุปกรณ์:</strong></td>
                                    <td id="detail_location"></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="mb-3">
                        <h6 class="text-primary mb-2">
                            <i class="fas fa-desktop me-2"></i>ข้อมูลอุปกรณ์
                        </h6>
                        <table class="table table-sm">
                            <tr>
                                <td width="30%"><strong>ชื่ออุปกรณ์:</strong></td>
                                <td id="detail_equipment_name"></td>
                            </tr>
                            <tr>
                                <td><strong>รหัสอุปกรณ์:</strong></td>
                                <td id="detail_equipment_code"></td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="text-danger mb-2">
                            <i class="fas fa-exclamation-triangle me-2"></i>อาการ/ปัญหาที่พบ
                        </h6>
                        <div class="p-3 bg-light rounded" id="detail_problem"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                    <button type="button" class="btn btn-orange" id="acceptFromModal">
                        <i class="fas fa-check me-1"></i>รับงาน
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Accept Form for Modal -->
    <form method="POST" action="" id="acceptForm" style="display: none;">
        <input type="hidden" name="action" value="accept">
        <input type="hidden" name="repair_id" id="accept_repair_id">
    </form>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let currentRepairId = null;
        
        function viewDetail(data) {
            currentRepairId = data.id;
            
            // เติมข้อมูล
            document.getElementById('detail_code').textContent = data.request_code;
            document.getElementById('detail_date').textContent = formatDateThai(data.created_at);
            document.getElementById('detail_urgency').innerHTML = getUrgencyBadgeHTML(data.urgency);
            document.getElementById('detail_requester').textContent = data.requester_name;
            document.getElementById('detail_department').textContent = data.department_name;
            document.getElementById('detail_location').textContent = data.location || 'ไม่ระบุ';
            document.getElementById('detail_equipment_name').textContent = data.equipment_name;
            document.getElementById('detail_equipment_code').textContent = data.equipment_code;
            document.getElementById('detail_problem').textContent = data.problem_description;
            
            // แสดง Modal
            new bootstrap.Modal(document.getElementById('detailModal')).show();
        }
        
        document.getElementById('acceptFromModal').addEventListener('click', function() {
            if (currentRepairId && confirm('ยืนยันการรับงานซ่อมนี้?')) {
                document.getElementById('accept_repair_id').value = currentRepairId;
                document.getElementById('acceptForm').submit();
            }
        });
        
        function formatDateThai(dateString) {
            const date = new Date(dateString);
            const options = { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            return date.toLocaleDateString('th-TH', options);
        }
        
        function getUrgencyBadgeHTML(urgency) {
            const badges = {
                'low': '<span class="badge bg-success">ต่ำ</span>',
                'medium': '<span class="badge bg-warning">ปานกลาง</span>',
                'high': '<span class="badge bg-danger">สูง</span>',
                'urgent': '<span class="badge bg-danger">เร่งด่วน</span>'
            };
            return badges[urgency] || urgency;
        }
    </script>
</body>
</html>