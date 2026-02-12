/**
 * E-Scooter Laws Map
 *
 * Interactive US map showing e-scooter legislation by state.
 * PHP renders state detail cards for SEO; this module adds
 * map interaction, search, ToC, and visual enhancements.
 */

// Classification → display mapping
const CLASSIFICATION_MAP = {
    specific_escooter: { cssClass: 'state-legal',      label: 'Legal',              icon: 'check-circle',  iconColor: 'icon-green' },
    local_rule:        { cssClass: 'state-conditional', label: 'Varies by Location', icon: 'interrogation', iconColor: 'icon-orange' },
    unclear_or_local:  { cssClass: 'state-conditional', label: 'Unclear / Local',    icon: 'interrogation', iconColor: 'icon-orange' },
    prohibited:        { cssClass: 'state-prohibited',  label: 'Prohibited',         icon: 'cross-circle',  iconColor: 'icon-red' },
};

/**
 * Inline SVG icon reference from the page sprite.
 */
function svgRef(name, cls = '') {
    return `<svg class="${cls}" aria-hidden="true"><use xlink:href="#${name}"></use></svg>`;
}

/**
 * Pretty-print an enum value (snake_case → Title Case).
 */
function enumLabel(value) {
    if (value == null || value === '') return 'N/A';
    return value.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

// ─────────────────────────────────────────────────────────────
//  Main init — called by calculator dispatcher
// ─────────────────────────────────────────────────────────────

export function init(container) {
    const lawsData = window.erhData?.lawsData;
    if (!lawsData || !Object.keys(lawsData).length) return;

    // ── DOM references ──

    const mapSvg           = container.querySelector('#us-map');
    const mapContainer     = container.querySelector('#map-container');
    const infoBox          = container.querySelector('#info-box');
    const searchInput      = container.querySelector('#state-search');
    const suggestionsBox   = container.querySelector('#suggestions-box');
    const desktopTocList   = container.querySelector('#desktop-toc-list');
    const mobileTocList    = container.querySelector('#mobile-toc-list');
    const backToTopBtn     = container.querySelector('#back-to-top-btn');
    const tocMobileBtn     = container.querySelector('#toc-mobile-btn');
    const mobileTocPopup   = container.querySelector('#mobile-toc-popup');
    const closeMobileTocBtn = container.querySelector('.close-mobile-toc');

    let activeStateId      = null;
    let activeSuggestionIdx = -1;

    // ── Map Coloring ──

    function applyStateColors() {
        if (!mapSvg) return;
        for (const [id, data] of Object.entries(lawsData)) {
            const el = mapSvg.querySelector(`#${id}`);
            if (!el) continue;
            const config = CLASSIFICATION_MAP[data.classification];
            if (config) el.classList.add(config.cssClass);
        }
    }

    function addMapTooltips() {
        if (!mapSvg) return;
        for (const [id, data] of Object.entries(lawsData)) {
            const el = mapSvg.querySelector(`#${id}`);
            if (!el) continue;
            const title = document.createElementNS('http://www.w3.org/2000/svg', 'title');
            title.textContent = `${data.name}: ${CLASSIFICATION_MAP[data.classification]?.label || 'No data'}`;
            el.prepend(title);
        }
    }

    // ── Info Box ──

    function showInfoBox(stateId) {
        const data = lawsData[stateId];
        if (!data || !infoBox) return;

        // Highlight active state on map
        if (activeStateId) {
            mapSvg?.querySelector(`#${activeStateId}`)?.classList.remove('active-state');
        }
        mapSvg?.querySelector(`#${stateId}`)?.classList.add('active-state');
        activeStateId = stateId;

        const config = CLASSIFICATION_MAP[data.classification] || {};
        const icon = (name) => `<svg class="info-icon" aria-hidden="true"><use xlink:href="#${name}"></use></svg>`;

        const gridItems = [
            { ic: 'legal',            lbl: 'Status',    val: config.label || 'Unknown', si: config.icon, sc: config.iconColor },
            { ic: 'age-alt',          lbl: 'Min Age',   val: data.minAge != null ? `${data.minAge} yrs` : 'N/A' },
            { ic: 'tachometer-fast',  lbl: 'Max Speed', val: data.maxSpeedMph != null ? `${data.maxSpeedMph} MPH` : 'N/A' },
            { ic: 'motorcycle-helmet', lbl: 'Helmet',   val: enumLabel(data.helmetRequired) },
            { ic: 'walking',          lbl: 'Sidewalk',  val: enumLabel(data.sidewalkRiding) },
            { ic: 'road',             lbl: 'Street',    val: enumLabel(data.streetRiding) },
        ];

        infoBox.innerHTML = `
            <div class="info-header-row">
                <span class="state-name">${data.name}</span>
                <button class="close-info-box" aria-label="Close">&times;</button>
            </div>
            <div class="info-grid-container">
                ${gridItems.map(g => `
                    <div class="info-grid-item">
                        <span class="item-header">${icon(g.ic)} <span>${g.lbl}</span></span>
                        <span class="item-value">${g.si ? svgRef(g.si, `status-icon ${g.sc}`) : ''}${g.val}</span>
                    </div>
                `).join('')}
            </div>
            <div class="details-link-container">
                <a href="#${stateId}-details" class="details-link">
                    View full details <svg><use xlink:href="#chevron"></use></svg>
                </a>
            </div>
        `;

        positionInfoBox(stateId);
        infoBox.classList.add('visible');

        // Bind close / detail-link inside fresh info-box markup
        infoBox.querySelector('.close-info-box')?.addEventListener('click', closeInfoBox);
        infoBox.querySelector('.details-link')?.addEventListener('click', (e) => {
            e.preventDefault();
            closeInfoBox();
            container.querySelector(`#${stateId}-details`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }

    function positionInfoBox(stateId) {
        if (!infoBox || !mapSvg || !mapContainer) return;

        // Mobile: CSS handles fixed bottom sheet
        if (window.innerWidth <= 991) {
            infoBox.style.left = '';
            infoBox.style.top = '';
            infoBox.style.transform = '';
            return;
        }

        const stateEl = mapSvg.querySelector(`#${stateId}`);
        if (!stateEl) return;

        const cRect = mapContainer.getBoundingClientRect();
        const sRect = stateEl.getBoundingClientRect();

        let left = sRect.left + sRect.width / 2 - cRect.left;
        const top  = sRect.top - cRect.top;

        // Clamp so box stays within container
        const halfBox = 140; // half of 280px width
        left = Math.max(halfBox + 10, Math.min(left, cRect.width - halfBox - 10));

        infoBox.style.left = `${left}px`;
        infoBox.style.top = `${top}px`;
        infoBox.style.transform = 'translate(-50%, -100%)';
    }

    function closeInfoBox() {
        if (!infoBox) return;
        infoBox.classList.remove('visible');
        if (activeStateId) {
            mapSvg?.querySelector(`#${activeStateId}`)?.classList.remove('active-state');
            activeStateId = null;
        }
    }

    // ── Map Click ──

    function initMapClicks() {
        if (!mapSvg) return;
        mapSvg.addEventListener('click', (e) => {
            let target = e.target;
            while (target && target !== mapSvg) {
                if (target.id && lawsData[target.id]) {
                    showInfoBox(target.id);
                    return;
                }
                target = target.parentElement;
            }
        });
    }

    // ── Search Autocomplete ──

    const stateList = Object.entries(lawsData).map(([id, data]) => ({
        id,
        name: data.name,
        text: `${data.name} ${id}`.toLowerCase(),
    }));

    function initSearch() {
        if (!searchInput || !suggestionsBox) return;

        searchInput.addEventListener('input', onSearchInput);
        searchInput.addEventListener('keydown', onSearchKeydown);
        searchInput.addEventListener('focus', () => {
            if (searchInput.value.trim().length >= 1) onSearchInput();
        });
    }

    function onSearchInput() {
        const query = searchInput.value.trim().toLowerCase();
        activeSuggestionIdx = -1;

        if (query.length < 1) {
            hideSuggestions();
            return;
        }

        const matches = stateList.filter(s => s.text.includes(query)).slice(0, 8);

        if (!matches.length) {
            suggestionsBox.innerHTML = '<div class="suggestion-item no-results">No matches found</div>';
            suggestionsBox.style.display = 'block';
            return;
        }

        suggestionsBox.innerHTML = matches.map((s, i) => {
            const display = `${s.name} (${s.id})`;
            return `<div class="suggestion-item" data-index="${i}" data-state="${s.id}">${highlightMatch(display, query)}</div>`;
        }).join('');

        suggestionsBox.style.display = 'block';

        suggestionsBox.querySelectorAll('.suggestion-item[data-state]').forEach(item => {
            item.addEventListener('click', () => selectState(item.dataset.state));
        });
    }

    function onSearchKeydown(e) {
        const items = suggestionsBox.querySelectorAll('.suggestion-item[data-state]');
        if (!items.length) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeSuggestionIdx = Math.min(activeSuggestionIdx + 1, items.length - 1);
            updateSuggestionHighlight(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeSuggestionIdx = Math.max(activeSuggestionIdx - 1, 0);
            updateSuggestionHighlight(items);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (activeSuggestionIdx >= 0 && items[activeSuggestionIdx]) {
                selectState(items[activeSuggestionIdx].dataset.state);
            }
        } else if (e.key === 'Escape') {
            hideSuggestions();
            searchInput.blur();
        }
    }

    function highlightMatch(text, query) {
        const idx = text.toLowerCase().indexOf(query);
        if (idx === -1) return text;
        return text.slice(0, idx) + '<strong>' + text.slice(idx, idx + query.length) + '</strong>' + text.slice(idx + query.length);
    }

    function updateSuggestionHighlight(items) {
        items.forEach((item, i) => {
            item.classList.toggle('suggestion-highlight', i === activeSuggestionIdx);
        });
        items[activeSuggestionIdx]?.scrollIntoView({ block: 'nearest' });
    }

    function selectState(stateId) {
        hideSuggestions();
        searchInput.value = lawsData[stateId]?.name || '';
        showInfoBox(stateId);
        setTimeout(() => {
            container.querySelector(`#${stateId}-details`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 300);
    }

    function hideSuggestions() {
        if (!suggestionsBox) return;
        suggestionsBox.style.display = '';
        suggestionsBox.innerHTML = '';
        activeSuggestionIdx = -1;
    }

    // ── Table of Contents (PHP-rendered, JS binds events) ──

    function initTocLinks() {
        [desktopTocList, mobileTocList].forEach(list => {
            if (!list) return;
            list.addEventListener('click', (e) => {
                const link = e.target.closest('.toc-link');
                if (!link) return;
                e.preventDefault();
                closeMobileToC();
                container.querySelector(link.getAttribute('href'))?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
    }

    // ── Intersection Observer (ToC highlight) ──

    function setupScrollObserver() {
        if (!desktopTocList) return;

        const sections = container.querySelectorAll('#details-container section[id$="-details"]');
        if (!sections.length) return;

        const observer = new IntersectionObserver((entries) => {
            for (const entry of entries) {
                if (!entry.isIntersecting) continue;
                const stateId = entry.target.id.replace('-details', '');

                desktopTocList.querySelectorAll('.toc-link').forEach(link => {
                    link.classList.toggle('active-toc-link', link.dataset.state === stateId);
                });

                desktopTocList.querySelector('.active-toc-link')?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            }
        }, { rootMargin: '-20% 0px -70% 0px' });

        sections.forEach(s => observer.observe(s));
    }

    // ── Floating Buttons ──

    function initFloatingButtons() {
        if (backToTopBtn) {
            window.addEventListener('scroll', () => {
                backToTopBtn.classList.toggle('visible', window.scrollY > 600);
            }, { passive: true });

            backToTopBtn.addEventListener('click', () => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        }

        if (tocMobileBtn && mobileTocPopup) {
            tocMobileBtn.addEventListener('click', () => {
                mobileTocPopup.classList.toggle('visible');
            });
        }

        if (closeMobileTocBtn) {
            closeMobileTocBtn.addEventListener('click', closeMobileToC);
        }
    }

    function closeMobileToC() {
        mobileTocPopup?.classList.remove('visible');
    }

    // ── Outside-Click Dismissal ──

    function initOutsideClicks() {
        document.addEventListener('click', (e) => {
            // Info box
            if (infoBox?.classList.contains('visible') &&
                !infoBox.contains(e.target) &&
                !e.target.closest('#us-map')) {
                closeInfoBox();
            }

            // Suggestions
            if (suggestionsBox?.style.display === 'block' &&
                !suggestionsBox.contains(e.target) &&
                e.target !== searchInput) {
                hideSuggestions();
            }

            // Mobile ToC
            if (mobileTocPopup?.classList.contains('visible') &&
                !mobileTocPopup.contains(e.target) &&
                e.target !== tocMobileBtn &&
                !tocMobileBtn?.contains(e.target)) {
                closeMobileToC();
            }
        });
    }

    // ── Resize handler (reposition info box) ──

    window.addEventListener('resize', () => {
        if (activeStateId && infoBox?.classList.contains('visible')) {
            positionInfoBox(activeStateId);
        }
    }, { passive: true });

    // ── Initialize ──

    applyStateColors();
    addMapTooltips();
    initMapClicks();
    initSearch();
    initTocLinks();
    setupScrollObserver();
    initFloatingButtons();
    initOutsideClicks();

    // Enable hover transitions now that colors are applied (prevents grey→color fade)
    requestAnimationFrame(() => container.classList.add('js-ready'));
}
