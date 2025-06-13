<?php
require_once 'config/database.php';
checkLogin();

$conn = connectDB();

// ดึงข้อมูลสถิติตามบทบาท
if ($_SESSION['role'] == 'admin') {
    // สถิติสำหรับ Admin
    $sql_equipment = "SELECT COUNT(*) as total FROM equipment";
    $result_equipment = mysqli_query($conn, $sql_equipment);
    $total_equipment = mysqli_fetch_assoc($result_equipment)['total'];
    
    $sql_repairs = "SELECT COUNT(*) as total FROM repair_requests WHERE status != 'completed' AND status != 'cancelled'";
    $result_repairs = mysqli_query($conn, $sql_repairs);
    $active_repairs = mysqli_fetch_assoc($result_repairs)['total'];
    
    $sql_users = "SELECT COUNT(*) as total FROM users WHERE is_active = TRUE";
    $result_users = mysqli_query($conn, $sql_users);
    $total_users = mysqli_fetch_assoc($result_users)['total'];
    
    $sql_teams = "SELECT COUNT(*) as total FROM maintenance_teams";
    $result_teams = mysqli_query($conn, $sql_teams);
    $total_teams = mysqli_fetch_assoc($result_teams)['total'];
    
} elseif ($_SESSION['role'] == 'user') {
    // สถิติสำหรับ User
    $user_id = $_SESSION['user_id'];
    
    $sql_my_repairs = "SELECT COUNT(*) as total FROM repair_requests WHERE user_id = $user_id";
    $result_my_repairs = mysqli_query($conn, $sql_my_repairs);
    $my_total_repairs = mysqli_fetch_assoc($result_my_repairs)['total'];
    
    $sql_pending = "SELECT COUNT(*) as total FROM repair_requests WHERE user_id = $user_id AND status IN ('pending', 'assigned', 'in_progress')";
    $result_pending = mysqli_query($conn, $sql_pending);
    $my_pending_repairs = mysqli_fetch_assoc($result_pending)['total'];
    
} else {
    // สถิติสำหรับช่างซ่อม
    $user_id = $_SESSION['user_id'];
    $team_id = $_SESSION['maintenance_team_id'];
    
    $sql_assigned = "SELECT COUNT(*) as total FROM repair_requests WHERE technician_id = $user_id AND status != 'completed'";
    $result_assigned = mysqli_query($conn, $sql_assigned);
    $my_assigned_repairs = mysqli_fetch_assoc($result_assigned)['total'];
    
    $sql_team_pending = "SELECT COUNT(*) as total FROM repair_requests WHERE maintenance_team_id = $team_id AND status = 'pending'";
    $result_team_pending = mysqli_query($conn, $sql_team_pending);
    $team_pending_repairs = mysqli_fetch_assoc($result_team_pending)['total'];
}

// ดึงรายการแจ้งซ่อมล่าสุด
$sql_recent = "SELECT r.*, e.name as equipment_name, u.fullname as requester_name 
               FROM repair_requests r 
               LEFT JOIN equipment e ON r.equipment_id = e.id 
               LEFT JOIN users u ON r.user_id = u.id ";

if ($_SESSION['role'] == 'user') {
    $sql_recent .= "WHERE r.user_id = " . $_SESSION['user_id'];
} elseif ($_SESSION['role'] == 'technician') {
    $sql_recent .= "WHERE r.maintenance_team_id = " . $_SESSION['maintenance_team_id'];
}

$sql_recent .= " ORDER BY r.created_at DESC LIMIT 5";
$result_recent = mysqli_query($conn, $sql_recent);

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หน้าหลัก - ระบบจัดการครุภัณฑ์</title>
    
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
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
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
        
        .welcome-banner {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .welcome-banner::before {
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
        
        .table-glass {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 15px;
            overflow: hidden;
        }
        
        .table-glass th {
            background: rgba(255, 107, 53, 0.1);
            font-weight: 600;
            color: #ff6b35;
        }
        
        .badge {
            padding: 6px 12px;
            font-weight: 500;
            border-radius: 20px;
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
                    <li class="nav-item ms-2">
                        <a class="btn btn-danger btn-sm" href="logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i>ออกจากระบบ
                        </a>
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
                
                <a href="index.php" class="sidebar-item active">
                    <i class="fas fa-home me-3"></i> หน้าหลัก
                </a>
                
                <?php if ($_SESSION['role'] == 'admin'): ?>
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
                    <a href="telegram_settings.php" class="sidebar-item">
                        <i class="fab fa-telegram me-3"></i> ตั้งค่า Telegram
                    </a>
                <?php endif; ?>
                
                <?php if ($_SESSION['role'] == 'user'): ?>
                    <a href="repair_request.php" class="sidebar-item">
                        <i class="fas fa-wrench me-3"></i> แจ้งซ่อม
                    </a>
                    <a href="my_repairs.php" class="sidebar-item">
                        <i class="fas fa-history me-3"></i> รายการแจ้งซ่อม
                    </a>
                <?php endif; ?>
                
                <?php if ($_SESSION['role'] == 'technician'): ?>
                    <a href="pending_repairs.php" class="sidebar-item">
                        <i class="fas fa-exclamation-circle me-3"></i> รอรับเรื่อง
                    </a>
                    <a href="my_assignments.php" class="sidebar-item">
                        <i class="fas fa-tasks me-3"></i> งานของฉัน
                    </a>
                <?php endif; ?>
                
                <hr class="my-3">
                
                <a href="logout.php" class="sidebar-item text-danger">
                    <i class="fas fa-sign-out-alt me-3"></i> ออกจากระบบ
                </a>
            </div>
        </div>
        
        <!-- Content -->
        <div class="flex-grow-1 content-area">
            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <h3 class="mb-2">สวัสดี, <?php echo $_SESSION['fullname']; ?> 👋</h3>
                <p class="mb-0">ยินดีต้อนรับเข้าสู่ระบบจัดการครุภัณฑ์และแจ้งซ่อม</p>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row g-4 mb-4">
                <?php if ($_SESSION['role'] == 'admin'): ?>
                    <div class="col-md-3">
                        <div class="glass-card p-4">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon gradient-bg">
                                    <i class="fas fa-desktop"></i>
                                </div>
                                <div class="ms-3">
                                    <h5 class="mb-0"><?php echo number_format($total_equipment); ?></h5>
                                    <p class="text-muted mb-0">ครุภัณฑ์ทั้งหมด</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="glass-card p-4">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon" style="background: #28a745;">
                                    <i class="fas fa-wrench"></i>
                                </div>
                                <div class="ms-3">
                                    <h5 class="mb-0"><?php echo number_format($active_repairs); ?></h5>
                                    <p class="text-muted mb-0">กำลังซ่อม</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="glass-card p-4">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon" style="background: #17a2b8;">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="ms-3">
                                    <h5 class="mb-0"><?php echo number_format($total_users); ?></h5>
                                    <p class="text-muted mb-0">ผู้ใช้งาน</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="glass-card p-4">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon" style="background: #6f42c1;">
                                    <i class="fas fa-users-cog"></i>
                                </div>
                                <div class="ms-3">
                                    <h5 class="mb-0"><?php echo number_format($total_teams); ?></h5>
                                    <p class="text-muted mb-0">ทีมซ่อม</p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php elseif ($_SESSION['role'] == 'user'): ?>
                    <div class="col-md-6">
                        <div class="glass-card p-4">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon gradient-bg">
                                    <i class="fas fa-clipboard-list"></i>
                                </div>
                                <div class="ms-3">
                                    <h5 class="mb-0"><?php echo number_format($my_total_repairs); ?></h5>
                                    <p class="text-muted mb-0">แจ้งซ่อมทั้งหมด</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="glass-card p-4">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon" style="background: #ffc107;">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="ms-3">
                                    <h5 class="mb-0"><?php echo number_format($my_pending_repairs); ?></h5>
                                    <p class="text-muted mb-0">รอดำเนินการ</p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="col-md-6">
                        <div class="glass-card p-4">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon gradient-bg">
                                    <i class="fas fa-tasks"></i>
                                </div>
                                <div class="ms-3">
                                    <h5 class="mb-0"><?php echo number_format($my_assigned_repairs); ?></h5>
                                    <p class="text-muted mb-0">งานที่รับผิดชอบ</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="glass-card p-4">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon" style="background: #dc3545;">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div class="ms-3">
                                    <h5 class="mb-0"><?php echo number_format($team_pending_repairs); ?></h5>
                                    <p class="text-muted mb-0">รอรับเรื่อง</p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Recent Repairs -->
            <div class="glass-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2" style="color: #ff6b35;"></i>
                        รายการแจ้งซ่อมล่าสุด
                    </h5>
                    <?php if ($_SESSION['role'] == 'user'): ?>
                        <a href="repair_request.php" class="btn btn-orange btn-sm">
                            <i class="fas fa-plus me-2"></i>แจ้งซ่อมใหม่
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover table-glass">
                        <thead>
                            <tr>
                                <th>รหัสแจ้งซ่อม</th>
                                <th>อุปกรณ์</th>
                                <th>ผู้แจ้ง</th>
                                <th>ความเร่งด่วน</th>
                                <th>สถานะ</th>
                                <th>วันที่แจ้ง</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($result_recent)): ?>
                                <tr>
                                    <td><?php echo $row['request_code']; ?></td>
                                    <td><?php echo $row['equipment_name']; ?></td>
                                    <td><?php echo $row['requester_name']; ?></td>
                                    <td><?php echo getUrgencyBadge($row['urgency']); ?></td>
                                    <td><?php echo getStatusBadge($row['status']); ?></td>
                                    <td><?php echo formatDateThai($row['created_at']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Bootstrap tooltips and popovers if any
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
        
        // Auto close alerts after 5 seconds
        window.setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>