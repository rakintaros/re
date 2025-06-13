<?php
require_once 'config/database.php';
checkLogin();
checkRole(['user']);

$conn = connectDB();
$user_id = $_SESSION['user_id'];

// ดึงข้อมูลรายการแจ้งซ่อมของผู้ใช้
$sql = "SELECT r.*, e.name as equipment_name, e.code as equipment_code, 
        mt.name as team_name, t.fullname as technician_name
        FROM repair_requests r 
        LEFT JOIN equipment e ON r.equipment_id = e.id 
        LEFT JOIN maintenance_teams mt ON r.maintenance_team_id = mt.id 
        LEFT JOIN users t ON r.technician_id = t.id 
        WHERE r.user_id = $user_id 
        ORDER BY r.created_at DESC";
$result = mysqli_query($conn, $sql);

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการแจ้งซ่อม - ระบบจัดการครุภัณฑ์</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
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
        
        /* DataTable Custom Styles */
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%) !important;
            color: white !important;
            border: none !important;
            border-radius: 5px;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: rgba(255, 107, 53, 0.1) !important;
            color: #ff6b35 !important;
            border: 1px solid rgba(255, 107, 53, 0.3) !important;
        }
        
        .modal-content {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #ddd;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -24px;
            top: 5px;
            width: 12px;
            height: 12px;
            background: #ff6b35;
            border-radius: 50%;
            box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.2);
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
                
                <a href="repair_request.php" class="sidebar-item">
                    <i class="fas fa-wrench me-3"></i> แจ้งซ่อม
                </a>
                <a href="my_repairs.php" class="sidebar-item active">
                    <i class="fas fa-history me-3"></i> รายการแจ้งซ่อม
                </a>
            </div>
        </div>
        
        <!-- Content -->
        <div class="flex-grow-1 content-area">
            <!-- Page Header -->
            <div class="page-header">
                <h4 class="mb-0">
                    <i class="fas fa-history me-2"></i>
                    รายการแจ้งซ่อมของฉัน
                </h4>
            </div>
            
            <!-- Main Content -->
            <div class="glass-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0">ประวัติการแจ้งซ่อม</h5>
                    <a href="repair_request.php" class="btn btn-orange">
                        <i class="fas fa-plus me-2"></i>แจ้งซ่อมใหม่
                    </a>
                </div>
                
                <div class="table-responsive">
                    <table id="repairsTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>เลขที่แจ้งซ่อม</th>
                                <th>วันที่แจ้ง</th>
                                <th>อุปกรณ์</th>
                                <th>อาการ</th>
                                <th>ความเร่งด่วน</th>
                                <th>ทีมซ่อม</th>
                                <th>ช่างผู้รับผิดชอบ</th>
                                <th>สถานะ</th>
                                <th class="text-center">รายละเอียด</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $row['request_code']; ?></span>
                                    </td>
                                    <td><?php echo formatDateThai($row['created_at']); ?></td>
                                    <td>
                                        <strong><?php echo $row['equipment_name']; ?></strong><br>
                                        <small class="text-muted"><?php echo $row['equipment_code']; ?></small>
                                    </td>
                                    <td>
                                        <?php 
                                        $problem = $row['problem_description'];
                                        echo mb_strlen($problem) > 50 ? mb_substr($problem, 0, 50) . '...' : $problem;
                                        ?>
                                    </td>
                                    <td><?php echo getUrgencyBadge($row['urgency']); ?></td>
                                    <td><?php echo $row['team_name'] ?: '-'; ?></td>
                                    <td><?php echo $row['technician_name'] ?: 'ยังไม่มอบหมาย'; ?></td>
                                    <td><?php echo getStatusBadge($row['status']); ?></td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-info text-white" 
                                                onclick="viewDetail(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
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
                    <div class="row">
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
                                    <td><strong>อุปกรณ์:</strong></td>
                                    <td id="detail_equipment"></td>
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
                                    <td width="40%"><strong>สถานะ:</strong></td>
                                    <td id="detail_status"></td>
                                </tr>
                                <tr>
                                    <td><strong>ทีมซ่อม:</strong></td>
                                    <td id="detail_team"></td>
                                </tr>
                                <tr>
                                    <td><strong>ช่างผู้รับผิดชอบ:</strong></td>
                                    <td id="detail_technician"></td>
                                </tr>
                                <tr>
                                    <td><strong>วันที่ซ่อมเสร็จ:</strong></td>
                                    <td id="detail_completed"></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="mb-3">
                        <strong>อาการ/ปัญหาที่พบ:</strong>
                        <p id="detail_problem" class="mt-2"></p>
                    </div>
                    
                    <div class="mb-3" id="solution_section" style="display:none;">
                        <strong>การแก้ไข:</strong>
                        <p id="detail_solution" class="mt-2"></p>
                    </div>
                    
                    <div class="mb-3" id="notes_section" style="display:none;">
                        <strong>หมายเหตุ:</strong>
                        <p id="detail_notes" class="mt-2"></p>
                    </div>
                    
                    <!-- Timeline -->
                    <div class="mt-4">
                        <h6 class="mb-3">
                            <i class="fas fa-clock me-2" style="color: #ff6b35;"></i>
                            ประวัติการดำเนินการ
                        </h6>
                        <div id="detail_timeline" class="timeline">
                            <!-- จะถูกเติมด้วย JavaScript -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#repairsTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json'
                },
                pageLength: 25,
                order: [[1, 'desc']]
            });
        });
        
        function viewDetail(data) {
            // เติมข้อมูลพื้นฐาน
            document.getElementById('detail_code').textContent = data.request_code;
            document.getElementById('detail_date').textContent = formatDateThai(data.created_at);
            document.getElementById('detail_equipment').textContent = data.equipment_name + ' (' + data.equipment_code + ')';
            document.getElementById('detail_urgency').innerHTML = getUrgencyBadgeHTML(data.urgency);
            document.getElementById('detail_status').innerHTML = getStatusBadgeHTML(data.status);
            document.getElementById('detail_team').textContent = data.team_name || '-';
            document.getElementById('detail_technician').textContent = data.technician_name || 'ยังไม่มอบหมาย';
            document.getElementById('detail_completed').textContent = data.repair_end_date ? formatDateThai(data.repair_end_date) : '-';
            document.getElementById('detail_problem').textContent = data.problem_description;
            
            // แสดง/ซ่อนส่วนต่างๆ
            if (data.solution) {
                document.getElementById('solution_section').style.display = 'block';
                document.getElementById('detail_solution').textContent = data.solution;
            } else {
                document.getElementById('solution_section').style.display = 'none';
            }
            
            if (data.notes) {
                document.getElementById('notes_section').style.display = 'block';
                document.getElementById('detail_notes').textContent = data.notes;
            } else {
                document.getElementById('notes_section').style.display = 'none';
            }
            
            // โหลด Timeline (จำลอง)
            loadTimeline(data.id);
            
            // แสดง Modal
            new bootstrap.Modal(document.getElementById('detailModal')).show();
        }
        
        function loadTimeline(repairId) {
            // จำลอง Timeline
            const timeline = document.getElementById('detail_timeline');
            timeline.innerHTML = `
                <div class="timeline-item">
                    <strong>สร้างใบแจ้งซ่อม</strong><br>
                    <small class="text-muted">โดย ${$_SESSION['fullname']}</small>
                </div>
            `;
        }
        
        function formatDateThai(dateString) {
            if (!dateString) return '-';
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
        
        function getStatusBadgeHTML(status) {
            const badges = {
                'pending': '<span class="badge bg-info">รอดำเนินการ</span>',
                'assigned': '<span class="badge bg-primary">มอบหมายแล้ว</span>',
                'in_progress': '<span class="badge bg-warning">กำลังซ่อม</span>',
                'completed': '<span class="badge bg-success">เสร็จสิ้น</span>',
                'cancelled': '<span class="badge bg-danger">ยกเลิก</span>'
            };
            return badges[status] || status;
        }
    </script>
</body>
</html>