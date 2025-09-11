/**
 * Live Search functionality for Company Wiki
 * Provides real-time search across all files and within current page
 */

class WikiLiveSearch {
    constructor() {
        this.searchInput = document.querySelector('.search-input');
        this.searchResults = null;
        this.currentPageContent = null;
        this.searchTimeout = null;
        this.minSearchLength = 2;
        this.searchDelay = 300; // ms
        this.currentMatches = [];
        this.currentMatchIndex = -1;
        this.cache = new Map(); // Cache search results
        this.debugMode = this.isDebugMode();
        
        this.init();
    }
    
    isDebugMode() {
        // Check if debug mode is enabled (you can set this via URL param or config)
        return window.location.search.includes('debug=1') || 
               document.body.dataset.debug === 'true' ||
               localStorage.getItem('searchDebug') === 'true';
    }
    
    log(message, data = null) {
        if (this.debugMode) {
            console.log(`[WikiLiveSearch] ${message}`, data || '');
        }
    }
    
    error(message, error = null) {
        console.error(`[WikiLiveSearch] ERROR: ${message}`, error || '');
        
        // Show user-friendly error in debug mode
        if (this.debugMode && this.searchResults) {
            const debugInfo = this.searchResults.querySelector('.debug-info') || 
                            this.createDebugContainer();
            debugInfo.innerHTML += `<div style="color: red; margin: 5px 0;">ERROR: ${message}</div>`;
        }
    }
    
    createDebugContainer() {
        const debugDiv = document.createElement('div');
        debugDiv.className = 'debug-info';
        debugDiv.style.cssText = `
            background: #f8f9fa; 
            border: 1px solid #dee2e6; 
            padding: 10px; 
            margin: 10px 0; 
            font-size: 12px; 
            border-radius: 4px;
            max-height: 200px;
            overflow-y: auto;
        `;
        this.searchResults.appendChild(debugDiv);
        return debugDiv;
    }
    
    init() {
        this.log('Initializing WikiLiveSearch');
        
        if (!this.searchInput) {
            this.error('Search input not found');
            return;
        }
        
        this.log('Search input found', this.searchInput);
        
        // Create search results container
        this.createSearchResultsContainer();
        
        // Get current page content for in-page search
        this.getCurrentPageContent();
        
        // Bind events
        this.bindEvents();
        
        // Test search API availability
        this.testSearchAPI();
        
        // Initialize with existing search query if present
        if (this.searchInput.value.length >= this.minSearchLength) {
            this.log('Initial search query detected', this.searchInput.value);
            this.performSearch(this.searchInput.value);
        }
        
        this.log('WikiLiveSearch initialized successfully');
    }
    
    async testSearchAPI() {
        try {
            this.log('Testing search API availability');
            const response = await fetch('search-api.php?q=test', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            });
            
            this.log('Search API response status', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            this.log('Search API test successful', data);
            
        } catch (error) {
            this.error('Search API test failed', error);
            this.showSearchAPIError(error);
        }
    }
    
    showSearchAPIError(error) {
        if (this.searchResults) {
            const acrossFilesContainer = this.searchResults.querySelector('.across-files-results');
            if (acrossFilesContainer) {
                acrossFilesContainer.innerHTML = `
                    <div class="search-api-error" style="color: #dc3545; padding: 10px; background: #f8d7da; border-radius: 4px; margin: 5px 0;">
                        <strong>Search API Error:</strong> ${error.message}<br>
                        <small>Cross-file search is currently unavailable. Only current page search is working.</small>
                    </div>
                `;
            }
        }
    }
    
    createSearchResultsContainer() {
        this.log('Creating search results container');
        
        // Create dropdown for search results
        const container = document.createElement('div');
        container.className = 'live-search-results';
        container.innerHTML = `
            <div class="live-search-dropdown">
                <div class="live-search-in-page">
                    <h4>In This Page</h4>
                    <div class="in-page-results"></div>
                </div>
                <div class="live-search-across-files">
                    <h4>Across All Files</h4>
                    <div class="across-files-results"></div>
                </div>
                ${this.debugMode ? '<div class="debug-info" style="margin-top: 10px;"></div>' : ''}
            </div>
        `;
        
        // Insert after search container
        const searchContainer = this.searchInput.closest('.search-container');
        if (searchContainer) {
            searchContainer.appendChild(container);
            this.searchResults = container;
            this.log('Search results container created and attached');
        } else {
            this.error('Search container not found - cannot attach results');
        }
        
        // Initially hidden
        this.searchResults.style.display = 'none';
    }
    
    getCurrentPageContent() {
        const contentElement = document.querySelector('.content');
        if (contentElement) {
            this.currentPageContent = contentElement.textContent || contentElement.innerText || '';
            this.log('Current page content loaded', `${this.currentPageContent.length} characters`);
        } else {
            this.log('No content element found for current page search');
        }
    }
    
    bindEvents() {
        this.log('Binding search events');
        
        // Search input events
        this.searchInput.addEventListener('input', (e) => {
            this.log('Search input changed', e.target.value);
            this.handleSearchInput(e.target.value);
        });
        
        this.searchInput.addEventListener('focus', () => {
            this.log('Search input focused');
            if (this.searchInput.value.length >= this.minSearchLength) {
                this.showResults();
            }
        });
        
        // Keyboard navigation
        this.searchInput.addEventListener('keydown', (e) => {
            this.handleKeyboardNavigation(e);
        });
        
        // Hide results when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.search-container')) {
                this.hideResults();
            }
        });
        
        // ESC key to close
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.hideResults();
                this.clearPageHighlights();
            }
        });
        
        this.log('Events bound successfully');
    }
    
    handleSearchInput(query) {
        clearTimeout(this.searchTimeout);
        
        if (query.length < this.minSearchLength) {
            this.log('Query too short, hiding results');
            this.hideResults();
            this.clearPageHighlights();
            return;
        }
        
        this.log('Debouncing search', query);
        
        // Debounce search
        this.searchTimeout = setTimeout(() => {
            this.performSearch(query);
        }, this.searchDelay);
    }
    
    async performSearch(query) {
        this.log('Starting search', query);
        
        if (!query || query.length < this.minSearchLength) {
            this.log('Query validation failed');
            return;
        }
        
        // Check cache first
        const cacheKey = query.toLowerCase();
        if (this.cache.has(cacheKey)) {
            this.log('Using cached results', cacheKey);
            this.displayResults(query, this.cache.get(cacheKey));
            return;
        }
        
        // Show loading state
        this.showLoadingState();
        
        try {
            this.log('Making AJAX request to search API');
            
            const url = `search-api.php?q=${encodeURIComponent(query)}`;
            this.log('Request URL', url);
            
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            });
            
            this.log('Response received', {
                status: response.status,
                statusText: response.statusText,
                headers: Object.fromEntries(response.headers.entries())
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const textResponse = await response.text();
                this.log('Non-JSON response received', textResponse.substring(0, 500));
                throw new Error(`Expected JSON response, got: ${contentType}. Response: ${textResponse.substring(0, 100)}...`);
            }
            
            const data = await response.json();
            this.log('Search API response', data);
            
            // Validate response structure
            if (!data || typeof data !== 'object') {
                throw new Error('Invalid response format');
            }
            
            if (data.success === false) {
                throw new Error(data.error || 'Search API returned error');
            }
            
            // Cache results
            this.cache.set(cacheKey, data);
            this.log('Results cached', cacheKey);
            
            // Display results
            this.displayResults(query, data);
            
        } catch (error) {
            this.error('Search API request failed', error);
            this.displayError(error.message);
        }
    }
    
    showLoadingState() {
        if (this.searchResults) {
            const acrossFilesContainer = this.searchResults.querySelector('.across-files-results');
            if (acrossFilesContainer) {
                acrossFilesContainer.innerHTML = '<div class="search-loading">Searching...</div>';
            }
            this.showResults();
        }
    }
    
    displayResults(query, data) {
        this.log('Displaying search results', { query, resultCount: data.results?.length || 0 });
        
        const inPageResults = this.searchInCurrentPage(query);
        const acrossFilesResults = data.results || [];
        
        this.log('In-page results', inPageResults.length);
        this.log('Cross-file results', acrossFilesResults.length);
        
        // Update in-page results
        const inPageContainer = this.searchResults.querySelector('.in-page-results');
        this.renderInPageResults(inPageResults, inPageContainer);
        
        // Update across-files results
        const acrossFilesContainer = this.searchResults.querySelector('.across-files-results');
        this.renderAcrossFilesResults(acrossFilesResults, acrossFilesContainer, query);
        
        // Show/hide sections based on results
        const inPageSection = this.searchResults.querySelector('.live-search-in-page');
        const acrossFilesSection = this.searchResults.querySelector('.live-search-across-files');
        
        // IMPORTANT: Always show both sections, even if empty, in debug mode
        if (this.debugMode) {
            inPageSection.style.display = 'block';
            acrossFilesSection.style.display = 'block';
        } else {
            inPageSection.style.display = inPageResults.length > 0 ? 'block' : 'none';
            acrossFilesSection.style.display = acrossFilesResults.length > 0 ? 'block' : 'none';
        }
        
        // Add debug information
        if (this.debugMode) {
            this.addDebugInfo(query, data, inPageResults, acrossFilesResults);
        }
        
        // Show results
        this.showResults();
        
        // Highlight matches in current page
        this.highlightPageMatches(query);
        
        this.log('Results display completed');
    }
    
    addDebugInfo(query, data, inPageResults, acrossFilesResults) {
        const debugContainer = this.searchResults.querySelector('.debug-info');
        if (debugContainer) {
            debugContainer.innerHTML = `
                <strong>Debug Information:</strong><br>
                Query: "${query}"<br>
                API Success: ${data.success ? 'Yes' : 'No'}<br>
                API Total Results: ${data.total || 0}<br>
                In-Page Matches: ${inPageResults.length}<br>
                Cross-File Results: ${acrossFilesResults.length}<br>
                Cached: ${data.cached ? 'Yes' : 'No'}<br>
                Cache Size: ${this.cache.size} entries<br>
                Current Page Content: ${this.currentPageContent ? this.currentPageContent.length + ' chars' : 'Not loaded'}
            `;
        }
    }
    
    searchInCurrentPage(query) {
        if (!this.currentPageContent) {
            this.log('No current page content for in-page search');
            return [];
        }
        
        const results = [];
        const regex = new RegExp(query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi');
        const lines = this.currentPageContent.split('\n');
        
        lines.forEach((line, index) => {
            if (regex.test(line)) {
                const snippet = line.length > 100 ? 
                    line.substring(0, 100) + '...' : line;
                results.push({
                    line: index + 1,
                    snippet: snippet.trim(),
                    fullLine: line
                });
            }
        });
        
        this.log('In-page search completed', results.length + ' matches');
        return results.slice(0, 5); // Limit to 5 results
    }
    
    renderInPageResults(results, container) {
        if (!container) return;
        
        if (results.length === 0) {
            container.innerHTML = this.debugMode ? 
                '<div class="no-results">No matches in current page</div>' : 
                '<div class="no-results">No matches found</div>';
            return;
        }
        
        const html = results.map((result, index) => `
            <div class="in-page-result" data-index="${index}">
                <div class="line-number">Line ${result.line}</div>
                <div class="snippet">${this.highlightText(result.snippet, this.searchInput.value)}</div>
            </div>
        `).join('');
        
        container.innerHTML = html;
    }
    
    renderAcrossFilesResults(results, container, query) {
        if (!container) return;
        
        if (results.length === 0) {
            container.innerHTML = this.debugMode ? 
                '<div class="no-results">No cross-file results (check debug info below)</div>' : 
                '<div class="no-results">No results found</div>';
            return;
        }
        
        const html = results.map(result => `
            <div class="across-files-result">
                <h5><a href="${result.url}">${result.title}</a></h5>
                ${result.snippet ? `<div class="snippet">${this.highlightText(result.snippet, query)}</div>` : ''}
                ${this.debugMode ? `<div class="debug-path">${result.path}</div>` : ''}
            </div>
        `).join('');
        
        container.innerHTML = html;
    }
    
    handleKeyboardNavigation(e) {
        if (!this.searchResults || this.searchResults.style.display === 'none') return;
        
        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                this.navigateResults('down');
                break;
            case 'ArrowUp':
                e.preventDefault();
                this.navigateResults('up');
                break;
            case 'Enter':
                e.preventDefault();
                this.activateSelectedResult();
                break;
            case 'Escape':
                this.hideResults();
                this.clearPageHighlights();
                break;
        }
    }
    
    navigateResults(direction) {
        const results = this.searchResults.querySelectorAll('.in-page-result, .across-files-result');
        if (results.length === 0) return;
        
        // Remove previous selection
        results.forEach(r => r.classList.remove('selected'));
        
        // Update selection index
        if (direction === 'down') {
            this.selectedResultIndex = Math.min(results.length - 1, (this.selectedResultIndex || -1) + 1);
        } else {
            this.selectedResultIndex = Math.max(0, (this.selectedResultIndex || 0) - 1);
        }
        
        // Highlight new selection
        if (results[this.selectedResultIndex]) {
            results[this.selectedResultIndex].classList.add('selected');
        }
    }
    
    activateSelectedResult() {
        const selected = this.searchResults.querySelector('.selected');
        if (selected) {
            if (selected.classList.contains('in-page-result')) {
                const index = parseInt(selected.dataset.index);
                this.scrollToPageMatch(index);
            } else {
                const link = selected.querySelector('a');
                if (link) {
                    window.location.href = link.href;
                }
            }
        }
    }
    
    scrollToPageMatch(index) {
        // Implementation for scrolling to specific match in page
        this.log('Scrolling to page match', index);
    }
    
    showResults() {
        if (this.searchResults) {
            this.searchResults.style.display = 'block';
            this.log('Search results shown');
        }
    }
    
    hideResults() {
        if (this.searchResults) {
            this.searchResults.style.display = 'none';
            this.selectedResultIndex = -1;
            this.log('Search results hidden');
        }
    }
    
    displayError(errorMessage) {
        if (this.searchResults) {
            const acrossFilesContainer = this.searchResults.querySelector('.across-files-results');
            if (acrossFilesContainer) {
                acrossFilesContainer.innerHTML = `
                    <div class="search-error" style="color: #dc3545; padding: 10px; background: #f8d7da; border-radius: 4px;">
                        <strong>Search Error:</strong> ${errorMessage}
                    </div>
                `;
            }
            this.showResults();
        }
    }
    
    clearPageHighlights() {
        // Remove any existing highlights
        const highlights = document.querySelectorAll('mark.search-highlight');
        highlights.forEach(mark => {
            const parent = mark.parentNode;
            parent.replaceChild(document.createTextNode(mark.textContent), mark);
            parent.normalize();
        });
    }
    
    highlightPageMatches(query) {
        // Implementation for highlighting matches in current page
        this.log('Highlighting page matches', query);
    }
    
    highlightText(text, searchTerm) {
        const regex = new RegExp(`(${searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
        return this.escapeHtml(text).replace(regex, '<mark>$1</mark>');
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Enable debug mode by adding ?debug=1 to URL or setting localStorage
    if (window.location.search.includes('debug=1')) {
        localStorage.setItem('searchDebug', 'true');
        document.body.dataset.debug = 'true';
    }
    
    window.wikiLiveSearch = new WikiLiveSearch();
    
    // Add debug toggle for easy access
    if (window.wikiLiveSearch.debugMode) {
        console.log('%cWikiLiveSearch Debug Mode Enabled', 'color: green; font-weight: bold;');
        console.log('To disable: localStorage.removeItem("searchDebug")');
    }
});