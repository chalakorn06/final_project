<?php
// ตรวจสอบว่ามีการเริ่ม Session หรือยัง
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ตรวจสอบสิทธิ์และชื่อผู้ใช้
$role = $_SESSION['role'] ?? 'Employee';
$username = $_SESSION['username'] ?? 'Guest';
$current_page = basename($_SERVER['PHP_SELF']);
?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Kanit', 'sans-serif'] },
                    colors: { 
                        brand: { 
                            50: '#eff6ff', 
                            100: '#dbeafe',
                            500: '#3b82f6', 
                            600: '#2563eb', 
                            700: '#1d4ed8',
                            800: '#1e40af' 
                        } 
                    }
                }
            }
        }
    </script>
<div id="sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-40 hidden transition-opacity duration-300 opacity-0"></div>

<aside id="main-sidebar" class="fixed top-0 left-0 h-full w-72 bg-brand-600 border-r border-brand-500 z-50 transform -translate-x-full transition-transform duration-300 ease-in-out lg:translate-x-0 shadow-2xl lg:shadow-none flex flex-col">
    
    <div class="h-20 flex-none flex items-center justify-between px-6 border-b border-white/10">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center shadow-lg shadow-blue-900/20">
                <i class="fa-solid fa-calendar-check text-brand-600 text-xl"></i>
            </div>
            <span class="text-xl font-black text-white tracking-tight">ระบบหลังร้านค้า</span>
        </div>
        <button onclick="toggleSidebar()" class="lg:hidden text-white/70 hover:text-white transition-colors p-2">
            <i class="fa-solid fa-xmark text-2xl"></i>
        </button>
    </div>
    
    <nav class="flex-1 overflow-y-auto py-6 px-4 space-y-1 custom-scrollbar">
        <p class="px-4 text-[10px] font-bold text-blue-200 uppercase tracking-[0.2em] mb-4">เมนูหลัก</p>
        
        <!-- พนักงานดูได้แค่ 3 เมนูนี้ -->
        <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3.5 rounded-2xl transition-all group <?php echo $current_page == 'dashboard.php' ? 'bg-white text-brand-600 shadow-lg' : 'text-white/80 hover:bg-white/10 hover:text-white'; ?>">
            <i class="fa-solid fa-gauge-high w-6 text-center group-hover:scale-110 transition-transform"></i>
            <span class="font-bold text-sm">หน้าแรก (Dashboard)</span>
        </a>

        <a href="pos.php" class="flex items-center space-x-3 px-4 py-3.5 rounded-2xl transition-all group <?php echo $current_page == 'pos.php' ? 'bg-white text-brand-600 shadow-lg' : 'text-white/80 hover:bg-white/10 hover:text-white'; ?>">
            <i class="fa-solid fa-cash-register w-6 text-center group-hover:scale-110 transition-transform"></i>
            <span class="font-bold text-sm">POS (ระบบคิดเงิน)</span>
        </a>

        <a href="sales_report.php" class="flex items-center space-x-3 px-4 py-3.5 rounded-2xl transition-all group <?php echo $current_page == 'sales_report.php' ? 'bg-white text-brand-600 shadow-lg' : 'text-white/80 hover:bg-white/10 hover:text-white'; ?>">
            <i class="fa-solid fa-file-invoice-dollar w-6 text-center group-hover:scale-110 transition-transform"></i>
            <span class="font-bold text-sm">รายงานการขาย (Reports)</span>
        </a>

        <!-- จัดการพนักงาน: แอดมินและผู้จัดการ ดูได้เท่านั้น -->
        <?php if($role == 'Admin' || $role == 'Manager'): ?>
        <a href="emp_manage.php" class="flex items-center space-x-3 px-4 py-3.5 rounded-2xl transition-all group <?php echo $current_page == 'emp_manage.php' ? 'bg-white text-brand-600 shadow-lg' : 'text-white/80 hover:bg-white/10 hover:text-white'; ?>">
            <i class="fa-solid fa-users-gear w-6 text-center group-hover:scale-110 transition-transform"></i>
            <span class="font-bold text-sm">ระบบจัดการพนักงาน</span>
        </a>
        <?php endif; ?>

        <!-- สต๊อกและวิเคราะห์ข้อมูล: แอดมิน, ผู้จัดการ และผู้ช่วยผู้จัดการ ดูได้ -->
        <?php if($role == 'Admin' || $role == 'Manager' || $role == 'Assistant Manager'): ?>
        <a href="stock.php" class="flex items-center space-x-3 px-4 py-3.5 rounded-2xl transition-all group <?php echo $current_page == 'stock.php' ? 'bg-white text-brand-600 shadow-lg' : 'text-white/80 hover:bg-white/10 hover:text-white'; ?>">
            <i class="fa-solid fa-boxes-stacked w-6 text-center group-hover:scale-110 transition-transform"></i>
            <span class="font-bold text-sm">จัดการสต๊อกสินค้า</span>
        </a>

        <a href="product_analysis.php" class="flex items-center space-x-3 px-4 py-3.5 rounded-2xl transition-all group <?php echo $current_page == 'product_analysis.php' ? 'bg-white text-brand-600 shadow-lg' : 'text-white/80 hover:bg-white/10 hover:text-white'; ?>">
            <i class="fa-solid fa-chart-pie w-6 text-center group-hover:scale-110 transition-transform"></i>
            <span class="font-bold text-sm">วิเคราะห์ข้อมูลสินค้า</span>
        </a>
        <?php endif; ?>
    </nav>
    
    <div class="flex-none p-4 border-t border-white/10 bg-brand-700/50">
        <div class="bg-brand-800/40 rounded-2xl p-4 flex items-center space-x-3 border border-white/10 mb-3 overflow-hidden">
            <div class="w-10 h-10 flex-none bg-white/10 rounded-xl flex items-center justify-center border border-white/10 text-white">
                <i class="fa-solid fa-user-tie"></i>
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-xs font-bold text-white truncate"><?php echo htmlspecialchars($username); ?></p>
                <span class="inline-block px-2 py-0.5 bg-white/20 text-white text-[8px] font-black rounded-full uppercase">
                    <?php echo htmlspecialchars($role); ?>
                </span>
            </div>
        </div>

        <a href="Logout.php" class="flex items-center justify-center space-x-3 px-4 py-3 rounded-xl bg-red-500 text-white hover:bg-red-600 shadow-lg shadow-red-900/20 transition-all group">
            <i class="fa-solid fa-right-from-bracket group-hover:translate-x-1 transition-transform"></i>
            <span class="font-bold text-sm">ออกจากระบบ</span>
        </a>
    </div>
</aside>

<div class="lg:hidden fixed bottom-6 right-6 z-50">
    <button onclick="toggleSidebar()" class="w-14 h-14 bg-brand-600 text-white rounded-2xl shadow-2xl flex items-center justify-center hover:scale-110 active:scale-95 transition-all focus:outline-none border border-white/20">
        <i id="hamburger-icon" class="fa-solid fa-bars text-xl"></i>
    </button>
</div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('main-sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        const icon = document.getElementById('hamburger-icon');
        const isHidden = sidebar.classList.contains('-translate-x-full');

        if (isHidden) {
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
            setTimeout(() => overlay.classList.add('opacity-100'), 10);
            if(icon) icon.classList.replace('fa-bars', 'fa-xmark');
        } else {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.remove('opacity-100');
            setTimeout(() => {
                overlay.classList.add('hidden');
            }, 300);
            if(icon) icon.classList.replace('fa-xmark', 'fa-bars');
        }
    }

    window.addEventListener('resize', () => {
        if (window.innerWidth >= 1024) {
            const overlay = document.getElementById('sidebar-overlay');
            if(overlay) {
                overlay.classList.add('hidden');
                overlay.classList.remove('opacity-100');
            }
            const icon = document.getElementById('hamburger-icon');
            if(icon) icon.classList.replace('fa-xmark', 'fa-bars');
        }
    });
</script>

<style>
    .custom-scrollbar::-webkit-scrollbar {
        width: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.2);
        border-radius: 10px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: transparent;
    }

    @media (min-width: 1024px) {
        body {
            padding-left: 18rem;
        }
    }
</style>