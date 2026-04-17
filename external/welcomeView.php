<?php
session_name('PHPSESSID_MRKSolutions');
session_start();

if (!isset($_SESSION['MRKSolutions'])) {
    die("Sessão expirada. Faça login novamente.");
}

$appData = $_SESSION['MRKSolutions'];

$token    = $appData['sessionid']  ?? '';
$unit_id  = $appData['userunitid'] ?? '';
$user_id  = $appData['userid']     ?? '';
$userName = $appData['username']   ?? 'Usuário';

if (empty($token)) {
    die("Acesso negado.");
}

$firstName = explode(' ', trim($userName))[0];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Início | Portal MRK</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>

    <link href="bsb/plugins/bootstrap/css/bootstrap.css" rel="stylesheet">
    <link href="bsb/css/style.css" rel="stylesheet">
    <link href="style/mrk.css" rel="stylesheet">

    <style>
        .mrk-alert-info    { background:#eff6ff; border-left-color:#3b82f6; }
        .mrk-alert-success { background:#f0fdf4; border-left-color: var(--mrk-green); }
        .mrk-alert-warning { background:#fffaf0; border-left-color: var(--mrk-amber); }
        .mrk-alert-danger  { background:#fef2f2; border-left-color: var(--mrk-red); }

        .mrk-alert-info    .alert-icon { background:rgba(59,130,246,.15); color:#3b82f6; }
        .mrk-alert-success .alert-icon { background:rgba(8,167,148,.15); color: var(--mrk-green); }
        .mrk-alert-warning .alert-icon { background:rgba(245,166,35,.15); color: var(--mrk-amber); }
        .mrk-alert-danger  .alert-icon { background:rgba(229,57,53,.15); color: var(--mrk-red); }
        
        :root {
            --mrk-blue: #0B46AC;
            --mrk-blue-dark: #083682;
            --mrk-blue-light: #1E5FD4;
            --mrk-green: #08A794;
            --mrk-amber: #F5A623;
            --mrk-red: #E53935;
            --mrk-gray: #F4F7F6;
            --mrk-text: #2b2b2b;
        }

        html, body { background: transparent !important; }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #F4F7F6;
            color: var(--mrk-text);
        }

        /* ===== WELCOME HERO ===== */
        .welcome-hero {
            border-radius: 10px;
            padding: 40px 35px;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.08);
            min-height: 200px;
            transition: background 2s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .sky-scene { position: absolute; inset: 0; z-index: 0; overflow: hidden; border-radius: 10px; }
        .sky-ground { position: absolute; bottom: 0; left: 0; right: 0; height: 35px; z-index: 2; transition: background 2s ease; }
        .sky-hills { position: absolute; bottom: 20px; left: 0; right: 0; height: 60px; z-index: 1; transition: opacity 2s ease; }
        .sky-hills svg { width: 100%; height: 100%; }
        .sky-sun {
            position: absolute; width: 50px; height: 50px; border-radius: 50%;
            background: radial-gradient(circle at 40% 40%, #fff7a8, #ffd43b 40%, #f5a623 70%);
            box-shadow: 0 0 30px rgba(245,166,35,0.6), 0 0 60px rgba(245,166,35,0.3), 0 0 100px rgba(245,166,35,0.15);
            z-index: 3; transition: transform 60s linear, opacity 1s ease;
        }
        .sky-sun::after {
            content:''; position:absolute; inset:-15px; border-radius:50%;
            background: radial-gradient(circle, rgba(245,166,35,0.2), transparent 70%);
            animation: sunPulse 4s ease-in-out infinite alternate;
        }
        @keyframes sunPulse { 0%{transform:scale(1);opacity:.5} 100%{transform:scale(1.3);opacity:.8} }
        .sky-moon {
            position:absolute; width:40px; height:40px; border-radius:50%;
            background: radial-gradient(circle at 35% 35%, #f5f5f5, #d1d5db 60%, #9ca3af);
            box-shadow: 0 0 20px rgba(255,255,255,0.3), 0 0 50px rgba(255,255,255,0.1);
            z-index:3; transition: transform 60s linear, opacity 1s ease;
        }
        .sky-moon::before { content:''; position:absolute; width:8px; height:8px; border-radius:50%; background:rgba(156,163,175,.4); top:10px; left:12px; }
        .sky-moon::after  { content:''; position:absolute; width:5px; height:5px; border-radius:50%; background:rgba(156,163,175,.3); top:22px; left:22px; }
        .sky-stars { position:absolute; inset:0; z-index:1; transition: opacity 2s ease; }
        .sky-star { position:absolute; background:#fff; border-radius:50%; animation: twinkle var(--dur) ease-in-out infinite alternate; }
        @keyframes twinkle { 0%{opacity:.2;transform:scale(.8)} 100%{opacity:1;transform:scale(1.3)} }
        .sky-clouds { position:absolute; inset:0; z-index:2; pointer-events:none; }
        .cloud { position:absolute; background:rgba(255,255,255,.25); border-radius:50px; animation: drift linear infinite; }
        .cloud::before, .cloud::after { content:''; position:absolute; background:inherit; border-radius:50%; }
        .cloud-1 { width:80px; height:24px; top:25%; left:-100px; animation-duration:35s; }
        .cloud-1::before { width:36px; height:36px; top:-18px; left:14px; }
        .cloud-1::after  { width:26px; height:26px; top:-10px; left:42px; }
        .cloud-2 { width:60px; height:18px; top:40%; left:-80px; animation-duration:45s; animation-delay:8s; }
        .cloud-2::before { width:28px; height:28px; top:-14px; left:10px; }
        .cloud-2::after  { width:20px; height:20px; top:-8px; left:32px; }
        .cloud-3 { width:70px; height:20px; top:15%; left:-90px; animation-duration:50s; animation-delay:15s; }
        .cloud-3::before { width:32px; height:32px; top:-16px; left:12px; }
        .cloud-3::after  { width:22px; height:22px; top:-8px; left:38px; }
        @keyframes drift { 0%{transform:translateX(0)} 100%{transform:translateX(calc(100vw + 200px))} }

        .welcome-hero .welcome-content { position: relative; z-index: 10; }
        .welcome-greeting {
            font-family: 'Kanit', sans-serif; font-size: 14px; font-weight: 400;
            color: var(--mrk-amber); text-transform: uppercase; letter-spacing: 3px;
            margin-bottom: 8px; display: flex; align-items: center; gap: 8px;
        }
        .welcome-greeting .dot-online {
            width:8px; height:8px; background: var(--mrk-amber); border-radius:50%;
            display:inline-block; animation: pulseOnline 2s ease-in-out infinite;
        }
        @keyframes pulseOnline {
            0%,100% { opacity:1; box-shadow:0 0 0 0 rgba(8,167,148,.4); }
            50%     { opacity:.7; box-shadow:0 0 0 6px rgba(8,167,148,0); }
        }
        .welcome-title {
            font-family:'Kanit',sans-serif; font-size:32px; font-weight:700;
            color:#fff; margin:0 0 6px; line-height:1.2; text-shadow:0 2px 10px rgba(0,0,0,.3);
        }
        .welcome-title span {
            background: linear-gradient(135deg, var(--mrk-amber), #ffc870);
            -webkit-background-clip:text; -webkit-text-fill-color:transparent;
            background-clip:text; filter:drop-shadow(0 2px 4px rgba(0,0,0,.2));
        }
        .welcome-subtitle {
            font-family:'Poppins',sans-serif; font-size:15px; color:rgba(255,255,255,.75);
            margin:0; max-width:600px; line-height:1.6; text-shadow:0 1px 4px rgba(0,0,0,.2);
        }
        .welcome-date { position:absolute; top:35px; right:35px; text-align:right; z-index:10; }
        .welcome-date .date-day  { font-family:'Kanit',sans-serif; font-size:42px; font-weight:700; color:rgba(255,255,255,.15); line-height:1; text-shadow:0 2px 8px rgba(0,0,0,.2); }
        .welcome-date .date-text { font-family:'Poppins',sans-serif; font-size:13px; color:rgba(255,255,255,.5); letter-spacing:.5px; }
        .welcome-date .date-time { font-family:'Kanit',sans-serif; font-size:18px; color:rgba(255,255,255,.25); letter-spacing:2px; margin-top:2px; }

        /* ===== KPIs ===== */
        .stat-cards {
            display:grid; grid-template-columns: repeat(4, 1fr); gap:16px; margin-bottom:20px;
        }
        .stat-card {
            background: rgba(255,255,255,.98); border-radius:8px; padding:24px 20px;
            border:1px solid #eee; border-top:3px solid var(--mrk-green);
            position:relative; overflow:hidden; transition:all .3s ease;
            cursor:pointer; text-decoration:none !important; display:block; color:inherit;
            box-shadow: 0 4px 12px rgba(0,0,0,.05);
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,.10); color:inherit; }
        .stat-card.card-blue  { border-top-color: var(--mrk-blue); }
        .stat-card.card-green { border-top-color: var(--mrk-green); }
        .stat-card.card-amber { border-top-color: var(--mrk-amber); }
        .stat-card.card-red   { border-top-color: var(--mrk-red); }

        .stat-card .stat-icon {
            width:44px; height:44px; border-radius:10px;
            display:flex; align-items:center; justify-content:center;
            font-size:22px; margin-bottom:14px;
        }
        .stat-card.card-blue  .stat-icon { background: rgba(11,70,172,.1);  color: var(--mrk-blue); }
        .stat-card.card-green .stat-icon { background: rgba(8,167,148,.1);  color: var(--mrk-green); }
        .stat-card.card-amber .stat-icon { background: rgba(245,166,35,.1); color: var(--mrk-amber); }
        .stat-card.card-red   .stat-icon { background: rgba(229,57,53,.1);  color: var(--mrk-red); }

        .stat-card .stat-number {
            font-family:'Kanit',sans-serif; font-size:26px; font-weight:700;
            color: var(--mrk-text); line-height:1; margin-bottom:4px;
        }
        .stat-card .stat-label {
            font-family:'Poppins',sans-serif; font-size:12px; color:#9ca3af;
            text-transform:uppercase; letter-spacing:.5px; font-weight:500;
        }
        .stat-card .stat-badge {
            position:absolute; top:16px; right:16px; font-size:11px; font-weight:600;
            padding:3px 10px; border-radius:20px; font-family:'Poppins',sans-serif;
        }
        .badge-up      { background: rgba(8,167,148,.1);  color: var(--mrk-green); }
        .badge-down    { background: rgba(229,57,53,.1);  color: var(--mrk-red); }
        .badge-neutral { background: rgba(107,114,128,.1); color:#6b7280; }

        .skeleton-stat {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%; border-radius:6px;
            animation: pulse 1.5s infinite; height:24px; width:90px;
        }
        @keyframes pulse { 0%{background-position:200% 0} 100%{background-position:-200% 0} }

        /* ===== BOTTOM GRID ===== */
        .bottom-grid { display:grid; grid-template-columns: 1fr 1fr; gap:20px; }

        .card {
            background: rgba(255,255,255,.98) !important;
            border-top: 2px solid var(--mrk-green) !important;
            border-radius: 8px !important;
            box-shadow: 0 4px 12px rgba(0,0,0,.05) !important;
        }
        .card .header h2 {
            font-family:'Kanit',sans-serif; font-weight:600; color: var(--mrk-green);
            display:flex; align-items:center; gap:8px; margin:0;
            font-size:14px; text-transform:uppercase; letter-spacing:1px;
        }

        /* ===== SHORTCUTS ===== */
        .shortcuts-grid { display:grid; grid-template-columns: repeat(3, 1fr); gap:12px; }
        .shortcut-btn {
            display:flex; flex-direction:column; align-items:center; justify-content:center;
            gap:10px; padding:22px 12px; background:#fafafa; border:1px solid #ececec;
            border-radius:8px; text-decoration:none !important; color: var(--mrk-text);
            transition:all .3s ease; cursor:pointer; position:relative;
        }
        .shortcut-btn:hover {
            background:#fff; border-color: var(--mrk-green); transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(245,166,35,.12); color: var(--mrk-text);
        }
        .shortcut-btn .sc-icon {
            width:44px; height:44px; border-radius:10px;
            display:flex; align-items:center; justify-content:center;
            font-size:22px; background:rgba(11,70,172,.08); color: var(--mrk-blue);
            transition:all .3s;
        }
        .shortcut-btn:hover .sc-icon { background: var(--mrk-green); color:#fff; }
        .shortcut-btn .sc-label {
            font-family:'Poppins',sans-serif; font-size:12px; font-weight:600;
            text-align:center; line-height:1.3; color:#4b5563;
        }
        .shortcut-btn .sc-count {
            position:absolute; top:8px; right:8px;
            background: var(--mrk-green); color:#fff; font-size:10px; font-weight:700;
            padding:2px 7px; border-radius:10px; font-family:'Kanit',sans-serif;
            min-width:20px; text-align:center;
        }
        .shortcut-skeleton {
            padding:22px 12px; background:#fafafa; border:1px solid #ececec;
            border-radius:8px; height:110px;
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%; animation: pulse 1.5s infinite;
        }

        /* ===== ALERTAS ===== */
        .alert-item {
            display:flex; align-items: flex-start; gap:12px; padding:16px; border-radius:8px;
            margin-bottom:12px; border-left:4px solid; position: relative;
        }
        .alert-item:last-child { margin-bottom:0; }
        .alert-info    { background:#eff6ff; border-left-color:#3b82f6; }
        .alert-success { background:#f0fdf4; border-left-color: var(--mrk-green); }
        .alert-warning { background:#fffaf0; border-left-color: var(--mrk-amber); }
        .alert-danger  { background:#fef2f2; border-left-color: var(--mrk-red); }

        .alert-item .alert-icon {
            width:36px; height:36px; border-radius:8px; display:flex;
            align-items:center; justify-content:center; font-size:20px; flex-shrink:0;
            margin-top: 2px; /* Alinha o ícone visualmente com a primeira linha do texto */
        }
        .alert-info    .alert-icon { background:rgba(59,130,246,.15); color:#3b82f6; }
        .alert-success .alert-icon { background:rgba(8,167,148,.15); color: var(--mrk-green); }
        .alert-warning .alert-icon { background:rgba(245,166,35,.15); color: var(--mrk-amber); }
        .alert-danger  .alert-icon { background:rgba(229,57,53,.15); color: var(--mrk-red); }

        .alert-item .alert-body { flex:1; min-width:0; }
        .alert-item .alert-title {
            font-family:'Kanit',sans-serif; font-weight:600; font-size:15px;
            color: var(--mrk-text); margin:0 0 4px; line-height: 1.2;
        }
        .alert-item .alert-msg {
            font-size:13px; color:#4b5563; margin:0 0 6px; line-height:1.5;
            display: block; width: 100%; word-break: break-word; /* Evita que o texto quebre o layout */
        }
        .alert-item .alert-meta {
            font-size:11px; color:#9ca3af; font-family:'Poppins',sans-serif;
            display: block; width: 100%;
        }

        .alert-empty {
            text-align:center; padding:30px 20px; color:#9ca3af; font-size:13px;
        }
        .alert-empty iconify-icon { font-size:40px; display:block; margin-bottom:8px; color:#d1d5db; }

        @media (max-width: 992px) {
            .stat-cards { grid-template-columns: repeat(2, 1fr); }
            .bottom-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 576px) {
            .stat-cards { grid-template-columns: 1fr; }
            .shortcuts-grid { grid-template-columns: repeat(2, 1fr); }
            .welcome-title { font-size:24px; }
            .welcome-date { position: static; text-align: left; margin-top: 15px; }
        }
    </style>
</head>

<body class="theme-blue">

<div class="container-fluid" style="padding: 20px;">

    <!-- HERO -->
    <div class="welcome-hero" id="welcomeHero">
        <div class="sky-scene" id="skyScene">
            <div class="sky-stars" id="skyStars"></div>
            <div class="sky-clouds" id="skyClouds">
                <div class="cloud cloud-1"></div>
                <div class="cloud cloud-2"></div>
                <div class="cloud cloud-3"></div>
            </div>
            <div class="sky-sun" id="skySun"></div>
            <div class="sky-moon" id="skyMoon"></div>
            <div class="sky-hills" id="skyHills">
                <svg viewBox="0 0 800 60" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M0,60 L0,35 Q50,10 100,30 Q150,45 200,25 Q250,5 300,20 Q350,40 400,15 Q450,0 500,25 Q550,40 600,20 Q650,5 700,30 Q750,45 800,20 L800,60 Z" fill="currentColor"/>
                </svg>
            </div>
            <div class="sky-ground" id="skyGround"></div>
        </div>

        <div class="welcome-content">
            <div class="welcome-greeting">
                <span class="dot-online"></span>
                <span id="greetingText">Bom dia</span>
            </div>
            <h1 class="welcome-title">Olá, <span><?php echo htmlspecialchars($firstName); ?></span>!</h1>
            <p class="welcome-subtitle">Bem-vindo ao Portal MRK. Aqui está o resumo dos últimos 7 dias.</p>
        </div>
        <div class="welcome-date">
            <div class="date-day"  id="dateDay"></div>
            <div class="date-text" id="dateText"></div>
            <div class="date-time" id="dateTime"></div>
        </div>
    </div>

    <!-- KPIs -->
    <div class="stat-cards">
        <div class="stat-card card-red" id="kpiAlertas">
            <div class="stat-icon"><iconify-icon icon="icon-park-outline:remind"></iconify-icon></div>
            <div class="stat-number" id="numAlertas"><div class="skeleton-stat"></div></div>
            <div class="stat-label">Alertas Ativos</div>
            <span class="stat-badge badge-neutral" id="badgeAlertas">total</span>
        </div>

        <div class="stat-card card-green" id="kpiFaturamento">
            <div class="stat-icon"><iconify-icon icon="icon-park-outline:sales-report"></iconify-icon></div>
            <div class="stat-number" id="numFaturamento"><div class="skeleton-stat"></div></div>
            <div class="stat-label">Faturamento Líquido (7d)</div>
            <span class="stat-badge badge-up">7 dias</span>
        </div>

        <div class="stat-card card-blue" id="kpiCompras">
            <div class="stat-icon"><iconify-icon icon="icon-park-outline:shopping"></iconify-icon></div>
            <div class="stat-number" id="numCompras"><div class="skeleton-stat"></div></div>
            <div class="stat-label">Total de Compras (7d)</div>
            <span class="stat-badge badge-neutral">7 dias</span>
        </div>

        <div class="stat-card card-amber" id="kpiContas">
            <div class="stat-icon"><iconify-icon icon="icon-park-outline:wallet"></iconify-icon></div>
            <div class="stat-number" id="numContas"><div class="skeleton-stat"></div></div>
            <div class="stat-label">Contas a Vencer (d+6)</div>
            <span class="stat-badge badge-neutral" id="badgeContas">próx. 7 dias</span>
        </div>
    </div>

    <!-- BOTTOM GRID -->
    <div class="bottom-grid">
        <!-- ATALHOS -->
        <div class="card">
            <div class="header">
                <h2>
                    <iconify-icon icon="icon-park-outline:lightning"></iconify-icon>
                    ACESSO RÁPIDO
                </h2>
            </div>
            <div class="body">
                <div class="shortcuts-grid" id="shortcutsGrid">
                    <div class="shortcut-skeleton"></div>
                    <div class="shortcut-skeleton"></div>
                    <div class="shortcut-skeleton"></div>
                    <div class="shortcut-skeleton"></div>
                    <div class="shortcut-skeleton"></div>
                    <div class="shortcut-skeleton"></div>
                </div>
            </div>
        </div>

        <!-- ALERTAS -->
        <div class="card">
            <div class="header">
                <h2>
                    <iconify-icon icon="icon-park-outline:attention"></iconify-icon>
                    ALERTAS
                </h2>
            </div>
            <div class="body" id="alertsContainer">
                <div class="alert-empty">
                    <iconify-icon icon="icon-park-outline:loading-three"></iconify-icon>
                    Carregando alertas...
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-2.2.4.min.js"></script>
<script src="bsb/plugins/bootstrap/js/bootstrap.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

<script>
    // ============================================
    //  CONFIG GLOBAL
    // ============================================
    const CONFIG = {
        user_id: <?= json_encode($user_id) ?>,
        unit_id: <?= json_encode($unit_id) ?>,
        token:   <?= json_encode($token) ?>,
        baseUrl: (window.location.hostname === 'localhost')
            ? 'http://localhost/portal-mrk/api/v1/index.php'
            : 'https://portal.mrksolucoes.com.br/api/v1/index.php'
    };

    const ADIANTI_BASE = (window.location.hostname === 'localhost')
        ? 'http://localhost/portal-mrk/index.php'
        : 'https://portal.mrksolucoes.com.br/index.php';

    // ============================================
    //  HELPERS
    // ============================================
    function formatMoney(v) {
        v = parseFloat(v) || 0;
        return 'R$ ' + v.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escapeHtml(s) {
        return String(s || '').replace(/[&<>"']/g, c => ({
            '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
        }[c]));
    }

    // Converte "R$ 1.234,56" → 1234.56
    function parseBrMoney(str) {
        if (typeof str === 'number') return str;
        return parseFloat(String(str || '0').replace(/[^\d,-]/g, '').replace(/\./g, '').replace(',', '.')) || 0;
    }

    function dataRange7Dias() {
        const hoje = new Date();
        const inicio = new Date(hoje);
        inicio.setDate(inicio.getDate() - 7);
        const fmt = (d) => d.toISOString().slice(0, 10);
        return {
            inicio_datetime: fmt(inicio) + ' 00:00:00',
            fim_datetime:    fmt(hoje)   + ' 23:59:59',
            inicio:          fmt(inicio),
            fim:             fmt(hoje),
        };
    }

    function apiCall(method, data) {
        return axios.post(CONFIG.baseUrl, {
            method: method,
            token: CONFIG.token,
            data: data || {}
        }).then(r => r.data);
    }

    function setKPI(id, value) {
        $('#' + id).text(value);
    }

    // ============================================
    //  FLUXO PRINCIPAL
    // ============================================
    $(document).ready(function() {
        generateStars();
        updateSky();
        setInterval(updateSky, 30000);
        updateDateTime();
        setInterval(updateDateTime, 1000);

        loadDashboard();
    });

    async function loadDashboard() {
        // Atalhos e alertas não dependem de grupo
        loadAtalhos();
        loadAlertas();

        try {
            // Descobre o grupo da unit atual do usuário
            const grupoResp = await apiCall('getGroupByUserUnit', { user_id: CONFIG.user_id });

            if (!grupoResp.success) {
                console.warn('Grupo não encontrado:', grupoResp.message);
                setKPI('numFaturamento', '—');
                setKPI('numCompras', '—');
                setKPI('numContas', '—');
                return;
            }

            const groupId = grupoResp.group_id;
            const periodo = dataRange7Dias();

            // KPIs financeiros em paralelo
            loadFaturamento(groupId, periodo);
            loadCompras(groupId, periodo);
            loadContas(groupId, periodo);

        } catch (err) {
            console.error('loadDashboard:', err);
        }
    }

    /* ==========================================
       ALERTAS (KPI + lista lateral)
    ========================================== */
    async function loadAlertas() {
        try {
            const resp = await apiCall('getAlertsForDashboard', {
                user_id: CONFIG.user_id,
                filters: { only_unread: false, limit: 5 }
            });

            if (!resp.success) {
                setKPI('numAlertas', '—');
                renderAlertasVazio('Erro ao carregar alertas');
                return;
            }

            setKPI('numAlertas', resp.total || 0);
            $('#badgeAlertas')
                .removeClass('badge-up badge-down badge-neutral')
                .addClass(resp.unread > 0 ? 'badge-down' : 'badge-up')
                .text(resp.unread > 0 ? resp.unread + ' novos' : 'em dia');

            renderAlertas(resp.alerts || []);

        } catch (err) {
            console.error('loadAlertas:', err);
            setKPI('numAlertas', '—');
            renderAlertasVazio('Erro ao carregar alertas');
        }
    }

    function renderAlertas(alerts) {
        const $c = $('#alertsContainer').empty();
        if (!alerts.length) {
            renderAlertasVazio('Nenhum alerta no momento');
            return;
        }

        const iconMap = {
            info:    'icon-park-outline:info',
            success: 'icon-park-outline:check-one',
            warning: 'icon-park-outline:attention',
            danger:  'icon-park-outline:caution',
        };

        alerts.forEach(a => {
            const type = a.type || 'info';
            const icon = iconMap[type] || iconMap.info;
            const dt   = a.created_at ? new Date(a.created_at).toLocaleString('pt-BR') : '';

            $c.append(`
            <div class="alert-item mrk-alert-${escapeHtml(type)}">
                <div class="alert-icon"><iconify-icon icon="${icon}"></iconify-icon></div>
                    <div class="alert-body">
                        <div class="alert-title">${escapeHtml(a.title)}</div>
                        <div class="alert-msg">${escapeHtml(a.message)}</div>
                        <div class="alert-meta">${escapeHtml(a.unit_name || '')} · ${escapeHtml(dt)}</div>
                    </div>
                </div>
            `);
        });
    }

    function renderAlertasVazio(msg) {
        $('#alertsContainer').html(`
            <div class="alert-empty">
                <iconify-icon icon="icon-park-outline:check-one"></iconify-icon>
                ${escapeHtml(msg)}
            </div>
        `);
    }

    /* ==========================================
       FATURAMENTO LÍQUIDO (7d) — soma de todas as lojas do grupo
    ========================================== */
    async function loadFaturamento(groupId, periodo) {
        try {
            const resp = await apiCall('generateResumoFinanceiroPorGrupo', {
                grupoId:   groupId,
                dt_inicio: periodo.inicio_datetime,
                dt_fim:    periodo.fim_datetime,
            });

            if (!resp.success || !Array.isArray(resp.data)) {
                setKPI('numFaturamento', '—');
                return;
            }

            const totalLiquido = resp.data.reduce(
                (sum, loja) => sum + (parseFloat(loja.faturamento_liquido) || 0),
                0
            );
            setKPI('numFaturamento', formatMoney(totalLiquido));

        } catch (err) {
            console.error('loadFaturamento:', err);
            setKPI('numFaturamento', '—');
        }
    }

    /* ==========================================
       COMPRAS (7d)
    ========================================== */
    async function loadCompras(groupId, periodo) {
        try {
            const resp = await apiCall('generateResumoEstoquePorGrupo', {
                grupoId:   groupId,
                dt_inicio: periodo.inicio_datetime,
                dt_fim:    periodo.fim_datetime,
            });

            if (!resp.success || !Array.isArray(resp.data)) {
                setKPI('numCompras', '—');
                return;
            }

            // total_compras vem como string formatada "R$ 1.234,56"
            const totalCompras = resp.data.reduce(
                (sum, loja) => sum + parseBrMoney(loja.total_compras),
                0
            );
            setKPI('numCompras', formatMoney(totalCompras));

        } catch (err) {
            console.error('loadCompras:', err);
            setKPI('numCompras', '—');
        }
    }

    /* ==========================================
       CONTAS A VENCER (d+6 = próximos 7 dias)
    ========================================== */
    async function loadContas(groupId, periodo) {
        try {
            const resp = await apiCall('getDashboardFinanceiroPorGrupo', {
                group_id:  groupId,
                dt_inicio: periodo.inicio,
                dt_fim:    periodo.fim,
                tipo_data: 'vencimento',
            });

            if (!resp.success || !resp.data) {
                setKPI('numContas', '—');
                return;
            }

            const totalPagar = parseFloat(resp.data.total_contas_vencer_7) || 0;
            const qtd        = parseInt(resp.data.qtd_contas_vencer_7)     || 0;

            setKPI('numContas', formatMoney(totalPagar));
            $('#badgeContas').text(qtd + ' contas');

        } catch (err) {
            console.error('loadContas:', err);
            setKPI('numContas', '—');
        }
    }

    /* ==========================================
       ATALHOS DINÂMICOS (menus mais acessados)
    ========================================== */
    async function loadAtalhos() {
        try {
            const resp = await apiCall('getMostAccessedMenus', {
                user_id: CONFIG.user_id,
                limit:   9,
                days:    30,
            });

            if (!resp.success || !Array.isArray(resp.menus) || !resp.menus.length) {
                $('#shortcutsGrid').html(
                    '<div class="alert-empty" style="grid-column: 1 / -1;">' +
                    '<iconify-icon icon="icon-park-outline:link-cloud-faild"></iconify-icon>' +
                    'Nenhum atalho ainda. Navegue pelo sistema para popular seus atalhos.' +
                    '</div>'
                );
                return;
            }

            renderAtalhos(resp.menus);

        } catch (err) {
            console.error('loadAtalhos:', err);
            $('#shortcutsGrid').html(
                '<div class="alert-empty" style="grid-column: 1 / -1;">Erro ao carregar atalhos</div>'
            );
        }
    }

    function renderAtalhos(menus) {
        const $grid = $('#shortcutsGrid').empty();

        menus.forEach(m => {
            const icon = normalizeIcon(m.icon);
            const label = m.label || m.class_name;
            const url = ADIANTI_BASE + '?class=' + encodeURIComponent(m.class_name);

            $grid.append(`
                <a href="${url}" target="_top" class="shortcut-btn"
                   title="${escapeHtml(label)} — ${m.total_acessos} acessos nos últimos 30 dias">
                    <div class="sc-icon"><iconify-icon icon="${escapeHtml(icon)}"></iconify-icon></div>
                    <span class="sc-label">${escapeHtml(label)}</span>
                    <span class="sc-count">${m.total_acessos}</span>
                </a>
            `);
        });
    }

    function normalizeIcon(icon) {
        if (!icon) return 'icon-park-outline:application-two';

        // 1. Remove classes extras separadas por espaço (ex: "fa-fw")
        // Ex: "fas:chart-bar fa-fw" vira apenas "fas:chart-bar"
        let baseIcon = icon.split(' ')[0].trim();

        // 2. Traduz os prefixos salvos no banco para as coleções do Iconify (FontAwesome 6)
        if (baseIcon.startsWith('fas:')) {
            return baseIcon.replace('fas:', 'fa6-solid:');
        }
        if (baseIcon.startsWith('far:')) {
            return baseIcon.replace('far:', 'fa6-regular:');
        }
        if (baseIcon.startsWith('fab:')) {
            return baseIcon.replace('fab:', 'fa6-brands:');
        }
        if (baseIcon.startsWith('fa:')) {
            // Se for um ícone de marca genérico como "fa:whatsapp"
            if (baseIcon.includes('whatsapp') || baseIcon.includes('facebook') || baseIcon.includes('instagram')) {
                return baseIcon.replace('fa:', 'fa6-brands:');
            }
            return baseIcon.replace('fa:', 'fa6-solid:');
        }

        // 3. Fallback caso o atalho seja uma imagem (como o logo-openfinance.png)
        // O Iconify não renderiza URLs de imagem, então colocamos um ícone padrão
        if (baseIcon.includes('.png') || baseIcon.includes('.jpg') || baseIcon.includes('.svg')) {
            return 'icon-park-outline:api-app';
        }

        // 4. Se já tiver um prefixo válido (ex: "icon-park-outline:...")
        if (baseIcon.indexOf(':') !== -1) return baseIcon;

        // 5. Fallback final
        return 'icon-park-outline:' + baseIcon;
    }

    /* ==========================================
       CÉU DINÂMICO
    ========================================== */
    const skyThemes = {
        dawn:         { sky:'linear-gradient(180deg, #1a1a3e 0%, #3d2c6e 20%, #c45e3a 55%, #f5a623 80%, #fce4a8 100%)', ground:'#1a1510', hills:'#2a1f18', cloudAlpha:.15, starsAlpha:.3, greeting:'Bom dia' },
        earlyMorning: { sky:'linear-gradient(180deg, #0B46AC 0%, #1E5FD4 30%, #4a8ec2 60%, #b8daf0 90%, #e8f0f5 100%)', ground:'#3d6b4a', hills:'#2d5a3a', cloudAlpha:.35, starsAlpha:0,  greeting:'Bom dia' },
        midday:       { sky:'linear-gradient(180deg, #0B46AC 0%, #1E5FD4 25%, #5ba3d9 60%, #b8e2f8 100%)',              ground:'#4a7c55', hills:'#3a6b45', cloudAlpha:.30, starsAlpha:0,  greeting:'Bom dia' },
        afternoon:    { sky:'linear-gradient(180deg, #0B46AC 0%, #3a7cc2 35%, #d4a868 70%, #F5A623 100%)',              ground:'#4a6b3a', hills:'#3a5a2d', cloudAlpha:.25, starsAlpha:0,  greeting:'Boa tarde' },
        sunset:       { sky:'linear-gradient(180deg, #083682 0%, #0B46AC 20%, #5c3a8c 45%, #c44e3a 65%, #F5A623 85%, #fce08a 100%)', ground:'#1a1510', hills:'#251a12', cloudAlpha:.20, starsAlpha:.15, greeting:'Boa tarde' },
        dusk:         { sky:'linear-gradient(180deg, #0a0e2a 0%, #0B46AC 30%, #3d2060 60%, #6a3060 80%, #1a1030 100%)', ground:'#0a0a10', hills:'#12101a', cloudAlpha:.08, starsAlpha:.7, greeting:'Boa noite' },
        night:        { sky:'linear-gradient(180deg, #050510 0%, #083682 20%, #0B46AC 40%, #0a1020 90%, #050510 100%)', ground:'#060608', hills:'#0a0a12', cloudAlpha:.05, starsAlpha:1,  greeting:'Boa noite' }
    };

    function getThemeForHour(h) {
        if (h >= 5  && h <  7) return skyThemes.dawn;
        if (h >= 7  && h < 10) return skyThemes.earlyMorning;
        if (h >= 10 && h < 15) return skyThemes.midday;
        if (h >= 15 && h < 17) return skyThemes.afternoon;
        if (h >= 17 && h < 19) return skyThemes.sunset;
        if (h >= 19 && h < 21) return skyThemes.dusk;
        return skyThemes.night;
    }

    function getSunPosition(h, m) {
        const t = h + m/60;
        if (t < 5 || t > 18.5) return { x:85, y:110, opacity:0 };
        const progress = (t - 5) / 13.5;
        const x = 10 + progress * 80;
        const y = 100 - 90 * Math.sin(progress * Math.PI);
        let opacity = 1;
        if (progress < 0.08) opacity = progress / 0.08;
        if (progress > 0.92) opacity = (1 - progress) / 0.08;
        return { x, y: Math.max(5, y), opacity: Math.min(1, opacity) };
    }

    function getMoonPosition(h, m) {
        const t = h + m/60;
        let progress;
        if (t >= 18) progress = (t - 18) / 11;
        else if (t < 5) progress = (t + 6) / 11;
        else return { x:85, y:110, opacity:0 };
        const x = 10 + progress * 80;
        const y = 100 - 90 * Math.sin(progress * Math.PI);
        let opacity = 1;
        if (progress < 0.1) opacity = progress / 0.1;
        if (progress > 0.9) opacity = (1 - progress) / 0.1;
        return { x, y: Math.max(5, y), opacity: Math.min(1, opacity) };
    }

    function updateSky() {
        const now = new Date();
        const h = now.getHours();
        const m = now.getMinutes();
        const theme = getThemeForHour(h);
        document.getElementById('welcomeHero').style.background = theme.sky;
        document.getElementById('skyGround').style.background = theme.ground;
        document.getElementById('skyHills').style.color = theme.hills;
        document.getElementById('skyStars').style.opacity = theme.starsAlpha;
        document.getElementById('skyClouds').querySelectorAll('.cloud').forEach(c => {
            c.style.background = `rgba(255,255,255,${theme.cloudAlpha})`;
        });

        const sunPos = getSunPosition(h, m);
        const sun = document.getElementById('skySun');
        sun.style.left = sunPos.x + '%';
        sun.style.top  = sunPos.y + '%';
        sun.style.opacity = sunPos.opacity;

        const moonPos = getMoonPosition(h, m);
        const moon = document.getElementById('skyMoon');
        moon.style.left = moonPos.x + '%';
        moon.style.top  = moonPos.y + '%';
        moon.style.opacity = moonPos.opacity;

        document.getElementById('greetingText').textContent = theme.greeting;
    }

    function generateStars() {
        const container = document.getElementById('skyStars');
        for (let i = 0; i < 60; i++) {
            const star = document.createElement('div');
            star.className = 'sky-star';
            const size = Math.random() * 2.5 + 0.5;
            star.style.cssText = `
                width: ${size}px;
                height: ${size}px;
                top: ${Math.random() * 75}%;
                left: ${Math.random() * 100}%;
                --dur: ${Math.random() * 3 + 1.5}s;
                animation-delay: ${Math.random() * 3}s;
            `;
            container.appendChild(star);
        }
    }

    function updateDateTime() {
        const now = new Date();
        const dias = ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'];
        const meses = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
        $('#dateDay').text(String(now.getDate()).padStart(2, '0'));
        $('#dateText').text(`${dias[now.getDay()]}, ${meses[now.getMonth()]} ${now.getFullYear()}`);
        $('#dateTime').text(now.toLocaleTimeString('pt-BR', { hour:'2-digit', minute:'2-digit', second:'2-digit' }));
    }
</script>

</body>
</html>