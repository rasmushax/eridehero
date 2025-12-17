/* global hftPriceHistoryData, wp */
(function ($) {
    $(document).ready(function () {
        const container = $('#hft-price-history-container');
        if (!container.length) {
            return;
        }

        const summaryElement = $('#hft-price-history-summary');
        const initialMessageElement = container.find('p:first');

        if (!summaryElement.length) {
            initialMessageElement.text(hftPriceHistoryData.labels.error || 'Summary element not found.');
            return;
        }

        console.log('[HFT Price History] Attempting to fetch data from URL:', hftPriceHistoryData.restApiUrl);
        console.log('[HFT Price History] Nonce:', hftPriceHistoryData.nonce);

        let apiPath = hftPriceHistoryData.restApiUrl;
        if (window.wp && window.wpApiSettings && typeof window.wpApiSettings.root === 'string') {
            if (apiPath.startsWith(window.wpApiSettings.root)) {
                apiPath = apiPath.substring(window.wpApiSettings.root.length);
                console.log('[HFT Price History] Stripped API root. Using path:', apiPath);
            } else {
                console.log('[HFT Price History] Full URL does not start with wpApiSettings.root. Using full URL for path. Root:', window.wpApiSettings.root);
            }
        } else {
            console.warn('[HFT Price History] wpApiSettings.root not available. Passing full URL to wp.apiFetch.');
        }

        wp.apiFetch({
            path: apiPath, 
            method: 'GET',
            headers: {
                'X-WP-Nonce': hftPriceHistoryData.nonce
            }
        })
        .then(function (response) {
            initialMessageElement.hide();

            if (!response || !response.links || response.links.length === 0) {
                summaryElement.html('<p>' + (response.message || hftPriceHistoryData.labels.noData) + '</p>');
                return;
            }

            renderSummaryTable(response, summaryElement);
        })
        .catch(function (error) {
            initialMessageElement.hide();
            console.error('Error fetching price history:', error);
            summaryElement.html('<p>' + hftPriceHistoryData.labels.error + ' ' + (error.message || '') + '</p>');
        });

        function renderSummaryTable(data, summaryContainer) {
            let tableHtml = '';

            // Overall Summary - REMOVED
            /*
            if (data.overallSummary) {
                const os = data.overallSummary;
                tableHtml += '<h3>Overall Price Summary</h3>';
                tableHtml += '<table class="wp-list-table widefat fixed striped"><tbody>';
                tableHtml += '<tr><td>Total Data Points:</td><td>' + (os.totalDataPoints || 'N/A') + '</td></tr>';
                if (os.lowestPrice) {
                     tableHtml += '<tr><td>Lowest Price Ever:</td><td>' + formatPrice(os.lowestPrice.price, data.links, os.lowestPrice.source_id) + ' on ' + formatDate(os.lowestPrice.date) + ' (Link ID: ' + os.lowestPrice.source_id + ')</td></tr>';
                }
                if (os.highestPrice) {
                    tableHtml += '<tr><td>Highest Price Ever:</td><td>' + formatPrice(os.highestPrice.price, data.links, os.highestPrice.source_id) + ' on ' + formatDate(os.highestPrice.date) + ' (Link ID: ' + os.highestPrice.source_id + ')</td></tr>';
                }
                tableHtml += '<tr><td>Average Price (All Links):</td><td>' + (os.averagePrice !== null ? formatPrice(os.averagePrice, data.links) : 'N/A') + '</td></tr>';
                tableHtml += '</tbody></table><br>';
            }
            */

            // Per-Link Summary
            tableHtml += '<h3>Price Summary Per Link</h3>';
            tableHtml += '<table class="wp-list-table widefat fixed striped">';
            tableHtml += '<thead><tr><th>Identifier</th><th>Currency</th><th>Current</th><th>Lowest</th><th>Highest</th><th>Average</th></tr></thead><tbody>';

            data.links.forEach(function(link) {
                tableHtml += '<tr>';
                tableHtml += '<td>' + (link.identifier || 'N/A') + '</td>';
                tableHtml += '<td>' + (link.currencySymbol || 'N/A') + '</td>';
                tableHtml += '<td>' + (link.summary.currentPrice !== null ? link.currencySymbol + link.summary.currentPrice.toFixed(2) : 'N/A') + '</td>';
                tableHtml += '<td>' + (link.summary.lowestPrice ? link.currencySymbol + link.summary.lowestPrice.price.toFixed(2) + ' on ' + formatDate(link.summary.lowestPrice.date) : 'N/A') + '</td>';
                tableHtml += '<td>' + (link.summary.highestPrice ? link.currencySymbol + link.summary.highestPrice.price.toFixed(2) + ' on ' + formatDate(link.summary.highestPrice.date) : 'N/A') + '</td>';
                tableHtml += '<td>' + (link.summary.averagePrice !== null ? link.currencySymbol + link.summary.averagePrice.toFixed(2) : 'N/A') + '</td>';
                tableHtml += '</tr>';
            });

            tableHtml += '</tbody></table>';
            summaryContainer.html(tableHtml);
        }

        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
        }
        
        function formatPrice(price, links, sourceLinkId = null) {
            if (price === null || price === undefined) return 'N/A';
            let symbol = '$ '; // Default symbol

            if (sourceLinkId && links) {
                const sourceLink = links.find(l => l.trackedLinkId === sourceLinkId);
                if (sourceLink && sourceLink.currencySymbol) {
                    symbol = sourceLink.currencySymbol + ' ';
                }
            } else if (links && links.length > 0 && links[0].currencySymbol) {
                 symbol = links[0].currencySymbol + ' '; 
            }
            return symbol + price.toFixed(2);
        }

    });
})(jQuery); 