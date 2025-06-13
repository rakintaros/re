<?php
session_start();

// เก็บชื่อผู้ใช้ก่อน logout เพื่อแสดงข้อความ
$username = isset($_SESSION['fullname']) ? $_SESSION['fullname'] : '';

// ทำลาย session ทั้งหมด
$_SESSION = array();

// ลบ session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// ทำลาย session
session_destroy();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ออกจากระบบ - ระบบจัดการครุภัณฑ์</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Prompt Font -->
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Animated Background */
        .bg-animation {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: 0;
        }
        
        .bg-animation span {
            position: absolute;
            display: block;
            width: 20px;
            height: 20px;
            background: rgba(255, 255, 255, 0.1);
            animation: move 25s linear infinite;
            bottom: -150px;
        }
        
        .bg-animation span:nth-child(1) {
            left: 25%;
            width: 80px;
            height: 80px;
            animation-delay: 0s;
        }
        
        .bg-animation span:nth-child(2) {
            left: 10%;
            width: 20px;
            height: 20px;
            animation-delay: 2s;
            animation-duration: 12s;
        }
        
        .bg-animation span:nth-child(3) {
            left: 70%;
            width: 20px;
            height: 20px;
            animation-delay: 4s;
        }
        
        .bg-animation span:nth-child(4) {
            left: 40%;
            width: 60px;
            height: 60px;
            animation-delay: 0s;
            animation-duration: 18s;
        }
        
        .bg-animation span:nth-child(5) {
            left: 65%;
            width: 20px;
            height: 20px;
            animation-delay: 0s;
        }
        
        .bg-animation span:nth-child(6) {
            left: 75%;
            width: 110px;
            height: 110px;
            animation-delay: 3s;
        }
        
        .bg-animation span:nth-child(7) {
            left: 35%;
            width: 150px;
            height: 150px;
            animation-delay: 7s;
        }
        
        .bg-animation span:nth-child(8) {
            left: 50%;
            width: 25px;
            height: 25px;
            animation-delay: 15s;
            animation-duration: 45s;
        }
        
        .bg-animation span:nth-child(9) {
            left: 20%;
            width: 15px;
            height: 15px;
            animation-delay: 2s;
            animation-duration: 35s;
        }
        
        .bg-animation span:nth-child(10) {
            left: 85%;
            width: 150px;
            height: 150px;
            animation-delay: 0s;
            animation-duration: 11s;
        }
        
        @keyframes move {
            0% {
                transform: translateY(0) rotate(0deg);
                opacity: 1;
                border-radius: 0;
            }
            100% {
                transform: translateY(-1000px) rotate(720deg);
                opacity: 0;
                border-radius: 50%;
            }
        }
        
        /* Glass Morphism Card */
        .glass-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            padding: 40px;
            position: relative;
            z-index: 1;
            text-align: center;
            max-width: 500px;
            width: 90%;
        }
        
        .glass-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0) 100%);
            border-radius: 20px;
            pointer-events: none;
        }
        
        .success-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            position: relative;
            animation: bounceIn 0.6s ease-out;
        }
        
        @keyframes bounceIn {
            0% {
                transform: scale(0);
                opacity: 0;
            }
            50% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }
        
        .success-icon i {
            font-size: 50px;
            color: white;
        }
        
        .success-icon::after {
            content: '';
            position: absolute;
            width: 120px;
            height: 120px;
            border: 2px solid rgba(40, 167, 69, 0.3);
            border-radius: 50%;
            animation: ripple 1.5s ease-out infinite;
        }
        
        @keyframes ripple {
            0% {
                transform: scale(0.8);
                opacity: 1;
            }
            100% {
                transform: scale(1.2);
                opacity: 0;
            }
        }
        
        h2 {
            color: #333;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .text-muted {
            color: rgba(0, 0, 0, 0.6) !important;
        }
        
        .btn-orange {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 12px 30px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px 0 rgba(255, 107, 53, 0.4);
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-orange:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px 0 rgba(255, 107, 53, 0.6);
            color: white;
        }
        
        .countdown {
            font-size: 0.9rem;
            color: rgba(0, 0, 0, 0.5);
            margin-top: 20px;
        }
        
        .logout-info {
            background: rgba(255, 107, 53, 0.1);
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="bg-animation">
        <span></span>
        <span></span>
        <span></span>
        <span></span>
        <span></span>
        <span></span>
        <span></span>
        <span></span>
        <span></span>
        <span></span>
    </div>
    
    <div class="glass-card">
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>
        
        <h2>ออกจากระบบสำเร็จ</h2>
        
        <?php if ($username): ?>
            <p class="text-muted mb-4">ขอบคุณที่ใช้งาน, คุณ<?php echo htmlspecialchars($username); ?></p>
        <?php else: ?>
            <p class="text-muted mb-4">ขอบคุณที่ใช้งานระบบจัดการครุภัณฑ์</p>
        <?php endif; ?>
        
        <div class="logout-info">
            <p class="mb-2">
                <i class="fas fa-shield-alt me-2" style="color: #ff6b35;"></i>
                คุณได้ออกจากระบบอย่างปลอดภัย
            </p>
            <p class="mb-0">
                <i class="fas fa-info-circle me-2" style="color: #ff6b35;"></i>
                ข้อมูลการเข้าสู่ระบบของคุณถูกลบออกแล้ว
            </p>
        </div>
        
        <a href="login.php" class="btn btn-orange">
            <i class="fas fa-sign-in-alt me-2"></i>เข้าสู่ระบบอีกครั้ง
        </a>
        
        <div class="countdown">
            กำลังกลับไปหน้า Login ใน <span id="counter">5</span> วินาที...
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // นับถอยหลังและ redirect
        let timeLeft = 5;
        const counterElement = document.getElementById('counter');
        
        const countdown = setInterval(() => {
            timeLeft--;
            counterElement.textContent = timeLeft;
            
            if (timeLeft <= 0) {
                clearInterval(countdown);
                window.location.href = 'login.php';
            }
        }, 1000);
    </script>
</body>
</html>