/**
 * Navigation JavaScript - Collapsible Categories
 * Handles expand/collapse functionality for navigation categories
 */

// Initialize navigation functionality when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
  initializeNavigation();
});

/**
 * Initialize navigation functionality
 */
function initializeNavigation() {
  // Add keyboard support for categories
  document.querySelectorAll('.nav-category').forEach(category => {
    category.addEventListener('keydown', function(e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        toggleCategory(this);
      }
    });
  });

  // Store navigation state in localStorage
  restoreNavigationState();
}

/**
 * Toggle category expand/collapse state
 * @param {HTMLElement} categoryElement - The category element clicked
 */
function toggleCategory(categoryElement) {
  const isCollapsed = categoryElement.classList.contains('collapsed');
  const categoryName = categoryElement.getAttribute('data-category');
  
  // Find the associated children container
  const childrenContainer = categoryElement.nextElementSibling;
  
  if (childrenContainer && childrenContainer.classList.contains('nav-children')) {
    if (isCollapsed) {
      // Expand
      categoryElement.classList.remove('collapsed');
      childrenContainer.classList.remove('collapsed');
      categoryElement.setAttribute('aria-expanded', 'true');
      
      // Store expanded state
      storeNavigationState(categoryName, true);
    } else {
      // Collapse
      categoryElement.classList.add('collapsed');
      childrenContainer.classList.add('collapsed');
      categoryElement.setAttribute('aria-expanded', 'false');
      
      // Store collapsed state
      storeNavigationState(categoryName, false);
    }
  }
}

/**
 * Store navigation state in localStorage
 * @param {string} categoryName - Name of the category
 * @param {boolean} isExpanded - Whether category is expanded
 */
function storeNavigationState(categoryName, isExpanded) {
  try {
    const navState = JSON.parse(localStorage.getItem('wikiNavState') || '{}');
    navState[categoryName] = isExpanded;
    localStorage.setItem('wikiNavState', JSON.stringify(navState));
  } catch (error) {
    // Fail silently if localStorage is not available
    console.warn('Could not save navigation state:', error);
  }
}

/**
 * Restore navigation state from localStorage
 */
function restoreNavigationState() {
  try {
    const navState = JSON.parse(localStorage.getItem('wikiNavState') || '{}');
    
    Object.keys(navState).forEach(categoryName => {
      const isExpanded = navState[categoryName];
      const categoryElement = document.querySelector(`[data-category="${categoryName}"]`);
      
      if (categoryElement) {
        const childrenContainer = categoryElement.nextElementSibling;
        
        if (childrenContainer && childrenContainer.classList.contains('nav-children')) {
          if (isExpanded) {
            categoryElement.classList.remove('collapsed');
            childrenContainer.classList.remove('collapsed');
            categoryElement.setAttribute('aria-expanded', 'true');
          } else {
            categoryElement.classList.add('collapsed');
            childrenContainer.classList.add('collapsed');
            categoryElement.setAttribute('aria-expanded', 'false');
          }
        }
      }
    });
  } catch (error) {
    // Fail silently if localStorage is not available
    console.warn('Could not restore navigation state:', error);
  }
}

/**
 * Expand all categories (useful for debugging or user preference)
 */
function expandAllCategories() {
  document.querySelectorAll('.nav-category.collapsed').forEach(category => {
    toggleCategory(category);
  });
}

/**
 * Collapse all categories (useful for cleaning up navigation)
 */
function collapseAllCategories() {
  document.querySelectorAll('.nav-category:not(.collapsed)').forEach(category => {
    // Don't collapse if it contains the current page
    if (!category.classList.contains('active')) {
      toggleCategory(category);
    }
  });
}

/**
 * Highlight current page in navigation
 * Called when page changes (for SPA-like behavior)
 */
function updateNavigationHighlight(currentPath) {
  // Remove all active states
  document.querySelectorAll('.nav-link.active, .nav-category.active, .nav-item.current-page')
    .forEach(element => {
      element.classList.remove('active', 'current-page');
    });

  // Find and highlight current page
  const currentLink = document.querySelector(`[href="?page=${encodeURIComponent(currentPath)}"]`);
  if (currentLink) {
    currentLink.classList.add('active');
    currentLink.closest('.nav-item').classList.add('current-page');
    
    // Expand parent categories
    let parent = currentLink.closest('.nav-children');
    while (parent) {
      const parentCategory = parent.previousElementSibling;
      if (parentCategory && parentCategory.classList.contains('nav-category')) {
        parentCategory.classList.add('active');
        parentCategory.classList.remove('collapsed');
        parent.classList.remove('collapsed');
        parentCategory.setAttribute('aria-expanded', 'true');
      }
      parent = parent.closest('.nav-children')?.parentElement?.closest('.nav-children');
    }
  }
}

/**
 * Search functionality for navigation
 * @param {string} searchTerm - Term to search for in navigation
 */
function searchNavigation(searchTerm) {
  const term = searchTerm.toLowerCase();
  const allNavItems = document.querySelectorAll('.nav-link, .nav-category');
  
  if (!term) {
    // Show all items
    allNavItems.forEach(item => {
      item.style.display = '';
      item.classList.remove('search-highlight');
    });
    return;
  }
  
  allNavItems.forEach(item => {
    const text = item.textContent.toLowerCase();
    if (text.includes(term)) {
      item.style.display = '';
      item.classList.add('search-highlight');
      
      // Expand parent categories for matching items
      const parent = item.closest('.nav-children');
      if (parent) {
        const parentCategory = parent.previousElementSibling;
        if (parentCategory && parentCategory.classList.contains('nav-category')) {
          parentCategory.classList.remove('collapsed');
          parent.classList.remove('collapsed');
        }
      }
    } else {
      item.style.display = 'none';
      item.classList.remove('search-highlight');
    }
  });
}

// Export functions for global access
window.toggleCategory = toggleCategory;
window.expandAllCategories = expandAllCategories;
window.collapseAllCategories = collapseAllCategories;
window.updateNavigationHighlight = updateNavigationHighlight;
window.searchNavigation = searchNavigation;