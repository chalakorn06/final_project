<?php
session_start();
require_once 'api/connect.php';

// ตรวจสอบสิทธิ์ (เฉพาะ Admin)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: dashboard.php");
    exit;
}

// ตั้งค่า Filter วันที่ (เริ่มต้นเป็นเดือนปัจจุบัน)
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// 1. สรุปยอดขายรวมและภาษี
$sql_summary = "SELECT 
                    COUNT(order_id) as total_bills,
                    SUM(total_amount) as gross_sales
                FROM orders 
                WHERE DATE(order_date) BETWEEN ? AND ?";
$stmt = $conn->prepare($sql_summary);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();

$gross_sales = $summary['gross_sales'] ?? 0;
$net_sales = $gross_sales / 1.07; // ถอด VAT 7%
$total_vat = $gross_sales - $net_sales;

// 2. คำนวณกำไร (ดึงราคาทุน ณ ปัจจุบันจากตาราง products มาเทียบ)
$sql_profit = "SELECT 
                    SUM(oi.subtotal) as total_revenue,
                    SUM(p.cost_price * oi.quantity) as total_cost
               FROM order_items oi
               JOIN orders o ON oi.order_id = o.order_id
               JOIN products p ON oi.prod_id = p.prod_id
               WHERE DATE(o.order_date) BETWEEN ? AND ?";
$stmt_p = $conn->prepare($sql_profit);
$stmt_p->bind_param("ss", $start_date, $end_date);
$stmt_p->execute();
$profit_data = $stmt_p->get_result()->fetch_assoc();

$total_profit = $profit_data['total_revenue'] - $profit_data['total_cost'];

// 3. สินค้าขายดี 5 อันดับ
$sql_top = "SELECT p.prod_name, SUM(oi.quantity) as qty, SUM(oi.subtotal) as sales
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.order_id
            JOIN products p ON oi.prod_id = p.prod_id
            WHERE DATE(o.order_date) BETWEEN ? AND ?
            GROUP BY oi.prod_id ORDER BY qty DESC LIMIT 5";
$stmt_top = $conn->prepare($sql_top);
$stmt_top->bind_param("ss", $start_date, $end_date);
$stmt_top->execute();
$top_products = $stmt_top->get_result();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานการขาย - POS System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <style>body { font-family: 'Kanit', sans-serif; }</style>
</head>
<body class="bg-slate-50">
    <?php include 'sidebar.php'; ?>

    <main class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex flex-col md:flex-row justify-between items-end mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-black text-slate-800">Sales Report</h1>
                <p class="text-slate-500">สรุปผลประกอบการและรายงานภาษีขาย</p>
            </div>
            
            <form class="flex gap-2 bg-white p-2 rounded-2xl shadow-sm border border-slate-200">
                <input type="date" name="start_date" value="<?= $start_date ?>" class="px-3 py-2 border-none focus:ring-0 text-sm">
                <span class="self-center text-slate-300">to</span>
                <input type="date" name="end_date" value="<?= $end_date ?>" class="px-3 py-2 border-none focus:ring-0 text-sm">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-xl font-bold hover:bg-blue-700 transition">ตกลง</button>
            </form>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border-b-4 border-blue-500">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">ยอดขายรวม (Inc. VAT)</p>
                <h3 class="text-2xl font-black text-slate-800">฿<?= number_format($gross_sales, 2) ?></h3>
                <p class="text-xs text-slate-400 mt-1">จำนวน <?= number_format($summary['total_bills']) ?> รายการขาย</p>
            </div>

            <div class="bg-white p-6 rounded-[2rem] shadow-sm border-b-4 border-emerald-500">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">กำไรสุทธิเบื้องต้น</p>
                <h3 class="text-2xl font-black text-emerald-600">฿<?= number_format($total_profit, 2) ?></h3>
                <p class="text-xs text-slate-400 mt-1">Margin: <?= $gross_sales > 0 ? round(($total_profit/$gross_sales)*100, 1) : 0 ?>%</p>
            </div>

            <div class="bg-white p-6 rounded-[2rem] shadow-sm border-b-4 border-orange-500">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">ภาษีขาย (VAT 7%)</p>
                <h3 class="text-2xl font-black text-orange-600">฿<?= number_format($total_vat, 2) ?></h3>
                <p class="text-xs text-slate-400 mt-1">มูลค่าก่อนภาษี: ฿<?= number_format($net_sales, 2) ?></p>
            </div>

            <div class="bg-white p-6 rounded-[2rem] shadow-sm border-b-4 border-purple-500">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">ยอดขายเฉลี่ย/บิล</p>
                <h3 class="text-2xl font-black text-slate-800">฿<?= $summary['total_bills'] > 0 ? number_format($gross_sales/$summary['total_bills'], 2) : '0.00' ?></h3>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
                <div class="p-6 border-b border-slate-50 bg-slate-50/50 flex justify-between items-center">
                    <h4 class="font-bold text-slate-800 italic"><i class="fa fa-fire text-orange-500 mr-2"></i>สินค้าขายดี 5 อันดับ</h4>
                </div>
                <table class="w-full text-left">
                    <thead class="text-[10px] font-bold text-slate-400 uppercase tracking-widest bg-white">
                        <tr>
                            <th class="px-8 py-4">ชื่อสินค้า</th>
                            <th class="px-6 py-4 text-center">จำนวนที่ขาย</th>
                            <th class="px-8 py-4 text-right">ยอดขายรวม</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php while($row = $top_products->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-8 py-4 font-bold text-slate-700"><?= $row['prod_name'] ?></td>
                            <td class="px-6 py-4 text-center font-medium text-blue-600"><?= number_format($row['qty']) ?></td>
                            <td class="px-8 py-4 text-right font-black text-slate-800">฿<?= number_format($row['sales'], 2) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <div class="bg-slate-800 text-white p-8 rounded-[2.5rem] shadow-xl">
                <h4 class="text-lg font-bold mb-6 flex items-center gap-2">
                    <i class="fa fa-file-invoice"></i> สรุปภาษีขาย (ABB)
                </h4>
                <div class="space-y-4 text-sm">
                    <div class="flex justify-between border-b border-slate-700 pb-2">
                        <span class="text-slate-400">ยอดขายรวม (Gross)</span>
                        <span class="font-bold">฿<?= number_format($gross_sales, 2) ?></span>
                    </div>
                    <div class="flex justify-between border-b border-slate-700 pb-2">
                        <span class="text-slate-400">ฐานภาษี (Exclude VAT)</span>
                        <span class="font-bold">฿<?= number_format($net_sales, 2) ?></span>
                    </div>
                    <div class="flex justify-between text-orange-400 pt-2 text-lg">
                        <span class="font-bold">ภาษีมูลค่าเพิ่ม 7%</span>
                        <span class="font-black underline">฿<?= number_format($total_vat, 2) ?></span>
                    </div>
                </div>
                <div class="mt-10 p-4 bg-slate-700/50 rounded-2xl text-[10px] text-slate-400 leading-relaxed uppercase tracking-widest">
                    หมายเหตุ: ข้อมูลนี้เป็นการสรุปยอดเบื้องต้นเพื่อใช้ประกอบการทำบัญชี กำไรคำนวณจากราคาทุนปัจจุบันในฐานข้อมูล
                </div>
            </div>
        </div>
    </main>
</body>
</html>