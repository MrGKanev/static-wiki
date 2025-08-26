/**
 * Keyboard Shortcuts System for Company Wiki
 * Add this to the layout.php template in the script section
 */

class WikiKeyboardShortcuts {
    constructor() {
        this.shortcuts = {
            // Search and navigation
            '/': () => this.focusSearch(),
            'Escape': () => this.unfocusSearch(),
            'g h': () => this.goHome(),
            'g s': () => this.focusSearch(),
            
            // Navigation
            'j': () => this.nextLink(),
            'k': () => this.previousLink(),
            'Enter': () => this.followCurrentLink(),
            
            // Page actions  
            'p': () => this.printPage(),
            'e': () => this.exportPDF(),
            'E': () => this.exportSectionPDF(),
            
            // Table of contents
            't': () => this.toggleTOC(),
            'T': () => this.focusTOC(),
            
            // Mobile menu
            'm': () => this.toggleMobileMenu(),
            
            // Help
            '?': () => this.showHelp()
        };
        
        this.currentLinkIndex = -1;
        this.links = [];
        this.keySequence = '';
        this.sequenceTimeout = null;
        this.helpVisible = false;
        
        this.init();
    }
    
    init() {
        // Bind keyboard events
        document.addEventListener('keydown', (e) => this.handleKeyDown(e));
        document.addEventListener('keyup', (e) => this.handleKeyUp(e));
        
        // Update links when page loads
        this.updateNavigableLinks();
        
        // Update links when DOM changes (for dynamic content)
        const observer = new MutationObserver(() => this.updateNavigableLinks());
        observer.observe(document.body, { childList: true, subtree: true });
        
        // Add visual indicators
        this.addKeyboardIndicators();
    }
    
    handleKeyDown(e) {
        // Don't interfere when user is typing in inputs
        if (this.isInputFocused()) {
            if (e.key === 'Escape') {
                this.unfocusSearch();
            }
            return;
        }
        
        // Handle multi-key sequences (like 'g h')
        if (e.key.length === 1) {
            this.keySequence += e.key;
            
            // Clear sequence after timeout
            clearTimeout(this.sequenceTimeout);
            this.sequenceTimeout = setTimeout(() => {
                this.keySequence = '';
            }, 1000);
            
            // Check for sequence matches
            const sequence = this.keySequence;
            if (this.shortcuts[sequence]) {
                e.preventDefault();
                this.shortcuts[sequence]();
                this.keySequence = '';
                return;
            }
        }
        
        // Handle single key shortcuts
        if (this.shortcuts[e.key]) {
            // Don't prevent default for some keys in certain contexts
            if (e.key !== '/' || !this.isSearchFocused()) {
                e.preventDefault();
                this.shortcuts[e.key]();
            }
        }
    }
    
    handleKeyUp(e) {
        // Handle any key up events if needed
    }
    
    // Search functions
    focusSearch() {
        const searchInput = document.querySelector('.search-input');
        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    }
    
    unfocusSearch() {
        const searchInput = document.querySelector('.search-input');
        if (searchInput && document.activeElement === searchInput) {
            searchInput.blur();
        }
        
        // Also close mobile menu and help if open
        this.closeMobileMenu();
        this.hideHelp();
    }
    
    isSearchFocused() {
        const searchInput = document.querySelector('.search-input');
        return searchInput && document.activeElement === searchInput;
    }
    
    // Navigation functions
    goHome() {
        window.location.href = '?';
    }
    
    updateNavigableLinks() {
        // Get all clickable elements in main content and navigation
        this.links = Array.from(document.querySelectorAll(
            '.main-content a, .navigation a, .table-of-contents a'
        )).filter(link => {
            const rect = link.getBoundingClientRect();
            return rect.height > 0 && rect.width > 0; // Visible links only
        });
        
        this.currentLinkIndex = -1;
        this.clearLinkHighlights();
    }
    
    nextLink() {
        if (this.links.length === 0) return;
        
        this.clearLinkHighlights();
        this.currentLinkIndex = (this.currentLinkIndex + 1) % this.links.length;
        this.highlightCurrentLink();
    }
    
    previousLink() {
        if (this.links.length === 0) return;
        
        this.clearLinkHighlights();
        this.currentLinkIndex = this.currentLinkIndex <= 0 
            ? this.links.length - 1 
            : this.currentLinkIndex - 1;
        this.highlightCurrentLink();
    }
    
    followCurrentLink() {
        if (this.currentLinkIndex >= 0 && this.links[this.currentLinkIndex]) {
            this.links[this.currentLinkIndex].click();
        }
    }
    
    highlightCurrentLink() {
        if (this.currentLinkIndex >= 0 && this.links[this.currentLinkIndex]) {
            const link = this.links[this.currentLinkIndex];
            link.classList.add('keyboard-focus');
            link.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    }
    
    clearLinkHighlights() {
        this.links.forEach(link => link.classList.remove('keyboard-focus'));
    }
    
    // Page actions
    printPage() {
        window.print();
    }
    
    exportPDF() {
        const currentParams = new URLSearchParams(window.location.search);
        currentParams.set('export', 'pdf');
        currentParams.set('type', 'page');
        window.location.href = '?' + currentParams.toString();
    }
    
    exportSectionPDF() {
        const currentParams = new URLSearchParams(window.location.search);
        currentParams.set('export', 'pdf');
        currentParams.set('type', 'section');
        window.location.href = '?' + currentParams.toString();
    }
    
    // Table of Contents
    toggleTOC() {
        const toc = document.querySelector('.right-sidebar');
        if (toc) {
            toc.style.display = toc.style.display === 'none' ? '' : 'none';
        }
    }
    
    focusTOC() {
        const firstTocLink = document.querySelector('.table-of-contents a');
        if (firstTocLink) {
            firstTocLink.focus();
        }
    }
    
    // Mobile menu
    toggleMobileMenu() {
        const sidebar = document.querySelector('.left-sidebar');
        if (sidebar) {
            sidebar.classList.toggle('mobile-open');
        }
    }
    
    closeMobileMenu() {
        const sidebar = document.querySelector('.left-sidebar');
        if (sidebar) {
            sidebar.classList.remove('mobile-open');
        }
    }
    
    // Help system
    showHelp() {
        if (this.helpVisible) {
            this.hideHelp();
            return;
        }
        
        const helpModal = this.createHelpModal();
        document.body.appendChild(helpModal);
        this.helpVisible = true;
        
        // Focus the modal for keyboard navigation
        helpModal.focus();
    }
    
    hideHelp() {
        const existingModal = document.querySelector('.keyboard-help-modal');
        if (existingModal) {
            existingModal.remove();
            this.helpVisible = false;
        }
    }
    
    createHelpModal() {
        const modal = document.createElement('div');
        modal.className = 'keyboard-help-modal';
        modal.tabIndex = -1;
        
        modal.innerHTML = `
            <div class="help-content">
                <div class="help-header">
                    <h3>Keyboard Shortcuts</h3>
                    <button class="help-close" onclick="wikiShortcuts.hideHelp()">&times;</button>
                </div>
                <div class="help-body">
                    <div class="help-section">
                        <h4>Navigation</h4>
                        <div class="shortcut-list">
                            <div><kbd>/</kbd> Focus search</div>
                            <div><kbd>g</kbd> <kbd>h</kbd> Go to home</div>
                            <div><kbd>j</kbd> Next link</div>
                            <div><kbd>k</kbd> Previous link</div>
                            <div><kbd>Enter</kbd> Follow highlighted link</div>
                        </div>
                    </div>
                    <div class="help-section">
                        <h4>Page Actions</h4>
                        <div class="shortcut-list">
                            <div><kbd>p</kbd> Print page</div>
                            <div><kbd>e</kbd> Export page to PDF</div>
                            <div><kbd>E</kbd> Export section to PDF</div>
                            <div><kbd>t</kbd> Toggle table of contents</div>
                        </div>
                    </div>
                    <div class="help-section">
                        <h4>General</h4>
                        <div class="shortcut-list">
                            <div><kbd>m</kbd> Toggle mobile menu</div>
                            <div><kbd>Esc</kbd> Close dialogs/unfocus</div>
                            <div><kbd>?</kbd> Show this help</div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Close on background click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                this.hideHelp();
            }
        });
        
        // Close on Escape
        modal.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.hideHelp();
            }
        });
        
        return modal;
    }
    
    // Utility functions
    isInputFocused() {
        const active = document.activeElement;
        return active && (
            active.tagName === 'INPUT' || 
            active.tagName === 'TEXTAREA' || 
            active.contentEditable === 'true'
        );
    }
    
    addKeyboardIndicators() {
        // Add CSS for keyboard navigation
        const style = document.createElement('style');
        style.textContent = `
            /* Keyboard navigation highlights */
            .keyboard-focus {
                outline: 2px solid var(--primary-color) !important;
                outline-offset: 2px;
                background-color: rgba(37, 99, 235, 0.1) !important;
                border-radius: 4px;
            }
            
            /* Help modal styles */
            .keyboard-help-modal {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 1000;
                backdrop-filter: blur(4px);
            }
            
            .help-content {
                background: white;
                border-radius: 8px;
                max-width: 600px;
                width: 90%;
                max-height: 80vh;
                overflow-y: auto;
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            }
            
            .help-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 20px 24px 0;
                border-bottom: 1px solid var(--border-color);
                margin-bottom: 20px;
            }
            
            .help-header h3 {
                margin: 0;
                color: var(--text-primary);
            }
            
            .help-close {
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                color: var(--text-muted);
                padding: 0;
                width: 30px;
                height: 30px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 4px;
            }
            
            .help-close:hover {
                background: var(--background-accent);
                color: var(--text-primary);
            }
            
            .help-body {
                padding: 0 24px 24px;
            }
            
            .help-section {
                margin-bottom: 24px;
            }
            
            .help-section h4 {
                margin: 0 0 12px 0;
                color: var(--text-primary);
                font-size: 14px;
                font-weight: 600;
            }
            
            .shortcut-list {
                display: grid;
                gap: 8px;
            }
            
            .shortcut-list div {
                display: flex;
                align-items: center;
                font-size: 14px;
                color: var(--text-secondary);
            }
            
            kbd {
                background: var(--background-accent);
                border: 1px solid var(--border-color);
                border-radius: 4px;
                padding: 2px 6px;
                font-family: var(--font-mono);
                font-size: 12px;
                margin-right: 8px;
                color: var(--text-primary);
            }
            
            /* PDF export buttons */
            .export-actions {
                margin-top: 16px;
                padding-top: 16px;
                border-top: 1px solid var(--border-light);
            }
            
            .export-button {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 6px 12px;
                background: var(--primary-color);
                color: white;
                text-decoration: none;
                border-radius: 4px;
                font-size: 13px;
                margin-right: 8px;
                transition: background-color 0.2s ease;
            }
            
            .export-button:hover {
                background: var(--primary-hover);
                color: white;
                border-bottom: none;
            }
            
            .export-button.secondary {
                background: var(--text-muted);
            }
            
            .export-button.secondary:hover {
                background: var(--text-secondary);
            }
        `;
        document.head.appendChild(style);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.wikiShortcuts = new WikiKeyboardShortcuts();
});