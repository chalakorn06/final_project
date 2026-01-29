<?php
session_start();
require_once 'api/connect.php';

// 1. ตรวจสอบสิทธิ์ (เฉพาะ Admin หรือ HR)
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'HR')) {
    header("Location: dashboard.php");
    exit;
}

// --- การจัดการ Filter ---
$f_cat = $_GET['cat_id'] ?? '';
$search = $_GET['search'] ?? '';

$where = ["1=1"];
if ($f_cat) $where[] = "p.cat_id = " . intval($f_cat);
if ($search) $where[] = "(p.prod_name LIKE '%$search%' OR p.prod_code LIKE '%$search%')";
$where_sql = implode(" AND ", $where);

// --- 2. ข้อมูลสรุปเชิงลึก (Advanced Summary) ---
$summary = $conn->query("
    SELECT 
        COUNT(*) as total_types,
        SUM(stock_qty) as total_units,
        SUM(price * stock_qty) as total_value,
        AVG(price) as avg_price,
        SUM(CASE WHEN stock_qty = 0 THEN 1 ELSE 0 END) as out_of_stock,
        SUM(CASE WHEN stock_qty > 0 AND stock_qty <= 5 THEN 1 ELSE 0 END) as low_stock
    FROM products p WHERE $where_sql
")->fetch_assoc();

// --- 3. ข้อมูลสำหรับกราฟสัดส่วนสต๊อก (Stock Health) ---
$healthy_count = $summary['total_types'] - $summary['out_of_stock'] - $summary['low_stock'];

// --- 4. ข้อมูลกราฟแยกตามหมวดหมู่ (Category Analysis) ---
$cat_stats = $conn->query("
    SELECT c.cat_name, COUNT(p.prod_id) as p_count, SUM(p.price * p.stock_qty) as p_val
    FROM categories c
    LEFT JOIN products p ON c.cat_id = p.cat_id
    GROUP BY c.cat_id ORDER BY p_val DESC
");

$cat_labels = []; $cat_counts = []; $cat_vals = [];
while($r = $cat_stats->fetch_assoc()){
    $cat_labels[] = $r['cat_name'] ?: 'ทั่วไป';
    $cat_counts[] = (int)$r['p_count'];
    $cat_vals[] = (float)$r['p_val'];
}

$all_cats = $conn->query("SELECT * FROM categories ORDER BY cat_name ASC");
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - วิเคราะห์ข้อมูลเชิงลึก</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Kanit', sans-serif; background-color: #f8fafc; }
        .chart-container { position: relative; height: 200px; width: 100%; }
    </style>
</head>
<body class="min-h-screen">
    <?php include 'sidebar.php'; ?>

    <main class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-black text-slate-800 tracking-tight">ProductAnalysis</h1>
                <p class="text-slate-500">วิเคราะห์ประสิทธิภาพและสุขภาพของสต๊อกสินค้า</p>
            </div>
            
            <form class="flex flex-wrap gap-2">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="ค้นหาชื่อ/รหัส..." 
                       class="px-4 py-2 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <select name="cat_id" class="px-4 py-2 rounded-xl border border-slate-200 text-sm outline-none">
                    <option value="">ทุกหมวดหมู่</option>
                    <?php $all_cats->data_seek(0); while($c = $all_cats->fetch_assoc()): ?>
                        <option value="<?= $c['cat_id'] ?>" <?= $f_cat == $c['cat_id'] ? 'selected' : '' ?>><?= $c['cat_name'] ?></option>
                    <?php endwhile; ?>
                </select>
                <button type="submit" class="bg-slate-800 text-white px-5 py-2 rounded-xl hover:bg-black transition text-sm font-bold">กรองข้อมูล</button>
            </form>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 group hover:border-blue-500 transition-all">
                <div class="flex justify-between items-start mb-4">
                    <div class="p-3 bg-blue-50 text-blue-600 rounded-2xl group-hover:bg-blue-600 group-hover:text-white transition-colors">
                        <i class="fa fa-boxes-stacked text-xl"></i>
                    </div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">Total Value</span>
                </div>
                <h3 class="text-2xl font-black text-slate-800">฿<?= number_format($summary['total_value'] ?? 0, 2) ?></h3>
                <p class="text-xs text-slate-400 mt-1">มูลค่าสินค้ารวมในระบบ</p>
            </div>

            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 group">
                <div class="flex justify-between items-start mb-4">
                    <div class="p-3 bg-orange-50 text-orange-600 rounded-2xl">
                        <i class="fa fa-triangle-exclamation text-xl"></i>
                    </div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">Stock Alert</span>
                </div>
                <h3 class="text-2xl font-black text-orange-600"><?= number_format($summary['low_stock'] ?? 0) ?></h3>
                <p class="text-xs text-slate-400 mt-1">รายการที่ควรเติมของ (≤ 5)</p>
            </div>

            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 group">
                <div class="flex justify-between items-start mb-4">
                    <div class="p-3 bg-red-50 text-red-600 rounded-2xl">
                        <i class="fa fa-circle-xmark text-xl"></i>
                    </div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">Out of Stock</span>
                </div>
                <h3 class="text-2xl font-black text-red-600"><?= number_format($summary['out_of_stock'] ?? 0) ?></h3>
                <p class="text-xs text-slate-400 mt-1">สินค้าที่หมดสต๊อกขณะนี้</p>
            </div>

            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 group">
                <div class="flex justify-between items-start mb-4">
                    <div class="p-3 bg-green-50 text-green-600 rounded-2xl">
                        <i class="fa fa-tag text-xl"></i>
                    </div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">Avg. Price</span>
                </div>
                <h3 class="text-2xl font-black text-slate-800">฿<?= number_format($summary['avg_price'] ?? 0, 2) ?></h3>
                <p class="text-xs text-slate-400 mt-1">ราคาสินค้าเฉลี่ยต่อหน่วย</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-[2.5rem] shadow-sm border border-slate-100">
                <h4 class="text-sm font-bold text-slate-800 mb-6">ความพร้อมของสต๊อก (Health)</h4>
                <div class="chart-container">
                    <canvas id="healthChart"></canvas>
                </div>
                <div class="mt-6 space-y-2">
                    <div class="flex justify-between text-xs font-medium">
                        <span class="text-green-500">● ปกติ</span><span><?= $healthy_count ?> รายการ</span>
                    </div>
                    <div class="flex justify-between text-xs font-medium">
                        <span class="text-orange-400">● ใกล้หมด</span><span><?= $summary['low_stock'] ?> รายการ</span>
                    </div>
                    <div class="flex justify-between text-xs font-medium">
                        <span class="text-red-500">● หมด</span><span><?= $summary['out_of_stock'] ?> รายการ</span>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2 bg-white p-6 rounded-[2.5rem] shadow-sm border border-slate-100">
                <h4 class="text-sm font-bold text-slate-800 mb-6">มูลค่าสินค้าแยกตามหมวดหมู่</h4>
                <div class="chart-container" style="height: 320px;">
                    <canvas id="catBarChart"></canvas>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-8 py-6 border-b border-slate-50 flex justify-between items-center bg-slate-50/50">
                <h4 class="font-bold text-slate-800">สินค้าวิกฤตที่ต้องจัดการ (High Value - Low Stock)</h4>
                <span class="px-3 py-1 bg-red-100 text-red-600 text-[10px] font-black rounded-full uppercase">Action Required</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-[10px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100">
                            <th class="px-8 py-4">สินค้า</th>
                            <th class="px-6 py-4">หมวดหมู่</th>
                            <th class="px-6 py-4 text-right">ราคา</th>
                            <th class="px-6 py-4 text-center">คงเหลือ</th>
                            <th class="px-8 py-4 text-right">มูลค่ารวม</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php 
                        $critical = $conn->query("
                            SELECT p.*, c.cat_name 
                            FROM products p 
                            LEFT JOIN categories c ON p.cat_id = c.cat_id 
                            WHERE p.stock_qty <= 5 
                            ORDER BY (p.price * p.stock_qty) DESC LIMIT 5
                        ");
                        while($row = $critical->fetch_assoc()): 
                        ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-8 py-4 font-bold text-slate-700"><?= $row['prod_name'] ?></td>
                            <td class="px-6 py-4 text-xs text-slate-500"><?= $row['cat_name'] ?: 'ทั่วไป' ?></td>
                            <td class="px-6 py-4 text-right text-sm">฿<?= number_format($row['price'], 2) ?></td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-2 py-0.5 rounded-lg text-[10px] font-bold <?= $row['stock_qty'] == 0 ? 'bg-red-100 text-red-600' : 'bg-orange-100 text-orange-600' ?>">
                                    <?= $row['stock_qty'] ?>
                                </span>
                            </td>
                            <td class="px-8 py-4 text-right font-black text-blue-600">฿<?= number_format($row['price'] * $row['stock_qty'], 2) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        // 1. Stock Health Chart (Doughnut)
        new Chart(document.getElementById('healthChart'), {
            type: 'doughnut',
            data: {
                labels: ['ปกติ', 'ใกล้หมด', 'หมด'],
                datasets: [{
                    data: [<?= $healthy_count ?>, <?= $summary['low_stock'] ?>, <?= $summary['out_of_stock'] ?>],
                    backgroundColor: ['#10b981', '#f97316', '#ef4444'],
                    borderWidth: 0,
                    cutout: '80%'
                }]
            },
            options: { plugins: { legend: { display: false } }, maintainAspectRatio: false }
        });

        // 2. Category Bar Chart
        new Chart(document.getElementById('catBarChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($cat_labels) ?>,
                datasets: [
                    {
                        label: 'มูลค่าสต๊อก (บาท)',
                        data: <?= json_encode($cat_vals) ?>,
                        backgroundColor: '#3b82f6',
                        borderRadius: 8,
                        yAxisID: 'y'
                    },
                    {
                        label: 'จำนวนรายการ',
                        data: <?= json_encode($cat_counts) ?>,
                        type: 'line',
                        borderColor: '#94a3b8',
                        borderDash: [5, 5],
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: { position: 'left', grid: { display: false } },
                    y1: { position: 'right', grid: { display: false } }
                },
                plugins: { legend: { position: 'bottom' } }
            }
        });
    </script>
</body>
</html>