<?php
session_start();

// ======================================================
// KUMPULAN FUNGSI PHP
// ======================================================

/**
 * Memuat data hotel dari file teks dan menambahkan ID unik.
 */
function loadHotels($filename) {
    $hotels = [];
    if (file_exists($filename)) {
        $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $idCounter = 0;
        foreach ($lines as $line) {
            $parts = explode('|', $line);
            if (count($parts) >= 3) {
                $hotels[] = [
                    'id' => 'hotel-' . $idCounter++, // ID Unik untuk setiap hotel
                    'nama' => trim($parts[0]),
                    'jarak' => floatval(trim($parts[1])),
                    'rating' => floatval(trim($parts[2]))
                ];
            }
        }
    }
    return $hotels;
}

/**
 * Menyimpan data hotel ke file teks.
 */
function saveHotels($filename, $hotels) {
    $lines = [];
    foreach ($hotels as $hotel) {
        $lines[] = implode('|', [$hotel['nama'], $hotel['jarak'], $hotel['rating']]);
    }
    file_put_contents($filename, implode(PHP_EOL, $lines));
}

/**
 * Fungsi perbandingan untuk sorting: Prioritas jarak (terdekat), lalu rating (tertinggi).
 */
function compareHotels($a, $b) {
    if ($a['jarak'] != $b['jarak']) {
        return $a['jarak'] <=> $b['jarak'];
    }
    return $b['rating'] <=> $a['rating'];
}


// --- Merge Sort dengan Metrik ---
function mergeSortWithSteps(&$hotels, &$steps = [], &$metrics) {
    if (count($hotels) <= 1) return $hotels;
    $mid = (int)(count($hotels) / 2);
    $left = array_slice($hotels, 0, $mid);
    $right = array_slice($hotels, $mid);

    $left = mergeSortWithSteps($left, $steps, $metrics);
    $right = mergeSortWithSteps($right, $steps, $metrics);
    
    $steps[] = ['type' => 'before_merge', 'left' => $left, 'right' => $right];
    $result = mergeWithSteps($left, $right, $steps, $metrics);
    $steps[] = ['type' => 'after_merge', 'result' => $result];

    return $result;
}

function mergeWithSteps($left, $right, &$steps, &$metrics) {
    $result = [];
    $leftIndex = $rightIndex = 0;
    while ($leftIndex < count($left) && $rightIndex < count($right)) {
        $metrics['comparisons']++;
        $comparison = compareHotels($left[$leftIndex], $right[$rightIndex]);
        $steps[] = ['type' => 'compare', 'left' => $left[$leftIndex], 'right' => $right[$rightIndex], 'comparison' => $comparison];

        if ($comparison <= 0) {
            $result[] = $left[$leftIndex++];
        } else {
            $result[] = $right[$rightIndex++];
        }
    }
    while ($leftIndex < count($left)) $result[] = $left[$leftIndex++];
    while ($rightIndex < count($right)) $result[] = $right[$rightIndex++];
    return $result;
}

// --- Quick Sort dengan Metrik ---
function quickSortWithSteps(&$hotels, $low, $high, &$steps = [], &$metrics) {
    if ($low < $high) {
        $pi = partitionWithSteps($hotels, $low, $high, $steps, $metrics);
        $steps[] = ['type' => 'after_partition', 'array' => $hotels, 'pivot_index' => $pi];
        quickSortWithSteps($hotels, $low, $pi - 1, $steps, $metrics);
        quickSortWithSteps($hotels, $pi + 1, $high, $steps, $metrics);
    }
}

function partitionWithSteps(&$hotels, $low, $high, &$steps, &$metrics) {
    $pivot = $hotels[$high];
    $i = $low - 1;
    $steps[] = ['type' => 'select_pivot', 'pivot' => $pivot, 'index' => $high];

    for ($j = $low; $j <= $high - 1; $j++) {
        $metrics['comparisons']++;
        $steps[] = ['type' => 'compare', 'current' => $hotels[$j], 'pivot' => $pivot, 'comparison' => compareHotels($hotels[$j], $pivot)];
        
        if (compareHotels($hotels[$j], $pivot) <= 0) {
            $i++;
            if ($i != $j) {
                $metrics['swaps']++;
                $steps[] = ['type' => 'swap', 'index1' => $i, 'index2' => $j, 'value1' => $hotels[$i], 'value2' => $hotels[$j]];
                [$hotels[$i], $hotels[$j]] = [$hotels[$j], $hotels[$i]];
            }
        }
    }
    
    if (($i + 1) != $high) {
        $metrics['swaps']++;
        $steps[] = ['type' => 'final_swap', 'index1' => $i + 1, 'index2' => $high, 'value1' => $hotels[$i + 1], 'value2' => $hotels[$high]];
        [$hotels[$i + 1], $hotels[$high]] = [$hotels[$high], $hotels[$i + 1]];
    }
    return $i + 1;
}

// ======================================================
// LOGIKA UTAMA HALAMAN
// ======================================================

$hotelFile = 'hotels.txt';

if (!file_exists($hotelFile)) {
    $initialData = [
        "Best View Studio Apartemen|1.87|3.0", "Oakwood Hotel & Residence Surabaya|2.00|5.0", "Best Choice and Nice Studio Apartemen|1.00|4.4", "Rumah Kertajaya|0.60|4.2", "Best and Tiny Studio With Pool view|1.86|3.0", "Nice and Comfy Studio Grand Dharmahusada|0.50|3.0", "Comfy and Nice Studio Grand Dharmahusada|0.60|3.0", "Apartemen Educity|1.73|4.7", "Frank's Hotel|1.83|4.2", "Luxurious & Cozy 2BR Apartemen|1.32|4.3", "Luxurious & Spacious 2BR|1.34|4.3", "Cozy Stay Studio Apartemen|1.89|4.8", "Calm and Relaxing Studio Apartemen|1.88|4.8", "Cozy Stay and Best Choice|1.44|4.4", "Homey and Modern 2BR|1.44|4.2", "Comfy and Clean 2BR Apartemen|1.45|4.2", "2BR Apartemen and Dian Regency|1.71|3.8", "Mandiri Mansion|1.09|4.2", "The Alimar Premiere Hotel|1.40|4.1"
    ];
    file_put_contents($hotelFile, implode(PHP_EOL, $initialData));
}

$hotels = loadHotels($hotelFile);

// Proses Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_hotel'])) {
        $newHotel = ['nama' => $_POST['nama'], 'jarak' => floatval($_POST['jarak']), 'rating' => floatval($_POST['rating'])];
        $currentHotels = loadHotels($hotelFile);
        $currentHotels[] = $newHotel;
        saveHotels($hotelFile, $currentHotels);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } 
    elseif (isset($_POST['sort_type'])) {
        $sortType = $_POST['sort_type'];
        $hotelsCopy = $hotels;
        $sortSteps = [];
        $metrics = ['swaps' => 0, 'comparisons' => 0, 'execution_time' => 0];
        $startTime = microtime(true);
        if ($sortType === 'merge') {
            $sortedHotels = mergeSortWithSteps($hotelsCopy, $sortSteps, $metrics);
        } elseif ($sortType === 'quick') {
            quickSortWithSteps($hotelsCopy, 0, count($hotelsCopy) - 1, $sortSteps, $metrics);
            $sortedHotels = $hotelsCopy;
        }
        $endTime = microtime(true);
        $metrics['execution_time'] = $endTime - $startTime;
        $_SESSION['sort_steps'] = $sortSteps;
        $_SESSION['sorted_hotels'] = $sortedHotels;
        $_SESSION['initial_hotels'] = $hotels;
        $_SESSION['metrics'] = $metrics;
        header('Location: visualize.php');
        exit;
    }
    // DIUBAH: Logika pencarian dipisah menjadi dua
    elseif (isset($_POST['search_by_rating'])) {
        $minRating = floatval($_POST['min_rating'] ?? 0);
        $searchType = 'rating';
        $searchResults = array_filter($hotels, function($hotel) use ($minRating) {
            return $hotel['rating'] >= $minRating;
        });
    }
    elseif (isset($_POST['search_by_distance'])) {
        $maxDistance = floatval($_POST['max_distance'] ?? 999);
        $searchType = 'distance';
        $searchResults = array_filter($hotels, function($hotel) use ($maxDistance) {
            return $hotel['jarak'] <= $maxDistance;
        });
    }
}

$hotelNames = array_map(fn($h) => $h['nama'], $hotels);
$hotelRatings = array_map(fn($h) => $h['rating'], $hotels);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel Sorting and Search System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background-color: #f1f5f9; }
        .card { background-color: white; border-radius: 0.75rem; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1); }
        .loader { border-top-color: #4f46e5; animation: spinner 1.5s linear infinite; }
        @keyframes spinner { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .table-row-animate { animation: fadeIn 0.5s ease-out forwards; }
    </style>
</head>
<body class="min-h-screen">
    <div class="container mx-auto px-4 py-10">
        <div class="max-w-7xl mx-auto">
            <header class="text-center mb-12">
                <h1 class="text-4xl md:text-5xl font-bold text-slate-800 mb-3">Hotel Sorting & Search</h1>
                <p class="text-lg text-slate-600">Analisis dan Visualisasi Algoritma Sorting</p>
            </header>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-1 space-y-8">
                    <div class="card p-6 h-fit">
                        <h2 class="text-2xl font-semibold text-slate-800 mb-5 flex items-center"><i class="fas fa-plus-circle text-indigo-500 mr-3"></i>Add New Hotel</h2>
                        <form method="POST" class="space-y-4">
                            <div>
                                <label for="nama" class="block text-sm font-medium text-slate-700 mb-1">Hotel Name</label>
                                <input type="text" id="nama" name="nama" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                            </div>
                            <div>
                                <label for="jarak" class="block text-sm font-medium text-slate-700 mb-1">Distance (km)</label>
                                <input type="number" step="0.01" id="jarak" name="jarak" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                            </div>
                            <div>
                                <label for="rating" class="block text-sm font-medium text-slate-700 mb-1">Rating (0-5)</label>
                                <input type="number" step="0.1" min="0" max="5" id="rating" name="rating" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                            </div>
                            <button type="submit" name="add_hotel" class="w-full bg-indigo-600 text-white font-bold py-2.5 px-4 rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-300 flex items-center justify-center">
                                <i class="fas fa-save mr-2"></i> Save Hotel
                            </button>
                        </form>
                    </div>

                    <div class="card p-6 h-fit">
                        <h2 class="text-2xl font-semibold text-slate-800 mb-5 flex items-center"><i class="fas fa-star text-amber-500 mr-3"></i>Search by Rating</h2>
                        <form method="POST" class="space-y-4">
                            <div>
                                <label for="min_rating" class="block text-sm font-medium text-slate-700 mb-1">Minimum Rating</label>
                                <input type="number" step="0.1" min="0" max="5" id="min_rating" name="min_rating" placeholder="e.g., 4.5" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition">
                            </div>
                            <button type="submit" name="search_by_rating" class="w-full bg-amber-500 text-white font-bold py-2.5 px-4 rounded-lg hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 transition duration-300 flex items-center justify-center">
                                <i class="fas fa-search mr-2"></i> Search Rating
                            </button>
                        </form>
                    </div>
                    
                    <div class="card p-6 h-fit">
                        <h2 class="text-2xl font-semibold text-slate-800 mb-5 flex items-center"><i class="fas fa-road text-teal-500 mr-3"></i>Search by Distance</h2>
                        <form method="POST" class="space-y-4">
                            <div>
                                <label for="max_distance" class="block text-sm font-medium text-slate-700 mb-1">Maximum Distance (km)</label>
                                <input type="number" step="0.1" min="0" id="max_distance" name="max_distance" placeholder="e.g., 1.5" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition">
                            </div>
                            <button type="submit" name="search_by_distance" class="w-full bg-teal-500 text-white font-bold py-2.5 px-4 rounded-lg hover:bg-teal-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500 transition duration-300 flex items-center justify-center">
                                <i class="fas fa-search-location mr-2"></i> Search Distance
                            </button>
                        </form>
                    </div>
                </div>

                <div class="lg:col-span-2 space-y-8">
                    <div id="loading" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                        <div class="loader ease-linear rounded-full border-8 border-t-8 border-slate-200 h-32 w-32"></div>
                    </div>
                    
                    <div class="card p-6">
                        <h3 class="text-2xl font-semibold text-slate-800 mb-4">Sort & Visualize</h3>
                        <p class="text-slate-600 mb-5">Pilih algoritma untuk memulai visualisasi pengurutan data hotel secara live.</p>
                        <form method="POST" class="grid grid-cols-1 sm:grid-cols-2 gap-4" id="sortForm">
                            <button type="submit" name="sort_type" value="merge" class="bg-blue-600 text-white font-bold py-3 px-4 rounded-lg hover:bg-blue-700 transition duration-300 flex items-center justify-center text-lg"><i class="fas fa-layer-group mr-3"></i> Merge Sort</button>
                            <button type="submit" name="sort_type" value="quick" class="bg-violet-600 text-white font-bold py-3 px-4 rounded-lg hover:bg-violet-700 transition duration-300 flex items-center justify-center text-lg"><i class="fas fa-bolt mr-3"></i> Quick Sort</button>
                        </form>
                    </div>

                    <?php if (isset($searchType)): ?>
                    <div class="card p-6 table-row-animate">
                        <?php
                            $resultTitle = ($searchType === 'rating') ? 'Rating' : 'Distance';
                            $resultIcon = ($searchType === 'rating') ? 'fa-star text-amber-500' : 'fa-road text-teal-500';
                            $results = isset($searchResults) ? $searchResults : [];
                        ?>
                        <h2 class="text-2xl font-semibold text-slate-800 mb-4"><i class="fas <?= $resultIcon ?> mr-3"></i> Search Results by <?= $resultTitle ?> (<?= count($results) ?> Found)</h2>
                        <?php if (empty($results)): ?>
                            <p class="text-center text-slate-500 py-8">No hotels found matching your criteria.</p>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full bg-white">
                                    <thead class="bg-slate-700 text-white">
                                        <tr>
                                            <th class="py-3 px-4 text-left font-semibold">Hotel Name</th>
                                            <th class="py-3 px-4 text-left font-semibold">Distance (km)</th>
                                            <th class="py-3 px-4 text-left font-semibold">Rating</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200">
                                        <?php foreach ($results as $hotel): ?>
                                            <tr class="hover:bg-slate-50">
                                                <td class="py-3 px-4 font-medium text-slate-700"><?= htmlspecialchars($hotel['nama']) ?></td>
                                                <td class="py-3 px-4 text-slate-700"><?= number_format($hotel['jarak'], 2) ?></td>
                                                <td class="py-3 px-4 text-slate-700"><?= number_format($hotel['rating'], 1) ?> ★</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="card p-6">
                        <h2 class="text-2xl font-semibold text-slate-800 mb-4"><i class="fas fa-list-ul text-slate-500 mr-3"></i> Current Hotel List</h2>
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white">
                                <thead class="bg-slate-800 text-white">
                                    <tr>
                                        <th class="py-3 px-4 text-left font-semibold">#</th>
                                        <th class="py-3 px-4 text-left font-semibold">Hotel Name</th>
                                        <th class="py-3 px-4 text-left font-semibold">Distance (km)</th>
                                        <th class="py-3 px-4 text-left font-semibold">Rating</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200">
                                    <?php foreach ($hotels as $index => $hotel): ?>
                                        <tr class="hover:bg-slate-50 table-row-animate" style="animation-delay: <?= $index * 0.05 ?>s">
                                            <td class="py-3 px-4 text-slate-500"><?= $index + 1 ?></td>
                                            <td class="py-3 px-4 font-medium text-slate-700"><?= htmlspecialchars($hotel['nama']) ?></td>
                                            <td class="py-3 px-4 text-slate-700"><?= number_format($hotel['jarak'], 2) ?></td>
                                            <td class="py-3 px-4 text-slate-700">
                                                <div class="flex items-center">
                                                    <?= number_format($hotel['rating'], 1) ?>
                                                    <div class="ml-2 flex text-amber-400">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?><i class="fa-star <?= $i <= $hotel['rating'] ? 'fas' : 'far' ?>"></i><?php endfor; ?>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <footer class="text-center mt-12 text-slate-500">
                <p>&copy; <?= date('Y') ?> Sorting Algorithm Visualizer. All rights reserved.</p>
            </footer>
        </div>
    </div>
<script>
    document.getElementById('sortForm').addEventListener('submit', function() {
        document.getElementById('loading').classList.remove('hidden');
    });
</script>
</body>
</html>