<?php
require_once 'config/database.php';
checkLogin();
checkRole(['admin']);

$conn = connectDB();

// รับค่าตัวกรอง
$filter_year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$filter_month = isset($_GET['month']) ? $_GET['month'] : date('m');
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'monthly';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';

// สถิติภาพรวม
$sql_overview = "SELECT 
    COUNT(DISTINCT e.id) as total_equipment,
    COUNT(DISTINCT CASE WHEN e.status = 'normal' THEN e.id END) as normal_equipment,
    COUNT(DISTINCT CASE WHEN e.status = 'repairing' THEN e.id END) as repairing_equipment,
    COUNT(DISTINCT CASE WHEN e.status = 'damaged' THEN e.id END) as damaged_equipment,
    COUNT(DISTINCT r.id) as total_repairs,
    COUNT(DISTINCT CASE WHEN r.status = 'completed' THEN r.id END) as completed_repairs,
    COUNT(DISTINCT CASE WHEN r.status IN ('pending', 'assigned', 'in_progress') THEN r.id END) as active_repairs,
    COUNT(DISTINCT u.id) as total_technicians,
    COUNT(DISTINCT mt.id) as total_teams
    FROM equipment e
    LEFT JOIN repair_requests r ON e.id = r.equipment_id
    LEFT JOIN users u ON u.role = 'technician'
    LEFT JOIN maintenance_teams mt ON 1=1";
$result_overview = mysqli_query($conn, $sql_overview);
$overview = mysqli_fetch_assoc($result_overview);

// กราฟตามประเภทที่เลือก
if ($filter_type == 'monthly') {
    // รายเดือนของปีที่เลือก
    $sql_chart = "SELECT 
        MONTH(created_at) as period,
        MONTHNAME(created_at) as period_label,
        COUNT(*) as total_count,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count
        FROM repair_requests
        WHERE YEAR(created_at) = '$filter_year'
        GROUP BY MONTH(created_at)
        ORDER BY MONTH(created_at)";
} else {
    // รายปี (5 ปีล่าสุด)
    $sql_chart = "SELECT 
        YEAR(created_at) as period,
        YEAR(created_at) as period_label,
        COUNT(*) as total_count,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count
        FROM repair_requests
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)
        GROUP BY YEAR(created_at)
        ORDER BY YEAR(created_at)";
}
$result_chart = mysqli_query($conn, $sql_chart);
$chart_data = [];
while ($row = mysqli_fetch_assoc($result_chart)) {
    $chart_data[] = $row;
}

// สถิติตามประเภทครุภัณฑ์
$sql_by_category = "SELECT 
    ec.name as category_name,
    COUNT(DISTINCT e.id) as equipment_count,
    COUNT(r.id) as repair_count
    FROM equipment_categories ec
    LEFT JOIN equipment e ON ec.id = e.category_id
    LEFT JOIN repair_requests r ON e.id = r.equipment_id
    GROUP BY ec.id
    ORDER BY repair_count DESC";
$result_by_category = mysqli_query($conn, $sql_by_category);

// สถิติตามแผนก
$sql_by_department = "SELECT 
    d.name as department_name,
    COUNT(DISTINCT e.id) as equipment_count,
    COUNT(r.id) as repair_count
    FROM departments d
    LEFT JOIN equipment e ON d.id = e.department_id
    LEFT JOIN repair_requests r ON e.id = r.equipment_id
    GROUP BY d.id
    ORDER BY repair_count DESC";
$result_by_department = mysqli_query($conn, $sql_by_department);

// ประสิทธิภาพทีมซ่อม
$sql_team_performance = "SELECT 
    mt.name as team_name,
    COUNT(DISTINCT r.id) as total_jobs,
    COUNT(DISTINCT CASE WHEN r.status = 'completed' THEN r.id END) as completed_jobs,
    AVG(CASE WHEN r.status = 'completed' THEN TIMESTAMPDIFF(HOUR, r.repair_start_date, r.repair_end_date) END) as avg_hours,
    COUNT(DISTINCT r.technician_id) as technician_count
    FROM maintenance_teams mt
    LEFT JOIN repair_requests r ON mt.id = r.maintenance_team_id
    WHERE r.created_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
    GROUP BY mt.id
    ORDER BY completed_jobs DESC";
$result_team_performance = mysqli_query($conn, $sql_team_performance);

// Top 10 ครุภัณฑ์ที่มีปัญหาบ่อย
$sql_top_problems = "SELECT 
    e.code, e.name, e.location,
    ec.name as category_name,
    COUNT(r.id) as repair_count,
    MAX(r.created_at) as last_repair_date
    FROM equipment e
    LEFT JOIN repair_requests r ON e.id = r.equipment_id
    LEFT JOIN equipment_categories ec ON e.category_id = ec.id
    WHERE r.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY e.id
    HAVING repair_count >= 2
    ORDER BY repair_count DESC
    LIMIT 10";
$result_top_problems = mysqli_query($conn, $sql_top_problems);

// Export Excel
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    // ตั้งค่า header สำหรับ Excel
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="equipment_report_' . date('Y-m-d') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // เริ่มเอกสาร HTML
    ?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #ff6b35; color: white; font-weight: bold; }
        .header { font-size: 16px; font-weight: bold; margin-bottom: 20px; }
        .section-title { background-color: #ff6b35; color: white; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">รายงานระบบครุภัณฑ์ - วันที่ <?php echo date('d/m/') . (date('Y') + 543) . ' เวลา ' . date('H:i:s'); ?> น.</div>
    
    <h3>สถิติภาพรวม</h3>
    <table>
        <tr>
            <th width="200">รายการ</th>
            <th width="150">จำนวน</th>
        </tr>
        <tr>
            <td>ครุภัณฑ์ทั้งหมด</td>
            <td><?php echo number_format($overview['total_equipment']); ?> รายการ</td>
        </tr>
        <tr>
            <td>ครุภัณฑ์ปกติ</td>
            <td><?php echo number_format($overview['normal_equipment']); ?> รายการ</td>
        </tr>
        <tr>
            <td>กำลังซ่อม</td>
            <td><?php echo number_format($overview['repairing_equipment']); ?> รายการ</td>
        </tr>
        <tr>
            <td>ชำรุด</td>
            <td><?php echo number_format($overview['damaged_equipment']); ?> รายการ</td>
        </tr>
        <tr>
            <td>การแจ้งซ่อมทั้งหมด</td>
            <td><?php echo number_format($overview['total_repairs']); ?> ครั้ง</td>
        </tr>
        <tr>
            <td>ซ่อมเสร็จแล้ว</td>
            <td><?php echo number_format($overview['completed_repairs']); ?> ครั้ง</td>
        </tr>
        <tr>
            <td>รอดำเนินการ</td>
            <td><?php echo number_format($overview['active_repairs']); ?> ครั้ง</td>
        </tr>
    </table>
    
    <br><br>
    
    <h3>รายละเอียดการแจ้งซ่อม</h3>
    <table>
        <tr>
            <th>เลขที่แจ้งซ่อม</th>
            <th>วันที่แจ้ง</th>
            <th>รหัสครุภัณฑ์</th>
            <th>ชื่อครุภัณฑ์</th>
            <th>ประเภท</th>
            <th>ผู้แจ้ง</th>
            <th>แผนก</th>
            <th>ทีมซ่อม</th>
            <th>ช่างผู้รับผิดชอบ</th>
            <th>สถานะ</th>
            <th>ความเร่งด่วน</th>
            <th>ระยะเวลา</th>
        </tr>
        <?php
        $sql_export_detail = "SELECT r.*, 
            e.code as equipment_code, e.name as equipment_name,
            ec.name as category_name,
            u1.fullname as requester_name,
            d.name as department_name,
            mt.name as team_name,
            u2.fullname as technician_name
            FROM repair_requests r
            LEFT JOIN equipment e ON r.equipment_id = e.id
            LEFT JOIN equipment_categories ec ON e.category_id = ec.id
            LEFT JOIN users u1 ON r.user_id = u1.id
            LEFT JOIN departments d ON u1.department_id = d.id
            LEFT JOIN maintenance_teams mt ON r.maintenance_team_id = mt.id
            LEFT JOIN users u2 ON r.technician_id = u2.id
            ORDER BY r.created_at DESC";
        
        $result_export = mysqli_query($conn, $sql_export_detail);
        
        $status_thai = [
            'pending' => 'รอดำเนินการ',
            'assigned' => 'มอบหมายแล้ว',
            'in_progress' => 'กำลังซ่อม',
            'completed' => 'เสร็จสิ้น',
            'cancelled' => 'ยกเลิก'
        ];
        
        $urgency_thai = [
            'low' => 'ต่ำ',
            'medium' => 'ปานกลาง',
            'high' => 'สูง',
            'urgent' => 'เร่งด่วน'
        ];
        
        while ($row = mysqli_fetch_assoc($result_export)) {
            $duration = '-';
            if ($row['repair_start_date'] && $row['repair_end_date']) {
                $start = new DateTime($row['repair_start_date']);
                $end = new DateTime($row['repair_end_date']);
                $diff = $start->diff($end);
                $duration = $diff->days . ' วัน ' . $diff->h . ' ชม.';
            }
            ?>
            <tr>
                <td><?php echo $row['request_code']; ?></td>
                <td><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                <td><?php echo $row['equipment_code']; ?></td>
                <td><?php echo $row['equipment_name']; ?></td>
                <td><?php echo $row['category_name']; ?></td>
                <td><?php echo $row['requester_name']; ?></td>
                <td><?php echo $row['department_name']; ?></td>
                <td><?php echo $row['team_name']; ?></td>
                <td><?php echo $row['technician_name'] ?: '-'; ?></td>
                <td><?php echo $status_thai[$row['status']]; ?></td>
                <td><?php echo $urgency_thai[$row['urgency']]; ?></td>
                <td><?php echo $duration; ?></td>
            </tr>
            <?php
        }
        ?>
    </table>
</body>
</html>
    <?php
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
        
        .btn-export {
            background: #28a745;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px 25px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            font-weight: 500;
        }
        
        .btn-export:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(40, 167, 69, 0.4);
            color: white;
            text-decoration: none;
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
            z-index: 1;
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
            z-index: -1;
            pointer-events: none;
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
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0) 100%);
            pointer-events: none;
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
        
        .stat-card.orange {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: white;
        }
        
        .stat-card.blue {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
        }
        
        .stat-card.green {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
            color: white;
        }
        
        .stat-card.purple {
            background: linear-gradient(135deg, #6f42c1 0%, #563d7c 100%);
            color: white;
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
        
        .performance-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #ff6b35;
            transition: all 0.3s ease;
        }
        
        .performance-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        
        .progress {
            height: 8px;
            border-radius: 4px;
            background: rgba(255, 107, 53, 0.1);
        }
        
        .progress-bar {
            background: linear-gradient(90deg, #ff6b35 0%, #f7931e 100%);
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
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-chart-bar me-2"></i>
                        รายงานและสถิติ
                    </h4>
                </div>
            </div>
            
            <!-- Export Button Outside Header -->
            <div class="mb-3 text-end">
                <a href="reports.php?export=excel" class="btn btn-export">
                    <i class="fas fa-file-excel me-2"></i>Export รายงานทั้งหมด
                </a>
            </div>
            
            <!-- Filter Section -->
            <div class="glass-card p-4 mb-4">
                <form method="GET" action="" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="type" class="form-label">ประเภทรายงาน</label>
                        <select class="form-select" id="type" name="type" onchange="toggleYearMonth()">
                            <option value="monthly" <?php echo $filter_type == 'monthly' ? 'selected' : ''; ?>>รายเดือน</option>
                            <option value="yearly" <?php echo $filter_type == 'yearly' ? 'selected' : ''; ?>>รายปี</option>
                        </select>
                    </div>
                    <div class="col-md-3" id="yearGroup">
                        <label for="year" class="form-label">ปี</label>
                        <select class="form-select" id="year" name="year">
                            <?php 
                            $current_year = date('Y');
                            for ($y = $current_year; $y >= $current_year - 4; $y--): 
                            ?>
                                <option value="<?php echo $y; ?>" <?php echo $filter_year == $y ? 'selected' : ''; ?>>
                                    <?php echo $y + 543; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3" id="monthGroup" style="<?php echo $filter_type == 'yearly' ? 'display:none;' : ''; ?>">
                        <label for="month" class="form-label">เดือน</label>
                        <select class="form-select" id="month" name="month">
                            <?php 
                            $thai_months = [
                                '01' => 'มกราคม', '02' => 'กุมภาพันธ์', '03' => 'มีนาคม',
                                '04' => 'เมษายน', '05' => 'พฤษภาคม', '06' => 'มิถุนายน',
                                '07' => 'กรกฎาคม', '08' => 'สิงหาคม', '09' => 'กันยายน',
                                '10' => 'ตุลาคม', '11' => 'พฤศจิกายน', '12' => 'ธันวาคม'
                            ];
                            foreach ($thai_months as $num => $name): 
                            ?>
                                <option value="<?php echo $num; ?>" <?php echo $filter_month == $num ? 'selected' : ''; ?>>
                                    <?php echo $name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-orange">
                            <i class="fas fa-filter me-2"></i>แสดงรายงาน
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="printReport()">
                            <i class="fas fa-print me-2"></i>พิมพ์
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Statistics Overview -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="glass-card stat-card orange">
                        <i class="fas fa-desktop"></i>
                        <h3><?php echo number_format($overview['total_equipment']); ?></h3>
                        <p class="mb-0">ครุภัณฑ์ทั้งหมด</p>
                        <small>ปกติ <?php echo number_format($overview['normal_equipment']); ?> | ซ่อม <?php echo number_format($overview['repairing_equipment']); ?></small>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="glass-card stat-card blue">
                        <i class="fas fa-wrench"></i>
                        <h3><?php echo number_format($overview['total_repairs']); ?></h3>
                        <p class="mb-0">แจ้งซ่อมทั้งหมด</p>
                        <small>เสร็จ <?php echo number_format($overview['completed_repairs']); ?> | รอ <?php echo number_format($overview['active_repairs']); ?></small>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="glass-card stat-card green">
                        <i class="fas fa-user-cog"></i>
                        <h3><?php echo number_format($overview['total_technicians']); ?></h3>
                        <p class="mb-0">ช่างซ่อม</p>
                        <small><?php echo number_format($overview['total_teams']); ?> ทีม</small>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="glass-card stat-card purple">
                        <i class="fas fa-percentage"></i>
                        <h3>
                            <?php 
                            $success_rate = $overview['total_repairs'] > 0 ? 
                                round(($overview['completed_repairs'] / $overview['total_repairs']) * 100, 1) : 0;
                            echo $success_rate;
                            ?>%
                        </h3>
                        <p class="mb-0">อัตราสำเร็จ</p>
                        <small>การซ่อมทั้งหมด</small>
                    </div>
                </div>
            </div>
            
            <!-- Tabs -->
            <div class="glass-card p-4">
                <ul class="nav nav-tabs mb-4" id="reportTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="chart-tab" data-bs-toggle="tab" data-bs-target="#chart" type="button">
                            <i class="fas fa-chart-line me-2"></i>กราฟสถิติ
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="category-tab" data-bs-toggle="tab" data-bs-target="#category" type="button">
                            <i class="fas fa-tags me-2"></i>ตามประเภท
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="department-tab" data-bs-toggle="tab" data-bs-target="#department" type="button">
                            <i class="fas fa-building me-2"></i>ตามแผนก
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="team-tab" data-bs-toggle="tab" data-bs-target="#team" type="button">
                            <i class="fas fa-users-cog me-2"></i>ประสิทธิภาพทีม
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="problem-tab" data-bs-toggle="tab" data-bs-target="#problem" type="button">
                            <i class="fas fa-exclamation-triangle me-2"></i>ปัญหาบ่อย
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="reportTabContent">
                    <!-- Chart Tab -->
                    <div class="tab-pane fade show active" id="chart" role="tabpanel">
                        <div class="row">
                            <div class="col-md-8">
                                <h5 class="mb-3">แนวโน้มการแจ้งซ่อม</h5>
                                <div style="position: relative; height: 400px;">
                                    <canvas id="mainChart"></canvas>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <h5 class="mb-3">สัดส่วนตามความเร่งด่วน</h5>
                                <div style="position: relative; height: 400px;">
                                    <canvas id="urgencyChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Category Tab -->
                    <div class="tab-pane fade" id="category" role="tabpanel">
                        <h5 class="mb-4">สถิติตามประเภทครุภัณฑ์</h5>
                        <div class="row">
                            <div class="col-md-8">
                                <div style="position: relative; height: 400px;">
                                    <canvas id="categoryChart"></canvas>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>ประเภท</th>
                                                <th class="text-center">ครุภัณฑ์</th>
                                                <th class="text-center">แจ้งซ่อม</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            mysqli_data_seek($result_by_category, 0);
                                            while ($cat = mysqli_fetch_assoc($result_by_category)): 
                                            ?>
                                                <tr>
                                                    <td><?php echo $cat['category_name']; ?></td>
                                                    <td class="text-center"><?php echo $cat['equipment_count']; ?></td>
                                                    <td class="text-center">
                                                        <span class="badge bg-warning"><?php echo $cat['repair_count']; ?></span>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Department Tab -->
                    <div class="tab-pane fade" id="department" role="tabpanel">
                        <h5 class="mb-4">สถิติตามแผนก</h5>
                        <div class="row">
                            <?php 
                            mysqli_data_seek($result_by_department, 0);
                            while ($dept = mysqli_fetch_assoc($result_by_department)): 
                                $percent = $overview['total_equipment'] > 0 ? 
                                    round(($dept['equipment_count'] / $overview['total_equipment']) * 100, 1) : 0;
                            ?>
                                <div class="col-md-6 mb-3">
                                    <div class="performance-card">
                                        <h6><?php echo $dept['department_name']; ?></h6>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>ครุภัณฑ์: <?php echo $dept['equipment_count']; ?> รายการ</span>
                                            <span>แจ้งซ่อม: <?php echo $dept['repair_count']; ?> ครั้ง</span>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar" style="width: <?php echo $percent; ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    
                    <!-- Team Performance Tab -->
                    <div class="tab-pane fade" id="team" role="tabpanel">
                        <h5 class="mb-4">ประสิทธิภาพทีมซ่อม (3 เดือนล่าสุด)</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ทีม</th>
                                        <th class="text-center">ช่าง</th>
                                        <th class="text-center">รับงาน</th>
                                        <th class="text-center">เสร็จ</th>
                                        <th class="text-center">อัตราสำเร็จ</th>
                                        <th class="text-center">เวลาเฉลี่ย</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($team = mysqli_fetch_assoc($result_team_performance)): 
                                        $team_success_rate = $team['total_jobs'] > 0 ? 
                                            round(($team['completed_jobs'] / $team['total_jobs']) * 100, 1) : 0;
                                    ?>
                                        <tr>
                                            <td>
                                                <i class="fas fa-users-cog me-2" style="color: #ff6b35;"></i>
                                                <?php echo $team['team_name']; ?>
                                            </td>
                                            <td class="text-center"><?php echo $team['technician_count']; ?></td>
                                            <td class="text-center"><?php echo $team['total_jobs']; ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-success"><?php echo $team['completed_jobs']; ?></span>
                                            </td>
                                            <td class="text-center">
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar" style="width: <?php echo $team_success_rate; ?>%">
                                                        <?php echo $team_success_rate; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <?php echo $team['avg_hours'] ? round($team['avg_hours'], 1) . ' ชม.' : '-'; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Problem Equipment Tab -->
                    <div class="tab-pane fade" id="problem" role="tabpanel">
                        <h5 class="mb-4">ครุภัณฑ์ที่มีปัญหาบ่อย (6 เดือนล่าสุด)</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>รหัส</th>
                                        <th>ชื่อครุภัณฑ์</th>
                                        <th>ประเภท</th>
                                        <th>ที่ตั้ง</th>
                                        <th class="text-center">จำนวนครั้ง</th>
                                        <th>ซ่อมล่าสุด</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($prob = mysqli_fetch_assoc($result_top_problems)): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo $prob['code']; ?></span>
                                            </td>
                                            <td><?php echo $prob['name']; ?></td>
                                            <td><?php echo $prob['category_name']; ?></td>
                                            <td><?php echo $prob['location'] ?: '-'; ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-danger"><?php echo $prob['repair_count']; ?></span>
                                            </td>
                                            <td><?php echo formatDateThai($prob['last_repair_date']); ?></td>
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
        // Toggle Year/Month
        function toggleYearMonth() {
            const type = document.getElementById('type').value;
            const monthGroup = document.getElementById('monthGroup');
            
            if (type === 'yearly') {
                monthGroup.style.display = 'none';
            } else {
                monthGroup.style.display = 'block';
            }
        }
        
        // Print Report
        function printReport() {
            window.print();
        }
        
        // Chart Data
        const chartLabels = <?php echo json_encode(array_column($chart_data, 'period_label')); ?>;
        const totalData = <?php echo json_encode(array_column($chart_data, 'total_count')); ?>;
        const completedData = <?php echo json_encode(array_column($chart_data, 'completed_count')); ?>;
        
        // Main Chart
        const mainCtx = document.getElementById('mainChart').getContext('2d');
        const mainChart = new Chart(mainCtx, {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'แจ้งซ่อมทั้งหมด',
                    data: totalData,
                    backgroundColor: 'rgba(255, 107, 53, 0.5)',
                    borderColor: '#ff6b35',
                    borderWidth: 2
                }, {
                    label: 'ซ่อมเสร็จ',
                    data: completedData,
                    backgroundColor: 'rgba(40, 167, 69, 0.5)',
                    borderColor: '#28a745',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            precision: 0
                        },
                        grid: {
                            drawBorder: false,
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                barThickness: 40,
                maxBarThickness: 50,
                categoryPercentage: 0.8,
                barPercentage: 0.9
            }
        });
        
        // Urgency Chart
        const urgencyCtx = document.getElementById('urgencyChart').getContext('2d');
        const urgencyChart = new Chart(urgencyCtx, {
            type: 'doughnut',
            data: {
                labels: ['ต่ำ', 'ปานกลาง', 'สูง', 'เร่งด่วน'],
                datasets: [{
                    data: [25, 40, 25, 10], // ข้อมูลจำลอง
                    backgroundColor: [
                        '#28a745',
                        '#ffc107',
                        '#fd7e14',
                        '#dc3545'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Category Chart
        <?php
        mysqli_data_seek($result_by_category, 0);
        $cat_names = [];
        $cat_repairs = [];
        while ($cat = mysqli_fetch_assoc($result_by_category)) {
            $cat_names[] = $cat['category_name'];
            $cat_repairs[] = $cat['repair_count'];
        }
        ?>
        
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($cat_names); ?>,
                datasets: [{
                    label: 'จำนวนการแจ้งซ่อม',
                    data: <?php echo json_encode($cat_repairs); ?>,
                    backgroundColor: 'rgba(255, 107, 53, 0.7)',
                    borderColor: '#ff6b35',
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            precision: 0
                        },
                        grid: {
                            drawBorder: false,
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    },
                    y: {
                        grid: {
                            display: false
                        }
                    }
                },
                barThickness: 30,
                maxBarThickness: 40
            }
        });
    </script>
</body>
</html>