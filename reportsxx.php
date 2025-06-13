<?php
require_once 'config/database.php';
checkLogin();
checkRole(['admin']);

$conn = connectDB();

// รับค่าตัวกรอง
$filter_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';

// สถิติภาพรวม
$sql_stats = "SELECT 
    COUNT(DISTINCT e.id) as total_equipment,
    COUNT(DISTINCT CASE WHEN e.status = 'normal' THEN e.id END) as normal_equipment,
    COUNT(DISTINCT CASE WHEN e.status = 'repairing' THEN e.id END) as repairing_equipment,
    COUNT(DISTINCT r.id) as total_repairs,
    COUNT(DISTINCT CASE WHEN r.status = 'completed' AND DATE_FORMAT(r.created_at, '%Y-%m') = '$filter_month' THEN r.id END) as completed_repairs,
    COUNT(DISTINCT CASE WHEN r.status IN ('pending', 'assigned', 'in_progress') THEN r.id END) as active_repairs
    FROM equipment e
    LEFT JOIN repair_requests r ON e.id = r.equipment_id";
$result_stats = mysqli_query($conn, $sql_stats);
$stats = mysqli_fetch_assoc($result_stats);

// กราฟแจ้งซ่อมรายเดือน (6 เดือนล่าสุด)
$sql_chart = "SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    DATE_FORMAT(created_at, '%M %Y') as month_label,
    COUNT(*) as count
    FROM repair_requests
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC";
$result_chart = mysqli_query($conn, $sql_chart);
$chart_data = [];
while ($row = mysqli_fetch_assoc($result_chart)) {
    $chart_data[] = $row;
}

// รายการซ่อมตามสถานะ
$sql_by_status = "SELECT status, COUNT(*) as count 
                  FROM repair_requests 
                  WHERE DATE_FORMAT(created_at, '%Y-%m') = '$filter_month'
                  GROUP BY status";
$result_by_status = mysqli_query($conn, $sql_by_status);
$status_data = [];
while ($row = mysqli_fetch_assoc($result_by_status)) {
    $status_data[$row['status']] = $row['count'];
}

// ประสิทธิภาพช่างซ่อม
$sql_technicians = "SELECT 
    u.fullname,
    COUNT(DISTINCT r.id) as total_jobs,
    COUNT(DISTINCT CASE WHEN r.status = 'completed' THEN r.id END) as completed_jobs,
    AVG(CASE WHEN r.status = 'completed' THEN TIMESTAMPDIFF(HOUR, r.repair_start_date, r.repair_end_date) END) as avg_repair_hours
    FROM users u
    LEFT JOIN repair_requests r ON u.id = r.technician_id
    WHERE u.role = 'technician'
    AND DATE_FORMAT(r.created_at, '%Y-%m') = '$filter_month'
    GROUP BY u.id
    ORDER BY completed_jobs DESC";
$result_technicians = mysqli_query($conn, $sql_technicians);

// ครุภัณฑ์ที่มีปัญหาบ่อย
$sql_frequent = "SELECT 
    e.code, e.name,
    COUNT(r.id) as repair_count,
    ec.name as category_name
    FROM equipment e
    LEFT JOIN repair_requests r ON e.id = r.equipment_id
    LEFT JOIN equipment_categories ec ON e.category_id = ec.id
    WHERE r.created_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
    GROUP BY e.id
    HAVING repair_count >= 2
    ORDER BY repair_count DESC
    LIMIT 10";
$result_frequent = mysqli_query($conn, $sql_frequent);

// Export to Excel
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="repair_report_' . $filter_month . '.xls"');
    header('Cache-Control: max-age=0');
    
    // สร้าง HTML table สำหรับ Excel
    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head><meta charset="UTF-8"></head>';
    echo '<body>';
    echo '<table border="1">';
    echo '<tr><th colspan="7" style="text-align:center; font-size:16px;">รายงานการซ่อมบำรุง เดือน ' . $filter_month . '</th></tr>';
    echo '<tr><th>เลขที่แจ้งซ่อม</th><th>วันที่แจ้ง</th><th>อุปกรณ์</th><th>ผู้แจ้ง</th><th>ช่างผู้รับผิดชอบ</th><th>สถานะ</th><th>ระยะเวลา (ชั่วโมง)</th></tr>';
    
    $sql_export = "SELECT r.*, e.name as equipment_name, e.code as equipment_code,
                   u1.fullname as requester_name, u2.fullname as technician_name
                   FROM repair_requests r
                   LEFT JOIN equipment e ON r.equipment_id = e.id
                   LEFT JOIN users u1 ON r.user_id = u1.id
                   LEFT JOIN users u2 ON r.technician_id = u2.id
                   WHERE DATE_FORMAT(r.created_at, '%Y-%m') = '$filter_month'";
    
    if ($filter_status != 'all') {
        $sql_export .= " AND r.status = '$filter_status'";
    }
    
    $result_export = mysqli_query($conn, $sql_export);
    
    while ($row = mysqli_fetch_assoc($result_export)) {
        $duration = '-';
        if ($row['repair_start_date'] && $row['repair_end_date']) {
            $start = new DateTime($row['repair_start_date']);
            $end = new DateTime($row['repair_end_date']);
            $diff = $start->diff($end);
            $duration = ($diff->days * 24) + $diff->h + round($diff->i / 60, 1);
        }
        
        echo '<tr>';
        echo '<td>' . $row['request_code'] . '</td>';
        echo '<td>' . $row['created_at'] . '</td>';
        echo '<td>' . $row['equipment_name'] . ' (' . $row['equipment_code'] . ')</td>';
        echo '<td>' . $row['requester_name'] . '</td>';
        echo '<td>' . ($row['technician_name'] ?: '-') . '</td>';
        echo '<td>' . $row['status'] . '</td>';
        echo '<td>' . $duration . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body></html>';
    exit();
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงาน - ระบบจัดการครุภัณฑ์</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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
        
        .stat-card {
            text-align: center;
            padding: 25px;
            border-radius: 15px;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card i {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .stat-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
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
                <a href="reports.php" class="sidebar-item active">
                    <i class="fas fa-chart-bar me-3"></i> รายงาน
                </a>
                <a href="telegram_settings.php" class="sidebar-item">
                    <i class="fab fa-telegram me-3"></i> ตั้งค่า Telegram
                </a>
            </div>
        </div>
        
        <!-- Content -->
        <div class="flex-grow-1 content-area">
            <!-- Page Header -->
            <div class="page-header">
                <h4 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    รายงานและสถิติ
                </h4>
            </div>
            
            <!-- Filter Section -->
            <div class="glass-card p-4 mb-4">
                <form method="GET" action="" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="month" class="form-label">เลือกเดือน</label>
                        <input type="month" class="form-control" id="month" name="month" 
                               value="<?php echo $filter_month; ?>" max="<?php echo date('Y-m'); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="status" class="form-label">สถานะ</label>
                        <select class="form-select" id="status" name="status">
                            <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>ทั้งหมด</option>
                            <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>รอดำเนินการ</option>
                            <option value="assigned" <?php echo $filter_status == 'assigned' ? 'selected' : ''; ?>>มอบหมายแล้ว</option>
                            <option value="in_progress" <?php echo $filter_status == 'in_progress' ? 'selected' : ''; ?>>กำลังซ่อม</option>
                            <option value="completed" <?php echo $filter_status == 'completed' ? 'selected' : ''; ?>>เสร็จสิ้น</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-orange">
                            <i class="fas fa-filter me-2"></i>กรองข้อมูล
                        </button>
                        <a href="?export=excel&month=<?php echo $filter_month; ?>&status=<?php echo $filter_status; ?>" 
                           class="btn btn-success">
                            <i class="fas fa-file-excel me-2"></i>Export Excel
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="glass-card stat-card">
                        <i class="fas fa-desktop gradient-bg text-white p-3 rounded-circle"></i>
                        <h3><?php echo number_format($stats['total_equipment']); ?></h3>
                        <p class="text-muted mb-0">ครุภัณฑ์ทั้งหมด</p>
                        <small class="text-success">
                            <i class="fas fa-check-circle"></i> ปกติ <?php echo number_format($stats['normal_equipment']); ?> รายการ
                        </small>
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <div class="glass-card stat-card">
                        <i class="fas fa-wrench gradient-bg text-white p-3 rounded-circle"></i>
                        <h3><?php echo number_format($stats['total_repairs']); ?></h3>
                        <p class="text-muted mb-0">การแจ้งซ่อมทั้งหมด</p>
                        <small class="text-warning">
                            <i class="fas fa-clock"></i> รอดำเนินการ <?php echo number_format($stats['active_repairs']); ?> รายการ
                        </small>
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <div class="glass-card stat-card">
                        <i class="fas fa-check-circle gradient-bg text-white p-3 rounded-circle"></i>
                        <h3><?php echo number_format($stats['completed_repairs']); ?></h3>
                        <p class="text-muted mb-0">ซ่อมเสร็จในเดือนนี้</p>
                        <small class="text-info">
                            <?php 
                            $completion_rate = $stats['total_repairs'] > 0 ? 
                                round(($stats['completed_repairs'] / $stats['total_repairs']) * 100, 1) : 0;
                            ?>
                            <i class="fas fa-percentage"></i> อัตราสำเร็จ <?php echo $completion_rate; ?>%
                        </small>
                    </div>
                </div>
            </div>
            
            <!-- Charts Row -->
            <div class="row mb-4">
                <!-- แจ้งซ่อมรายเดือน -->
                <div class="col-md-8 mb-4">
                    <div class="glass-card p-4">
                        <h5 class="mb-4">
                            <i class="fas fa-chart-line me-2" style="color: #ff6b35;"></i>
                            แนวโน้มการแจ้งซ่อม (6 เดือนล่าสุด)
                        </h5>
                        <canvas id="monthlyChart" height="100"></canvas>
                    </div>
                </div>
                
                <!-- สถานะการซ่อม -->
                <div class="col-md-4 mb-4">
                    <div class="glass-card p-4">
                        <h5 class="mb-4">
                            <i class="fas fa-chart-pie me-2" style="color: #ff6b35;"></i>
                            สถานะการซ่อมในเดือนนี้
                        </h5>
                        <canvas id="statusChart" height="200"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Tables Row -->
            <div class="row">
                <!-- ประสิทธิภาพช่างซ่อม -->
                <div class="col-md-6 mb-4">
                    <div class="glass-card p-4">
                        <h5 class="mb-4">
                            <i class="fas fa-user-cog me-2" style="color: #ff6b35;"></i>
                            ประสิทธิภาพช่างซ่อม
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-hover table-glass">
                                <thead>
                                    <tr>
                                        <th>ช่างซ่อม</th>
                                        <th class="text-center">รับงาน</th>
                                        <th class="text-center">เสร็จ</th>
                                        <th class="text-center">เวลาเฉลี่ย</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($tech = mysqli_fetch_assoc($result_technicians)): ?>
                                        <tr>
                                            <td><?php echo $tech['fullname']; ?></td>
                                            <td class="text-center"><?php echo $tech['total_jobs']; ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-success"><?php echo $tech['completed_jobs']; ?></span>
                                            </td>
                                            <td class="text-center">
                                                <?php echo $tech['avg_repair_hours'] ? round($tech['avg_repair_hours'], 1) . ' ชม.' : '-'; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- ครุภัณฑ์ที่มีปัญหาบ่อย -->
                <div class="col-md-6 mb-4">
                    <div class="glass-card p-4">
                        <h5 class="mb-4">
                            <i class="fas fa-exclamation-triangle me-2" style="color: #ff6b35;"></i>
                            ครุภัณฑ์ที่มีปัญหาบ่อย (3 เดือนล่าสุด)
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-hover table-glass">
                                <thead>
                                    <tr>
                                        <th>รหัส</th>
                                        <th>ชื่อครุภัณฑ์</th>
                                        <th>ประเภท</th>
                                        <th class="text-center">จำนวนครั้ง</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($freq = mysqli_fetch_assoc($result_frequent)): ?>
                                        <tr>
                                            <td><?php echo $freq['code']; ?></td>
                                            <td><?php echo $freq['name']; ?></td>
                                            <td><?php echo $freq['category_name']; ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-danger"><?php echo $freq['repair_count']; ?></span>
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
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // กราฟแจ้งซ่อมรายเดือน
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyChart = new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($chart_data, 'month_label')); ?>,
                datasets: [{
                    label: 'จำนวนการแจ้งซ่อม',
                    data: <?php echo json_encode(array_column($chart_data, 'count')); ?>,
                    borderColor: '#ff6b35',
                    backgroundColor: 'rgba(255, 107, 53, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
        
        // กราฟสถานะ
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['รอดำเนินการ', 'มอบหมายแล้ว', 'กำลังซ่อม', 'เสร็จสิ้น', 'ยกเลิก'],
                datasets: [{
                    data: [
                        <?php echo $status_data['pending'] ?? 0; ?>,
                        <?php echo $status_data['assigned'] ?? 0; ?>,
                        <?php echo $status_data['in_progress'] ?? 0; ?>,
                        <?php echo $status_data['completed'] ?? 0; ?>,
                        <?php echo $status_data['cancelled'] ?? 0; ?>
                    ],
                    backgroundColor: [
                        '#17a2b8',
                        '#007bff',
                        '#ffc107',
                        '#28a745',
                        '#dc3545'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: {
                                size: 12
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>