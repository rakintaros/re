<?php
require_once 'config/database.php';
checkLogin();
checkRole(['admin']);

$conn = connectDB();

// ประมวลผลการเพิ่ม/แก้ไข/ลบ
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $code = generateCode('EQ', $conn, 'equipment', 'code');
                $name = escape($conn, $_POST['name']);
                $category_id = (int)$_POST['category_id'];
                $department_id = (int)$_POST['department_id'];
                $brand = escape($conn, $_POST['brand']);
                $model = escape($conn, $_POST['model']);
                $serial_number = escape($conn, $_POST['serial_number']);
                $purchase_date = $_POST['purchase_date'] ?: 'NULL';
                $warranty_expire = $_POST['warranty_expire'] ?: 'NULL';
                $location = escape($conn, $_POST['location']);
                $description = escape($conn, $_POST['description']);
                
                $sql = "INSERT INTO equipment (code, name, category_id, department_id, brand, model, serial_number, 
                        purchase_date, warranty_expire, location, description) 
                        VALUES ('$code', '$name', $category_id, $department_id, '$brand', '$model', '$serial_number', 
                        " . ($purchase_date != 'NULL' ? "'$purchase_date'" : "NULL") . ", 
                        " . ($warranty_expire != 'NULL' ? "'$warranty_expire'" : "NULL") . ", 
                        '$location', '$description')";
                
                if (mysqli_query($conn, $sql)) {
                    $_SESSION['success'] = "เพิ่มครุภัณฑ์สำเร็จ รหัส: $code";
                } else {
                    $_SESSION['error'] = "เกิดข้อผิดพลาด: " . mysqli_error($conn);
                }
                break;
                
            case 'edit':
                $id = (int)$_POST['id'];
                $name = escape($conn, $_POST['name']);
                $category_id = (int)$_POST['category_id'];
                $department_id = (int)$_POST['department_id'];
                $brand = escape($conn, $_POST['brand']);
                $model = escape($conn, $_POST['model']);
                $serial_number = escape($conn, $_POST['serial_number']);
                $purchase_date = $_POST['purchase_date'] ?: 'NULL';
                $warranty_expire = $_POST['warranty_expire'] ?: 'NULL';
                $status = escape($conn, $_POST['status']);
                $location = escape($conn, $_POST['location']);
                $description = escape($conn, $_POST['description']);
                
                $sql = "UPDATE equipment SET 
                        name = '$name', 
                        category_id = $category_id, 
                        department_id = $department_id, 
                        brand = '$brand', 
                        model = '$model', 
                        serial_number = '$serial_number', 
                        purchase_date = " . ($purchase_date != 'NULL' ? "'$purchase_date'" : "NULL") . ", 
                        warranty_expire = " . ($warranty_expire != 'NULL' ? "'$warranty_expire'" : "NULL") . ", 
                        status = '$status',
                        location = '$location', 
                        description = '$description' 
                        WHERE id = $id";
                
                if (mysqli_query($conn, $sql)) {
                    $_SESSION['success'] = "แก้ไขครุภัณฑ์สำเร็จ";
                } else {
                    $_SESSION['error'] = "เกิดข้อผิดพลาด: " . mysqli_error($conn);
                }
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                
                // ตรวจสอบว่ามีการแจ้งซ่อมหรือไม่
                $check_sql = "SELECT COUNT(*) as count FROM repair_requests WHERE equipment_id = $id";
                $check_result = mysqli_query($conn, $check_sql);
                $count = mysqli_fetch_assoc($check_result)['count'];
                
                if ($count > 0) {
                    $_SESSION['error'] = "ไม่สามารถลบได้ เนื่องจากมีประวัติการแจ้งซ่อม";
                } else {
                    $sql = "DELETE FROM equipment WHERE id = $id";
                    if (mysqli_query($conn, $sql)) {
                        $_SESSION['success'] = "ลบครุภัณฑ์สำเร็จ";
                    } else {
                        $_SESSION['error'] = "เกิดข้อผิดพลาด: " . mysqli_error($conn);
                    }
                }
                break;
        }
        
        header("Location: equipment.php");
        exit();
    }
}

// ดึงข้อมูลประเภทครุภัณฑ์
$sql_categories = "SELECT * FROM equipment_categories ORDER BY name";
$result_categories = mysqli_query($conn, $sql_categories);

// ดึงข้อมูลแผนก
$sql_departments = "SELECT * FROM departments ORDER BY name";
$result_departments = mysqli_query($conn, $sql_departments);

// ดึงข้อมูลครุภัณฑ์
$sql = "SELECT e.*, c.name as category_name, d.name as department_name 
        FROM equipment e 
        LEFT JOIN equipment_categories c ON e.category_id = c.id 
        LEFT JOIN departments d ON e.department_id = d.id 
        ORDER BY e.created_at DESC";
$result = mysqli_query($conn, $sql);

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการครุภัณฑ์ - ระบบจัดการครุภัณฑ์</title>
    
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
        
        .table-glass {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 15px;
            overflow: hidden;
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
        
        .modal-content {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.3);
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
        
        .dataTables_wrapper .dataTables_filter input {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(255, 107, 53, 0.2);
            border-radius: 10px;
            padding: 5px 10px;
        }
        
        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: #ff6b35;
            box-shadow: 0 0 0 0.2rem rgba(255, 107, 53, 0.25);
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
                <a href="equipment.php" class="sidebar-item active">
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
            </div>
        </div>
        
        <!-- Content -->
        <div class="flex-grow-1 content-area">
            <!-- Page Header -->
            <div class="page-header">
                <h4 class="mb-0">
                    <i class="fas fa-desktop me-2"></i>
                    จัดการครุภัณฑ์
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
            <div class="glass-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0">รายการครุภัณฑ์</h5>
                    <button class="btn btn-orange" data-bs-toggle="modal" data-bs-target="#addModal">
                        <i class="fas fa-plus me-2"></i>เพิ่มครุภัณฑ์
                    </button>
                </div>
                
                <div class="table-responsive">
                    <table id="equipmentTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>รหัส</th>
                                <th>ชื่อครุภัณฑ์</th>
                                <th>ประเภท</th>
                                <th>แผนก</th>
                                <th>ยี่ห้อ/รุ่น</th>
                                <th>สถานะ</th>
                                <th>ที่ตั้ง</th>
                                <th class="text-center">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $row['code']; ?></span>
                                    </td>
                                    <td>
                                        <i class="fas fa-desktop me-2" style="color: #ff6b35;"></i>
                                        <?php echo $row['name']; ?>
                                    </td>
                                    <td><?php echo $row['category_name'] ?: '-'; ?></td>
                                    <td><?php echo $row['department_name'] ?: '-'; ?></td>
                                    <td>
                                        <?php 
                                        if ($row['brand'] || $row['model']) {
                                            echo $row['brand'] . ' ' . $row['model'];
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo getStatusBadge($row['status']); ?></td>
                                    <td><?php echo $row['location'] ?: '-'; ?></td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-info text-white" 
                                                onclick="viewEquipment(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-warning text-white" 
                                                onclick="editEquipment(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" 
                                                onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo $row['name']; ?>')">
                                            <i class="fas fa-trash"></i>
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
    
    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus-circle me-2" style="color: #ff6b35;"></i>
                        เพิ่มครุภัณฑ์
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="add_name" class="form-label">ชื่อครุภัณฑ์ <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="add_name" name="name" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="add_category_id" class="form-label">ประเภท <span class="text-danger">*</span></label>
                                <select class="form-select" id="add_category_id" name="category_id" required>
                                    <option value="">เลือกประเภท</option>
                                    <?php 
                                    mysqli_data_seek($result_categories, 0);
                                    while ($cat = mysqli_fetch_assoc($result_categories)): 
                                    ?>
                                        <option value="<?php echo $cat['id']; ?>"><?php echo $cat['name']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="add_department_id" class="form-label">แผนก <span class="text-danger">*</span></label>
                                <select class="form-select" id="add_department_id" name="department_id" required>
                                    <option value="">เลือกแผนก</option>
                                    <?php 
                                    mysqli_data_seek($result_departments, 0);
                                    while ($dept = mysqli_fetch_assoc($result_departments)): 
                                    ?>
                                        <option value="<?php echo $dept['id']; ?>"><?php echo $dept['name']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="add_location" class="form-label">ที่ตั้ง</label>
                                <input type="text" class="form-control" id="add_location" name="location">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="add_brand" class="form-label">ยี่ห้อ</label>
                                <input type="text" class="form-control" id="add_brand" name="brand">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="add_model" class="form-label">รุ่น</label>
                                <input type="text" class="form-control" id="add_model" name="model">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="add_serial_number" class="form-label">Serial Number</label>
                                <input type="text" class="form-control" id="add_serial_number" name="serial_number">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="add_purchase_date" class="form-label">วันที่ซื้อ</label>
                                <input type="date" class="form-control" id="add_purchase_date" name="purchase_date">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="add_warranty_expire" class="form-label">วันหมดประกัน</label>
                                <input type="date" class="form-control" id="add_warranty_expire" name="warranty_expire">
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label for="add_description" class="form-label">รายละเอียด</label>
                                <textarea class="form-control" id="add_description" name="description" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-orange">
                            <i class="fas fa-save me-2"></i>บันทึก
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2" style="color: #ff6b35;"></i>
                        แก้ไขครุภัณฑ์
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_id">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_name" class="form-label">ชื่อครุภัณฑ์ <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_name" name="name" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="edit_category_id" class="form-label">ประเภท <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_category_id" name="category_id" required>
                                    <option value="">เลือกประเภท</option>
                                    <?php 
                                    mysqli_data_seek($result_categories, 0);
                                    while ($cat = mysqli_fetch_assoc($result_categories)): 
                                    ?>
                                        <option value="<?php echo $cat['id']; ?>"><?php echo $cat['name']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="edit_department_id" class="form-label">แผนก <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_department_id" name="department_id" required>
                                    <option value="">เลือกแผนก</option>
                                    <?php 
                                    mysqli_data_seek($result_departments, 0);
                                    while ($dept = mysqli_fetch_assoc($result_departments)): 
                                    ?>
                                        <option value="<?php echo $dept['id']; ?>"><?php echo $dept['name']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="edit_status" class="form-label">สถานะ <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_status" name="status" required>
                                    <option value="normal">ปกติ</option>
                                    <option value="repairing">กำลังซ่อม</option>
                                    <option value="damaged">ชำรุด</option>
                                    <option value="retired">เลิกใช้งาน</option>
                                </select>
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <label for="edit_location" class="form-label">ที่ตั้ง</label>
                                <input type="text" class="form-control" id="edit_location" name="location">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="edit_brand" class="form-label">ยี่ห้อ</label>
                                <input type="text" class="form-control" id="edit_brand" name="brand">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="edit_model" class="form-label">รุ่น</label>
                                <input type="text" class="form-control" id="edit_model" name="model">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="edit_serial_number" class="form-label">Serial Number</label>
                                <input type="text" class="form-control" id="edit_serial_number" name="serial_number">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="edit_purchase_date" class="form-label">วันที่ซื้อ</label>
                                <input type="date" class="form-control" id="edit_purchase_date" name="purchase_date">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="edit_warranty_expire" class="form-label">วันหมดประกัน</label>
                                <input type="date" class="form-control" id="edit_warranty_expire" name="warranty_expire">
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label for="edit_description" class="form-label">รายละเอียด</label>
                                <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-orange">
                            <i class="fas fa-save me-2"></i>บันทึกการแก้ไข
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- View Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-info-circle me-2" style="color: #ff6b35;"></i>
                        รายละเอียดครุภัณฑ์
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewContent">
                    <!-- จะถูกเติมด้วย JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Form -->
    <form method="POST" action="" id="deleteForm" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="delete_id">
    </form>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        $(document).ready(function() {
            $('#equipmentTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json'
                },
                pageLength: 25,
                order: [[0, 'desc']]
            });
        });
        
        function viewEquipment(data) {
            let content = `
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td width="40%"><strong>รหัส:</strong></td>
                                <td><span class="badge bg-secondary">${data.code}</span></td>
                            </tr>
                            <tr>
                                <td><strong>ชื่อครุภัณฑ์:</strong></td>
                                <td>${data.name}</td>
                            </tr>
                            <tr>
                                <td><strong>ประเภท:</strong></td>
                                <td>${data.category_name || '-'}</td>
                            </tr>
                            <tr>
                                <td><strong>แผนก:</strong></td>
                                <td>${data.department_name || '-'}</td>
                            </tr>
                            <tr>
                                <td><strong>สถานะ:</strong></td>
                                <td>${getStatusBadgeJS(data.status)}</td>
                            </tr>
                            <tr>
                                <td><strong>ที่ตั้ง:</strong></td>
                                <td>${data.location || '-'}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td width="40%"><strong>ยี่ห้อ:</strong></td>
                                <td>${data.brand || '-'}</td>
                            </tr>
                            <tr>
                                <td><strong>รุ่น:</strong></td>
                                <td>${data.model || '-'}</td>
                            </tr>
                            <tr>
                                <td><strong>Serial Number:</strong></td>
                                <td>${data.serial_number || '-'}</td>
                            </tr>
                            <tr>
                                <td><strong>วันที่ซื้อ:</strong></td>
                                <td>${formatDate(data.purchase_date)}</td>
                            </tr>
                            <tr>
                                <td><strong>วันหมดประกัน:</strong></td>
                                <td>${formatDate(data.warranty_expire)}</td>
                            </tr>
                        </table>
                    </div>
                </div>
                ${data.description ? `
                    <hr>
                    <div class="row">
                        <div class="col-12">
                            <strong>รายละเอียด:</strong><br>
                            ${data.description}
                        </div>
                    </div>
                ` : ''}
            `;
            
            document.getElementById('viewContent').innerHTML = content;
            new bootstrap.Modal(document.getElementById('viewModal')).show();
        }
        
        function editEquipment(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_name').value = data.name;
            document.getElementById('edit_category_id').value = data.category_id || '';
            document.getElementById('edit_department_id').value = data.department_id || '';
            document.getElementById('edit_status').value = data.status;
            document.getElementById('edit_location').value = data.location || '';
            document.getElementById('edit_brand').value = data.brand || '';
            document.getElementById('edit_model').value = data.model || '';
            document.getElementById('edit_serial_number').value = data.serial_number || '';
            document.getElementById('edit_purchase_date').value = data.purchase_date || '';
            document.getElementById('edit_warranty_expire').value = data.warranty_expire || '';
            document.getElementById('edit_description').value = data.description || '';
            
            new bootstrap.Modal(document.getElementById('editModal')).show();
        }
        
        function confirmDelete(id, name) {
            Swal.fire({
                title: 'ยืนยันการลบ?',
                text: `คุณต้องการลบครุภัณฑ์ "${name}" ใช่หรือไม่?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'ใช่, ลบเลย!',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete_id').value = id;
                    document.getElementById('deleteForm').submit();
                }
            });
        }
        
        function getStatusBadgeJS(status) {
            const badges = {
                'normal': '<span class="badge bg-success">ปกติ</span>',
                'repairing': '<span class="badge bg-warning">กำลังซ่อม</span>',
                'damaged': '<span class="badge bg-danger">ชำรุด</span>',
                'retired': '<span class="badge bg-secondary">เลิกใช้งาน</span>'
            };
            return badges[status] || status;
        }
        
        function formatDate(dateString) {
            if (!dateString || dateString === '0000-00-00') return '-';
            
            const date = new Date(dateString);
            const day = date.getDate();
            const month = date.getMonth() + 1;
            const year = date.getFullYear() + 543;
            
            return `${day}/${month.toString().padStart(2, '0')}/${year}`;
        }
    </script>
</body>
</html>