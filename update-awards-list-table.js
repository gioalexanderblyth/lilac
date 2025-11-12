// Updated Awards List Table Functions for Users
// Add these functions to user-awards.php JavaScript section

// Add this global variable at the top
let allUserSubmissions = [];

// Modified loadAwardListData function
async function loadAwardListData() {
    const isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;

    try {
        if (isAdmin) {
            // Load award criteria with applicant counts (existing code)
            const response = await fetch('api/award-applicants.php?list_all=true', {
                headers: {
                    'Authorization': 'Bearer ' + AUTH_TOKEN
                }
            });

            if (!response.ok) {
                throw new Error('Failed to load award criteria');
            }

            const result = await response.json();
            if (!result.success) {
                throw new Error(result.error || 'Failed to load criteria');
            }

            renderAwardCriteriaList(result.criteria);

        } else {
            // Load user's own submissions
            const response = await fetch('api/user-award-submissions.php');

            if (!response.ok) {
                throw new Error('Failed to load submissions');
            }

            const result = await response.json();
            if (!result.success) {
                throw new Error(result.error || 'Failed to load submissions');
            }

            allUserSubmissions = result.submissions || [];
            renderUserSubmissionsList(allUserSubmissions);
        }

    } catch (error) {
        console.error('Error loading award list:', error);
        const tbody = document.getElementById('award-criteria-tbody');
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="px-6 py-8 text-center text-red-600 dark:text-red-400">
                        Failed to load: ${error.message}
                    </td>
                </tr>
            `;
        }
    }
}

// New function to render user submissions list
function renderUserSubmissionsList(submissions) {
    const tbody = document.getElementById('award-criteria-tbody');
    if (!tbody) return;

    if (submissions.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                    <div class="flex flex-col items-center gap-3">
                        <span class="material-symbols-outlined text-5xl">folder_open</span>
                        <p class="text-lg font-medium">No awards submitted yet</p>
                        <p class="text-sm">Go to <strong>Process Award</strong> tab to submit your first award!</p>
                    </div>
                </td>
            </tr>
        `;
        return;
    }

    // Update counters
    const eligible = submissions.filter(s => parseFloat(s.match_percentage || 0) >= 90).length;
    const pending = submissions.filter(s => s.status === 'pending').length;
    const recognized = submissions.filter(s => s.status === 'approved').length;

    document.getElementById('total-processed-count').textContent = submissions.length;
    document.getElementById('recognized-count').textContent = recognized;
    document.getElementById('pending-count').textContent = pending;

    tbody.innerHTML = submissions.map(sub => {
        const matchPct = parseFloat(sub.match_percentage || 0);
        const criteriaMet = sub.criteria_met || 0;
        const criteriaTotal = sub.criteria_total || 0;

        // Status badge
        let statusBadge, statusText;
        if (sub.status === 'approved') {
            statusBadge = 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400';
            statusText = '✅ Recognized';
        } else if (sub.status === 'analyzed' && matchPct >= 90) {
            statusBadge = 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400';
            statusText = '✅ Eligible';
        } else if (sub.status === 'analyzed' && matchPct >= 70) {
            statusBadge = 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400';
            statusText = '🟡 Almost Eligible';
        } else if (sub.status === 'analyzed') {
            statusBadge = 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400';
            statusText = '❌ Not Eligible';
        } else {
            statusBadge = 'bg-gray-100 dark:bg-gray-900/30 text-gray-700 dark:text-gray-400';
            statusText = '⏳ Pending';
        }

        return `
            <tr class="hover:bg-primary/5 transition-colors cursor-pointer"
                onclick="viewSubmissionDetail(${sub.award_id})">
                <td class="px-6 py-4">
                    <div class="font-semibold text-primary hover:text-primary/80">
                        ${escapeHtml(sub.award_name || 'Unknown Award')}
                    </div>
                </td>
                <td class="px-6 py-4">
                    <div class="text-sm text-gray-700 dark:text-gray-300">
                        ${escapeHtml(sub.submission_title || 'Untitled')}
                    </div>
                </td>
                <td class="px-6 py-4 text-center">
                    <span class="text-lg font-bold ${matchPct >= 90 ? 'text-green-600 dark:text-green-400' : matchPct >= 70 ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400'}">
                        ${criteriaMet}/${criteriaTotal}
                    </span>
                </td>
                <td class="px-6 py-4">
                    <div class="flex items-center gap-2">
                        <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div class="h-2 rounded-full transition-all ${
                                matchPct >= 90 ? 'bg-green-500' :
                                matchPct >= 70 ? 'bg-yellow-500' :
                                'bg-red-500'
                            }" style="width: ${matchPct}%"></div>
                        </div>
                        <span class="text-sm font-semibold ${
                            matchPct >= 90 ? 'text-green-600 dark:text-green-400' :
                            matchPct >= 70 ? 'text-yellow-600 dark:text-yellow-400' :
                            'text-red-600 dark:text-red-400'
                        }">${matchPct.toFixed(1)}%</span>
                    </div>
                </td>
                <td class="px-6 py-4">
                    <span class="px-2 py-1 text-xs font-medium rounded-full ${statusBadge}">
                        ${statusText}
                    </span>
                </td>
                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                    ${new Date(sub.created_at).toLocaleDateString()}
                </td>
                <td class="px-6 py-4 text-center">
                    <button class="p-2 rounded-md hover:bg-primary/10 text-primary"
                            onclick="event.stopPropagation(); viewSubmissionDetail(${sub.award_id})"
                            title="View Details">
                        <span class="material-symbols-outlined text-lg">visibility</span>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

// View submission detail function
function viewSubmissionDetail(awardId) {
    window.location.href = `user-award-detail.php?id=${awardId}`;
}

// Updated filter function for user submissions
function filterAwardsList() {
    const isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;

    if (isAdmin) {
        // Use existing admin filter logic
        return;
    }

    // User filter logic
    const searchTerm = document.getElementById('searchInput')?.value.toLowerCase() || '';
    const categoryFilter = document.getElementById('categoryFilter')?.value || '';
    const confidenceFilter = document.getElementById('confidenceFilter')?.value || '';
    const dateFilter = document.getElementById('dateFilter')?.value || '';

    let filtered = [...allUserSubmissions];

    // Search filter
    if (searchTerm) {
        filtered = filtered.filter(sub =>
            (sub.award_name && sub.award_name.toLowerCase().includes(searchTerm)) ||
            (sub.submission_title && sub.submission_title.toLowerCase().includes(searchTerm))
        );
    }

    // Category filter
    if (categoryFilter) {
        filtered = filtered.filter(sub => sub.award_name === categoryFilter);
    }

    // Confidence/Match percentage filter
    if (confidenceFilter) {
        filtered = filtered.filter(sub => {
            const matchPct = parseFloat(sub.match_percentage || 0);
            const [min, max] = confidenceFilter.split('-').map(Number);
            return matchPct >= min && matchPct <= max;
        });
    }

    // Date filter
    if (dateFilter) {
        filtered = filtered.filter(sub => {
            const subDate = new Date(sub.created_at).toISOString().split('T')[0];
            return subDate === dateFilter;
        });
    }

    renderUserSubmissionsList(filtered);
}

// Clear filters function
function clearFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('categoryFilter').value = '';
    document.getElementById('confidenceFilter').value = '';
    document.getElementById('dateFilter').value = '';

    const isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;
    if (!isAdmin) {
        renderUserSubmissionsList(allUserSubmissions);
    }
}

// Call loadAwardListData when award list tab is clicked
document.getElementById('award-list-tab')?.addEventListener('click', () => {
    loadAwardListData();
});
