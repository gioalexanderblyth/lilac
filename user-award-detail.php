<?php
session_start();
require_once 'api/config.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$awardId = $_GET['id'] ?? null;
if (!$awardId) {
    header('Location: user-awards.php#award-list');
    exit();
}

$userId = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Award Submission Detail | LILAC</title>

    <!-- Tailwind CSS (compiled via npm build:css) -->
    <link rel="stylesheet" href="assets/css/tailwind.css">

    <!-- Material Symbols -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>

    <script>
        // Apply theme immediately to prevent flash
        (function() {
            const savedTheme = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const shouldBeDark = savedTheme === 'dark' || (!savedTheme && prefersDark);
            if (shouldBeDark) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100">

    <!-- Header -->
    <header class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 sticky top-0 z-50">
        <div class="container mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <a href="user-awards.php#award-list" class="flex items-center gap-2 text-gray-600 dark:text-gray-400 hover:text-primary transition-colors">
                        <span class="material-symbols-outlined">arrow_back</span>
                        <span>Back to Award List</span>
                    </a>
                </div>
                <div class="flex items-center gap-4">
                    <div class="relative z-[9999]">
                        <button id="notificationBtn" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors relative">
                            <span class="material-symbols-outlined text-gray-600 dark:text-gray-400">notifications</span>
                            <span id="notificationBadge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center hidden">0</span>
                        </button>
                        <div id="notificationDropdown" class="absolute right-0 top-full mt-2 w-96 bg-white dark:bg-background-dark rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 z-[9999] hidden flex flex-col max-h-96">
                            <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex-shrink-0">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Notifications</h3>
                                    <a href="#" id="markAllReadBtn" class="text-sm text-primary hover:text-primary/80 dark:text-primary-400 dark:hover:text-primary-300">Mark all read</a>
                                </div>
                            </div>
                            
                            <!-- Scrollable list area -->
                            <div id="notificationList" class="flex-1 min-h-0 overflow-y-auto divide-y divide-gray-200 dark:divide-gray-700">
                                <!-- Notifications will be populated here -->
                            </div>
                            <div id="noNotifications" class="p-6 text-center text-gray-500 dark:text-gray-400 flex-shrink-0">
                                <span class="material-symbols-outlined text-4xl mb-2 block">notifications_off</span>
                                <p>No notifications</p>
                            </div>
                            
                            <div class="p-4 border-t border-gray-200 dark:border-gray-700 flex-shrink-0">
                                <button type="button" id="viewAllNotifications" class="w-full text-center text-sm text-primary hover:text-primary/80 dark:text-primary-400 dark:hover:text-primary-300">View all notifications</button>
                            </div>
                        </div>
                    </div>
                    <span class="text-sm text-gray-600 dark:text-gray-400">
                        <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </span>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-6 py-8">

        <!-- Loading State -->
        <div id="loading" class="flex items-center justify-center py-12">
            <div class="flex items-center gap-3 text-gray-500 dark:text-gray-400">
                <svg class="animate-spin h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Loading submission details...</span>
            </div>
        </div>

        <!-- Content -->
        <div id="content" class="hidden space-y-6">

            <!-- Header Card -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex-1">
                        <h1 id="award-name" class="text-3xl font-bold text-gray-900 dark:text-white mb-2"></h1>
                        <p id="submission-title" class="text-lg text-gray-600 dark:text-gray-400"></p>
                    </div>
                    <div id="status-badge" class="px-4 py-2 rounded-full text-sm font-medium"></div>
                </div>

                <!-- User Information -->
                <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                    <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Submitted By</h3>
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center">
                            <span class="material-symbols-outlined text-primary text-2xl">person</span>
                        </div>
                        <div>
                            <p id="user-name" class="font-semibold text-gray-900 dark:text-white"></p>
                            <p id="user-email" class="text-sm text-gray-600 dark:text-gray-400"></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Overview Card -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Submission Status</h2>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Criteria Met -->
                    <div class="text-center p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                        <div class="flex items-center justify-center gap-2 mb-2">
                            <span class="material-symbols-outlined text-blue-600 dark:text-blue-400">checklist</span>
                            <p class="text-sm font-semibold text-blue-600 dark:text-blue-400 uppercase">Criteria Met</p>
                        </div>
                        <p class="text-3xl font-bold text-blue-700 dark:text-blue-300" id="criteria-met-display">0/0</p>
                    </div>

                    <!-- Eligibility -->
                    <div class="text-center p-4 rounded-lg border" id="eligibility-card">
                        <div class="flex items-center justify-center gap-2 mb-2">
                            <span class="material-symbols-outlined">verified</span>
                            <p class="text-sm font-semibold uppercase">Eligibility</p>
                        </div>
                        <p class="text-2xl font-bold" id="eligibility-status">-</p>
                    </div>

                    <!-- Status -->
                    <div class="text-center p-4 rounded-lg border" id="processing-status-card">
                        <div class="flex items-center justify-center gap-2 mb-2">
                            <span class="material-symbols-outlined">timeline</span>
                            <p class="text-sm font-semibold uppercase">Status</p>
                        </div>
                        <p class="text-2xl font-bold" id="processing-status">-</p>
                    </div>

                    <!-- Match Score -->
                    <div class="text-center p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg border border-purple-200 dark:border-purple-800">
                        <div class="flex items-center justify-center gap-2 mb-2">
                            <span class="material-symbols-outlined text-purple-600 dark:text-purple-400">analytics</span>
                            <p class="text-sm font-semibold text-purple-600 dark:text-purple-400 uppercase">Match Score</p>
                        </div>
                        <p class="text-3xl font-bold text-purple-700 dark:text-purple-300" id="match-percentage">0%</p>
                    </div>
                </div>
            </div>

            <!-- CHED Criteria Guidance -->
            <div id="guidance-card" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6 hidden">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2 text-red-600 dark:text-red-300 font-semibold">
                            <span class="material-symbols-outlined text-base">campaign</span>
                            <span>Action Needed to Reach Eligibility</span>
                        </div>
                        <p id="guidance-summary" class="text-xs text-gray-600 dark:text-gray-400 mt-1"></p>
                    </div>
                    <button type="button" id="guidance-toggle"
                        class="flex items-center gap-1 text-xs font-semibold text-red-600 dark:text-red-200 underline decoration-dotted">
                        <span>Show details</span>
                        <span id="guidance-toggle-icon" class="material-symbols-outlined text-sm transition-transform">chevron_right</span>
                    </button>
                </div>

                <div id="guidance-body" class="space-y-4 hidden mt-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <h3 class="text-xs font-semibold text-green-700 dark:text-green-400 uppercase mb-2 tracking-wide">Matched Criteria</h3>
                            <div id="matched-criteria-chips" class="flex flex-wrap gap-2 text-sm"></div>
                        </div>
                        <div>
                            <h3 class="text-xs font-semibold text-red-600 dark:text-red-400 uppercase mb-2 tracking-wide">Unmatched Criteria</h3>
                            <div id="unmatched-criteria-chips" class="flex flex-wrap gap-2 text-sm"></div>
                        </div>
                    </div>

                    <div id="guidance-content"></div>
                </div>
            </div>

            <!-- Progress Visualization -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Progress Overview</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Criteria Breakdown -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Criteria Met</span>
                            <span class="text-sm font-bold" id="criteria-met">0</span>
                        </div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Total Criteria</span>
                            <span class="text-sm font-bold" id="criteria-total">0</span>
                        </div>
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Missing Criteria</span>
                            <span class="text-sm font-bold text-red-600 dark:text-red-400" id="criteria-missing">0</span>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Overall Progress</span>
                            <span id="progress-percentage" class="text-lg font-bold"></span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-4">
                            <div id="progress-bar" class="h-4 rounded-full transition-all"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Keywords Analysis -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Keywords Analysis</h2>

                <div class="mb-6" id="matched-keywords-section">
                    <h3 class="text-lg font-semibold text-green-700 dark:text-green-400 mb-3">✅ Matched Keywords</h3>
                    <div id="matched-keywords" class="flex flex-wrap gap-2"></div>
                </div>

                <div id="missing-keywords-section">
                    <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-400 mb-3">⚪ Missing Keywords</h3>
                    <div id="missing-keywords" class="flex flex-wrap gap-2"></div>
                </div>
            </div>

            <!-- Description -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Description</h2>
                <p id="description" class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap"></p>
            </div>

            <!-- Document -->
            <div id="document-section" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Uploaded Document</h2>
                <div id="document-info" class="flex items-center gap-4"></div>
            </div>

            <!-- Metadata -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Submission Info</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-600 dark:text-gray-400">Submitted on:</span>
                        <span id="submit-date" class="font-medium text-gray-900 dark:text-white ml-2"></span>
                    </div>
                    <div>
                        <span class="text-gray-600 dark:text-gray-400">Status:</span>
                        <span id="status-text" class="font-medium text-gray-900 dark:text-white ml-2"></span>
                    </div>
                </div>
            </div>

        </div>

    </main>

    <script>
        const awardId = <?php echo json_encode($awardId); ?>;
        const userId = <?php echo json_encode($userId); ?>;
        const CHED_GUIDANCE_URL = 'https://sites.google.com/ched.gov.ph/icons-awards-2024/home?authuser=0';
        const ELIGIBILITY_GUIDANCE_THRESHOLD = 70;
        const CHED_CRITERIA_GUIDANCE = {
            equity: 'Show how your program creates inclusive, intercultural spaces and removes participation barriers.',
            poverty: 'Highlight measurable SDG-aligned efforts addressing poverty or inequality in the last 1-2 years.',
            innovation: 'Describe bold, scalable approaches to internationalization or global engagement.',
            community: 'Provide evidence of student/community partnerships that convert awareness into action.',
            international: 'Attach MOUs or stories of collaboration with overseas institutions or partners.',
            sustainability: 'Explain the long-term, just and sustainable outcomes produced by the initiative.',
            citizenship: 'Document how learners are empowered as global citizens ready to engage worldwide.',
            global: 'Connect your work to cross-border impact, intercultural understanding, or SDG commitments.',
            access: 'Show policies/support that keep internationalization accessible to diverse learners.',
            partnerships: 'List key local/international partners and the shared impact achieved.',
            default: 'Add documentation or narrative proof that clearly links your submission to this CHED ICONS criterion.'
        };

        function normalizeCriteriaList(criteria) {
            if (!criteria) return [];
            if (Array.isArray(criteria)) return criteria.filter(Boolean);
            if (typeof criteria === 'string') {
                const trimmed = criteria.trim();
                if (!trimmed) return [];
                try {
                    const parsed = JSON.parse(trimmed);
                    if (Array.isArray(parsed)) return parsed.filter(Boolean);
                } catch (error) {
                    // not json
                }
                return trimmed.split(',').map(item => item.trim()).filter(Boolean);
            }
            return [criteria].filter(Boolean);
        }

        function buildGuidanceTips(unmatchedCriteria = [], similarityScore = 0, awardLabel = '') {
            const normalized = normalizeCriteriaList(unmatchedCriteria);
            if (normalized.length === 0) return '';
            if (similarityScore >= ELIGIBILITY_GUIDANCE_THRESHOLD) return '';

            const seen = new Set();
            const renderedItems = normalized.reduce((acc, label) => {
                const key = (label || '').toString().trim().toLowerCase();
                if (!key || seen.has(key)) return acc;
                seen.add(key);
                const description = CHED_CRITERIA_GUIDANCE[key] || CHED_CRITERIA_GUIDANCE.default;
                acc.push(`
                    <li class="flex items-start gap-2">
                        <span class="material-symbols-outlined text-sm text-red-500 mt-0.5">priority_high</span>
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">${label}</p>
                            <p class="text-xs text-gray-600 dark:text-gray-400">${description}</p>
                        </div>
                    </li>
                `);
                return acc;
            }, []);

            if (!renderedItems.length) return '';

            return `
                <ul class="space-y-3">${renderedItems.join('')}</ul>
                <a href="${CHED_GUIDANCE_URL}" target="_blank" rel="noopener"
                   class="mt-3 inline-flex items-center gap-1 text-xs font-medium text-red-700 dark:text-red-200 underline">
                    Review CHED ICONS 2024 criteria
                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                </a>
            `;
        }

        async function loadSubmissionDetail() {
            try {
                const response = await fetch(`api/user-award-submissions.php`);
                if (!response.ok) throw new Error('Failed to load submission');

                const result = await response.json();
                if (!result.success) throw new Error(result.error || 'Failed to load');

                // Find the specific submission
                const submission = result.submissions.find(s => s.award_id == awardId);
                if (!submission) {
                    throw new Error('Submission not found or you do not have access');
                }

                // Hide loading, show content
                document.getElementById('loading').classList.add('hidden');
                document.getElementById('content').classList.remove('hidden');

                // Populate data
                document.getElementById('award-name').textContent = submission.award_name || 'Unknown Award';
                document.getElementById('submission-title').textContent = submission.submission_title || 'Untitled';
                document.getElementById('description').textContent = submission.description || 'No description provided';
                document.getElementById('submit-date').textContent = new Date(submission.created_at).toLocaleDateString();

                // User information
                const userName = submission.full_name
                    ? submission.full_name
                    : submission.username || 'Unknown User';
                document.getElementById('user-name').textContent = userName;
                document.getElementById('user-email').textContent = submission.email || 'No email';

                // Match percentage
                const matchPct = parseFloat(submission.match_percentage || 0);
                document.getElementById('match-percentage').textContent = matchPct.toFixed(1) + '%';
                document.getElementById('progress-percentage').textContent = matchPct.toFixed(1) + '%';

                // Progress bar
                const progressBar = document.getElementById('progress-bar');
                progressBar.style.width = matchPct + '%';
                if (matchPct >= 90) {
                    progressBar.className = 'h-4 rounded-full transition-all bg-green-500';
                } else if (matchPct >= 70) {
                    progressBar.className = 'h-4 rounded-full transition-all bg-yellow-500';
                } else {
                    progressBar.className = 'h-4 rounded-full transition-all bg-red-500';
                }

                // Criteria
                const criteriaMet = parseInt(submission.criteria_met || 0);
                const criteriaTotal = parseInt(submission.criteria_total || 0);
                const criteriaMissing = criteriaTotal - criteriaMet;

                document.getElementById('criteria-met').textContent = criteriaMet;
                document.getElementById('criteria-total').textContent = criteriaTotal;
                document.getElementById('criteria-missing').textContent = criteriaMissing;
                document.getElementById('criteria-met-display').textContent = `${criteriaMet}/${criteriaTotal}`;

                // Eligibility Status
                const eligibilityCard = document.getElementById('eligibility-card');
                const eligibilityStatus = document.getElementById('eligibility-status');
                if (matchPct >= 90) {
                    eligibilityCard.className = 'text-center p-4 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800';
                    eligibilityCard.querySelector('.material-symbols-outlined').className = 'material-symbols-outlined text-green-600 dark:text-green-400';
                    eligibilityCard.querySelector('.text-sm').className = 'text-sm font-semibold text-green-600 dark:text-green-400 uppercase';
                    eligibilityStatus.className = 'text-2xl font-bold text-green-700 dark:text-green-300';
                    eligibilityStatus.textContent = 'Eligible';
                } else if (matchPct >= 70) {
                    eligibilityCard.className = 'text-center p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800';
                    eligibilityCard.querySelector('.material-symbols-outlined').className = 'material-symbols-outlined text-yellow-600 dark:text-yellow-400';
                    eligibilityCard.querySelector('.text-sm').className = 'text-sm font-semibold text-yellow-600 dark:text-yellow-400 uppercase';
                    eligibilityStatus.className = 'text-2xl font-bold text-yellow-700 dark:text-yellow-300';
                    eligibilityStatus.textContent = 'Almost';
                } else {
                    eligibilityCard.className = 'text-center p-4 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800';
                    eligibilityCard.querySelector('.material-symbols-outlined').className = 'material-symbols-outlined text-red-600 dark:text-red-400';
                    eligibilityCard.querySelector('.text-sm').className = 'text-sm font-semibold text-red-600 dark:text-red-400 uppercase';
                    eligibilityStatus.className = 'text-2xl font-bold text-red-700 dark:text-red-300';
                    eligibilityStatus.textContent = 'Not Eligible';
                }

                // Processing Status
                const processingCard = document.getElementById('processing-status-card');
                const processingStatus = document.getElementById('processing-status');
                if (submission.status === 'approved') {
                    processingCard.className = 'text-center p-4 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800';
                    processingCard.querySelector('.material-symbols-outlined').className = 'material-symbols-outlined text-green-600 dark:text-green-400';
                    processingCard.querySelector('.text-sm').className = 'text-sm font-semibold text-green-600 dark:text-green-400 uppercase';
                    processingStatus.className = 'text-2xl font-bold text-green-700 dark:text-green-300';
                    processingStatus.textContent = 'Recognized';
                } else if (submission.status === 'analyzed') {
                    processingCard.className = 'text-center p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800';
                    processingCard.querySelector('.material-symbols-outlined').className = 'material-symbols-outlined text-blue-600 dark:text-blue-400';
                    processingCard.querySelector('.text-sm').className = 'text-sm font-semibold text-blue-600 dark:text-blue-400 uppercase';
                    processingStatus.className = 'text-2xl font-bold text-blue-700 dark:text-blue-300';
                    processingStatus.textContent = 'Processed';
                } else {
                    processingCard.className = 'text-center p-4 bg-gray-50 dark:bg-gray-900/20 rounded-lg border border-gray-200 dark:border-gray-800';
                    processingCard.querySelector('.material-symbols-outlined').className = 'material-symbols-outlined text-gray-600 dark:text-gray-400';
                    processingCard.querySelector('.text-sm').className = 'text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase';
                    processingStatus.className = 'text-2xl font-bold text-gray-700 dark:text-gray-300';
                    processingStatus.textContent = 'Pending';
                }

                // Status badge
                const statusBadge = document.getElementById('status-badge');
                const statusText = document.getElementById('status-text');
                if (submission.status === 'approved') {
                    statusBadge.className = 'px-4 py-2 rounded-full text-sm font-medium bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400';
                    statusBadge.textContent = '✅ Recognized';
                    statusText.textContent = 'Recognized by Admin';
                } else if (submission.status === 'analyzed' && matchPct >= 90) {
                    statusBadge.className = 'px-4 py-2 rounded-full text-sm font-medium bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400';
                    statusBadge.textContent = '✅ Eligible';
                    statusText.textContent = 'Eligible (≥90% match)';
                } else if (submission.status === 'analyzed' && matchPct >= 70) {
                    statusBadge.className = 'px-4 py-2 rounded-full text-sm font-medium bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400';
                    statusBadge.textContent = '🟡 Almost Eligible';
                    statusText.textContent = 'Almost Eligible (70-89% match)';
                } else if (submission.status === 'analyzed') {
                    statusBadge.className = 'px-4 py-2 rounded-full text-sm font-medium bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400';
                    statusBadge.textContent = '❌ Not Eligible';
                    statusText.textContent = 'Not Eligible (<70% match)';
                } else {
                    statusBadge.className = 'px-4 py-2 rounded-full text-sm font-medium bg-gray-100 dark:bg-gray-900/30 text-gray-700 dark:text-gray-400';
                    statusBadge.textContent = '⏳ Pending';
                    statusText.textContent = 'Pending Analysis';
                }

                // Keywords
                const matchedKeywords = submission.matched_keywords_array || [];
                const missingKeywords = submission.missing_keywords_array || [];

                const matchedContainer = document.getElementById('matched-keywords');
                if (matchedKeywords.length > 0) {
                    matchedContainer.innerHTML = matchedKeywords.map(kw => `
                        <span class="px-3 py-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 rounded-lg text-sm font-medium">
                            ✓ ${kw}
                        </span>
                    `).join('');
                } else {
                    matchedContainer.innerHTML = '<p class="text-gray-500 dark:text-gray-400">No matched keywords</p>';
                }

                const missingContainer = document.getElementById('missing-keywords');
                if (missingKeywords.length > 0) {
                    missingContainer.innerHTML = missingKeywords.map(kw => `
                        <span class="px-3 py-1 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 rounded-lg text-sm">
                            ${kw}
                        </span>
                    `).join('');
                } else {
                    missingContainer.innerHTML = '<p class="text-gray-500 dark:text-gray-400">No missing keywords - All criteria met!</p>';
                }

                // Criteria & guidance
                const matchedCriteria = normalizeCriteriaList(submission.matched_criteria || submission.matched_keywords_array || []);
                const unmatchedCriteria = normalizeCriteriaList(submission.unmatched_criteria || submission.missing_keywords_array || []);
                const matchedChips = document.getElementById('matched-criteria-chips');
                const unmatchedChips = document.getElementById('unmatched-criteria-chips');
                const guidanceCard = document.getElementById('guidance-card');
                const guidanceContent = document.getElementById('guidance-content');
                const guidanceSummary = document.getElementById('guidance-summary');
                const guidanceToggle = document.getElementById('guidance-toggle');
                const guidanceToggleIcon = document.getElementById('guidance-toggle-icon');
                const guidanceBody = document.getElementById('guidance-body');

                matchedChips.innerHTML = matchedCriteria.length
                    ? matchedCriteria.map(c => `
                        <span class="px-3 py-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 rounded-lg">
                            ✓ ${c}
                        </span>
                    `).join('')
                    : '<p class="text-gray-500 dark:text-gray-400 text-sm">No matched criteria yet.</p>';

                unmatchedChips.innerHTML = unmatchedCriteria.length
                    ? unmatchedCriteria.map(c => `
                        <span class="px-3 py-1 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 rounded-lg">
                            ✗ ${c}
                        </span>
                    `).join('')
                    : '<p class="text-gray-500 dark:text-gray-400 text-sm">No unmatched criteria remaining!</p>';

                const guidanceHtml = buildGuidanceTips(unmatchedCriteria, matchPct, submission.award_name || submission.predicted_category || 'Award');
                if (guidanceHtml) {
                    guidanceCard.classList.remove('hidden');
                    guidanceContent.innerHTML = guidanceHtml;
                    guidanceSummary.textContent = unmatchedCriteria.length
                        ? unmatchedCriteria.slice(0, 3).join(' • ') + (unmatchedCriteria.length > 3 ? '…' : '')
                        : 'All CHED ICONS criteria satisfied';
                    let detailsVisible = false;
                    const toggleDetails = () => {
                        detailsVisible = !detailsVisible;
                        guidanceBody.classList.toggle('hidden', !detailsVisible);
                        guidanceToggle.querySelector('span').textContent = detailsVisible ? 'Hide details' : 'Show details';
                        guidanceToggleIcon.classList.toggle('rotate-90', detailsVisible);
                    };
                    guidanceToggle.onclick = toggleDetails;
                } else {
                    guidanceCard.classList.add('hidden');
                    guidanceContent.innerHTML = '';
                    guidanceSummary.textContent = '';
                }

                // Document
                if (submission.file_name && submission.file_path) {
                    document.getElementById('document-info').innerHTML = `
                        <span class="material-symbols-outlined text-4xl text-primary">description</span>
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">${submission.file_name}</p>
                            <a href="${submission.file_path}" target="_blank"
                               class="text-sm text-primary hover:underline">
                                View Document →
                            </a>
                        </div>
                    `;
                } else {
                    document.getElementById('document-section').remove();
                }

            } catch (error) {
                console.error('Error:', error);
                document.getElementById('loading').innerHTML = `
                    <div class="text-center text-red-600 dark:text-red-400">
                        <p class="text-lg font-semibold">Error loading submission</p>
                        <p class="text-sm">${error.message}</p>
                        <a href="user-awards.php#award-list" class="text-primary hover:underline mt-4 inline-block">
                            Return to Award List
                        </a>
                    </div>
                `;
            }
        }

        // Load on page load
        loadSubmissionDetail();
        
        // Notification handling
        (function initNotifications() {
            const notificationBtn = document.getElementById('notificationBtn');
            const notificationDropdown = document.getElementById('notificationDropdown');
            const notificationBadge = document.getElementById('notificationBadge');
            const notificationList = document.getElementById('notificationList');
            const noNotifications = document.getElementById('noNotifications');
            const markAllReadBtn = document.getElementById('markAllReadBtn');
            const viewAllNotifications = document.getElementById('viewAllNotifications');
            
            if (!notificationBtn || !notificationDropdown) return;
            
            let notifications = [];
            
            // Toggle dropdown
            notificationBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                notificationDropdown.classList.toggle('hidden');
                if (!notificationDropdown.classList.contains('hidden')) {
                    loadNotifications();
                }
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', (e) => {
                if (!notificationBtn.contains(e.target) && !notificationDropdown.contains(e.target)) {
                    notificationDropdown.classList.add('hidden');
                }
            });
            
            // Mark all notifications as read
            if (markAllReadBtn) {
                markAllReadBtn.addEventListener('click', async function(e) {
                    e.preventDefault();
                    try {
                        const response = await fetch('api/notifications.php?action=mark_all_read', {
                            method: 'POST'
                        });
                        if (response.ok) {
                            await loadNotifications();
                            await updateNotificationBadge();
                        }
                    } catch (error) {
                        console.error('Error marking all notifications as read:', error);
                    }
                });
            }
            
            // Handle notification item clicks
            if (notificationList) {
                notificationList.addEventListener('click', function(event) {
                    // Don't handle clicks on confirmation buttons
                    if (event.target.closest('button') || event.target.closest('[onclick*="confirmMouRenewal"]')) {
                        return;
                    }
                    
                    const target = event.target.closest('[data-notification-id]');
                    if (!target) return;
                    
                    event.preventDefault();
                    event.stopPropagation();
                    
                    const notificationId = Number(target.dataset.notificationId);
                    if (!notificationId) return;
                    
                    // Handle MOU notifications specially
                    const relatedType = target.dataset.relatedType;
                    const relatedId = target.dataset.relatedId;
                    
                    if (relatedType === 'mou_moa' && relatedId) {
                        // For MOU notifications, navigate to the MOU page
                        markNotificationAsRead(notificationId).then(() => {
                            loadNotifications();
                            updateNotificationBadge();
                            if (notificationDropdown) {
                                notificationDropdown.classList.add('hidden');
                            }
                            window.location.href = `mou-moa.php?entry=${encodeURIComponent(relatedId)}`;
                        });
                    } else {
                        // For other notifications, navigate to the URL if available
                        const targetUrl = decodeUrlAttribute(target.dataset.url);
                        markNotificationAsRead(notificationId).then(() => {
                            loadNotifications();
                            updateNotificationBadge();
                            if (targetUrl) {
                                if (notificationDropdown) {
                                    notificationDropdown.classList.add('hidden');
                                }
                                window.location.href = targetUrl;
                            }
                        });
                    }
                });
            }
            
            function getTimeAgo(dateString) {
                const date = new Date(dateString);
                const now = new Date();
                const diffInSeconds = Math.floor((now - date) / 1000);
                if (diffInSeconds < 60) return 'Just now';
                if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
                if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
                if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)}d ago`;
                return date.toLocaleDateString();
            }
            
            function getNotificationUrl(notif) {
                if (!notif || !notif.related_type || !notif.related_id) return '';
                const encodedId = encodeURIComponent(notif.related_id);
                if (notif.related_type === 'mou_moa') return `mou-moa.php?entry=${encodedId}`;
                if (notif.related_type === 'event') return `events-activities.php?event=${encodedId}`;
                if (notif.related_type === 'schedule') return `scheduler.php`;
                return '';
            }
            
            function decodeUrlAttribute(value) {
                if (!value) return '';
                try { return decodeURIComponent(value); } catch (e) { return value; }
            }
            
            async function markNotificationAsRead(id) {
                try {
                    const response = await fetch(`api/notifications.php?id=${id}`, { method: 'PUT' });
                    const data = await response.json();
                    return data.success;
                } catch (error) {
                    console.error('Error marking notification as read:', error);
                    return false;
                }
            }
            
            function createAllNotificationsModal() {
                if (document.getElementById('allNotificationsModal')) return;
                const modalHTML = `
                    <div id="allNotificationsModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 dark:bg-black/70 backdrop-blur-sm hidden">
                        <div class="w-full max-w-4xl bg-card-light dark:bg-card-dark rounded-xl shadow-2xl m-4 flex flex-col max-h-[90vh] border border-border-light dark:border-border-dark">
                            <!-- Modal Header -->
                            <div class="p-6 border-b border-border-light dark:border-border-dark flex-shrink-0">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="text-xl font-semibold text-text-light dark:text-text-dark">All Notifications</h3>
                                        <p class="text-sm text-text-muted-light dark:text-text-muted-dark mt-1">Manage your MOU/MOA notifications</p>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <button id="markAllReadModalBtn" class="px-4 py-2 text-sm font-medium text-primary bg-primary/10 dark:bg-primary/20 rounded-lg hover:bg-primary/20 dark:hover:bg-primary/30">
                                            Mark All Read
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Filter Tabs -->
                            <div class="px-6 py-4 border-b border-border-light dark:border-border-dark flex-shrink-0">
                                <div class="flex space-x-1 bg-gray-100 dark:bg-gray-800 rounded-lg p-1">
                                    <button class="notification-tab px-4 py-2 text-sm font-medium rounded-md transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm" data-filter="all">
                                        All
                                    </button>
                                    <button class="notification-tab px-4 py-2 text-sm font-medium rounded-md transition-colors text-gray-600 dark:text-gray-400" data-filter="critical">
                                        Critical
                                    </button>
                                    <button class="notification-tab px-4 py-2 text-sm font-medium rounded-md transition-colors text-gray-600 dark:text-gray-400" data-filter="unread">
                                        Unread
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Notifications List -->
                            <div id="allNotificationsList" class="flex-1 overflow-y-auto p-6">
                                <div class="text-center py-12">
                                    <span class="material-symbols-outlined text-6xl text-text-muted-light dark:text-text-muted-dark mb-4 block">notifications_off</span>
                                    <p class="text-text-muted-light dark:text-text-muted-dark text-lg">Loading notifications...</p>
                                </div>
                            </div>
                            
                            <!-- Modal Footer -->
                            <div class="p-6 border-t border-border-light dark:border-border-dark flex-shrink-0">
                                <div class="flex items-center justify-between">
                                    <div class="text-sm text-text-muted-light dark:text-text-muted-dark">
                                        <span id="notificationsCount">0</span> notifications
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <button id="clearOldNotifications" class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                                            Clear All
                                        </button>
                                        <button id="closeAllNotificationsModalBtn2" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-background-dark/50 dark:text-gray-300 dark:border-gray-700 dark:hover:bg-gray-800">
                                            Close
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                document.body.insertAdjacentHTML('beforeend', modalHTML);
                setupAllNotificationsModalEvents();
            }
            
            // Store notifications for filtering
            let allNotificationsData = [];
            
            function setupAllNotificationsModalEvents() {
                const modal = document.getElementById('allNotificationsModal');
                if (!modal) return;
                const closeBtn2 = document.getElementById('closeAllNotificationsModalBtn2');
                const markAllReadBtn = document.getElementById('markAllReadModalBtn');
                const clearOldBtn = document.getElementById('clearOldNotifications');
                const tabs = document.querySelectorAll('.notification-tab');
                
                if (closeBtn2) {
                    closeBtn2.addEventListener('click', function(e) { e.preventDefault(); e.stopPropagation(); closeAllNotificationsModal(); });
                }
                modal.addEventListener('click', function(e) { if (e.target === modal) closeAllNotificationsModal(); });
                const escapeHandler = function(e) { if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) closeAllNotificationsModal(); };
                document.addEventListener('keydown', escapeHandler);
                if (markAllReadBtn) {
                    markAllReadBtn.addEventListener('click', async function(e) {
                        e.preventDefault(); e.stopPropagation();
                        try {
                            const response = await fetch('api/notifications.php', { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'mark_all_read' }) });
                            const data = await response.json();
                            if (data.success) {
                                await loadAllNotificationsIntoModal();
                                if (typeof updateNotificationBadge === 'function') await updateNotificationBadge();
                            }
                        } catch (error) { console.error('Error marking all as read:', error); }
                    });
                }
                if (clearOldBtn) {
                    clearOldBtn.addEventListener('click', async function(e) {
                        e.preventDefault(); e.stopPropagation();
                        try {
                            const response = await fetch('api/notifications.php', { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'mark_all_read' }) });
                            if (response.ok) {
                                await loadAllNotificationsIntoModal();
                                if (typeof updateNotificationBadge === 'function') await updateNotificationBadge();
                            }
                        } catch (error) { console.error('Error clearing notifications:', error); }
                    });
                }
                if (tabs && tabs.length > 0) {
                    tabs.forEach(tab => {
                        tab.addEventListener('click', function() {
                            tabs.forEach(t => {
                                t.classList.remove('bg-white', 'dark:bg-gray-700', 'text-gray-900', 'dark:text-white', 'shadow-sm');
                                t.classList.add('text-gray-600', 'dark:text-gray-400');
                            });
                            this.classList.remove('text-gray-600', 'dark:text-gray-400');
                            this.classList.add('bg-white', 'dark:bg-gray-700', 'text-gray-900', 'dark:text-white', 'shadow-sm');
                            const filter = this.dataset.filter;
                            filterAllNotifications(filter);
                        });
                    });
                }
            }
            
            function filterAllNotifications(filter) {
                const modalList = document.getElementById('allNotificationsList');
                if (!modalList) return;
                let filteredNotifications = allNotificationsData;
                if (filter === 'unread') {
                    filteredNotifications = allNotificationsData.filter(n => !n.is_read);
                } else if (filter === 'critical') {
                    filteredNotifications = allNotificationsData.filter(n => 
                        n.type === 'mou_expired' || 
                        (n.type === 'mou_expiring_soon' && n.mou_days_until_expiry !== undefined && n.mou_days_until_expiry <= 3)
                    );
                }
                renderNotificationsInModal(filteredNotifications);
            }
            
            function renderNotificationsInModal(notifications) {
                const modalList = document.getElementById('allNotificationsList');
                const countElement = document.getElementById('notificationsCount');
                if (!modalList) return;
                if (countElement) countElement.textContent = notifications.length;
                if (notifications.length === 0) {
                    modalList.innerHTML = `<div class="text-center py-12"><span class="material-symbols-outlined text-6xl text-gray-400 dark:text-gray-600 mb-4 block">notifications_off</span><p class="text-gray-500 dark:text-gray-400 text-lg">No notifications</p></div>`;
                    return;
                }
                modalList.innerHTML = notifications.map(notif => {
                    const timeAgo = getTimeAgo(notif.created_at);
                    const icon = getNotificationIcon(notif.type);
                    const bgColor = getNotificationBgColor(notif.type);
                    const targetUrl = getNotificationUrl(notif);
                    const urlAttribute = targetUrl ? ` data-url="${encodeURIComponent(targetUrl)}"` : '';
                    const actionHint = targetUrl ? '<p class="text-xs text-primary mt-2 font-semibold flex items-center gap-1">Open related record<span class="material-symbols-outlined text-sm">arrow_outward</span></p>' : '';
                    return `<div class="p-4 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer transition-colors ${notif.is_read ? 'opacity-60' : ''}" data-notification-id="${notif.id}"${urlAttribute}><div class="flex items-start gap-3"><div class="flex-shrink-0 w-10 h-10 rounded-full ${bgColor} flex items-center justify-center"><span class="material-symbols-outlined text-white text-lg">${icon}</span></div><div class="flex-1 min-w-0"><p class="text-sm font-medium text-gray-900 dark:text-white">${escapeHtml(notif.title)}</p><p class="text-sm text-gray-500 dark:text-gray-400 mt-1">${escapeHtml(notif.message)}</p><p class="text-xs text-gray-400 dark:text-gray-500 mt-1">${timeAgo}</p>${actionHint}</div>${!notif.is_read ? '<div class="flex-shrink-0 w-2 h-2 bg-primary rounded-full mt-2"></div>' : ''}</div></div>`;
                }).join('');
                modalList.querySelectorAll('[data-notification-id]').forEach(item => {
                    item.addEventListener('click', async function(e) {
                        if (e.target.closest('button')) return;
                        const notificationId = Number(item.dataset.notificationId);
                        if (notificationId) {
                            await markNotificationAsRead(notificationId);
                            const targetUrl = decodeUrlAttribute(item.dataset.url);
                            if (targetUrl) { closeAllNotificationsModal(); window.location.href = targetUrl; } else { await loadAllNotificationsIntoModal(); }
                        }
                    });
                });
            }
            }
            
            function showAllNotificationsModal() {
                createAllNotificationsModal();
                const modal = document.getElementById('allNotificationsModal');
                if (modal) { modal.classList.remove('hidden'); loadAllNotificationsIntoModal(); }
            }
            
            function closeAllNotificationsModal() {
                const modal = document.getElementById('allNotificationsModal');
                if (modal) modal.classList.add('hidden');
            }
            
            async function loadAllNotificationsIntoModal() {
                const modalList = document.getElementById('allNotificationsList');
                const countElement = document.getElementById('notificationsCount');
                if (!modalList) return;
                try {
                    const response = await fetch('api/notifications.php');
                    const data = await response.json();
                    if (data.notifications && Array.isArray(data.notifications)) {
                        let allNotifications = data.notifications;
                        allNotifications.sort((a, b) => { const dateA = new Date(a.created_at); const dateB = new Date(b.created_at); return dateB - dateA; });
                        allNotificationsData = allNotifications;
                        renderNotificationsInModal(allNotifications);
                    } else {
                        allNotificationsData = [];
                        if (countElement) countElement.textContent = 0;
                        modalList.innerHTML = `<div class="text-center py-12"><span class="material-symbols-outlined text-6xl text-gray-400 dark:text-gray-600 mb-4 block">notifications_off</span><p class="text-gray-500 dark:text-gray-400 text-lg">No notifications</p></div>`;
                    }
                } catch (error) {
                    console.error('Error loading notifications into modal:', error);
                    modalList.innerHTML = `<div class="text-center py-12"><p class="text-red-500">Error loading notifications. Please try again.</p></div>`;
                }
            }
            
            function renderNotificationsInModal(notifications) {
                const modalList = document.getElementById('allNotificationsList');
                const countElement = document.getElementById('notificationsCount');
                if (!modalList) return;
                if (countElement) countElement.textContent = notifications.length;
                if (notifications.length === 0) {
                            modalList.innerHTML = `<div class="text-center py-12"><span class="material-symbols-outlined text-6xl text-text-muted-light dark:text-text-muted-dark mb-4 block">notifications_off</span><p class="text-text-muted-light dark:text-text-muted-dark text-lg">No notifications</p></div>`;
                        } else {
                            modalList.innerHTML = allNotifications.map(notif => {
                                const timeAgo = getTimeAgo(notif.created_at);
                                const icon = getNotificationIcon(notif.type);
                                const bgColor = getNotificationBgColor(notif.type);
                                const targetUrl = getNotificationUrl(notif);
                                const urlAttribute = targetUrl ? ` data-url="${encodeURIComponent(targetUrl)}"` : '';
                                const actionHint = targetUrl ? '<p class="text-xs text-primary mt-2 font-semibold flex items-center gap-1">Open related record<span class="material-symbols-outlined text-sm">arrow_outward</span></p>' : '';
                                return `<div class="p-4 border-b border-border-light dark:border-border-dark hover:bg-background-light dark:hover:bg-background-dark cursor-pointer transition-colors ${notif.is_read ? 'opacity-60' : ''}" data-notification-id="${notif.id}"${urlAttribute}><div class="flex items-start gap-3"><div class="flex-shrink-0 w-10 h-10 rounded-full ${bgColor} flex items-center justify-center"><span class="material-symbols-outlined text-white text-lg">${icon}</span></div><div class="flex-1 min-w-0"><p class="text-sm font-medium text-text-light dark:text-text-dark">${escapeHtml(notif.title)}</p><p class="text-sm text-text-muted-light dark:text-text-muted-dark mt-1">${escapeHtml(notif.message)}</p><p class="text-xs text-text-muted-light dark:text-text-muted-dark mt-1">${timeAgo}</p>${actionHint}</div>${!notif.is_read ? '<div class="flex-shrink-0 w-2 h-2 bg-primary rounded-full mt-2"></div>' : ''}</div></div>`;
                            }).join('');
                            modalList.querySelectorAll('[data-notification-id]').forEach(item => {
                                item.addEventListener('click', async function(e) {
                                    if (e.target.closest('button')) return;
                                    const notificationId = Number(item.dataset.notificationId);
                                    if (notificationId) {
                                        await markNotificationAsRead(notificationId);
                                        const targetUrl = decodeUrlAttribute(item.dataset.url);
                                        if (targetUrl) { closeAllNotificationsModal(); window.location.href = targetUrl; }
                                    }
                                });
                            });
                        }
                    }
                } catch (error) {
                    console.error('Error loading notifications into modal:', error);
                    modalList.innerHTML = `<div class="text-center py-12"><span class="material-symbols-outlined text-6xl text-red-500 mb-4 block">error</span><p class="text-text-light dark:text-text-dark text-lg">Error loading notifications</p></div>`;
                }
            }
            
            // View all notifications - open modal
            if (viewAllNotifications) {
                viewAllNotifications.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    if (notificationDropdown) notificationDropdown.classList.add('hidden');
                    showAllNotificationsModal();
                }, true);
            }
            
            // Load notifications from API
            async function loadNotifications() {
                try {
                    const enabled = window.areNotificationsEnabled ? await window.areNotificationsEnabled() : true;
                    if (!enabled) {
                        notifications = [];
                        updateNotificationDisplay();
                        if (notificationBadge) {
                            notificationBadge.classList.add('hidden');
                        }
                        return;
                    }

                    const response = await fetch('api/notifications.php');
                    const data = await response.json();
                    if (data.notifications) {
                        notifications = data.notifications;
                        updateNotificationDisplay();
                        updateNotificationBadge();
                    }
                } catch (error) {
                    console.error('Error loading notifications:', error);
                }
            }
            
            // Get unread count
            async function updateNotificationBadge() {
                try {
                    const badge = document.getElementById('notificationBadge');
                    
                    const enabled = window.areNotificationsEnabled ? await window.areNotificationsEnabled() : true;
                    if (!enabled) {
                        if (badge) {
                            badge.classList.add('hidden');
                        }
                        return;
                    }

                    const response = await fetch('api/notifications.php?action=count');
                    if (!response.ok) {
                        console.error('Failed to get notification count:', response.status, response.statusText);
                        return;
                    }
                    const data = await response.json();
                    const count = data.count || 0;
                    
                    if (badge) {
                        if (count > 0) {
                            badge.textContent = count > 99 ? '99+' : count;
                            badge.classList.remove('hidden');
                        } else {
                            badge.classList.add('hidden');
                        }
                    }
                } catch (error) {
                    console.error('Error updating notification badge:', error);
                }
            }
            
            // Update notification display
            function updateNotificationDisplay() {
                if (!notificationList) return;
                
                if (notifications.length === 0) {
                    if (noNotifications) {
                        noNotifications.classList.remove('hidden');
                    }
                    notificationList.innerHTML = '';
                    return;
                }
                
                if (noNotifications) {
                    noNotifications.classList.add('hidden');
                }
                
                notificationList.innerHTML = notifications.slice(0, 5).map(notif => {
                    const timeAgo = formatTimeAgo(notif.created_at);
                    const icon = getNotificationIcon(notif.type);
                    const bgColor = getNotificationBgColor(notif.type);
                    const targetUrl = getNotificationUrl(notif);
                    const urlAttribute = targetUrl ? ` data-url="${encodeURIComponent(targetUrl)}"` : '';
                    const relatedTypeAttr = notif.related_type ? ` data-related-type="${notif.related_type}"` : '';
                    const relatedIdAttr = notif.related_id ? ` data-related-id="${notif.related_id}"` : '';
                    const isMouNotification = notif.related_type === 'mou_moa';
                    const isConfirmed = notif.is_confirmed || false;
                    
                    // Show confirmation buttons for MOU notifications that haven't been confirmed
                    let confirmationButtons = '';
                    if (isMouNotification && !isConfirmed) {
                        confirmationButtons = `
                            <div class="mt-3 flex gap-2">
                                <button onclick="event.stopPropagation(); if(typeof confirmMouRenewal === 'function') confirmMouRenewal(${notif.id}, 'renewed', ${notif.related_id})" 
                                        class="px-3 py-1.5 text-xs font-medium bg-green-500 hover:bg-green-600 text-white rounded-lg transition-colors flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">check_circle</span>
                                    Renewed
                                </button>
                                <button onclick="event.stopPropagation(); if(typeof confirmMouRenewal === 'function') confirmMouRenewal(${notif.id}, 'not_renewed', ${notif.related_id})" 
                                        class="px-3 py-1.5 text-xs font-medium bg-red-500 hover:bg-red-600 text-white rounded-lg transition-colors flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">cancel</span>
                                    Not Renewed
                                </button>
                            </div>
                        `;
                    } else if (isMouNotification && isConfirmed) {
                        const statusText = notif.mou_renewal_status === 'renewed' ? 'Renewed' : 'Not Renewed';
                        const statusColor = notif.mou_renewal_status === 'renewed' ? 'text-green-500' : 'text-red-500';
                        confirmationButtons = `
                            <div class="mt-2">
                                <p class="text-xs ${statusColor} font-medium flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">${notif.mou_renewal_status === 'renewed' ? 'check_circle' : 'cancel'}</span>
                                    Status: ${statusText}
                                </p>
                            </div>
                        `;
                    }
                    
                    const actionHint = targetUrl && !isMouNotification ? '<p class="text-xs text-primary mt-2 font-semibold flex items-center gap-1">Open related record<span class="material-symbols-outlined text-sm">arrow_outward</span></p>' : '';
                    
                    return `
                        <div class="p-4 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/60 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-background-dark ${notif.is_read ? 'opacity-60' : ''}" 
                             role="button" tabindex="0" data-id="${notif.id}" data-notification-id="${notif.id}"${relatedTypeAttr}${relatedIdAttr}${urlAttribute}>
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full ${bgColor} flex items-center justify-center">
                                    <span class="material-symbols-outlined text-white text-lg">${icon}</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">${escapeHtml(notif.title)}</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">${escapeHtml(notif.message)}</p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">${timeAgo}</p>
                                    ${actionHint}
                                    ${confirmationButtons}
                                </div>
                                ${!notif.is_read ? '<div class="flex-shrink-0 w-2 h-2 bg-primary rounded-full mt-2"></div>' : ''}
                            </div>
                        </div>
                    `;
                }).join('');
            }
            
            function formatTimeAgo(datetime) {
                if (!datetime) return 'Just now';
                const timestamp = new Date(datetime).getTime();
                if (!timestamp || isNaN(timestamp)) return 'Just now';
                const diff = Date.now() - timestamp;
                if (diff < 60000) return 'Just now';
                const minutes = Math.floor(diff / 60000);
                if (minutes < 60) return `${minutes}m ago`;
                const hours = Math.floor(minutes / 60);
                if (hours < 24) return `${hours}h ago`;
                const days = Math.floor(hours / 24);
                return `${days}d ago`;
            }
            
            function getNotificationIcon(type) {
                const icons = {
                    'award_submitted': 'emoji_events',
                    'award_analyzed': 'analytics',
                    'mou_expired': 'warning',
                    'event_created': 'event',
                    'default': 'notifications'
                };
                return icons[type] || icons.default;
            }
            
            function getNotificationBgColor(type) {
                const colors = {
                    'award_submitted': 'bg-purple-500',
                    'award_analyzed': 'bg-blue-500',
                    'mou_expired': 'bg-red-500',
                    'event_created': 'bg-green-500',
                    'default': 'bg-gray-500'
                };
                return colors[type] || colors.default;
            }
            
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
            
            // Confirm MOU renewal status
            window.confirmMouRenewal = async function(notificationId, renewalStatus, entryId) {
                // For "renewed": navigate to MOU/MOA page for renewal
                if (renewalStatus === 'renewed') {
                    if (!entryId) {
                        alert('Error: missing MOU/MOA entry id for renewal.');
                        return;
                    }
                    window.location.href = `mou-moa.php?entry=${encodeURIComponent(entryId)}&renew=1&notif=${encodeURIComponent(notificationId)}`;
                    return;
                }
                try {
                    const response = await fetch('api/notifications.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'confirm_mou_renewal',
                            notification_id: notificationId,
                            renewal_status: renewalStatus
                        })
                    });
                    
                    const data = await response.json();
                    if (data.success) {
                        // Reload notifications to reflect the confirmation
                        await loadNotifications();
                        await updateNotificationBadge();
                    } else {
                        console.error('Failed to confirm MOU renewal:', data.error);
                        alert('Failed to confirm MOU renewal status. Please try again.');
                    }
                } catch (error) {
                    console.error('Error confirming MOU renewal:', error);
                    alert('An error occurred while confirming MOU renewal status. Please try again.');
                }
            };
            
            // Initial load
            updateNotificationBadge();
            // Update badge every 30 seconds
            setInterval(updateNotificationBadge, 30000);
        })();
    </script>
</body>
</html>
