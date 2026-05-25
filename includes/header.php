<?php require_once __DIR__ . '/../config/database.php'; ?>
<?php require_once __DIR__ . '/functions.php'; ?>
<?php 
// Cek halaman aktif
$currentPage = basename($_SERVER['PHP_SELF']); 
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Tracker</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: { 
                        primary: '#4f46e5', // Indigo 600
                        primaryHover: '#4338ca', // Indigo 700
                        secondary: '#64748b' 
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none;  scrollbar-width: none; }
        .toggle-switch { position: relative; display: inline-flex; align-items: center; width: 2.75rem; height: 1.5rem; border-radius: 9999px; background: #cbd5e1; transition: background-color .2s ease; }
        .toggle-switch::after { content: ""; position: absolute; left: .1875rem; top: .1875rem; width: 1.125rem; height: 1.125rem; border-radius: 9999px; background: #fff; box-shadow: 0 1px 2px rgb(15 23 42 / .25); transition: transform .2s ease; }
        .toggle-input:checked + .toggle-switch { background: #4f46e5; }
        .toggle-input:checked + .toggle-switch::after { transform: translateX(1.25rem); }
        .toggle-input:focus-visible + .toggle-switch { outline: 2px solid #818cf8; outline-offset: 2px; }
        .toggle-input:disabled + .toggle-switch { opacity: .5; cursor: not-allowed; }
    </style>
</head>
<body class="text-slate-800 antialiased bg-slate-50">

<?php if (isset($_SESSION['user_id'])): ?>
<div class="flex h-screen overflow-hidden">

    <aside id="sidebar" class="bg-white border-r border-slate-200 w-64 flex flex-col fixed inset-y-0 left-0 z-50 transform -translate-x-full md:relative md:translate-x-0 transition-transform duration-200 ease-in-out">
        
        <div class="h-16 flex items-center px-6 border-b border-slate-100">
            <div class="flex items-center gap-3 font-bold text-xl tracking-tight text-slate-900">
                <div class="bg-primary text-white p-1.5 rounded-lg shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                        <path d="M2.25 2.25a.75.75 0 000 1.5h1.386c.17 0 .318.114.362.278l2.558 9.592a3.752 3.752 0 00-2.806 3.63c0 .414.336.75.75.75h15.75a.75.75 0 000-1.5H5.378A2.25 2.25 0 017.5 15h11.218a.75.75 0 00.674-.421 60.358 60.358 0 002.96-7.228.75.75 0 00-.525-.965A60.864 60.864 0 005.68 4.509l-.232-.867A1.875 1.875 0 003.636 2.25H2.25zM3.75 20.25a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zM16.5 20.25a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0z" />
                    </svg>
                </div>
                MyFinance
            </div>
            <button id="closeSidebar" class="md:hidden ml-auto text-slate-400 hover:text-slate-600 p-1">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
            
            <p class="px-2 text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">Menu Utama</p>
            
            <a href="index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage == 'index.php' ? 'bg-indigo-50 text-primary' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 <?= $currentPage == 'index.php' ? 'text-primary' : 'text-slate-400' ?>">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                </svg>
                Dashboard
            </a>

            <a href="input.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage == 'input.php' ? 'bg-indigo-50 text-primary' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 <?= $currentPage == 'input.php' ? 'text-primary' : 'text-slate-400' ?>">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Catat Pengeluaran
            </a>

            <a href="transactions.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage == 'transactions.php' ? 'bg-indigo-50 text-primary' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 <?= $currentPage == 'transactions.php' ? 'text-primary' : 'text-slate-400' ?>">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />
                </svg>
                Riwayat Transaksi
            </a>

            <a href="analysis.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage == 'analysis.php' ? 'bg-indigo-50 text-primary' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 <?= $currentPage == 'analysis.php' ? 'text-primary' : 'text-slate-400' ?>">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0013.5 3v7.5z" />
                </svg>
                Laporan & Analisis
            </a>

            <a href="pockets.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage == 'pockets.php' ? 'bg-indigo-50 text-primary' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 <?= $currentPage == 'pockets.php' ? 'text-primary' : 'text-slate-400' ?>">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6.75A2.25 2.25 0 0118.75 21H5.25A2.25 2.25 0 013 18.75V12m18 0V8.25A2.25 2.25 0 0018.75 6H5.25A2.25 2.25 0 003 8.25V12" />
                </svg>
                Konfigurasi Pocket
            </a>

            <a href="category_rules.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage == 'category_rules.php' ? 'bg-indigo-50 text-primary' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 <?= $currentPage == 'category_rules.php' ? 'text-primary' : 'text-slate-400' ?>">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.169.659 1.591l8.182 8.182a2.25 2.25 0 003.182 0l4.318-4.318a2.25 2.25 0 000-3.182L11.159 3.659A2.25 2.25 0 009.568 3z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z" />
                </svg>
                Rule Kategori
            </a>

            <a href="notifications.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage == 'notifications.php' ? 'bg-indigo-50 text-primary' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 <?= $currentPage == 'notifications.php' ? 'text-primary' : 'text-slate-400' ?>">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                </svg>
                Notifikasi Summary
            </a>

            <a href="notification_logs.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage == 'notification_logs.php' ? 'bg-indigo-50 text-primary' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 <?= $currentPage == 'notification_logs.php' ? 'text-primary' : 'text-slate-400' ?>">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m5-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Histori Notifikasi
            </a>

            <a href="profile.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage == 'profile.php' ? 'bg-indigo-50 text-primary' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 <?= $currentPage == 'profile.php' ? 'text-primary' : 'text-slate-400' ?>">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                </svg>
                Profil & Pengaturan
            </a>

        </nav>

        <div class="p-4 border-t border-slate-100 bg-slate-50/50">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold uppercase border border-indigo-200 shadow-sm">
                    <?= substr($_SESSION['username'], 0, 1) ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-slate-800 truncate">
                        <a href="profile.php" class="hover:text-primary hover:underline transition">
                            <?= e($_SESSION['username']) ?>
                        </a>
                    </p>
                    <p class="text-xs text-slate-500 truncate">Admin</p>
                </div>
            </div>
            
            <a href="logout.php" class="flex items-center gap-2 w-full px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50 rounded-lg transition-colors border border-transparent hover:border-red-100">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                </svg>
                Logout
            </a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col overflow-hidden relative">
        
        <header class="md:hidden bg-white border-b border-slate-200 h-16 flex items-center px-4 justify-between z-10 shrink-0">
            <div class="flex items-center gap-2 font-bold text-slate-900">
                <span class="text-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                        <path d="M2.25 2.25a.75.75 0 000 1.5h1.386c.17 0 .318.114.362.278l2.558 9.592a3.752 3.752 0 00-2.806 3.63c0 .414.336.75.75.75h15.75a.75.75 0 000-1.5H5.378A2.25 2.25 0 017.5 15h11.218a.75.75 0 00.674-.421 60.358 60.358 0 002.96-7.228.75.75 0 00-.525-.965A60.864 60.864 0 005.68 4.509l-.232-.867A1.875 1.875 0 003.636 2.25H2.25zM3.75 20.25a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zM16.5 20.25a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0z" />
                    </svg>
                </span>
                MyFinance
            </div>
            <button id="openSidebar" class="text-slate-500 hover:text-slate-700 p-2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>
        </header>

        <main class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-8">
            <div class="max-w-5xl mx-auto">
<?php else: ?>
    <main class="min-h-screen">
<?php endif; ?>
