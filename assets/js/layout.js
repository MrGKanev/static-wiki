/**
 * Layout JavaScript functionality
 * Handles mobile menu, navigation, and page interactions
 */

// Mobile menu functionality
function toggleMobileMenu() {
  const sidebar = document.querySelector('.left-sidebar');
  sidebar.classList.toggle('mobile-open');
}

// Initialize layout functionality when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
  initializeLayout();
});

function initializeLayout() {
  initializeMobileMenu();
  initializeTableOfContents();
  initializePageChangeDetection();
}

/**
 * Mobile Menu Initialization
 */
function initializeMobileMenu() {
  // Close mobile menu when clicking outside
  document.addEventListener('click', function(event) {
    const sidebar = document.querySelector('.left-sidebar');
    const isClickInsideSidebar = sidebar.contains(event.target);
    const isMenuToggle = event.target.closest('.mobile-menu-toggle');

    if (window.innerWidth <= 768 && !isClickInsideSidebar && !isMenuToggle && sidebar.classList.contains('mobile-open')) {
      sidebar.classList.remove('mobile-open');
    }
  });

  // Handle window resize
  window.addEventListener('resize', function() {
    const sidebar = document.querySelector('.left-sidebar');
    if (window.innerWidth > 768) {
      sidebar.classList.remove('mobile-open');
    }
  });
}

/**
 * Table of Contents Functionality
 */
function initializeTableOfContents() {
  // Smooth scrolling for table of contents links
  document.querySelectorAll('.table-of-contents a').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
      e.preventDefault();
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        target.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });
      }
    });
  });

  // Update TOC highlight on scroll
  window.addEventListener('scroll', updateTocHighlight);
  
  // Initial highlight
  updateTocHighlight();
}

/**
 * Highlight current section in table of contents
 */
function updateTocHighlight() {
  const headings = document.querySelectorAll('h1, h2, h3, h4, h5, h6');
  const tocLinks = document.querySelectorAll('.table-of-contents a');

  let current = '';
  headings.forEach(heading => {
    const rect = heading.getBoundingClientRect();
    if (rect.top <= 100) {
      current = '#' + heading.id;
    }
  });

  tocLinks.forEach(link => {
    link.classList.remove('active');
    if (link.getAttribute('href') === current) {
      link.classList.add('active');
    }
  });
}

/**
 * Page Change Detection
 * Updates navigation and search functionality when page changes
 */
function initializePageChangeDetection() {
  let lastUrl = location.href;
  
  new MutationObserver(() => {
    const url = location.href;
    if (url !== lastUrl) {
      lastUrl = url;
      console.log('Page changed to:', url);

      // Update keyboard shortcuts navigation links
      if (window.wikiShortcuts) {
        window.wikiShortcuts.updateNavigableLinks();
      }

      // Update live search current page content
      if (window.wikiLiveSearch) {
        window.wikiLiveSearch.getCurrentPageContent();
      }
    }
  }).observe(document, {
    subtree: true,
    childList: true
  });
}

/**
 * Utility Functions
 */

// Export functions for global access
window.toggleMobileMenu = toggleMobileMenu;
window.updateTocHighlight = updateTocHighlight;