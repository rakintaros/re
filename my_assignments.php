<?php
require_once 'config/database.php';
checkLogin();
checkRole(['technician']);

$conn = connectDB();
$user_id = $_SESSION['user_id'];

// ประมวลผลการอัพเดทสถานะ
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $repair_id = (int)$_POST['repair_id'];
    
    switch ($_POST['action']) {
        case 'start':
            $sql = "UPDATE repair_requests SET status = 'in_progress' WHERE id = $repair_id AND technician_id = $user_id";
            $action_text = "เริ่มซ่อม";
            $message_title = "🔧 <b>เริ่มซ่อม</b>";
            break;
            
        case 'complete':
            $solution = escape($conn, $_POST['solution']);
            $notes = escape($conn, $_POST['notes']);
            
            $sql = "UPDATE repair_requests SET 
                    status = 'completed',
                    repair_end_date = NOW(),
                    solution = '$solution',
                    notes = '$notes'
                    WHERE id = $repair_id AND technician_id = $user_id";
            $action_text = "ซ่อมเสร็จสิ้น";
            $message_title = "✅ <b>ซ่อมเสร็จสิ้น</b>";
            
            // อัพเดทสถานะครุภัณฑ์กลับเป็นปกติ
            $equip_sql = "UPDATE equipment e 
                         INNER JOIN repair_requests r ON e.id = r.equipment_id 
                         SET e.status = 'normal' 
                         WHERE r.id = $repair_id";
            mysqli_query($conn, $equip_sql);
            break;
    }
    
    if (mysqli_query($conn, $sql)) {
        // บันทึกประวัติ
        $sql_history = "INSERT INTO repair_history (repair_request_id, action, description, created_by) 
                        VALUES ($repair_id, '$action_text', '$action_text โดย {$_SESSION['fullname']}', $user_id)";
        mysqli_query($conn, $sql_history);
        
        // ส่งการแจ้งเตือน Telegram
        $sql_info = "SELECT r.request_code, e.name as equipment_name, u.fullname as requester_name 
                   FROM repair_requests r 
                   LEFT JOIN equipment e ON r.equipment_id = e.id 
                   LEFT JOIN users u ON r.user_id = u.id 
                   WHERE r.id = $repair_id";
        $result_info = mysqli_query($conn, $sql_info);
        $info = mysqli_fetch_assoc($result_info);
        
        $message = "$message_title\n\n";
        $message .= "📋 เลขที่: {$info['request_code']}\n";
        $message .= "🖥️ อุปกรณ์: {$info['equipment_name']}\n";
        $message .= "👤 ผู้แจ้ง: {$info['requester_name']}\n";
        $message .= "🔧 ช่างผู้รับผิดชอบ: {$_SESSION['fullname']}";
        
        if ($_POST['action'] == 'complete' && $solution) {
            $message .= "\n\n📝 การแก้ไข: $solution";
        }
        
        sendTelegramNotification($message);
        
        $_SESSION['success'] = "$action_text สำเร็จ";
    } else {
        $_SESSION['error'] = "เกิดข้อผิดพลาด: " . mysqli_error($conn);
    }
    
    header("Location: my_assignments.php");
    exit();
}

// ดึงข้อมูลงานที่รับผิดชอบ
$sql = "SELECT r.*, e.name as equipment_name, e.code as equipment_code, e.location,
        u.fullname as requester_name, d.name as department_name, mt.name as team_name
        FROM repair_requests r 
        LEFT JOIN equipment e ON r.equipment_id = e.id 
        LEFT JOIN users u ON r.user_id = u.id 
        LEFT JOIN departments d ON u.department_id = d.id
        LEFT JOIN maintenance_teams mt ON r.maintenance_team_id = mt.id
        WHERE r.technician_id = $user_id AND r.status IN ('assigned', 'in_progress')
        ORDER BY 
            CASE r.status 
                WHEN 'in_progress' THEN 1 
                WHEN 'assigned' THEN 2 
            END,
            CASE r.urgency 
                WHEN 'urgent' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                WHEN 'low' THEN 4 
            END,
            r.created_at ASC";
$result = mysqli_query($conn, $sql);

// ดึงข้อมูลงานที่เสร็จแล้ว
$sql_completed = "SELECT r.*, e.name as equipment_name, e.code as equipment_code
                  FROM repair_requests r 
                  LEFT JOIN equipment e ON r.equipment_id = e.id 
                  WHERE r.technician_id = $user_id AND r.status = 'completed'
                  ORDER BY r.repair_end_date DESC
                  LIMIT 10";
$result_completed = mysqli_query($conn, $sql_completed);

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>งานของฉัน - ระบบจัดการครุภัณฑ์</title>
    
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
        
        .work-card {
            border-left: 4px solid transparent;
        }
        
        .work-card.in_progress {
            border-left-color: #ffc107;
            background: rgba(255, 193, 7, 0.05);
        }
        
        .work-card.assigned {
            border-left-color: #17a2b8;
            background: rgba(23, 162, 184, 0.05);
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
        
        .nav-tabs {
            border-bottom: 2px solid rgba(255, 107, 53, 0.2);
        }
        
        .nav-tabs .nav-link {
            color: #666;
            border: none;
            padding: 10px 20px;
            transition: all 0.3s ease;
        }
        
        .nav-tabs .nav-link:hover {
            color: #ff6b35;
            background: rgba(255, 107, 53, 0.05);
        }
        
        .nav-tabs .nav-link.active {
            color: #ff6b35;
            background: white;
            border-bottom: 3px solid #ff6b35;
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
                
                <a href="pending_repairs.php" class="sidebar-item">
                    <i class="fas fa-exclamation-circle me-3"></i> รอรับเรื่อง
                </a>
                <a href="my_assignments.php" class="sidebar-item active">
                    <i class="fas fa-tasks me-3"></i> งานของฉัน
                </a>
            </div>
        </div>
        
        <!-- Content -->
        <div class="flex-grow-1 content-area">
            <!-- Page Header -->
            <div class="page-header">
                <h4 class="mb-0">
                    <i class="fas fa-tasks me-2"></i>
                    งานที่รับผิดชอบ
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
            
            <!-- Main Content with Tabs -->
            <div class="glass-card p-4">
                <ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="current-tab" data-bs-toggle="tab" data-bs-target="#current" type="button" role="tab">
                            <i class="fas fa-clipboard-list me-2"></i>งานปัจจุบัน
                            <span class="badge bg-danger ms-2"><?php echo mysqli_num_rows($result); ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed" type="button" role="tab">
                            <i class="fas fa-check-circle me-2"></i>งานที่เสร็จแล้ว
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="myTabContent">
                    <!-- Current Work Tab -->
                    <div class="tab-pane fade show active" id="current" role="tabpanel">
                        <div class="row">
                            <?php if (mysqli_num_rows($result) == 0): ?>
                                <div class="col-12">
                                    <div class="text-center py-5">
                                        <i class="fas fa-clipboard-check fa-4x text-muted mb-3"></i>
                                        <h5 class="text-muted">ไม่มีงานที่รับผิดชอบ</h5>
                                        <p class="text-muted">คุณไม่มีงานซ่อมที่รับผิดชอบอยู่ในขณะนี้</p>
                                        <a href="pending_repairs.php" class="btn btn-orange">
                                            <i class="fas fa-search me-2"></i>ดูงานรอรับเรื่อง
                                        </a>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <div class="col-md-6 mb-4">
                                        <div class="glass-card work-card <?php echo $row['status']; ?> p-4">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <h5 class="mb-1">
                                                        <span class="badge bg-secondary"><?php echo $row['request_code']; ?></span>
                                                    </h5>
                                                    <small class="text-muted">
                                                        <i class="fas fa-clock me-1"></i>
                                                        รับงานเมื่อ: <?php echo formatDateThai($row['repair_start_date']); ?>
                                                    </small>
                                                </div>
                                                <div>
                                                    <?php echo getStatusBadge($row['status']); ?>
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
                                                <div><?php echo getUrgencyBadge($row['urgency']); ?></div>
                                            </div>
                                            
                                            <hr>
                                            
                                            <div class="mb-3">
                                                <strong>อาการ:</strong>
                                                <p class="mb-0 text-muted small">
                                                    <?php 
                                                    $problem = $row['problem_description'];
                                                    echo mb_strlen($problem) > 100 ? mb_substr($problem, 0, 100) . '...' : $problem;
                                                    ?>
                                                </p>
                                            </div>
                                            
                                            <div class="d-flex gap-2">
                                                <?php if ($row['status'] == 'assigned'): ?>
                                                    <form method="POST" action="" style="display: inline;">
                                                        <input type="hidden" name="action" value="start">
                                                        <input type="hidden" name="repair_id" value="<?php echo $row['id']; ?>">
                                                        <button type="submit" class="btn btn-warning btn-sm text-white">
                                                            <i class="fas fa-play me-1"></i>เริ่มซ่อม
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <button class="btn btn-success btn-sm" 
                                                            onclick="showCompleteModal(<?php echo $row['id']; ?>)">
                                                        <i class="fas fa-check me-1"></i>เสร็จสิ้น
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <button class="btn btn-info btn-sm text-white" 
                                                        onclick="viewDetail(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                                    <i class="fas fa-eye me-1"></i>รายละเอียด
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Completed Work Tab -->
                    <div class="tab-pane fade" id="completed" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>เลขที่</th>
                                        <th>อุปกรณ์</th>
                                        <th>วันที่เริ่ม</th>
                                        <th>วันที่เสร็จ</th>
                                        <th>ระยะเวลา</th>
                                        <th>การแก้ไข</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($completed = mysqli_fetch_assoc($result_completed)): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo $completed['request_code']; ?></span>
                                            </td>
                                            <td>
                                                <?php echo $completed['equipment_name']; ?>
                                                <small class="text-muted">(<?php echo $completed['equipment_code']; ?>)</small>
                                            </td>
                                            <td><?php echo formatDateThai($completed['repair_start_date']); ?></td>
                                            <td><?php echo formatDateThai($completed['repair_end_date']); ?></td>
                                            <td>
                                                <?php
                                                $start = new DateTime($completed['repair_start_date']);
                                                $end = new DateTime($completed['repair_end_date']);
                                                $diff = $start->diff($end);
                                                
                                                if ($diff->days > 0) {
                                                    echo $diff->days . ' วัน';
                                                } elseif ($diff->h > 0) {
                                                    echo $diff->h . ' ชั่วโมง';
                                                } else {
                                                    echo $diff->i . ' นาที';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $solution = $completed['solution'];
                                                echo mb_strlen($solution) > 50 ? mb_substr($solution, 0, 50) . '...' : $solution;
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Complete Modal -->
    <div class="modal fade" id="completeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-check-circle me-2" style="color: #ff6b35;"></i>
                        บันทึกการซ่อมเสร็จสิ้น
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="complete">
                        <input type="hidden" name="repair_id" id="complete_repair_id">
                        
                        <div class="mb-3">
                            <label for="solution" class="form-label">การแก้ไข/วิธีการซ่อม <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="solution" name="solution" rows="4" 
                                      placeholder="อธิบายวิธีการแก้ไขหรือซ่อมแซม..." required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">หมายเหตุเพิ่มเติม</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      placeholder="หมายเหตุอื่นๆ (ถ้ามี)"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check me-2"></i>บันทึกเสร็จสิ้น
                        </button>
                    </div>
                </form>
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
                        รายละเอียดงานซ่อม
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
                                    <td><strong>สถานะ:</strong></td>
                                    <td id="detail_status"></td>
                                </tr>
                                <tr>
                                    <td><strong>ความเร่งด่วน:</strong></td>
                                    <td id="detail_urgency"></td>
                                </tr>
                                <tr>
                                    <td><strong>ทีมซ่อม:</strong></td>
                                    <td id="detail_team"></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td width="40%"><strong>วันที่แจ้ง:</strong></td>
                                    <td id="detail_created"></td>
                                </tr>
                                <tr>
                                    <td><strong>วันที่รับงาน:</strong></td>
                                    <td id="detail_start"></td>
                                </tr>
                                <tr>
                                    <td><strong>ผู้แจ้ง:</strong></td>
                                    <td id="detail_requester"></td>
                                </tr>
                                <tr>
                                    <td><strong>แผนก:</strong></td>
                                    <td id="detail_department"></td>
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
                            <tr>
                                <td><strong>ที่ตั้ง:</strong></td>
                                <td id="detail_location"></td>
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
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function showCompleteModal(repairId) {
            document.getElementById('complete_repair_id').value = repairId;
            new bootstrap.Modal(document.getElementById('completeModal')).show();
        }
        
        function viewDetail(data) {
            // เติมข้อมูล
            document.getElementById('detail_code').textContent = data.request_code;
            document.getElementById('detail_status').innerHTML = getStatusBadgeHTML(data.status);
            document.getElementById('detail_urgency').innerHTML = getUrgencyBadgeHTML(data.urgency);
            document.getElementById('detail_team').textContent = data.team_name;
            document.getElementById('detail_created').textContent = formatDateThai(data.created_at);
            document.getElementById('detail_start').textContent = formatDateThai(data.repair_start_date);
            document.getElementById('detail_requester').textContent = data.requester_name;
            document.getElementById('detail_department').textContent = data.department_name;
            document.getElementById('detail_equipment_name').textContent = data.equipment_name;
            document.getElementById('detail_equipment_code').textContent = data.equipment_code;
            document.getElementById('detail_location').textContent = data.location || 'ไม่ระบุ';
            document.getElementById('detail_problem').textContent = data.problem_description;
            
            // แสดง Modal
            new bootstrap.Modal(document.getElementById('detailModal')).show();
        }
        
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
        
        function getStatusBadgeHTML(status) {
            const badges = {
                'assigned': '<span class="badge bg-primary">มอบหมายแล้ว</span>',
                'in_progress': '<span class="badge bg-warning">กำลังซ่อม</span>',
                'completed': '<span class="badge bg-success">เสร็จสิ้น</span>'
            };
            return badges[status] || status;
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