<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — Portal Santri</title>
    <style>

        /* tambahan dari salma :) */

        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Amiri:wght@400;700&display=swap');

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --sidebar-w:    248px;
            --header-h:     56px;

            /* Palet Hijau Pesantren */
            --c-primary:    #1A6B3A;
            --c-primary-dk: #145530;
            --c-primary-lt: #E8F5EE;
            --c-primary-bd: #A8D5BB;
            --c-accent:     #C8972B;   /* emas */
            --c-accent-lt:  #FEF9EC;

            /* Netral */
            --c-bg:       #F4F6F3;
            --c-surface:  #FFFFFF;
            --c-border:   #DDE3DA;
            --c-text:     #1C2B1E;
            --c-muted:    #5A6B5C;
            --c-hint:     #8A9E8C;

            /* Status */
            --c-success:    #15803D;
            --c-success-bg: #F0FDF4;
            --c-warning:    #92400E;
            --c-warning-bg: #FFFBEB;
            --c-danger:     #991B1B;
            --c-danger-bg:  #FEF2F2;
            --c-info:       #1E40AF;
            --c-info-bg:    #EFF6FF;

            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 14px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,.07);
            --shadow-md: 0 4px 16px rgba(0,0,0,.1);
        }




        /* ═══════════════════════════════════════
   SANTRI SHARED STYLES
   Include via @push('styles') or layout
   ═══════════════════════════════════════ */
:root {
    --p:       #1e6b4f;   /* primary green pesantren */
    --p-dark:  #155238;
    --p-light: #e8f5ee;
    --gold:    #c8922a;
    --gold-lt: #fdf3e0;
    --red:     #c0392b;
    --red-lt:  #fdf0ef;
    --blue:    #1a6db5;
    --blue-lt: #e8f1fb;
    --gray-50: #f8f9fa;
    --gray-100:#f1f3f5;
    --gray-200:#e9ecef;
    --gray-300:#dee2e6;
    --gray-400:#ced4da;
    --gray-500:#adb5bd;
    --gray-600:#6c757d;
    --gray-700:#495057;
    --gray-800:#343a40;
    --gray-900:#212529;
    --radius:  12px;
    --shadow:  0 2px 12px rgba(0,0,0,.08);
    --shadow-md:0 6px 24px rgba(0,0,0,.12);
}

/* Page wrapper */
.s-page { padding: 24px 0; }

/* ── Page Header ── */
.s-header {
    display: flex; align-items: flex-start;
    justify-content: space-between; flex-wrap: wrap;
    gap: 14px; margin-bottom: 24px;
}
.s-title  { font-size: 1.4rem; font-weight: 700; color: var(--gray-900); margin: 0 0 3px; }
.s-sub    { font-size: .85rem; color: var(--gray-600); margin: 0; }

/* ── Card ── */
.s-card {
    background: #fff; border: 1px solid var(--gray-200);
    border-radius: var(--radius); padding: 20px;
    box-shadow: var(--shadow);
}
.s-card-title { font-size: .95rem; font-weight: 700; color: var(--gray-800); margin: 0 0 14px; }

/* ── Badge ── */
.s-badge {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: .7rem; font-weight: 700; padding: 3px 10px;
    border-radius: 999px; white-space: nowrap; letter-spacing: .02em;
}
.s-badge-green  { background: var(--p-light);  color: var(--p); }
.s-badge-gold   { background: var(--gold-lt);  color: var(--gold); }
.s-badge-red    { background: var(--red-lt);   color: var(--red); }
.s-badge-blue   { background: var(--blue-lt);  color: var(--blue); }
.s-badge-gray   { background: var(--gray-100); color: var(--gray-600); }

/* ── Buttons ── */
.s-btn {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 9px 18px; border-radius: 8px; font-size: .85rem;
    font-weight: 600; cursor: pointer; border: none;
    text-decoration: none; transition: filter .15s, background .15s;
}
.s-btn:hover { filter: brightness(.92); }
.s-btn-primary { background: var(--p);       color: #fff; }
.s-btn-outline { background: #fff; color: var(--gray-700); border: 1.5px solid var(--gray-300); }
.s-btn-outline:hover { background: var(--gray-50); color: var(--gray-900); filter: none; }
.s-btn-danger  { background: var(--red);     color: #fff; }
.s-btn-gold    { background: var(--gold);    color: #fff; }
.s-btn-sm { padding: 6px 13px; font-size: .78rem; }

/* ── Form controls ── */
.s-label { font-size: .75rem; font-weight: 700; color: var(--gray-700); text-transform: uppercase; letter-spacing: .04em; margin-bottom: 5px; display: block; }
.s-control {
    width: 100%; padding: 9px 12px; border: 1.5px solid var(--gray-300);
    border-radius: 8px; font-size: .875rem; color: var(--gray-900);
    background: var(--gray-50); transition: border-color .15s, box-shadow .15s;
}
.s-control:focus { outline: none; border-color: var(--p); box-shadow: 0 0 0 3px rgba(30,107,79,.12); }

/* ── Filter bar ── */
.s-filter { background: #fff; border: 1px solid var(--gray-200); border-radius: var(--radius); padding: 16px 18px; margin-bottom: 20px; box-shadow: var(--shadow); }
.s-filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px,1fr)); gap: 12px; align-items: end; }

/* ── Empty state ── */
.s-empty { text-align: center; padding: 56px 20px; color: var(--gray-500); }
.s-empty-icon { width: 60px; height: 60px; background: var(--gray-100); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 14px; }
.s-empty-title { font-size: .95rem; font-weight: 700; color: var(--gray-700); margin: 0 0 5px; }
.s-empty-desc  { font-size: .85rem; margin: 0; }

/* ── Alert ── */
.s-alert { padding: 12px 16px; border-radius: 8px; font-size: .875rem; margin-bottom: 18px; display: flex; align-items: center; gap: 10px; }
.s-alert-success { background: var(--p-light); color: var(--p); border: 1px solid #b2d8c3; }
.s-alert-error   { background: var(--red-lt);  color: var(--red); border: 1px solid #f5c6c2; }

/* ── Stats row ── */
.s-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px,1fr)); gap: 14px; margin-bottom: 22px; }
.s-stat-card { background: #fff; border: 1px solid var(--gray-200); border-radius: var(--radius); padding: 16px 18px; text-align: center; box-shadow: var(--shadow); }
.s-stat-val   { font-size: 1.6rem; font-weight: 800; color: var(--gray-900); line-height: 1; }
.s-stat-label { font-size: .75rem; color: var(--gray-500); margin-top: 4px; }

/* ── Table ── */
.s-table-wrap { overflow-x: auto; }
.s-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
.s-table th { background: var(--gray-50); color: var(--gray-600); font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; padding: 10px 14px; text-align: left; border-bottom: 2px solid var(--gray-200); white-space: nowrap; }
.s-table td { padding: 11px 14px; border-bottom: 1px solid var(--gray-100); color: var(--gray-800); vertical-align: middle; }
.s-table tr:last-child td { border-bottom: none; }
.s-table tr:hover td { background: var(--gray-50); }

/* ── Month/Year nav ── */
.s-period { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.s-period select.s-control { width: auto; min-width: 100px; }




        html, body {
            height: 100%;
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            font-size: 14px;
            color: var(--c-text);
            background: var(--c-bg);
        }

        /* ── Sidebar ─────────────────────────────────────────── */
        .sidebar {
            position: fixed; top: 0; left: 0;
            width: var(--sidebar-w); height: 100vh;
            background: var(--c-primary-dk);
            display: flex; flex-direction: column;
            z-index: 100; overflow-y: auto;
        }

        /* Header logo */
        .sidebar-logo {
            padding: 20px 16px 16px;
            background: var(--c-primary);
            display: flex; align-items: center; gap: 12px;
            border-bottom: 1px solid rgba(255,255,255,.1);
        }
        .logo-icon {
            width: 40px; height: 40px; border-radius: 10px;
            background: rgba(255,255,255,.15);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Amiri', serif; font-size: 20px; color: #fff;
        }
        .logo-title { font-size: 14px; font-weight: 700; color: #fff; }
        .logo-sub   { font-size: 11px; color: rgba(255,255,255,.65); margin-top: 1px; }

        /* Prayer time strip */
        .prayer-strip {
            background: rgba(255,255,255,.08);
            padding: 8px 16px;
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,.08);
        }
        .prayer-strip-label { font-size: 10px; color: rgba(255,255,255,.55); }
        .prayer-strip-val   { font-size: 13px; font-weight: 600; color: var(--c-accent); }
        .prayer-strip-name  { font-size: 10px; color: rgba(255,255,255,.7); text-transform: capitalize; }

        /* Nav */
        .nav-section {
            padding: 14px 16px 4px;
            font-size: 9.5px; font-weight: 700;
            color: rgba(255,255,255,.35);
            letter-spacing: .08em; text-transform: uppercase;
        }
        .nav-link {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 12px; margin: 1px 8px;
            border-radius: var(--radius-sm);
            color: rgba(255,255,255,.72);
            font-size: 13px; font-weight: 500;
            text-decoration: none; transition: all .15s;
        }
        .nav-link:hover { background: rgba(255,255,255,.1); color: #fff; }
        .nav-link.active { background: rgba(255,255,255,.18); color: #fff; }
        .nav-icon { width: 18px; text-align: center; font-size: 14px; opacity: .85; }
        .nav-badge {
            margin-left: auto;
            background: var(--c-accent); color: #fff;
            font-size: 10px; font-weight: 700;
            padding: 1px 6px; border-radius: 10px;
        }

        /* Sidebar user */
        .sidebar-user {
            margin-top: auto;
            padding: 12px 14px;
            background: rgba(0,0,0,.2);
            display: flex; align-items: center; gap: 10px;
        }
        .user-avatar {
            width: 34px; height: 34px; border-radius: 50%;
            background: rgba(255,255,255,.2);
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 13px; color: #fff;
            flex-shrink: 0;
        }
        .user-name { font-size: 13px; font-weight: 600; color: #fff; }
        .user-role { font-size: 11px; color: rgba(255,255,255,.55); }

        /* ── Main Layout ─────────────────────────────────────── */
        .main-wrap { margin-left: var(--sidebar-w); min-height: 100vh; display: flex; flex-direction: column; }

        /* Top header */
        .top-header {
            position: sticky; top: 0; z-index: 50;
            height: var(--header-h);
            background: var(--c-surface);
            border-bottom: 1px solid var(--c-border);
            display: flex; align-items: center; padding: 0 28px; gap: 16px;
        }
        .breadcrumb { font-size: 13px; color: var(--c-muted); display: flex; align-items: center; gap: 6px; }
        .breadcrumb a { color: var(--c-muted); text-decoration: none; }
        .breadcrumb a:hover { color: var(--c-primary); }
        .breadcrumb .current { color: var(--c-text); font-weight: 600; }
        .header-actions { margin-left: auto; display: flex; gap: 8px; align-items: center; }
        .header-date { font-size: 12px; color: var(--c-muted); }

        /* ── Page body ───────────────────────────────────────── */
        .page-body { flex: 1; padding: 28px; }
        .page-title { font-size: 20px; font-weight: 700; margin-bottom: 4px; }
        .page-sub   { font-size: 13px; color: var(--c-muted); margin-bottom: 24px; }

        /* ── Flash messages ──────────────────────────────────── */
        .flash { display: flex; align-items: flex-start; gap: 10px; padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px; font-size: 13px; }
        .flash-success { background: var(--c-success-bg); color: var(--c-success); border: 1px solid #BBF7D0; }
        .flash-error   { background: var(--c-danger-bg);  color: var(--c-danger);  border: 1px solid #FECACA; }
        .flash-warning { background: var(--c-warning-bg); color: var(--c-warning); border: 1px solid #FDE68A; }

        /* ── Cards ───────────────────────────────────────────── */
        .card { background: var(--c-surface); border: 1px solid var(--c-border); border-radius: var(--radius-lg); padding: 20px 24px; margin-bottom: 20px; }
        .card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
        .card-title  { font-size: 14px; font-weight: 600; }

        /* Accent card (hijau) */
        .card-primary {
            background: linear-gradient(135deg, var(--c-primary) 0%, var(--c-primary-dk) 100%);
            color: #fff; border: none;
        }
        .card-primary .card-title { color: rgba(255,255,255,.8); }

        /* ── Metrics ─────────────────────────────────────────── */
        .metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; margin-bottom: 24px; }
        .metric  { background: var(--c-surface); border: 1px solid var(--c-border); border-radius: var(--radius-md); padding: 14px 16px; }
        .metric-label { font-size: 11px; color: var(--c-muted); margin-bottom: 6px; font-weight: 500; }
        .metric-val   { font-size: 26px; font-weight: 700; line-height: 1; }
        .metric-val.primary { color: var(--c-primary); }
        .metric-val.success { color: var(--c-success); }
        .metric-val.warning { color: var(--c-warning); }
        .metric-val.danger  { color: var(--c-danger); }
        .metric-val.accent  { color: var(--c-accent); }

        /* ── Table ───────────────────────────────────────────── */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        thead th { padding: 8px 14px; text-align: left; font-size: 11px; font-weight: 600; color: var(--c-hint); letter-spacing: .04em; text-transform: uppercase; border-bottom: 1px solid var(--c-border); }
        tbody td { padding: 12px 14px; border-bottom: 1px solid var(--c-border); }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover td { background: var(--c-bg); }

        /* ── Badges ──────────────────────────────────────────── */
        .badge { display: inline-block; padding: 3px 9px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .badge-success { background: var(--c-success-bg); color: var(--c-success); }
        .badge-warning { background: var(--c-warning-bg); color: var(--c-warning); }
        .badge-danger  { background: var(--c-danger-bg);  color: var(--c-danger); }
        .badge-info    { background: var(--c-info-bg);    color: var(--c-info); }
        .badge-gray    { background: var(--c-bg); color: var(--c-muted); }
        .badge-primary { background: var(--c-primary-lt); color: var(--c-primary); }
        .badge-accent  { background: var(--c-accent-lt);  color: var(--c-accent); }

        /* Warna mutabaah */
        .badge-hijau   { background: #D1FAE5; color: #065F46; }
        .badge-merah   { background: #FEE2E2; color: #991B1B; }
        .badge-kuning  { background: #FEF9C3; color: #854D0E; }
        .badge-biru    { background: #DBEAFE; color: #1E40AF; }

        /* ── Buttons ─────────────────────────────────────────── */
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: var(--radius-sm); font-size: 13px; font-weight: 500; font-family: inherit; cursor: pointer; border: 1px solid var(--c-border); background: var(--c-surface); color: var(--c-text); text-decoration: none; transition: all .15s; }
        .btn:hover { background: var(--c-bg); }
        .btn-primary { background: var(--c-primary); color: #fff; border-color: var(--c-primary); }
        .btn-primary:hover { background: var(--c-primary-dk); }
        .btn-accent  { background: var(--c-accent);  color: #fff; border-color: var(--c-accent); }
        .btn-danger  { background: var(--c-danger-bg); color: var(--c-danger); border-color: #FECACA; }
        .btn-danger:hover { background: #FEE2E2; }
        .btn-sm { padding: 5px 11px; font-size: 12px; }

        /* ── Forms ───────────────────────────────────────────── */
        .form-grid  { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
        .form-full  { grid-column: 1 / -1; }
        .form-group { display: flex; flex-direction: column; gap: 5px; }
        label.lbl   { font-size: 12px; font-weight: 600; color: var(--c-muted); }
        input, select, textarea { padding: 9px 12px; border: 1px solid var(--c-border); border-radius: var(--radius-sm); font-size: 13px; font-family: inherit; color: var(--c-text); background: var(--c-surface); transition: border-color .15s; width: 100%; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: var(--c-primary); box-shadow: 0 0 0 3px rgba(26,107,58,.12); }
        textarea { resize: vertical; min-height: 80px; }
        .form-hint  { font-size: 11px; color: var(--c-hint); }
        .form-error { font-size: 11px; color: var(--c-danger); }

        /* ── Progress bar ────────────────────────────────────── */
        .progress { height: 6px; border-radius: 3px; background: var(--c-bg); overflow: hidden; }
        .progress-fill { height: 100%; border-radius: 3px; background: var(--c-primary); transition: width .4s; }
        .progress-fill.accent { background: var(--c-accent); }

        /* ── Info list ───────────────────────────────────────── */
        .info-list .row { display: flex; justify-content: space-between; align-items: center; padding: 9px 0; border-bottom: 1px solid var(--c-border); font-size: 13px; }
        .info-list .row:last-child { border-bottom: none; }
        .info-list .lbl { color: var(--c-muted); font-weight: 500; }
        .info-list .val { font-weight: 600; text-align: right; }

        /* ── Two-col layout ──────────────────────────────────── */
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .three-col { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }

        /* ── Prayer times widget ─────────────────────────────── */
        .prayer-card { display: flex; flex-direction: column; align-items: center; gap: 2px; padding: 10px; background: var(--c-primary-lt); border-radius: var(--radius-md); border: 1px solid var(--c-primary-bd); }
        .prayer-card.active { background: var(--c-primary); }
        .prayer-card.active .prayer-name, .prayer-card.active .prayer-time { color: #fff; }
        .prayer-name { font-size: 11px; font-weight: 600; color: var(--c-primary); text-transform: capitalize; }
        .prayer-time { font-size: 16px; font-weight: 700; color: var(--c-primary); font-variant-numeric: tabular-nums; }

        /* ── Arabic text ─────────────────────────────────────── */
        .arabic { font-family: 'Amiri', serif; font-size: 22px; line-height: 2; text-align: right; color: var(--c-text); direction: rtl; }
        .latin  { font-size: 12px; color: var(--c-muted); line-height: 1.6; }
        .terjemahan { font-size: 12px; color: var(--c-text); line-height: 1.6; font-style: italic; }

        /* ── Mutabaah card ───────────────────────────────────── */
        .mutabaah-sesi { padding: 12px 14px; border-radius: var(--radius-md); border: 1px solid var(--c-border); margin-bottom: 8px; display: flex; align-items: center; gap: 14px; }
        .mutabaah-icon { width: 36px; height: 36px; border-radius: 8px; background: var(--c-primary-lt); display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }

        /* ── Checkin card ────────────────────────────────────── */
        .checkin-card { background: linear-gradient(135deg, var(--c-primary) 0%, var(--c-primary-dk) 100%); border-radius: var(--radius-lg); padding: 24px 28px; color: #fff; margin-bottom: 20px; display: flex; align-items: center; gap: 24px; }
        .checkin-clock { font-size: 40px; font-weight: 700; font-variant-numeric: tabular-nums; letter-spacing: -1px; }
        .checkin-date  { font-size: 13px; opacity: .75; margin-bottom: 12px; }
        .btn-white     { background: rgba(255,255,255,.95); color: var(--c-primary); border: none; font-weight: 600; }
        .btn-white:hover { background: #fff; }
        .btn-white-outline { background: transparent; color: #fff; border: 1px solid rgba(255,255,255,.4); }
        .btn-white-outline:hover { background: rgba(255,255,255,.12); }

        /* ── Tab bar ─────────────────────────────────────────── */
        .tabs { display: flex; gap: 4px; border-bottom: 1px solid var(--c-border); margin-bottom: 20px; }
        .tab  { padding: 8px 16px; font-size: 13px; font-weight: 500; color: var(--c-muted); border-bottom: 2px solid transparent; cursor: pointer; text-decoration: none; margin-bottom: -1px; }
        .tab:hover { color: var(--c-primary); }
        .tab.active { color: var(--c-primary); border-bottom-color: var(--c-primary); }

        /* ── Pagination ──────────────────────────────────────── */
        .pagination { display: flex; gap: 4px; justify-content: center; margin-top: 16px; }
        .pagination a, .pagination span { padding: 6px 11px; border-radius: var(--radius-sm); border: 1px solid var(--c-border); font-size: 13px; text-decoration: none; color: var(--c-text); }
        .pagination .active { background: var(--c-primary); color: #fff; border-color: var(--c-primary); }
        .pagination a:hover { background: var(--c-bg); }

        /* ── Empty state ─────────────────────────────────────── */
        .empty { text-align: center; padding: 48px 20px; color: var(--c-hint); }
        .empty-icon { font-size: 40px; margin-bottom: 10px; }
        .empty-text { font-size: 14px; }

        /* ── Ayat block ──────────────────────────────────────── */
        .ayat-block { padding: 16px; border-radius: var(--radius-md); border: 1px solid var(--c-border); margin-bottom: 12px; }
        .ayat-number { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: var(--c-primary); color: #fff; font-size: 11px; font-weight: 700; margin-bottom: 8px; }

        @media (max-width: 768px) {
            .two-col, .three-col { grid-template-columns: 1fr; }
            .sidebar { transform: translateX(-100%); }
            .main-wrap { margin-left: 0; }
        }

        @media print {
            .sidebar, .top-header, .btn, .page-sub, .no-print { display: none !important; }
            .main-wrap { margin-left: 0; }
            .card { border: none; box-shadow: none; }
        }
    </style>
    @stack('styles')
</head>
<body>

{{-- ── Sidebar ───────────────────────────────────────────────────── --}}
<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon">م</div>
        <div>
            <div class="logo-title">Portal Santri</div>
            <div class="logo-sub">Sistem Pesantren</div>
        </div>
    </div>

    {{-- Prayer time strip --}}
    <div class="prayer-strip" id="sidebar-prayer">
        <div>
            <div class="prayer-strip-label">Waktu Sholat Berikutnya</div>
            <div class="prayer-strip-name" id="sp-name">Memuat...</div>
        </div>
        <div class="prayer-strip-val" id="sp-time">--:--</div>
    </div>

    <div style="flex:1;overflow-y:auto;padding:8px 0">
        <div class="nav-section">Utama</div>
        <a href="{{ route('santri.dashboard') }}" class="nav-link {{ request()->routeIs('santri.dashboard') ? 'active' : '' }}">
            <span class="nav-icon">⊞</span> Dashboard
        </a>
        <a href="{{ route('santri.attendance.index') }}" class="nav-link {{ request()->routeIs('santri.attendance.*') ? 'active' : '' }}">
            <span class="nav-icon">✔</span> Absensi
        </a>
        <a href="{{ route('santri.schedule.index') }}" class="nav-link {{ request()->routeIs('santri.schedule.*') ? 'active' : '' }}">
            <span class="nav-icon">◷</span> Jadwal
        </a>
        <a href="{{ route('santri.prayer.today') }}" class="nav-link {{ request()->routeIs('santri.prayer.*') ? 'active' : '' }}">
            <span class="nav-icon">🕌</span> Jadwal Sholat
        </a>

        <div class="nav-section">Al-Quran & Ngaji</div>
        <a href="{{ route('santri.quran.index') }}" class="nav-link {{ request()->routeIs('santri.quran.*') ? 'active' : '' }}">
            <span class="nav-icon">📖</span> Al-Quran
        </a>
        <a href="{{ route('santri.mutabaah.index') }}" class="nav-link {{ request()->routeIs('santri.mutabaah.*') ? 'active' : '' }}">
            <span class="nav-icon">📜</span> Kartu Ngaji
        </a>

        <div class="nav-section">Laporan</div>
        <a href="{{ route('santri.daily-report.index') }}" class="nav-link {{ request()->routeIs('santri.daily-report.*') ? 'active' : '' }}">
            <span class="nav-icon">📋</span> Laporan Harian
        </a>
        <a href="{{ route('santri.monthly-report.index') }}" class="nav-link {{ request()->routeIs('santri.monthly-report.*') ? 'active' : '' }}">
            <span class="nav-icon">📊</span> Laporan Bulanan
        </a>

        <div class="nav-section">Pengajuan</div>
        <a href="{{ route('santri.permission.index') }}" class="nav-link {{ request()->routeIs('santri.permission.*') ? 'active' : '' }}">
            <span class="nav-icon">📝</span> Izin
        </a>

        <div class="nav-section">Lainnya</div>
        <a href="{{ route('santri.notes.index') }}" class="nav-link {{ request()->routeIs('santri.notes.*') ? 'active' : '' }}">
            <span class="nav-icon">💬</span> Catatan Ustadz
        </a>
        <a href="{{ route('santri.performance.index') }}" class="nav-link {{ request()->routeIs('santri.performance.*') ? 'active' : '' }}">
            <span class="nav-icon">⭐</span> Performa
        </a>
        <a href="{{ route('santri.holiday.index') }}" class="nav-link {{ request()->routeIs('santri.holiday.*') ? 'active' : '' }}">
            <span class="nav-icon">🌙</span> Hari Libur
        </a>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar">{{ strtoupper(substr(auth()->user()->name ?? 'S', 0, 2)) }}</div>
        <div>
            <div class="user-name">{{ auth()->user()->name ?? 'Santri' }}</div>
            <div class="user-role">{{ auth()->user()->department ?? 'Kelas' }} · {{ auth()->user()->position ?? 'Kamar' }}</div>
        </div>
    </div>
</aside>

{{-- ── Main Wrap ──────────────────────────────────────────────────── --}}
<div class="main-wrap">
    <header class="top-header">
        <nav class="breadcrumb">
            <a href="{{ route('santri.dashboard') }}">🏠</a>
            @hasSection('breadcrumb')
                <span style="color:var(--c-hint)">/</span>
                @yield('breadcrumb')
            @endif
        </nav>
        <div class="header-actions">
            <span class="header-date">{{ now()->isoFormat('dddd, D MMM Y') }}</span>
            <form method="POST" action="{{ route('logout') }}" style="display:inline">
                @csrf
                <button type="submit" class="btn btn-sm">Logout</button>
            </form>
        </div>
    </header>

    <main class="page-body">
        @if(session('success'))
            <div class="flash flash-success">✓ {{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="flash flash-error">✕ {{ session('error') }}</div>
        @endif
        @if(session('warning'))
            <div class="flash flash-warning">⚠ {{ session('warning') }}</div>
        @endif

        @yield('content')
    </main>
</div>

@stack('scripts')
<script>
// Fetch waktu sholat berikutnya untuk sidebar strip
async function loadNextPrayer() {
    try {
        const r = await fetch('/api/pesantren/prayers/next', {
            headers: { 'Authorization': 'Bearer {{ session('api_token','') }}', 'Accept': 'application/json' }
        });
        const j = await r.json();
        if (j.status && j.data?.nama) {
            const map = { fajr:'Subuh', dzuhur:'Dzuhur', ashar:'Ashar', maghrib:'Maghrib', isya:'Isya' };
            document.getElementById('sp-name').textContent = (map[j.data.nama] ?? j.data.nama) + ' · ' + (j.data.sisa_label ?? '');
            document.getElementById('sp-time').textContent = j.data.waktu ?? '--:--';
        } else {
            document.getElementById('sp-name').textContent = 'Semua waktu sudah lewat';
            document.getElementById('sp-time').textContent = '—';
        }
    } catch(e) {
        document.getElementById('sp-name').textContent = 'Tidak tersedia';
    }
}
loadNextPrayer();
</script>
</body>
</html>
