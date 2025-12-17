// Housefresh Tools - Affiliate Link Block Script
window.HFT_AffiliateBlock = window.HFT_AffiliateBlock || {};

(function (HFT_AB) {
    'use strict';
    const DEBUG = false; // Set to false for production

    function log(...args) {
        if (DEBUG) {
            console.log('[HFT Affiliate Block]', ...args);
        }
    }

    /**
     * Initializes all affiliate link placeholders on the page (async for GEO detection).
     */
    HFT_AB.initializeAffiliateLinks = async function() {
        log('Initializing affiliate links (v5 - Container & Multi-link Prep)...');
        const linkContainers = document.querySelectorAll('.hft-affiliate-links-container');
        log(`Found ${linkContainers.length} link container(s).`);

        if (linkContainers.length === 0) {
            return;
        }

        // Check for HFT_Frontend and its detectUserGeo function
        if (typeof window.HFT_Frontend === 'undefined' || typeof window.HFT_Frontend.detectUserGeo !== 'function') {
            linkContainers.forEach(container => {
                container.style.display = 'none';
            });
            return;
        }

        // hft_frontend_data is used by the shared HFT_Frontend.detectUserGeo and also locally here for the get-affiliate-link endpoint
        if (typeof hft_frontend_data === 'undefined' || typeof hft_frontend_data.rest_url === 'undefined') {
            linkContainers.forEach(container => {
                container.style.display = 'none';
            });
            return;
        }
        log('Localization data found:', hft_frontend_data);

        const userGeo = await window.HFT_Frontend.detectUserGeo(); // Call the shared function
        log(`GEO detection complete (via shared function). Using GEO: ${userGeo}`);

        linkContainers.forEach(function (container, index) {
            log(`Processing container #${index + 1}:`, container);
            
            const productId = container.dataset.hftProductId;
            log(` - Data Read: ProductID=${productId}`);

            if (!productId || parseInt(productId, 10) <= 0) {
                container.style.display = 'none';
                return;
            }

            const geoForApi = userGeo || 'US';
            log(` - GEO for API Call: ${geoForApi}`);

            HFT_AB.fetchAndPopulateAffiliateLinks(
                container,
                productId,
                geoForApi
            );
        });
    };

    /**
     * Fetches affiliate link(s) and populates the container.
     * @param {HTMLElement} container The container element to populate.
     * @param {string|number} productId The WordPress Post ID of the product.
     * @param {string} targetGeo The desired GEO.
     */
    HFT_AB.fetchAndPopulateAffiliateLinks = function (container, productId, targetGeo) {
        if (!container || !productId) {
            log('Error: Missing container or productId for fetch.');
            return;
        }

        // The 'is-loading' class is on the container by default from PHP.
        // CSS will make it opacity: 0. We remove the class after processing to fade it in.
        container.innerHTML = ''; 

        let apiUrl = (hft_frontend_data && hft_frontend_data.rest_url)
                      ? hft_frontend_data.rest_url + 'housefresh-tools/v1/get-affiliate-link'
                      : '/wp-json/housefresh-tools/v1/get-affiliate-link';

        log(`Fetching link(s) for ProductID: ${productId}, GEO: ${targetGeo}`);
        
        const queryParams = new URLSearchParams({
            product_id: productId,
            target_geo: targetGeo
        });
        
        apiUrl = `${apiUrl}?${queryParams.toString()}`;
        log(` - API URL: ${apiUrl}`);

        // Create AbortController for timeout
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout

        fetch(apiUrl, {
            method: 'GET',
            signal: controller.signal
        })
        .then(response => {
            clearTimeout(timeoutId);
            log(` - API Response Status: ${response.status}`);
            if (!response.ok) {
                throw new Error(`Network response was not ok: ${response.status} ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            log(' - API Response Data:', data);
            // let contentAdded = false; // Not strictly needed anymore as we always remove is-loading

            if (data.links && Array.isArray(data.links) && data.links.length > 0) {
                data.links.forEach(linkInfo => {
                    if (!linkInfo.url || !linkInfo.retailer_name) {
                        log('Skipping link due to missing URL or retailer name:', linkInfo);
                        return; 
                    }

                    const button = document.createElement('a');
                    button.href = linkInfo.url;
                    button.classList.add('hft-affiliate-button');
                    button.setAttribute('role', 'button');
                    button.setAttribute('target', '_blank');
                    button.setAttribute('rel', 'noopener noreferrer sponsored');

                    const retailerClass = linkInfo.parser_identifier ? linkInfo.parser_identifier.toLowerCase().replace(/[^a-z0-9-_]/g, '-') : 'generic-store';
                    button.classList.add(retailerClass); // Keep for potential data or minor non-visual styling
                    
                    // Determine if this is an Amazon link
                    const isAmazon = linkInfo.parser_identifier && linkInfo.parser_identifier.toLowerCase() === 'amazon';
                    if (isAmazon) {
                        button.classList.add('amazon');
                    }
                    
                    let buttonText = '';
                    if (linkInfo.price_string && linkInfo.price_string.includes('View at')) {
                         buttonText = linkInfo.price_string;
                    } else if (linkInfo.price_string) {
                        buttonText = `${linkInfo.price_string} AT ${linkInfo.retailer_name.toUpperCase()}`;
                    } else {
                        buttonText = `VIEW AT ${linkInfo.retailer_name.toUpperCase()}`;
                    }
                    
                    // Create text node
                    const textSpan = document.createElement('span');
                    textSpan.textContent = buttonText;
                    button.appendChild(textSpan);
                    
                    // Create and add SVG icon
                    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                    svg.setAttribute('width', '1em');
                    svg.setAttribute('height', '1em');
                    svg.setAttribute('aria-hidden', 'true');
                    
                    const use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
                    if (isAmazon) {
                        use.setAttributeNS('http://www.w3.org/1999/xlink', 'href', '#hft-amazon-icon');
                    } else {
                        use.setAttributeNS('http://www.w3.org/1999/xlink', 'href', '#hft-price-tag-icon');
                    }
                    
                    svg.appendChild(use);
                    button.appendChild(svg);
                    
                    container.appendChild(button);
                    // contentAdded = true;
                });
            } else {
                // No links, no error, no message - hide the container completely
                container.style.display = 'none';
                return;
            }
            // Only remove loading class if we have content
            container.classList.remove('is-loading');
        })
        .catch(error => {
            // On error (including timeout), hide the container
            container.style.display = 'none';
        });
    };

    // Auto-initialize on DOMContentLoaded
    if (document.readyState === 'loading') { 
        document.addEventListener('DOMContentLoaded', () => { HFT_AB.initializeAffiliateLinks(); });
    } else {
        HFT_AB.initializeAffiliateLinks(); // Already loaded
    }

})(window.HFT_AffiliateBlock); 