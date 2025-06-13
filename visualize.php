<?php
session_start();

// Validasi untuk memastikan semua data yang dibutuhkan ada di session
if (!isset($_SESSION['sort_steps'], $_SESSION['sorted_hotels'], $_SESSION['initial_hotels'], $_SESSION['metrics'])) {
    // Jika tidak ada, kembalikan ke halaman utama
    header('Location: index.php');
    exit;
}

// Ambil semua data dari session ke dalam variabel lokal
$steps = $_SESSION['sort_steps'];
$initialHotels = $_SESSION['initial_hotels'];
$finalMetrics = $_SESSION['metrics']; // Ganti nama agar lebih jelas

// Hapus session setelah data diambil untuk membersihkan memori
unset($_SESSION['sort_steps'], $_SESSION['sorted_hotels'], $_SESSION['initial_hotels'], $_SESSION['metrics']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sorting Visualization</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background-color: #f1f5f9; font-family: 'Inter', sans-serif; }
        .card { background-color: white; border-radius: 0.75rem; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1); }
        .table-row { transition: transform 0.5s ease-in-out, background-color 0.3s; }
        .highlight { background-color: #e0e7ff; }
        .comparing { background-color: #fef9c3; }
        .pivot { background-color: #f3e8ff; }
        .sorted { background-color: #d1fae5; border-left: 4px solid #10b981; }
        .swapping { background-color: #fee2e2; transform: scale(1.02); z-index: 10; box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1); }
        #controls { position: fixed; bottom: 1.5rem; left: 50%; transform: translateX(-50%); background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); padding: 0.75rem 1rem; border-radius: 999px; box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1); z-index: 100; border: 1px solid #e2e8f0; }
        
        @keyframes metric-pop {
            0% { transform: scale(1); }
            50% { transform: scale(1.25); color: #4f46e5; }
            100% { transform: scale(1); }
        }
        .metric-update {
            animation: metric-pop 0.3s ease-in-out;
        }
    </style>
</head>
<body class="min-h-screen">
    <div class="container mx-auto px-4 py-10">
        <div class="max-w-7xl mx-auto">
            <header class="text-center mb-10">
                <h1 class="text-4xl md:text-5xl font-bold text-slate-800 mb-3">Live Sorting Visualization</h1>
                <p class="text-lg text-slate-600">Melihat cara kerja algoritma secara real-time</p>
                <a href="index.php" class="text-indigo-600 hover:text-indigo-800 mt-5 inline-block group font-semibold">
                    <i class="fas fa-arrow-left mr-2 transition-transform group-hover:-translate-x-1"></i> Back to Home
                </a>
            </header>
            
            <div class="grid grid-cols-1 gap-8">
                <div class="card p-6">
                     <h2 class="text-2xl font-semibold text-slate-800 mb-4 flex items-center"><i class="fas fa-chart-bar text-slate-500 mr-3"></i> Live Chart</h2>
                    <div class="relative h-96 lg:h-[450px]"><canvas id="visualizationChart"></canvas></div>
                </div>
                <div class="card p-6">
                    <h2 class="text-2xl font-semibold text-slate-800 mb-4"><i class="fas fa-tasks text-slate-500 mr-3"></i> Data View</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white" id="visualizationTable">
                            <thead class="bg-slate-800 text-white">
                                <tr>
                                    <th class="py-3 px-4 text-left font-semibold">Hotel Name</th>
                                    <th class="py-3 px-4 text-left font-semibold">Distance</th>
                                    <th class="py-3 px-4 text-left font-semibold">Rating</th>
                                    <th class="py-3 px-4 text-left font-semibold">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200" id="visualizationBody">
                                <?php foreach ($initialHotels as $hotel): ?>
                                    <tr class="table-row" data-id="<?= htmlspecialchars($hotel['id']) ?>" data-nama="<?= htmlspecialchars($hotel['nama']) ?>" data-jarak="<?= $hotel['jarak'] ?>" data-rating="<?= $hotel['rating'] ?>">
                                        <td class="py-3 px-4 font-medium text-slate-700"><?= htmlspecialchars($hotel['nama']) ?></td>
                                        <td class="py-3 px-4 text-slate-700"><?= number_format($hotel['jarak'], 2) ?> km</td>
                                        <td class="py-3 px-4 text-slate-700"><?= number_format($hotel['rating'], 1) ?> ★</td>
                                        <td class="py-3 px-4 status-cell font-mono text-xs"></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-5 gap-8 mt-8">
                <div class="card p-6 lg:col-span-3">
                    <h2 class="text-2xl font-semibold text-slate-800 mb-4"><i class="fas fa-file-alt text-slate-500 mr-3"></i> Step Explanation</h2>
                    <div id="stepDescription" class="p-4 bg-slate-50 rounded-lg min-h-[8rem] text-slate-800 font-mono text-sm leading-relaxed">
                        <p class="text-slate-500">Click "Start" to begin the visualization.</p>
                    </div>
                </div>
                <div class="card p-6 lg:col-span-2">
                    <h2 class="text-2xl font-semibold text-slate-800 mb-4"><i class="fas fa-calculator text-slate-500 mr-3"></i> Statistics</h2>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-3">
                            <h3 class="font-bold text-center text-slate-600">Live Metrics</h3>
                            <div class="text-center p-3 bg-slate-50 rounded-lg">
                                <span class="text-xs text-slate-500">Visualization Time</span>
                                <div id="liveTimer" class="font-bold text-amber-600 text-2xl">0.0 s</div>
                            </div>
                            <div class="text-center p-3 bg-slate-50 rounded-lg">
                                <span class="text-xs text-slate-500">Live Swaps</span>
                                <div id="liveSwaps" class="font-bold text-sky-600 text-2xl">0</div>
                            </div>
                            <div class="text-center p-3 bg-slate-50 rounded-lg">
                                <span class="text-xs text-slate-500">Live Comparisons</span>
                                <div id="liveComparisons" class="font-bold text-teal-600 text-2xl">0</div>
                            </div>
                        </div>
                        <div class="space-y-3 border-l border-slate-200 pl-4">
                            <h3 class="font-bold text-center text-slate-600">Final Server Stats</h3>
                             <div class="text-center p-3 bg-slate-100 rounded-lg">
                                <span class="text-xs text-slate-500">Execution Time</span>
                                <div class="font-bold text-amber-700 text-2xl" title="Waktu eksekusi algoritma di server"><?= number_format($finalMetrics['execution_time'], 4) ?> s</div>
                            </div>
                            <div class="text-center p-3 bg-slate-100 rounded-lg">
                                <span class="text-xs text-slate-500">Total Swaps</span>
                                <div class="font-bold text-sky-700 text-2xl"><?= number_format($finalMetrics['swaps']) ?></div>
                            </div>
                            <div class="text-center p-3 bg-slate-100 rounded-lg">
                                <span class="text-xs text-slate-500">Total Comparisons</span>
                                <div class="font-bold text-teal-700 text-2xl"><?= number_format($finalMetrics['comparisons']) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
             <footer class="text-center mt-12 text-slate-500">
                <p>&copy; <?= date('Y') ?> Sorting Algorithm Visualizer. All rights reserved.</p>
            </footer>
        </div>
    </div>

    <div id="controls" class="flex items-center gap-2 md:gap-3">
        <select id="speedControl" class="border border-slate-300 rounded-full px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500"><option value="1200">Slow</option><option value="700" selected>Medium</option><option value="300">Fast</option></select>
        <button id="startBtn" class="bg-indigo-600 text-white py-2 px-5 rounded-full hover:bg-indigo-700 transition flex items-center font-semibold"><i class="fas fa-play mr-2"></i> Start</button>
        <button id="nextStepBtn" class="bg-slate-700 text-white py-2 px-5 rounded-full hover:bg-slate-800 transition hidden"><i class="fas fa-step-forward mr-2"></i> Next</button>
        <button id="autoPlayBtn" class="bg-teal-600 text-white py-2 px-5 rounded-full hover:bg-teal-700 transition hidden"><i class="fas fa-sync-alt mr-2"></i> Auto</button>
    </div>

    <script>
        const steps = <?= json_encode($steps) ?>;
        
        let liveSwaps = 0;
        let liveComparisons = 0;
        let visualizationTimerInterval = null;
        let elapsedTime = 0;

        const liveTimerDisplay = document.getElementById('liveTimer');
        const liveSwapsDisplay = document.getElementById('liveSwaps');
        const liveComparisonsDisplay = document.getElementById('liveComparisons');
        
        let currentStep = 0;
        let animationSpeed = 700;
        let autoPlayInterval = null;
        const tbody = document.getElementById('visualizationBody');
        let originalOrder = Array.from(tbody.querySelectorAll('tr')).map(row => row.cloneNode(true));
        let visualizationChart = null;
        
        const findRow = (hotelData) => hotelData ? document.querySelector(`tr[data-id="${hotelData.id}"]`) : null;

        function initializeChart() {
            const ctx = document.getElementById('visualizationChart').getContext('2d');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const labels = rows.map(row => row.dataset.nama);
            const data = rows.map(row => parseFloat(row.dataset.jarak));
            visualizationChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Distance (km)',
                        data: data,
                        backgroundColor: '#e0e7ff',
                        borderColor: '#c7d2fe',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: 400
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Distance (km)'
                            }
                        },
                        x: {
                            ticks: {
                                maxRotation: 45,
                                minRotation: 45,
                                font: {
                                    size: 10
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }

        function updateChart(updateOrder = true) {
            if (!visualizationChart) return;
            const rowsInTable = Array.from(tbody.querySelectorAll('tr'));
            const labelsInTable = rowsInTable.map(row => row.dataset.nama);
            const dataMap = new Map();
            rowsInTable.forEach(row => {
                dataMap.set(row.dataset.nama, {
                    jarak: parseFloat(row.dataset.jarak),
                    row: row,
                });
            });
            const currentChartLabels = visualizationChart.data.labels;
            const newData = currentChartLabels.map(label => dataMap.get(label)?.jarak || 0);
            const newColors = currentChartLabels.map(label => {
                const row = dataMap.get(label)?.row;
                if (!row) return '#e0e7ff';
                if (row.classList.contains('swapping')) return '#fee2e2';
                if (row.classList.contains('pivot')) return '#f3e8ff';
                if (row.classList.contains('comparing')) return '#fef9c3';
                if (row.classList.contains('sorted')) return '#d1fae5';
                return '#e0e7ff';
            });
            visualizationChart.data.datasets[0].data = newData;
            visualizationChart.data.datasets[0].backgroundColor = newColors;
            if (updateOrder) {
                visualizationChart.data.labels = labelsInTable;
            }
            visualizationChart.update();
        }

        function updateStepDescription(html) {
            document.getElementById('stepDescription').innerHTML = html;
        }

        function startVisualizationTimer() {
            if (visualizationTimerInterval) clearInterval(visualizationTimerInterval);
            elapsedTime = 0;
            liveTimerDisplay.textContent = '0.0 s';
            visualizationTimerInterval = setInterval(() => {
                elapsedTime += 0.1;
                liveTimerDisplay.textContent = elapsedTime.toFixed(1) + ' s';
            }, 100);
        }

        function stopVisualizationTimer() {
            clearInterval(visualizationTimerInterval);
        }

        function resetLiveMetrics() {
            liveSwaps = 0;
            liveComparisons = 0;
            updateMetricDisplay(liveSwapsDisplay, 0);
            updateMetricDisplay(liveComparisonsDisplay, 0);
            liveSwapsDisplay.classList.remove('metric-update');
            liveComparisonsDisplay.classList.remove('metric-update');
            stopVisualizationTimer();
            liveTimerDisplay.textContent = '0.0 s';
        }
        
        function updateMetricDisplay(element, value) {
            element.textContent = value;
            element.classList.add('metric-update');
            setTimeout(() => element.classList.remove('metric-update'), 300);
        }

        function executeStep(step) {
            document.querySelectorAll("#visualizationBody tr").forEach(row => {
                row.classList.remove("highlight", "comparing", "swapping", "pivot");
                if (!row.classList.contains("sorted")) {
                    row.querySelector(".status-cell").textContent = ""
                }
            });

            if (step.type === "compare") {
                liveComparisons++;
                updateMetricDisplay(liveComparisonsDisplay, liveComparisons)
            }
            if (step.type === "swap" || step.type === "final_swap") {
                liveSwaps++;
                updateMetricDisplay(liveSwapsDisplay, liveSwaps)
            }

            const findRowIndex = rowElement => Array.from(tbody.children).indexOf(rowElement);
            let descriptionHtml = "";
            switch (step.type) {
                case "select_pivot": {
                    const pivotRow = findRow(step.pivot);
                    if (pivotRow) pivotRow.classList.add("pivot");
                    descriptionHtml = `<i class="fas fa-crosshairs text-violet-500 mr-2"></i><b>PIVOT:</b> Dipilih <strong>${step.pivot.nama}</strong>.`;
                    updateChart(false);
                    break
                }
                case "compare": {
                    const leftHotelRow = findRow(step.left ?? step.current);
                    const rightHotelRow = findRow(step.right ?? step.pivot);
                    if (leftHotelRow) leftHotelRow.classList.add("comparing");
                    if (rightHotelRow) rightHotelRow.classList.add("comparing");
                    let reason = "",
                        op = step.left ? "<strong>" + step.left.nama + "</strong> vs <strong>" + step.right.nama + "</strong>" : "<strong>" + step.current.nama + "</strong> vs Pivot";
                    if (step.comparison <= 0) {
                        reason = "Kondisi terpenuhi, posisi sesuai."
                    } else {
                        reason = "Kondisi tidak terpenuhi."
                    }
                    descriptionHtml = `<i class="fas fa-balance-scale-right text-amber-500 mr-2"></i><b>COMPARE:</b> ${op}<br><span class="text-xs pl-6"><em>&rarr; ${reason}</em></span>`;
                    updateChart(false);
                    break
                }
                case "swap":
                case "final_swap": {
                    const row1 = findRow(step.value1);
                    const row2 = findRow(step.value2);
                    const index1 = findRowIndex(row1);
                    const index2 = findRowIndex(row2);
                    descriptionHtml = `<i class="fas fa-exchange-alt text-red-500 mr-2"></i><b>SWAP:</b> Tukar <strong>${step.value1.nama}</strong> (posisi ${index1+1}) dengan <strong>${step.value2.nama}</strong> (posisi ${index2+1}).`;
                    if (row1 && row2 && row1 !== row2) {
                        row1.classList.add("swapping");
                        row2.classList.add("swapping");
                        updateChart(false);
                        setTimeout(() => {
                            const parent = row1.parentNode;
                            const next1 = row1.nextSibling;
                            const next2 = row2.nextSibling;
                            if (next1) parent.insertBefore(row2, next1);
                            else parent.appendChild(row2);
                            if (next2) parent.insertBefore(row1, next2);
                            else parent.appendChild(row1);
                            updateChart(true);
                            setTimeout(() => {
                                row1.classList.remove("swapping");
                                row2.classList.remove("swapping");
                                updateChart(true)
                            }, 150)
                        }, animationSpeed * .5)
                    }
                    break
                }
                case "after_partition": {
                    const pivotData = step.array[step.pivot_index];
                    const pivotRow = findRow(pivotData);
                    if (pivotRow) {
                        pivotRow.classList.add("sorted");
                        pivotRow.querySelector(".status-cell").textContent = "\u2714"
                    }
                    descriptionHtml = `<i class="fas fa-check-circle text-green-500 mr-2"></i><b>SORTED:</b> Pivot <strong>${pivotData.nama}</strong> berada di posisi final.`;
                    updateChart(false);
                    break
                }
                case "before_merge": {
                    descriptionHtml = `<i class="fas fa-layer-group text-sky-500 mr-2"></i><b>MERGE:</b> Mempersiapkan penggabungan dua sub-array.`;
                    step.left.concat(step.right).forEach(h => {
                        const r = findRow(h);
                        if (r) r.classList.add("highlight")
                    });
                    updateChart(false);
                    break
                }
                case "after_merge": {
                    descriptionHtml = `<i class="fas fa-check-double text-sky-500 mr-2"></i><b>MERGED:</b> Sub-array berhasil digabung & diurutkan.`;
                    const rowsToMove = step.result.map(h => findRow(h)).filter(r => r);
                    rowsToMove.forEach(r => r.classList.add("swapping"));
                    updateChart(false);
                    setTimeout(() => {
                        const tempFragment = document.createDocumentFragment();
                        const anchor = rowsToMove.length > 0 ? rowsToMove[0].previousSibling : null;
                        rowsToMove.forEach(r => tempFragment.appendChild(r));
                        if (anchor) anchor.after(tempFragment);
                        else tbody.prepend(tempFragment);
                        updateChart(true);
                        setTimeout(() => {
                            rowsToMove.forEach(r => r.classList.remove("swapping"));
                            updateChart(true)
                        }, 150)
                    }, animationSpeed * .5)
                }
            }
            updateStepDescription(descriptionHtml)
        }
        
        const controls = {
            speed: document.getElementById("speedControl"),
            start: document.getElementById("startBtn"),
            next: document.getElementById("nextStepBtn"),
            auto: document.getElementById("autoPlayBtn")
        };

        controls.speed.addEventListener("change", function() {
            animationSpeed = parseInt(this.value);
            if (autoPlayInterval) {
                clearInterval(autoPlayInterval);
                startAutoPlay()
            }
        });

        controls.start.addEventListener("click", function() {
            this.classList.add("hidden");
            controls.next.classList.remove("hidden");
            controls.auto.classList.remove("hidden");
            updateStepDescription("Ready. Click 'Next' or 'Auto'.");
            resetLiveMetrics();
            startVisualizationTimer();
            currentStep = 0;
            if (steps.length > 0) executeStep(steps[0])
        });

        controls.next.addEventListener("click", function() {
            if (currentStep >= steps.length - 1) {
                updateStepDescription('<i class="fas fa-flag-checkered text-green-500 mr-2"></i><b>SELESAI!</b> Semua hotel telah terurut.');
                finalizeSort();
                return
            }
            currentStep++;
            executeStep(steps[currentStep])
        });

        controls.auto.addEventListener("click", function() {
            if (autoPlayInterval) {
                clearInterval(autoPlayInterval);
                autoPlayInterval = null;
                this.innerHTML = '<i class="fas fa-sync-alt mr-2"></i> Auto';
                controls.next.disabled = false;
                stopVisualizationTimer();
            } else {
                startAutoPlay();
                this.innerHTML = '<i class="fas fa-stop mr-2"></i> Stop';
                controls.next.disabled = true;
            }
        });

        function startAutoPlay() {
            if (currentStep >= steps.length - 1) {
                resetToOriginal()
            }
            startVisualizationTimer();
            autoPlayInterval = setInterval(() => {
                if (currentStep >= steps.length - 1) {
                    clearInterval(autoPlayInterval);
                    autoPlayInterval = null;
                    controls.auto.innerHTML = '<i class="fas fa-sync-alt mr-2"></i> Auto';
                    controls.next.disabled = false;
                    updateStepDescription('<i class="fas fa-flag-checkered text-green-500 mr-2"></i><b>SELESAI!</b> Semua hotel telah terurut.');
                    finalizeSort();
                    return
                }
                currentStep++;
                executeStep(steps[currentStep])
            }, animationSpeed + 200)
        }

        function finalizeSort() {
            stopVisualizationTimer();
            document.querySelectorAll("#visualizationBody tr").forEach(row => {
                row.classList.remove("comparing", "swapping", "pivot", "highlight");
                row.classList.add("sorted");
                row.querySelector(".status-cell").textContent = "\u2714"
            });
            updateChart(false)
        }

        function resetToOriginal() {
            tbody.innerHTML = "";
            originalOrder.forEach(row => tbody.appendChild(row.cloneNode(true)));
            visualizationChart.data.labels = Array.from(tbody.querySelectorAll("tr")).map(row => row.dataset.nama);
            updateChart(true);
            resetLiveMetrics();
        }

        document.addEventListener("DOMContentLoaded", initializeChart);
    </script>
</body>
</html>