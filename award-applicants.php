<?php
session_start();
require_once 'api/config.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Get award category from URL
$awardCategory = $_GET['category'] ?? null;

if (!$awardCategory) {
    header('Location: awards.php#award-list');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($awardCategory); ?> - Applicants | LILAC</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Material Symbols -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

    <!-- Custom Styles -->
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-slide-in {
            animation: slideIn 0.3s ease-out forwards;
        }

        .stagger-1 { animation-delay: 0.1s; }
        .stagger-2 { animation-delay: 0.2s; }
        .stagger-3 { animation-delay: 0.3s; }
        .stagger-4 { animation-delay: 0.4s; }
    </style>

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#3B82F6',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100">

    <!-- Header -->
    <header class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 sticky top-0 z-50">
        <div class="container mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <a href="awards.php#award-list" class="flex items-center gap-2 text-gray-600 dark:text-gray-400 hover:text-primary transition-colors">
                        <span class="material-symbols-outlined">arrow_back</span>
                        <span>Back to Award List</span>
                    </a>
                </div>
                <div class="flex items-center gap-4">
                    <button class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-200 dark:hover:bg-white/10 text-gray-600 dark:text-gray-400 transition-colors duration-200" id="theme-toggle" title="Toggle dark mode">
                        <span class="material-symbols-outlined dark:hidden">light_mode</span>
                        <span class="material-symbols-outlined hidden dark:inline">dark_mode</span>
                    </button>
                    <span class="text-sm text-gray-600 dark:text-gray-400">
                        <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </span>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-6 py-8">

        <!-- Page Title -->
        <div class="mb-4">
            <div class="flex items-center gap-3 mb-2">
                <div class="h-1 w-8 bg-gradient-to-r from-primary to-purple-600 rounded-full"></div>
                <h1 class="text-lg font-bold text-gray-900 dark:text-white" id="page-title">
                    Loading...
                </h1>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 ml-11">
                All applicants for this award category
            </p>
        </div>

        <!-- Statistics Cards -->
        <div id="stats-container" class="grid grid-cols-2 md:grid-cols-4 gap-2.5 mb-4">
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 border border-blue-200 dark:border-blue-800 rounded-lg p-2.5 animate-slide-in stagger-1 opacity-0 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-blue-600 dark:text-blue-400 text-sm">people</span>
                        <span class="text-[10px] font-medium text-blue-700 dark:text-blue-300 uppercase tracking-wide">Total</span>
                    </div>
                    <div class="text-lg font-bold text-blue-600 dark:text-blue-400">
                        <span id="stat-total">0</span>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 dark:from-yellow-900/20 dark:to-yellow-800/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-2.5 animate-slide-in stagger-2 opacity-0 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-yellow-600 dark:text-yellow-400 text-sm">schedule</span>
                        <span class="text-[10px] font-medium text-yellow-700 dark:text-yellow-300 uppercase tracking-wide">Pending</span>
                    </div>
                    <div class="text-lg font-bold text-yellow-600 dark:text-yellow-400">
                        <span id="stat-pending">0</span>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20 border border-green-200 dark:border-green-800 rounded-lg p-2.5 animate-slide-in stagger-3 opacity-0 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-green-600 dark:text-green-400 text-sm">check_circle</span>
                        <span class="text-[10px] font-medium text-green-700 dark:text-green-300 uppercase tracking-wide">Recognized</span>
                    </div>
                    <div class="text-lg font-bold text-green-600 dark:text-green-400">
                        <span id="stat-recognized">0</span>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900/20 dark:to-purple-800/20 border border-purple-200 dark:border-purple-800 rounded-lg p-2.5 animate-slide-in stagger-4 opacity-0 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-purple-600 dark:text-purple-400 text-sm">settings</span>
                        <span class="text-[10px] font-medium text-purple-700 dark:text-purple-300 uppercase tracking-wide">Processed</span>
                    </div>
                    <div class="text-lg font-bold text-purple-600 dark:text-purple-400">
                        <span id="stat-processed">0</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter and Search -->
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-2.5 mb-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-2">
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 text-sm">search</span>
                    <input type="text" id="search-input" placeholder="Search by username or email..."
                        class="w-full pl-8 pr-3 py-1.5 text-sm bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all">
                </div>

                <select id="status-filter" class="px-3 py-1.5 text-sm bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Recognized</option>
                    <option value="analyzed">Processed</option>
                </select>

                <select id="eligibility-filter" class="px-3 py-1.5 text-sm bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all">
                    <option value="">All Eligibility</option>
                    <option value="eligible">✅ Eligible (≥90%)</option>
                    <option value="almost">🟡 Almost Eligible (70-89%)</option>
                    <option value="not-eligible">❌ Not Eligible (<70%)</option>
                </select>

                <select id="sort-select" class="px-3 py-1.5 text-sm bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all">
                    <option value="similarity-desc">Similarity (High to Low)</option>
                    <option value="similarity-asc">Similarity (Low to High)</option>
                    <option value="date-desc">Date (Newest First)</option>
                    <option value="date-asc">Date (Oldest First)</option>
                </select>
            </div>
        </div>

        <!-- Applicants Grid -->
        <div id="applicants-container" class="grid grid-cols-1 gap-6">
            <!-- Loading indicator -->
            <div id="loading-indicator" class="col-span-full flex items-center justify-center py-12">
                <div class="flex items-center gap-3 text-gray-500 dark:text-gray-400">
                    <svg class="animate-spin h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Loading applicants...</span>
                </div>
            </div>
        </div>

    </main>

    <!-- Toast Notification -->
    <div id="toast" class="fixed bottom-6 right-6 px-6 py-3 rounded-lg shadow-lg transform translate-y-20 opacity-0 transition-all duration-300 z-50">
        <span id="toast-message"></span>
    </div>

    <!-- File Viewer Modal -->
    <div id="fileViewerModal" class="fixed inset-0 bg-black bg-opacity-90 z-50 hidden flex items-center justify-center p-4">
        <div class="relative max-w-6xl w-full max-h-[90vh] bg-white dark:bg-gray-800 rounded-lg overflow-hidden">
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white" id="fileViewerTitle">View File</h3>
                <button onclick="closeFileViewer()" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                    <span class="material-symbols-outlined text-gray-600 dark:text-gray-400">close</span>
                </button>
            </div>
            <!-- Modal Content -->
            <div class="p-4 overflow-auto max-h-[calc(90vh-80px)] flex items-center justify-center">
                <img id="fileViewerImage" src="" alt="File Preview" class="max-w-full max-h-[80vh] object-contain rounded-lg hidden">
                <iframe id="fileViewerIframe" src="" class="w-full h-[80vh] border-0 hidden"></iframe>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all duration-300 scale-95 opacity-0" id="deleteModalContent">
            <div class="p-6">
                <!-- Modal Header -->
                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 dark:bg-red-900/20 rounded-full">
                    <span class="material-symbols-outlined text-red-600 dark:text-red-400 text-2xl">warning</span>
                </div>

                <!-- Modal Title -->
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white text-center mb-2">
                    Delete Award
                </h3>

                <!-- Modal Message -->
                <p class="text-sm text-gray-600 dark:text-gray-300 text-center mb-6">
                    Are you sure you want to delete this award? This action cannot be undone and will permanently remove the award and its associated files.
                </p>

                <!-- Modal Actions -->
                <div class="flex gap-3 justify-center">
                    <button id="deleteCancelBtn"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors duration-200">
                        Cancel
                    </button>
                    <button id="deleteConfirmBtn"
                        class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors duration-200">
                        Delete Award
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const AUTH_TOKEN = '<?php echo $_SESSION['token'] ?? ''; ?>';
        const AWARD_CATEGORY = '<?php echo addslashes($awardCategory); ?>';
        const IS_ADMIN = <?php echo $isAdmin ? 'true' : 'false'; ?>;

        let allApplicants = [];
        let filteredApplicants = [];

        // Load applicants data
        async function loadApplicants() {
            try {
                const response = await fetch(`api/award-applicants.php?category=${encodeURIComponent(AWARD_CATEGORY)}`, {
                    headers: {
                        'Authorization': 'Bearer ' + AUTH_TOKEN
                    }
                });

                if (!response.ok) throw new Error('Failed to fetch applicants');

                const result = await response.json();
                if (!result.success) throw new Error(result.error || 'Failed to load applicants');

                allApplicants = result.applicants;
                filteredApplicants = [...allApplicants];

                // Update page title
                document.getElementById('page-title').textContent = AWARD_CATEGORY;

                // Update statistics
                updateStatistics(result.stats);

                // Render applicants
                renderApplicants();

            } catch (error) {
                console.error('Error loading applicants:', error);
                showToast('Failed to load applicants: ' + error.message, 'error');
            }
        }

        // Update statistics
        function updateStatistics(stats) {
            document.getElementById('stat-total').textContent = stats.total || 0;
            document.getElementById('stat-pending').textContent = stats.pending || 0;
            document.getElementById('stat-recognized').textContent = stats.recognized || 0;
            document.getElementById('stat-processed').textContent = stats.processed || 0;
        }

        // Render applicants - All awards are displayed with the same collapsible card format
        function renderApplicants() {
            const container = document.getElementById('applicants-container');
            const loadingIndicator = document.getElementById('loading-indicator');

            if (loadingIndicator) {
                loadingIndicator.remove();
            }

            if (filteredApplicants.length === 0) {
                container.innerHTML = `
                    <div class="col-span-full text-center py-12">
                        <span class="material-symbols-outlined text-6xl text-gray-300 dark:text-gray-600 mb-4">inbox</span>
                        <p class="text-gray-500 dark:text-gray-400">No applicants found</p>
                    </div>
                `;
                return;
            }

            // Apply the same collapsible card format to ALL awards
            container.innerHTML = filteredApplicants.map((app, index) => {
                const similarity = Math.round((app.similarity_score || app.match_percentage / 100) * 100);

                const statusMap = {
                    'pending': { bg: 'bg-yellow-100 dark:bg-yellow-900/30', text: 'text-yellow-700 dark:text-yellow-400', border: 'border-yellow-200 dark:border-yellow-800', label: 'Pending' },
                    'approved': { bg: 'bg-green-100 dark:bg-green-900/30', text: 'text-green-700 dark:text-green-400', border: 'border-green-200 dark:border-green-800', label: 'Recognized' },
                    'analyzed': { bg: 'bg-purple-100 dark:bg-purple-900/30', text: 'text-purple-700 dark:text-purple-400', border: 'border-purple-200 dark:border-purple-800', label: 'Processed' },
                    'rejected': { bg: 'bg-red-100 dark:bg-red-900/30', text: 'text-red-700 dark:text-red-400', border: 'border-red-200 dark:border-red-800', label: 'Rejected' }
                };

                const currentStatus = (app.award_status || 'pending').toLowerCase();
                const statusColor = statusMap[currentStatus] || statusMap['pending'];

                const progressColorClass = similarity >= 90 ? 'bg-gradient-to-r from-green-400 to-green-600' :
                                          similarity >= 75 ? 'bg-gradient-to-r from-blue-400 to-blue-600' :
                                          similarity >= 60 ? 'bg-gradient-to-r from-yellow-400 to-yellow-600' :
                                          'bg-gradient-to-r from-red-400 to-red-600';

                const textColorClass = similarity >= 90 ? 'text-green-600 dark:text-green-400' :
                                      similarity >= 75 ? 'text-blue-600 dark:text-blue-400' :
                                      similarity >= 60 ? 'text-yellow-600 dark:text-yellow-400' :
                                      'text-red-600 dark:text-red-400';

                // Eligibility status based on similarity score
                const eligibilityStatus = similarity >= 90 ? {
                    label: '✅ Eligible',
                    bg: 'bg-green-100 dark:bg-green-900/30',
                    text: 'text-green-700 dark:text-green-400',
                    border: 'border-green-300 dark:border-green-700'
                } : similarity >= 70 ? {
                    label: '🟡 Almost Eligible',
                    bg: 'bg-yellow-100 dark:bg-yellow-900/30',
                    text: 'text-yellow-700 dark:text-yellow-400',
                    border: 'border-yellow-300 dark:border-yellow-700'
                } : {
                    label: '❌ Not Eligible',
                    bg: 'bg-red-100 dark:bg-red-900/30',
                    text: 'text-red-700 dark:text-red-400',
                    border: 'border-red-300 dark:border-red-700'
                };

                return `
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700/50 rounded-xl py-2.5 px-4 hover:shadow-lg hover:-translate-y-0.5 hover:border-primary/30 transition-all duration-200 animate-slide-in opacity-0 group" style="animation-delay: ${index * 0.05}s" data-award-id="${app.award_id}">
                        <!-- Collapsed Summary View -->
                        <div class="collapsed-view">
                            <div class="flex items-center gap-3">
                                <!-- Avatar Section -->
                                <div class="flex-shrink-0">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br ${similarity >= 90 ? 'from-green-400 to-green-600' : similarity >= 70 ? 'from-yellow-400 to-yellow-600' : 'from-red-400 to-red-600'} flex items-center justify-center shadow-md">
                                        <span class="material-symbols-outlined text-white text-lg">person</span>
                                    </div>
                                </div>
                                
                                <!-- Main Content - Single Line -->
                                <div class="flex-1 min-w-0 flex items-center gap-3">
                                    ${app.submission_title ? `
                                        <div class="flex items-center gap-1.5 text-sm font-medium text-gray-900 dark:text-white truncate max-w-[200px]">
                                            <span class="truncate">${escapeHtml(app.submission_title)}</span>
                                        </div>
                                    ` : ''}
                                    <div class="flex items-center gap-1.5">
                                        <span class="px-3 py-1 ${statusColor.bg} ${statusColor.text} rounded-full text-[10px] font-semibold whitespace-nowrap shadow-sm">
                                            ${statusColor.label}
                                        </span>
                                        <span class="px-3 py-1 ${eligibilityStatus.bg} ${eligibilityStatus.text} border ${eligibilityStatus.border} rounded-full text-[10px] font-semibold whitespace-nowrap">
                                            ${eligibilityStatus.label}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                                        <span class="material-symbols-outlined text-sm">calendar_today</span>
                                        <span>${new Date(app.created_at).toLocaleDateString()}</span>
                                    </div>
                                </div>
                                
                                <!-- Similarity Score & Expand -->
                                <div class="flex items-center gap-3 flex-shrink-0">
                                    <div class="flex items-center gap-2">
                                        <div class="text-right">
                                            <div class="flex items-baseline gap-0.5">
                                                <span class="text-lg font-bold ${textColorClass}">${similarity}</span>
                                                <span class="text-xs font-medium ${textColorClass}">%</span>
                                            </div>
                                            <div class="text-[9px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Match</div>
                                        </div>
                                        <div class="w-12 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                            <div class="h-full ${progressColorClass} ${similarity >= 90 ? 'bg-gradient-to-r from-green-500 to-green-600' : similarity >= 70 ? 'bg-gradient-to-r from-yellow-500 to-yellow-600' : 'bg-gradient-to-r from-red-500 to-red-600'} rounded-full transition-all duration-500" style="width: ${similarity}%"></div>
                                        </div>
                                    </div>
                                    <button onclick="toggleAwardDetails('${app.award_id}')" class="w-8 h-8 flex items-center justify-center hover:bg-gray-100 dark:hover:bg-gray-700 rounded-full transition-all group-hover:bg-primary/10 group-hover:text-primary">
                                        <span class="material-symbols-outlined text-gray-600 dark:text-gray-400 group-hover:text-primary transition-transform duration-200" id="icon-${app.award_id}">expand_more</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Expanded Detailed View -->
                        <div class="expanded-view hidden">
                            <!-- Header with Collapse Button -->
                            <div class="flex items-center justify-between mb-4 pb-3 border-b border-gray-200 dark:border-gray-700">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 rounded-full bg-gradient-to-br ${similarity >= 90 ? 'from-green-400 to-green-600' : similarity >= 70 ? 'from-yellow-400 to-yellow-600' : 'from-red-400 to-red-600'} flex items-center justify-center shadow-md">
                                        <span class="material-symbols-outlined text-white text-xl">person</span>
                                    </div>
                                    <div>
                                        <h3 class="text-base font-bold text-gray-900 dark:text-white">${escapeHtml(app.username || 'Unknown User')}</h3>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">${escapeHtml(app.email || 'No email')}</p>
                                    </div>
                                </div>
                                <button onclick="toggleAwardDetails('${app.award_id}')" class="px-3 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-full transition-colors flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-400">
                                    <span class="material-symbols-outlined text-sm">expand_less</span>
                                    <span>Hide Details</span>
                                </button>
                            </div>

                            <!-- Main Content Grid -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4 items-start">
                                <!-- Left Column -->
                                <div class="flex flex-col gap-4">
                                    <!-- Similarity Score Card -->
                                    <div class="bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-700/50 dark:to-gray-800/50 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                                        <div class="flex items-center justify-between mb-3">
                                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Similarity Score</span>
                                            <span class="text-2xl font-bold ${textColorClass}">${similarity}%</span>
                                        </div>
                                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3 overflow-hidden mb-2">
                                            <div class="h-3 rounded-full transition-all duration-500 ${progressColorClass} ${similarity >= 90 ? 'bg-gradient-to-r from-green-500 to-green-600' : similarity >= 70 ? 'bg-gradient-to-r from-yellow-500 to-yellow-600' : 'bg-gradient-to-r from-red-500 to-red-600'}"
                                                 style="width: ${similarity}%">
                                            </div>
                                        </div>
                                        <p class="text-xs text-gray-600 dark:text-gray-400">
                                            ${similarity >= 90 ? '✅ 90%+ = Eligible' : similarity >= 70 ? '🟡 70-89% = Almost Eligible' : '❌ <70% = Not Eligible'}
                                        </p>
                                    </div>

                                    <!-- Submission Info -->
                                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                                        <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Submission Details</h4>
                                        <div class="space-y-2">
                                            <div class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                                <span class="material-symbols-outlined text-base text-gray-500 dark:text-gray-400">description</span>
                                                <span class="font-medium">${escapeHtml(app.submission_title || 'No title')}</span>
                                            </div>
                                            <div class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                                <span class="material-symbols-outlined text-base text-gray-500 dark:text-gray-400">calendar_today</span>
                                                <span>${new Date(app.created_at).toLocaleDateString()}</span>
                                            </div>
                                            <div class="flex items-center gap-2 pt-2">
                                                <span class="px-3 py-1 ${statusColor.bg} ${statusColor.text} rounded-full text-xs font-semibold whitespace-nowrap shadow-sm">
                                                    ${statusColor.label}
                                                </span>
                                                <span class="px-3 py-1 ${eligibilityStatus.bg} ${eligibilityStatus.text} border ${eligibilityStatus.border} rounded-full text-xs font-semibold whitespace-nowrap">
                                                    ${eligibilityStatus.label}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Right Column -->
                                <div class="flex flex-col gap-4">
                                    <!-- Matched Criteria -->
                                    ${app.matched_criteria && app.matched_criteria.length > 0 ? `
                                        <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 border border-green-200 dark:border-green-800">
                                            <div class="flex items-center justify-between mb-3">
                                                <h4 class="text-sm font-semibold text-green-700 dark:text-green-400 flex items-center gap-2">
                                                    <span class="material-symbols-outlined text-base">check_circle</span>
                                                    Matched Criteria
                                                </h4>
                                                <span class="text-xs font-medium text-green-600 dark:text-green-400">${app.criteria_met || app.matched_criteria.length}/${app.criteria_total || app.matched_criteria.length + (app.unmatched_criteria?.length || 0)}</span>
                                            </div>
                                            <div class="flex flex-wrap gap-2">
                                                ${app.matched_criteria.map(c => `
                                                    <span class="px-3 py-1.5 bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-400 rounded-full text-xs font-medium">
                                                        ✓ ${escapeHtml(c)}
                                                    </span>
                                                `).join('')}
                                            </div>
                                        </div>
                                    ` : '<div></div>'}
                                    
                                    <!-- Unmatched Criteria -->
                                    ${app.unmatched_criteria && app.unmatched_criteria.length > 0 ? `
                                        <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4 border border-red-200 dark:border-red-800">
                                            <h4 class="text-sm font-semibold text-red-700 dark:text-red-400 flex items-center gap-2 mb-3">
                                                <span class="material-symbols-outlined text-base">cancel</span>
                                                Unmatched Criteria
                                            </h4>
                                            <div class="flex flex-wrap gap-2">
                                                ${app.unmatched_criteria.map(c => `
                                                    <span class="px-3 py-1.5 bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-400 rounded-full text-xs font-medium">
                                                        ✗ ${escapeHtml(c)}
                                                    </span>
                                                `).join('')}
                                            </div>
                                        </div>
                                    ` : '<div></div>'}
                                </div>
                            </div>

                            <!-- Uploaded File Section -->
                            ${app.file_path && app.file_name ? `
                                <div class="mb-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                                    <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Uploaded File</h4>
                                    <div class="flex items-center gap-3 p-3 bg-white dark:bg-gray-800 rounded-lg">
                                        ${(() => {
                                            const fileName = app.file_name || '';
                                            const fileExt = fileName.toLowerCase().split('.').pop() || '';
                                            const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'].includes(fileExt);
                                            const isDocument = ['pdf', 'doc', 'docx', 'txt'].includes(fileExt);
                                            return `
                                                <span class="material-symbols-outlined text-2xl text-primary flex-shrink-0">
                                                    ${isImage ? 'image' : isDocument ? 'description' : 'insert_drive_file'}
                                                </span>
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">${escapeHtml(fileName)}</p>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400">${app.file_type || fileExt.toUpperCase() || 'File'}</p>
                                                </div>
                                                <div class="flex gap-2 flex-shrink-0">
                                                    ${isImage ? `
                                                        <button onclick="viewFile('${escapeHtml(app.file_path)}', '${escapeHtml(fileName)}', 'image')"
                                                            class="px-3 py-1.5 bg-primary hover:bg-primary/90 text-white text-xs font-medium rounded-full transition-all hover:scale-105 flex items-center gap-1.5">
                                                            <span class="material-symbols-outlined text-sm">visibility</span>
                                                            <span>View</span>
                                                        </button>
                                                    ` : isDocument ? `
                                                        <button onclick="viewFile('${escapeHtml(app.file_path)}', '${escapeHtml(fileName)}', 'document')"
                                                            class="px-3 py-1.5 bg-primary hover:bg-primary/90 text-white text-xs font-medium rounded-full transition-all hover:scale-105 flex items-center gap-1.5">
                                                            <span class="material-symbols-outlined text-sm">visibility</span>
                                                            <span>View</span>
                                                        </button>
                                                    ` : ''}
                                                    <a href="${escapeHtml(app.file_path)}" target="_blank" download="${escapeHtml(fileName)}"
                                                        class="px-3 py-1.5 bg-gray-600 hover:bg-gray-700 text-white text-xs font-medium rounded-full transition-all hover:scale-105 flex items-center gap-1.5">
                                                        <span class="material-symbols-outlined text-sm">download</span>
                                                        <span>Download</span>
                                                    </a>
                                                </div>
                                            `;
                                        })()}
                                    </div>
                                </div>
                            ` : ''}

                        <!-- Status Update Buttons (Admin Only) -->
                        ${IS_ADMIN ? `
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-2.5">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-1.5 flex-1">
                                        <span class="text-[10px] font-medium text-gray-600 dark:text-gray-400 mr-1">Status:</span>
                                        <button onclick="updateStatus('${app.award_id}', 'pending')"
                                            class="px-2.5 py-1 ${currentStatus === 'pending' ? 'bg-yellow-600 text-white border-yellow-600' : 'bg-transparent border border-yellow-300 dark:border-yellow-700 text-yellow-700 dark:text-yellow-400'} rounded-full text-[10px] font-medium hover:opacity-80 transition-all hover:scale-105 flex items-center gap-1">
                                            <span class="material-symbols-outlined text-xs">schedule</span>
                                            <span>Pending</span>
                                        </button>
                                        <button onclick="updateStatus('${app.award_id}', 'recognized')"
                                            class="px-2.5 py-1 ${currentStatus === 'approved' ? 'bg-green-600 text-white border-green-600' : 'bg-transparent border border-green-300 dark:border-green-700 text-green-700 dark:text-green-400'} rounded-full text-[10px] font-medium hover:opacity-80 transition-all hover:scale-105 flex items-center gap-1">
                                            <span class="material-symbols-outlined text-xs">emoji_events</span>
                                            <span>Recognized</span>
                                        </button>
                                        <button onclick="updateStatus('${app.award_id}', 'processed')"
                                            class="px-2.5 py-1 ${currentStatus === 'analyzed' ? 'bg-purple-600 text-white border-purple-600' : 'bg-transparent border border-purple-300 dark:border-purple-700 text-purple-700 dark:text-purple-400'} rounded-full text-[10px] font-medium hover:opacity-80 transition-all hover:scale-105 flex items-center gap-1">
                                            <span class="material-symbols-outlined text-xs">settings</span>
                                            <span>Processed</span>
                                        </button>
                                    </div>
                                    <button onclick="deleteAward('${app.award_id}')"
                                        class="px-2.5 py-1 bg-red-600 hover:bg-red-700 text-white rounded-full text-[10px] font-medium hover:opacity-90 transition-all hover:scale-105 flex items-center gap-1 shadow-sm">
                                        <span class="material-symbols-outlined text-xs">delete</span>
                                        <span>Delete</span>
                                    </button>
                                </div>
                            </div>
                        ` : ''}
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Update status
        async function updateStatus(awardId, newStatus) {
            try {
                const response = await fetch(`api/award-applicants.php?award_id=${awardId}`, {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + AUTH_TOKEN,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ status: newStatus })
                });

                if (!response.ok) throw new Error('Failed to update status');

                const result = await response.json();
                if (!result.success) throw new Error(result.error || 'Failed to update');

                showToast(`Status updated to: ${newStatus}`, 'success');

                // Reload applicants
                await loadApplicants();

            } catch (error) {
                console.error('Error updating status:', error);
                showToast('Failed to update status: ' + error.message, 'error');
            }
        }

        // Delete award functionality
        let pendingDeleteId = null;

        function deleteAward(awardId) {
            pendingDeleteId = awardId;
            showDeleteModal();
        }

        function showDeleteModal() {
            const modal = document.getElementById('deleteModal');
            const modalContent = document.getElementById('deleteModalContent');
            
            modal.classList.remove('hidden');
            setTimeout(() => {
                modalContent.classList.remove('scale-95', 'opacity-0');
                modalContent.classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        function hideDeleteModal() {
            const modal = document.getElementById('deleteModal');
            const modalContent = document.getElementById('deleteModalContent');
            
            modalContent.classList.remove('scale-100', 'opacity-100');
            modalContent.classList.add('scale-95', 'opacity-0');
            
            setTimeout(() => {
                modal.classList.add('hidden');
                pendingDeleteId = null;
            }, 300);
        }

        async function confirmDelete() {
            if (!pendingDeleteId) return;

            const awardId = pendingDeleteId;
            hideDeleteModal();

            try {
                // Use the award ID directly (database ID)
                const deleteUrl = `api/delete-award.php?id=${encodeURIComponent(awardId)}`;
                const response = await fetch(deleteUrl, {
                    method: 'DELETE'
                });

                if (response.ok) {
                    const result = await response.json();
                    showToast('Award deleted successfully!', 'success');
                    // Reload applicants
                    await loadApplicants();
                } else {
                    const errorData = await response.json().catch(() => ({ error: 'Failed to delete award' }));
                    throw new Error(errorData.error || 'Failed to delete award');
                }
            } catch (error) {
                console.error('Error deleting award:', error);
                showToast('Failed to delete award: ' + error.message, 'error');
            }
        }

        // Toggle award details (expand/collapse)
        function toggleAwardDetails(awardId) {
            const card = document.querySelector(`[data-award-id="${awardId}"]`);
            if (!card) return;

            const collapsedView = card.querySelector('.collapsed-view');
            const expandedView = card.querySelector('.expanded-view');
            const icon = document.getElementById(`icon-${awardId}`);

            if (!collapsedView || !expandedView || !icon) return;

            const isExpanded = !expandedView.classList.contains('hidden');

            if (isExpanded) {
                // Collapse
                expandedView.classList.add('hidden');
                collapsedView.classList.remove('hidden');
                icon.style.transform = 'rotate(0deg)';
                icon.textContent = 'expand_more';
            } else {
                // Expand
                collapsedView.classList.add('hidden');
                expandedView.classList.remove('hidden');
                icon.style.transform = 'rotate(180deg)';
                icon.textContent = 'expand_less';
            }
        }

        // File viewer functions
        function viewFile(filePath, fileName, fileType) {
            const modal = document.getElementById('fileViewerModal');
            const title = document.getElementById('fileViewerTitle');
            const image = document.getElementById('fileViewerImage');
            const iframe = document.getElementById('fileViewerIframe');
            
            title.textContent = fileName || 'View File';
            
            if (fileType === 'image') {
                image.src = filePath;
                image.classList.remove('hidden');
                iframe.classList.add('hidden');
            } else {
                // For documents, try to display in iframe or download
                if (filePath.endsWith('.pdf') || filePath.endsWith('.docx') || filePath.endsWith('.doc')) {
                    iframe.src = filePath;
                    iframe.classList.remove('hidden');
                    image.classList.add('hidden');
                } else {
                    // For other file types, just download
                    window.open(filePath, '_blank');
                    return;
                }
            }
            
            modal.classList.remove('hidden');
        }

        function closeFileViewer() {
            const modal = document.getElementById('fileViewerModal');
            const image = document.getElementById('fileViewerImage');
            const iframe = document.getElementById('fileViewerIframe');
            
            modal.classList.add('hidden');
            image.src = '';
            iframe.src = '';
        }

        // Close file viewer on Escape key or outside click
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const fileModal = document.getElementById('fileViewerModal');
                if (!fileModal.classList.contains('hidden')) {
                    closeFileViewer();
                }
            }
        });

        document.getElementById('fileViewerModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeFileViewer();
            }
        });

        // Setup delete modal event listeners
        // Dark mode toggle functionality
        const themeToggle = document.getElementById('theme-toggle');
        if (themeToggle) {
            // Function to toggle dark mode
            const toggleDarkMode = (enable) => {
                if (enable) {
                    document.documentElement.classList.add('dark');
                    localStorage.setItem('theme', 'dark');
                } else {
                    document.documentElement.classList.remove('dark');
                    localStorage.setItem('theme', 'light');
                }
            };

            // Check for saved theme in localStorage or system preference
            if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                toggleDarkMode(true);
            } else {
                toggleDarkMode(false);
            }

            // Event listener for theme toggle button
            themeToggle.addEventListener('click', () => {
                const isCurrentlyDark = document.documentElement.classList.contains('dark');
                toggleDarkMode(!isCurrentlyDark);
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('deleteCancelBtn')?.addEventListener('click', hideDeleteModal);
            document.getElementById('deleteConfirmBtn')?.addEventListener('click', confirmDelete);
            
            // Close modal when clicking outside
            document.getElementById('deleteModal')?.addEventListener('click', function(e) {
                if (e.target === this) {
                    hideDeleteModal();
                }
            });

            // Close modal on Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && !document.getElementById('deleteModal').classList.contains('hidden')) {
                    hideDeleteModal();
                }
            });
        });

        // Filter and search
        function applyFilters() {
            const searchTerm = document.getElementById('search-input').value.toLowerCase();
            const statusFilter = document.getElementById('status-filter').value;
            const eligibilityFilter = document.getElementById('eligibility-filter').value;
            const sortBy = document.getElementById('sort-select').value;

            // Filter
            filteredApplicants = allApplicants.filter(app => {
                const matchesSearch = !searchTerm ||
                    (app.username && app.username.toLowerCase().includes(searchTerm)) ||
                    (app.email && app.email.toLowerCase().includes(searchTerm)) ||
                    (app.submission_title && app.submission_title.toLowerCase().includes(searchTerm));

                const matchesStatus = !statusFilter ||
                    (app.award_status && app.award_status.toLowerCase() === statusFilter);

                // Eligibility filter
                const similarity = (app.similarity_score || app.match_percentage / 100) * 100;
                let matchesEligibility = !eligibilityFilter;
                if (eligibilityFilter) {
                    if (eligibilityFilter === 'eligible') {
                        matchesEligibility = similarity >= 90;
                    } else if (eligibilityFilter === 'almost') {
                        matchesEligibility = similarity >= 70 && similarity < 90;
                    } else if (eligibilityFilter === 'not-eligible') {
                        matchesEligibility = similarity < 70;
                    }
                }

                return matchesSearch && matchesStatus && matchesEligibility;
            });

            // Sort
            filteredApplicants.sort((a, b) => {
                const simA = (a.similarity_score || a.match_percentage / 100) * 100;
                const simB = (b.similarity_score || b.match_percentage / 100) * 100;
                const dateA = new Date(a.created_at);
                const dateB = new Date(b.created_at);

                switch(sortBy) {
                    case 'similarity-desc': return simB - simA;
                    case 'similarity-asc': return simA - simB;
                    case 'date-desc': return dateB - dateA;
                    case 'date-asc': return dateA - dateB;
                    default: return simB - simA;
                }
            });

            renderApplicants();
        }

        // Show toast notification
        function showToast(message, type = 'info') {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toast-message');

            const colors = {
                success: 'bg-green-600 text-white',
                error: 'bg-red-600 text-white',
                info: 'bg-blue-600 text-white'
            };

            toast.className = `fixed bottom-6 right-6 px-6 py-3 rounded-lg shadow-lg transition-all duration-300 z-50 ${colors[type]}`;
            toastMessage.textContent = message;

            setTimeout(() => {
                toast.style.transform = 'translateY(0)';
                toast.style.opacity = '1';
            }, 10);

            setTimeout(() => {
                toast.style.transform = 'translateY(20px)';
                toast.style.opacity = '0';
            }, 3000);
        }

        // Escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }

        // Event listeners
        document.getElementById('search-input').addEventListener('input', applyFilters);
        document.getElementById('status-filter').addEventListener('change', applyFilters);
        document.getElementById('eligibility-filter').addEventListener('change', applyFilters);
        document.getElementById('sort-select').addEventListener('change', applyFilters);

        // Load data on page load
        loadApplicants();
    </script>
</body>
</html>
