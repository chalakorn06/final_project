<?php
require_once 'api/connect.php';

// จัดการการบันทึกข้อมูลแผนก
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $dept_name = trim($_POST['dept_name']);
    
    if (!empty($dept_name)) {
        $stmt = $conn->prepare("INSERT INTO departments (dept_name) VALUES (?)");
        $stmt->bind_param("s", $dept_name);
        
        if ($stmt->execute()) {
            // ส่งค่า success กลับเพื่อให้ฟังก์ชัน setupAjaxForm ใน emp_manage.php ทำการปิด Modal และ Reload หน้าเว็บ
            echo "success";
            exit;
        } else {
            $error = "ไม่สามารถเพิ่มข้อมูลได้: " . $conn->error;
        }
    } else {
        $error = "กรุณาระบุชื่อแผนก";
    }
}
?>

<div class="bg-white p-8 rounded-2xl w-full">
    <div class="text-center mb-8">
        <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-sitemap text-2xl"></i>
        </div>
        <h1 class="text-2xl font-bold text-gray-800">เพิ่มแผนกใหม่</h1>
        <p class="text-gray-500 text-sm">ระบุชื่อแผนกที่ต้องการเพิ่มเข้าสู่ระบบ</p>
    </div>

    <?php if (isset($error)): ?>
        <div class="mb-4 p-3 bg-red-100 text-red-700 text-sm rounded-lg border-l-4 border-red-500">
            <?= $error ?>
        </div>
    <?php endif; ?>

    <form action="dept_manage.php" method="POST" class="space-y-6">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อแผนก <span class="text-red-500">*</span></label>
            <input type="text" name="dept_name" required placeholder="เช่น ฝ่ายบุคคล, ฝ่ายการตลาด"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none transition-all">
        </div>

        <div class="pt-4 flex gap-3">
            <button type="button" onclick="closeModal()" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-600 font-medium py-3 rounded-lg transition">
                ยกเลิก
            </button>
            <button type="submit" 
                class="flex-[2] bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-3 rounded-lg shadow-lg shadow-emerald-100 transition active:scale-[0.98]">
                บันทึกแผนกใหม่
            </button>
        </div>
    </form>
</div>