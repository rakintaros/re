<?php
require_once 'config/database.php';
checkLogin();
checkRole(['user']);

$conn = connectDB();

// ประมวลผลการแจ้งซ่อม
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $equipment_id = (int)$_POST['equipment_id'];
    $problem_description = escape($conn, $_POST['problem_description']);
    $urgency = escape($conn, $_POST['urgency']);
    $user_id = $_SESSION['user_id'];
    $request_code = generateCode('REQ', $conn, 'repair_requests', 'request_code');
    
    // ดึงข้อมูลครุภัณฑ์เพื่อหาทีมซ่อม
    $sql_equipment = "SELECT e.*, c.name as category_name 
                      FROM equipment e 
                      LEFT JOIN equipment_categories c ON e.category_id = c.id 
                      WHERE e.id = $equipment_id";
    $result_equipment = mysqli_query($conn, $sql_equipment);
    $equipment = mysqli_fetch_assoc($result_equipment);
    
    // กำหนดทีมซ่อมตามประเภทครุภัณฑ์ (ตัวอย่างการ mapping)
    $team_mapping = [
        'คอมพิวเตอร์' => 'ทีมซ่อมคอมพิวเตอร์',
        'กล้องวงจรปิด' => 'ทีมซ่อมวงจรปิด',
        'เครือข่าย' => 'ทีมซ่อมเครือข่าย',
        'เครื่องพิมพ์' => 'ทีมซ่อมคอมพิวเตอร์'
    ];
    
    $team_name = $team_mapping[$equipment['category_name']] ?? 'ทีมซ่อมคอมพิวเตอร์';
    
    // หา team_id
    $sql_team = "SELECT id FROM maintenance_teams WHERE name = '$team_name'";
    $result_team = mysqli_query($conn, $sql_team);
    $team = mysqli_fetch_assoc($result_team);
    $maintenance_team_id = $team['id'] ?? 1;
    
    // บันทึกการแจ้งซ่อม
    $sql = "INSERT INTO repair_requests (request_code, equipment_id, user_id, maintenance_team_id, 
            problem_description, urgency, status) 
            VALUES ('$request_code', $equipment_id, $user_id, $maintenance_team_id, 
            '$problem_description', '$urgency', 'pending')";
    
    if (mysqli_query($conn, $sql)) {
        $repair_id = mysqli_insert_id($conn);
        
        // บันทึกประวัติ
        $sql_history = "INSERT INTO repair_history (repair_request_id, action, description, created_by) 
                        VALUES ($repair_id, 'สร้างใบแจ้งซ่อม', 'แจ้งซ่อม $equipment[name]', $user_id)";
        mysqli_query($conn, $sql_history);
        
        // อัพเดทสถานะครุภัณฑ์
        $sql_update = "UPDATE equipment SET status = 'repairing' WHERE id = $equipment_id";
        mysqli_query($conn, $sql_update);
        
        // ส่งการแจ้งเตือน Telegram
        $message = "🔧 <b>แจ้งซ่อมใหม่</b>\n\n";
        $message .= "📋 เลขที่: $request_code\n";
        $message .= "🖥️ อุปกรณ์: {$equipment['name']}\n";
        $message .= "👤 ผู้แจ้ง: {$_SESSION['fullname']}\n";
        $message .= "⚠️ ความเร่งด่วน: $urgency\n";
        $message .= "📝 อาการ: $problem_description\n";
        $message .= "🏢 ทีมซ่อม: $team_name";
        
        sendTelegramNotification($message);
        
        $_SESSION['success'] = "แจ้งซ่อมสำเร็จ รหัสการแจ้งซ่อม: $request_code";
        header("Location: my_repairs.php");
        exit();
    } else {
        $_SESSION['error'] = "เกิดข้อผิดพลาด: " . mysqli_error($conn);
    }
}

// ดึงข้อมูลครุภัณฑ์ที่สามารถแจ้งซ่อมได้
$dept_id = $_SESSION['department_id'];
$sql_equipment = "SELECT e.*, c.name as category_name 
                  FROM equipment e 
                  LEFT JOIN equipment_categories c ON e.category_id = c.id 
                  WHERE e.department_id = $dept_id AND e.status = 'normal' 
                  ORDER BY e.name";
$result_equipment = mysqli_query($conn, $sql_equipment);

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แจ้งซ่อม - ระบบจัดการครุภัณฑ์</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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
        
        .urgency-card {
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .urgency-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        
        .urgency-card.selected {
            border-color: #ff6b35;
            box-shadow: 0 5px 20px rgba(255, 107, 53, 0.3);
        }
        
        .urgency-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        /* Select2 Custom Style */
        .select2-container--default .select2-selection--single {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(255, 107, 53, 0.2);
            border-radius: 10px;
            height: 38px;
            padding: 5px;
        }
        
        .select2-container--default .select2-selection--single:focus {
            border-color: #ff6b35;
        }
        
        .equipment-info {
            background: rgba(255, 107, 53, 0.05);
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
            display: none;
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
                
                <a href="repair_request.php" class="sidebar-item active">
                    <i class="fas fa-wrench me-3"></i> แจ้งซ่อม
                </a>
                <a href="my_repairs.php" class="sidebar-item">
                    <i class="fas fa-history me-3"></i> รายการแจ้งซ่อม
                </a>
            </div>
        </div>
        
        <!-- Content -->
        <div class="flex-grow-1 content-area">
            <!-- Page Header -->
            <div class="page-header">
                <h4 class="mb-0">
                    <i class="fas fa-wrench me-2"></i>
                    แจ้งซ่อมครุภัณฑ์
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
            <form method="POST" action="">
                <div class="glass-card p-4">
                    <h5 class="mb-4">
                        <i class="fas fa-file-alt me-2" style="color: #ff6b35;"></i>
                        ฟอร์มแจ้งซ่อม
                    </h5>
                    
                    <div class="row">
                        <div class="col-md-12 mb-4">
                            <label for="equipment_id" class="form-label">เลือกครุภัณฑ์ที่ต้องการแจ้งซ่อม <span class="text-danger">*</span></label>
                            <select class="form-control" id="equipment_id" name="equipment_id" required>
                                <option value="">-- เลือกครุภัณฑ์ --</option>
                                <?php while ($equipment = mysqli_fetch_assoc($result_equipment)): ?>
                                    <option value="<?php echo $equipment['id']; ?>" 
                                            data-code="<?php echo $equipment['code']; ?>"
                                            data-category="<?php echo $equipment['category_name']; ?>"
                                            data-location="<?php echo $equipment['location']; ?>"
                                            data-brand="<?php echo $equipment['brand']; ?>"
                                            data-model="<?php echo $equipment['model']; ?>">
                                        <?php echo $equipment['name']; ?> (<?php echo $equipment['code']; ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            
                            <div id="equipment_info" class="equipment-info">
                                <h6 class="mb-2">ข้อมูลครุภัณฑ์</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <small class="text-muted">รหัส:</small> <span id="info_code">-</span><br>
                                        <small class="text-muted">ประเภท:</small> <span id="info_category">-</span>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-muted">ยี่ห้อ/รุ่น:</small> <span id="info_brand_model">-</span><br>
                                        <small class="text-muted">ที่ตั้ง:</small> <span id="info_location">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-12 mb-4">
                            <label class="form-label">ระดับความเร่งด่วน <span class="text-danger">*</span></label>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <div class="glass-card p-3 text-center urgency-card" onclick="selectUrgency('low')">
                                        <div class="urgency-icon text-success">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                        <h6 class="mb-1">ต่ำ</h6>
                                        <small class="text-muted">ยังใช้งานได้</small>
                                        <input type="radio" name="urgency" value="low" id="urgency_low" class="d-none">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="glass-card p-3 text-center urgency-card" onclick="selectUrgency('medium')">
                                        <div class="urgency-icon text-warning">
                                            <i class="fas fa-exclamation-triangle"></i>
                                        </div>
                                        <h6 class="mb-1">ปานกลาง</h6>
                                        <small class="text-muted">ใช้งานได้บางส่วน</small>
                                        <input type="radio" name="urgency" value="medium" id="urgency_medium" class="d-none" checked>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="glass-card p-3 text-center urgency-card" onclick="selectUrgency('high')">
                                        <div class="urgency-icon text-danger">
                                            <i class="fas fa-exclamation-circle"></i>
                                        </div>
                                        <h6 class="mb-1">สูง</h6>
                                        <small class="text-muted">ใช้งานไม่ได้</small>
                                        <input type="radio" name="urgency" value="high" id="urgency_high" class="d-none">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="glass-card p-3 text-center urgency-card" onclick="selectUrgency('urgent')">
                                        <div class="urgency-icon text-danger">
                                            <i class="fas fa-fire"></i>
                                        </div>
                                        <h6 class="mb-1">เร่งด่วน</h6>
                                        <small class="text-muted">กระทบการทำงาน</small>
                                        <input type="radio" name="urgency" value="urgent" id="urgency_urgent" class="d-none">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-12 mb-4">
                            <label for="problem_description" class="form-label">อาการ/ปัญหาที่พบ <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="problem_description" name="problem_description" 
                                      rows="5" placeholder="กรุณาอธิบายอาการหรือปัญหาที่พบโดยละเอียด..." required></textarea>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <button type="submit" class="btn btn-orange btn-lg">
                            <i class="fas fa-paper-plane me-2"></i>ส่งแจ้งซ่อม
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#equipment_id').select2({
                placeholder: "-- เลือกครุภัณฑ์ --",
                allowClear: true
            });
            
            $('#equipment_id').on('change', function() {
                const selected = $(this).find(':selected');
                if (selected.val()) {
                    $('#info_code').text(selected.data('code'));
                    $('#info_category').text(selected.data('category') || '-');
                    $('#info_location').text(selected.data('location') || '-');
                    
                    const brand = selected.data('brand') || '';
                    const model = selected.data('model') || '';
                    $('#info_brand_model').text((brand + ' ' + model).trim() || '-');
                    
                    $('#equipment_info').slideDown();
                } else {
                    $('#equipment_info').slideUp();
                }
            });
            
            // Set default urgency
            selectUrgency('medium');
        });
        
        function selectUrgency(level) {
            // Remove all selected classes
            document.querySelectorAll('.urgency-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to clicked card
            document.getElementById('urgency_' + level).checked = true;
            document.getElementById('urgency_' + level).closest('.urgency-card').classList.add('selected');
        }
    </script>
</body>
</html>