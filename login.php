<?php
session_start();
require_once 'api/connect.php'; // ปรับ path ให้ตรงกับไฟล์จริงของคุณ

$error_message = "";

// ตรวจสอบการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password)) {
        // 1. เตรียมคำสั่ง SQL (ป้องกัน SQL Injection)
        $sql = "SELECT user_id, emp_id, password_hash, role FROM users WHERE username = ? LIMIT 1";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $username); // "s" คือ string
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                // 2. ตรวจสอบรหัสผ่าน (ใช้ password_verify สำหรับ password_hash)
                if (password_verify($password, $user['password_hash'])) {
                    
                    // เข้าสู่ระบบสำเร็จ: บันทึกข้อมูลลง Session
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['emp_id'] = $user['emp_id'];
                    $_SESSION['username'] = $username;
                    $_SESSION['role'] = $user['role'];

                    // อัปเดตเวลา Last Login
                    $update_sql = "UPDATE users SET last_login = NOW() WHERE user_id = ?";
                    $upd_stmt = $conn->prepare($update_sql);
                    $upd_stmt->bind_param("i", $user['user_id']);
                    $upd_stmt->execute();

                    // เปลี่ยนเส้นทางไปหน้า Dashboard (สมมติว่าเป็น index.php)
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error_message = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
                }
            } else {
                $error_message = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
            }
            $stmt->close();
        }
    } else {
        $error_message = "กรุณากรอกข้อมูลให้ครบถ้วน";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Kanit', sans-serif; }
        .glass-effect-light {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
        }
        .animate-blob {
            animation: blob 7s infinite;
        }
        @keyframes blob {
            0% { transform: translate(0px, 0px) scale(1); }
            33% { transform: translate(30px, -50px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
            100% { transform: translate(0px, 0px) scale(1); }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 overflow-hidden">

    <!-- Background Decoration -->
    <div class="fixed inset-0 z-0 overflow-hidden">
        <div class="absolute top-[-10%] left-[-10%] w-96 h-96 bg-blue-200 rounded-full filter blur-3xl opacity-40 animate-blob"></div>
        <div class="absolute top-[-10%] right-[-10%] w-96 h-96 bg-cyan-200 rounded-full filter blur-3xl opacity-40 animate-blob" style="animation-delay: 2s;"></div>
        <div class="absolute bottom-[-20%] left-[20%] w-96 h-96 bg-indigo-200 rounded-full filter blur-3xl opacity-40 animate-blob" style="animation-delay: 4s;"></div>
    </div>

    <div class="relative z-10 min-h-screen flex items-center justify-center px-4">
        <div data-aos="zoom-in" data-aos-duration="1000" class="glass-effect-light w-full max-w-md p-8 rounded-2xl relative">
            
            <div class="text-center mb-8">
                <h2 class="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-900 to-blue-600">POS SYSTEM</h2>
                <p class="text-slate-500 text-sm mt-2">ยินดีต้อนรับเข้าสู่ระบบจัดการเวลาทำงาน</p>
            </div>

            <!-- Error Message Display -->
            <?php if ($error_message): ?>
                <div class="mb-4 p-3 bg-red-100 border-l-4 border-red-500 text-red-700 text-sm rounded">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="space-y-6">
                <div class="relative group">
                    <label class="block text-slate-700 text-sm font-medium mb-2 ml-1">ชื่อผู้ใช้</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-slate-400 group-focus-within:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <input type="text" name="username" required
                            class="w-full bg-white/60 border border-slate-200 text-slate-800 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 p-3 transition-all duration-300 hover:border-blue-300" 
                            placeholder="กรอกชื่อผู้ใช้">
                    </div>
                </div>

                <div class="relative group">
                    <label class="block text-slate-700 text-sm font-medium mb-2 ml-1">รหัสผ่าน</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-slate-400 group-focus-within:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <input type="password" name="password" required
                            class="w-full bg-white/60 border border-slate-200 text-slate-800 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 p-3 transition-all duration-300 hover:border-blue-300" 
                            placeholder="กรอกรหัสผ่าน">
                    </div>
                </div>

                <button type="submit" class="w-full text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 font-medium rounded-lg text-sm px-5 py-3 text-center shadow-lg transition transform hover:-translate-y-1 active:translate-y-0">
                    เข้าสู่ระบบ
                </button>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>AOS.init({ once: true, duration: 800 });</script>
</body>
</html>