<?php
/**
 * Header Template — Shared across all pages
 */
if (!defined('PAGE_TITLE')) define('PAGE_TITLE', 'Dashboard');
if (!defined('PAGE_ID')) define('PAGE_ID', 'dashboard');

$currentUser = getCurrentUser();
$userTheme = $_SESSION['user_theme'] ?? 'light';
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($userTheme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(PAGE_TITLE) ?> — <?= APP_NAME ?></title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <!-- GSAP -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <!-- Three.js (only on dashboard) -->
    <?php if (PAGE_ID === 'dashboard'): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <?php endif; ?>
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
</head>
<body class="theme-<?= e($userTheme) ?>">
    
    <!-- ─── THREE.JS BACKGROUND CANVAS ─── -->
    <?php if (PAGE_ID === 'dashboard'): ?>
    <canvas id="three-bg" class="three-background"></canvas>
    <?php endif; ?>

    <!-- ─── APP WRAPPER ─── -->
    <div class="app-wrapper" id="appWrapper">
        
        <!-- ─── SIDEBAR ─── -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <div class="logo-icon">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <span class="logo-text"><?= APP_NAME ?></span>
                </div>
                <button class="sidebar-toggle d-lg-none" id="sidebarClose" aria-label="Close menu">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <nav class="sidebar-nav">
                <ul class="nav-list">
                    <li class="nav-item <?= PAGE_ID === 'dashboard' ? 'active' : '' ?>">
                        <a href="dashboard.php"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
                    </li>
                    <li class="nav-item <?= PAGE_ID === 'income' ? 'active' : '' ?>">
                        <a href="income.php"><i class="fas fa-wallet"></i><span>Income</span></a>
                    </li>
                    <li class="nav-item <?= PAGE_ID === 'expenses' ? 'active' : '' ?>">
                        <a href="expenses.php"><i class="fas fa-receipt"></i><span>Expenses</span></a>
                    </li>
                    <li class="nav-item <?= PAGE_ID === 'friends' ? 'active' : '' ?>">
                        <a href="friends.php"><i class="fas fa-user-friends"></i><span>Friends</span></a>
                    </li>
                    <li class="nav-item <?= PAGE_ID === 'goals' ? 'active' : '' ?>">
                        <a href="goals.php"><i class="fas fa-bullseye"></i><span>Goals</span></a>
                    </li>
                    <li class="nav-item <?= PAGE_ID === 'emi' ? 'active' : '' ?>">
                        <a href="emi.php"><i class="fas fa-calculator"></i><span>EMI</span></a>
                    </li>
                    <li class="nav-item <?= PAGE_ID === 'calendar' ? 'active' : '' ?>">
                        <a href="calendar-view.php"><i class="fas fa-calendar-alt"></i><span>Calendar</span></a>
                    </li>
                    <li class="nav-item <?= PAGE_ID === 'analytics' ? 'active' : '' ?>">
                        <a href="analytics.php"><i class="fas fa-chart-line"></i><span>Analytics</span></a>
                    </li>
                    <li class="nav-item <?= PAGE_ID === 'forecast' ? 'active' : '' ?>">
                        <a href="forecast.php"><i class="fas fa-crystal-ball"></i><span>Forecast</span></a>
                    </li>
                    <li class="nav-item <?= PAGE_ID === 'reports' ? 'active' : '' ?>">
                        <a href="reports.php"><i class="fas fa-file-alt"></i><span>Reports</span></a>
                    </li>
                    <li class="nav-item <?= PAGE_ID === 'networth' ? 'active' : '' ?>">
                        <a href="net-worth.php"><i class="fas fa-coins"></i><span>Net Worth</span></a>
                    </li>
                </ul>
                
                <div class="nav-divider"></div>
                
                <ul class="nav-list">
                    <li class="nav-item <?= PAGE_ID === 'settings' ? 'active' : '' ?>">
                        <a href="settings.php"><i class="fas fa-cog"></i><span>Settings</span></a>
                    </li>
                    <li class="nav-item">
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
                    </li>
                </ul>
            </nav>
            
            <!-- Sidebar user card -->
            <div class="sidebar-footer">
                <div class="user-card">
                    <div class="user-avatar">
                        <?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)) ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?= e($currentUser['name'] ?? 'User') ?></div>
                        <div class="user-email"><?= e($currentUser['email'] ?? '') ?></div>
                    </div>
                </div>
            </div>
        </aside>
        
        <!-- ─── MAIN CONTENT ─── -->
        <main class="main-content" id="mainContent">
            <!-- Top bar -->
            <header class="top-bar">
                <div class="top-bar-left">
                    <button class="hamburger d-lg-none" id="hamburgerBtn" aria-label="Open menu">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="page-title-area">
                        <h1 class="page-title"><?= e(PAGE_TITLE) ?></h1>
                        <p class="page-subtitle">Personal Financial Command Center</p>
                    </div>
                </div>
                <div class="top-bar-right">
                    <div class="current-date">
                        <i class="far fa-calendar"></i>
                        <span><?= date('d M Y') ?></span>
                    </div>
                    <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
                        <i class="fas fa-<?= $userTheme === 'dark' ? 'moon' : 'sun' ?>"></i>
                    </button>
                </div>
            </header>
            
            <!-- Page content container -->
            <div class="content-wrapper">