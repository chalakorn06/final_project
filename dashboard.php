<?php
session_start();
require_once 'api/connect.php';
require_once 'api/config.php'; // [Refactor] เรียกไฟล์ config เพิ่ม

// 1. ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id']) || !isset($_SESSION['emp_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$emp_id = $_SESSION['emp_id'];

// 2. ดึงข้อมูลพนักงานและแผนก
$stmt_emp = $conn->prepare("
    SELECT e.*, d.dept_name 
    FROM employees e 
    LEFT JOIN departments d ON e.dept_id = d.dept_id 
    WHERE e.emp_id = ?
");
$stmt_emp->bind_param("i", $emp_id);
$stmt_emp->execute();
$employee = $stmt_emp->get_result()->fetch_assoc();

// 3. ดึงข้อมูลการลงเวลาของวันนี้
$today = date('Y-m-d');
$stmt_today = $conn->prepare("SELECT * FROM attendance WHERE emp_id = ? AND work_date = ?");
$stmt_today->bind_param("is", $emp_id, $today);
$stmt_today->execute();
$attendance_today = $stmt_today->get_result()->fetch_assoc();

// 4. คำนวณสถิติของเดือนนี้
$first_day_month = date('Y-m-01');
$last_day_month = date('Y-m-t');
$stmt_stats = $conn->prepare("
    SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_count,
        SUM(CASE WHEN status = 'Leave' THEN 1 ELSE 0 END) as leave_count,
        SUM(work_hours) as total_hours
    FROM attendance 
    WHERE emp_id = ? AND work_date BETWEEN ? AND ?
");
$stmt_stats->bind_param("iss", $emp_id, $first_day_month, $last_day_month);
$stmt_stats->execute();
$stats = $stmt_stats->get_result()->fetch_assoc();

// 5. ระบบ Filter ประวัติการลงเวลา
$filter_start = $_GET['start_date'] ?? '';
$filter_end = $_GET['end_date'] ?? '';
$filter_status = $_GET['status_filter'] ?? '';

$where_clauses = ["emp_id = ?"];
$params = [$emp_id];
$types = "i";

if ($filter_start) {
    $where_clauses[] = "work_date >= ?";
    $params[] = $filter_start;
    $types .= "s";
}
if ($filter_end) {
    $where_clauses[] = "work_date <= ?";
    $params[] = $filter_end;
    $types .= "s";
}
if ($filter_status) {
    $where_clauses[] = "status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

$where_sql = implode(" AND ", $where_clauses);
$sql_history = "SELECT * FROM attendance WHERE $where_sql ORDER BY work_date DESC, clock_in DESC LIMIT 50";
$stmt_history = $conn->prepare($sql_history);
$stmt_history->bind_param($types, ...$params);
$stmt_history->execute();
$history = $stmt_history->get_result();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - พนักงาน</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Kanit', 'sans-serif'] },
                    colors: { brand: { 50: '#eff6ff', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8' } }
                }
            }
        }
    </script>
    <style>
        .modal-active { overflow: hidden; }
        #attendanceModal, #mapModal { transition: opacity 0.3s ease-out; }
        .clock-bg {
            background: linear-gradient(135deg, #ffffff 0%, #f1f5f9 100%);
        }
        #historyMap { height: 400px; width: 100%; border-radius: 1.5rem; z-index: 10; }
        
        /* สไตล์ไอคอนหมุดแบบวงกลมเพื่อให้ตรงกับ Legend */
        .map-marker-in {
            background-color: #2563eb;
            border: 2px solid white;
            border-radius: 50%;
            box-shadow: 0 0 5px rgba(0,0,0,0.3);
        }
        .map-marker-out {
            background-color: #f97316;
            border: 2px solid white;
            border-radius: 50%;
            box-shadow: 0 0 5px rgba(0,0,0,0.3);
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen font-sans">
<?php include 'sidebar.php'; ?>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <div class="flex flex-col lg:flex-row lg:items-center justify-between mb-8 gap-6">
            <div class="flex-1">
                <h1 class="text-2xl font-bold text-slate-800 mb-1">สวัสดีครับ, <?= htmlspecialchars($employee['first_name']) ?> 👋</h1>
                <p class="text-slate-500 text-sm">ยินดีต้อนรับกลับเข้าสู่ระบบจัดการเวลาทำงาน</p>
                <div class="mt-3 flex items-center space-x-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $attendance_today ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' ?>">
                        <span class="w-1.5 h-1.5 mr-1.5 rounded-full <?= $attendance_today ? 'bg-green-500 animate-pulse' : 'bg-amber-500 animate-bounce' ?>"></span>
                        <?= $attendance_today ? 'บันทึกเวลาเข้างานแล้ว' : 'ยังไม่ได้ลงเวลาเข้างานของวันนี้' ?>
                    </span>
                    <?php if($attendance_today): ?>
                        <span class="text-[11px] text-slate-400 font-medium">เข้างานเมื่อ: <?= date('H:i', strtotime($attendance_today['clock_in'])) ?> น.</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="flex items-center space-x-4 clock-bg p-4 pr-6 rounded-[2rem] border border-slate-100 shadow-sm min-w-[280px]">
                <div class="w-12 h-12 bg-brand-600 text-white rounded-2xl flex items-center justify-center shadow-lg shadow-blue-200">
                    <i class="fa-solid fa-clock text-xl"></i>
                </div>
                <div>
                    <p id="live-clock" class="text-3xl font-black text-brand-600 tabular-nums leading-none tracking-tighter">00:00:00</p>
                    <p id="live-date" class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">วันที่ปัจจุบัน</p>
                </div>
            </div>

            <button onclick="toggleModal(true)" class="bg-brand-600 text-white px-8 py-4 rounded-2xl font-bold hover:bg-brand-700 transition-all transform active:scale-95 shadow-xl shadow-blue-100 flex items-center justify-center group">
                <i class="fa-solid fa-camera mr-2 group-hover:rotate-12 transition-transform"></i> ลงเวลาเข้า-ออกงาน
            </button>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 hover:shadow-md transition-shadow group">
                <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-calendar-day"></i>
                </div>
                <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest">มาทำงาน</p>
                <p class="text-2xl font-black text-slate-800"><?= number_format($stats['total_days']) ?> <span class="text-sm font-normal text-slate-300">วัน</span></p>
            </div>

            <div class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 hover:shadow-md transition-shadow group">
                <div class="w-10 h-10 bg-amber-50 text-amber-600 rounded-2xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-user-clock"></i>
                </div>
                <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest">เข้างานสาย</p>
                <p class="text-2xl font-black text-slate-800"><?= number_format($stats['late_count']) ?> <span class="text-sm font-normal text-slate-300">ครั้ง</span></p>
            </div>

            <div class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 hover:shadow-md transition-shadow group">
                <div class="w-10 h-10 bg-purple-50 text-purple-600 rounded-2xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-envelope-open-text"></i>
                </div>
                <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest">การลา</p>
                <p class="text-2xl font-black text-slate-800"><?= number_format($stats['leave_count']) ?> <span class="text-sm font-normal text-slate-300">วัน</span></p>
            </div>

            <div class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 hover:shadow-md transition-shadow group">
                <div class="w-10 h-10 bg-green-50 text-green-600 rounded-2xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-stopwatch"></i>
                </div>
                <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest">เวลารวม</p>
                <p class="text-2xl font-black text-slate-800"><?= number_format($stats['total_hours'], 1) ?> <span class="text-sm font-normal text-slate-300">ชม.</span></p>
            </div>
        </div>

        <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 mb-8 overflow-hidden">
            <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                <div class="flex items-center space-x-2 text-slate-700 font-bold mb-4">
                    <i class="fa-solid fa-filter text-brand-500"></i>
                    <span>ตัวกรองประวัติการเข้างาน</span>
                </div>
                <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-slate-400 uppercase ml-1">ตั้งแต่วันที่</label>
                        <input type="date" name="start_date" value="<?= $filter_start ?>" 
                            class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-brand-500 outline-none transition">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-slate-400 uppercase ml-1">ถึงวันที่</label>
                        <input type="date" name="end_date" value="<?= $filter_end ?>" 
                            class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-brand-500 outline-none transition">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-slate-400 uppercase ml-1">สถานะ</label>
                        <select name="status_filter" 
                            class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-brand-500 outline-none transition appearance-none">
                            <option value="">ทั้งหมด</option>
                            <option value="Present" <?= $filter_status == 'Present' ? 'selected' : '' ?>>Present (ปกติ)</option>
                            <option value="Late" <?= $filter_status == 'Late' ? 'selected' : '' ?>>Late (สาย)</option>
                            <option value="Leave" <?= $filter_status == 'Leave' ? 'selected' : '' ?>>Leave (ลา)</option>
                            <option value="Absent" <?= $filter_status == 'Absent' ? 'selected' : '' ?>>Absent (ขาด)</option>
                        </select>
                    </div>
                    <div class="flex items-end space-x-2">
                        <button type="submit" class="flex-1 bg-brand-600 text-white font-bold py-2 rounded-xl hover:bg-brand-700 transition shadow-md text-sm">
                            <i class="fa-solid fa-magnifying-glass mr-1"></i> ค้นหา
                        </button>
                        <a href="dashboard.php" class="bg-slate-100 text-slate-500 font-bold py-2 px-4 rounded-xl hover:bg-slate-200 transition text-sm">
                            <i class="fa-solid fa-rotate-left"></i>
                        </a>
                    </div>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-slate-50/80 text-slate-400 text-[10px] uppercase font-bold tracking-widest border-b border-slate-100">
                            <th class="px-8 py-4">วันที่ทำงาน</th>
                            <th class="px-6 py-4">เวลาเข้า</th>
                            <th class="px-6 py-4">เวลาออก</th>
                            <th class="px-6 py-4 text-center">ชั่วโมง</th>
                            <th class="px-6 py-4 text-center">สาย (นาที)</th>
                            <th class="px-6 py-4 text-center">พิกัด</th>
                            <th class="px-8 py-4 text-right">สถานะ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php if($history->num_rows > 0): ?>
                            <?php while($row = $history->fetch_assoc()): ?>
                                <tr class="hover:bg-brand-50/30 transition-colors group">
                                    <td class="px-8 py-4">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-bold text-slate-700"><?= date('d M Y', strtotime($row['work_date'])) ?></span>
                                            <span class="text-[10px] text-slate-400"><?= date('l', strtotime($row['work_date'])) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium text-slate-600">
                                        <?= $row['clock_in'] ? date('H:i', strtotime($row['clock_in'])) : '--:--' ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium text-slate-600">
                                        <?= $row['clock_out'] ? date('H:i', strtotime($row['clock_out'])) : '--:--' ?>
                                    </td>
                                    <td class="px-6 py-4 text-center text-sm font-bold text-brand-600">
                                        <?= $row['work_hours'] ? $row['work_hours'] : '0.0' ?>
                                    </td>
                                    <td class="px-6 py-4 text-center text-sm font-medium <?= $row['late_minutes'] > 0 ? 'text-red-500' : 'text-slate-300' ?>">
                                        <?= $row['late_minutes'] > 0 ? $row['late_minutes'] : '-' ?>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <button onclick="showHistoryMap(<?= $row['lat_in'] ?? 'null' ?>, <?= $row['long_in'] ?? 'null' ?>, <?= $row['lat_out'] ?? 'null' ?>, <?= $row['long_out'] ?? 'null' ?>, '<?= date('d/m/Y', strtotime($row['work_date'])) ?>')" 
                                                class="text-brand-500 hover:text-brand-700 transition-colors p-2 bg-blue-50 rounded-lg">
                                            <i class="fa-solid fa-map-location-dot"></i>
                                        </button>
                                    </td>
                                    <td class="px-8 py-4 text-right">
                                        <?php 
                                            $st_class = "bg-slate-100 text-slate-500";
                                            if($row['status'] == 'Present') $st_class = "bg-green-100 text-green-700";
                                            if($row['status'] == 'Late') $st_class = "bg-amber-100 text-amber-700";
                                            if($row['status'] == 'Leave') $st_class = "bg-purple-100 text-purple-700";
                                            if($row['status'] == 'Absent') $st_class = "bg-red-100 text-red-700";
                                        ?>
                                        <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-tight <?= $st_class ?>">
                                            <?= $row['status'] ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="px-8 py-20 text-center">
                                    <div class="flex flex-col items-center justify-center opacity-30">
                                        <i class="fa-solid fa-box-open text-5xl mb-4"></i>
                                        <p class="font-bold">ไม่พบข้อมูลที่ตรงตามเงื่อนไข</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if(!$filter_start && !$filter_end && !$filter_status && $history->num_rows > 0): ?>
                <div class="p-4 bg-slate-50 text-center">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">แสดง 50 รายการล่าสุด ใช้ตัวกรองด้านบนเพื่อค้นหาข้อมูลเพิ่มเติม</p>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <div id="attendanceModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
        <div class="bg-white w-full max-w-xl rounded-[3rem] shadow-2xl overflow-hidden relative animate-in fade-in zoom-in duration-300">
            <div class="flex justify-between items-center px-10 py-6 border-b border-slate-100">
                <div class="flex items-center space-x-3">
                    <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                    <h3 class="font-bold text-slate-800 tracking-tight">ระบบบันทึกเวลาปฏิบัติงาน</h3>
                </div>
                <button onclick="toggleModal(false)" class="text-slate-300 hover:text-red-500 transition-colors">
                    <i class="fa-solid fa-circle-xmark text-2xl"></i>
                </button>
            </div>
            <div class="w-full h-[680px]">
                <iframe src="check_in_out_work.php" class="w-full h-full border-none" id="attendanceFrame"></iframe>
            </div>
        </div>
    </div>

    <div id="mapModal" class="fixed inset-0 z-[60] hidden flex items-center justify-center p-4 bg-slate-900/70 backdrop-blur-sm">
        <div class="bg-white w-full max-w-2xl rounded-[2.5rem] shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-300">
            <div class="px-8 py-5 border-b flex justify-between items-center">
                <div>
                    <h3 class="font-bold text-slate-800">จุดพิกัดลงเวลา</h3>
                    <p id="map-date-info" class="text-[10px] text-slate-400 font-bold uppercase tracking-widest"></p>
                </div>
                <button onclick="closeMapModal()" class="text-slate-300 hover:text-red-500 transition-colors">
                    <i class="fa-solid fa-circle-xmark text-2xl"></i>
                </button>
            </div>
            <div class="p-6">
                <div id="historyMap"></div>
                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div class="flex items-center space-x-3 p-3 bg-blue-50 rounded-2xl border border-blue-100">
                        <div class="w-3 h-3 bg-blue-600 rounded-full border border-white shadow-sm"></div>
                        <span class="text-xs font-bold text-blue-800">จุดเข้างาน (IN)</span>
                    </div>
                    <div class="flex items-center space-x-3 p-3 bg-orange-50 rounded-2xl border border-orange-100">
                        <div class="w-3 h-3 bg-orange-600 rounded-full border border-white shadow-sm"></div>
                        <span class="text-xs font-bold text-orange-800">จุดออกงาน (OUT)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        let map = null;
        let markers = [];
        let polyline = null;

        // [Refactor] ดึงค่าจาก PHP Config มาใส่ใน JavaScript
        const workZoneCoords = <?php echo json_encode($work_zone); ?>;

        // กำหนดไอคอนหมุดแบบแยกสี
        const blueIcon = L.divIcon({
            className: 'map-marker-in',
            iconSize: [16, 16],
            iconAnchor: [8, 8]
        });

        const orangeIcon = L.divIcon({
            className: 'map-marker-out',
            iconSize: [16, 16],
            iconAnchor: [8, 8]
        });

        // ฟังก์ชันนาฬิกา Real-time
        function updateClock() {
            const now = new Date();
            const timeOptions = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false, timeZone: 'Asia/Bangkok' };
            const dateOptions = { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'Asia/Bangkok' };
            
            const liveClock = document.getElementById('live-clock');
            const liveDate = document.getElementById('live-date');
            
            if(liveClock) liveClock.innerText = now.toLocaleTimeString('th-TH', timeOptions);
            if(liveDate) liveDate.innerText = now.toLocaleDateString('th-TH', dateOptions);
        }
        setInterval(updateClock, 1000);
        updateClock();

        function toggleModal(show) {
            const modal = document.getElementById('attendanceModal');
            const frame = document.getElementById('attendanceFrame');
            
            if (show) {
                modal.classList.remove('hidden');
                document.body.classList.add('modal-active');
                frame.src = frame.src; 
            } else {
                modal.classList.add('hidden');
                document.body.classList.remove('modal-active');
                if (!window.location.search) {
                    window.location.reload();
                }
            }
        }

        // ระบบแผนที่ประวัติ
        function showHistoryMap(latIn, lngIn, latOut, lngOut, dateStr) {
            const dateInfo = document.getElementById('map-date-info');
            if(dateInfo) dateInfo.innerText = "ประจำวันที่: " + dateStr;
            
            document.getElementById('mapModal').classList.remove('hidden');
            document.body.classList.add('modal-active');

            setTimeout(() => {
                if (!map) {
                    map = L.map('historyMap').setView([12.8098, 100.9185], 16);
                    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '© OpenStreetMap'
                    }).addTo(map);
                } else {
                    markers.forEach(m => map.removeLayer(m));
                    if(polyline) map.removeLayer(polyline);
                    markers = [];
                }

                L.polygon(workZoneCoords, {color: 'red', weight: 1, fillOpacity: 0.1}).addTo(map);

                const points = [];
                
                // ใช้หมุดสีน้ำเงินสำหรับจุดเข้างาน
                if (latIn && lngIn) {
                    const mIn = L.marker([latIn, lngIn], {icon: blueIcon}).addTo(map).bindPopup("<b>จุดเข้างาน</b><br>" + dateStr);
                    markers.push(mIn);
                    points.push([latIn, lngIn]);
                }

                // ใช้หมุดสีส้มสำหรับจุดออกงาน
                if (latOut && lngOut) {
                    const mOut = L.marker([latOut, lngOut], {icon: orangeIcon}).addTo(map).bindPopup("<b>จุดออกงาน</b><br>" + dateStr);
                    markers.push(mOut);
                    points.push([latOut, lngOut]);
                }

                if (points.length > 0) {
                    if(points.length === 2) {
                        polyline = L.polyline(points, {color: '#2563eb', weight: 2, dashArray: '5, 10'}).addTo(map);
                    }
                    map.fitBounds(L.latLngBounds(points).pad(0.5));
                } else {
                    map.setView([12.8098, 100.9185], 16);
                }
                
                map.invalidateSize();
            }, 300);
        }

        function closeMapModal() {
            document.getElementById('mapModal').classList.add('hidden');
            document.body.classList.remove('modal-active');
        }
    </script>
</body>
</html>