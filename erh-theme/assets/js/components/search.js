/**
 * Search Component
 * Handles search toggle, dropdown, live search, and keyboard navigation
 */

import { search, getTypeInfo, loadSearchData } from '../services/search-client.js';
import { DEBOUNCE_MS, escapeHtml, escapeRegex } from '../utils/search-utils.js';

const MAX_RESULTS = 6;

export function initSearch() {
    const searchToggle = document.querySelector('.search-toggle');
    const searchDropdown = document.getElementById('search-dropdown');
    const searchInput = searchDropdown?.querySelector('.search-input');
    const searchClose = searchDropdown?.querySelector('.search-close');
    const inlineSearch = document.querySelector('.search-inline');
    const inlineInput = inlineSearch?.querySelector('.search-inline-input');
    const searchSuggestions = searchDropdown?.querySelector('.search-suggestions');
    const searchResultsContainer = searchDropdown?.querySelector('.search-results-container');

    if (!searchToggle || !searchDropdown) return null;

    let isOpen = false;
    let debounceTimer = null;
    let currentResults = [];
    let selectedIndex = -1;
    let dataPreloaded = false;

    // Preload search data on first interaction (not page load)
    function preloadOnce() {
        if (!dataPreloaded) {
            dataPreloaded = true;
            loadSearchData();
        }
    }

    function open(focusInput = true) {
        isOpen = true;
        preloadOnce();
        searchToggle.setAttribute('aria-expanded', 'true');
        searchDropdown.classList.add('active');

        if (focusInput) {
            setTimeout(() => {
                if (searchInput) searchInput.focus();
            }, 100);
        }
    }

    function close() {
        isOpen = false;
        searchToggle.setAttribute('aria-expanded', 'false');
        searchDropdown.classList.remove('active');
        clearResults();
    }

    function toggle() {
        if (isOpen) {
            close();
            searchToggle.focus();
        } else {
            open();
        }
    }

    function focusInlineSearch() {
        if (inlineSearch && getComputedStyle(inlineSearch).display !== 'none') {
            if (inlineInput) inlineInput.focus();
            return true;
        }
        return false;
    }

    function clearResults() {
        if (searchResultsContainer) {
            searchResultsContainer.innerHTML = '';
            searchResultsContainer.classList.remove('active');
        }
        if (searchSuggestions) {
            searchSuggestions.style.display = '';
        }
        currentResults = [];
        selectedIndex = -1;
    }

    function showLoading() {
        if (!searchResultsContainer) return;
        searchResultsContainer.innerHTML = `
            <div class="search-loading">
                <span class="search-loading-spinner"></span>
                <span>Searching...</span>
            </div>
        `;
        searchResultsContainer.classList.add('active');
        if (searchSuggestions) searchSuggestions.style.display = 'none';
    }

    function showNoResults(query) {
        if (!searchResultsContainer) return;
        searchResultsContainer.innerHTML = `
            <div class="search-no-results">
                No results for "${escapeHtml(query)}"
            </div>
        `;
        searchResultsContainer.classList.add('active');
        if (searchSuggestions) searchSuggestions.style.display = 'none';
    }

    function renderResults(results, query) {
        if (!searchResultsContainer) return;

        currentResults = results.slice(0, MAX_RESULTS);
        const totalCount = results.length;

        let html = '<div class="search-results">';

        currentResults.forEach((item, index) => {
            const typeInfo = getTypeInfo(item);
            const isProduct = item.type === 'Product';
            const thumbWrapClass = isProduct ? 'search-result-thumb-wrap search-result-thumb-wrap--product' : 'search-result-thumb-wrap';
            html += `
                <a class="search-result" href="${escapeHtml(item.url)}" data-index="${index}">
                    <div class="${thumbWrapClass}">
                        <img class="search-result-thumb" src="${escapeHtml(item.thumbnail)}" alt="" loading="lazy">
                    </div>
                    <div class="search-result-content">
                        <span class="search-result-title">${highlightMatch(item.title, query)}</span>
                        <span class="search-result-meta">
                            <span class="search-result-type">${escapeHtml(typeInfo.label)}</span>
                        </span>
                    </div>
                </a>
            `;
        });

        // Add "View all results" link if there are more
        if (totalCount > MAX_RESULTS) {
            const siteUrl = window.erhData?.siteUrl || '';
            const searchPageUrl = `${siteUrl}/search/?q=${encodeURIComponent(query)}`;
            html += `
                <a class="search-results-footer btn btn-secondary btn-sm" href="${searchPageUrl}">
                    View all ${totalCount} results
                </a>
            `;
        }

        html += '</div>';

        searchResultsContainer.innerHTML = html;
        searchResultsContainer.classList.add('active');
        if (searchSuggestions) searchSuggestions.style.display = 'none';
        selectedIndex = -1;
    }

    function highlightMatch(text, query) {
        if (!query) return escapeHtml(text);
        const words = query.toLowerCase().split(/\s+/).filter(w => w.length > 0);
        let result = text;

        words.forEach(word => {
            const regex = new RegExp(`(${escapeRegex(word)})`, 'gi');
            result = result.replace(regex, '<mark>$1</mark>');
        });

        return result;
    }

    async function handleSearch(query) {
        if (!query || query.length < 2) {
            clearResults();
            return;
        }

        showLoading();

        try {
            const results = await search(query);
            if (results.length === 0) {
                showNoResults(query);
            } else {
                renderResults(results, query);
            }
        } catch (error) {
            console.error('[Search] Error:', error);
            showNoResults(query);
        }
    }

    function debounceSearch(query) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => handleSearch(query), DEBOUNCE_MS);
    }

    function updateSelection(newIndex) {
        const items = searchResultsContainer?.querySelectorAll('.search-result');
        if (!items || items.length === 0) return;

        // Remove previous selection
        items.forEach(item => item.classList.remove('selected'));

        // Clamp index
        if (newIndex < 0) newIndex = items.length - 1;
        if (newIndex >= items.length) newIndex = 0;

        selectedIndex = newIndex;
        items[selectedIndex]?.classList.add('selected');
        items[selectedIndex]?.scrollIntoView({ block: 'nearest' });
    }

    function handleKeyDown(e) {
        const hasResults = currentResults.length > 0;

        switch (e.key) {
            case 'ArrowDown':
                if (hasResults) {
                    e.preventDefault();
                    updateSelection(selectedIndex + 1);
                }
                break;
            case 'ArrowUp':
                if (hasResults) {
                    e.preventDefault();
                    updateSelection(selectedIndex - 1);
                }
                break;
            case 'Enter':
                if (selectedIndex >= 0 && currentResults[selectedIndex]) {
                    e.preventDefault();
                    window.location.href = currentResults[selectedIndex].url;
                } else if (e.target.value?.trim()) {
                    // Submit to search page
                    e.preventDefault();
                    const siteUrl = window.erhData?.siteUrl || '';
                    window.location.href = `${siteUrl}/search/?q=${encodeURIComponent(e.target.value.trim())}`;
                }
                break;
            case 'Escape':
                if (isOpen) {
                    e.preventDefault();
                    close();
                    searchToggle.focus();
                }
                break;
        }
    }

    // Event Listeners
    searchToggle.addEventListener('click', toggle);

    if (searchClose) {
        searchClose.addEventListener('click', () => {
            close();
            searchToggle.focus();
        });
    }

    // Wire up dropdown search input
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value;
            // Sync to inline input
            if (inlineInput) inlineInput.value = query;
            debounceSearch(query);
        });
        searchInput.addEventListener('keydown', handleKeyDown);
    }

    // Wire up inline search input (syncs with dropdown)
    if (inlineInput) {
        inlineInput.addEventListener('input', (e) => {
            const query = e.target.value;
            // Open dropdown and sync query (don't steal focus from inline)
            if (query.length >= 2 && !isOpen) {
                open(false);
                if (searchInput) searchInput.value = query;
            }
            debounceSearch(query);
        });
        inlineInput.addEventListener('keydown', handleKeyDown);
        inlineInput.addEventListener('focus', () => {
            preloadOnce();
            // When focusing inline, open dropdown if there's a query
            const query = inlineInput.value;
            if (query.length >= 2) {
                open(false);
                if (searchInput) searchInput.value = query;
                handleSearch(query);
            }
        });
    }

    // Close when clicking outside
    document.addEventListener('click', (e) => {
        if (isOpen &&
            !searchDropdown.contains(e.target) &&
            !searchToggle.contains(e.target) &&
            !inlineSearch?.contains(e.target)) {
            close();
        }
    });

    // Public API
    return {
        open,
        close,
        toggle,
        focusInlineSearch,
        isOpen: () => isOpen
    };
}
