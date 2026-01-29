<?php
session_start();
include 'api/connect.php';

// จัดการการลบข้อมูล
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM employees WHERE emp_id = $id");
    header("Location: emp_manage.php");
    exit;
}

// ดึงข้อมูลสำหรับ Dashboard
$total_emp = $conn->query("SELECT COUNT(*) as count FROM employees")->fetch_assoc()['count'];
$total_dept = $conn->query("SELECT COUNT(*) as count FROM departments")->fetch_assoc()['count'];
$recent_hires = $conn->query("SELECT COUNT(*) as count FROM employees WHERE hire_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)")->fetch_assoc()['count'];

// ดึงข้อมูลพนักงานพร้อมชื่อแผนก
$emp_query = $conn->query("SELECT e.*, d.dept_name 
                          FROM employees e 
                          LEFT JOIN departments d ON e.dept_id = d.dept_id 
                          ORDER BY e.emp_id DESC");
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ระบบจัดการพนักงาน</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@200;300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Kanit', sans-serif; }
        .modal-active { overflow: hidden; }
        #modalContent::-webkit-scrollbar { width: 6px; }
        #modalContent::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex flex-col">

    <?php if(file_exists('sidebar.php')) include 'sidebar.php'; ?>

    <div class="container mx-auto py-8 px-4">
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-2xl shadow-sm border-l-4 border-blue-500 flex items-center justify-between hover:shadow-md transition">
                <div>
                    <p class="text-sm text-gray-500 font-medium mb-1">พนักงานทั้งหมด</p>
                    <h3 class="text-3xl font-bold text-gray-800"><?php echo number_format($total_emp); ?></h3>
                </div>
                <div class="bg-blue-100 p-4 rounded-full">
                    <i class="fas fa-users text-blue-600 text-2xl"></i>
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-sm border-l-4 border-emerald-500 flex items-center justify-between hover:shadow-md transition">
                <div>
                    <p class="text-sm text-gray-500 font-medium mb-1">จำนวนแผนก</p>
                    <h3 class="text-3xl font-bold text-gray-800"><?php echo number_format($total_dept); ?></h3>
                </div>
                <div class="bg-emerald-100 p-4 rounded-full">
                    <i class="fas fa-sitemap text-emerald-600 text-2xl"></i>
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-sm border-l-4 border-orange-500 flex items-center justify-between hover:shadow-md transition">
                <div>
                    <p class="text-sm text-gray-500 font-medium mb-1">พนักงานใหม่ (เดือนนี้)</p>
                    <h3 class="text-3xl font-bold text-gray-800"><?php echo number_format($recent_hires); ?></h3>
                </div>
                <div class="bg-orange-100 p-4 rounded-full">
                    <i class="fas fa-user-plus text-orange-600 text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm overflow-hidden border border-gray-100">
            <div class="p-6 border-b border-gray-100 flex flex-col md:flex-row justify-between items-center gap-4">
                <div>
                    <h2 class="text-xl font-bold text-gray-800">จัดการข้อมูลพนักงาน</h2>
                    <p class="text-sm text-gray-400">ตรวจสอบและแก้ไขข้อมูลพนักงานในระบบของคุณ</p>
                </div>
                
                <div class="flex flex-wrap gap-3">
                    <button onclick="openModal('dept_manage.php')" class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2.5 rounded-xl font-medium transition flex items-center shadow-lg shadow-emerald-200">
                        <i class="fas fa-sitemap mr-2"></i> เพิ่มแผนก
                    </button>

                    <button onclick="openModal('register.php')" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-xl font-medium transition flex items-center shadow-lg shadow-blue-200">
                        <i class="fas fa-plus-circle mr-2"></i> เพิ่มพนักงานใหม่
                    </button>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50">
                            <th class="p-4 font-semibold text-gray-600 text-sm">รหัสพนักงาน</th>
                            <th class="p-4 font-semibold text-gray-600 text-sm">ชื่อ-นามสกุล</th>
                            <th class="p-4 font-semibold text-gray-600 text-sm">แผนก / ตำแหน่ง</th>
                            <th class="p-4 font-semibold text-gray-600 text-sm text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php while($emp = $emp_query->fetch_assoc()): ?>
                        <tr class="hover:bg-blue-50/50 transition group">
                            <td class="p-4">
                                <span class="bg-gray-100 text-gray-600 text-xs px-2.5 py-1 rounded font-mono font-bold">
                                    #<?php echo htmlspecialchars($emp['emp_code']); ?>
                                </span>
                            </td>
                            <td class="p-4">
                                <div class="font-semibold text-gray-800"><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></div>
                                <div class="text-xs text-gray-400 italic"><?php echo htmlspecialchars($emp['email']); ?></div>
                            </td>
                            <td class="p-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    <?php echo htmlspecialchars($emp['dept_name'] ?? 'ไม่มีแผนก'); ?>
                                </span>
                            </td>
                            <td class="p-4 text-center">
                                <div class="flex justify-center space-x-2">
                                    <button onclick="openModal('register.php?edit=<?php echo $emp['emp_id']; ?>')" 
                                       class="text-blue-500 hover:bg-blue-500 hover:text-white transition w-9 h-9 flex items-center justify-center rounded-lg border border-blue-200"
                                       title="แก้ไขข้อมูล">
                                        <i class="fas fa-pen-to-square"></i>
                                    </button>
                                    <a href="?delete=<?php echo $emp['emp_id']; ?>" 
                                       onclick="return confirm('ยืนยันการลบข้อมูลพนักงาน?')"
                                       class="text-red-500 hover:bg-red-500 hover:text-white transition w-9 h-9 flex items-center justify-center rounded-lg border border-red-200"
                                       title="ลบข้อมูล">
                                        <i class="fas fa-trash-can"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="mainModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-gray-900/60 backdrop-blur-sm" aria-hidden="true" onclick="closeModal()"></div>
            
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-2xl shadow-2xl sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                <div class="absolute top-0 right-0 pt-4 pr-4 z-10">
                    <button type="button" onclick="closeModal()" class="text-gray-400 hover:text-gray-600 transition p-2">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div id="modalContent" class="bg-white max-h-[90vh] overflow-y-auto">
                    </div>
            </div>
        </div>
    </div>

    <script>
        async function openModal(url) {
            const modal = document.getElementById('mainModal');
            const content = document.getElementById('modalContent');
            
            modal.classList.remove('hidden');
            document.body.classList.add('modal-active');
            content.innerHTML = `
                <div class="p-20 text-center">
                    <i class="fas fa-circle-notch fa-spin text-4xl text-blue-600 mb-4"></i>
                    <p class="text-gray-500 font-light">กำลังเตรียมข้อมูล...</p>
                </div>
            `;
            
            try {
                const response = await fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const html = await response.text();
                
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const fragment = doc.querySelector('.bg-white') || doc.body;
                
                content.innerHTML = fragment.outerHTML;

                const scripts = content.querySelectorAll('script');
                scripts.forEach(oldScript => {
                    const newScript = document.createElement('script');
                    Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                    newScript.appendChild(document.createTextNode(oldScript.innerHTML));
                    oldScript.parentNode.replaceChild(newScript, oldScript);
                });

                setupAjaxForm(url);

            } catch (error) {
                content.innerHTML = `<div class="p-10 text-center text-red-500">ผิดพลาด: ${error.message}</div>`;
            }
        }

        function setupAjaxForm(currentUrl) {
            const form = document.querySelector('#modalContent form');
            if(!form) return;

            form.onsubmit = async (e) => {
                e.preventDefault();
                
                Swal.fire({
                    title: 'กำลังดำเนินการ...',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                try {
                    const formData = new FormData(form);
                    const response = await fetch(form.action || currentUrl, {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const result = await response.text();
                    
                    if (result.includes('success')) {
                        Swal.fire({
                            icon: 'success',
                            title: 'สำเร็จ!',
                            text: 'ทำรายการเรียบร้อยแล้ว',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(result, 'text/html');
                        const fragment = doc.querySelector('.bg-white') || doc.body;
                        document.getElementById('modalContent').innerHTML = fragment.outerHTML;
                        setupAjaxForm(currentUrl);
                        Swal.close();
                    }
                } catch (error) {
                    Swal.fire('เกิดข้อผิดพลาด', error.message, 'error');
                }
            };
        }

        function closeModal() {
            document.getElementById('mainModal').classList.add('hidden');
            document.body.classList.remove('modal-active');
        }

        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeModal();
        });
    </script>
</body>
</html>