<?php
include 'securite.php';

require 'config.php';
try {
    $stmt = $pdo->query("
        SELECT c.id, c.date_collecte, c.lieu, b.nom AS benevole,
                SUM(d.quantite_kg) AS total,
                SUM(d.quantite_kg * (d.type_dechet = 'plastique')) AS plastique,
                SUM(d.quantite_kg * (d.type_dechet = 'verre')) AS verre,
                SUM(d.quantite_kg * (d.type_dechet = 'métal')) AS metal,
                SUM(d.quantite_kg * (d.type_dechet = 'organique')) AS organiques,
                SUM(d.quantite_kg * (d.type_dechet = 'papier')) AS papier
        FROM collectes c
        LEFT JOIN benevoles b ON c.id_benevole = b.id
        LEFT JOIN dechets_collectes d ON d.id_collecte = c.id
        GROUP BY c.id, c.date_collecte, c.lieu, b.nom
        ORDER BY c.date_collecte DESC;
    ");

    $query = $pdo->prepare("SELECT nom FROM benevoles WHERE role = 'admin' LIMIT 1");
    $query->execute();

    $collectes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $admin = $query->fetch(PDO::FETCH_ASSOC);
    $adminNom = $admin ? htmlspecialchars($admin['nom']) : 'Aucun administrateur trouvé';

    $totalDechets = 0;
    foreach ($collectes as $collecte) {
        $totalDechets += $collecte['total'];
    }

} catch (PDOException $e) {
    echo "Erreur de base de données : " . $e->getMessage();
    exit;
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Collectes</title>
    <head>
        <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&family=Lora:wght@400;700&family=Montserrat:wght@300;400;700&family=Open+Sans:wght@300;400;700&family=Poppins:wght@300;400;700&family=Playfair+Display:wght@400;700&family=Raleway:wght@300;400;700&family=Nunito:wght@300;400;700&family=Merriweather:wght@300;400;700&family=Oswald:wght@300;400;700&display=swap" rel="stylesheet">
    </head>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100 text-gray-900">
<div class="flex h-screen">
<?php 
require('menu.php');
?>
    <!-- Contenu principal -->
    <div class="flex-1 p-8 overflow-y-auto">
        <!-- Titre -->
        <h1 class="text-4xl font-bold text-sky-700 mb-6">Liste des Collectes de Déchets</h1>

        <!-- Message de notification (ex: succès de suppression ou ajout) -->
        <?php if (isset($_GET['message'])): ?>
            <div class="bg-green-100 text-green-800 p-4 rounded-md mb-6">
                <?= htmlspecialchars($_GET['message']) ?>
            </div>
        <?php endif; ?>

        <!-- Cartes d'informations -->
<div class="grid grid-cols-3 gap-6 mb-8">
    <!-- colonne Large -->
    <div class="col-span-2 bg-white p-6 rounded-lg shadow-lg">
        <h3 class="text-xl font-semibold text-amber-500 mb-3">Statistiques Globales</h3>

        <div class="grid grid-cols-2 gap-6">
            <!-- colonne infos -->
            <div class="flex flex-col space-y-4">
                <div>
                    <h4 class="text-lg font-medium text-gray-700">Total des Collectes</h4>
                    <p class="text-3xl font-bold text-sky-700"><?= count($collectes) ?></p>
                </div>
                <div>
                    <h4 class="text-lg font-medium text-gray-700">Total des déchets collectés</h4>
                    <p class="text-3xl font-bold text-sky-700"><?= number_format((float)$totalDechets, 2, '.', '')?> kg</p>
                </div>
            </div>

            <!-- colonne graphe -->
            <div class="flex justify-center">
                <canvas id="myPolarChart" class="h-140 max-w-xl"></canvas>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const ctx = document.getElementById('myPolarChart').getContext('2d');

                // Données récupérées depuis PHP
                const labels = ['Plastique', 'Verre', 'Métal', 'Organiques', 'Papier'];
                const dataValues = [
                    <?= array_sum(array_column($collectes, 'plastique')) ?>,
                    <?= array_sum(array_column($collectes, 'verre')) ?>,
                    <?= array_sum(array_column($collectes, 'metal')) ?>,
                    <?= array_sum(array_column($collectes, 'organiques')) ?>,
                    <?= array_sum(array_column($collectes, 'papier')) ?>
                ];

                Chart.register(ChartDataLabels);

                new Chart(ctx, {
                    type: 'polarArea',
                    data: {
                        
                        datasets: [{
                            data: dataValues,
                            backgroundColor: [
                                'rgba(255, 99, 132, 0.6)',
                                'rgba(54, 162, 235, 0.6)',
                                'rgba(255, 206, 86, 0.6)',
                                'rgba(75, 192, 192, 0.6)',
                                'rgba(153, 102, 255, 0.6)'
                            ]
                        }],
                        labels: labels,
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right'
                            },
                            datalabels: {
                                color: '#fff',
                                font: {
                                    weight: 'bold',
                                    size: 14
                                },
                                formatter: (value, context) => {
                                    return context.chart.data.labels[context.dataIndex];
                                }
                            }
                        }
                    }
                });
            });
        </script>
    </div>

    <!-- Colonne Petite (Dernière Collecte et Bénévole Admin) -->
    <div class="col-span-1 bg-white p-6 rounded-lg shadow-lg">
        <h3 class="text-xl font-semibold text-amber-500 mb-3">Dernière Collecte</h3>
        <p class="text-lg text-gray-600"><?= htmlspecialchars($collectes[0]['lieu']) ?></p>
        <p class="text-lg text-gray-600"><?= date('d/m/Y', strtotime($collectes[0]['date_collecte'])) ?></p>
        <br>
        <h3 class="text-xl font-semibold text-amber-500 mb-3">Bénévole Admin</h3>
        <p class="text-lg text-gray-600"><?= $adminNom ?></p>
    </div>
</div>
        <!-- Tableau des collectes -->
        <div class="overflow-hidden rounded-lg shadow-lg bg-white">
            <table class="w-full table-auto border-collapse">
                <thead class="bg-sky-700 text-white">
                <tr>
                    <th class="py-3 px-4 text-left">Date</th>
                    <th class="py-3 px-4 text-left">Lieu</th>
                    <th class="py-3 px-4 text-left">Bénévole Responsable</th>
                    <th class="py-3 px-4 text-left">Plastique</th>
                    <th class="py-3 px-4 text-left">Verre</th>
                    <th class="py-3 px-4 text-left">Métal</th>
                    <th class="py-3 px-4 text-left">Organiques</th>
                    <th class="py-3 px-4 text-left">Papier</th>
                    <th class="py-3 px-4 text-left">Total</th>
                    <th class="py-3 px-4 text-left">Actions</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-300">
                <?php foreach ($collectes as $collecte) : ?>
                    <tr class="hover:bg-gray-100 transition duration-200">
                        <td class="py-3 px-4"><?= date('d/m/Y', strtotime($collecte['date_collecte'])) ?></td>
                        <td class="py-3 px-4"><?= htmlspecialchars($collecte['lieu']) ?></td>
                        <td class="py-3 px-4"><?= $collecte['benevole'] ? htmlspecialchars($collecte['benevole']) : 'Aucun bénévole' ?></td>
                        <td class="py-3 px-4"><?= number_format((float)$collecte['plastique'], 2, '.', '')?> kg</td>
                        <td class="py-3 px-4"><?= number_format((float)$collecte['verre'], 2, '.', '')?> kg</td>
                        <td class="py-3 px-4"><?= number_format((float)$collecte['metal'], 2, '.', '')?> kg</td>
                        <td class="py-3 px-4"><?= number_format((float)$collecte['organiques'], 2, '.', '')?> kg</td>
                        <td class="py-3 px-4"><?= number_format((float)$collecte['papier'], 2, '.', '')?> kg</td>
                        <td class="py-3 px-4"><?= number_format((float)$collecte['total'], 2, '.', '')?> kg</td>                        
                        <td class="py-3 px-4 flex space-x-2">
                            <a href="collection_edit.php?id=<?= $collecte['id'] ?>" class="bg-cyan-500 hover:bg-cyan-700 text-white px-4 py-2 rounded-xl shadow focus:outline-none focus:ring-2 focus:ring-cyan-700 transition duration-200">
                                Modifier la collecte
                            </a>
                            <a href="collection_delete.php?id=<?= $collecte['id'] ?>" class="bg-amber-500 hover:bg-amber-700 text-white px-4 py-2 rounded-xl shadow focus:outline-none focus:ring-2 focus:ring-amber-700 transition duration-200" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette collecte ?');">
                                Supprimer la collecte
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>
