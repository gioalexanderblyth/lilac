// New showSuccessRate function - replace in user-awards.php around line 5288
function showSuccessRate() {
    const data = window.analyticsData;
    if (!data || !data.kpis) {
        showModal('📊 Success Rate', '<p class="text-gray-600">No analytics data available.</p>');
        return;
    }

    const requirements = data.requirements || [];
    const successRate = parseFloat(data.kpis.success_rate || 0);
    const totalAwards = data.kpis.total_awards || 0;
    const eligibleAwards = data.kpis.awards_eligible || 0;
    const recognizedAwards = data.kpis.awards_recognized || 0;

    // Calculate performance metrics
    const eligibilityRate = totalAwards > 0 ? ((eligibleAwards / totalAwards) * 100).toFixed(1) : 0;
    const recognitionRate = totalAwards > 0 ? ((recognizedAwards / totalAwards) * 100).toFixed(1) : 0;

    // Count by match percentage ranges
    const excellent = requirements.filter(r => parseFloat(r.match_percentage || 0) >= 90).length;
    const good = requirements.filter(r => {
        const pct = parseFloat(r.match_percentage || 0);
        return pct >= 70 && pct < 90;
    }).length;
    const needsImprovement = requirements.filter(r => parseFloat(r.match_percentage || 0) < 70).length;

    // Average criteria met
    const avgCriteriaMet = requirements.length > 0
        ? (requirements.reduce((sum, r) => sum + (parseInt(r.criteria_met) || 0), 0) / requirements.length).toFixed(1)
        : 0;
    const avgCriteriaTotal = requirements.length > 0
        ? (requirements.reduce((sum, r) => sum + (parseInt(r.criteria_total) || 0), 0) / requirements.length).toFixed(1)
        : 0;

    // Best and worst performances
    const sortedByMatch = [...requirements].sort((a, b) =>
        parseFloat(b.match_percentage || 0) - parseFloat(a.match_percentage || 0)
    );
    const bestPerformance = sortedByMatch[0];
    const worstPerformance = sortedByMatch[sortedByMatch.length - 1];

    const content = `
        <div class="space-y-4">
            <div class="bg-gradient-to-r from-pink-50 to-purple-50 dark:from-pink-900/20 dark:to-purple-900/20 border border-pink-200 dark:border-pink-800 rounded-lg p-4">
                <h3 class="font-semibold text-pink-800 dark:text-pink-300 mb-3">📊 Performance Analytics</h3>

                <!-- Overall Success Rate -->
                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 mb-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Overall Success Rate</span>
                        <span class="text-3xl font-bold text-pink-600 dark:text-pink-400">${successRate}%</span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                        <div class="bg-gradient-to-r from-pink-500 to-purple-500 h-3 rounded-full transition-all" style="width: ${successRate}%"></div>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Average match percentage across all ${totalAwards} submissions</p>
                </div>

                <!-- Key Performance Indicators -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div class="bg-green-100 dark:bg-green-900/30 rounded-lg p-4 text-center">
                        <p class="text-2xl font-bold text-green-700 dark:text-green-400">${eligibilityRate}%</p>
                        <p class="text-xs text-gray-600 dark:text-gray-400">Eligibility Rate</p>
                        <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">${eligibleAwards}/${totalAwards} eligible</p>
                    </div>
                    <div class="bg-blue-100 dark:bg-blue-900/30 rounded-lg p-4 text-center">
                        <p class="text-2xl font-bold text-blue-700 dark:text-blue-400">${recognitionRate}%</p>
                        <p class="text-xs text-gray-600 dark:text-gray-400">Recognition Rate</p>
                        <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">${recognizedAwards}/${totalAwards} approved</p>
                    </div>
                    <div class="bg-purple-100 dark:bg-purple-900/30 rounded-lg p-4 text-center">
                        <p class="text-2xl font-bold text-purple-700 dark:text-purple-400">${avgCriteriaMet}/${avgCriteriaTotal}</p>
                        <p class="text-xs text-gray-600 dark:text-gray-400">Avg Criteria Met</p>
                        <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">Per submission</p>
                    </div>
                </div>

                <!-- Performance Distribution -->
                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 mb-4">
                    <h4 class="font-semibold text-gray-800 dark:text-gray-200 mb-3">Performance Distribution</h4>
                    <div class="space-y-3">
                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-sm text-gray-600 dark:text-gray-400">✅ Excellent (≥90%)</span>
                                <span class="font-bold text-green-600 dark:text-green-400">${excellent} submissions</span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div class="bg-green-500 h-2 rounded-full" style="width: ${totalAwards > 0 ? (excellent / totalAwards * 100) : 0}%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-sm text-gray-600 dark:text-gray-400">🟡 Good (70-89%)</span>
                                <span class="font-bold text-yellow-600 dark:text-yellow-400">${good} submissions</span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div class="bg-yellow-500 h-2 rounded-full" style="width: ${totalAwards > 0 ? (good / totalAwards * 100) : 0}%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-sm text-gray-600 dark:text-gray-400">❌ Needs Improvement (<70%)</span>
                                <span class="font-bold text-red-600 dark:text-red-400">${needsImprovement} submissions</span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div class="bg-red-500 h-2 rounded-full" style="width: ${totalAwards > 0 ? (needsImprovement / totalAwards * 100) : 0}%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Best & Worst Performance -->
                ${bestPerformance ? `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                        <h4 class="font-semibold text-green-800 dark:text-green-300 mb-2 flex items-center gap-2">
                            <span>🏆</span> Best Performance
                        </h4>
                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">${bestPerformance.award_name || 'N/A'}</p>
                        <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">${bestPerformance.submission_title || 'N/A'}</p>
                        <p class="text-2xl font-bold text-green-600 dark:text-green-400">${parseFloat(bestPerformance.match_percentage || 0).toFixed(1)}%</p>
                    </div>
                    ${worstPerformance && worstPerformance !== bestPerformance ? `
                    <div class="bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-lg p-4">
                        <h4 class="font-semibold text-orange-800 dark:text-orange-300 mb-2 flex items-center gap-2">
                            <span>💪</span> Improvement Opportunity
                        </h4>
                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">${worstPerformance.award_name || 'N/A'}</p>
                        <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">${worstPerformance.submission_title || 'N/A'}</p>
                        <p class="text-2xl font-bold text-orange-600 dark:text-orange-400">${parseFloat(worstPerformance.match_percentage || 0).toFixed(1)}%</p>
                    </div>
                    ` : ''}
                </div>
                ` : '<p class="text-center text-gray-500 dark:text-gray-400 py-4">No performance data yet. Submit your first award!</p>'}
            </div>
        </div>
    `;
    showModal('📊 Success Rate - Performance Analytics', content);
}
