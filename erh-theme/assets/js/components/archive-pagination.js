/**
 * Archive Pagination Component
 *
 * Client-side pagination for archive grids that load all posts.
 * Works with archive-filter.js — only paginates filter-visible cards.
 *
 * Hidden attribute strategy:
 *   - `hidden` attribute  = filtered out by category (set by SSR or filter JS)
 *   - `data-page-hidden`  = not on current page (set by this module)
 *   A card is visible only when it has neither.
 */

/**
 * Initialize pagination for an archive grid.
 *
 * @param {HTMLElement} grid - The [data-archive-grid] element
 * @param {{ managed: boolean }} options - If managed=true, popstate is handled externally
 * @returns {{ update: () => void, sync: () => void }} Pagination controller
 */
export function initPagination(grid, { managed = false } = {}) {
    const perPage = parseInt(grid.dataset.archivePaginate, 10) || 12;
    const container = document.querySelector('[data-archive-pagination]');
    if (!container) return { update() {}, sync() {} };

    let currentPage = getPageFromUrl();

    /**
     * Get all cards that pass the category filter (not hidden by filter).
     */
    function getFilteredCards() {
        return [...grid.querySelectorAll('[data-category]')].filter(
            card => !card.hasAttribute('hidden')
        );
    }

    /**
     * Show/hide cards for the current page and render nav.
     */
    function render() {
        const filtered = getFilteredCards();
        const totalPages = Math.ceil(filtered.length / perPage);

        // Clamp page to valid range.
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        // Show/hide cards based on page.
        filtered.forEach((card, i) => {
            const cardPage = Math.floor(i / perPage) + 1;
            if (cardPage === currentPage) {
                card.removeAttribute('data-page-hidden');
                card.style.display = '';
            } else {
                card.setAttribute('data-page-hidden', '');
                card.style.display = 'none';
            }
        });

        renderNav(totalPages);
    }

    /**
     * Render pagination nav HTML mirroring pagination.php structure.
     */
    function renderNav(totalPages) {
        if (totalPages <= 1) {
            container.innerHTML = '';
            return;
        }

        const range = 2;
        const chevronLeft = '<svg class="icon" aria-hidden="true"><use href="#icon-chevron-left"></use></svg>';
        const chevronRight = '<svg class="icon" aria-hidden="true"><use href="#icon-chevron-right"></use></svg>';

        const prevDisabled = currentPage <= 1 ? ' aria-disabled="true"' : '';
        const nextDisabled = currentPage >= totalPages ? ' aria-disabled="true"' : '';

        // Build page numbers with ellipsis.
        let pagesHtml = '';
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= currentPage - range && i <= currentPage + range)) {
                const isActive = i === currentPage;
                pagesHtml += `<button type="button" class="pagination-page${isActive ? ' is-active' : ''}" data-page="${i}"${isActive ? ' aria-current="page"' : ''}>${i}</button>`;
            } else if (i === currentPage - range - 1 || i === currentPage + range + 1) {
                pagesHtml += '<span class="pagination-ellipsis">...</span>';
            }
        }

        container.innerHTML = `
            <nav class="pagination" aria-label="Pagination">
                <button type="button" class="pagination-btn pagination-prev"${prevDisabled} data-page="${currentPage - 1}">
                    ${chevronLeft}
                    <span>Previous</span>
                </button>
                <div class="pagination-pages">
                    ${pagesHtml}
                </div>
                <button type="button" class="pagination-btn pagination-next"${nextDisabled} data-page="${currentPage + 1}">
                    <span>Next</span>
                    ${chevronRight}
                </button>
            </nav>`;

        // Bind click handlers.
        container.querySelectorAll('[data-page]').forEach(btn => {
            btn.addEventListener('click', () => {
                if (btn.getAttribute('aria-disabled') === 'true') return;
                const page = parseInt(btn.dataset.page, 10);
                if (page >= 1 && page <= totalPages) {
                    goToPage(page);
                }
            });
        });
    }

    /**
     * Navigate to a specific page.
     */
    function goToPage(page) {
        currentPage = page;
        render();
        updateUrl();
        scrollToGrid();
    }

    /**
     * Scroll to the grid top (with offset for sticky header).
     */
    function scrollToGrid() {
        const rect = grid.getBoundingClientRect();
        const offset = 100; // Account for sticky header.
        if (rect.top < 0) {
            window.scrollTo({
                top: window.scrollY + rect.top - offset,
                behavior: 'smooth',
            });
        }
    }

    /**
     * Update URL with current page param.
     */
    function updateUrl() {
        const url = new URL(window.location);
        if (currentPage > 1) {
            url.searchParams.set('page', currentPage);
        } else {
            url.searchParams.delete('page');
        }
        history.pushState(null, '', url);
    }

    /**
     * Read ?page= from current URL.
     */
    function getPageFromUrl() {
        const params = new URLSearchParams(window.location.search);
        return parseInt(params.get('page'), 10) || 1;
    }

    // Handle browser back/forward (only when not managed by filter module).
    if (!managed) {
        window.addEventListener('popstate', () => {
            currentPage = getPageFromUrl();
            render();
        });
    }

    // Initial render.
    render();

    return {
        /** Filter changed — reset to page 1. */
        update() {
            currentPage = 1;
            const url = new URL(window.location);
            url.searchParams.delete('page');
            history.replaceState(null, '', url);
            render();
        },
        /** Popstate — read page from URL (called by filter module). */
        sync() {
            currentPage = getPageFromUrl();
            render();
        },
    };
}
