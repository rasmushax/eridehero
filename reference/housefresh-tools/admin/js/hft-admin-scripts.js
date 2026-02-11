/**
 * Housefresh Tools Admin Scripts for Meta Boxes.
 */
(function ($) {
    'use strict';

    $(document).ready(function () {
        var repeaterContainer = $('#hft-tracking-links-repeater');
        var rowTemplateHtml = $('#hft-repeater-row-template').html();

        if (!rowTemplateHtml && repeaterContainer.length > 0) { // Check if repeaterContainer exists before erroring for template
            // console.error('HFT: Repeater row template not found, but repeater container exists.');
            // If you don't always have a repeater (e.g. only one link allowed), this check might be too strict.
        }

        // Function to show/hide and enable/disable fields based on parser selection
        function toggleSourceFields(rowElement) {
            var $row = $(rowElement);
            var parserValue = $row.find('.hft-parser-select').val();
            var sourceType = (parserValue === 'amazon') ? 'amazon_asin' : 'website_url';
            
            // Update hidden source type field
            $row.find('.hft-source-type-hidden').val(sourceType);
            
            // Corrected selectors to match the template HTML structure
            var $urlGroup = $row.find('.hft-url-input-group');
            var $asinGroup = $row.find('.hft-asin-input-group');
            var $urlInput = $urlGroup.find('input[name*="[tracking_url]"]'); 
            var $asinInput = $asinGroup.find('input[name*="[amazon_asin]"]');
            var $geoTargetSubfield = $asinGroup.find('.hft-geo-target-subfield'); 
            var $geoTargetSelect = $geoTargetSubfield.find('.hft-geo-target-selector');

            var $forceScrapeButton = $row.find('.hft-force-scrape-button');
            var trackedLinkIdInput = $row.find('input[type="hidden"][name*="[id]"]');
            var trackedLinkId = trackedLinkIdInput.val(); // Get current ID value

            if (sourceType === 'amazon_asin') {
                $urlGroup.hide();
                $urlInput.prop('disabled', true).val(''); // Clear value when hiding and disable
                $asinGroup.show();
                $asinInput.prop('disabled', false);
                $geoTargetSubfield.show();
                $geoTargetSelect.prop('disabled', false);

                // Check if GEO options are already loaded or if it's a new row template part
                if ($geoTargetSelect.find('option').length <= 1 && !$row.data('geos-loaded')) { // <=1 to account for a potential "-- Select --" option
                    // Fetch and populate GEOs
                    $geoTargetSelect.prop('disabled', true).empty().append('<option value="">Loading GEOs...</option>');
                    
                    if (typeof hft_admin_meta_box_l10n === 'undefined' || !hft_admin_meta_box_l10n.ajax_url || !hft_admin_meta_box_l10n.nonce) {
                        console.error('HFT: AJAX data (hft_admin_meta_box_l10n) not available.');
                        $geoTargetSelect.empty().append('<option value="">Error loading GEOs</option>').prop('disabled', false);
                        return;
                    }

                    $.ajax({
                        url: hft_admin_meta_box_l10n.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'hft_get_amazon_geos',
                            nonce: hft_admin_meta_box_l10n.nonce
                        },
                        success: function(response) {
                            $geoTargetSelect.empty().append('<option value="">-- Select --</option>');
                            if (response.success && response.data && Array.isArray(response.data)) {
                                $.each(response.data, function(index, geo) {
                                    $geoTargetSelect.append($('<option>', { value: geo, text: geo }));
                                });
                                $row.data('geos-loaded', true); // Mark as loaded
                            } else {
                                var errorMsg = response.data || 'Failed to load GEOs.';
                                console.error('HFT GEO Load Error:', errorMsg);
                                $geoTargetSelect.append('<option value="">Error</option>'); // Indicate error in select
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            console.error('HFT AJAX Error fetching GEOs:', textStatus, errorThrown);
                            $geoTargetSelect.empty().append('<option value="">-- Select --</option>').append('<option value="">AJAX Error</option>');
                        },
                        complete: function() {
                            $geoTargetSelect.prop('disabled', false);
                        }
                    });
                }
            } else { // Default to website_url or any other type
                $urlGroup.show();
                $urlInput.prop('disabled', false);
                $asinGroup.hide();
                $asinInput.prop('disabled', true).val(''); // Clear value when hiding and disable
                $geoTargetSubfield.hide();
                $geoTargetSelect.prop('disabled', true);
                // $row.data('geos-loaded', false); // Optionally reset if needed when switching away
            }
            // Enable force scrape button only if there's a tracked link ID and it's not a new row template placeholder
            var isActualLink = trackedLinkId && trackedLinkId !== '' && !isNaN(parseInt(trackedLinkId)); // Check if it's a numeric ID
            $forceScrapeButton.prop('disabled', !isActualLink);
        }

        // Function to re-index rows
        function reindexRows() {
            repeaterContainer.find('.hft-tracking-link-row').each(function (rowIndex) {
                var $row = $(this);
                var uniqueRowIdPart = $row.data('id') || ('new_' + rowIndex); // Use data-id or generate one

                var titleText = (typeof hft_admin_meta_box_l10n !== 'undefined' && hft_admin_meta_box_l10n.tracking_link_title) ? hft_admin_meta_box_l10n.tracking_link_title : 'Tracking Link';
                $row.find('h4.hft-row-title').text(titleText + ' ' + (rowIndex + 1)); // Target specific h4

                $row.find('input, select, textarea, label').each(function () {
                    var $el = $(this);
                    ['name', 'id', 'for'].forEach(function (attrName) {
                        var attrVal = $el.attr(attrName);
                        if (attrVal) {
                            // More robust replacement for indices like [rule_XXX] or [new_timestamp] or [0]
                            var newAttrVal = attrVal.replace(/\[(rule_\d+|new_[\d_]+|\d+)\]/g, '[' + uniqueRowIdPart + ']');
                            $el.attr(attrName, newAttrVal);
                        }
                    });
                });
                 toggleSourceFields($row); // Ensure button state is correct after re-index
            });
        }

        // Add new row
        $('#hft-add-tracking-link-button').on('click', function () {
            if (!rowTemplateHtml) {
                alert('Repeater template not found. Cannot add new row.');
                return;
            }
            var newIndex = repeaterContainer.find('.hft-tracking-link-row').length;
            var newRowIdPart = 'new_' + Date.now() + '_' + newIndex; // More unique

            var newRowHtmlProcessed = rowTemplateHtml.replace(/{index}/g, newRowIdPart)
                .replace(/{rowIndexPlus1}/g, (newIndex + 1).toString());

            var $newRow = $(newRowHtmlProcessed);
            $newRow.attr('data-id', newRowIdPart); // Store unique part for re-indexing
            $newRow.attr('data-row-index', newIndex); // Store visual index

            repeaterContainer.append($newRow);
            
            // Populate parser dropdown if available
            if (hft_admin_meta_box_l10n && hft_admin_meta_box_l10n.available_parsers) {
                var $parserSelect = $newRow.find('.hft-parser-select');
                $parserSelect.empty().append('<option value="">Select Parser</option>');
                $.each(hft_admin_meta_box_l10n.available_parsers, function(key, label) {
                    $parserSelect.append($('<option>', { value: key, text: label }));
                });
            }
            
            toggleSourceFields($newRow);
            reindexRows();
        });

        // Delete row
        repeaterContainer.on('click', '.hft-delete-row-button', function () {
            var confirmMsg = (typeof hft_admin_meta_box_l10n !== 'undefined' && hft_admin_meta_box_l10n.confirm_delete_row) ? hft_admin_meta_box_l10n.confirm_delete_row : 'Are you sure you want to delete this row?';
            if (confirm(confirmMsg)) {
                $(this).closest('.hft-tracking-link-row').remove();
                reindexRows();
            }
        });

        // Handle Source Type change
        repeaterContainer.on('change', '.hft-parser-select', function () {
            toggleSourceFields($(this).closest('.hft-tracking-link-row'));
        });

        // Initial setup for existing rows on page load
        repeaterContainer.find('.hft-tracking-link-row').each(function (idx) {
            var $row = $(this);
            // Ensure data-id is set for re-indexing logic
            var trackedIdInput = $row.find('input[type="hidden"][name*="[id]"]');
            var trackedId = trackedIdInput.val();
            if (trackedId && trackedId !== '') {
                 $row.data('id', 'rule_' + trackedId); // Use data() for consistency
            } else {
                 $row.data('id', 'new_' + idx); // Fallback for rows without a saved ID
            }
            toggleSourceFields(this);
        });
        reindexRows();

        // Force Scrape AJAX for CPT Meta Box (Repeater compatible)
        repeaterContainer.on('click', '.hft-force-scrape-button', function(e) {
            e.preventDefault();
            var $button = $(this);
            var $row = $button.closest('.hft-tracking-link-row');
            var $messageDiv = $row.find('.hft-scrape-row-ajax-message');
            
            if (!$messageDiv.length) {
                // Create a message div if it doesn't exist in this row yet
                $messageDiv = $('<div class="hft-scrape-row-ajax-message" style="margin-top: 5px; display: inline-block; padding-left: 10px;"></div>').insertAfter($button);
            }
            $messageDiv.hide().empty(); // Clear previous messages

            var trackedLinkId = $button.data('tracked-link-id'); // Get from button's data attribute
            var productId = typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor') ? wp.data.select('core/editor').getCurrentPostId() : ($('#post_ID').val() || 0);

            if (!productId) {
                $messageDiv.html('<p class="notice notice-error is-inline">Error: Product ID (CPT ID) not found.</p>').show();
                return;
            }
            if (!trackedLinkId) { // Check if trackedLinkId is valid (not empty, not 0)
                $messageDiv.html('<p class="notice notice-error is-inline">Error: Tracked Link ID not found. Save the post first if this is a new link.</p>').show();
                return;
            }

            $button.prop('disabled', true).find('.spinner').addClass('is-active');
            $messageDiv.html('<p class="notice notice-info is-inline">Processing...</p>').show();

            $.ajax({
                url: hft_admin_meta_box_l10n.ajax_url,
                type: 'POST',
                data: {
                    action: 'hft_force_scrape_product',
                    product_id: productId,      // CPT ID
                    tracked_link_id: trackedLinkId, // Specific link ID from button data attribute
                    nonce: hft_admin_meta_box_l10n.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $messageDiv.html('<p class="notice notice-success is-inline">Scrape successful!</p>');
                        if (response.data.updated_display) {
                            var display = response.data.updated_display;

                            // Update last scraped timestamp
                            $row.find('.hft-last-scraped .value').text(display.last_scraped_at || 'N/A');

                            // Check if multi-market prices are returned
                            if (display.market_prices && typeof display.market_prices === 'object') {
                                // Build multi-market HTML
                                var marketHtml = '<div class="hft-market-prices">';
                                $.each(display.market_prices, function(geoKey, market) {
                                    var priceFormatted = parseFloat(market.price).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                    marketHtml += '<div class="hft-market-price-row">';
                                    marketHtml += '<span class="hft-market-currency">' + $('<span>').text(market.currency).html() + '</span> ';
                                    marketHtml += '<span class="hft-market-price-value">' + $('<span>').text(priceFormatted).html() + '</span> ';
                                    marketHtml += '<span class="hft-market-geo">(' + $('<span>').text(geoKey).html() + ')</span>';
                                    marketHtml += ' &mdash; ';
                                    marketHtml += '<span class="hft-market-status">' + $('<span>').text(market.status).html() + '</span>';
                                    marketHtml += '</div>';
                                });
                                marketHtml += '</div>';

                                // Replace single-price display with multi-market display
                                var $statusDisplay = $row.find('.hft-status-display');
                                $statusDisplay.find('.hft-market-prices').remove();
                                // Remove single-price elements and their separator text nodes
                                $statusDisplay.find('.hft-current-price, .hft-current-status').each(function() {
                                    // Remove adjacent pipe separator text nodes
                                    var nextNode = this.nextSibling;
                                    if (nextNode && nextNode.nodeType === 3 && nextNode.textContent.trim() === '|') {
                                        nextNode.parentNode.removeChild(nextNode);
                                    }
                                    $(this).remove();
                                });
                                $statusDisplay.prepend(marketHtml);
                            } else {
                                // Single-price update
                                $row.find('.hft-current-price .value').text(display.current_price_display || 'N/A');
                                $row.find('.hft-current-status .value').text(display.current_status || 'N/A');
                            }
                        }
                        setTimeout(function() { $messageDiv.hide().empty(); }, 4000);
                    } else {
                        var errorMessage = response.data && response.data.message ? response.data.message : 'Unknown error occurred.';
                        $messageDiv.html('<p class="notice notice-error is-inline">Error: ' + errorMessage + '</p>');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    var errorMessage = errorThrown || textStatus;
                    $messageDiv.html('<p class="notice notice-error is-inline">AJAX Error: ' + errorMessage + '</p>');
                },
                complete: function() {
                    // Re-enable button only if it still has a valid tracked_link_id
                    var currentTrackedId = $button.data('tracked-link-id');
                    $button.prop('disabled', !currentTrackedId || currentTrackedId === '').find('.spinner').removeClass('is-active');
                }
            });
        });

        // Check if hft_admin_data and geo_whitelist are available
        if (typeof hft_admin_data !== 'undefined' && typeof hft_admin_data.geo_whitelist !== 'undefined' && typeof Tagify !== 'undefined') {
            
            // Check if we are on the settings page - might not be strictly necessary if hft_admin_data.geo_whitelist is only localized there
            // but good for specificity.
            const urlParams = new URLSearchParams(window.location.search);
            const currentPage = urlParams.get('page');
            const currentTab = urlParams.get('tab');

            if (currentPage === hft_admin_data.settings_page_slug && currentTab === 'scrapers') {
                const geoTagifyInputs = document.querySelectorAll('.hft-geos-tagify');

                geoTagifyInputs.forEach(function(inputEl) {
                    new Tagify(inputEl, {
                        whitelist: hft_admin_data.geo_whitelist,
                        enforceWhitelist: true, // Prevent adding tags not in the whitelist
                        dropdown: {
                            enabled: 0, // Show suggestion list on focus
                            maxItems: hft_admin_data.geo_whitelist.length, // Show all whitelist items in dropdown
                            closeOnSelect: true,
                            highlightFirst: true
                        },
                        editTags: false // Prevent editing tags
                    });
                });
            }
        }

        // Handle Secret Key Remove Button
        $(document).on('click', '.hft-remove-secret-key', function(e) {
            e.preventDefault();
            var $button = $(this);
            var fieldId = $button.data('field');
            var $wrapper = $button.closest('.hft-password-field-wrapper');
            
            if (confirm('Are you sure you want to remove this secret key? This action cannot be undone.')) {
                // Replace the masked input and remove button with password input
                $wrapper.html('<input type="password" id="' + fieldId + '" name="hft_settings[' + fieldId + ']" value="" class="regular-text">' +
                             '<input type="hidden" name="hft_settings[' + fieldId + '_removed]" value="true">');
                
                // Add a notice to inform user to save changes
                if (!$wrapper.next('.notice.inline').length) {
                    var $notice = $('<div class="notice notice-warning inline"><p>Secret key removed. Click "Save API & General Settings" to confirm removal.</p></div>');
                    $wrapper.after($notice);
                }
                
                // Focus on the password field to indicate it's now editable
                $wrapper.find('input[type="password"]').focus();
            }
        });

    }); // end document.ready

})(jQuery);