<?php
session_name('PHPSESSID_MRKSolutions');
session_start();

if (!isset($_SESSION['MRKSolutions'])) {
    die("Sessão expirada. Faça login novamente.");
}

$appData = $_SESSION['MRKSolutions'];

$token   = $appData['sessionid']  ?? '';
$unit_id = $appData['userunitid'] ?? '';
$user_id = $appData['userid']     ?? '';
$userName = $appData['username']  ?? 'Usuário';

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

        /* ===== WELCOME HERO COM CÉU DINÂMICO ===== */
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

        /* Sky canvas */
        .sky-scene {
            position: absolute;
            inset: 0;
            z-index: 0;
            overflow: hidden;
            border-radius: 10px;
        }

        /* Ground / horizon */
        .sky-ground {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 35px;
            z-index: 2;
            transition: background 2s ease;
        }

        /* Hills silhouette */
        .sky-hills {
            position: absolute;
            bottom: 20px;
            left: 0;
            right: 0;
            height: 60px;
            z-index: 1;
            transition: opacity 2s ease;
        }
        .sky-hills svg { width: 100%; height: 100%; }

        /* Sun */
        .sky-sun {
            position: absolute;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: radial-gradient(circle at 40% 40%, #fff7a8, #ffd43b 40%, #f5a623 70%);
            box-shadow:
                    0 0 30px rgba(245, 166, 35, 0.6),
                    0 0 60px rgba(245, 166, 35, 0.3),
                    0 0 100px rgba(245, 166, 35, 0.15);
            z-index: 3;
            transition: transform 60s linear, opacity 1s ease;
        }
        .sky-sun::after {
            content: '';
            position: absolute;
            inset: -15px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(245,166,35,0.2), transparent 70%);
            animation: sunPulse 4s ease-in-out infinite alternate;
        }
        @keyframes sunPulse {
            0% { transform: scale(1); opacity: 0.5; }
            100% { transform: scale(1.3); opacity: 0.8; }
        }

        /* Moon */
        .sky-moon {
            position: absolute;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: radial-gradient(circle at 35% 35%, #f5f5f5, #d1d5db 60%, #9ca3af);
            box-shadow:
                    0 0 20px rgba(255, 255, 255, 0.3),
                    0 0 50px rgba(255, 255, 255, 0.1);
            z-index: 3;
            transition: transform 60s linear, opacity 1s ease;
        }
        .sky-moon::before {
            content: '';
            position: absolute;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: rgba(156, 163, 175, 0.4);
            top: 10px;
            left: 12px;
        }
        .sky-moon::after {
            content: '';
            position: absolute;
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: rgba(156, 163, 175, 0.3);
            top: 22px;
            left: 22px;
        }

        /* Stars */
        .sky-stars {
            position: absolute;
            inset: 0;
            z-index: 1;
            transition: opacity 2s ease;
        }
        .sky-star {
            position: absolute;
            background: #fff;
            border-radius: 50%;
            animation: twinkle var(--dur) ease-in-out infinite alternate;
        }
        @keyframes twinkle {
            0% { opacity: 0.2; transform: scale(0.8); }
            100% { opacity: 1; transform: scale(1.3); }
        }

        /* Clouds */
        .sky-clouds {
            position: absolute;
            inset: 0;
            z-index: 2;
            pointer-events: none;
        }
        .cloud {
            position: absolute;
            background: rgba(255,255,255,0.25);
            border-radius: 50px;
            animation: drift linear infinite;
        }
        .cloud::before, .cloud::after {
            content: '';
            position: absolute;
            background: inherit;
            border-radius: 50%;
        }
        .cloud-1 {
            width: 80px; height: 24px; top: 25%; left: -100px;
            animation-duration: 35s;
        }
        .cloud-1::before { width: 36px; height: 36px; top: -18px; left: 14px; }
        .cloud-1::after { width: 26px; height: 26px; top: -10px; left: 42px; }

        .cloud-2 {
            width: 60px; height: 18px; top: 40%; left: -80px;
            animation-duration: 45s; animation-delay: 8s;
        }
        .cloud-2::before { width: 28px; height: 28px; top: -14px; left: 10px; }
        .cloud-2::after { width: 20px; height: 20px; top: -8px; left: 32px; }

        .cloud-3 {
            width: 70px; height: 20px; top: 15%; left: -90px;
            animation-duration: 50s; animation-delay: 15s;
        }
        .cloud-3::before { width: 32px; height: 32px; top: -16px; left: 12px; }
        .cloud-3::after { width: 22px; height: 22px; top: -8px; left: 38px; }

        @keyframes drift {
            0% { transform: translateX(0); }
            100% { transform: translateX(calc(100vw + 200px)); }
        }

        /* Welcome content on top of sky */
        .welcome-hero .welcome-content {
            position: relative;
            z-index: 10;
        }
        .welcome-greeting {
            font-family: 'Kanit', sans-serif;
            font-size: 14px;
            font-weight: 400;
            color: var(--mrk-amber);
            text-transform: uppercase;
            letter-spacing: 3px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .welcome-greeting .dot-online {
            width: 8px;
            height: 8px;
            background: var(--mrk-green);
            border-radius: 50%;
            display: inline-block;
            animation: pulseOnline 2s ease-in-out infinite;
        }
        @keyframes pulseOnline {
            0%, 100% { opacity: 1; box-shadow: 0 0 0 0 rgba(8,167,148,0.4); }
            50% { opacity: 0.7; box-shadow: 0 0 0 6px rgba(8,167,148,0); }
        }
        .welcome-title {
            font-family: 'Kanit', sans-serif;
            font-size: 32px;
            font-weight: 700;
            color: #ffffff;
            margin: 0 0 6px;
            line-height: 1.2;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }
        .welcome-title span {
            background: linear-gradient(135deg, var(--mrk-amber), #ffc870);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
        }
        .welcome-subtitle {
            font-family: 'Poppins', sans-serif;
            font-size: 15px;
            color: rgba(255,255,255,0.75);
            margin: 0;
            max-width: 600px;
            line-height: 1.6;
            text-shadow: 0 1px 4px rgba(0,0,0,0.2);
        }
        .welcome-date {
            position: absolute;
            top: 35px;
            right: 35px;
            text-align: right;
            z-index: 10;
        }
        .welcome-date .date-day {
            font-family: 'Kanit', sans-serif;
            font-size: 42px;
            font-weight: 700;
            color: rgba(255,255,255,0.15);
            line-height: 1;
            text-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .welcome-date .date-text {
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            color: rgba(255,255,255,0.5);
            letter-spacing: 0.5px;
        }
        .welcome-date .date-time {
            font-family: 'Kanit', sans-serif;
            font-size: 18px;
            color: rgba(255,255,255,0.25);
            letter-spacing: 2px;
            margin-top: 2px;
        }

        /* ===== STAT CARDS ===== */
        .stat-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 8px;
            padding: 24px 20px;
            border: 1px solid #eee;
            border-top: 2px solid var(--mrk-amber);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none !important;
            display: block;
            color: inherit;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.10);
            color: inherit;
        }
        .stat-card.card-blue { border-top-color: var(--mrk-blue); }
        .stat-card.card-green { border-top-color: var(--mrk-green); }
        .stat-card.card-amber { border-top-color: var(--mrk-amber); }
        .stat-card.card-red { border-top-color: var(--mrk-red); }

        .stat-card .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-bottom: 14px;
        }
        .stat-card.card-blue .stat-icon { background: rgba(11,70,172,0.1); color: var(--mrk-blue); }
        .stat-card.card-green .stat-icon { background: rgba(8,167,148,0.1); color: var(--mrk-green); }
        .stat-card.card-amber .stat-icon { background: rgba(245,166,35,0.1); color: var(--mrk-amber); }
        .stat-card.card-red .stat-icon { background: rgba(229,57,53,0.1); color: var(--mrk-red); }

        .stat-card .stat-number {
            font-family: 'Kanit', sans-serif;
            font-size: 28px;
            font-weight: 700;
            color: var(--mrk-text);
            line-height: 1;
            margin-bottom: 4px;
        }
        .stat-card .stat-label {
            font-family: 'Poppins', sans-serif;
            font-size: 12px;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }
        .stat-card .stat-badge {
            position: absolute;
            top: 16px;
            right: 16px;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
            font-family: 'Poppins', sans-serif;
        }
        .badge-up { background: rgba(8,167,148,0.1); color: var(--mrk-green); }
        .badge-down { background: rgba(229,57,53,0.1); color: var(--mrk-red); }
        .badge-neutral { background: rgba(107,114,128,0.1); color: #6b7280; }

        .skeleton-stat {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            border-radius: 6px;
            animation: pulse 1.5s infinite;
            height: 28px;
            width: 60px;
        }
        @keyframes pulse { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

        /* ===== BOTTOM GRID ===== */
        .bottom-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .card {
            background: rgba(255, 255, 255, 0.98) !important;
            border-top: 2px solid var(--mrk-amber) !important;
            border-radius: 8px !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05) !important;
        }

        .card .header h2 {
            font-family: 'Kanit', sans-serif;
            font-weight: 600;
            color: var(--mrk-amber);
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* ===== SHORTCUTS ===== */
        .shortcuts-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        .shortcut-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 22px 12px;
            background: #fafafa;
            border: 1px solid #ececec;
            border-radius: 8px;
            text-decoration: none !important;
            color: var(--mrk-text);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .shortcut-btn:hover {
            background: #fff;
            border-color: var(--mrk-amber);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(245,166,35,0.12);
            color: var(--mrk-text);
        }
        .shortcut-btn .sc-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            background: rgba(11,70,172,0.08);
            color: var(--mrk-blue);
            transition: all 0.3s;
        }
        .shortcut-btn:hover .sc-icon {
            background: var(--mrk-amber);
            color: #fff;
        }
        .shortcut-btn .sc-label {
            font-family: 'Poppins', sans-serif;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
            line-height: 1.3;
            color: #4b5563;
        }

        /* ===== ACTIVITY LIST ===== */
        .activity-list { list-style: none; padding: 0; margin: 0; }
        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px dashed #ececec;
        }
        .activity-item:last-child { border-bottom: none; }
        .activity-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-top: 6px;
            flex-shrink: 0;
        }
        .dot-success { background: var(--mrk-green); box-shadow: 0 0 0 3px rgba(8,167,148,0.15); }
        .dot-info { background: var(--mrk-blue); box-shadow: 0 0 0 3px rgba(11,70,172,0.15); }
        .dot-warning { background: var(--mrk-amber); box-shadow: 0 0 0 3px rgba(245,166,35,0.15); }
        .dot-danger { background: var(--mrk-red); box-shadow: 0 0 0 3px rgba(229,57,53,0.15); }

        .activity-text {
            font-size: 13px;
            color: var(--mrk-text);
            line-height: 1.5;
        }
        .activity-time {
            font-size: 11px;
            color: #9ca3af;
            font-family: 'Poppins', sans-serif;
        }

        @media (max-width: 992px) {
            .stat-cards { grid-template-columns: repeat(2, 1fr); }
            .bottom-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 576px) {
            .stat-cards { grid-template-columns: 1fr; }
            .shortcuts-grid { grid-template-columns: repeat(2, 1fr); }
            .welcome-title { font-size: 24px; }
            .welcome-date { position: static; text-align: left; margin-top: 15px; }
        }
    </style>
</head>

<body class="theme-blue">

<div class="container-fluid" style="padding: 20px;">

    <!-- HERO COM CÉU DINÂMICO -->
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
            <p class="welcome-subtitle">Bem-vindo ao Portal MRK. Gerencie manipulações, insumos, produção e controle de perdas com agilidade e precisão.</p>
        </div>
        <div class="welcome-date">
            <div class="date-day" id="dateDay"></div>
            <div class="date-text" id="dateText"></div>
            <div class="date-time" id="dateTime"></div>
        </div>
    </div>

    <!-- STAT CARDS -->
    <?php
    // Base URL do Adianti para os cards do topo também
    if ($_SERVER['SERVER_NAME'] === 'localhost') {
        $adiantiBaseTop = 'http://localhost/portal-mrk/index.php';
    } else {
        $adiantiBaseTop = 'https://portal.mrksolucoes.com.br/index.php';
    }
    ?>
    <div class="stat-cards">
        <a href="<?= $adiantiBaseTop ?>?class=DashboardFaturamento" target="_top" class="stat-card card-amber">
            <div class="stat-icon"><iconify-icon icon="icon-park-outline:sales-report"></iconify-icon></div>
            <div class="stat-number" id="statManipulacoes"><div class="skeleton-stat"></div></div>
            <div class="stat-label">Faturamento do Dia</div>
            <span class="stat-badge badge-neutral">hoje</span>
        </a>
        <a href="<?= $adiantiBaseTop ?>?class=DashboardEstoque" target="_top" class="stat-card card-blue">
            <div class="stat-icon"><iconify-icon icon="icon-park-outline:box"></iconify-icon></div>
            <div class="stat-number" id="statInsumos"><div class="skeleton-stat"></div></div>
            <div class="stat-label">Insumos em Estoque</div>
            <span class="stat-badge badge-up">↑ ativos</span>
        </a>
        <a href="<?= $adiantiBaseTop ?>?class=DashboardFinanceiro" target="_top" class="stat-card card-green">
            <div class="stat-icon"><iconify-icon icon="icon-park-outline:chart-line"></iconify-icon></div>
            <div class="stat-number" id="statPerda"><div class="skeleton-stat"></div></div>
            <div class="stat-label">Saldo Financeiro</div>
            <span class="stat-badge badge-up">este mês</span>
        </a>
        <a href="<?= $adiantiBaseTop ?>?class=OpenFinanceContas" target="_top" class="stat-card card-red">
            <div class="stat-icon"><iconify-icon icon="icon-park-outline:bank-card"></iconify-icon></div>
            <div class="stat-number" id="statAlertas"><div class="skeleton-stat"></div></div>
            <div class="stat-label">Contas Open Finance</div>
            <span class="stat-badge badge-neutral">sincronizadas</span>
        </a>
    </div>

    <!-- BOTTOM GRID -->
    <div class="bottom-grid">
        <!-- ATALHOS RÁPIDOS -->
        <div class="card">
            <div class="header">
                <h2>
                    <iconify-icon icon="icon-park-outline:lightning"></iconify-icon>
                    ACESSO RÁPIDO
                </h2>
            </div>
            <div class="body">
                <?php
                // Base URL do Adianti (janela de cima do iframe)
                if ($_SERVER['SERVER_NAME'] === 'localhost') {
                    $adiantiBase = 'http://localhost/portal-mrk/index.php';
                } else {
                    $adiantiBase = 'https://portal.mrksolucoes.com.br/index.php';
                }
                ?>
                <div class="shortcuts-grid">
                    <a href="<?= $adiantiBase ?>?class=DashboardFaturamento" target="_top" class="shortcut-btn">
                        <div class="sc-icon"><iconify-icon icon="icon-park-outline:sales-report"></iconify-icon></div>
                        <span class="sc-label">Dashboard Faturamento</span>
                    </a>
                    <a href="<?= $adiantiBase ?>?class=DashboardEstoque" target="_top" class="shortcut-btn">
                        <div class="sc-icon"><iconify-icon icon="icon-park-outline:box"></iconify-icon></div>
                        <span class="sc-label">Dashboard Estoque</span>
                    </a>
                    <a href="<?= $adiantiBase ?>?class=DashboardFinanceiro" target="_top" class="shortcut-btn">
                        <div class="sc-icon"><iconify-icon icon="icon-park-outline:chart-line"></iconify-icon></div>
                        <span class="sc-label">Dashboard Financeiro</span>
                    </a>
                    <a href="<?= $adiantiBase ?>?class=OpenFinanceContas" target="_top" class="shortcut-btn">
                        <div class="sc-icon"><iconify-icon icon="icon-park-outline:bank-card"></iconify-icon></div>
                        <span class="sc-label">Open Finance Contas</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- ATIVIDADE RECENTE -->
        <div class="card">
            <div class="header">
                <h2>
                    <iconify-icon icon="icon-park-outline:time"></iconify-icon>
                    ATIVIDADE RECENTE
                </h2>
            </div>
            <div class="body">
                <ul class="activity-list" id="activityList">
                    <li class="activity-item">
                        <div class="activity-dot dot-success"></div>
                        <div>
                            <div class="activity-text">Manipulação <strong>#MAN-2045</strong> concluída — Perda: 2,3%</div>
                            <span class="activity-time">Há 12 minutos</span>
                        </div>
                    </li>
                    <li class="activity-item">
                        <div class="activity-dot dot-info"></div>
                        <div>
                            <div class="activity-text">Novo insumo <strong>Farinha Integral</strong> cadastrado</div>
                            <span class="activity-time">Há 38 minutos</span>
                        </div>
                    </li>
                    <li class="activity-item">
                        <div class="activity-dot dot-warning"></div>
                        <div>
                            <div class="activity-text">Perda acima da meta em <strong>MAN-2043</strong> (7,8%)</div>
                            <span class="activity-time">Há 1 hora</span>
                        </div>
                    </li>
                    <li class="activity-item">
                        <div class="activity-dot dot-danger"></div>
                        <div>
                            <div class="activity-text">Estoque crítico: <strong>Açúcar Refinado</strong> abaixo do mínimo</div>
                            <span class="activity-time">Há 2 horas</span>
                        </div>
                    </li>
                    <li class="activity-item">
                        <div class="activity-dot dot-success"></div>
                        <div>
                            <div class="activity-text">Ordem de produção <strong>OP-0198</strong> finalizada</div>
                            <span class="activity-time">Há 3 horas</span>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-2.2.4.min.js"></script>
<script src="bsb/plugins/bootstrap/js/bootstrap.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

<script>
    $(document).ready(function () {
        generateStars();
        updateSky();
        setInterval(updateSky, 30000);
        updateDateTime();
        setInterval(updateDateTime, 1000);
        loadStats();
    });

    // ===== CÉU DINÂMICO =====
    const skyThemes = {
        dawn: {
            sky: 'linear-gradient(180deg, #1a1a3e 0%, #3d2c6e 20%, #c45e3a 55%, #f5a623 80%, #fce4a8 100%)',
            ground: '#1a1510',
            hills: '#2a1f18',
            cloudAlpha: 0.15,
            starsAlpha: 0.3,
            greeting: 'Bom dia'
        },
        earlyMorning: {
            sky: 'linear-gradient(180deg, #0B46AC 0%, #1E5FD4 30%, #4a8ec2 60%, #b8daf0 90%, #e8f0f5 100%)',
            ground: '#3d6b4a',
            hills: '#2d5a3a',
            cloudAlpha: 0.35,
            starsAlpha: 0,
            greeting: 'Bom dia'
        },
        midday: {
            sky: 'linear-gradient(180deg, #0B46AC 0%, #1E5FD4 25%, #5ba3d9 60%, #b8e2f8 100%)',
            ground: '#4a7c55',
            hills: '#3a6b45',
            cloudAlpha: 0.3,
            starsAlpha: 0,
            greeting: 'Bom dia'
        },
        afternoon: {
            sky: 'linear-gradient(180deg, #0B46AC 0%, #3a7cc2 35%, #d4a868 70%, #F5A623 100%)',
            ground: '#4a6b3a',
            hills: '#3a5a2d',
            cloudAlpha: 0.25,
            starsAlpha: 0,
            greeting: 'Boa tarde'
        },
        sunset: {
            sky: 'linear-gradient(180deg, #083682 0%, #0B46AC 20%, #5c3a8c 45%, #c44e3a 65%, #F5A623 85%, #fce08a 100%)',
            ground: '#1a1510',
            hills: '#251a12',
            cloudAlpha: 0.2,
            starsAlpha: 0.15,
            greeting: 'Boa tarde'
        },
        dusk: {
            sky: 'linear-gradient(180deg, #0a0e2a 0%, #0B46AC 30%, #3d2060 60%, #6a3060 80%, #1a1030 100%)',
            ground: '#0a0a10',
            hills: '#12101a',
            cloudAlpha: 0.08,
            starsAlpha: 0.7,
            greeting: 'Boa noite'
        },
        night: {
            sky: 'linear-gradient(180deg, #050510 0%, #083682 20%, #0B46AC 40%, #0a1020 90%, #050510 100%)',
            ground: '#060608',
            hills: '#0a0a12',
            cloudAlpha: 0.05,
            starsAlpha: 1,
            greeting: 'Boa noite'
        }
    };

    function getThemeForHour(h) {
        if (h >= 5 && h < 7)   return skyThemes.dawn;
        if (h >= 7 && h < 10)  return skyThemes.earlyMorning;
        if (h >= 10 && h < 15) return skyThemes.midday;
        if (h >= 15 && h < 17) return skyThemes.afternoon;
        if (h >= 17 && h < 19) return skyThemes.sunset;
        if (h >= 19 && h < 21) return skyThemes.dusk;
        return skyThemes.night;
    }

    function getSunPosition(h, m) {
        const t = h + m / 60;
        if (t < 5 || t > 18.5) return { x: 85, y: 110, opacity: 0 };
        const progress = (t - 5) / 13.5;
        const x = 10 + progress * 80;
        const y = 100 - 90 * Math.sin(progress * Math.PI);
        let opacity = 1;
        if (progress < 0.08) opacity = progress / 0.08;
        if (progress > 0.92) opacity = (1 - progress) / 0.08;
        return { x, y: Math.max(5, y), opacity: Math.min(1, opacity) };
    }

    function getMoonPosition(h, m) {
        const t = h + m / 60;
        let progress;
        if (t >= 18) {
            progress = (t - 18) / 11;
        } else if (t < 5) {
            progress = (t + 6) / 11;
        } else {
            return { x: 85, y: 110, opacity: 0 };
        }
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

        const hero = document.getElementById('welcomeHero');
        hero.style.background = theme.sky;

        document.getElementById('skyGround').style.background = theme.ground;
        const hills = document.getElementById('skyHills');
        hills.style.color = theme.hills;
        document.getElementById('skyStars').style.opacity = theme.starsAlpha;

        document.getElementById('skyClouds').querySelectorAll('.cloud').forEach(c => {
            c.style.background = `rgba(255,255,255,${theme.cloudAlpha})`;
        });

        const sunPos = getSunPosition(h, m);
        const sun = document.getElementById('skySun');
        sun.style.left = sunPos.x + '%';
        sun.style.top = sunPos.y + '%';
        sun.style.opacity = sunPos.opacity;

        const moonPos = getMoonPosition(h, m);
        const moon = document.getElementById('skyMoon');
        moon.style.left = moonPos.x + '%';
        moon.style.top = moonPos.y + '%';
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

    // ===== DATA/HORA =====
    function updateDateTime() {
        const now = new Date();
        const dias = ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'];
        const meses = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];

        $('#dateDay').text(String(now.getDate()).padStart(2, '0'));
        $('#dateText').text(`${dias[now.getDay()]}, ${meses[now.getMonth()]} ${now.getFullYear()}`);
        $('#dateTime').text(now.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', second: '2-digit' }));
    }

    // ===== STATS =====
    async function loadStats() {
        try {
            // TODO: Substituir pelos endpoints reais do MRK
            // Exemplo: axios.post(baseUrl, { method: 'getDashboardStats', token: token, data: {...} })
            setTimeout(() => {
                animateNumber('#statManipulacoes', 18);
                animateNumber('#statInsumos', 142);
                animateNumber('#statPerda', 3, '%');
                animateNumber('#statAlertas', 4);
            }, 800);
        } catch (e) {
            $('#statManipulacoes, #statInsumos, #statPerda, #statAlertas').text('--');
        }
    }

    function animateNumber(selector, target, suffix) {
        suffix = suffix || '';
        const el = $(selector);
        el.empty();
        let current = 0;
        const step = Math.max(1, Math.floor(target / 30));
        const interval = setInterval(() => {
            current += step;
            if (current >= target) { current = target; clearInterval(interval); }
            el.text(current + suffix);
        }, 30);
    }
</script>

</body>
</html>