<?php
session_start();
include 'api/connect.php';

// 1. ตรวจสอบ Session
if (!isset($_SESSION['user_id']) || !isset($_SESSION['emp_id'])) {
    echo "<!DOCTYPE html><html><head><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head><body>
    <script>
        Swal.fire({
            icon: 'warning',
            title: 'กรุณาเข้าสู่ระบบ',
            text: 'ไม่พบข้อมูลการล็อกอินของคุณในระบบ',
            confirmButtonColor: '#2563eb',
            background: '#ffffff'
        }).then(() => { window.location.href = 'login.php'; });
    </script></body></html>";
    exit;
}

$user_id = $_SESSION['user_id'];
$emp_id = $_SESSION['emp_id'];

// 2. ดึงข้อมูลพนักงาน
$stmt = $conn->prepare("
    SELECT e.first_name, e.last_name, e.emp_code, u.username, u.role 
    FROM employees e 
    JOIN users u ON e.emp_id = u.emp_id 
    WHERE u.user_id = ? AND e.emp_id = ?
");
$stmt->bind_param("ii", $user_id, $emp_id);
$stmt->execute();
$user_info = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลงเวลาทำงาน - <?php echo htmlspecialchars($user_info['username']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Kanit', sans-serif;
            background-color: #ffffff; /* พื้นหลังขาว */
        }
        #preview { transform: scaleX(-1); }
        .main-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.1), 0 8px 10px -6px rgba(37, 99, 235, 0.1);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full main-card rounded-[2.5rem] overflow-hidden relative">
        
        <!-- ส่วนหัว (Header) -->
        <div class="p-8 text-center">
            <div class="inline-flex items-center justify-center bg-blue-50 px-4 py-1 rounded-full text-[10px] font-bold tracking-widest uppercase mb-4 text-[#2563eb]">
                <span class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></span>
                Login: <?php echo htmlspecialchars($user_info['username']); ?>
            </div>
            <h1 class="text-4xl font-black text-[#2563eb] mb-2">ระบบลงเวลาเข้างาน</h1>
            <p id="current-time" class="text-slate-500 text-sm font-medium">รอการเชื่อมต่อนาฬิกา...</p>
        </div>

        <div class="px-8 pb-10 space-y-6">
            
            <!-- ข้อมูลพนักงาน (ตัวอักษรสีฟ้า) -->
            <div class="flex items-center space-x-4 p-5 bg-blue-50 rounded-3xl border border-blue-100">
                <div class="h-14 w-14 bg-[#2563eb] text-white rounded-2xl flex items-center justify-center shadow-md">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-8 h-8">
                        <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0 .75.75 0 0 1-.437.695A18.683 18.683 0 0 1 12 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 0 1-.437-.695Z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="text-[#2563eb]">
                    <h2 class="font-bold text-lg leading-tight">
                        <?php echo $user_info['first_name'] . " " . $user_info['last_name']; ?>
                    </h2>
                    <p class="text-[12px] font-semibold opacity-80">
                        รหัส: <?php echo $user_info['emp_code']; ?> • <?php echo $user_info['role']; ?>
                    </p>
                </div>
            </div>

            <!-- กล้อง (Preview) -->
            <div class="relative">
                <div class="bg-slate-100 rounded-[2rem] overflow-hidden aspect-[4/3] shadow-inner border border-slate-200">
                    <video id="preview" autoplay playsinline class="w-full h-full object-cover"></video>
                    <canvas id="capture" class="hidden"></canvas>
                </div>
                <div class="absolute top-4 left-4 bg-[#2563eb] text-white text-[10px] px-3 py-1 rounded-full flex items-center shadow-lg">
                    <span class="w-1.5 h-1.5 bg-white rounded-full mr-2 animate-pulse"></span> SYSTEM ONLINE
                </div>
            </div>

            <!-- พิกัด (ตัวอักษรสีฟ้า) -->
            <div id="geo-status" class="flex items-center justify-center space-x-2 text-[11px] font-bold text-[#2563eb] py-2 px-4 rounded-full bg-blue-50 border border-blue-100">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span id="geo-text">กำลังค้นหาสัญญาณ GPS...</span>
            </div>

            <!-- ปุ่มลงเวลา -->
            <div class="grid grid-cols-2 gap-4">
                <button onclick="submitAttendance('In')" class="bg-[#2563eb] hover:bg-blue-700 text-white py-5 rounded-[2rem] font-black transition-all shadow-lg active:scale-95 text-xs uppercase tracking-widest">
                    Check In
                </button>
                <button onclick="submitAttendance('Out')" class="bg-white hover:bg-slate-50 text-[#2563eb] border-2 border-[#2563eb] py-5 rounded-[2rem] font-black transition-all active:scale-95 text-xs uppercase tracking-widest">
                    Check Out
                </button>
            </div>
        </div>
    </div>

    <script>
        const video = document.getElementById('preview');
        const canvas = document.getElementById('capture');
        const geoText = document.getElementById('geo-text');
        let lat = null, lng = null;

        // ตั้งค่า SweetAlert2 พื้นฐานเป็นสีขาวและปุ่มสีฟ้า
        const Toast = Swal.mixin({
            background: '#ffffff',
            confirmButtonColor: '#2563eb',
            customClass: {
                popup: 'rounded-[2rem] shadow-2xl border border-slate-100',
                title: 'text-[#2563eb] font-bold',
                htmlContainer: 'text-slate-600'
            }
        });

        navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" } })
            .then(stream => { video.srcObject = stream; })
            .catch(err => { Toast.fire('ผิดพลาด', 'กรุณาอนุญาตให้ใช้งานกล้อง', 'error'); });

        if (navigator.geolocation) {
            navigator.geolocation.watchPosition(pos => {
                lat = pos.coords.latitude;
                lng = pos.coords.longitude;
                geoText.innerText = `พิกัดคงที่: ${lat.toFixed(5)}, ${lng.toFixed(5)}`;
            }, null, { enableHighAccuracy: true });
        }

        async function submitAttendance(type) {
            if (!lat || !lng) return Toast.fire('รอสักครู่', 'กำลังระบุตำแหน่งของคุณ...', 'info');

            const context = canvas.getContext('2d');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            const imageData = canvas.toDataURL('image/jpeg', 0.8);

            Toast.fire({
                title: 'กำลังบันทึก...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            const formData = new FormData();
            formData.append('type', type);
            formData.append('lat', lat);
            formData.append('lng', lng);
            formData.append('image', imageData);

            try {
                const resp = await fetch('save_attendance.php', { method: 'POST', body: formData });
                const result = await resp.json();
                
                if (result.status === 'success') {
                    Toast.fire({
                        icon: 'success',
                        title: 'สำเร็จ!',
                        text: result.message,
                        timer: 2500,
                        showConfirmButton: false
                    });
                } else {
                    Toast.fire('ล้มเหลว', result.message, 'error');
                }
            } catch (error) {
                Toast.fire('Error', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
            }
        }

        setInterval(() => {
            const now = new Date();
            document.getElementById('current-time').innerText = now.toLocaleString('th-TH');
        }, 1000);
    </script>
</body>
</html>