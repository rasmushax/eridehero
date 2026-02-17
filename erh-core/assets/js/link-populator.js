/**
 * Link Populator - Admin JavaScript
 *
 * @package ERH\Admin
 */

(function() {
    'use strict';

    const config = window.erhLinkPopulator || {};
    const { ajaxUrl, adminUrl, nonce, amazonLocales, amazonConfigured, perplexityConfigured, i18n } = config;

    // State
    let currentMode = 'scraper'; // 'scraper' or 'amazon'
    let selectedScraper = null;
    let selectedLocale = 'US';
    let products = [];
    let results = [];

    // DOM Elements
    const modeRadios = document.querySelectorAll('input[name="erh-lp-mode"]');
    const scraperField = document.querySelector('.erh-lp-scraper-field');
    const amazonField = document.querySelector('.erh-lp-amazon-field');
    const scraperSelect = document.getElementById('erh-lp-scraper');
    const amazonLocaleSelect = document.getElementById('erh-lp-amazon-locale');
    const searchInput = document.getElementById('erh-lp-search');
    const brandFilter = document.getElementById('erh-lp-brand');
    const statusFilter = document.getElementById('erh-lp-status');
    const productsContainer = document.getElementById('erh-lp-products');
    const checkAllBtn = document.getElementById('erh-lp-check-all');
    const uncheckAllBtn = document.getElementById('erh-lp-uncheck-all');
    const findUrlsBtn = document.getElementById('erh-lp-find-urls');
    const addLinksBtn = document.getElementById('erh-lp-add-links');
    const resultsTable = document.getElementById('erh-lp-results');
    const checkAllResults = document.getElementById('erh-lp-check-all-results');

    /**
     * Initialize the module.
     */
    function init() {
        if (!scraperSelect) return;

        loadScrapers();
        bindEvents();
    }

    /**
     * Bind event listeners.
     */
    function bindEvents() {
        // Mode selector
        modeRadios.forEach(radio => {
            radio.addEventListener('change', onModeChange);
        });

        scraperSelect.addEventListener('change', onScraperChange);

        if (amazonLocaleSelect) {
            amazonLocaleSelect.addEventListener('change', onAmazonLocaleChange);
        }

        if (searchInput) {
            searchInput.addEventListener('input', debounce(filterProducts, 300));
        }
        if (brandFilter) {
            brandFilter.addEventListener('change', filterProducts);
        }
        if (statusFilter) {
            statusFilter.addEventListener('change', filterProducts);
        }
        if (checkAllBtn) {
            checkAllBtn.addEventListener('click', checkAllVisible);
        }
        if (uncheckAllBtn) {
            uncheckAllBtn.addEventListener('click', uncheckAll);
        }
        if (findUrlsBtn) {
            findUrlsBtn.addEventListener('click', findUrls);
        }
        if (addLinksBtn) {
            addLinksBtn.addEventListener('click', addLinks);
        }
        if (checkAllResults) {
            checkAllResults.addEventListener('change', toggleAllResults);
        }
    }

    /**
     * Handle mode change (Scraper vs Amazon).
     */
    function onModeChange(e) {
        currentMode = e.target.value;

        // Toggle visibility
        if (scraperField) {
            scraperField.style.display = currentMode === 'scraper' ? 'block' : 'none';
        }
        if (amazonField) {
            amazonField.style.display = currentMode === 'amazon' ? 'block' : 'none';
        }

        // Reset state
        selectedScraper = null;
        products = [];
        results = [];
        hideSteps([2, 3, 4]);

        // For Amazon, auto-load products with current locale (if configured)
        if (currentMode === 'amazon' && amazonConfigured && amazonLocaleSelect) {
            selectedLocale = amazonLocaleSelect.value || 'US';
            showStep(2);
            loadAmazonProducts(selectedLocale);
        }
    }

    /**
     * Handle Amazon locale change.
     */
    async function onAmazonLocaleChange() {
        selectedLocale = amazonLocaleSelect.value;
        hideSteps([3, 4]);
        await loadAmazonProducts(selectedLocale);
    }

    /**
     * Load scrapers from server.
     */
    async function loadScrapers() {
        try {
            const response = await ajax('erh_lp_get_scrapers');

            if (response.success && response.data.scrapers) {
                populateScraperDropdown(response.data.scrapers);
            } else {
                showError(response.data?.message || i18n.error);
            }
        } catch (error) {
            showError(error.message);
        }
    }

    /**
     * Populate the scraper dropdown.
     *
     * @param {Array} scrapers - List of scrapers.
     */
    function populateScraperDropdown(scrapers) {
        scraperSelect.innerHTML = '<option value="">' + i18n.loading.replace('...', '') + '-- Select Scraper --</option>';

        scrapers.forEach(scraper => {
            const option = document.createElement('option');
            option.value = scraper.id;
            option.textContent = scraper.label;
            option.dataset.domain = scraper.domain;
            option.dataset.currency = scraper.currency;
            option.dataset.geos = scraper.geos;
            scraperSelect.appendChild(option);
        });
    }

    /**
     * Handle scraper selection change.
     */
    async function onScraperChange() {
        const scraperId = scraperSelect.value;

        if (!scraperId) {
            hideSteps([2, 3, 4]);
            return;
        }

        const option = scraperSelect.selectedOptions[0];
        selectedScraper = {
            id: parseInt(scraperId, 10),
            domain: option.dataset.domain,
            currency: option.dataset.currency,
            geos: option.dataset.geos,
        };

        showStep(2);
        await loadProducts(scraperId);
    }

    /**
     * Load products for selected scraper.
     *
     * @param {number} scraperId - The scraper ID.
     */
    async function loadProducts(scraperId) {
        productsContainer.innerHTML = '<p class="erh-lp-loading">' + i18n.loading + '</p>';

        try {
            const response = await ajax('erh_lp_get_products', { scraper_id: scraperId });

            if (response.success) {
                products = response.data.products;
                populateBrandFilter(response.data.brands);
                renderProducts();
                showStep(3);
            } else {
                showError(response.data?.message || i18n.error);
            }
        } catch (error) {
            showError(error.message);
        }
    }

    /**
     * Load products for Amazon mode.
     *
     * @param {string} locale - The Amazon locale (US, GB, etc).
     */
    async function loadAmazonProducts(locale) {
        productsContainer.innerHTML = '<p class="erh-lp-loading">' + i18n.loading + '</p>';

        try {
            const response = await ajax('erh_lp_get_amazon_products', { locale: locale });

            if (response.success) {
                products = response.data.products;
                populateBrandFilter(response.data.brands);
                renderProducts();
                showStep(3);
            } else {
                showError(response.data?.message || i18n.error);
            }
        } catch (error) {
            showError(error.message);
        }
    }

    /**
     * Populate brand filter dropdown.
     *
     * @param {Array} brands - List of brand names.
     */
    function populateBrandFilter(brands) {
        brandFilter.innerHTML = '<option value="">All Brands</option>';

        brands.forEach(brand => {
            const option = document.createElement('option');
            option.value = brand;
            option.textContent = brand;
            brandFilter.appendChild(option);
        });
    }

    /**
     * Render products list.
     */
    function renderProducts() {
        const filtered = getFilteredProducts();

        if (filtered.length === 0) {
            productsContainer.innerHTML = '<p class="erh-lp-empty">No products match your filters.</p>';
            return;
        }

        const html = filtered.map(product => {
            const checked = product.checked ? 'checked' : '';
            const statusClass = product.has_link ? 'has-link' : 'no-link';

            // For Amazon mode, show existing ASIN; for scraper mode, show existing URL
            const existingValue = currentMode === 'amazon'
                ? product.existing_asin
                : product.existing_url;
            const existingTitle = existingValue
                ? `Current: ${existingValue}`
                : '';
            const statusLabel = product.has_link
                ? `<span class="erh-lp-overwrite-warning" title="${escapeHtml(existingTitle)}">&#9888; has link</span>`
                : '(no link)';
            const editUrl = `${adminUrl}post.php?post=${product.id}&action=edit`;

            return `
                <label class="erh-lp-product ${statusClass}" data-id="${product.id}">
                    <input type="checkbox" ${checked} data-id="${product.id}" data-link-id="${product.link_id || ''}">
                    <a href="${editUrl}" target="_blank" class="erh-lp-product-edit" title="Edit product" onclick="event.stopPropagation();">&#9998;</a>
                    <span class="erh-lp-product-name">${escapeHtml(product.name)}</span>
                    <span class="erh-lp-product-brand">${escapeHtml(product.brand)}</span>
                    <span class="erh-lp-product-status">${statusLabel}</span>
                </label>
            `;
        }).join('');

        productsContainer.innerHTML = html;

        // Bind checkbox events
        productsContainer.querySelectorAll('input[type="checkbox"]').forEach(cb => {
            cb.addEventListener('change', () => {
                const id = parseInt(cb.dataset.id, 10);
                const product = products.find(p => p.id === id);
                if (product) {
                    product.checked = cb.checked;
                }
                updateSelectedCount();
            });
        });

        updateSelectedCount();
        updateProductCount(filtered.length, products.length);
    }

    /**
     * Update product count display.
     */
    function updateProductCount(shown, total) {
        let countEl = document.querySelector('.erh-lp-product-count');
        if (!countEl) {
            countEl = document.createElement('span');
            countEl.className = 'erh-lp-product-count';
            document.querySelector('.erh-lp-filters')?.appendChild(countEl);
        }
        countEl.textContent = shown === total
            ? `${total} products`
            : `${shown} of ${total} products`;
    }

    /**
     * Get filtered products based on current filters.
     *
     * @returns {Array} Filtered products.
     */
    function getFilteredProducts() {
        const search = (searchInput?.value || '').toLowerCase();
        const brand = brandFilter?.value || '';
        const status = statusFilter?.value || 'no-link';

        return products.filter(product => {
            // Search filter
            if (search && !product.name.toLowerCase().includes(search)) {
                return false;
            }

            // Brand filter
            if (brand && product.brand !== brand) {
                return false;
            }

            // Status filter
            if (status === 'no-link' && product.has_link) {
                return false;
            }
            if (status === 'has-link' && !product.has_link) {
                return false;
            }

            return true;
        });
    }

    /**
     * Filter and re-render products.
     */
    function filterProducts() {
        renderProducts();
    }

    /**
     * Check all visible products.
     */
    function checkAllVisible() {
        const checkboxes = productsContainer.querySelectorAll('input[type="checkbox"]:not(:disabled)');
        checkboxes.forEach(cb => {
            cb.checked = true;
            const id = parseInt(cb.dataset.id, 10);
            const product = products.find(p => p.id === id);
            if (product) product.checked = true;
        });
        updateSelectedCount();
    }

    /**
     * Uncheck all products.
     */
    function uncheckAll() {
        products.forEach(p => p.checked = false);
        productsContainer.querySelectorAll('input[type="checkbox"]').forEach(cb => {
            cb.checked = false;
        });
        updateSelectedCount();
    }

    /**
     * Update selected count display.
     */
    function updateSelectedCount() {
        const count = products.filter(p => p.checked).length;
        const countEl = document.querySelector('.erh-lp-selected-count strong');
        if (countEl) {
            countEl.textContent = count;
        }

        // Enable/disable find URLs button
        if (findUrlsBtn) {
            findUrlsBtn.disabled = count === 0;
        }
    }

    /**
     * Find URLs for selected products.
     */
    async function findUrls() {
        if (currentMode === 'amazon') {
            await findAmazonUrls();
        } else {
            await findScraperUrls();
        }
    }

    /**
     * Find URLs using scraper domain.
     */
    async function findScraperUrls() {
        const selected = products.filter(p => p.checked);

        if (selected.length === 0) {
            alert(i18n.noProducts);
            return;
        }

        results = [];
        showStep(4);

        const progressBar = document.querySelector('.erh-lp-progress');
        const progressFill = document.querySelector('.erh-lp-progress-fill');
        const progressText = document.querySelector('.erh-lp-progress-text');

        progressBar.style.display = 'block';
        findUrlsBtn.disabled = true;

        for (let i = 0; i < selected.length; i++) {
            const product = selected[i];
            const progress = ((i + 1) / selected.length) * 100;

            progressFill.style.width = progress + '%';
            progressText.textContent = i18n.productOf
                .replace('%1$d', i + 1)
                .replace('%2$d', selected.length);

            try {
                const response = await ajax('erh_lp_find_urls', {
                    product_id: product.id,
                    product_name: product.name,
                    domain: selectedScraper.domain,
                });

                if (response.success) {
                    const result = response.data.result;

                    // Verify URL if found
                    let verification = null;
                    if (result.url) {
                        const verifyResponse = await ajax('erh_lp_verify_urls', { url: result.url });
                        if (verifyResponse.success) {
                            verification = verifyResponse.data.result;
                        }
                    }

                    results.push({
                        product_id: product.id,
                        product_name: product.name,
                        url: result.url,
                        error: result.error,
                        verification: verification,
                        link_id: product.link_id || null,
                        has_link: product.has_link,
                    });
                }
            } catch (error) {
                results.push({
                    product_id: product.id,
                    product_name: product.name,
                    url: null,
                    error: error.message,
                    verification: null,
                    link_id: product.link_id || null,
                    has_link: product.has_link,
                });
            }

            // Small delay between requests
            if (i < selected.length - 1) {
                await sleep(500);
            }
        }

        progressBar.style.display = 'none';
        findUrlsBtn.disabled = false;
        renderResults();
    }

    /**
     * Find ASINs for Amazon mode.
     */
    async function findAmazonUrls() {
        const selected = products.filter(p => p.checked);

        if (selected.length === 0) {
            alert(i18n.noProducts);
            return;
        }

        results = [];
        showStep(4);

        const progressBar = document.querySelector('.erh-lp-progress');
        const progressFill = document.querySelector('.erh-lp-progress-fill');
        const progressText = document.querySelector('.erh-lp-progress-text');

        progressBar.style.display = 'block';
        findUrlsBtn.disabled = true;

        for (let i = 0; i < selected.length; i++) {
            const product = selected[i];
            const progress = ((i + 1) / selected.length) * 100;

            progressFill.style.width = progress + '%';
            progressText.textContent = (i18n.findingAsins || i18n.productOf)
                .replace('%1$d', i + 1)
                .replace('%2$d', selected.length);

            try {
                const response = await ajax('erh_lp_find_amazon_urls', {
                    product_id: product.id,
                    product_name: product.name,
                    locale: selectedLocale,
                });

                if (response.success) {
                    const result = response.data.result;

                    results.push({
                        product_id: product.id,
                        product_name: product.name,
                        asin: result.asin,
                        url: result.url,
                        title: result.title,
                        brand: result.brand,
                        image: result.image,
                        price: result.price,
                        price_display: result.price_display,
                        is_prime: result.is_prime,
                        availability: result.availability,
                        error: result.error,
                        link_id: product.link_id || null,
                        has_link: product.has_link,
                    });
                }
            } catch (error) {
                results.push({
                    product_id: product.id,
                    product_name: product.name,
                    asin: null,
                    url: null,
                    error: error.message,
                    link_id: product.link_id || null,
                    has_link: product.has_link,
                });
            }

            // Amazon Creators API rate limit: 1 request/second per associate tag.
            // Use 1.2s to avoid 429s from timing edge cases.
            if (i < selected.length - 1) {
                await sleep(2000);
            }
        }

        progressBar.style.display = 'none';
        findUrlsBtn.disabled = false;
        renderResults();
    }

    /**
     * Render results table.
     */
    function renderResults() {
        const tbody = resultsTable.querySelector('tbody');

        if (results.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4">' + i18n.noResults + '</td></tr>';
            return;
        }

        const html = results.map(result => {
            if (currentMode === 'amazon') {
                return renderAmazonResultRow(result);
            } else {
                return renderScraperResultRow(result);
            }
        }).join('');

        tbody.innerHTML = html;

        // Bind checkbox events
        tbody.querySelectorAll('input[type="checkbox"]').forEach(cb => {
            cb.addEventListener('change', updateAddCount);
        });

        // Bind URL input events for manual entry
        if (currentMode !== 'amazon') {
            tbody.querySelectorAll('.erh-lp-url-input').forEach(input => {
                input.addEventListener('input', handleManualUrlInput);
            });
        }

        updateAddCount();
    }

    /**
     * Render a scraper result row.
     */
    function renderScraperResultRow(result) {
        const hasUrl = result.url && !result.error;
        const isVerified = result.verification?.success;
        const httpCode = result.verification?.http_code || '--';
        const statusClass = hasUrl ? (isVerified ? 'success' : 'warning') : 'error';
        const statusText = hasUrl
            ? (isVerified ? httpCode + ' OK' : httpCode + ' ' + (result.verification?.error || ''))
            : (result.error || 'NOT_FOUND');
        const overwriteIndicator = result.has_link
            ? '<span class="erh-lp-overwrite-badge" title="Will overwrite existing link">UPDATE</span>'
            : '';

        const editUrl = `${adminUrl}post.php?post=${result.product_id}&action=edit`;

        return `
            <tr class="status-${statusClass}${result.has_link ? ' is-overwrite' : ''}">
                <td class="check-column">
                    <input type="checkbox"
                           ${hasUrl && isVerified ? 'checked' : ''}
                           data-product-id="${result.product_id}"
                           data-link-id="${result.link_id || ''}">
                </td>
                <td>
                    <a href="${editUrl}" target="_blank" class="erh-lp-edit-link" title="Edit product">&#9998;</a>
                    ${escapeHtml(result.product_name)} ${overwriteIndicator}
                </td>
                <td class="url-cell">
                    <input type="text" class="erh-lp-url-input" value="${hasUrl ? escapeHtml(result.url) : ''}" data-product-id="${result.product_id}" placeholder="${hasUrl ? '' : 'Paste URL...'}">
                    ${hasUrl ? `<a href="${escapeHtml(result.url)}" target="_blank" class="erh-lp-url-open" title="Open URL">&#8599;</a>` : ''}
                </td>
                <td class="status-cell">
                    <span class="erh-lp-status erh-lp-status-${statusClass}">
                        ${hasUrl && isVerified ? '&#10003;' : (hasUrl ? '?' : '&#10007;')} ${statusText}
                    </span>
                </td>
            </tr>
        `;
    }

    /**
     * Render an Amazon result row with product details for verification.
     */
    function renderAmazonResultRow(result) {
        const hasAsin = result.asin && !result.error;
        const statusClass = hasAsin ? 'success' : 'error';
        const overwriteIndicator = result.has_link
            ? '<span class="erh-lp-overwrite-badge" title="Will overwrite existing ASIN">UPDATE</span>'
            : '';

        const editUrl = `${adminUrl}post.php?post=${result.product_id}&action=edit`;
        const domain = amazonLocales[selectedLocale] || 'www.amazon.com';
        const amazonUrl = hasAsin ? `https://${domain}/dp/${result.asin}` : '';

        // Build Amazon product info display
        let amazonInfo = '';
        if (hasAsin) {
            const image = result.image ? `<img src="${result.image}" alt="" class="erh-lp-amazon-thumb">` : '';
            const brand = result.brand ? `<span class="erh-lp-amazon-brand">${escapeHtml(result.brand)}</span>` : '';
            const price = result.price_display || '';
            const prime = result.is_prime ? '<span class="erh-lp-prime-badge" title="Prime eligible">Prime</span>' : '';
            const title = result.title ? escapeHtml(result.title.substring(0, 60) + (result.title.length > 60 ? '...' : '')) : '';

            amazonInfo = `
                <div class="erh-lp-amazon-preview">
                    ${image}
                    <div class="erh-lp-amazon-details">
                        <div class="erh-lp-amazon-title" title="${escapeHtml(result.title || '')}">${title}</div>
                        <div class="erh-lp-amazon-meta">
                            ${brand} ${price ? `<span class="erh-lp-amazon-price">${price}</span>` : ''} ${prime}
                        </div>
                    </div>
                </div>
            `;
        }

        // Status column - show availability or match status
        let statusHtml = '';
        if (hasAsin) {
            const availability = result.availability || 'Available';
            statusHtml = `<span class="erh-lp-status erh-lp-status-success">&#10003; ${availability}</span>`;
        } else {
            statusHtml = `<span class="erh-lp-status erh-lp-status-error">&#10007; Not Found</span>`;
        }

        return `
            <tr class="status-${statusClass}${result.has_link ? ' is-overwrite' : ''}">
                <td class="check-column">
                    <input type="checkbox"
                           ${hasAsin ? 'checked' : ''}
                           ${!hasAsin ? 'disabled' : ''}
                           data-product-id="${result.product_id}"
                           data-asin="${result.asin || ''}"
                           data-link-id="${result.link_id || ''}">
                </td>
                <td class="erh-lp-product-cell">
                    <a href="${editUrl}" target="_blank" class="erh-lp-edit-link" title="Edit product">&#9998;</a>
                    <strong>${escapeHtml(result.product_name)}</strong> ${overwriteIndicator}
                </td>
                <td class="erh-lp-amazon-cell">
                    ${hasAsin
                        ? `${amazonInfo}
                           <div class="erh-lp-asin-row">
                               <input type="text" class="erh-lp-asin-input" value="${escapeHtml(result.asin)}" data-product-id="${result.product_id}">
                               <a href="${amazonUrl}" target="_blank" class="erh-lp-url-open" title="Open on Amazon">&#8599;</a>
                           </div>`
                        : `<span class="erh-lp-not-found">${result.error || 'Not found'}</span>`}
                </td>
                <td class="status-cell">
                    ${statusHtml}
                </td>
            </tr>
        `;
    }

    /**
     * Toggle all result checkboxes.
     */
    function toggleAllResults() {
        const checked = checkAllResults.checked;
        resultsTable.querySelectorAll('tbody input[type="checkbox"]:not(:disabled)').forEach(cb => {
            cb.checked = checked;
        });
        updateAddCount();
    }

    /**
     * Update add links count.
     */
    function updateAddCount() {
        const checked = resultsTable.querySelectorAll('tbody input[type="checkbox"]:checked');
        const countEl = document.querySelector('.erh-lp-add-count');

        if (countEl) {
            countEl.textContent = checked.length + ' link(s) selected';
        }

        if (addLinksBtn) {
            addLinksBtn.disabled = checked.length === 0;
        }
    }

    /**
     * Handle manual URL input on not-found rows.
     * Auto-checks the checkbox and adds the open-link icon when a URL is entered.
     */
    function handleManualUrlInput(e) {
        const input = e.target;
        const row = input.closest('tr');
        const cb = row.querySelector('input[type="checkbox"]');
        const url = input.value.trim();
        const urlCell = input.closest('.url-cell');

        if (url) {
            cb.checked = true;
            // Add open-link icon if not already present
            if (!urlCell.querySelector('.erh-lp-url-open')) {
                const link = document.createElement('a');
                link.href = url;
                link.target = '_blank';
                link.className = 'erh-lp-url-open';
                link.title = 'Open URL';
                link.innerHTML = '&#8599;';
                urlCell.appendChild(link);
            } else {
                urlCell.querySelector('.erh-lp-url-open').href = url;
            }
            // Update row status to manual
            row.className = row.className.replace(/\bstatus-\w+/, 'status-warning');
            const statusSpan = row.querySelector('.erh-lp-status');
            if (statusSpan) {
                statusSpan.className = 'erh-lp-status erh-lp-status-warning';
                statusSpan.innerHTML = '&#9998; Manual';
            }
        } else {
            cb.checked = false;
            // Remove open-link icon
            const openLink = urlCell.querySelector('.erh-lp-url-open');
            if (openLink && !input.defaultValue) {
                openLink.remove();
            }
            // Revert status
            row.className = row.className.replace(/\bstatus-\w+/, 'status-error');
            const statusSpan = row.querySelector('.erh-lp-status');
            if (statusSpan) {
                statusSpan.className = 'erh-lp-status erh-lp-status-error';
                statusSpan.innerHTML = '&#10007; NOT_FOUND';
            }
        }

        updateAddCount();
    }

    /**
     * Add selected links to HFT.
     */
    async function addLinks() {
        if (currentMode === 'amazon') {
            await addAmazonLinks();
        } else {
            await addScraperLinks();
        }
    }

    /**
     * Add scraper links to HFT.
     */
    async function addScraperLinks() {
        const checked = resultsTable.querySelectorAll('tbody input[type="checkbox"]:checked');

        if (checked.length === 0) {
            alert(i18n.noProducts);
            return;
        }

        if (!confirm(i18n.confirmAdd)) {
            return;
        }

        const links = Array.from(checked).map(cb => {
            const productId = parseInt(cb.dataset.productId, 10);
            const row = cb.closest('tr');
            const urlInput = row.querySelector('.erh-lp-url-input');
            return {
                product_id: productId,
                url: urlInput ? urlInput.value.trim() : '',
                link_id: cb.dataset.linkId ? parseInt(cb.dataset.linkId, 10) : null,
            };
        }).filter(link => link.url);

        addLinksBtn.disabled = true;
        addLinksBtn.textContent = i18n.adding;

        try {
            const response = await ajax('erh_lp_add_links', {
                links: JSON.stringify(links),
                scraper_id: selectedScraper.id,
            });

            if (response.success) {
                alert(response.data.message);
                await loadProducts(selectedScraper.id);
                hideSteps([4]);
            } else {
                alert(response.data?.message || i18n.error);
            }
        } catch (error) {
            alert(error.message);
        } finally {
            addLinksBtn.disabled = false;
            addLinksBtn.textContent = 'Add Links to HFT';
        }
    }

    /**
     * Add Amazon links to HFT.
     */
    async function addAmazonLinks() {
        const checked = resultsTable.querySelectorAll('tbody input[type="checkbox"]:checked');

        if (checked.length === 0) {
            alert(i18n.noProducts);
            return;
        }

        if (!confirm(i18n.confirmAdd)) {
            return;
        }

        const links = Array.from(checked).map(cb => {
            const productId = parseInt(cb.dataset.productId, 10);
            const row = cb.closest('tr');
            const asinInput = row.querySelector('.erh-lp-asin-input');
            return {
                product_id: productId,
                asin: asinInput ? asinInput.value.trim().toUpperCase() : (cb.dataset.asin || ''),
                link_id: cb.dataset.linkId ? parseInt(cb.dataset.linkId, 10) : null,
            };
        }).filter(link => link.asin);

        addLinksBtn.disabled = true;
        addLinksBtn.textContent = i18n.adding;

        try {
            const response = await ajax('erh_lp_add_amazon_links', {
                links: JSON.stringify(links),
                locale: selectedLocale,
            });

            if (response.success) {
                alert(response.data.message);
                await loadAmazonProducts(selectedLocale);
                hideSteps([4]);
            } else {
                alert(response.data?.message || i18n.error);
            }
        } catch (error) {
            alert(error.message);
        } finally {
            addLinksBtn.disabled = false;
            addLinksBtn.textContent = 'Add Links to HFT';
        }
    }

    /**
     * Show a specific step.
     *
     * @param {number} step - Step number to show.
     */
    function showStep(step) {
        const stepEl = document.querySelector(`.erh-lp-step[data-step="${step}"]`);
        if (stepEl) {
            stepEl.style.display = 'block';
        }
    }

    /**
     * Hide specific steps.
     *
     * @param {Array} steps - Step numbers to hide.
     */
    function hideSteps(steps) {
        steps.forEach(step => {
            const stepEl = document.querySelector(`.erh-lp-step[data-step="${step}"]`);
            if (stepEl) {
                stepEl.style.display = 'none';
            }
        });
    }

    /**
     * Show error message.
     *
     * @param {string} message - Error message.
     */
    function showError(message) {
        if (productsContainer) {
            productsContainer.innerHTML = '<p class="erh-lp-error">' + escapeHtml(message) + '</p>';
        }
    }

    /**
     * Make AJAX request.
     *
     * @param {string} action - AJAX action name.
     * @param {Object} data - Additional data.
     * @returns {Promise} Response promise.
     */
    function ajax(action, data = {}) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', nonce);

        Object.keys(data).forEach(key => {
            formData.append(key, data[key]);
        });

        return fetch(ajaxUrl, {
            method: 'POST',
            body: formData,
        }).then(res => res.json());
    }

    /**
     * Escape HTML entities.
     *
     * @param {string} str - String to escape.
     * @returns {string} Escaped string.
     */
    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    /**
     * Debounce function.
     *
     * @param {Function} fn - Function to debounce.
     * @param {number} delay - Delay in ms.
     * @returns {Function} Debounced function.
     */
    function debounce(fn, delay) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => fn.apply(this, args), delay);
        };
    }

    /**
     * Sleep for specified milliseconds.
     *
     * @param {number} ms - Milliseconds to sleep.
     * @returns {Promise} Promise that resolves after delay.
     */
    function sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
