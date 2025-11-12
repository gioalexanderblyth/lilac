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
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Award Submission Detail | LILAC</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Material Symbols -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
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
                    <a href="user-awards.php#award-list" class="flex items-center gap-2 text-gray-600 dark:text-gray-400 hover:text-primary transition-colors">
                        <span class="material-symbols-outlined">arrow_back</span>
                        <span>Back to Award List</span>
                    </a>
                </div>
                <div class="flex items-center gap-4">
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
    </script>
</body>
</html>
