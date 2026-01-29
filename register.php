<?php
require_once 'api/connect.php';

// ดึงข้อมูลแผนกสำหรับ Dropdown
$departments = [];
$dept_query = "SELECT dept_id, dept_name FROM departments ORDER BY dept_name ASC";
$result = $conn->query($dept_query);
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $departments[] = $row;
    }
}

// ตรวจสอบว่าเป็นโหมดแก้ไขหรือไม่
$is_edit = false;
$edit_id = null;
$emp_data = [
    'emp_code' => '', 'first_name' => '', 'last_name' => '', 
    'email' => '', 'phone' => '', 'hire_date' => date('Y-m-d'), 'dept_id' => '',
    'username' => ''
];

if (isset($_GET['edit'])) {
    $is_edit = true;
    $edit_id = intval($_GET['edit']);
    
    // ดึงข้อมูลพนักงานและ Username
    $stmt = $conn->prepare("SELECT e.*, u.username FROM employees e 
                            LEFT JOIN users u ON e.emp_id = u.emp_id 
                            WHERE e.emp_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $emp_data = $res->fetch_assoc();
    }
}

$message = "";
$status = "";

// จัดการการส่งข้อมูล (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $emp_id = isset($_POST['emp_id']) ? intval($_POST['emp_id']) : null;
    $emp_code = $_POST['emp_code'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $hire_date = $_POST['hire_date'];
    $dept_id = $_POST['dept_id'];
    $username = $_POST['username'];
    $password = $_POST['password'];

    $conn->begin_transaction();
    try {
        if ($emp_id) {
            // --- กรณีแก้ไข (UPDATE) ---
            $sql_emp = "UPDATE employees SET emp_code=?, first_name=?, last_name=?, email=?, phone=?, hire_date=?, dept_id=? WHERE emp_id=?";
            $stmt_emp = $conn->prepare($sql_emp);
            $stmt_emp->bind_param("ssssssii", $emp_code, $first_name, $last_name, $email, $phone, $hire_date, $dept_id, $emp_id);
            $stmt_emp->execute();

            // อัปเดต User (ถ้ามีการกรอกรหัสผ่านใหม่ให้ Hash และอัปเดต)
            if (!empty($password)) {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $sql_user = "UPDATE users SET username=?, password_hash=? WHERE emp_id=?";
                $stmt_user = $conn->prepare($sql_user);
                $stmt_user->bind_param("ssi", $username, $password_hash, $emp_id);
            } else {
                $sql_user = "UPDATE users SET username=? WHERE emp_id=?";
                $stmt_user = $conn->prepare($sql_user);
                $stmt_user->bind_param("si", $username, $emp_id);
            }
            $stmt_user->execute();
            
            $message = "อัปเดตข้อมูลเรียบร้อยแล้ว!";
        } else {
            // --- กรณีเพิ่มใหม่ (INSERT) ---
            // ตรวจสอบความซ้ำซ้อน
            $check_sql = "SELECT username FROM users WHERE username = ? UNION SELECT emp_code FROM employees WHERE emp_code = ?";
            $stmt_check = $conn->prepare($check_sql);
            $stmt_check->bind_param("ss", $username, $emp_code);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows > 0) {
                throw new Exception("รหัสพนักงาน หรือ Username นี้มีในระบบแล้ว");
            }

            $sql_emp = "INSERT INTO employees (emp_code, first_name, last_name, email, phone, hire_date, dept_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt_emp = $conn->prepare($sql_emp);
            $stmt_emp->bind_param("ssssssi", $emp_code, $first_name, $last_name, $email, $phone, $hire_date, $dept_id);
            $stmt_emp->execute();
            
            $new_id = $conn->insert_id;
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $sql_user = "INSERT INTO users (emp_id, username, password_hash, role) VALUES (?, ?, ?, 'Employee')";
            $stmt_user = $conn->prepare($sql_user);
            $stmt_user->bind_param("iss", $new_id, $username, $password_hash);
            $stmt_user->execute();

            $message = "ลงทะเบียนพนักงานเรียบร้อยแล้ว!";
        }

        $conn->commit();
        $status = "success";
        
        // ส่งค่ากลับสำหรับ AJAX
        if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo "success";
            exit;
        }
    } catch (Exception $e) {
        $conn->rollback();
        $status = "error";
        $message = $e->getMessage();
    }
}
?>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Kanit', sans-serif; }
    </style>
    
<div class="bg-white p-8 rounded-2xl w-full">
    <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800"><?= $is_edit ? 'แก้ไขข้อมูลพนักงาน' : 'สมัครพนักงานใหม่' ?></h1>
        <p class="text-gray-500"><?= $is_edit ? 'แก้ไขรายละเอียดข้อมูลด้านล่าง' : 'กรุณากรอกข้อมูลเพื่อสร้างบัญชีผู้ใช้งาน' ?></p>
    </div>

    <form action="register.php" method="POST" class="space-y-6">
        <!-- Hidden ID สำหรับโหมดแก้ไข -->
        <?php if($is_edit): ?>
            <input type="hidden" name="emp_id" value="<?= $edit_id ?>">
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">รหัสพนักงาน <span class="text-red-500">*</span></label>
                <input type="text" name="emp_code" required value="<?= htmlspecialchars($emp_data['emp_code']) ?>"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">วันที่เริ่มงาน <span class="text-red-500">*</span></label>
                <input type="date" name="hire_date" required value="<?= $emp_data['hire_date'] ?>"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อ <span class="text-red-500">*</span></label>
                <input type="text" name="first_name" required value="<?= htmlspecialchars($emp_data['first_name']) ?>"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">นามสกุล <span class="text-red-500">*</span></label>
                <input type="text" name="last_name" required value="<?= htmlspecialchars($emp_data['last_name']) ?>"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">อีเมล</label>
                <input type="email" name="email" value="<?= htmlspecialchars($emp_data['email']) ?>"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">เบอร์โทรศัพท์</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($emp_data['phone']) ?>"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">แผนก <span class="text-red-500">*</span></label>
                <select name="dept_id" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                    <option value="">-- เลือกแผนก --</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['dept_id'] ?>" <?= $emp_data['dept_id'] == $dept['dept_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dept['dept_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <hr class="my-6">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อผู้ใช้งาน (Username) <span class="text-red-500">*</span></label>
                <input type="text" name="username" required value="<?= htmlspecialchars($emp_data['username']) ?>"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    รหัสผ่าน <?= $is_edit ? '<span class="text-gray-400 font-normal">(เว้นว่างไว้หากไม่ต้องการเปลี่ยน)</span>' : '<span class="text-red-500">*</span>' ?>
                </label>
                <input type="password" name="password" <?= $is_edit ? '' : 'required' ?>
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
        </div>

        <div class="pt-4 flex gap-3">
            <button type="button" onclick="closeModal()" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-600 font-medium py-3 rounded-lg transition">
                ยกเลิก
            </button>
            <button type="submit" 
                class="flex-[2] bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 rounded-lg shadow-lg transition active:scale-[0.98]">
                <?= $is_edit ? 'บันทึกการแก้ไข' : 'ลงทะเบียนพนักงาน' ?>
            </button>
        </div>
    </form>
</div>

<?php if ($message != "" && $status == "error"): ?>
<script>
    Swal.fire({
        title: 'เกิดข้อผิดพลาด!',
        text: '<?= $message ?>',
        icon: 'error',
        confirmButtonText: 'ตกลง'
    });
</script>
<?php endif; ?>