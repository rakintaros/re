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
                $username = escape($conn, $_POST['username']);
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $fullname = escape($conn, $_POST['fullname']);
                $email = escape($conn, $_POST['email']);
                $phone = escape($conn, $_POST['phone']);
                $role = escape($conn, $_POST['role']);
                $department_id = (int)$_POST['department_id'];
                $maintenance_team_id = ($role == 'technician' && $_POST['maintenance_team_id']) ? (int)$_POST['maintenance_team_id'] : 'NULL';
                
                // ตรวจสอบ username ซ้ำ
                $check_sql = "SELECT id FROM users WHERE username = '$username'";
                $check_result = mysqli_query($conn, $check_sql);
                
                if (mysqli_num_rows($check_result) > 0) {
                    $_SESSION['error'] = "ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว";
                } else {
                    $sql = "INSERT INTO users (username, password, fullname, email, phone, role, department_id, maintenance_team_id) 
                            VALUES ('$username', '$password', '$fullname', '$email', '$phone', '$role', $department_id, 
                            " . ($maintenance_team_id != 'NULL' ? $maintenance_team_id : "NULL") . ")";
                    
                    if (mysqli_query($conn, $sql)) {
                        $_SESSION['success'] = "เพิ่มพนักงานสำเร็จ";
                    } else {
                        $_SESSION['error'] = "เกิดข้อผิดพลาด: " . mysqli_error($conn);
                    }
                }
                break;
                
            case 'edit':
                $id = (int)$_POST['id'];
                $fullname = escape($conn, $_POST['fullname']);
                $email = escape($conn, $_POST['email']);
                $phone = escape($conn, $_POST['phone']);
                $role = escape($conn, $_POST['role']);
                $department_id = (int)$_POST['department_id'];
                $maintenance_team_id = ($role == 'technician' && $_POST['maintenance_team_id']) ? (int)$_POST['maintenance_team_id'] : 'NULL';
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                $sql = "UPDATE users SET 
                        fullname = '$fullname', 
                        email = '$email', 
                        phone = '$phone', 
                        role = '$role', 
                        department_id = $department_id, 
                        maintenance_team_id = " . ($maintenance_team_id != 'NULL' ? $maintenance_team_id : "NULL") . ", 
                        is_active = $is_active 
                        WHERE id = $id";
                
                // ถ้ามีการเปลี่ยนรหัสผ่าน
                if (!empty($_POST['password'])) {
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $sql = "UPDATE users SET 
                            fullname = '$fullname', 
                            email = '$email', 
                            phone = '$phone', 
                            role = '$role', 
                            department_id = $department_id, 
                            maintenance_team_id = " . ($maintenance_team_id != 'NULL' ? $maintenance_team_id : "NULL") . ", 
                            is_active = $is_active,
                            password = '$password' 
                            WHERE id = $id";
                }
                
                if (mysqli_query($conn, $sql)) {
                    $_SESSION['success'] = "แก้ไขข้อมูลพนักงานสำเร็จ";
                } else {
                    $_SESSION['error'] = "เกิดข้อผิดพลาด: " . mysqli_error($conn);
                }
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                
                // ไม่ให้ลบตัวเอง
                if ($id == $_SESSION['user_id']) {
                    $_SESSION['error'] = "ไม่สามารถลบบัญชีตัวเองได้";
                } else {
                    $sql = "DELETE FROM users WHERE id = $id";
                    if (mysqli_query($conn, $sql)) {
                        $_SESSION['success'] = "ลบพนักงานสำเร็จ";
                    } else {
                        $_SESSION['error'] = "เกิดข้อผิดพลาด: " . mysqli_error($conn);
                    }
                }
                break;
        }
        
        header("Location: users.php");
        exit();
    }
}

// ดึงข้อมูลแผนก
$sql_departments = "SELECT * FROM departments ORDER BY name";
$result_departments = mysqli_query($conn, $sql_departments);

// ดึงข้อมูลทีมซ่อม
$sql_teams = "SELECT * FROM maintenance_teams ORDER BY name";
$result_teams = mysqli_query($conn, $sql_teams);

// ดึงข้อมูลพนักงาน
$sql = "SELECT u.*, d.name as department_name, m.name as team_name 
        FROM users u 
        LEFT JOIN departments d ON u.department_id = d.id 
        LEFT JOIN maintenance_teams m ON u.maintenance_team_id = m.id 
        ORDER BY u.created_at DESC";
$result = mysqli_query($conn, $sql);

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการพนักงาน - ระบบจัดการครุภัณฑ์</title>
    
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
        
        .role-badge {
            font-size: 0.75rem;
            padding: 4px 10px;
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
                <a href="equipment.php" class="sidebar-item">
                    <i class="fas fa-desktop me-3"></i> ครุภัณฑ์
                </a>
                <a href="departments.php" class="sidebar-item">
                    <i class="fas fa-building me-3"></i> แผนก
                </a>
                <a href="maintenance_teams.php" class="sidebar-item">
                    <i class="fas fa-users-cog me-3"></i> ทีมซ่อมบำรุง
                </a>
                <a href="users.php" class="sidebar-item active">
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
                    <i class="fas fa-users me-2"></i>
                    จัดการพนักงาน
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
                    <h5 class="mb-0">รายการพนักงาน</h5>
                    <button class="btn btn-orange" data-bs-toggle="modal" data-bs-target="#addModal">
                        <i class="fas fa-plus me-2"></i>เพิ่มพนักงาน
                    </button>
                </div>
                
                <div class="table-responsive">
                    <table id="usersTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>ชื่อผู้ใช้</th>
                                <th>ชื่อ-นามสกุล</th>
                                <th>แผนก</th>
                                <th>บทบาท</th>
                                <th>อีเมล</th>
                                <th>เบอร์โทร</th>
                                <th>สถานะ</th>
                                <th class="text-center">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td>
                                        <i class="fas fa-user-circle me-2" style="color: #ff6b35;"></i>
                                        <?php echo $row['username']; ?>
                                    </td>
                                    <td><?php echo $row['fullname']; ?></td>
                                    <td><?php echo $row['department_name'] ?: '-'; ?></td>
                                    <td>
                                        <?php 
                                        $role_badges = [
                                            'admin' => '<span class="badge bg-danger role-badge">ผู้ดูแลระบบ</span>',
                                            'user' => '<span class="badge bg-primary role-badge">ผู้ใช้งาน</span>',
                                            'technician' => '<span class="badge bg-warning role-badge">ช่างซ่อม</span>'
                                        ];
                                        echo $role_badges[$row['role']];
                                        
                                        if ($row['role'] == 'technician' && $row['team_name']) {
                                            echo '<br><small class="text-muted">' . $row['team_name'] . '</small>';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo $row['email'] ?: '-'; ?></td>
                                    <td><?php echo $row['phone'] ?: '-'; ?></td>
                                    <td>
                                        <?php if ($row['is_active']): ?>
                                            <span class="badge bg-success">ใช้งาน</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">ปิดใช้งาน</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-warning text-white" 
                                                onclick="editUser(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($row['id'] != $_SESSION['user_id']): ?>
                                            <button class="btn btn-sm btn-danger" 
                                                    onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo $row['fullname']; ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
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
                        เพิ่มพนักงาน
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="add_username" class="form-label">ชื่อผู้ใช้ <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="add_username" name="username" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="add_password" class="form-label">รหัสผ่าน <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="add_password" name="password" required>
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <label for="add_fullname" class="form-label">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="add_fullname" name="fullname" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="add_email" class="form-label">อีเมล</label>
                                <input type="email" class="form-control" id="add_email" name="email">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="add_phone" class="form-label">เบอร์โทร</label>
                                <input type="tel" class="form-control" id="add_phone" name="phone">
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
                                <label for="add_role" class="form-label">บทบาท <span class="text-danger">*</span></label>
                                <select class="form-select" id="add_role" name="role" required onchange="toggleTeamSelect('add')">
                                    <option value="">เลือกบทบาท</option>
                                    <option value="admin">ผู้ดูแลระบบ</option>
                                    <option value="user">ผู้ใช้งาน</option>
                                    <option value="technician">ช่างซ่อม</option>
                                </select>
                            </div>
                            
                            <div class="col-md-12 mb-3" id="add_team_group" style="display: none;">
                                <label for="add_maintenance_team_id" class="form-label">ทีมซ่อมบำรุง <span class="text-danger">*</span></label>
                                <select class="form-select" id="add_maintenance_team_id" name="maintenance_team_id">
                                    <option value="">เลือกทีม</option>
                                    <?php 
                                    mysqli_data_seek($result_teams, 0);
                                    while ($team = mysqli_fetch_assoc($result_teams)): 
                                    ?>
                                        <option value="<?php echo $team['id']; ?>"><?php echo $team['name']; ?></option>
                                    <?php endwhile; ?>
                                </select>
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
                        แก้ไขข้อมูลพนักงาน
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_id">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_username" class="form-label">ชื่อผู้ใช้</label>
                                <input type="text" class="form-control" id="edit_username" disabled>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="edit_password" class="form-label">รหัสผ่านใหม่ (ถ้าต้องการเปลี่ยน)</label>
                                <input type="password" class="form-control" id="edit_password" name="password">
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <label for="edit_fullname" class="form-label">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_fullname" name="fullname" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="edit_email" class="form-label">อีเมล</label>
                                <input type="email" class="form-control" id="edit_email" name="email">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="edit_phone" class="form-label">เบอร์โทร</label>
                                <input type="tel" class="form-control" id="edit_phone" name="phone">
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
                                <label for="edit_role" class="form-label">บทบาท <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_role" name="role" required onchange="toggleTeamSelect('edit')">
                                    <option value="">เลือกบทบาท</option>
                                    <option value="admin">ผู้ดูแลระบบ</option>
                                    <option value="user">ผู้ใช้งาน</option>
                                    <option value="technician">ช่างซ่อม</option>
                                </select>
                            </div>
                            
                            <div class="col-md-12 mb-3" id="edit_team_group" style="display: none;">
                                <label for="edit_maintenance_team_id" class="form-label">ทีมซ่อมบำรุง <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_maintenance_team_id" name="maintenance_team_id">
                                    <option value="">เลือกทีม</option>
                                    <?php 
                                    mysqli_data_seek($result_teams, 0);
                                    while ($team = mysqli_fetch_assoc($result_teams)): 
                                    ?>
                                        <option value="<?php echo $team['id']; ?>"><?php echo $team['name']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active" checked>
                                    <label class="form-check-label" for="edit_is_active">
                                        เปิดใช้งาน
                                    </label>
                                </div>
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
            $('#usersTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json'
                },
                pageLength: 25,
                order: [[0, 'asc']]
            });
        });
        
        function toggleTeamSelect(prefix) {
            const role = document.getElementById(prefix + '_role').value;
            const teamGroup = document.getElementById(prefix + '_team_group');
            const teamSelect = document.getElementById(prefix + '_maintenance_team_id');
            
            if (role === 'technician') {
                teamGroup.style.display = 'block';
                teamSelect.required = true;
            } else {
                teamGroup.style.display = 'none';
                teamSelect.required = false;
                teamSelect.value = '';
            }
        }
        
        function editUser(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_username').value = data.username;
            document.getElementById('edit_fullname').value = data.fullname;
            document.getElementById('edit_email').value = data.email || '';
            document.getElementById('edit_phone').value = data.phone || '';
            document.getElementById('edit_department_id').value = data.department_id || '';
            document.getElementById('edit_role').value = data.role;
            document.getElementById('edit_is_active').checked = data.is_active == 1;
            
            if (data.role === 'technician') {
                document.getElementById('edit_team_group').style.display = 'block';
                document.getElementById('edit_maintenance_team_id').value = data.maintenance_team_id || '';
                document.getElementById('edit_maintenance_team_id').required = true;
            } else {
                document.getElementById('edit_team_group').style.display = 'none';
                document.getElementById('edit_maintenance_team_id').required = false;
            }
            
            new bootstrap.Modal(document.getElementById('editModal')).show();
        }
        
        function confirmDelete(id, name) {
            Swal.fire({
                title: 'ยืนยันการลบ?',
                text: `คุณต้องการลบพนักงาน "${name}" ใช่หรือไม่?`,
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
    </script>
</body>
</html>