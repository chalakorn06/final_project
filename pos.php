<?php
session_start();
require_once 'api/connect.php';

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// --- ส่วนจัดการ Backend (AJAX Requests) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // จัดการการชำระเงิน (Checkout)
    if ($_POST['action'] === 'checkout') {
        $data = json_decode($_POST['order_data'], true);
        
        if (empty($data['items'])) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่มีสินค้าในตะกร้า']);
            exit;
        }

        $user_id = $_SESSION['user_id'];
        $total = floatval($data['total']);
        $cash = floatval($data['cash']);
        $change = floatval($data['change']);
        $method = $data['method'];
        $items = $data['items'];

        $conn->begin_transaction();
        try {
            // 1. บันทึก Order หลัก
            $stmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, cash_received, change_given, payment_method) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iddds", $user_id, $total, $cash, $change, $method);
            $stmt->execute();
            $order_id = $conn->insert_id;

            // 2. บันทึกรายการสินค้า (Order Items) และตัดสต๊อก
            $stmt_item = $conn->prepare("INSERT INTO order_items (order_id, prod_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)");
            $stmt_stock = $conn->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE prod_id = ? AND stock_qty >= ?");

            foreach ($items as $item) {
                $subtotal = $item['price'] * $item['qty'];
                
                // Insert Item
                $stmt_item->bind_param("iiidd", $order_id, $item['id'], $item['qty'], $item['price'], $subtotal);
                $stmt_item->execute();

                // Update Stock (Check if stock is sufficient)
                $stmt_stock->bind_param("iii", $item['qty'], $item['id'], $item['qty']);
                $stmt_stock->execute();
                
                if ($stmt_stock->affected_rows === 0) {
                    throw new Exception("สินค้า '" . $item['name'] . "' มีจำนวนไม่เพียงพอ (เหลือ " . $item['stock'] . ")");
                }
            }

            $conn->commit();
            // ส่ง order_id กลับไปด้วยเพื่อใช้แสดงใบเสร็จ
            echo json_encode(['status' => 'success', 'order_id' => $order_id, 'change' => $change]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
        }
        exit;
    }
}

// --- ดึงข้อมูลสำหรับหน้าเว็บ ---
// ดึงหมวดหมู่
$categories = $conn->query("SELECT * FROM categories ORDER BY cat_name ASC");
$cats_data = [];
if ($categories) {
    while($row = $categories->fetch_assoc()) $cats_data[] = $row;
}

// ดึงสินค้า (เฉพาะที่มีของ)
$products = $conn->query("SELECT p.*, c.cat_name FROM products p LEFT JOIN categories c ON p.cat_id = c.cat_id WHERE p.stock_qty > 0 ORDER BY p.prod_name ASC");
$prods_data = [];
if ($products) {
    while($row = $products->fetch_assoc()) $prods_data[] = $row;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS System - ขายหน้าร้าน</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Kanit', 'sans-serif'] },
                    colors: { 
                        brand: { 50: '#eff6ff', 100: '#dbeafe', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8' } 
                    }
                }
            }
        }
    </script>
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .product-card:active { transform: scale(0.95); }
        .glass-header {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
        }
    </style>
</head>
<body class="bg-slate-50 h-screen flex flex-col overflow-hidden text-slate-800">

    <header class="glass-header h-16 flex items-center justify-between px-6 z-20 shadow-sm flex-none">
        <div class="flex items-center gap-4">
            <div class="w-10 h-10 bg-brand-600 rounded-xl flex items-center justify-center text-white shadow-lg shadow-brand-200">
                <i class="fa-solid fa-cash-register"></i>
            </div>
            <div>
                <h1 class="font-bold text-lg leading-none text-slate-800">POS SYSTEM</h1>
                <p class="text-[10px] text-slate-500 font-medium">จุดชำระเงิน</p>
            </div>
        </div>
        
        <div class="flex items-center gap-4">
            <div class="hidden md:flex items-center gap-2 px-4 py-1.5 bg-slate-100 rounded-full border border-slate-200">
                <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                <span class="text-xs font-bold text-slate-600">Online</span>
                <span class="text-xs text-slate-400 border-l border-slate-300 pl-2 ml-1">
                    <?= htmlspecialchars($_SESSION['username'] ?? 'Cashier') ?>
                </span>
            </div>
            <a href="dashboard.php" class="bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 px-4 py-2 rounded-xl text-sm font-bold transition flex items-center gap-2">
                <i class="fa-solid fa-arrow-left"></i> กลับหลังบ้าน
            </a>
        </div>
    </header>

    <main class="flex-1 flex overflow-hidden">
        
        <section class="flex-1 flex flex-col bg-slate-50/50 relative border-r border-slate-200">
            <div class="p-4 bg-white border-b border-slate-100 flex gap-3 flex-none">
                <div class="relative flex-1">
                    <i class="fa-solid fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" id="searchInput" onkeyup="filterProducts()" 
                           placeholder="ค้นหาสินค้า (ชื่อ, บาร์โค้ด)..." 
                           class="w-full bg-slate-100 border-transparent focus:bg-white focus:border-brand-500 focus:ring-2 focus:ring-brand-200 rounded-xl py-3 pl-11 pr-4 text-sm font-medium transition outline-none">
                </div>
                <div class="flex gap-2 overflow-x-auto no-scrollbar max-w-[50%] items-center" id="categoryContainer">
                    <button onclick="filterCategory('all')" class="cat-btn active whitespace-nowrap px-4 py-2 rounded-xl text-sm font-bold bg-brand-600 text-white shadow-md transition" data-cat="all">ทั้งหมด</button>
                    <?php foreach($cats_data as $cat): ?>
                        <button onclick="filterCategory('<?= $cat['cat_id'] ?>')" class="cat-btn whitespace-nowrap px-4 py-2 rounded-xl text-sm font-bold bg-white text-slate-500 border border-slate-200 hover:bg-slate-50 transition" data-cat="<?= $cat['cat_id'] ?>">
                            <?= htmlspecialchars($cat['cat_name']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto p-4 custom-scrollbar">
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4" id="productGrid">
                    <?php foreach($prods_data as $p): ?>
                    <!-- แก้ไขตรง data-price ให้ใช้ price_inc_vat -->
                    <div class="product-item product-card bg-white rounded-2xl p-3 shadow-sm border border-slate-100 cursor-pointer hover:shadow-md hover:border-brand-300 transition-all group relative overflow-hidden"
                         data-id="<?= $p['prod_id'] ?>" 
                         data-name="<?= htmlspecialchars($p['prod_name']) ?>"
                         data-price="<?= $p['price_inc_vat'] ?>" 
                         data-stock="<?= $p['stock_qty'] ?>"
                         data-cat="<?= $p['cat_id'] ?>"
                         data-code="<?= $p['prod_code'] ?>"
                         onclick="addToCart(this)">
                        
                        <div class="absolute top-2 right-2 bg-slate-900/80 text-white text-[10px] font-bold px-2 py-0.5 rounded-full z-10 backdrop-blur-sm">
                            <?= $p['stock_qty'] ?>
                        </div>

                        <div class="aspect-square bg-slate-50 rounded-xl mb-3 overflow-hidden flex items-center justify-center relative">
                            <?php if($p['img_path'] && file_exists($p['img_path'])): ?>
                                <img src="<?= $p['img_path'] ?>" class="w-full h-full object-cover group-hover:scale-110 transition duration-500">
                            <?php else: ?>
                                <i class="fa-solid fa-box-open text-3xl text-slate-300"></i>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <p class="text-xs text-slate-400 mb-0.5"><?= $p['prod_code'] ?></p>
                            <h3 class="font-bold text-slate-700 text-sm leading-tight line-clamp-2 h-9"><?= htmlspecialchars($p['prod_name']) ?></h3>
                            <div class="mt-2 flex items-end justify-between">
                                <!-- แก้ไขตรงการแสดงราคา ให้ใช้ price_inc_vat -->
                                <span class="font-black text-brand-600 text-lg">฿<?= number_format($p['price_inc_vat'], 2) ?></span>
                                <div class="w-6 h-6 bg-brand-50 text-brand-600 rounded-lg flex items-center justify-center text-xs group-hover:bg-brand-600 group-hover:text-white transition">
                                    <i class="fa-solid fa-plus"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div id="emptyState" class="hidden h-full flex flex-col items-center justify-center text-slate-400">
                    <i class="fa-solid fa-box-open text-6xl mb-4 opacity-30"></i>
                    <p>ไม่พบสินค้าที่ค้นหา</p>
                </div>
            </div>
        </section>

        <section class="w-[380px] xl:w-[450px] bg-white border-l border-slate-200 flex flex-col flex-none z-30 shadow-xl">
            <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-white">
                <h2 class="font-bold text-xl text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-basket-shopping text-brand-500"></i> รายการขาย
                </h2>
                <button onclick="clearCart()" class="text-xs text-red-500 hover:text-red-700 font-bold bg-red-50 px-3 py-1.5 rounded-lg transition">
                    <i class="fa-solid fa-trash-can mr-1"></i> ล้างตะกร้า
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-4 space-y-3 custom-scrollbar" id="cartContainer">
                <div class="h-full flex flex-col items-center justify-center text-slate-300">
                    <i class="fa-solid fa-cart-arrow-down text-5xl mb-4"></i>
                    <p class="text-sm">ยังไม่มีสินค้าในตะกร้า</p>
                    <p class="text-xs">เลือกสินค้าจากฝั่งซ้ายเพื่อเริ่มขาย</p>
                </div>
            </div>

            <div class="p-6 bg-slate-50 border-t border-slate-200">
                <div class="space-y-2 mb-4 text-sm">
                    <div class="flex justify-between text-slate-500">
                        <span>ยอดรวมสินค้า (รวม VAT)</span>
                        <span id="grandTotalDisplay" class="font-bold">0.00</span>
                    </div>
                    <div class="flex justify-between text-slate-400 text-xs">
                        <span>— มูลค่าสินค้า (Pre-VAT)</span>
                        <span id="subTotalDisplay">0.00</span>
                    </div>
                    <div class="flex justify-between text-slate-400 text-xs mb-2">
                        <span>— ภาษีมูลค่าเพิ่ม (VAT 7%)</span>
                        <span id="vatDisplay">0.00</span>
                    </div>

                    <div class="flex justify-between items-end pt-2 border-t border-slate-200">
                        <span class="font-bold text-slate-800 text-lg">ยอดสุทธิที่ต้องชำระ</span>
                        <span class="font-black text-3xl text-brand-600" id="totalPayDisplay">0.00</span>
                    </div>
                </div>

                <button onclick="openPaymentModal()" id="payButton" disabled
                        class="w-full bg-slate-800 hover:bg-black text-white py-4 rounded-2xl font-bold text-lg shadow-lg shadow-slate-300 transition-all disabled:opacity-50 disabled:cursor-not-allowed flex justify-center items-center gap-2 group">
                    <span>ชำระเงิน</span> <i class="fa-solid fa-chevron-right group-hover:translate-x-1 transition-transform"></i>
                </button>
            </div>
        </section>

    </main>

    <div id="paymentModal" class="hidden fixed inset-0 z-50 bg-slate-900/70 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white w-full max-w-md rounded-3xl shadow-2xl overflow-hidden animate-in zoom-in duration-200">
            <div class="bg-slate-50 px-8 py-6 border-b border-slate-100 flex justify-between items-center">
                <h3 class="font-bold text-xl text-slate-800">ชำระเงิน</h3>
                <button onclick="closePaymentModal()" class="text-slate-400 hover:text-red-500 transition"><i class="fa-solid fa-times text-2xl"></i></button>
            </div>
            
            <div class="p-8">
                <div class="text-center mb-6">
                    <p class="text-slate-500 text-sm mb-1">ยอดที่ต้องชำระ</p>
                    <h2 class="text-4xl font-black text-brand-600" id="modalTotal">0.00</h2>
                </div>

                <div class="mb-6">
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">วิธีชำระเงิน</label>
                    <div class="grid grid-cols-2 gap-3">
                        <button onclick="setPaymentMethod('Cash')" id="btnCash" class="pay-method active w-full py-3 rounded-xl border-2 border-brand-500 bg-brand-50 text-brand-700 font-bold text-sm flex items-center justify-center gap-2">
                            <i class="fa-solid fa-money-bill-wave"></i> เงินสด
                        </button>
                        <button onclick="setPaymentMethod('QR PromptPay')" id="btnQR" class="pay-method w-full py-3 rounded-xl border-2 border-slate-200 text-slate-500 font-bold text-sm flex items-center justify-center gap-2 hover:border-slate-300">
                            <i class="fa-solid fa-qrcode"></i> QR Code
                        </button>
                    </div>
                </div>

                <div class="mb-6" id="cashInputSection">
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">รับเงินมา</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-bold">฿</span>
                        <input type="number" id="receivedAmount" oninput="calculateChange()" class="w-full bg-slate-100 border-2 border-transparent focus:bg-white focus:border-brand-500 rounded-xl py-3 pl-10 pr-4 font-bold text-lg outline-none text-right placeholder-slate-300" placeholder="0.00">
                    </div>
                    <div class="grid grid-cols-4 gap-2 mt-3">
                        <button onclick="addCash(100)" class="bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold py-2 rounded-lg text-xs">+100</button>
                        <button onclick="addCash(500)" class="bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold py-2 rounded-lg text-xs">+500</button>
                        <button onclick="addCash(1000)" class="bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold py-2 rounded-lg text-xs">+1,000</button>
                        <button onclick="exactCash()" class="bg-blue-100 hover:bg-blue-200 text-blue-600 font-bold py-2 rounded-lg text-xs">พอดี</button>
                    </div>
                </div>

                <div class="flex justify-between items-center bg-slate-50 p-4 rounded-xl mb-6">
                    <span class="font-bold text-slate-600">เงินทอน</span>
                    <span class="font-black text-2xl text-green-600" id="changeAmount">0.00</span>
                </div>

                <button onclick="processCheckout()" id="confirmPayBtn" disabled class="w-full bg-brand-600 hover:bg-brand-700 disabled:bg-slate-300 disabled:cursor-not-allowed text-white font-bold py-4 rounded-xl shadow-lg transition-all text-lg">
                    ยืนยันการชำระเงิน
                </button>
            </div>
        </div>
    </div>

    <div id="receiptModal" class="hidden fixed inset-0 z-[60] bg-slate-900/80 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white w-full max-w-sm h-[85vh] rounded-3xl shadow-2xl flex flex-col overflow-hidden animate-in zoom-in duration-300">
            <div class="bg-slate-50 px-4 py-3 border-b border-slate-100 flex justify-between items-center flex-none">
                <h3 class="font-bold text-slate-700 flex items-center"><i class="fa-solid fa-receipt mr-2 text-brand-600"></i> ใบเสร็จรับเงิน</h3>
                <button onclick="closeReceiptModal()" class="text-slate-400 hover:text-red-500 transition">
                    <i class="fa-solid fa-circle-xmark text-2xl"></i>
                </button>
            </div>

            <div class="flex-1 bg-slate-200 p-2 overflow-hidden relative">
                <iframe id="receiptFrame" src="" class="w-full h-full rounded-xl bg-white shadow-sm border border-slate-300" style="border:none;"></iframe>
            </div>

            <div class="bg-white p-4 border-t border-slate-100 grid grid-cols-2 gap-3 flex-none">
                <button onclick="document.getElementById('receiptFrame').contentWindow.print()" class="py-3 rounded-xl border border-brand-200 bg-brand-50 text-brand-700 font-bold hover:bg-brand-100 transition flex items-center justify-center">
                    <i class="fa-solid fa-print mr-2"></i> พิมพ์
                </button>
                <button onclick="closeReceiptModal()" class="py-3 rounded-xl bg-brand-600 text-white font-bold hover:bg-brand-700 shadow-lg shadow-brand-200 transition">
                    ขายรายการใหม่
                </button>
            </div>
        </div>
    </div>

    <script>
        let cart = [];
        let currentPaymentMethod = 'Cash';
        let grandTotal = 0;

        // --- Cart Functions ---
        function addToCart(el) {
            const product = {
                id: parseInt(el.dataset.id),
                name: el.dataset.name,
                price: parseFloat(el.dataset.price),
                stock: parseInt(el.dataset.stock),
                code: el.dataset.code,
                qty: 1
            };

            const existing = cart.find(item => item.id === product.id);
            if (existing) {
                if (existing.qty < product.stock) {
                    existing.qty++;
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'สินค้าหมด',
                        text: 'จำนวนสินค้าในสต๊อกมีไม่เพียงพอ',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    return;
                }
            } else {
                cart.push(product);
            }
            renderCart();
            playSound('beep');
        }

        function updateQty(id, change) {
            const item = cart.find(i => i.id === id);
            if (!item) return;

            const newQty = item.qty + change;
            if (newQty > 0 && newQty <= item.stock) {
                item.qty = newQty;
            } else if (newQty > item.stock) {
                Swal.fire({
                    icon: 'warning',
                    text: 'จำนวนจำกัด (เหลือ ' + item.stock + ')',
                    timer: 1000, showConfirmButton: false
                });
            }
            renderCart();
        }

        function removeItem(id) {
            cart = cart.filter(i => i.id !== id);
            renderCart();
        }

        function clearCart() {
            if(cart.length === 0) return;
            Swal.fire({
                title: 'ยืนยันการล้างตะกร้า?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'ล้างเลย',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    cart = [];
                    renderCart();
                }
            });
        }

        function renderCart() {
            const container = document.getElementById('cartContainer');
            if (cart.length === 0) {
                container.innerHTML = `
                    <div class="h-full flex flex-col items-center justify-center text-slate-300">
                        <i class="fa-solid fa-cart-arrow-down text-5xl mb-4 opacity-50"></i>
                        <p class="text-sm font-medium">ยังไม่มีสินค้าในตะกร้า</p>
                        <p class="text-xs mt-1">คลิกเลือกสินค้าเพื่อเริ่มขาย</p>
                    </div>`;
                document.getElementById('payButton').disabled = true;
                updateTotals(0);
                return;
            }

            let html = '';
            let total = 0;

            cart.forEach(item => {
                const itemTotal = item.price * item.qty;
                total += itemTotal;
                html += `
                    <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl border border-slate-100 group hover:border-brand-200 transition">
                        <div class="flex-1 min-w-0 pr-3">
                            <h4 class="font-bold text-slate-700 text-sm truncate">${item.name}</h4>
                            <div class="text-xs text-slate-400 mt-0.5">฿${item.price.toFixed(2)} x ${item.qty}</div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="font-bold text-slate-800 w-16 text-right">฿${itemTotal.toFixed(2)}</div>
                            <div class="flex items-center bg-white rounded-lg border border-slate-200 overflow-hidden">
                                <button onclick="updateQty(${item.id}, -1)" class="w-7 h-7 flex items-center justify-center text-slate-400 hover:bg-slate-100 hover:text-red-500"><i class="fa-solid fa-minus text-[10px]"></i></button>
                                <span class="w-8 text-center text-xs font-bold text-slate-700">${item.qty}</span>
                                <button onclick="updateQty(${item.id}, 1)" class="w-7 h-7 flex items-center justify-center text-slate-400 hover:bg-slate-100 hover:text-brand-500"><i class="fa-solid fa-plus text-[10px]"></i></button>
                            </div>
                            <button onclick="removeItem(${item.id})" class="w-7 h-7 flex items-center justify-center text-slate-300 hover:text-red-500 transition"><i class="fa-solid fa-times"></i></button>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
            document.getElementById('payButton').disabled = false;
            updateTotals(total);
        }

        function updateTotals(total) {
            grandTotal = total;
            // เนื่องจากราคาสินค้าเป็นราคารวม VAT แล้ว (Inclusive)
            // สูตรถอด VAT: VAT = Price * 7 / 107
            const vatAmount = (total * 7) / 107;
            const subTotal = total - vatAmount;

            document.getElementById('subTotalDisplay').innerText = subTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('vatDisplay').innerText = vatAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('grandTotalDisplay').innerText = total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('totalPayDisplay').innerText = '฿' + total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }

        // --- Filter Functions ---
        function filterCategory(catId) {
            document.querySelectorAll('.cat-btn').forEach(btn => {
                if (btn.dataset.cat === catId) {
                    btn.classList.add('bg-brand-600', 'text-white', 'shadow-md');
                    btn.classList.remove('bg-white', 'text-slate-500', 'border', 'border-slate-200');
                } else {
                    btn.classList.remove('bg-brand-600', 'text-white', 'shadow-md');
                    btn.classList.add('bg-white', 'text-slate-500', 'border', 'border-slate-200');
                }
            });
            
            const cards = document.querySelectorAll('.product-item');
            let hasItem = false;
            cards.forEach(card => {
                if (catId === 'all' || card.dataset.cat === catId) {
                    card.classList.remove('hidden');
                    hasItem = true;
                } else {
                    card.classList.add('hidden');
                }
            });
            filterProducts();
        }

        function filterProducts() {
            const txt = document.getElementById('searchInput').value.toLowerCase();
            const activeCatBtn = document.querySelector('.cat-btn.bg-brand-600');
            const activeCat = activeCatBtn ? activeCatBtn.dataset.cat : 'all';
            
            const cards = document.querySelectorAll('.product-item');
            let visibleCount = 0;

            cards.forEach(card => {
                const name = card.dataset.name.toLowerCase();
                const code = card.dataset.code.toLowerCase();
                const cat = card.dataset.cat;
                
                const matchesSearch = name.includes(txt) || code.includes(txt);
                const matchesCat = activeCat === 'all' || cat === activeCat;

                if (matchesSearch && matchesCat) {
                    card.classList.remove('hidden');
                    visibleCount++;
                } else {
                    card.classList.add('hidden');
                }
            });

            const emptyState = document.getElementById('emptyState');
            if (visibleCount === 0) {
                emptyState.classList.remove('hidden');
            } else {
                emptyState.classList.add('hidden');
            }
        }

        // --- Payment Logic ---
        function openPaymentModal() {
            document.getElementById('paymentModal').classList.remove('hidden');
            document.getElementById('modalTotal').innerText = '฿' + grandTotal.toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('receivedAmount').value = '';
            document.getElementById('changeAmount').innerText = '0.00';
            setPaymentMethod('Cash');
            calculateChange();
            setTimeout(() => document.getElementById('receivedAmount').focus(), 100);
        }

        function closePaymentModal() {
            document.getElementById('paymentModal').classList.add('hidden');
        }

        function setPaymentMethod(method) {
            currentPaymentMethod = method;
            document.querySelectorAll('.pay-method').forEach(btn => {
                btn.classList.remove('active', 'border-brand-500', 'bg-brand-50', 'text-brand-700');
                btn.classList.add('border-slate-200', 'text-slate-500');
            });
            
            const activeBtn = method === 'Cash' ? document.getElementById('btnCash') : document.getElementById('btnQR');
            activeBtn.classList.add('active', 'border-brand-500', 'bg-brand-50', 'text-brand-700');
            activeBtn.classList.remove('border-slate-200', 'text-slate-500');

            const cashSection = document.getElementById('cashInputSection');
            if (method === 'Cash') {
                cashSection.classList.remove('opacity-50', 'pointer-events-none');
                document.getElementById('receivedAmount').focus();
            } else {
                cashSection.classList.add('opacity-50', 'pointer-events-none');
                document.getElementById('receivedAmount').value = grandTotal;
                calculateChange();
            }
        }

        function addCash(amount) {
            const input = document.getElementById('receivedAmount');
            const current = parseFloat(input.value) || 0;
            input.value = current + amount;
            calculateChange();
        }

        function exactCash() {
            document.getElementById('receivedAmount').value = grandTotal;
            calculateChange();
        }

        function calculateChange() {
            const received = parseFloat(document.getElementById('receivedAmount').value) || 0;
            const change = received - grandTotal;
            const changeDisplay = document.getElementById('changeAmount');
            const btn = document.getElementById('confirmPayBtn');

            if (change >= 0) {
                changeDisplay.innerText = '฿' + change.toLocaleString('en-US', {minimumFractionDigits: 2});
                changeDisplay.classList.remove('text-red-500');
                changeDisplay.classList.add('text-green-600');
                btn.disabled = false;
            } else {
                changeDisplay.innerText = 'ขาด ' + Math.abs(change).toLocaleString('en-US', {minimumFractionDigits: 2});
                changeDisplay.classList.add('text-red-500');
                changeDisplay.classList.remove('text-green-600');
                btn.disabled = true;
            }
        }

        function processCheckout() {
            const received = parseFloat(document.getElementById('receivedAmount').value) || 0;
            const change = received - grandTotal;

            const orderData = {
                items: cart,
                total: grandTotal,
                cash: received,
                change: change,
                method: currentPaymentMethod
            };

            const btn = document.getElementById('confirmPayBtn');
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> กำลังบันทึก...';
            btn.disabled = true;

            const formData = new FormData();
            formData.append('action', 'checkout');
            formData.append('order_data', JSON.stringify(orderData));

            fetch('pos.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        // ปิดหน้าชำระเงิน
                        closePaymentModal();
                        
                        // แสดงใบเสร็จใน iframe (Modal) โดยไม่ต้องเปิดแท็บใหม่
                        showReceiptModal(data.order_id);

                        // ล้างตะกร้า
                        cart = [];
                        renderCart();
                    } else {
                        Swal.fire('เกิดข้อผิดพลาด', data.message, 'error');
                        btn.innerHTML = 'ยืนยันการชำระเงิน';
                        btn.disabled = false;
                    }
                })
                .catch(err => {
                    Swal.fire('Error', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
                    btn.innerHTML = 'ยืนยันการชำระเงิน';
                    btn.disabled = false;
                });
        }

        // ฟังก์ชันใหม่สำหรับจัดการ Modal ใบเสร็จ
        function showReceiptModal(orderId) {
            const modal = document.getElementById('receiptModal');
            const iframe = document.getElementById('receiptFrame');
            
            // โหลด receipt.php เข้าไปใน iframe
            iframe.src = 'api/receipt.php?order_id=' + orderId;
            
            modal.classList.remove('hidden');
        }

        function closeReceiptModal() {
            const modal = document.getElementById('receiptModal');
            const iframe = document.getElementById('receiptFrame');
            
            modal.classList.add('hidden');
            iframe.src = 'about:blank'; // ล้างข้อมูล iframe
            
            // โฟกัสไปที่ช่องค้นหา เพื่อให้พร้อมยิงบาร์โค้ดขายต่อได้เลย
            document.getElementById('searchInput').focus();
        }

        function playSound(type) {
            // Optional: Add sound effects here
        }
    </script>
</body>
</html>