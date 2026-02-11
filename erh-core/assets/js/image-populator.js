/**
 * Image Populator - Product image finder modal on edit screens.
 *
 * Adds a floating button on product edit pages. Opens a modal that
 * searches Google Images, displays a grid, and lets the admin select
 * an image to download, transcode, and set as the featured image.
 *
 * @package ERH\Admin
 */

(function() {
    'use strict';

    const config = window.erhImagePopulator || {};
    const { ajaxUrl, nonce, productId, productName, defaultQuery, isConfigured, hasThumbnail } = config;

    if (!isConfigured || !productId) return;

    let modalEl = null;
    let isSearching = false;
    let isSelecting = false;

    /**
     * Initialize: inject the floating button.
     */
    function init() {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'erh-ip-trigger';
        btn.innerHTML = '<span class="erh-ip-trigger-icon">&#128247;</span> Find Image';
        btn.title = 'Find product image';
        btn.addEventListener('click', openModal);
        document.body.appendChild(btn);
    }

    /**
     * Open the modal overlay.
     */
    function openModal() {
        if (modalEl) {
            modalEl.style.display = 'flex';
            return;
        }

        modalEl = document.createElement('div');
        modalEl.className = 'erh-ip-overlay';
        modalEl.innerHTML = buildModalHtml();
        document.body.appendChild(modalEl);

        // Bind events.
        modalEl.querySelector('.erh-ip-close').addEventListener('click', closeModal);
        modalEl.querySelector('.erh-ip-search-btn').addEventListener('click', searchImages);
        modalEl.querySelector('.erh-ip-search-input').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchImages();
            }
        });
        modalEl.addEventListener('click', function(e) {
            if (e.target === modalEl) closeModal();
        });

        // Auto-search on open.
        searchImages();
    }

    /**
     * Close the modal.
     */
    function closeModal() {
        if (modalEl) {
            modalEl.style.display = 'none';
        }
    }

    /**
     * Build the modal HTML.
     */
    function buildModalHtml() {
        return '<div class="erh-ip-modal">'
            + '<div class="erh-ip-header">'
            + '<h2>Find Product Image</h2>'
            + '<button type="button" class="erh-ip-close">&times;</button>'
            + '</div>'
            + '<div class="erh-ip-body">'
            + '<div class="erh-ip-search-bar">'
            + '<input type="text" class="erh-ip-search-input" value="' + escAttr(defaultQuery) + '" placeholder="Search for product image...">'
            + '<button type="button" class="button button-primary erh-ip-search-btn">Search</button>'
            + '</div>'
            + (hasThumbnail ? '<p class="erh-ip-warning">This product already has a featured image. Selecting a new one will replace it.</p>' : '')
            + '<div class="erh-ip-status" style="display: none;"></div>'
            + '<div class="erh-ip-grid"></div>'
            + '</div>'
            + '</div>';
    }

    /**
     * Search for images via AJAX.
     */
    async function searchImages() {
        if (isSearching) return;
        isSearching = true;

        const input = modalEl.querySelector('.erh-ip-search-input');
        const query = input.value.trim();
        if (!query) {
            isSearching = false;
            return;
        }

        const gridEl = modalEl.querySelector('.erh-ip-grid');
        const statusEl = modalEl.querySelector('.erh-ip-status');
        const searchBtn = modalEl.querySelector('.erh-ip-search-btn');

        searchBtn.disabled = true;
        statusEl.style.display = 'block';
        statusEl.textContent = 'Searching...';
        statusEl.className = 'erh-ip-status';
        gridEl.innerHTML = '';

        try {
            const formData = new FormData();
            formData.append('action', 'erh_ip_search_images');
            formData.append('nonce', nonce);
            formData.append('query', query);

            const response = await fetch(ajaxUrl, { method: 'POST', body: formData });
            const data = await response.json();

            if (!data.success) {
                statusEl.textContent = data.data?.message || 'Search failed.';
                statusEl.className = 'erh-ip-status is-error';
            } else if (!data.data.images || !data.data.images.length) {
                statusEl.textContent = 'No images found. Try a different search.';
                statusEl.className = 'erh-ip-status is-info';
            } else {
                statusEl.textContent = 'Click an image to set it as the featured image.';
                statusEl.className = 'erh-ip-status is-success';
                renderGrid(data.data.images, gridEl);
            }
        } catch (err) {
            statusEl.textContent = 'Network error.';
            statusEl.className = 'erh-ip-status is-error';
        }

        searchBtn.disabled = false;
        isSearching = false;
    }

    /**
     * Render the image results grid.
     */
    function renderGrid(images, container) {
        let html = '';

        images.forEach(function(img) {
            const dims = img.width && img.height ? img.width + ' x ' + img.height : '';
            const source = img.source || '';

            html += '<div class="erh-ip-card" data-url="' + escAttr(img.url) + '">'
                + '<div class="erh-ip-card-img">'
                + '<img src="' + escAttr(img.thumbnail) + '" alt="' + escAttr(img.title) + '" loading="lazy">'
                + '</div>'
                + '<div class="erh-ip-card-info">'
                + (dims ? '<span class="erh-ip-card-dims">' + escHtml(dims) + '</span>' : '')
                + '<span class="erh-ip-card-format">' + escHtml(img.format) + '</span>'
                + (source ? '<span class="erh-ip-card-source" title="' + escAttr(source) + '">' + escHtml(truncate(source, 25)) + '</span>' : '')
                + '</div>'
                + '</div>';
        });

        container.innerHTML = html;

        // Bind click handlers.
        container.querySelectorAll('.erh-ip-card').forEach(function(card) {
            card.addEventListener('click', function() {
                selectImage(card.dataset.url, card);
            });
        });
    }

    /**
     * Select an image: download, process, and set as featured.
     */
    async function selectImage(imageUrl, cardEl) {
        if (isSelecting) return;
        isSelecting = true;

        const statusEl = modalEl.querySelector('.erh-ip-status');

        // Highlight selected card.
        modalEl.querySelectorAll('.erh-ip-card').forEach(function(c) {
            c.classList.remove('is-selected');
        });
        cardEl.classList.add('is-selected', 'is-loading');

        statusEl.textContent = 'Downloading and processing image...';
        statusEl.className = 'erh-ip-status';
        statusEl.style.display = 'block';

        try {
            const formData = new FormData();
            formData.append('action', 'erh_ip_select_image');
            formData.append('nonce', nonce);
            formData.append('product_id', productId);
            formData.append('image_url', imageUrl);

            const response = await fetch(ajaxUrl, { method: 'POST', body: formData });
            const data = await response.json();

            cardEl.classList.remove('is-loading');

            if (data.success) {
                statusEl.textContent = 'Image set as featured image! Reloading...';
                statusEl.className = 'erh-ip-status is-success';
                cardEl.classList.add('is-done');

                // Reload so the featured image metabox updates.
                setTimeout(function() { window.location.reload(); }, 1500);
            } else {
                statusEl.textContent = data.data?.message || 'Failed to set image.';
                statusEl.className = 'erh-ip-status is-error';
                cardEl.classList.remove('is-selected');
                isSelecting = false;
            }
        } catch (err) {
            cardEl.classList.remove('is-loading', 'is-selected');
            statusEl.textContent = 'Network error.';
            statusEl.className = 'erh-ip-status is-error';
            isSelecting = false;
        }
    }

    /**
     * Truncate a string with ellipsis.
     */
    function truncate(str, max) {
        return str.length > max ? str.substring(0, max) + '...' : str;
    }

    /**
     * HTML-escape a string.
     */
    function escHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    /**
     * Attribute-escape a string.
     */
    function escAttr(str) {
        return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // Initialize on DOM ready.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
