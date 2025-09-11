/**
 * Theme Toggle JavaScript - Dark/Light Mode Functionality
 */

// Theme management
class ThemeManager {
    constructor() {
        this.themes = ['light', 'dark', 'system'];
        this.currentThemeIndex = 0;
        this.init();
    }

    init() {
        // Get saved theme or default to system
        const savedTheme = localStorage.getItem('wiki-theme') || 'system';
        this.setTheme(savedTheme);
        this.updateUI();
    }

    setTheme(theme) {
        // Prevent transitions during theme switch
        document.documentElement.classList.add('theme-switching');
        
        // Apply theme
        document.documentElement.setAttribute('data-theme', theme);
        
        // Update current index
        this.currentThemeIndex = this.themes.indexOf(theme);
        
        // Save to localStorage
        localStorage.setItem('wiki-theme', theme);
        
        // Re-enable transitions after a brief delay
        setTimeout(() => {
            document.documentElement.classList.remove('theme-switching');
        }, 100);
    }

    cycleTheme() {
        this.currentThemeIndex = (this.currentThemeIndex + 1) % this.themes.length;
        const newTheme = this.themes[this.currentThemeIndex];
        this.setTheme(newTheme);
        this.updateUI();
    }

    getCurrentTheme() {
        return this.themes[this.currentThemeIndex];
    }

    getThemeDisplayName(theme) {
        const names = {
            'light': 'Light',
            'dark': 'Dark', 
            'system': 'System'
        };
        return names[theme] || theme;
    }

    getThemeIcon(theme) {
        const icons = {
            'light': `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="5"/>
                <line x1="12" y1="1" x2="12" y2="3"/>
                <line x1="12" y1="21" x2="12" y2="23"/>
                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                <line x1="1" y1="12" x2="3" y2="12"/>
                <line x1="21" y1="12" x2="23" y2="12"/>
                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
            </svg>`,
            'dark': `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
            </svg>`,
            'system': `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                <line x1="8" y1="21" x2="16" y2="21"/>
                <line x1="12" y1="17" x2="12" y2="21"/>
            </svg>`
        };
        return icons[theme] || icons['system'];
    }

    updateUI() {
        const button = document.querySelector('.theme-toggle-button');
        if (!button) return;

        const currentTheme = this.getCurrentTheme();
        const iconContainer = button.querySelector('.theme-icon');
        const textElement = button.querySelector('.theme-text');
        
        if (iconContainer) {
            iconContainer.innerHTML = this.getThemeIcon(currentTheme);
        }
        
        if (textElement) {
            textElement.textContent = this.getThemeDisplayName(currentTheme);
        }

        // Update button title for accessibility
        button.title = `Current theme: ${this.getThemeDisplayName(currentTheme)}. Click to cycle themes.`;
    }
}

// Initialize theme manager
let themeManager;

document.addEventListener('DOMContentLoaded', function() {
    themeManager = new ThemeManager();
});

// Global function for theme toggle button
function toggleTheme() {
    if (themeManager) {
        themeManager.cycleTheme();
    }
}

// Keyboard shortcut for theme toggle (Shift+T)
document.addEventListener('keydown', function(e) {
    if (e.shiftKey && e.key === 'T') {
        e.preventDefault();
        toggleTheme();
    }
});

// Export for global access
window.toggleTheme = toggleTheme;
window.themeManager = themeManager;