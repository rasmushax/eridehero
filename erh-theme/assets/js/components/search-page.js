/**
 * Search Page Component
 * Full-page search with type filters matching archive style
 */

import { search, getTypeInfo, loadSearchData } from '../services/search-client.js';
import { DEBOUNCE_MS, escapeHtml } from '../utils/search-utils.js';

export function initSearchPage() {
    const page = document.querySelector('[data-search-page]');
    if (!page) return null;

    const input = page.querySelector('[data-search-input]');
    const clearBtn = page.querySelector('[data-search-clear]');
    const filtersContainer = page.querySelector('[data-search-filters]');
    const resultsContainer = page.querySelector('[data-search-results]');
    const emptyState = page.querySelector('[data-search-empty]');
    const loadingState = page.querySelector('[data-search-loading]');
    const emptyText = page.querySelector('[data-empty-text]');
    const filterBtns = page.querySelectorAll('[data-filter]');

    // Count elements
    const countAll = page.querySelector('[data-count-all]');
    const countProduct = page.querySelector('[data-count-product]');
    const countArticle = page.querySelector('[data-count-article]');
    const countTool = page.querySelector('[data-count-tool]');

    let debounceTimer = null;
    let allResults = [];
    let currentFilter = 'all';
    let dataPreloaded = false;

    // Preload search data on first interaction
    function preloadOnce() {
        if (!dataPreloaded) {
            dataPreloaded = true;
            loadSearchData();
        }
    }

    function showLoading() {
        loadingState?.removeAttribute('hidden');
        resultsContainer?.setAttribute('hidden', '');
        emptyState?.setAttribute('hidden', '');
        filtersContainer?.setAttribute('hidden', '');
    }

    function showEmpty(isNoResults = false, query = '') {
        loadingState?.setAttribute('hidden', '');
        resultsContainer?.setAttribute('hidden', '');
        emptyState?.removeAttribute('hidden');
        filtersContainer?.setAttribute('hidden', '');

        if (isNoResults && emptyText) {
            emptyText.textContent = `No results found for "${query}"`;
        } else if (emptyText) {
            emptyText.textContent = 'Start typing to search products, reviews, and guides.';
        }
    }

    function showResults() {
        loadingState?.setAttribute('hidden', '');
        emptyState?.setAttribute('hidden', '');
        resultsContainer?.removeAttribute('hidden');
        filtersContainer?.removeAttribute('hidden');
    }

    function updateFilterCounts(results) {
        const counts = { all: results.length, Product: 0, Article: 0, Tool: 0 };

        results.forEach(item => {
            if (counts[item.type] !== undefined) {
                counts[item.type]++;
            }
        });

        // Update count badges
        if (countAll) countAll.textContent = counts.all;
        if (countProduct) countProduct.textContent = counts.Product;
        if (countArticle) countArticle.textContent = counts.Article;
        if (countTool) countTool.textContent = counts.Tool;

        // Show/hide filter buttons based on counts
        filterBtns.forEach(btn => {
            const filter = btn.dataset.filter;
            if (filter === 'all') {
                btn.removeAttribute('hidden');
            } else if (counts[filter] > 0) {
                btn.removeAttribute('hidden');
            } else {
                btn.setAttribute('hidden', '');
            }
        });
    }

    function renderResults(results) {
        if (!resultsContainer) return;

        const filtered = currentFilter === 'all'
            ? results
            : results.filter(item => item.type === currentFilter);

        if (filtered.length === 0) {
            resultsContainer.innerHTML = '';
            return;
        }

        let html = '';

        filtered.forEach(item => {
            const typeInfo = getTypeInfo(item);
            const isProduct = item.type === 'Product';
            // Use larger image for cards, fallback to thumbnail
            const imageUrl = item.image || item.thumbnail;

            html += `
                <a class="archive-card" href="${escapeHtml(item.url)}">
                    <div class="archive-card-img${isProduct ? ' archive-card-img--product' : ''}">
                        <img src="${escapeHtml(imageUrl)}" alt="" loading="lazy">
                        <span class="archive-card-tag">${escapeHtml(typeInfo.label)}</span>
                    </div>
                    <h3 class="archive-card-title">${escapeHtml(item.title)}</h3>
                </a>
            `;
        });

        resultsContainer.innerHTML = html;
    }

    async function handleSearch(query) {
        // Update clear button visibility
        if (clearBtn) {
            clearBtn.hidden = !query;
        }

        // Update URL without reload
        const url = new URL(window.location);
        if (query) {
            url.searchParams.set('q', query);
        } else {
            url.searchParams.delete('q');
        }
        window.history.replaceState({}, '', url);

        if (!query || query.length < 2) {
            allResults = [];
            showEmpty(false);
            return;
        }

        showLoading();

        try {
            allResults = await search(query);
            if (allResults.length === 0) {
                showEmpty(true, query);
            } else {
                // Reset filter to all on new search
                currentFilter = 'all';
                filterBtns.forEach(btn => {
                    const isActive = btn.dataset.filter === 'all';
                    btn.classList.toggle('is-active', isActive);
                    btn.setAttribute('aria-pressed', isActive);
                });

                updateFilterCounts(allResults);
                showResults();
                renderResults(allResults);
            }
        } catch (error) {
            console.error('[SearchPage] Error:', error);
            showEmpty(true, query);
        }
    }

    function debounceSearch(query) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => handleSearch(query), DEBOUNCE_MS);
    }

    function setFilter(filter) {
        currentFilter = filter;
        filterBtns.forEach(btn => {
            const isActive = btn.dataset.filter === filter;
            btn.classList.toggle('is-active', isActive);
            btn.setAttribute('aria-pressed', isActive);
        });
        if (allResults.length > 0) {
            renderResults(allResults);
        }
    }

    // Event listeners
    if (input) {
        input.addEventListener('focus', preloadOnce);
        input.addEventListener('input', (e) => debounceSearch(e.target.value));
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                input.value = '';
                handleSearch('');
            }
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            if (input) {
                input.value = '';
                input.focus();
            }
            handleSearch('');
        });
    }

    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            setFilter(btn.dataset.filter);
        });
    });

    // Initial search from URL
    const initialQuery = window.erhData?.searchQuery || '';
    if (initialQuery) {
        preloadOnce();
        handleSearch(initialQuery);
    } else {
        showEmpty(false);
    }

    return {
        search: handleSearch,
        setFilter
    };
}
