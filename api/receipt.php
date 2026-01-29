<?php
session_start();
require_once 'connect.php';

if (!isset($_GET['order_id'])) exit('No Order ID');
$order_id = intval($_GET['order_id']);

// ดึงข้อมูลออเดอร์
$sql_order = "SELECT o.*, u.username FROM orders o LEFT JOIN users u ON o.user_id = u.user_id WHERE o.order_id = ?";
$stmt = $conn->prepare($sql_order);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) exit('Order not found');

// ดึงรายการสินค้า
$sql_items = "SELECT oi.*, p.prod_name, p.prod_code FROM order_items oi LEFT JOIN products p ON oi.prod_id = p.prod_id WHERE oi.order_id = ?";
$stmt_items = $conn->prepare($sql_items);
$stmt_items->bind_param("i", $order_id);
$stmt_items->execute();
$items = $stmt_items->get_result();

// สร้างชื่อไฟล์สำหรับตั้งเป็น Title (เวลา save pdf จะได้ชื่อนี้)
$bill_no = str_pad($order['order_id'], 6, '0', STR_PAD_LEFT);
$file_name = "Bill_" . $bill_no; 
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $file_name ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <style>
        body { 
            font-family: 'Sarabun', sans-serif; 
            background: #e5e7eb; 
            font-size: 14px;
            color: #1f2937;
        }
        .receipt-container {
            width: 100%;
            max-width: 80mm;
            margin: 0 auto;
            background: #fff;
            padding: 15px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        .dashed-line {
            border-bottom: 1.5px dashed #cbd5e1;
            margin: 10px 0;
            width: 100%;
        }
        .double-line {
            border-bottom: 3px double #cbd5e1;
            margin: 10px 0;
            width: 100%;
        }
        
        @media print {
            body { 
                background: none; 
                margin: 0; 
                padding: 0;
            }
            .receipt-container {
                width: 100%;
                max-width: 100%;
                box-shadow: none;
                padding: 0;
                margin: 0;
            }
            .no-print { display: none !important; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
    </style>
</head>
<body class="py-4 md:py-8">
    
    <div class="receipt-container rounded-none md:rounded-lg">
        
        <div class="text-center mb-4">
            <div class="flex items-center justify-center gap-2 mb-2">
                <i class="fa-solid fa-store text-2xl text-slate-800"></i>
                <h1 class="text-xl font-bold tracking-tight text-slate-900">POS SYSTEM</h1>
            </div>
            <p class="text-xs text-slate-500 font-medium">วิทยาลัยเทคนิคสัตหีบ</p>
            <p class="text-xs text-slate-500">โทร. 02-123-4567</p>
            <p class="text-[10px] text-slate-400 mt-1">TAX ID: 1234567890123</p>
        </div>

        <div class="double-line"></div>

        <div class="flex justify-between items-end text-[11px] text-slate-500 mb-2">
            <div class="text-left">
                <p>ใบเสร็จรับเงิน (ABB)</p>
                <p>เลขที่: <span class="font-bold text-slate-900 text-sm">#<?= $bill_no ?></span></p>
            </div>
            <div class="text-right">
                <p>วันที่: <?= date('d/m/Y', strtotime($order['order_date'])) ?></p>
                <p>เวลา: <?= date('H:i', strtotime($order['order_date'])) ?></p>
            </div>
        </div>
        <div class="text-[11px] text-slate-500 text-right mb-2">
            พนักงานขาย: <span class="font-medium text-slate-700"><?= htmlspecialchars($order['username']) ?></span>
        </div>

        <div class="dashed-line"></div>

        <table class="w-full text-xs mb-2 border-collapse">
            <thead>
                <tr class="text-slate-400 font-normal text-[10px] uppercase tracking-wider">
                    <th class="text-left py-1 w-[55%]">รายการ</th>
                    <th class="text-center py-1 w-[15%]">ราคา</th>
                    <th class="text-center py-1 w-[10%]">Qty</th>
                    <th class="text-right py-1 w-[20%]">รวม</th>
                </tr>
            </thead>
            <tbody class="text-slate-700 font-medium">
                <?php 
                $total_qty = 0; 
                while($item = $items->fetch_assoc()): 
                    $total_qty += $item['quantity'];
                ?>
                <tr>
                    <td class="py-1.5 align-top pr-1">
                        <div class="leading-tight"><?= htmlspecialchars($item['prod_name']) ?></div>
                        <div class="text-[9px] text-slate-400 font-light"><?= $item['prod_code'] ?></div>
                    </td>
                    <td class="py-1.5 text-center align-top text-slate-500"><?= number_format($item['unit_price'], 2) ?></td>
                    <td class="py-1.5 text-center align-top"><?= $item['quantity'] ?></td>
                    <td class="py-1.5 text-right align-top font-bold text-slate-800"><?= number_format($item['subtotal'], 2) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <div class="dashed-line"></div>

        <div class="space-y-1 text-xs">
            <div class="flex justify-between text-slate-500">
                <span>จำนวนชิ้นรวม</span>
                <span class="font-medium"><?= $total_qty ?> ชิ้น</span>
            </div>
            
            <?php 
                $subtotal = ($order['total_amount'] * 100) / 107;
                $vat = $order['total_amount'] - $subtotal;
            ?>
            <div class="flex justify-between text-slate-500 text-[10px]">
                <span>มูลค่าสินค้า (Pre-VAT)</span>
                <span><?= number_format($subtotal, 2) ?></span>
            </div>
            <div class="flex justify-between text-slate-500 text-[10px] mb-2">
                <span>ภาษีมูลค่าเพิ่ม 7%</span>
                <span><?= number_format($vat, 2) ?></span>
            </div>

            <div class="double-line"></div>

            <div class="flex justify-between items-center py-1">
                <span class="font-bold text-slate-800 text-sm">ยอดสุทธิ (Total)</span>
                <span class="font-black text-2xl text-slate-900">฿<?= number_format($order['total_amount'], 2) ?></span>
            </div>

            <div class="dashed-line"></div>

            <div class="bg-slate-50 rounded p-2 mt-2 space-y-1.5 border border-slate-100">
                <div class="flex justify-between text-slate-600 font-medium">
                    <span>ชำระโดย (<?= $order['payment_method'] ?>)</span>
                    <span><?= number_format($order['cash_received'], 2) ?></span>
                </div>
                <div class="flex justify-between text-slate-600 font-bold">
                    <span>เงินทอน (Change)</span>
                    <span class="text-green-600 text-sm"><?= number_format($order['change_given'], 2) ?></span>
                </div>
            </div>
        </div>

        <div class="text-center mt-6 space-y-2">
            <p class="text-[10px] font-bold text-slate-600 mt-2">ขอบคุณที่ใช้บริการ</p>
            <p class="text-[9px] text-slate-400">กรุณาตรวจสอบรายการสินค้าและเงินทอนทันที</p>
        </div>

        <div class="mt-6 no-print">
            <button onclick="window.print()" class="w-full bg-slate-900 text-white py-3 rounded-lg font-bold text-sm shadow-lg hover:bg-black transition flex items-center justify-center gap-2 group">
                <i class="fa-solid fa-print group-hover:scale-110 transition-transform"></i> พิมพ์ใบเสร็จ
            </button>
            <p class="text-[10px] text-center text-slate-400 mt-2">
                <i class="fa-solid fa-circle-info mr-1"></i>ใบเสร็จจะถูกบันทึกชื่อว่า <span class="font-mono bg-slate-100 px-1 rounded"><?= $file_name ?>.pdf</span>
            </p>
        </div>
    </div>

</body>
</html>