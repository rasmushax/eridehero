/**
 * Listicle Item Block Frontend Script
 * Handles tabs, AJAX loading, video modals, and rating overlays
 */

(function() {
    'use strict';
    
    // Store active chart instances to prevent duplicates
    const activePriceCharts = {};
    
    // Cache DOM queries
    const bodyElement = document.body;
    
    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', initializeListicleBlocks);
    
    /**
     * Initialize all listicle item blocks on the page
     */
    function initializeListicleBlocks() {
        const listicleBlocks = document.querySelectorAll('.listicle-item-block');
        
        listicleBlocks.forEach(block => {
            initializeTabs(block);
            initializeRatingOverlay(block);
        });
        
        // Initialize video modals once for all blocks
        initializeVideoModals();
    }
    
    /**
     * Initialize tab functionality for a block
     */
    function initializeTabs(block) {
        const tabList = block.querySelector('.tab-list');
        const tabs = block.querySelectorAll('.tab-button:not(:disabled)');
        const panels = block.querySelectorAll('.tab-panel');
        
        if (!tabList || tabs.length === 0 || panels.length === 0) return;
        
        // Tab click handling
        tabList.addEventListener('click', event => {
            const clickedTab = event.target.closest('.tab-button:not(:disabled)');
            if (!clickedTab) return;
            
            event.preventDefault();
            activateTab(clickedTab, tabs, panels, block);
        });
        
        // Keyboard navigation
        tabList.addEventListener('keydown', event => {
            handleTabKeyboardNavigation(event, tabs);
        });
    }
    
    /**
     * Activate a tab and its corresponding panel
     */
    function activateTab(clickedTab, tabs, panels, block) {
        const targetPanelId = clickedTab.getAttribute('aria-controls');
        if (!targetPanelId) return;
        
        const targetPanel = block.querySelector(`#${targetPanelId}`);
        if (!targetPanel) return;
        
        // Deactivate all tabs and panels
        tabs.forEach(tab => {
            tab.classList.remove('is-active');
            tab.setAttribute('aria-selected', 'false');
            tab.setAttribute('tabindex', '-1');
        });
        
        panels.forEach(panel => {
            panel.classList.remove('is-active');
            panel.hidden = true;
        });
        
        // Activate clicked tab and panel
        clickedTab.classList.add('is-active');
        clickedTab.setAttribute('aria-selected', 'true');
        clickedTab.setAttribute('tabindex', '0');
        
        targetPanel.classList.add('is-active');
        targetPanel.hidden = false;
        
        // Load content if needed
        if (clickedTab.id.includes('-price-history-tab')) {
            loadPriceHistoryChart(clickedTab, targetPanel);
        } else if (clickedTab.id.includes('-specs-tab')) {
            loadSpecifications(clickedTab, targetPanel);
        } else {
            targetPanel.focus({ preventScroll: true });
        }
    }
    
    /**
     * Handle keyboard navigation for tabs
     */
    function handleTabKeyboardNavigation(event, tabs) {
        const currentTab = document.activeElement;
        if (!currentTab || !currentTab.matches('.tab-button')) return;
        
        const enabledTabs = Array.from(tabs).filter(tab => !tab.disabled);
        const currentIndex = enabledTabs.indexOf(currentTab);
        if (currentIndex === -1) return;
        
        let newIndex = currentIndex;
        
        switch(event.key) {
            case 'ArrowRight':
                newIndex = (currentIndex + 1) % enabledTabs.length;
                break;
            case 'ArrowLeft':
                newIndex = (currentIndex - 1 + enabledTabs.length) % enabledTabs.length;
                break;
            case 'Home':
                newIndex = 0;
                break;
            case 'End':
                newIndex = enabledTabs.length - 1;
                break;
            default:
                return;
        }
        
        event.preventDefault();
        enabledTabs[newIndex].focus();
    }
    
    /**
     * Initialize rating overlay functionality
     */
    function initializeRatingOverlay(block) {
        const ratingOverlay = block.querySelector('.listicle-item-rating-overlay');
        if (!ratingOverlay) return;
        
        const ratingContainer = ratingOverlay.querySelector('.rating-container');
        const viewButton = ratingOverlay.querySelector('.rating-details-button');
        const detailsPanel = ratingOverlay.querySelector('.rating-details-panel');
        
        if (!ratingContainer || !viewButton || !detailsPanel) return;
        
        // Toggle on click
        ratingContainer.addEventListener('click', () => {
            toggleRatingDetails(viewButton, detailsPanel);
        });
        
        // Close on Escape
        detailsPanel.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                closeRatingDetails(viewButton, detailsPanel);
            }
        });
        
        // Close when clicking outside
        document.addEventListener('click', event => {
            if (!ratingOverlay.contains(event.target) && 
                viewButton.getAttribute('aria-expanded') === 'true') {
                closeRatingDetails(viewButton, detailsPanel);
            }
        }, true);
    }
    
    /**
     * Toggle rating details panel
     */
    function toggleRatingDetails(viewButton, detailsPanel) {
        const isExpanded = viewButton.getAttribute('aria-expanded') === 'true';
        viewButton.setAttribute('aria-expanded', !isExpanded);
        detailsPanel.hidden = isExpanded;
        
        const textSpan = viewButton.querySelector('.details-text');
        if (textSpan) {
            textSpan.textContent = isExpanded ? 'View Details' : 'Hide Details';
        }
    }
    
    /**
     * Close rating details panel
     */
    function closeRatingDetails(viewButton, detailsPanel) {
        viewButton.setAttribute('aria-expanded', 'false');
        detailsPanel.hidden = true;
        
        const textSpan = viewButton.querySelector('.details-text');
        if (textSpan) {
            textSpan.textContent = 'View Details';
        }
    }
    
    /**
     * Initialize video modal functionality
     */
    function initializeVideoModals() {
        // Video button clicks
        document.addEventListener('click', event => {
            const videoButton = event.target.closest('.video-review-link');
            if (videoButton) {
                event.preventDefault();
                openVideoModal(videoButton);
            }
            
            // Close button clicks
            const closeButton = event.target.closest('.video-modal-close');
            if (closeButton) {
                closeVideoModal(closeButton.closest('.video-modal'));
            }
            
            // Click outside modal
            if (event.target.classList.contains('video-modal') && 
                event.target.classList.contains('is-visible')) {
                closeVideoModal(event.target);
            }
        });
        
        // Escape key
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                const visibleModal = document.querySelector('.video-modal.is-visible');
                if (visibleModal) {
                    closeVideoModal(visibleModal);
                }
            }
        });
    }
    
    /**
     * Open video modal
     */
    function openVideoModal(button) {
        const youtubeUrl = button.dataset.youtubeUrl;
        const modalId = button.dataset.modalId;
        const modal = document.getElementById(modalId);
        
        if (!youtubeUrl || !modal) return;
        
        const videoId = extractYouTubeId(youtubeUrl);
        if (!videoId) {
            console.error('Could not extract YouTube video ID from URL:', youtubeUrl);
            return;
        }
        
        const videoContainer = modal.querySelector('.video-container');
        videoContainer.innerHTML = `
            <iframe 
                src="https://www.youtube.com/embed/${videoId}?autoplay=1" 
                frameborder="0" 
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                allowfullscreen>
            </iframe>
        `;
        
        modal.classList.add('is-visible');
        bodyElement.classList.add('modal-open');
        modal.focus();
    }
    
    /**
     * Close video modal
     */
    function closeVideoModal(modal) {
        if (!modal) return;
        
        const videoContainer = modal.querySelector('.video-container');
        videoContainer.innerHTML = '';
        
        modal.classList.remove('is-visible');
        bodyElement.classList.remove('modal-open');
    }
    
    /**
     * Extract YouTube video ID from URL
     */
    function extractYouTubeId(url) {
        const regex = /(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i;
        const match = url.match(regex);
        return match && match[1] ? match[1] : null;
    }
    
    /**
     * Load price history chart via AJAX
     */
    function loadPriceHistoryChart(tabButton, targetPanel) {
        const productId = tabButton.dataset.productId;
        const canvasId = tabButton.dataset.chartCanvasId;
        const trackingInfoId = tabButton.dataset.trackingInfoId;
        const summaryStatsId = tabButton.dataset.summaryStatsId;
        const spinnerId = tabButton.dataset.spinnerId;
        const alreadyLoaded = tabButton.dataset.loaded === 'true';
        
        // Check if already loaded
        if (alreadyLoaded) {
            if (activePriceCharts[canvasId]) {
                showPriceHistoryElements(targetPanel, trackingInfoId, summaryStatsId);
            }
            return;
        }
        
        // Get required elements
        const elements = getPriceHistoryElements(targetPanel, spinnerId, trackingInfoId, summaryStatsId);
        if (!elements || !productId || !canvasId) {
            showError(elements?.errorMessage, 'Configuration error: Cannot load chart elements.');
            return;
        }
        
        // Check prerequisites
        if (typeof Chart === 'undefined') {
            showError(elements.errorMessage, 'Error: Chart library not loaded.');
            return;
        }
        
        if (!checkAjaxConfig()) {
            showError(elements.errorMessage, 'Error: Configuration data missing.');
            return;
        }
        
        // Start loading
        showLoading(elements);
        
        // Make AJAX request
        makeAjaxRequest('load_price_history', { product_id: productId })
            .then(result => handlePriceHistoryResponse(result, elements, canvasId, tabButton))
            .catch(error => handleAjaxError(error, elements));
    }
    
    /**
     * Load specifications via AJAX
     */
    function loadSpecifications(tabButton, targetPanel) {
        const productId = tabButton.dataset.productId;
        const spinnerId = tabButton.dataset.spinnerId;
        const errorId = tabButton.dataset.errorId;
        const targetId = tabButton.dataset.targetId;
        const alreadyLoaded = tabButton.dataset.loaded === 'true';
        
        // Check if already loaded
        if (alreadyLoaded) {
            showSpecificationsElements(targetId, errorId, spinnerId);
            return;
        }
        
        // Get required elements
        const elements = getSpecificationsElements(spinnerId, errorId, targetId);
        if (!elements || !productId) {
            showError(elements?.errorMessage, 'Configuration error: Cannot load specification elements.');
            return;
        }
        
        if (!checkAjaxConfig()) {
            showError(elements.errorMessage, 'Error: Configuration data missing.');
            return;
        }
        
        // Start loading
        showLoading(elements);
        
        // Make AJAX request
        makeAjaxRequest('load_specifications', { product_id: productId })
            .then(result => handleSpecificationsResponse(result, elements, tabButton))
            .catch(error => handleAjaxError(error, elements));
    }
    
    /**
     * Make AJAX request
     */
    function makeAjaxRequest(action, data) {
        const formData = new FormData();
        formData.append('action', action);
        
        for (const key in data) {
            formData.append(key, data[key]);
        }
        
        const cacheBuster = new Date().getTime();
        const ajaxUrl = `${listicleItemData.ajax_url}${listicleItemData.ajax_url.includes('?') ? '&' : '?'}nocache=${cacheBuster}`;
        
        return fetch(ajaxUrl, {
            method: 'POST',
            body: formData
        }).then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error(`HTTP error! status: ${response.status}, message: ${text || response.statusText}`);
                });
            }
            return response.json();
        });
    }
    
    /**
     * Handle price history response
     */
    function handlePriceHistoryResponse(result, elements, canvasId, tabButton) {
        elements.loadingSpinner.style.display = 'none';
        
        if (!result.success || !result.data) {
            showError(elements.errorMessage, result.data || 'Could not load price history data.');
            return;
        }
        
        const responseData = result.data;
        
        // Populate tracking info
        if (responseData.summaryStats || responseData.currentBestPrice) {
            populateTrackingInfo(responseData, elements.trackingInfoDiv);
        }
        
        // Populate summary stats
        if (responseData.summaryStats) {
            populateSummaryStats(responseData.summaryStats, elements.summaryStatsDiv);
        }
        
        // Initialize chart
        if (responseData.chartData) {
            initializeChart(responseData.chartData, canvasId, elements);
        }
        
        tabButton.dataset.loaded = 'true';
    }
    
    /**
     * Handle specifications response
     */
    function handleSpecificationsResponse(result, elements, tabButton) {
        elements.loadingSpinner.style.display = 'none';
        
        if (!result.success || !result.data || !result.data.html) {
            showError(elements.errorMessage, result.data || 'Could not load specifications data.');
            return;
        }
        
        elements.contentTarget.innerHTML = result.data.html;
		initializeSpecAccordions(elements.contentTarget);
        elements.contentTarget.style.display = 'block';
        elements.errorMessage.style.display = 'none';
        tabButton.dataset.loaded = 'true';
    }
    
	/**
	 * Initialize specification accordions
	 */
	function initializeSpecAccordions(container) {
		const accordionTriggers = container.querySelectorAll('.specs-accordion-trigger');
		
		accordionTriggers.forEach(trigger => {
			trigger.addEventListener('click', () => {
				const isExpanded = trigger.getAttribute('aria-expanded') === 'true';
				const content = trigger.nextElementSibling;
				
				// Toggle state
				trigger.setAttribute('aria-expanded', !isExpanded);
				content.hidden = isExpanded;
				
				// Animate icon rotation
				const icon = trigger.querySelector('.specs-accordion-icon');
				if (icon) {
					icon.classList.toggle('is-rotated', !isExpanded);
				}
			});
		});
		
		// Optionally open first accordion by default
		if (accordionTriggers.length > 0) {
			accordionTriggers[0].click();
		}
	}
	
    /**
     * Initialize price history chart
     */
    function initializeChart(chartData, canvasId, elements) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) {
            showError(elements.errorMessage, 'Error: Chart canvas element missing.');
            return;
        }
        
        const ctx = canvas.getContext('2d');
        
        // Destroy existing chart if present
        if (activePriceCharts[canvasId]) {
            activePriceCharts[canvasId].destroy();
        }
        
        const chartConfig = {
            type: 'line',
            data: {
                datasets: chartData.datasets.map(dataset => ({
                    label: dataset.label || 'Price History',
                    data: dataset.data,
                    borderColor: 'rgb(94, 44, 237)',
                    backgroundColor: 'rgba(111, 97, 242, 0.1)',
                    fill: true,
                    tension: 0,
                    pointRadius: 0,
                    borderWidth: dataset.borderWidth || 2,
                }))
            },
            options: getChartOptions()
        };
        
        try {
            activePriceCharts[canvasId] = new Chart(ctx, chartConfig);
            elements.chartContainer.style.display = 'block';
        } catch (error) {
            console.error(`Error creating chart for canvas ${canvasId}:`, error);
            showError(elements.errorMessage, 'Error rendering chart.');
        }
    }
    
    /**
     * Get chart configuration options
     */
    function getChartOptions() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            font: { family: 'Poppins' },
            plugins: {
                legend: { display: false },
                tooltip: {
                    displayColors: false,
                    padding: 8,
                    bodySpacing: 5,
                    titleFont: { size: 16, family: "Poppins" },
                    titleColor: "white",
                    titleAlign: "center",
                    bodyFont: { size: 14, family: "Poppins" },
                    bodyColor: "#f9fafe",
                    bodyAlign: "center",
                    backgroundColor: "#21273a",
                    callbacks: {
                        title: function(context) {
                            if (!context || context.length === 0) return '';
                            const price = context[0].parsed.y;
                            if (price === null || price === undefined) return '';
                            return new Intl.NumberFormat('en-US', {
                                style: 'currency',
                                currency: 'USD',
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            }).format(price) + " USD";
                        },
                        label: function(context) {
                            const date = context.parsed.x;
                            if (date === null || date === undefined) return 'Date N/A';
                            try {
                                return context.chart.adapters.date.format(date, 'PP');
                            } catch (e) {
                                const fallbackDate = new Date(date);
                                if (!isNaN(fallbackDate.getTime())) {
                                    return fallbackDate.toLocaleDateString('en-US', {
                                        year: 'numeric',
                                        month: 'long',
                                        day: 'numeric',
                                        timeZone: 'UTC'
                                    });
                                }
                                return 'Invalid Date';
                            }
                        },
                        afterLabel: function(context) {
                            return context.raw?.domain || '';
                        }
                    }
                }
            },
            scales: {
                x: {
                    type: 'time',
                    time: {
                        unit: 'day',
                        tooltipFormat: 'MMM d, yyyy',
                        displayFormats: {
                            day: 'MMM d',
                            week: 'MMM d',
                            month: 'MMM yy',
                            year: 'yyyy'
                        }
                    },
                    grid: { display: false, drawBorder: false },
                    ticks: { maxTicksLimit: 10, font: { size: 12 }, color: '#636d93' },
                    border: { display: false }
                },
                y: {
                    beginAtZero: false,
                    offset: false,
                    position: 'left',
                    border: { display: false },
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toLocaleString('en-US', {
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 0
                            });
                        },
                        font: { size: 12 },
                        color: '#636d93'
                    },
                    grid: { color: '#e3e8ed', drawBorder: false }
                }
            },
            layout: { padding: { left: 0, right: 0, top: 5, bottom: 0 } }
        };
    }
    
    /**
     * Populate tracking info
     */
    function populateTrackingInfo(data, container) {
        let html = '';
        
        if (data.productTitle && data.summaryStats && data.summaryStats.tracking_start_date) {
            html += `<p>We have tracked <strong>${escapeHTML(data.productTitle)}</strong> since ${escapeHTML(data.summaryStats.tracking_start_date)}.</p>`;
        }
        
        if (data.currentBestPrice && data.currentBestPrice.isAvailable) {
            const price = data.currentBestPrice;
            const formattedPrice = formatCurrency(price.price);
            const vendorLink = `<a class="afftrigger" href="${escapeHTML(price.url)}" target="_blank" rel="noopener sponsored">${escapeHTML(price.vendor)}</a>`;
            html += `<p class="current-best-price">Current best price is ${formattedPrice} at ${vendorLink}.</p>`;
        }
        
        if (html) {
            container.innerHTML = html;
            container.style.display = 'block';
        }
    }
    
    /**
     * Populate summary statistics
     */
    function populateSummaryStats(stats, container) {
        let html = '';
        
        if (stats.lowest_price && typeof stats.lowest_price.amount === 'number' && stats.lowest_price.amount < Infinity) {
            html += createStatItem('Lowest Price', stats.lowest_price.amount, stats.lowest_price.date);
        }
        
        if (stats.highest_price && typeof stats.highest_price.amount === 'number' && stats.highest_price.amount >= 0) {
            html += createStatItem('Highest Price', stats.highest_price.amount, stats.highest_price.date);
        }
        
        if (typeof stats.average_price === 'number') {
            html += createStatItem('Average Price', stats.average_price, `Since ${stats.tracking_start_date || 'Start'}`);
        }
        
        container.innerHTML = html;
        container.style.display = 'grid';
    }
    
    /**
     * Create a stat item HTML
     */
    function createStatItem(label, value, date) {
        return `
            <div class="stat-item">
                <span class="stat-label">${label}</span>
                <span class="stat-value">${formatCurrency(value)}</span>
                <span class="stat-date">on ${escapeHTML(date || 'N/A')}</span>
            </div>
        `;
    }
    
    /**
     * Helper functions
     */
    function checkAjaxConfig() {
        return typeof listicleItemData !== 'undefined' && listicleItemData.ajax_url;
    }
    
    function showError(element, message) {
        if (element) {
            element.textContent = message;
            element.style.display = 'block';
        }
    }
    
    function showLoading(elements) {
        elements.loadingSpinner.style.display = 'flex';
        elements.errorMessage.style.display = 'none';
        
        if (elements.chartContainer) {
            elements.chartContainer.style.display = 'none';
            elements.trackingInfoDiv.innerHTML = '';
            elements.trackingInfoDiv.style.display = 'none';
            elements.summaryStatsDiv.innerHTML = '';
            elements.summaryStatsDiv.style.display = 'none';
        }
        
        if (elements.contentTarget) {
            elements.contentTarget.innerHTML = '';
            elements.contentTarget.style.display = 'none';
        }
    }
    
    function handleAjaxError(error, elements) {
        elements.loadingSpinner.style.display = 'none';
        showError(elements.errorMessage, `Request failed: ${error.message || 'Please check connection.'}`);
        
        if (elements.chartContainer) {
            elements.chartContainer.style.display = 'none';
            elements.trackingInfoDiv.style.display = 'none';
            elements.summaryStatsDiv.style.display = 'none';
        }
        
        if (elements.contentTarget) {
            elements.contentTarget.style.display = 'none';
        }
        
        console.error('AJAX request failed:', error);
    }
    
    function getPriceHistoryElements(panel, spinnerId, trackingInfoId, summaryStatsId) {
        const elements = {
            chartContainer: panel.querySelector('.chart-container'),
            loadingSpinner: document.getElementById(spinnerId),
            errorMessage: panel.querySelector('.chart-error-message'),
            trackingInfoDiv: document.getElementById(trackingInfoId),
            summaryStatsDiv: document.getElementById(summaryStatsId)
        };
        
        return Object.values(elements).every(el => el) ? elements : null;
    }
    
    function getSpecificationsElements(spinnerId, errorId, targetId) {
        const elements = {
            loadingSpinner: document.getElementById(spinnerId),
            errorMessage: document.getElementById(errorId),
            contentTarget: document.getElementById(targetId)
        };
        
        return Object.values(elements).every(el => el) ? elements : null;
    }
    
    function showPriceHistoryElements(panel, trackingInfoId, summaryStatsId) {
        const chartContainer = panel.querySelector('.chart-container');
        const trackingInfoDiv = document.getElementById(trackingInfoId);
        const summaryStatsDiv = document.getElementById(summaryStatsId);
        const errorMessage = panel.querySelector('.chart-error-message');
        
        if (chartContainer) chartContainer.style.display = 'block';
        if (trackingInfoDiv) trackingInfoDiv.style.display = 'block';
        if (summaryStatsDiv) summaryStatsDiv.style.display = 'grid';
        if (errorMessage) errorMessage.style.display = 'none';
    }
    
    function showSpecificationsElements(targetId, errorId, spinnerId) {
        const contentTarget = document.getElementById(targetId);
        const errorMessage = document.getElementById(errorId);
        const loadingSpinner = document.getElementById(spinnerId);
        
        if (contentTarget) contentTarget.style.display = 'block';
        if (errorMessage) errorMessage.style.display = 'none';
        if (loadingSpinner) loadingSpinner.style.display = 'none';
    }
    
    function formatCurrency(amount) {
        if (amount === null || amount === undefined || isNaN(amount)) return 'N/A';
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(amount);
    }
    
    function escapeHTML(str) {
        if (!str) return '';
        return str.toString()
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
    
})();

function copyListicleCoupon(code, button) {
    // Copy to clipboard
    navigator.clipboard.writeText(code).then(() => {
        // Update button state
        const originalText = button.querySelector('.coupon-code-text').innerText;
        button.classList.add('copied');
        button.querySelector('.coupon-code-text').innerText = 'Copied!';
        
        // Reset after 2 seconds
        setTimeout(() => {
            button.classList.remove('copied');
            button.querySelector('.coupon-code-text').innerText = originalText;
        }, 2000);
    }).catch(err => {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = code;
        textArea.style.position = 'fixed';
        textArea.style.opacity = '0';
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        
        // Update button state
        const originalText = button.querySelector('.coupon-code-text').innerText;
        button.classList.add('copied');
        button.querySelector('.coupon-code-text').innerText = 'Copied!';
        
        setTimeout(() => {
            button.classList.remove('copied');
            button.querySelector('.coupon-code-text').innerText = originalText;
        }, 2000);
    });
}