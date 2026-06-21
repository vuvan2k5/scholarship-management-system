<?php
// ============================================================
// index.php  –  Public landing page
// Redirects authenticated users to their dashboard
// ============================================================

require_once 'config/app.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If already logged in, send to the correct dashboard
if (!empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {
    if ($_SESSION['role'] === 'council') $_SESSION['role'] = 'reviewer';
    $role = $_SESSION['role'];
    if ($role === 'admin')         { header('Location: ' . BASE_URL . '/admin/dashboard.php');    exit; }
    elseif ($role === 'student')   { header('Location: ' . BASE_URL . '/student/dashboard.php');  exit; }
    elseif ($role === 'reviewer')  { header('Location: ' . BASE_URL . '/reviewer/dashboard.php'); exit; }
}

// Fetch live stats from DB (optional — graceful fallback if DB unavailable)
$stats = ['programs' => 120, 'students' => 3800, 'awarded' => 940, 'budget' => '5.2M', 'applications' => 4500];
$programs = [];

try {
    require_once 'config/db.php';
    $pdo = getDB();
    $stats['programs'] = $pdo->query("SELECT COUNT(*) FROM scholarship_programs")->fetchColumn();
    $stats['students'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
    $stats['awarded']  = $pdo->query("SELECT COUNT(*) FROM ranking_results WHERE recommended=1")->fetchColumn();
    $stats['applications'] = $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
    $budget = $pdo->query("SELECT SUM(budget) FROM scholarship_programs")->fetchColumn();
    $stats['budget']   = $budget ? '$' . number_format($budget / 1000000, 1) . 'M' : '—';

    $programs = $pdo->query("
        SELECT id, name, description, budget, end_date AS deadline, status
        FROM scholarship_programs
        WHERE status = 'open'
        ORDER BY id DESC LIMIT 3
    ")->fetchAll();
} catch (Exception $e) {
    // Silently fall back to placeholder values
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Scholarship Management System — A modern platform that helps students apply for, track, and receive scholarships in a transparent and efficient manner.">
  <title>Scholarship Management System — Home</title>

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet" crossorigin="anonymous">
  <!-- Design System (reuse existing) -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

  <style>
    /* ── LANDING PAGE OVERRIDES & TYPOGRAPHY ──────────────── */
    body {
      font-family: 'Inter', system-ui, -apple-system, sans-serif;
      background: #fcfcfd;
      color: #334155;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
    }

    /* Common section titles */
    .section-tag {
      display: inline-block;
      background: var(--primary-light);
      color: var(--primary);
      font-size: 11px;
      font-weight: 700;
      letter-spacing: .12em;
      text-transform: uppercase;
      padding: 6px 16px;
      border-radius: 50px;
      margin-bottom: 16px;
    }
    .section-title {
      font-size: clamp(26px, 4.2vw, 36px);
      font-weight: 900;
      color: var(--gray-900);
      margin-bottom: 12px;
      letter-spacing: -0.02em;
      line-height: 1.25;
    }
    .section-subtitle {
      font-size: 16px;
      color: var(--gray-500);
      max-width: 520px;
      line-height: 1.65;
    }

    /* ── NAVBAR ──────────────────────────────────────────── */
    .lp-navbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;

    padding: 8px 0;

    background: rgba(255,255,255,0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
}
    .lp-navbar.scrolled {
      padding: 12px 0;
      background: rgba(255,255,255,0.95);
      box-shadow: 0 10px 30px -10px rgba(15, 23, 42, 0.08);
      border-bottom-color: rgba(226,232,240,0.8);
    }
    .lp-brand {
      display: flex; align-items: center; gap: 12px;
      text-decoration: none;
    }
    .lp-brand-icon {
      width: 38px; height: 38px;
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-size: 20px;
      box-shadow: 0 4px 12px rgba(37,99,235,0.25);
    }
    .lp-brand-text { font-size: 15.5px; font-weight: 800; color: var(--gray-900); line-height: 1.15; letter-spacing: -0.01em; }
    .lp-brand-sub  { font-size: 10px; color: var(--gray-400); letter-spacing: .05em; font-weight: 600; text-transform: uppercase; }
    .lp-nav-links { display: flex; align-items: center; gap: 4px; }
    .lp-nav-links a {
      font-size: 14px; font-weight: 500; color: var(--gray-600);
      padding: 8px 16px; border-radius: 10px;
      text-decoration: none; transition: all 0.2s ease;
    }
    .lp-nav-links a:hover { background: var(--primary-light); color: var(--primary); }

    /* ── HERO ────────────────────────────────────────────── */
    .hero-section {
      min-height: 75vh;
      background: linear-gradient(145deg, #0f172a 0%, #1e3a5f 45%, #1e40af 100%);
      display: flex; align-items: center;
      position: relative; overflow: hidden;
      padding: 100px 0 50px;
    }
    .hero-bg-orb {
      position: absolute; border-radius: 50%; filter: blur(80px); opacity: .18; pointer-events: none;
    }
    .hero-bg-orb.orb1 {
      width: 600px; height: 600px;
      background: #3b82f6;
      top: -100px; right: -100px;
    }
    .hero-bg-orb.orb2 {
      width: 400px; height: 400px;
      background: #8b5cf6;
      bottom: -80px; left: 20%;
    }
    .hero-badge {
      display: inline-flex; align-items: center; gap: 7px;
      background: rgba(59,130,246,.18);
      border: 1px solid rgba(59,130,246,.35);
      color: #93c5fd;
      font-size: 12px; font-weight: 600;
      padding: 6px 14px; border-radius: 50px;
      margin-bottom: 16px;
      animation: fadeInDown .6s ease both;
    }
    .hero-badge .dot { width: 7px; height: 7px; background: #60a5fa; border-radius: 50%; animation: pulse 1.5s infinite; }
    @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }
    .hero-title {
      font-size: clamp(30px, 4vw, 44px);
      font-weight: 800; line-height: 1.2;
      color: #fff; margin-bottom: 14px;
      animation: fadeInUp .7s .1s ease both;
    }
    .hero-title .highlight {
      background: linear-gradient(135deg, #60a5fa, #a78bfa);
      -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
    }
    .hero-desc {
      font-size: 15px; color: rgba(255,255,255,.72);
      line-height: 1.65; max-width: 540px;
      margin-bottom: 24px;
      animation: fadeInUp .7s .2s ease both;
    }
    .hero-cta {
      display: flex; flex-wrap: wrap; gap: 12px;
      animation: fadeInUp .7s .3s ease both;
    }
    .btn-hero-primary {
      display: inline-flex; align-items: center; gap: 8px;
      background: #2563eb; color: #fff;
      font-size: 14.5px; font-weight: 700;
      padding: 10px 22px; border-radius: 10px;
      text-decoration: none; transition: all .25s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: 0 6px 20px rgba(37,99,235,.4);
    }
    .btn-hero-primary:hover {
      background: #1d4ed8; color: #fff;
      transform: translateY(-2px);
      box-shadow: 0 10px 26px rgba(37,99,235,.5);
    }
    .btn-hero-outline {
      display: inline-flex; align-items: center; gap: 8px;
      background: rgba(255,255,255,.1);
      border: 1.5px solid rgba(255,255,255,.3);
      color: #fff;
      font-size: 14.5px; font-weight: 600;
      padding: 10px 22px; border-radius: 10px;
      text-decoration: none; transition: all .25s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .btn-hero-outline:hover {
      background: rgba(255,255,255,.18); color: #fff;
      transform: translateY(-2px);
    }
    .hero-visual {
      animation: fadeInRight .8s .2s ease both;
    }
    .hero-card-float {
      background: rgba(255,255,255,.06);
      border: 1px solid rgba(255,255,255,.1);
      backdrop-filter: blur(16px);
      border-radius: 16px; padding: 22px;
      color: #fff;
      box-shadow: 0 20px 50px rgba(0,0,0,0.3);
    }
    .hero-card-float .hc-row {
      display: flex; align-items: center; gap: 12px;
      padding: 10px 0;
      border-bottom: 1px solid rgba(255,255,255,.08);
    }
    .hero-card-float .hc-row:last-child { border-bottom: none; padding-bottom: 0; }
    .hero-card-float .hc-row:first-child { padding-top: 0; }
    .hc-icon {
      width: 38px; height: 38px; border-radius: 10px;
      display: flex; align-items: center; justify-content: center; font-size: 16px;
      flex-shrink: 0;
    }
    .hc-label { font-size: 11.5px; color: rgba(255,255,255,.5); margin-bottom: 1px; }
    .hc-val   { font-size: 14px; font-weight: 700; color: #fff; }
    .hc-badge {
      margin-left: auto; font-size: 10.5px; font-weight: 600;
      padding: 3px 8px; border-radius: 20px;
    }
    .hc-badge.green { background: rgba(16,185,129,.2); color: #34d399; }
    .hc-badge.blue  { background: rgba(59,130,246,.2);  color: #93c5fd; }
    .hc-badge.yellow{ background: rgba(245,158,11,.2);  color: #fbbf24; }

    @keyframes fadeInUp    { from{opacity:0;transform:translateY(24px)} to{opacity:1;transform:translateY(0)} }
    @keyframes fadeInDown  { from{opacity:0;transform:translateY(-16px)} to{opacity:1;transform:translateY(0)} }
    @keyframes fadeInRight { from{opacity:0;transform:translateX(32px)} to{opacity:1;transform:translateX(0)} }

    /* ── STATS SECTION ───────────────────────────────────── */
    .stats-section { padding: 100px 0; background: #ffffff; }

    .stat-item {
      text-align: center; padding: 40px 24px;
      background: #ffffff; border-radius: 24px;
      border: 1px solid rgba(226, 232, 240, 0.8);
      box-shadow: 0 10px 30px -10px rgba(15, 23, 42, 0.04), 0 1px 3px rgba(15, 23, 42, 0.02);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .stat-item:hover {
      transform: translateY(-5px);
      box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.08);
      border-color: var(--primary-muted);
    }
    .stat-item .si-icon {
      width: 64px; height: 64px; border-radius: 18px;
      display: flex; align-items: center; justify-content: center;
      font-size: 26px; margin: 0 auto 20px;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.02);
    }
    .stat-item .si-num {
      font-size: 44px; font-weight: 900;
      color: var(--gray-900); line-height: 1;
      margin-bottom: 8px;
      letter-spacing: -0.03em;
    }
    .stat-item .si-label {
      font-size: 14px; font-weight: 600; color: var(--gray-500);
    }
    .counter { display: inline-block; }

    /* ── PROGRAMS SECTION ────────────────────────────────── */
    .programs-section { padding: 100px 0; background: #f8fafc; border-top: 1px solid rgba(226, 232, 240, 0.6); }
    .section-header { margin-bottom: 48px; }

    .program-card {
      background: #ffffff;
      border: 1px solid rgba(226, 232, 240, 0.8);
      border-radius: 24px;
      box-shadow: 0 10px 30px -10px rgba(15, 23, 42, 0.04), 0 1px 3px rgba(15, 23, 42, 0.02);
      overflow: hidden;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      height: 100%;
      display: flex; flex-direction: column;
    }
    .program-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.08);
      border-color: var(--primary-muted);
    }
    .program-card-top {
      padding: 32px 32px 0;
    }
    .program-icon {
      width: 54px; height: 54px; border-radius: 16px;
      display: flex; align-items: center; justify-content: center;
      font-size: 24px; margin-bottom: 20px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.02);
    }
    .program-name {
      font-size: 18px; font-weight: 700;
      color: var(--gray-900); margin-bottom: 12px; line-height: 1.4; letter-spacing: -0.01em;
    }
    .program-desc {
      font-size: 14.5px; color: var(--gray-500);
      line-height: 1.65; margin-bottom: 24px;
    }
    .program-meta {
      display: flex; flex-direction: column; gap: 10px;
      padding: 22px 32px; background: #fafbfc;
      border-top: 1px solid #f1f5f9;
      margin-top: auto;
    }
    .program-meta-row {
      display: flex; align-items: center; justify-content: space-between;
      font-size: 13px;
    }
    .program-meta-row .label { color: var(--gray-400); display: flex; align-items: center; gap: 6px; }
    .program-meta-row .value { font-weight: 600; color: var(--gray-700); }
    .program-footer { padding: 20px 32px; border-top: 1px solid #f1f5f9; }
    .btn-apply {
      display: flex; align-items: center; justify-content: center; gap: 8px;
      background: var(--primary); color: #fff;
      font-size: 14px; font-weight: 700;
      padding: 12px; border-radius: 12px;
      text-decoration: none; transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); width: 100%;
    }
    .btn-apply:hover {
      background: var(--primary-dark);
      color: #fff;
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(37,99,235,.35);
    }

    /* placeholder programs when DB empty */
    .program-placeholder { color: var(--gray-300); }

    /* ── PROCESS SECTION ─────────────────────────────────── */
    .process-section {
      padding: 100px 0;
      background: linear-gradient(135deg, #0b0f19 0%, #1e293b 100%);
      position: relative; overflow: hidden;
    }
    .process-section::before {
      content: ''; position: absolute; inset: 0;
      background: radial-gradient(ellipse 60% 50% at 70% 50%, rgba(59,130,246,.10) 0%, transparent 70%);
    }
    .process-section .section-tag { background: rgba(59,130,246,.15); color: #93c5fd; }
    .process-section .section-title { color: #fff; }
    .process-section .section-subtitle { color: rgba(255,255,255,.5); }

    .process-step {
      position: relative; text-align: center; padding: 0 16px;
    }
    .process-connector {
      position: absolute; top: 40px; left: calc(50% + 40px); right: calc(-50% + 40px);
      height: 2px;
      background: linear-gradient(90deg, rgba(59,130,246,.4), rgba(59,130,246,.05));
      display: none;
    }
    @media(min-width:768px) { .process-connector { display: block; } }

    .step-bubble {
      width: 80px; height: 80px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 30px; margin: 0 auto 24px;
      position: relative; z-index: 1;
      background: rgba(255,255,255,.03);
      border: 1px solid rgba(255,255,255,.1);
      box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.2);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .process-step:hover .step-bubble {
      background: rgba(59, 130, 246, 0.15);
      border-color: rgba(59, 130, 246, 0.6);
      box-shadow: 0 0 20px rgba(59, 130, 246, 0.3);
      transform: scale(1.06);
    }
    .step-num {
      position: absolute; top: -2px; right: -2px;
      width: 24px; height: 24px; border-radius: 50%;
      background: var(--primary); color: #fff;
      font-size: 11px; font-weight: 800;
      display: flex; align-items: center; justify-content: center;
      box-shadow: 0 2px 8px rgba(37,99,235,0.4);
    }
    .step-title { font-size: 17px; font-weight: 700; color: #fff; margin-bottom: 10px; letter-spacing: -0.01em; }
    .step-desc  { font-size: 14px; color: rgba(255,255,255,.45); line-height: 1.6; }

    /* ── FAQ SECTION ─────────────────────────────────────── */
    .faq-section { padding: 100px 0; background: #ffffff; border-top: 1px solid rgba(226, 232, 240, 0.6); }
    .faq-section .accordion-item {
      background: #ffffff;
      border: 1px solid rgba(226, 232, 240, 0.8) !important;
      border-radius: 16px !important;
      margin-bottom: 14px;
      overflow: hidden;
      box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.02), 0 2px 4px -2px rgba(15, 23, 42, 0.02);
      transition: all 0.25s ease;
    }
    .faq-section .accordion-item:hover {
      box-shadow: 0 12px 20px -8px rgba(15, 23, 42, 0.08);
      border-color: rgba(226, 232, 240, 1) !important;
    }
    .faq-section .accordion-button {
      font-size: 15px; font-weight: 600;
      color: var(--gray-800); background: #ffffff;
      padding: 22px 26px;
      border-radius: 16px !important;
    }
    .faq-section .accordion-button:not(.collapsed) {
      color: var(--primary); background: var(--primary-light);
      box-shadow: none;
    }
    .faq-section .accordion-button:focus { box-shadow: none; }
    .faq-section .accordion-button::after { filter: none; }
    .faq-section .accordion-button:not(.collapsed)::after {
      filter: invert(38%) sepia(76%) saturate(1500%) hue-rotate(210deg);
    }
    .faq-section .accordion-body {
      font-size: 14px; color: var(--gray-600);
      line-height: 1.75; padding: 0 26px 22px;
    }

    /* ── CTA BANNER ──────────────────────────────────────── */
    .cta-section { padding: 80px 0; background: #f8fafc; border-top: 1px solid rgba(226, 232, 240, 0.6); }
    .cta-inner {
      background: linear-gradient(135deg, var(--primary) 0%, #7c3aed 100%);
      border-radius: 28px; padding: 60px 48px;
      text-align: center; position: relative; overflow: hidden;
      box-shadow: 0 20px 40px -15px rgba(37, 99, 235, 0.3);
    }
    .cta-inner::before {
      content: ''; position: absolute;
      width: 300px; height: 300px; border-radius: 50%;
      background: rgba(255,255,255,.06);
      top: -80px; right: -60px; pointer-events: none;
    }
    .cta-inner::after {
      content: ''; position: absolute;
      width: 200px; height: 200px; border-radius: 50%;
      background: rgba(255,255,255,.05);
      bottom: -60px; left: -40px; pointer-events: none;
    }
    .cta-inner h2 { font-size: clamp(26px,4vw,38px); font-weight: 900; color: #fff; margin-bottom: 16px; letter-spacing: -0.02em; line-height: 1.2; }
    .cta-inner p  { font-size: 16.5px; color: rgba(255,255,255,.8); margin-bottom: 32px; max-width: 520px; margin-left: auto; margin-right: auto; line-height: 1.6; }

    .btn-cta-white {
      display: inline-flex; align-items: center; gap: 8px;
      background: #fff; color: var(--primary);
      font-size: 15px; font-weight: 750;
      padding: 12px 28px; border-radius: 12px;
      text-decoration: none; transition: all .25s ease;
      box-shadow: 0 6px 20px rgba(0,0,0,.15);
    }
    .btn-cta-white:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
      color: var(--primary-dark);
    }
    .btn-cta-ghost {
      display: inline-flex; align-items: center; gap: 8px;
      background: rgba(255,255,255,.12);
      border: 1.5px solid rgba(255,255,255,.3);
      color: #fff;
      font-size: 15px; font-weight: 600;
      padding: 12px 28px; border-radius: 12px;
      text-decoration: none; transition: all .25s ease;
    }
    .btn-cta-ghost:hover { background: rgba(255,255,255,.2); color: #fff; transform: translateY(-2px); }

    /* ── FOOTER ──────────────────────────────────────────── */
    .lp-footer {
      background: var(--gray-900);
      padding: 64px 0 28px;
      color: rgba(255,255,255,.55);
      border-top: 1px solid rgba(255,255,255,.05);
    }
    .lp-footer .footer-brand { margin-bottom: 14px; }
    .lp-footer .footer-desc { font-size: 13.5px; line-height: 1.7; max-width: 280px; margin-bottom: 20px; }
    .lp-footer h6 {
      font-size: 11px; font-weight: 700;
      text-transform: uppercase; letter-spacing: .12em;
      color: rgba(255,255,255,.35); margin-bottom: 18px;
    }
    .lp-footer ul { list-style: none; padding: 0; margin: 0; }
    .lp-footer ul li { margin-bottom: 10px; }
    .lp-footer ul li a {
      font-size: 13.5px; color: rgba(255,255,255,.55);
      text-decoration: none; transition: color .2s;
    }
    .lp-footer ul li a:hover { color: #fff; }
    .lp-footer .footer-divider { border-color: rgba(255,255,255,.08); margin: 40px 0 24px; }
    .lp-footer .footer-bottom { font-size: 12.5px; }
    .role-chips { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
    .role-chip {
      font-size: 11px; font-weight: 600; padding: 4px 12px; border-radius: 50px;
    }

    /* ── SCROLL ANIMATION ────────────────────────────────── */
    .reveal { opacity: 0; transform: translateY(28px); transition: opacity .6s ease, transform .6s ease; }
    .reveal.visible { opacity: 1; transform: translateY(0); }
    .reveal-delay-1 { transition-delay: .1s; }
    .reveal-delay-2 { transition-delay: .2s; }
    .reveal-delay-3 { transition-delay: .3s; }
    .reveal-delay-4 { transition-delay: .4s; }

    /* ── SCROLL OFFSET for fixed header (announce bar 34px + navbar ~72px) ── */
    #hero, #partners, #stats, #programs, #process, #faq, #final-cta {
      scroll-margin-top: 110px;
    }

    /* ── ANNOUNCEMENT BANNER (static, fixed) ─────────────────── */
    .announce-bar {
      position: fixed; top: 0; left: 0; right: 0; z-index: 1100;
      background: linear-gradient(90deg, #1d4ed8 0%, #4f46e5 50%, #7c3aed 100%);
      height: 44px;
      display: flex; align-items: center; justify-content: center;
      text-align: center;
    }
    .announce-bar .announce-text {
      font-size: 13px; font-weight: 600;
      color: #fff;
      letter-spacing: .04em;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      padding: 0 16px;
    }
    @media (max-width: 480px) {
      .announce-bar .announce-text { font-size: 11.5px; }
    }

    /* Offset navbar + banner */
    .lp-navbar { top: 44px !important; }
    .hero-section { padding-top: 144px !important; }

    /* ── UNIVERSITY ANNOUNCEMENT BANNER ──────────────────── */
    .partners-section {
      padding: 0;
      background: #ffffff;
      border-top: 1px solid #e5e7eb;
      border-bottom: 1px solid #e5e7eb;
      overflow: hidden;
      margin-top: 40px;
      box-shadow: 0 2px 12px rgba(15,23,42,.05);
    }

    /* One unified flex row — logo left, ticker right */
    .announce-strip {
      display: flex;
      align-items: stretch;
      min-height: 90px;
    }

    /* ── Logo panel — stationary, never moves ── */
    .announce-logo-panel {
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 14px 36px;
      background: #ffffff;
      /* right border only — acts as a gentle separator, no colored bar */
      border-right: 1px solid #e5e7eb;
      position: relative;
      z-index: 2;
    }
    .announce-logo-panel img {
      height: 82px;
      width: auto;
      object-fit: contain;
      display: block;
    }

    /* ── Ticker panel — clips overflow, centers text vertically ── */
    .announce-ticker-panel {
      flex: 1;
      overflow: hidden;
      display: flex;
      align-items: center;
      background: #ffffff;
      position: relative;
    }
    /* Soft right-edge fade only — keeps clean entry on left */
    .announce-ticker-panel::after {
      content: '';
      position: absolute;
      top: 0; right: 0; bottom: 0;
      width: 64px;
      background: linear-gradient(270deg, #ffffff, transparent);
      pointer-events: none;
      z-index: 2;
    }

    /* ── Scrolling track ── */
    .announce-ticker-track {
      display: flex;
      align-items: center;
      gap: 0;
      animation: tickerScroll 30s linear infinite;
      width: max-content;
      will-change: transform;
      padding-left: 40px; /* breathing room from logo border */
    }
    .announce-ticker-panel:hover .announce-ticker-track {
      animation-play-state: paused;
    }

    /* ── Text segments ── */
    .announce-ticker-item {
      display: inline-flex;
      align-items: center;
      white-space: nowrap;
      padding: 0 0;
    }
    .ticker-text {
      font-size: 14px;
      font-weight: 500;
      color: #374151;
      letter-spacing: 0.025em;
      line-height: 1;
    }
    .ticker-text strong {
      font-weight: 700;
      color: #1e293b;
    }
    /* Pipe separator between segments */
    .ticker-pipe {
      display: inline-block;
      margin: 0 32px;
      color: #d1d5db;
      font-weight: 300;
      font-size: 18px;
      line-height: 1;
      vertical-align: middle;
    }

    /* ── Keyframe ── */
    @keyframes tickerScroll {
      0%   { transform: translateX(0); }
      100% { transform: translateX(-50%); }
    }

    /* ── Responsive ── */
    @media (max-width: 768px) {
      .partners-section { margin-top: 28px; }
      .announce-strip { min-height: 72px; }
      .announce-logo-panel { padding: 12px 24px; }
      .announce-logo-panel img { height: 58px; }
      .announce-ticker-track { padding-left: 28px; }
      .ticker-text { font-size: 13px; }
      .ticker-pipe { margin: 0 22px; font-size: 16px; }
    }
    @media (max-width: 480px) {
      .partners-section { margin-top: 16px; }
      .announce-strip { min-height: 58px; }
      .announce-logo-panel { padding: 10px 16px; }
      .announce-logo-panel img { height: 44px; }
      .announce-ticker-track { padding-left: 18px; }
      .ticker-text { font-size: 12px; }
      .ticker-pipe { margin: 0 16px; font-size: 14px; }
    }



    /* ── FINAL CTA SECTION ───────────────────────────────── */
    .final-cta-section {
      padding: 100px 0;
      background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #1e40af 100%);
      position: relative; overflow: hidden;
    }
    .final-cta-section::before {
      content: ''; position: absolute;
      width: 700px; height: 700px; border-radius: 50%;
      background: radial-gradient(circle, rgba(99,102,241,.18) 0%, transparent 70%);
      top: -200px; right: -150px; pointer-events: none;
    }
    .final-cta-section::after {
      content: ''; position: absolute;
      width: 500px; height: 500px; border-radius: 50%;
      background: radial-gradient(circle, rgba(16,185,129,.12) 0%, transparent 70%);
      bottom: -180px; left: -100px; pointer-events: none;
    }
    .final-cta-box {
      position: relative; z-index: 1;
      text-align: center; max-width: 680px; margin: 0 auto;
    }
    .final-cta-badge {
      display: inline-flex; align-items: center; gap: 8px;
      background: rgba(99,102,241,.18);
      border: 1px solid rgba(99,102,241,.35);
      color: #a5b4fc;
      font-size: 11.5px; font-weight: 600; letter-spacing: .1em; text-transform: uppercase;
      padding: 6px 16px; border-radius: 50px;
      margin-bottom: 24px;
    }
    .final-cta-title {
      font-size: clamp(28px, 4.5vw, 46px);
      font-weight: 900; color: #fff;
      line-height: 1.15; letter-spacing: -0.025em;
      margin-bottom: 18px;
    }
    .final-cta-title span {
      background: linear-gradient(135deg, #60a5fa, #a78bfa);
      -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
    }
    .final-cta-desc {
      font-size: 16.5px; color: rgba(255,255,255,.7);
      line-height: 1.65; margin-bottom: 40px;
    }
    .final-cta-btns {
      display: flex; flex-wrap: wrap; gap: 14px; justify-content: center;
    }
    .btn-fcta-primary {
      display: inline-flex; align-items: center; gap: 9px;
      background: #fff; color: #1d4ed8;
      font-size: 15px; font-weight: 700;
      padding: 13px 30px; border-radius: 12px;
      text-decoration: none;
      box-shadow: 0 8px 24px rgba(0,0,0,.2);
      transition: all .25s ease;
    }
    .btn-fcta-primary:hover {
      background: #f0f9ff; color: #1d4ed8;
      transform: translateY(-3px);
      box-shadow: 0 14px 32px rgba(0,0,0,.25);
    }
    .btn-fcta-ghost {
      display: inline-flex; align-items: center; gap: 9px;
      background: rgba(255,255,255,.1);
      border: 1.5px solid rgba(255,255,255,.3);
      color: #fff;
      font-size: 15px; font-weight: 600;
      padding: 13px 30px; border-radius: 12px;
      text-decoration: none;
      transition: all .25s ease;
    }
    .btn-fcta-ghost:hover {
      background: rgba(255,255,255,.18); color: #fff;
      transform: translateY(-3px);
    }
    .final-cta-stats {
      display: flex; justify-content: center; gap: 48px;
      margin-top: 56px; flex-wrap: wrap;
    }
    .fcta-stat {
      text-align: center;
    }
    .fcta-stat-num {
      font-size: 30px; font-weight: 900; color: #fff;
      letter-spacing: -0.02em;
    }
    .fcta-stat-label {
      font-size: 12px; font-weight: 500; color: rgba(255,255,255,.5);
      margin-top: 4px; text-transform: uppercase; letter-spacing: .08em;
    }
  </style>
</head>
<body>

<!-- ═══════════════════════════════════════════
     NAVBAR
════════════════════════════════════════════ -->
<nav class="lp-navbar" id="lpNavbar">
  <div class="container">
    <div class="d-flex align-items-center justify-content-between">
      <!-- Brand (scrolls to hero on same page) -->
      <a href="#hero" class="lp-brand" id="nav-brand">
    <img src="<?= BASE_URL ?>/assets/images/logoupdate.png"
         alt="Scholarship System"
         style="height:80px;">
</a>

      <!-- Desktop Nav -->
      <div class="lp-nav-links d-none d-md-flex">
        <a href="#hero" id="nav-home">Home</a>
        <a href="#stats" id="nav-stats">Statistics</a>
        <a href="#programs" id="nav-programs">Scholarships</a>
        <a href="#process" id="nav-process">Application Process</a>
        <a href="#faq" id="nav-faq">FAQ</a>
      </div>

      <!-- Auth Buttons -->
      <div class="d-flex align-items-center gap-2">
        <a href="<?= BASE_URL ?>/login.php" class="btn btn-secondary btn-sm d-none d-sm-inline-flex" id="nav-login">
          <i class="bi bi-box-arrow-in-right"></i> Login
        </a>
        <a href="<?= BASE_URL ?>/login.php" class="btn btn-primary btn-sm" id="nav-apply">
          <i class="bi bi-mortarboard"></i>
          <span class="d-none d-sm-inline">Apply Now</span>
          <span class="d-sm-none">Apply</span>
        </a>
      </div>
    </div>
  </div>
</nav>

<!-- ═══════════════════════════════════════════
     ANNOUNCEMENT BANNER
════════════════════════════════════════════ -->
<div class="announce-bar" role="banner" aria-label="Site Announcement">
  <p class="announce-text">
    🎓 Scholarship Applications for Academic Year 2026 are Now Open
  </p>
</div>


<!-- ═══════════════════════════════════════════
     HERO
════════════════════════════════════════════ -->
<section class="hero-section" id="hero">
  <div class="hero-bg-orb orb1"></div>
  <div class="hero-bg-orb orb2"></div>

  <div class="container position-relative">
    <div class="row align-items-center gy-4">

      <!-- Left: Text -->
      <div class="col-lg-6">
        <div class="hero-badge">
          <span class="dot"></span>
          Vietnam's #1 Scholarship Platform
        </div>
        <h1 class="hero-title">
          Unlock Your<br>
          <span class="highlight">Scholarship</span><br>
          Opportunity Today
        </h1>
        <p class="hero-desc">
          A modern scholarship management system — apply online,
          track progress transparently, and receive results quickly.
          From submission to award, everything in one platform.
        </p>
        <div class="hero-cta">
          <a href="<?= BASE_URL ?>/login.php" class="btn-hero-primary" id="hero-apply-btn">
            <i class="bi bi-rocket-takeoff-fill"></i>
            Get Started
          </a>
          <a href="#process" class="btn-hero-outline" id="hero-process-btn">
            <i class="bi bi-play-circle"></i>
            View Process
          </a>
        </div>

        <!-- Trust badges -->
        <div class="d-flex flex-wrap align-items-center gap-3 mt-3">
          <div style="font-size:12.5px;color:rgba(255,255,255,.45);">Trusted by:</div>
          <div class="role-chips">
            <span class="role-chip badge-student">👨‍🎓 Students</span>
            <span class="role-chip badge-reviewer" style="background:rgba(16,185,129,.15);color:#34d399;">📋 Review Council</span>
            <span class="role-chip badge-admin" style="background:rgba(139,92,246,.15);color:#c4b5fd;">⚙️ Administrators</span>
          </div>
        </div>
      </div>

      <!-- Right: Floating card -->
      <div class="col-lg-5 offset-lg-1 hero-visual">
        <div class="hero-card-float">
          <div style="font-size:13px;font-weight:700;color:rgba(255,255,255,.6);letter-spacing:.06em;text-transform:uppercase;margin-bottom:18px;">
            📊 System Overview
          </div>

          <div class="hc-row">
            <div class="hc-icon" style="background:rgba(59,130,246,.2);">🎓</div>
            <div>
              <div class="hc-label">Open Scholarships</div>
              <div class="hc-val"><?= htmlspecialchars($stats['programs']) ?> programs</div>
            </div>
            <span class="hc-badge green">● Open</span>
          </div>

          <div class="hc-row">
            <div class="hc-icon" style="background:rgba(16,185,129,.2);">👨‍🎓</div>
            <div>
              <div class="hc-label">Registered Students</div>
              <div class="hc-val"><?= number_format($stats['students']) ?> students</div>
            </div>
            <span class="hc-badge blue">● Enrolled</span>
          </div>

          <div class="hc-row">
            <div class="hc-icon" style="background:rgba(245,158,11,.2);">🏆</div>
            <div>
              <div class="hc-label">Scholarships Awarded</div>
              <div class="hc-val"><?= number_format($stats['awarded']) ?> recipients</div>
            </div>
            <span class="hc-badge yellow">● Awarded</span>
          </div>

          <div class="hc-row">
            <div class="hc-icon" style="background:rgba(139,92,246,.2);">💰</div>
            <div>
              <div class="hc-label">Total Scholarship Value</div>
              <div class="hc-val"><?= htmlspecialchars($stats['budget']) ?></div>
            </div>
            <span class="hc-badge green">● Budget</span>
          </div>

          <!-- Progress bar decoration -->
          <div style="margin-top:20px;padding-top:16px;border-top:1px solid rgba(255,255,255,.08);">
            <div style="display:flex;justify-content:space-between;font-size:11.5px;color:rgba(255,255,255,.45);margin-bottom:8px;">
              <span>Approval Rate</span>
              <span style="color:#34d399;font-weight:600;">87%</span>
            </div>
            <div style="height:6px;background:rgba(255,255,255,.08);border-radius:99px;overflow:hidden;">
              <div style="height:100%;width:87%;background:linear-gradient(90deg,#3b82f6,#34d399);border-radius:99px;"></div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>


<!-- ═══════════════════════════════════════════
     UNIVERSITY ANNOUNCEMENT BANNER
════════════════════════════════════════════ -->
<section class="partners-section" id="partners" aria-label="University Announcement">
  <div class="announce-strip">

    <!-- Fixed logo — never moves -->
    <div class="announce-logo-panel">
      <img
        src="<?= BASE_URL ?>/assets/images/vnu-is-logo.png"
        alt="VNU International School"
        title="VNU International School — Đại học Quốc gia Hà Nội"
      >
    </div>

    <!-- Text ticker — scrolls right to left, logo stays put -->
    <div class="announce-ticker-panel">
      <div class="announce-ticker-track">

        <!-- Copy 1 -->
        <span class="announce-ticker-item">
          <span class="ticker-text"><strong>Supporting Academic Excellence</strong> &amp; Student Development</span>
          <span class="ticker-pipe">|</span>
          <span class="ticker-text">Scholarship Applications for Academic Year <strong>2025–2026</strong> Are Now Open</span>
          <span class="ticker-pipe">|</span>
          <span class="ticker-text">Apply online, track your progress, and receive results transparently</span>
          <span class="ticker-pipe">|</span>
        </span>

        <!-- Copy 2 — exact duplicate for seamless infinite loop -->
        <span class="announce-ticker-item" aria-hidden="true">
          <span class="ticker-text"><strong>Supporting Academic Excellence</strong> &amp; Student Development</span>
          <span class="ticker-pipe">|</span>
          <span class="ticker-text">Scholarship Applications for Academic Year <strong>2025–2026</strong> Are Now Open</span>
          <span class="ticker-pipe">|</span>
          <span class="ticker-text">Apply online, track your progress, and receive results transparently</span>
          <span class="ticker-pipe">|</span>
        </span>

      </div>
    </div>

  </div>
</section>


<!-- ═══════════════════════════════════════════
     STATISTICS
════════════════════════════════════════════ -->
<section class="stats-section" id="stats">
  <div class="container">
    <div class="text-center mb-5 reveal">
      <span class="section-tag">Statistics</span>
      <h2 class="section-title">Impressive Numbers</h2>
      <p class="section-subtitle mx-auto">Thousands of students have been supported through our scholarship platform.</p>
    </div>

    <div class="row g-4">
      <div class="col-6 col-lg-3 reveal reveal-delay-1">
        <div class="stat-item">
          <div class="si-icon" style="background:var(--primary-light);color:var(--primary);">
            <i class="bi bi-award-fill"></i>
          </div>
          <div class="si-num">
            <span class="counter" data-target="<?= (int)$stats['programs'] ?>">0</span>
          </div>
          <div class="si-label">Scholarship Programs</div>
        </div>
      </div>
      <div class="col-6 col-lg-3 reveal reveal-delay-2">
        <div class="stat-item">
          <div class="si-icon" style="background:var(--success-light);color:var(--success);">
            <i class="bi bi-people-fill"></i>
          </div>
          <div class="si-num">
            <span class="counter" data-target="<?= (int)$stats['students'] ?>">0</span>
          </div>
          <div class="si-label">Registered Students</div>
        </div>
      </div>
      <div class="col-6 col-lg-3 reveal reveal-delay-3">
        <div class="stat-item">
          <div class="si-icon" style="background:var(--warning-light);color:var(--warning);">
            <i class="bi bi-trophy-fill"></i>
          </div>
          <div class="si-num">
            <span class="counter" data-target="<?= (int)$stats['awarded'] ?>">0</span>
          </div>
          <div class="si-label">Scholarships Awarded</div>
        </div>
      </div>
      <div class="col-6 col-lg-3 reveal reveal-delay-4">
        <div class="stat-item">
          <div class="si-icon" style="background:var(--info-light);color:var(--info);">
            <i class="bi bi-file-earmark-text-fill"></i>
          </div>
          <div class="si-num">
            <span class="counter" data-target="<?= (int)$stats['applications'] ?>">0</span>
          </div>
          <div class="si-label">Total Applications</div>
        </div>
      </div>
    </div>
  </div>
</section>


<!-- ═══════════════════════════════════════════
     FEATURED PROGRAMS
════════════════════════════════════════════ -->
<section class="programs-section" id="programs">
  <div class="container">
    <div class="row align-items-end section-header">
      <div class="col-lg-7 reveal">
        <span class="section-tag">Featured Scholarships</span>
        <h2 class="section-title">Programs Currently Accepting Applications</h2>
        <p class="section-subtitle">Explore scholarships that match your profile and apply directly through our platform.</p>
      </div>
      <div class="col-lg-5 text-lg-end reveal reveal-delay-1">
        <a href="<?= BASE_URL ?>/login.php" class="btn btn-outline-primary">
          <i class="bi bi-grid"></i> View All Programs
        </a>
      </div>
    </div>

    <div class="row g-4">
      <?php
      $icons   = ['bi-award-fill','bi-mortarboard-fill','bi-star-fill'];
      $colors  = [
        ['bg'=>'var(--primary-light)',  'color'=>'var(--primary)'],
        ['bg'=>'var(--success-light)',  'color'=>'var(--success)'],
        ['bg'=>'var(--warning-light)',  'color'=>'var(--warning)'],
      ];
      $placeholders = [
        ['name'=>'Academic Excellence Scholarship','description'=>'For students with outstanding academic performance, GPA ≥ 3.5, and notable community involvement.','budget'=>15000000,'deadline'=>'2024-08-31'],
        ['name'=>'Need-Based Support Grant','description'=>'Supporting students from low-income families to continue their higher education journey without financial barriers.','budget'=>8000000,'deadline'=>'2024-09-15'],
        ['name'=>'Research & Innovation Award','description'=>'Empowering young researchers and innovators with funding to pursue meaningful academic projects and publications.','budget'=>20000000,'deadline'=>'2024-10-01'],
      ];
      $rows = !empty($programs) ? $programs : $placeholders;
      foreach ($rows as $i => $prog):
        $c = $colors[$i % 3];
        $ic = $icons[$i % 3];
        $isEmpty = empty($programs);
      ?>
      <div class="col-lg-4 reveal reveal-delay-<?= $i+1 ?>">
        <div class="program-card<?= $isEmpty ? ' program-placeholder' : '' ?>">
          <div class="program-card-top">
            <div class="program-icon" style="background:<?= $c['bg'] ?>;color:<?= $c['color'] ?>;">
              <i class="bi <?= $ic ?>"></i>
            </div>
            <div class="program-name"><?= htmlspecialchars($prog['name']) ?></div>
            <p class="program-desc"><?= htmlspecialchars(mb_strimwidth($prog['description'] ?? '', 0, 120, '…')) ?></p>
          </div>
          <div class="program-meta">
            <div class="program-meta-row">
              <span class="label"><i class="bi bi-cash-coin"></i> Budget</span>
              <span class="value"><?= isset($prog['budget']) ? '$' . number_format($prog['budget'] / 1000000, 1) . 'M' : '—' ?></span>
            </div>
            <div class="program-meta-row">
              <span class="label"><i class="bi bi-calendar3"></i> Deadline</span>
              <span class="value"><?= isset($prog['deadline']) ? date('M d, Y', strtotime($prog['deadline'])) : '—' ?></span>
            </div>
          </div>
          <?php if (!$isEmpty): ?>
          <div class="program-footer">
            <a href="<?= BASE_URL ?>/login.php" class="btn-apply" id="apply-program-<?= $prog['id'] ?>">
              <i class="bi bi-send-fill"></i> Apply Now
            </a>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>


<!-- ═══════════════════════════════════════════
     HOW IT WORKS
════════════════════════════════════════════ -->
<section class="process-section" id="process">
  <div class="container position-relative">
    <div class="text-center mb-5 reveal">
      <span class="section-tag">How It Works</span>
      <h2 class="section-title">Simple 4-Step Process</h2>
      <p class="section-subtitle mx-auto">From registration to receiving your scholarship — everything is straightforward and transparent.</p>
    </div>

    <div class="row g-4 justify-content-center">
      <div class="col-6 col-lg-3 reveal reveal-delay-1">
        <div class="process-step">
          <div class="process-connector"></div>
          <div class="step-bubble">
            📝
            <span class="step-num">1</span>
          </div>
          <div class="step-title">Register</div>
          <div class="step-desc">Create your student account and complete your profile with academic information.</div>
        </div>
      </div>
      <div class="col-6 col-lg-3 reveal reveal-delay-2">
        <div class="process-step">
          <div class="process-connector"></div>
          <div class="step-bubble">
            🔍
            <span class="step-num">2</span>
          </div>
          <div class="step-title">Explore</div>
          <div class="step-desc">Browse available scholarships and find programs that match your academic profile.</div>
        </div>
      </div>
      <div class="col-6 col-lg-3 reveal reveal-delay-3">
        <div class="process-step">
          <div class="process-connector"></div>
          <div class="step-bubble">
            📤
            <span class="step-num">3</span>
          </div>
          <div class="step-title">Apply</div>
          <div class="step-desc">Submit your application with required documents through our secure online portal.</div>
        </div>
      </div>
      <div class="col-6 col-lg-3 reveal reveal-delay-4">
        <div class="process-step">
          <div class="step-bubble">
            🏆
            <span class="step-num">4</span>
          </div>
          <div class="step-title">Receive</div>
          <div class="step-desc">Track your application status in real-time and receive your scholarship award.</div>
        </div>
      </div>
    </div>
  </div>
</section>


<!-- ═══════════════════════════════════════════
     FAQ
════════════════════════════════════════════ -->
<section class="faq-section" id="faq">
  <div class="container">
    <div class="row">
      <div class="col-lg-5 reveal">
        <span class="section-tag">FAQ</span>
        <h2 class="section-title">Frequently Asked Questions</h2>
        <p class="section-subtitle">Everything you need to know about applying for scholarships through our platform.</p>
      </div>
      <div class="col-lg-7 reveal reveal-delay-1">
        <div class="accordion" id="faqAccordion">

          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1" id="faq-btn-1">
                Who is eligible to apply for scholarships?
              </button>
            </h2>
            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
              <div class="accordion-body">
                Eligibility varies by scholarship program. Generally, you need to be an enrolled student at a participating institution. Specific requirements such as GPA thresholds, financial need criteria, or field of study may apply to individual programs.
              </div>
            </div>
          </div>

          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2" id="faq-btn-2">
                What documents do I need to submit?
              </button>
            </h2>
            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
              <div class="accordion-body">
                Typically required documents include: academic transcripts, proof of enrollment, identification documents, and a personal statement. Some programs may also require letters of recommendation or financial statements. Check each program's specific requirements before applying.
              </div>
            </div>
          </div>

          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3" id="faq-btn-3">
                How long does the review process take?
              </button>
            </h2>
            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
              <div class="accordion-body">
                The review process typically takes 2–4 weeks after the application deadline. You can track your application status in real-time through your student dashboard. You will receive email notifications at each stage of the review process.
              </div>
            </div>
          </div>

          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4" id="faq-btn-4">
                Can I apply for multiple scholarships?
              </button>
            </h2>
            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
              <div class="accordion-body">
                Yes! You can apply for multiple scholarship programs simultaneously, provided you meet the eligibility criteria for each. Our platform allows you to manage and track all your applications from a single dashboard.
              </div>
            </div>
          </div>

          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5" id="faq-btn-5">
                How will I receive my scholarship funds?
              </button>
            </h2>
            <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
              <div class="accordion-body">
                Once your scholarship is approved, funds are typically disbursed directly to your institution's student account or via bank transfer, depending on the program's terms. The disbursement timeline and method will be clearly communicated in your award notification.
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</section>


<!-- ═══════════════════════════════════════════
     FINAL CTA
════════════════════════════════════════════ -->
<section class="final-cta-section" id="final-cta">
  <div class="container">
    <div class="final-cta-box reveal">
      <div class="final-cta-badge">
        <i class="bi bi-stars"></i>
        Start Your Journey Today
      </div>
      <h2 class="final-cta-title">
        Ready to <span>Unlock Your</span><br>Scholarship?
      </h2>
      <p class="final-cta-desc">
        Join thousands of students who have successfully secured funding for their education. 
        Our platform makes the application process simple, transparent, and efficient.
      </p>
      <div class="final-cta-btns">
        <a href="<?= BASE_URL ?>/login.php" class="btn-fcta-primary" id="final-cta-apply">
          <i class="bi bi-rocket-takeoff-fill"></i>
          Apply Now — It's Free
        </a>
        <a href="#faq" class="btn-fcta-ghost" id="final-cta-faq">
          <i class="bi bi-question-circle"></i>
          Learn More
        </a>
      </div>

      <div class="final-cta-stats">
        <div class="fcta-stat">
          <div class="fcta-stat-num"><?= (int)$stats['programs'] ?>+</div>
          <div class="fcta-stat-label">Programs</div>
        </div>
        <div class="fcta-stat">
          <div class="fcta-stat-num"><?= number_format((int)$stats['students']) ?>+</div>
          <div class="fcta-stat-label">Students</div>
        </div>
        <div class="fcta-stat">
          <div class="fcta-stat-num"><?= number_format((int)$stats['awarded']) ?>+</div>
          <div class="fcta-stat-label">Awarded</div>
        </div>
        <div class="fcta-stat">
          <div class="fcta-stat-num"><?= htmlspecialchars($stats['budget']) ?></div>
          <div class="fcta-stat-label">Total Value</div>
        </div>
      </div>
    </div>
  </div>
</section>


<!-- ═══════════════════════════════════════════
     FOOTER
════════════════════════════════════════════ -->
<footer class="lp-footer">
  <div class="container">
    <div class="row g-5">
      <div class="col-lg-4">
        <div class="footer-brand">
          <a href="#hero" class="lp-brand" id="footer-brand">
            <div class="lp-brand-icon">🎓</div>
            <div>
              <div class="lp-brand-text" style="color:#fff;">Scholarship</div>
              <div class="lp-brand-sub">Management System</div>
            </div>
          </a>
        </div>
        <p class="footer-desc">
          A modern platform empowering students to discover, apply for, and receive scholarships with full transparency.
        </p>
        <div class="role-chips">
          <span class="role-chip" style="background:rgba(37,99,235,.2);color:#93c5fd;">Students</span>
          <span class="role-chip" style="background:rgba(16,185,129,.2);color:#34d399;">Reviewers</span>
          <span class="role-chip" style="background:rgba(139,92,246,.2);color:#c4b5fd;">Admins</span>
        </div>
      </div>

      <div class="col-6 col-lg-2">
        <h6>Platform</h6>
        <ul>
          <li><a href="#hero" id="footer-home">Home</a></li>
          <li><a href="#programs" id="footer-programs">Scholarships</a></li>
          <li><a href="#process" id="footer-process">How It Works</a></li>
          <li><a href="#faq" id="footer-faq">FAQ</a></li>
        </ul>
      </div>

      <div class="col-6 col-lg-2">
        <h6>Account</h6>
        <ul>
          <li><a href="<?= BASE_URL ?>/login.php" id="footer-login">Login</a></li>
          <li><a href="<?= BASE_URL ?>/login.php" id="footer-register">Register</a></li>
          <li><a href="<?= BASE_URL ?>/student/dashboard.php" id="footer-dashboard">Dashboard</a></li>
        </ul>
      </div>

      <div class="col-lg-4">
        <h6>About</h6>
        <p style="font-size:13.5px;line-height:1.7;">
          Built for Vietnam National University — International School (VNU-IS) to streamline scholarship administration and empower student success.
        </p>
        <div style="margin-top:16px;">
          <span style="font-size:12px;color:rgba(255,255,255,.3);">Powered by</span>
          <div style="font-size:13px;font-weight:600;color:rgba(255,255,255,.6);margin-top:4px;">VNU International School</div>
        </div>
      </div>
    </div>

    <hr class="footer-divider">
    <div class="footer-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
      <span>© <?= date('Y') ?> Scholarship Management System. All rights reserved.</span>
      <span style="color:rgba(255,255,255,.3);">VNU International School</span>
    </div>
  </div>
</footer>


<!-- ═══════════════════════════════════════════
     SCRIPTS
════════════════════════════════════════════ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script>
// ── Navbar scroll effect ──────────────────────────────────
const navbar = document.getElementById('lpNavbar');
window.addEventListener('scroll', () => {
  navbar.classList.toggle('scrolled', window.scrollY > 20);
}, { passive: true });

// ── Intersection Observer for reveal animations ───────────
const revealEls = document.querySelectorAll('.reveal');
const revealObserver = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.classList.add('visible');
      revealObserver.unobserve(entry.target);
    }
  });
}, { threshold: 0.12 });
revealEls.forEach(el => revealObserver.observe(el));

// ── Counter animation ─────────────────────────────────────
function animateCounter(el) {
  const target = parseInt(el.dataset.target, 10);
  if (!target) return;
  const duration = 1800;
  const step = target / (duration / 16);
  let current = 0;
  const timer = setInterval(() => {
    current = Math.min(current + step, target);
    el.textContent = Math.floor(current).toLocaleString();
    if (current >= target) clearInterval(timer);
  }, 16);
}

const counterEls = document.querySelectorAll('.counter[data-target]');
const counterObserver = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      animateCounter(entry.target);
      counterObserver.unobserve(entry.target);
    }
  });
}, { threshold: 0.3 });
counterEls.forEach(el => counterObserver.observe(el));
</script>
</body>
</html>
