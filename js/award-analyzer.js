/**
 * Unified Award Analyzer Component
 * Displays Analysis & Recommendations Results across Documents, Events, and Process Awards pages
 */
class AwardAnalyzer {
    constructor(options = {}) {
        this.resultsContainerId = options.resultsContainerId || 'award-analysis-results';
        this.apiEndpoint = options.apiEndpoint || 'api/analyze-award.php';
        this.onAnalysisComplete = options.onAnalysisComplete || null;
        this.detectedText = '';
        this.injectEvidenceModal();
    }

    injectEvidenceModal() {
        if (document.getElementById('analyzerEvidenceModal')) return;
        
        // Wait for DOM to be ready
        if (!document.body) {
            // If body doesn't exist yet, wait for DOMContentLoaded
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.injectEvidenceModal());
                return;
            }
            // If still no body, return early
            return;
        }
        
        const html = `
            <div id="analyzerEvidenceModal" class="fixed inset-0 bg-black bg-opacity-50 z-[80] hidden flex items-center justify-center p-4">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full max-h-[80vh] flex flex-col">
                    <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-green-600 dark:text-green-400">find_in_page</span>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Matched Evidence</h3>
                        </div>
                        <button onclick="document.getElementById('analyzerEvidenceModal').classList.add('hidden')" class="p-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-full text-gray-500">✕</button>
                    </div>
                    <div class="p-6 overflow-y-auto">
                        <div class="mb-2 text-xs text-gray-500">Context for: <span id="analyzerEvidenceKeyword" class="font-bold text-primary"></span></div>
                        <div class="bg-gray-50 dark:bg-gray-900 p-4 rounded border border-gray-200 dark:border-gray-700">
                            <div id="analyzerEvidenceText" class="text-sm text-gray-700 dark:text-gray-300 font-mono whitespace-pre-wrap leading-relaxed"></div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', html);
    }

    /**
     * Analyze a file and display results
     * @param {File|string} file - File object or file path for re-analysis
     * @param {Object} options - Additional options (award_name, description, document_id, etc.)
     */
    async analyzeFile(file, options = {}) {
        if (!file && !options.filePath) {
            alert('Please select a file');
            return;
        }

        const formData = new FormData();
        
        // Handle file upload or re-analysis
        if (file instanceof File) {
            formData.append('award_file', file);
        } else if (options.filePath) {
            formData.append('reanalyze', 'true');
            formData.append('original_file_path', options.filePath);
        }

        // API requires award_name for new uploads; default from filename / path so analysis always gets a title
        let awardName = options.award_name;
        if (!awardName) {
            if (file instanceof File && file.name) {
                awardName = file.name.replace(/\.[^/.]+$/, '') || 'Document analysis';
            } else if (options.filePath) {
                const seg = options.filePath.split(/[/\\]/).filter(Boolean).pop() || '';
                awardName = seg.replace(/\.[^/.]+$/, '') || 'Document analysis';
            } else {
                awardName = 'Document analysis';
            }
        }
        formData.append('award_name', awardName);

        // Add optional fields
        if (options.description) formData.append('description', options.description);
        if (options.document_id) formData.append('document_id', options.document_id);
        if (options.source_page) formData.append('source_page', options.source_page);

        try {
            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                body: formData
            });

            const result = await response.json().catch(() => ({}));
            
            if (!response.ok && !result.success && !result.analysis) {
                const msg = result.error || result.message || ('Request failed (' + response.status + ')');
                alert('Error: ' + msg);
                return;
            }
            
            if (result.success || result.analysis) {
                // Handle different response formats
                const analysisData = result.analysis || result.data || result;
                // Pass detected text for context highlighting
                if (result.detected_text) options.detected_text = result.detected_text;
                this.displayResults(analysisData, options);
                
                if (this.onAnalysisComplete) {
                    this.onAnalysisComplete(analysisData);
                }
            } else {
                alert('Error: ' + (result.error || 'Analysis failed'));
            }
        } catch (error) {
            console.error('Analysis error:', error);
            alert('Analysis failed: ' + error.message);
        }
    }

    /**
     * Display analysis results in a unified format
     * @param {Object|Array} analysisData - Analysis results from API
     * @param {Object} options - Display options
     */
    displayResults(analysisData, options = {}) {
        if (options.detected_text) {
            this.detectedText = options.detected_text;
        }
        // Ensure window.awardAnalyzer is set for onclick handlers
        window.awardAnalyzer = this;
        let container = document.getElementById(this.resultsContainerId);
        
        if (!container) {
            container = document.createElement('div');
            container.id = this.resultsContainerId;
            container.className = 'award-analysis-results-container';
            
            // Try to find a suitable parent container
            const targetContainer = options.targetContainer || 
                                  document.querySelector('.space-y-6') ||
                                  document.querySelector('#analysis-results') ||
                                  document.body;
            
            targetContainer.appendChild(container);
        }

        // Normalize analysis data to array format
        let awards = [];
        if (Array.isArray(analysisData)) {
            awards = analysisData;
        } else if (analysisData.analysis && Array.isArray(analysisData.analysis)) {
            awards = analysisData.analysis;
        } else if (analysisData.all_matches && Array.isArray(analysisData.all_matches)) {
            awards = analysisData.all_matches;
        } else if (analysisData.matched_categories_json) {
            // ... existing parsing ...
            try {
                const parsed = typeof analysisData.matched_categories_json === 'string' 
                    ? JSON.parse(analysisData.matched_categories_json) 
                    : analysisData.matched_categories_json;
                awards = Array.isArray(parsed) ? parsed : [parsed];
            } catch (e) {
                console.error('Error parsing matched_categories_json:', e);
            }
        }

        // If still no awards, create a single award from the data
        if (awards.length === 0 && analysisData) {
            awards = [{
                category: analysisData.predicted_category || analysisData.category || 'Unknown',
                status: analysisData.status || 'Not Eligible',
                match_percentage: analysisData.match_percentage || analysisData.confidence || 0,
                confidence: analysisData.confidence || analysisData.match_percentage || 0,
                recommendation: analysisData.recommendations || analysisData.recommendation || 'No recommendation available',
                keywords: this.extractKeywords(analysisData),
                matched_count: this.getMatchedCount(analysisData),
                total_count: this.getTotalCount(analysisData)
            }];
        }

        // Store current awards for interactivity
        this.currentAwards = awards;

        let html = `
            <div class="analysis-results">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-text-light dark:text-text-dark">📊 Analysis & Recommendations Results</h3>
                    <button onclick="this.closest('.award-analysis-results-container').style.display='none'" 
                            class="close-results-btn">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
        `;

        if (awards.length === 0) {
            // ...
            html += `
                <div class="text-center py-8 text-text-muted-light dark:text-text-muted-dark">
                    <p>No analysis results available.</p>
                </div>
            `;
        } else {
            awards.forEach((award, index) => {
                const status = this.normalizeStatus(award.status || award.predicted_category);
                const statusClass = status.toLowerCase().replace(/\s+/g, '-');
                
                // Fix for 0 score issue: explicit check for undefined/null
                let matchPercentage = 0;
                if (award.match_percentage !== undefined && award.match_percentage !== null) {
                    matchPercentage = award.match_percentage;
                } else if (award.score !== undefined && award.score !== null) {
                    matchPercentage = award.score;
                } else if (award.confidence !== undefined && typeof award.confidence === 'number') {
                    matchPercentage = award.confidence;
                }

                const confidence = matchPercentage <= 1 ? matchPercentage * 100 : matchPercentage;
                const confidenceLevel = confidence >= 70 ? 'high' : (confidence >= 40 ? 'medium' : 'low');
                const category = award.category || award.predicted_category || award.name || 'Unknown Award';
                const recommendation = award.recommendations || award.recommendation || award.recommendations_text || this.getDefaultRecommendation(status, category, confidence);
                const keywords = this.extractKeywords(award);
                const matchedCount = award.matched_count || keywords.filter(k => k.matched).length;
                const totalCount = award.total_count || keywords.length || 10;

                html += `
                    <div class="award-card" data-award-index="${index}">
                        <div class="card-header">
                            <div>
                                <h4 class="text-lg font-semibold text-text-light dark:text-text-dark">${category}</h4>
                                <span class="badge">${award.award_type || 'Institutional'} - <span class="confidence-val">${Math.round(confidence)}</span>%</span>
                            </div>
                            <span class="status status-${statusClass}">
                                ${status === 'Eligible' ? '✓' : status === 'Almost Eligible' ? '~' : '✕'} ${status}
                            </span>
                        </div>

                        <div class="confidence-row">
                            <span class="text-sm text-text-muted-light dark:text-text-muted-dark">Match Confidence</span>
                            <div class="confidence-display">
                                <div class="bar">
                                    <div class="fill fill-${confidenceLevel}" style="width: ${Math.min(confidence, 100)}%"></div>
                                </div>
                                <span class="text text-${confidenceLevel}">${Math.round(confidence)}% (${confidenceLevel})</span>
                            </div>
                        </div>

                        <div class="recommendation recommendation-${statusClass}">
                            <span class="icon">${status === 'Eligible' ? '✓' : '✕'}</span>
                            <span class="text-sm">${recommendation}</span>
                        </div>

                        ${keywords.length > 0 ? `
                            <div class="keywords-section">
                                <div class="keywords-header" onclick="this.nextElementSibling.classList.toggle('hidden')">
                                    ▼ Keyword Analysis (${matchedCount} of ${totalCount} keywords matched)
                                </div>
                                <div class="keywords-list">
                                    ${keywords.map(kw => `
                                        <div class="keyword-item p-1 rounded flex items-center gap-2">
                                            <span class="icon cursor-pointer ${kw.matched ? 'text-green-600' : 'text-gray-400'}" 
                                                  onclick="window.awardAnalyzer.toggleKeyword(${index}, '${kw.name.replace(/'/g, "\\'")}')"
                                                  title="Click to toggle match status">
                                                ${kw.matched ? '✅' : '⚪'}
                                            </span>
                                            <span class="text-gray-800 dark:text-gray-200 ${!kw.matched ? 'text-gray-500 dark:text-gray-400' : 'cursor-pointer hover:text-blue-600 hover:underline'}"
                                                  ${kw.matched ? `onclick="window.awardAnalyzer.showContext('${kw.name.replace(/'/g, "\\'")}')" title="Click to see evidence context"` : ''}>
                                                ${kw.name}
                                                ${!kw.matched ? ' (missing)' : ''}
                                            </span>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        ` : ''}
                    </div>
                `;
            });
        }

        html += '</div>';

        container.innerHTML = html;
        container.style.display = 'block';
        container.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    /**
     * Toggle keyword match status and recalculate score
     */
    toggleKeyword(awardIndex, keywordName) {
        if (!this.currentAwards || !this.currentAwards[awardIndex]) return;
        
        const award = this.currentAwards[awardIndex];
        
        // Find keyword in lists
        let found = false;
        
        // Check matched_keywords array (strings)
        if (award.matched_keywords && Array.isArray(award.matched_keywords)) {
            const idx = award.matched_keywords.indexOf(keywordName);
            if (idx !== -1) {
                // Move from matched to missing
                award.matched_keywords.splice(idx, 1);
                if (!award.missing_keywords) award.missing_keywords = [];
                award.missing_keywords.push(keywordName);
                found = true;
            }
        }
        
        if (!found && award.missing_keywords && Array.isArray(award.missing_keywords)) {
            const idx = award.missing_keywords.indexOf(keywordName);
            if (idx !== -1) {
                // Move from missing to matched
                award.missing_keywords.splice(idx, 1);
                if (!award.matched_keywords) award.matched_keywords = [];
                award.matched_keywords.push(keywordName);
                found = true;
            }
        }
        
        // If using extracted objects format (fallback)
        // ... logic for fallback format omitted for brevity as we prefer the lists ...

        if (found) {
            this.recalculateScore(awardIndex);
            // Re-render (simple but effective)
            this.displayResults(this.currentAwards, {});
        }
    }

    /**
     * Recalculate score after keyword toggle
     */
    recalculateScore(awardIndex) {
        const award = this.currentAwards[awardIndex];
        
        const keywords = this.extractKeywords(award);
        const matchedCount = keywords.filter(k => k.matched).length;
        const totalCount = keywords.length;
        
        if (totalCount > 0) {
            // Simple unweighted recalculation: (matched / total) * 100
            const newPercentage = (matchedCount / totalCount) * 100;
            
            // Update score fields
            award.match_percentage = newPercentage;
            award.score = newPercentage;
            award.confidence = newPercentage; // Normalized 0-100
            
            // Update status
            if (newPercentage >= 90) {
                award.status = 'Eligible';
            } else if (newPercentage >= 70) {
                award.status = 'Almost Eligible';
            } else {
                award.status = 'Not Eligible';
            }
            
            // Update recommendation text
            award.recommendation = this.getDefaultRecommendation(award.status, award.category || award.name, newPercentage);
        }
    }

    /**
     * Show evidence context for a keyword
     */
    showContext(keyword) {
        const modal = document.getElementById('analyzerEvidenceModal');
        const keywordEl = document.getElementById('analyzerEvidenceKeyword');
        const textEl = document.getElementById('analyzerEvidenceText');
        
        if (!modal || !keywordEl || !textEl) return;
        
        keywordEl.textContent = keyword;
        
        const text = this.detectedText || '';
        if (!text) {
            textEl.textContent = 'Evidence text not available in this context.';
            modal.classList.remove('hidden');
            return;
        }
        
        // Find snippets
        // Escape keyword
        const escapedKeyword = keyword.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const regex = new RegExp(`(${escapedKeyword})`, 'gi');
        
        const matches = [...text.matchAll(regex)];
        
        if (matches.length > 0) {
            const snippets = matches.slice(0, 5).map(match => {
                const start = Math.max(0, match.index - 100);
                const end = Math.min(text.length, match.index + keyword.length + 100);
                let snippet = text.substring(start, end);
                
                // Highlight
                snippet = snippet.replace(regex, '<mark class="bg-yellow-200 dark:bg-yellow-900/50 text-gray-900 dark:text-white rounded px-1 font-bold">$1</mark>');
                return `...${snippet}...`;
            });
            
            textEl.innerHTML = snippets.join('<hr class="my-4 border-gray-200 dark:border-gray-700 border-dashed">');
        } else {
            textEl.textContent = 'Keyword matched in analysis but not found in current text (possibly partial/fuzzy match).';
        }
        
        modal.classList.remove('hidden');
    }

    /**
     * Get default recommendation if none provided
     */
    getDefaultRecommendation(status, award, percentage) {
        if (status === 'Eligible') {
            return `🎉 Excellent! Your document qualifies for the ${award}. You should apply for this award!`;
        } else if (status === 'Partially Eligible' || status === 'Almost Eligible') {
            return `👍 Good potential for the ${award}. With some refinements, you could be eligible for this award.`;
        } else {
            return `Your document doesn't currently meet the criteria for the ${award}. Consider revising to better align with the award requirements.`;
        }
    }

    /**
     * Extract keywords from analysis data
     */
    extractKeywords(award) {
        const keywords = [];
        
        // Handle unified structure (matched_keywords and missing_keywords arrays)
        if (award.matched_keywords && Array.isArray(award.matched_keywords)) {
             award.matched_keywords.forEach(kw => keywords.push({ name: kw, matched: true }));
        }
        if (award.missing_keywords && Array.isArray(award.missing_keywords)) {
             award.missing_keywords.forEach(kw => keywords.push({ name: kw, matched: false }));
        }

        // If keywords found via unified structure, return them
        if (keywords.length > 0) return keywords;
        
        // Try legacy/fallback keyword sources
        if (award.matched_keywords) {
            const matched = typeof award.matched_keywords === 'string' 
                ? JSON.parse(award.matched_keywords) 
                : award.matched_keywords;
            
            if (Array.isArray(matched) && keywords.length === 0) {
                matched.forEach(kw => {
                    keywords.push({ name: kw, matched: true });
                });
            }
        }
        
        if (award.all_matches && Array.isArray(award.all_matches)) {
            award.all_matches.forEach(match => {
                if (match.met_criteria) {
                    match.met_criteria.forEach(criteria => {
                        keywords.push({ name: criteria, matched: true });
                    });
                }
                if (match.unmet_criteria) {
                    match.unmet_criteria.forEach(criteria => {
                        keywords.push({ name: criteria, matched: false });
                    });
                }
            });
        }

        // If no keywords found, create placeholder
        if (keywords.length === 0) {
            keywords.push(
                { name: 'Document analysis', matched: true },
                { name: 'Keyword matching', matched: award.status === 'Eligible' }
            );
        }

        return keywords;
    }

    /**
     * Get matched keyword count
     */
    getMatchedCount(award) {
        const keywords = this.extractKeywords(award);
        return keywords.filter(k => k.matched).length;
    }

    /**
     * Get total keyword count
     */
    getTotalCount(award) {
        const keywords = this.extractKeywords(award);
        return keywords.length || 10;
    }

    /**
     * Normalize status string
     */
    normalizeStatus(status) {
        if (!status) return 'Not Eligible';
        const s = String(status).trim();
        if (s.match(/eligible/i)) {
            if (s.match(/almost|partial/i)) return 'Almost Eligible';
            if (s.match(/not/i)) return 'Not Eligible';
            return 'Eligible';
        }
        return s;
    }

    /**
     * Hide results container
     */
    hide() {
        const container = document.getElementById(this.resultsContainerId);
        if (container) {
            container.style.display = 'none';
        }
    }

    /**
     * Show results container
     */
    show() {
        const container = document.getElementById(this.resultsContainerId);
        if (container) {
            container.style.display = 'block';
        }
    }
}

// Create global instance
if (typeof window !== 'undefined') {
    window.AwardAnalyzer = AwardAnalyzer;
    // Wait for DOM to be ready before creating instance
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.awardAnalyzer = new AwardAnalyzer();
        });
    } else {
        // DOM is already ready
        window.awardAnalyzer = new AwardAnalyzer();
    }
}

