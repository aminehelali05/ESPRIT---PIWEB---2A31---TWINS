<?php
include_once(__DIR__ . '/../../Controllers/stats_brainstorming.php');
include_once(__DIR__ . '/../../Controllers/BrainstormingController.php');

$bid = $_GET['id'] ?? null;
if (!$bid) {
    die("ID de brainstorming manquant.");
}

$statsController = new BrainstormingStats();
$brainController = new BrainstormingController();

$session = $brainController->getBrainstormingById($bid);
$stats = $statsController->getStats($bid);
$summary = $statsController->generateEnhancedSummary($bid);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Analytique - <?= htmlspecialchars($session->getTitle()) ?></title>
    <link rel="stylesheet" href="../../assets/css/global.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --primary: #8b5cf6;
            --secondary: #3b82f6;
            --bg-dark: #0f172a;
            --card-bg: #ffffff;
        }

        body { background: #f8fafc; font-family: 'Inter', sans-serif; color: #1e293b; }

        .dashboard-container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }

        .header-section { margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }

        .ai-summary-card {
            grid-column: 1 / -1;
            background: linear-gradient(135deg, #8b5cf6 0%, #3b82f6 100%);
            color: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 25px -5px rgba(139, 92, 246, 0.3);
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .ai-summary-card::after {
            content: "✨ AI ANALYSIS";
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 0.7rem;
            font-weight: 800;
            background: rgba(255,255,255,0.2);
            padding: 4px 12px;
            border-radius: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
        }

        .chart-card {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }

        .chart-title { font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }

        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr; }
        }

        .loading-overlay {
            position: absolute;
            inset: 0;
            background: rgba(139, 92, 246, 0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="header-section">
            <div>
                <a href="brainstorming_history.php" style="color: var(--primary); text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 5px;">
                    <i data-lucide="arrow-left"></i> Retour à l'historique
                </a>
                <h1 style="margin-top: 10px;"><?= htmlspecialchars($session->getTitle()) ?></h1>
            </div>
            <div style="text-align: right;">
                <span style="display: block; font-weight: 700; font-size: 1.5rem; color: var(--primary);"><?= $stats['general']['total_ideas'] ?></span>
                <small style="color: #64748b;">Contributions totales</small>
            </div>
        </div>

        <div class="ai-summary-card" id="aiSummarySection">
            <div class="loading-overlay" id="aiLoading">Génération du nouveau résumé...</div>
            <h2 style="margin-top: 0; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <i data-lucide="sparkles"></i> Résumé Stratégique
            </h2>
            <div id="aiSummaryContent">
                <?= $summary ?>
            </div>
        </div>

        <div class="stats-grid">
            <!-- Graphique 1 : Catégories -->
            <div class="chart-card">
                <div class="chart-title"><i data-lucide="pie-chart"></i> Répartition par Catégorie</div>
                <canvas id="categoryChart"></canvas>
            </div>

            <!-- Graphique 2 : Tendance Votes -->
            <div class="chart-card">
                <div class="chart-title"><i data-lucide="trending-up"></i> Tendance des Votes</div>
                <canvas id="votesTrendChart"></canvas>
            </div>

            <!-- Graphique 3 : Top Idées -->
            <div class="chart-card">
                <div class="chart-title"><i data-lucide="award"></i> Top 3 Idées (Score vs Votants)</div>
                <canvas id="topIdeasChart"></canvas>
            </div>

            <!-- Graphique 4 : Heatmap Horaire (Simulée) -->
            <div class="chart-card">
                <div class="chart-title"><i data-lucide="clock"></i> Activité Horaire</div>
                <canvas id="hourlyChart"></canvas>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        // 1. Chart Catégories (Pie)
        const catCtx = document.getElementById('categoryChart').getContext('2d');
        const catLabels = <?= json_encode(array_column($stats['categories'], 'category')) ?>;
        const catData = <?= json_encode(array_column($stats['categories'], 'count')) ?>;

        const categoryChart = new Chart(catCtx, {
            type: 'pie',
            data: {
                labels: catLabels,
                datasets: [{
                    data: catData,
                    backgroundColor: ['#8b5cf6', '#3b82f6', '#10b981', '#f59e0b', '#ef4444'],
                    borderWidth: 0
                }]
            },
            options: {
                plugins: { legend: { position: 'bottom' } },
                onClick: (evt, item) => {
                    if (item.length > 0) {
                        const index = item[0].index;
                        const category = catLabels[index];
                        refreshAiSummary(category);
                    }
                }
            }
        });

        // 2. Chart Tendance (Line)
        const trendCtx = document.getElementById('votesTrendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($stats['vote_trends'], 'date')) ?>,
                datasets: [{
                    label: 'Upvotes',
                    data: <?= json_encode(array_column($stats['vote_trends'], 'upvotes')) ?>,
                    borderColor: '#10b981',
                    tension: 0.4,
                    fill: true,
                    backgroundColor: 'rgba(16, 185, 129, 0.1)'
                }]
            },
            options: { responsive: true, scales: { y: { beginAtZero: true } } }
        });

        // 3. Top Idées (Horizontal Bar)
        const topCtx = document.getElementById('topIdeasChart').getContext('2d');
        new Chart(topCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($stats['top_ideas'], 'title')) ?>,
                datasets: [{
                    label: 'Score de Vote',
                    data: <?= json_encode(array_column($stats['top_ideas'], 'score')) ?>,
                    backgroundColor: '#8b5cf6'
                }, {
                    label: 'Nb Votants',
                    data: <?= json_encode(array_column($stats['top_ideas'], 'total_voters')) ?>,
                    backgroundColor: '#3b82f6'
                }]
            },
            options: { indexAxis: 'y', responsive: true }
        });

        // 4. Heatmap Horaire (Simulée)
        const hourCtx = document.getElementById('hourlyChart').getContext('2d');
        new Chart(hourCtx, {
            type: 'bar',
            data: {
                labels: ['08h', '10h', '12h', '14h', '16h', '18h', '20h'],
                datasets: [{
                    label: 'Activité',
                    data: [12, 19, 3, 5, 2, 23, 10],
                    backgroundColor: 'rgba(59, 130, 246, 0.5)',
                    borderRadius: 5
                }]
            }
        });

        // Interaction AJAX
        async function refreshAiSummary(category) {
            const overlay = document.getElementById('aiLoading');
            const content = document.getElementById('aiSummaryContent');
            
            overlay.style.display = 'flex';
            
            try {
                const response = await fetch(`../../api/api_resume_filtered.php?id=<?= $bid ?>&category=${category}`);
                const data = await response.json();
                
                if (data.success) {
                    content.innerHTML = `<h3>Analyse pour : ${category}</h3>` + data.summary;
                } else {
                    alert("Erreur lors de la mise à jour du résumé.");
                }
            } catch (e) {
                console.error(e);
            } finally {
                overlay.style.display = 'none';
            }
        }
    </script>
</body>
</html>
