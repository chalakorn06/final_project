<?php
session_start();
// ตั้งค่าเขตเวลาเป็นประเทศไทย (GMT+7)
date_default_timezone_set('Asia/Bangkok');

header('Content-Type: application/json');
require_once 'api/connect.php';
require_once 'api/config.php'; // [Refactor] เรียกใช้ค่า Config จากไฟล์กลาง

// 1. ตรวจสอบ Session
if (!isset($_SESSION['emp_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session หมดอายุ กรุณาเข้าสู่ระบบใหม่']);
    exit;
}

// --- ตั้งค่าระบบ (Configuration) ---
// $check_in_deadline และ $work_zone ถูกโหลดมาจาก api/config.php แล้ว

$emp_id = $_SESSION['emp_id'];
$type   = $_POST['type'] ?? ''; // 'In' หรือ 'Out'
$lat    = (float)($_POST['lat'] ?? 0);
$lng    = (float)($_POST['lng'] ?? 0);
$img_data = $_POST['image'] ?? null;

/**
 * ฟังก์ชันตรวจสอบว่าพิกัดปัจจุบันอยู่ใน Polygon หรือไม่ (Ray Casting Algorithm)
 */
function isInsidePolygon($pointLat, $pointLng, $polygon) {
    $inside = false;
    $numPoints = count($polygon);
    $j = $numPoints - 1;
    
    for ($i = 0; $i < $numPoints; $i++) {
        if (
            ($polygon[$i][1] > $pointLng) != ($polygon[$j][1] > $pointLng) &&
            ($pointLat < ($polygon[$j][0] - $polygon[$i][0]) * ($pointLng - $polygon[$i][1]) / ($polygon[$j][1] - $polygon[$i][1]) + $polygon[$i][0])
        ) {
            $inside = !$inside;
        }
        $j = $i;
    }
    return $inside;
}

// 2. ประมวลผลข้อมูล
$is_inside = isInsidePolygon($lat, $lng, $work_zone); // ใช้ตัวแปร $work_zone จาก config.php
$work_date = date('Y-m-d');
$now_time = date('H:i:s');
$now_datetime = date('Y-m-d H:i:s');
$ip = $_SERVER['REMOTE_ADDR'];
$device = $_SERVER['HTTP_USER_AGENT'];

// 3. จัดการรูปภาพ (แยกโฟลเดอร์ตามรหัสพนักงาน)
$img_path = null;
if ($img_data) {
    // กำหนดชื่อไฟล์
    $img_name = "att_" . date('Ymd_His') . ".jpg";
    
    // สร้างเส้นทางโฟลเดอร์แยกตาม emp_id
    $folder = "uploads/" . $emp_id . "/";
    
    // ตรวจสอบและสร้างโฟลเดอร์
    if (!file_exists($folder)) {
        mkdir($folder, 0777, true);
    }
    
    $parts = explode(',', $img_data);
    if (count($parts) > 1) {
        file_put_contents($folder . $img_name, base64_decode($parts[1]));
        $img_path = $folder . $img_name;
    }
}

try {
    if ($type === 'In') {
        // เช็คการลงเวลาซ้ำ
        $check = $conn->prepare("SELECT att_id FROM attendance WHERE emp_id = ? AND work_date = ?");
        $check->bind_param("is", $emp_id, $work_date);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => 'วันนี้คุณได้ลงเวลาเข้างานไปแล้ว']);
            exit;
        }

        // ตรวจสอบเงื่อนไขพื้นที่และเวลา
        $late_min = 0;
        if (!$is_inside) {
            $status = 'Absent'; // อยู่นอกพื้นที่ = ขาดงาน
            $msg = "บันทึก: ขาดงาน (Absent) เนื่องจากคุณอยู่นอกพื้นที่ที่กำหนด";
        } else {
            // อยู่ในพื้นที่ -> เช็คเวลา
            if (strtotime($now_time) > strtotime($check_in_deadline)) { // ใช้ $check_in_deadline จาก config.php
                $status = 'Late'; // มาสาย
                $late_sec = strtotime($now_time) - strtotime($check_in_deadline);
                $late_min = ceil($late_sec / 60);
                $msg = "บันทึก: เข้างานสาย ($late_min นาที)";
            } else {
                $status = 'Present'; // มาปกติ
                $msg = "บันทึก: เข้างานตรงเวลา (ปกติ)";
            }
        }

        // บันทึกข้อมูลเข้างาน
        $stmt = $conn->prepare("INSERT INTO attendance (emp_id, work_date, clock_in, lat_in, long_in, img_in_path, status, late_minutes, ip_address, device_info) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issddssiss", $emp_id, $work_date, $now_datetime, $lat, $lng, $img_path, $status, $late_min, $ip, $device);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => $msg]);
        }

    } else if ($type === 'Out') {
        // ลงเวลาออก
        $check = $conn->prepare("SELECT att_id, clock_in FROM attendance WHERE emp_id = ? AND work_date = ?");
        $check->bind_param("is", $emp_id, $work_date);
        $check->execute();
        $res = $check->get_result();
        
        if ($res->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลการเข้างานวันนี้ กรุณาเข้างานก่อนลงเวลาออก']);
            exit;
        }
        
        $row = $res->fetch_assoc();
        $start = new DateTime($row['clock_in']);
        $end = new DateTime($now_datetime);
        $diff = $start->diff($end);
        $hours = round($diff->h + ($diff->i / 60), 2);

        $stmt = $conn->prepare("UPDATE attendance SET clock_out = ?, lat_out = ?, long_out = ?, work_hours = ? WHERE att_id = ?");
        $stmt->bind_param("sdddi", $now_datetime, $lat, $lng, $hours, $row['att_id']);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => "ลงเวลาออกสำเร็จ (รวมเวลาทำงานวันนี้: $hours ชม.)"]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ประเภทการทำรายการไม่ถูกต้อง']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}

$conn->close();
?>