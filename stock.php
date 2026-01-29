<?php
require_once 'api/connect.php';

// --- ส่วนจัดการ Logic (Backend) ---

// 1. เพิ่ม/แก้ไข สินค้า
if (isset($_POST['save_product'])) {
    $prod_id = isset($_POST['prod_id']) ? intval($_POST['prod_id']) : 0;
    $cat_id = intval($_POST['cat_id']);
    $prod_code = trim($_POST['prod_code']);
    $prod_name = trim($_POST['prod_name']);
    $price = floatval($_POST['price']);
    $stock_qty = intval($_POST['stock_qty']);
    $img_path = $_POST['existing_img'] ?? ''; 

    // จัดการอัปโหลดรูปภาพ
    if (isset($_FILES['product_img']) && $_FILES['product_img']['error'] == 0) {
        $upload_dir = 'products_img/'; 
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_ext = pathinfo($_FILES['product_img']['name'], PATHINFO_EXTENSION);
        $new_file_name = "prod_" . time() . "_" . rand(1000, 9999) . "." . $file_ext;
        $target_file = $upload_dir . $new_file_name;

        if (move_uploaded_file($_FILES['product_img']['tmp_name'], $target_file)) {
            // ลบรูปเก่าถ้ามีและมีการอัปโหลดใหม่
            if ($prod_id > 0 && !empty($img_path) && file_exists($img_path)) {
                unlink($img_path);
            }
            $img_path = $target_file;
        }
    }

    if ($prod_id > 0) {
        // แก้ไขสินค้า (Update) - ใช้ Prepared Statement
        $stmt = $conn->prepare("UPDATE products SET cat_id=?, prod_code=?, prod_name=?, price=?, stock_qty=?, img_path=? WHERE prod_id=?");
        $stmt->bind_param("issdisi", $cat_id, $prod_code, $prod_name, $price, $stock_qty, $img_path, $prod_id);
    } else {
        // เพิ่มสินค้าใหม่ (Insert) - ใช้ Prepared Statement
        $stmt = $conn->prepare("INSERT INTO products (cat_id, prod_code, prod_name, price, stock_qty, img_path) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issdis", $cat_id, $prod_code, $prod_name, $price, $stock_qty, $img_path);
    }
    
    if($stmt->execute()) {
        $stmt->close();
        header("Location: stock.php");
        exit;
    } else {
        echo "Error: " . $stmt->error;
    }
}

// 2. ลบสินค้า
if (isset($_GET['delete_prod'])) {
    $id = intval($_GET['delete_prod']);
    
    // ดึงข้อมูลรูปภาพเพื่อลบไฟล์
    $stmt = $conn->prepare("SELECT img_path FROM products WHERE prod_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if($row = $res->fetch_assoc()) {
        if(!empty($row['img_path']) && file_exists($row['img_path'])) {
            unlink($row['img_path']);
        }
    }
    $stmt->close();

    // ลบข้อมูลจากฐานข้อมูล
    $del_stmt = $conn->prepare("DELETE FROM products WHERE prod_id = ?");
    $del_stmt->bind_param("i", $id);
    $del_stmt->execute();
    $del_stmt->close();
    
    header("Location: stock.php");
    exit;
}

// 3. จัดการหมวดหมู่
if (isset($_POST['save_category'])) {
    $cat_id = isset($_POST['cat_id']) ? intval($_POST['cat_id']) : 0;
    $cat_name = trim($_POST['cat_name']);
    
    if ($cat_id > 0) {
        $stmt = $conn->prepare("UPDATE categories SET cat_name = ? WHERE cat_id = ?");
        $stmt->bind_param("si", $cat_name, $cat_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO categories (cat_name) VALUES (?)");
        $stmt->bind_param("s", $cat_name);
    }
    
    $stmt->execute();
    $stmt->close();
    
    header("Location: stock.php");
    exit;
}

if (isset($_GET['delete_cat'])) {
    $id = intval($_GET['delete_cat']);
    
    $stmt = $conn->prepare("DELETE FROM categories WHERE cat_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: stock.php");
    exit;
}

// --- ดึงข้อมูลมาแสดงผล ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$products = null;

if ($search) {
    // ใช้ Prepared Statement สำหรับการค้นหา
    $search_param = "%{$search}%";
    $stmt = $conn->prepare("SELECT p.*, c.cat_name FROM products p LEFT JOIN categories c ON p.cat_id = c.cat_id WHERE p.prod_name LIKE ? OR p.prod_code LIKE ? ORDER BY p.prod_id DESC");
    $stmt->bind_param("ss", $search_param, $search_param);
    $stmt->execute();
    $products = $stmt->get_result();
} else {
    // Query ปกติ (ไม่มี Parameter input จาก user)
    $products = $conn->query("SELECT p.*, c.cat_name FROM products p LEFT JOIN categories c ON p.cat_id = c.cat_id ORDER BY p.prod_id DESC");
}

$categories = $conn->query("SELECT * FROM categories ORDER BY cat_name ASC");

$total_prods = $conn->query("SELECT COUNT(*) as total FROM products")->fetch_assoc()['total'];
$low_stock = $conn->query("SELECT COUNT(*) as total FROM products WHERE stock_qty <= 5")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสต๊อกสินค้า - POS System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@200;300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Kanit', sans-serif; }
        .animate-slide-up { animation: slideUp 0.3s ease-out; }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-700">

    <?php include 'sidebar.php'; ?>

    <main class="max-w-7xl mx-auto px-4 py-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-2xl shadow-sm border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 font-medium uppercase tracking-wider">สินค้าทั้งหมด</p>
                        <h3 class="text-3xl font-bold text-gray-800"><?= number_format($total_prods) ?> <span class="text-lg font-normal text-gray-400">รายการ</span></h3>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-xl text-blue-600"><i class="fa fa-list-ul fa-2x"></i></div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-2xl shadow-sm border-l-4 border-orange-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 font-medium uppercase tracking-wider">สินค้าใกล้หมด (≤ 5)</p>
                        <h3 class="text-3xl font-bold text-gray-800"><?= number_format($low_stock) ?> <span class="text-lg font-normal text-gray-400">รายการ</span></h3>
                    </div>
                    <div class="bg-orange-100 p-3 rounded-xl text-orange-600"><i class="fa fa-triangle-exclamation fa-2x"></i></div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-2xl shadow-sm border-l-4 border-green-500 flex flex-col gap-2">
                <button onclick="openModal('productModal')" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-xl transition-all shadow-md flex items-center justify-center gap-2 text-sm">
                    <i class="fa fa-plus-circle"></i> เพิ่มสินค้าใหม่
                </button>
                <button onclick="openScannerModal()" class="w-full bg-slate-800 hover:bg-black text-white font-bold py-2 px-4 rounded-xl transition-all shadow-md flex items-center justify-center gap-2 text-sm">
                    <i class="fa fa-barcode"></i> สแกนรับสินค้าเข้า
                </button>
            </div>
        </div>

        <div class="flex flex-col lg:flex-row gap-8">
            <div class="flex-1">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="p-6 border-b border-gray-50 flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                            <i class="fa fa-table text-blue-500"></i> รายการสต๊อกสินค้า
                        </h2>
                        <form method="GET" class="relative">
                            <i class="fa fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="ค้นหาชื่อหรือรหัสสินค้า..." 
                                   class="pl-10 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:bg-white outline-none w-full md:w-64 text-sm transition-all">
                        </form>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-gray-50/50 text-gray-500 text-xs uppercase font-bold tracking-widest">
                                <tr>
                                    <th class="px-6 py-4">รูปภาพ</th>
                                    <th class="px-6 py-4">ชื่อสินค้า / รหัส</th>
                                    <th class="px-6 py-4">หมวดหมู่</th>
                                    <th class="px-4 py-4 text-right">ราคาฐาน (฿)</th>
                                    <th class="px-4 py-4 text-right">ราคารวม VAT</th>
                                    <th class="px-6 py-4 text-center">คงเหลือ</th>
                                    <th class="px-6 py-4 text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if($products && $products->num_rows > 0): ?>
                                    <?php while($row = $products->fetch_assoc()): 
                                        $price = floatval($row['price']);
                                        $price_inc_vat = floatval($row['price_inc_vat']);
                                    ?>
                                    <tr class="hover:bg-blue-50/30 transition-colors group text-sm">
                                        <td class="px-6 py-4">
                                            <div class="w-12 h-12 rounded-lg bg-gray-100 overflow-hidden border border-gray-200">
                                                <?php if(!empty($row['img_path']) && file_exists($row['img_path'])): ?>
                                                    <img src="<?= $row['img_path'] ?>" class="w-full h-full object-cover" alt="product">
                                                <?php else: ?>
                                                    <div class="w-full h-full flex items-center justify-center text-gray-300">
                                                        <i class="fa fa-image"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col">
                                                <span class="font-semibold text-gray-800"><?= htmlspecialchars($row['prod_name']) ?></span>
                                                <span class="font-mono text-[10px] text-gray-400"><?= htmlspecialchars($row['prod_code']) ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="px-3 py-1 bg-slate-100 rounded-full text-[11px] font-medium text-slate-600"><?= htmlspecialchars($row['cat_name'] ?: 'ทั่วไป') ?></span>
                                        </td>
                                        <td class="px-4 py-4 text-right font-medium text-slate-500"><?= number_format($price, 2) ?></td>
                                        <td class="px-4 py-4 text-right font-bold text-blue-600">
                                            <div class="flex flex-col items-end">
                                                <span><?= number_format($price_inc_vat, 2) ?></span>
                                                <span class="text-[9px] text-gray-400 font-normal italic">VAT 7% แล้ว</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <span class="inline-block min-w-[3rem] px-3 py-1 rounded-full text-xs font-bold <?= $row['stock_qty'] <= 5 ? 'bg-red-100 text-red-600 ring-1 ring-red-200' : 'bg-green-100 text-green-600 ring-1 ring-green-200' ?>">
                                                <?= number_format($row['stock_qty']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <div class="flex justify-center gap-1">
                                                <button onclick='editProduct(<?= json_encode($row) ?>)' class="w-8 h-8 flex items-center justify-center text-blue-500 hover:bg-blue-50 rounded-lg transition-colors" title="แก้ไข">
                                                    <i class="fa fa-edit"></i>
                                                </button>
                                                <a href="?delete_prod=<?= $row['prod_id'] ?>" onclick="return confirm('ยืนยันการลบสินค้าชิ้นนี้?')" class="w-8 h-8 flex items-center justify-center text-red-400 hover:bg-red-50 rounded-lg transition-colors" title="ลบ">
                                                    <i class="fa fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="7" class="px-6 py-12 text-center text-gray-400 italic font-light">ไม่พบข้อมูลสินค้า</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="w-full lg:w-80 space-y-6">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <i class="fa fa-tags text-blue-500"></i> หมวดหมู่สินค้า
                    </h2>
                    <button onclick="openModal('categoryModal')" class="w-full bg-slate-800 text-white px-4 py-2.5 rounded-xl hover:bg-black transition-colors text-sm font-medium mb-6">
                        <i class="fa fa-plus mr-1"></i> เพิ่มหมวดหมู่ใหม่
                    </button>
                    
                    <div class="space-y-2 max-h-[450px] overflow-y-auto pr-2 custom-scrollbar">
                        <?php 
                        if($categories) {
                            $categories->data_seek(0);
                            while($cat = $categories->fetch_assoc()): 
                        ?>
                        <div class="group flex justify-between items-center p-3 bg-gray-50 rounded-xl hover:bg-blue-50 border border-transparent hover:border-blue-100 transition-all">
                            <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars($cat['cat_name']) ?></span>
                            <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button onclick='editCategory(<?= json_encode($cat) ?>)' class="text-blue-500 hover:bg-white p-1.5 rounded-lg shadow-sm">
                                    <i class="fa fa-edit text-xs"></i>
                                </button>
                                <a href="?delete_cat=<?= $cat['cat_id'] ?>" onclick="return confirm('ลบหมวดหมู่นี้?')" class="text-red-400 hover:bg-white p-1.5 rounded-lg shadow-sm">
                                    <i class="fa fa-trash text-xs"></i>
                                </a>
                            </div>
                        </div>
                        <?php 
                            endwhile; 
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal เพิ่ม/แก้ไขสินค้า -->
    <div id="productModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
        <div class="bg-white w-full max-w-lg rounded-3xl shadow-2xl overflow-hidden animate-slide-up">
            <div class="bg-blue-600 px-8 py-6 text-white flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-white/20 rounded-lg"><i class="fa fa-box-open"></i></div>
                    <h3 id="modalTitle" class="text-xl font-bold">เพิ่มสินค้าใหม่</h3>
                </div>
                <button onclick="closeModal('productModal')" class="text-2xl hover:text-red-200 transition-colors">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-8 space-y-4">
                <input type="hidden" name="prod_id" id="form_id" value="0">
                <input type="hidden" name="existing_img" id="existing_img" value="">

                <div class="flex flex-col items-center mb-4">
                    <div id="image_preview_container" class="w-32 h-32 rounded-2xl bg-slate-100 border-2 border-dashed border-slate-300 overflow-hidden flex items-center justify-center relative group cursor-pointer" onclick="document.getElementById('product_img').click()">
                        <img id="image_preview" src="" class="hidden w-full h-full object-cover">
                        <div id="image_placeholder" class="text-center text-slate-400">
                            <i class="fa fa-camera text-2xl mb-1"></i>
                            <p class="text-[10px] font-bold uppercase">เลือกรูปภาพ</p>
                        </div>
                    </div>
                    <input type="file" name="product_img" id="product_img" class="hidden" accept="image/*" onchange="previewImage(this, 'image_preview', 'image_placeholder')">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">รหัสสินค้า (SKU)</label>
                        <input type="text" name="prod_code" id="form_code" required class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">หมวดหมู่</label>
                        <select name="cat_id" id="form_cat" required class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-blue-500 outline-none">
                            <option value="">-- เลือก --</option>
                            <?php if($categories) { 
                                $categories->data_seek(0); 
                                while($cat = $categories->fetch_assoc()): ?>
                                <option value="<?= $cat['cat_id'] ?>"><?= htmlspecialchars($cat['cat_name']) ?></option>
                            <?php endwhile; } ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">ชื่อสินค้า</label>
                    <input type="text" name="prod_name" id="form_name" required class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">ราคาจำหน่าย (ก่อน VAT)</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 font-bold">฿</span>
                            <input type="number" step="0.01" name="price" id="form_price" required oninput="updatePriceDisplay()"
                                   class="w-full bg-gray-50 border border-gray-200 rounded-xl pl-8 pr-4 py-2.5 focus:ring-2 focus:ring-blue-500 outline-none">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">จำนวนคงเหลือ</label>
                        <input type="number" name="stock_qty" id="form_qty" required class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                </div>

                <div class="bg-blue-50 rounded-2xl p-4 border border-blue-100 space-y-2">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-[10px] font-bold text-blue-400 uppercase">ราคาสุทธิ (Inc. VAT 7%)</p>
                            <p id="totalPreview" class="text-2xl font-black text-blue-800">฿ 0.00</p>
                        </div>
                        <i class="fa fa-calculator text-blue-200 text-3xl"></i>
                    </div>
                </div>

                <div class="pt-4 flex gap-4">
                    <button type="button" onclick="closeModal('productModal')" class="flex-1 border-2 border-gray-100 text-gray-400 font-bold py-3 rounded-2xl hover:bg-gray-50 transition-all">ยกเลิก</button>
                    <button type="submit" name="save_product" class="flex-1 bg-blue-600 text-white font-bold py-3 rounded-2xl hover:bg-blue-700 shadow-lg shadow-blue-200 transition-all">บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal จัดการหมวดหมู่ -->
    <div id="categoryModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
        <div class="bg-white w-full max-w-sm rounded-3xl shadow-2xl overflow-hidden animate-slide-up">
            <div class="bg-slate-800 px-6 py-4 text-white flex justify-between items-center">
                <h3 id="catModalTitle" class="font-bold">เพิ่มหมวดหมู่</h3>
                <button onclick="closeModal('categoryModal')" class="text-xl">&times;</button>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="cat_id" id="cat_form_id" value="0">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">ชื่อหมวดหมู่</label>
                    <input type="text" name="cat_name" id="cat_form_name" required placeholder="ระบุชื่อหมวดหมู่..." class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div class="flex gap-3">
                    <button type="button" onclick="closeModal('categoryModal')" class="flex-1 bg-gray-100 text-gray-500 font-bold py-2.5 rounded-xl hover:bg-gray-200 transition-all">ยกเลิก</button>
                    <button type="submit" name="save_category" class="flex-1 bg-blue-600 text-white font-bold py-2.5 rounded-xl hover:bg-blue-700 transition-all">บันทึก</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal สแกนรับสินค้า -->
    <div id="scannerModal" class="hidden fixed inset-0 bg-slate-900/70 backdrop-blur-md flex items-center justify-center z-50 p-4">
        <div class="bg-white w-full max-w-md rounded-[2.5rem] shadow-2xl overflow-hidden animate-slide-up border border-slate-100">
            <div class="bg-slate-900 px-8 py-6 text-white flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-white/10 rounded-xl"><i class="fa fa-barcode"></i></div>
                    <h3 class="text-lg font-bold tracking-tight">สแกนรับสินค้าเข้าสต๊อก</h3>
                </div>
                <button onclick="closeModal('scannerModal')" class="text-slate-400 hover:text-red-400 transition-colors"><i class="fa fa-times-circle text-2xl"></i></button>
            </div>
            
            <div class="p-8">
                <div class="mb-6">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">สแกนบาร์โค้ด</label>
                    <div class="relative">
                        <i class="fa fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-300"></i>
                        <input type="text" id="scan_code_input" onkeypress="handleScan(event)" placeholder="สแกน หรือ กรอกรหัสบาร์โค้ดที่นี่..." 
                               class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl py-4 pl-12 pr-4 font-bold text-slate-800 focus:border-blue-500 focus:bg-white transition-all outline-none">
                    </div>
                    <p class="text-[10px] text-slate-400 mt-2 italic">* แนะนำให้ใช้เครื่องสแกนเพื่อความรวดเร็ว</p>
                </div>

                <!-- แสดงข้อมูลสินค้าเมื่อพบ -->
                <div id="scan_result_area" class="hidden animate-in fade-in slide-in-from-top-4 duration-300">
                    <div class="bg-blue-50 rounded-3xl p-6 border border-blue-100 mb-6">
                        <div class="flex items-start gap-4">
                            <div id="scan_prod_img" class="w-20 h-20 bg-white rounded-2xl flex items-center justify-center border border-blue-100 overflow-hidden flex-none">
                                <i class="fa fa-image text-slate-200 text-2xl"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 id="scan_prod_name" class="font-bold text-slate-800 line-clamp-2 leading-tight mb-1">ชื่อสินค้า</h4>
                                <p id="scan_prod_code" class="text-[10px] font-mono text-blue-500 font-bold">#00000</p>
                                <div class="mt-2 flex items-center gap-2">
                                    <span class="text-[10px] text-slate-400 font-bold uppercase">คงเหลือ:</span>
                                    <span id="scan_prod_stock" class="text-sm font-black text-slate-800">0</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">จำนวนที่รับเข้า</label>
                            <input type="number" id="scan_qty_input" value="1" min="1" 
                                   class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl py-4 px-6 font-black text-2xl text-slate-800 text-center focus:border-blue-500 focus:bg-white outline-none transition-all">
                        </div>
                        <button onclick="submitReceiveStock()" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-2xl shadow-lg shadow-blue-200 transition-all transform active:scale-95 flex items-center justify-center gap-2">
                            <i class="fa fa-save"></i> ยืนยันรับเข้าสต๊อก
                        </button>
                    </div>
                </div>

                <div id="scan_placeholder" class="py-12 text-center text-slate-300 opacity-50">
                    <i class="fa fa-barcode text-6xl mb-4"></i>
                    <p class="text-sm font-bold">รอการสแกนบาร์โค้ด...</p>
                </div>
            </div>
        </div>
    </div>

    <script src="api/main_script.js"></script>

<script>
let current_scanned_prod = null;

function updatePriceDisplay() {
    const price = document.getElementById('form_price').value;
    const result = calculateVat(price);
    document.getElementById('totalPreview').innerText = '฿ ' + result.total.toLocaleString(undefined, {minimumFractionDigits: 2});
}

function editProduct(prod) {
    document.getElementById('modalTitle').innerText = 'แก้ไขข้อมูลสินค้า';
    document.getElementById('form_id').value = prod.prod_id;
    document.getElementById('form_code').value = prod.prod_code;
    document.getElementById('form_name').value = prod.prod_name;
    document.getElementById('form_cat').value = prod.cat_id;
    document.getElementById('form_price').value = prod.price;
    document.getElementById('form_qty').value = prod.stock_qty;
    document.getElementById('existing_img').value = prod.img_path;

    if (prod.img_path) {
        document.getElementById('image_preview').src = prod.img_path;
        document.getElementById('image_preview').classList.remove('hidden');
        document.getElementById('image_placeholder').classList.add('hidden');
    } else {
        document.getElementById('image_preview').classList.add('hidden');
        document.getElementById('image_placeholder').classList.remove('hidden');
    }
    
    updatePriceDisplay();
    openModal('productModal');
}

function editCategory(cat) {
    document.getElementById('catModalTitle').innerText = 'แก้ไขหมวดหมู่';
    document.getElementById('cat_form_id').value = cat.cat_id;
    document.getElementById('cat_form_name').value = cat.cat_name;
    openModal('categoryModal');
}

// ระบบสแกนรับสินค้า
function openScannerModal() {
    openModal('scannerModal');
    resetScanner();
    setTimeout(() => document.getElementById('scan_code_input').focus(), 300);
}

function resetScanner() {
    document.getElementById('scan_code_input').value = '';
    document.getElementById('scan_result_area').classList.add('hidden');
    document.getElementById('scan_placeholder').classList.remove('hidden');
    current_scanned_prod = null;
}

async function handleScan(e) {
    if (e.key === 'Enter') {
        const code = e.target.value.trim();
        if (!code) return;

        try {
            const resp = await fetch(`api/product_actions.php?action=get_prod_by_code&code=${code}`);
            const res = await resp.json();

            if (res.status === 'success') {
                current_scanned_prod = res.data;
                showScanResult(res.data);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'ไม่พบสินค้า',
                    text: res.message,
                    timer: 1500,
                    showConfirmButton: false
                });
                resetScanner();
                document.getElementById('scan_code_input').focus();
            }
        } catch (error) {
            console.error(error);
        }
    }
}

function showScanResult(prod) {
    document.getElementById('scan_prod_name').innerText = prod.prod_name;
    document.getElementById('scan_prod_code').innerText = '#' + (prod.prod_code || prod.prod_id);
    document.getElementById('scan_prod_stock').innerText = prod.stock_qty;
    
    document.getElementById('scan_placeholder').classList.add('hidden');
    document.getElementById('scan_result_area').classList.remove('hidden');
    
    // โฟกัสไปที่ช่องจำนวนทันที
    document.getElementById('scan_qty_input').focus();
    document.getElementById('scan_qty_input').select();
}

async function submitReceiveStock() {
    if (!current_scanned_prod) return;
    const qty = document.getElementById('scan_qty_input').value;
    
    if (qty <= 0) {
        return Swal.fire('ผิดพลาด', 'กรุณาระบุจำนวนที่ถูกต้อง', 'warning');
    }

    const formData = new FormData();
    formData.append('action', 'receive_stock');
    formData.append('prod_id', current_scanned_prod.prod_id);
    formData.append('qty_add', qty);

    try {
        const resp = await fetch('api/product_actions.php', { method: 'POST', body: formData });
        const res = await resp.json();

        if (res.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ',
                text: res.message,
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                location.reload(); // รีโหลดเพื่ออัปเดตตาราง
            });
        } else {
            Swal.fire('ล้มเหลว', res.message, 'error');
        }
    } catch (error) {
        Swal.fire('Error', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
    }
}
</script>

</body>
</html>