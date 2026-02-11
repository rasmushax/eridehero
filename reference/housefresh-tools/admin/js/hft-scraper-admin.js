jQuery(document).ready(function($) {
    'use strict';

    // Cache DOM elements
    const $testButton = $('#test-scraper');
    const $viewSourceButton = $('#view-source');
    const $testUrl = $('#test-url');
    const $testResults = $('#test-results');
    const $sourceModal = $('#hft-source-modal');

    // WordPress Media Uploader for Logo
    let logoMediaFrame;

    $('#upload-logo').on('click', function(e) {
        e.preventDefault();

        // If the media frame already exists, reopen it
        if (logoMediaFrame) {
            logoMediaFrame.open();
            return;
        }

        // Create the media frame
        logoMediaFrame = wp.media({
            title: hftScraperAdmin.strings.selectLogo || 'Select Logo',
            button: {
                text: hftScraperAdmin.strings.useLogo || 'Use this logo'
            },
            library: {
                type: 'image'
            },
            multiple: false
        });

        // When an image is selected, run a callback
        logoMediaFrame.on('select', function() {
            const attachment = logoMediaFrame.state().get('selection').first().toJSON();

            // Set the attachment ID in the hidden field
            $('#logo_attachment_id').val(attachment.id);

            // Update the preview
            const imageUrl = attachment.sizes && attachment.sizes.thumbnail
                ? attachment.sizes.thumbnail.url
                : attachment.url;

            $('#logo-preview img').attr('src', imageUrl);
            $('#logo-preview').show();
            $('#upload-logo').hide();
        });

        // Open the media frame
        logoMediaFrame.open();
    });

    // Remove logo
    $('#remove-logo').on('click', function(e) {
        e.preventDefault();

        // Clear the attachment ID
        $('#logo_attachment_id').val('');

        // Hide preview and show upload button
        $('#logo-preview').hide();
        $('#logo-preview img').attr('src', '');
        $('#upload-logo').show();
    });

    // Toggle regex fields from existing functionality
    $('.hft-regex-toggle').on('change', function() {
        const field = $(this).data('field');
        const optionsDiv = $('#regex_options_' + field);
        
        if ($(this).is(':checked')) {
            optionsDiv.slideDown();
        } else {
            optionsDiv.slideUp();
        }
    });
    
    // Toggle ScrapingRobot JS rendering option
    $('#use_scrapingrobot').on('change', function() {
        const $jsOption = $('.scrapingrobot-js-option');
        
        if ($(this).is(':checked')) {
            $jsOption.slideDown();
        } else {
            $jsOption.slideUp();
            // Optionally uncheck the JS rendering when ScrapingRobot is disabled
            $('#scrapingrobot_render_js').prop('checked', false);
        }
    });
    
    // Store tagify instance globally for geo group buttons
    let hftGeosTagify = null;
    const geoGroups = hftScraperAdmin.geoGroups || {};

    // Initialize Tagify for GEO selection (existing functionality)
    if (typeof Tagify !== 'undefined' && $('.hft-geos-tagify').length) {
        $('.hft-geos-tagify').each(function() {
            var input = this;
            var tagify = new Tagify(input, {
                whitelist: hftScraperAdmin.geoWhitelist || [],
                enforceWhitelist: false, // Allow non-whitelist values (for group country codes)
                dropdown: {
                    enabled: 0, // Show dropdown on focus
                    closeOnSelect: false,
                    maxItems: 20,
                    searchKeys: ['value', 'name']
                },
                editTags: false // Prevent editing after tag creation
            });

            // Store reference for geo group buttons
            hftGeosTagify = tagify;
            window.hftGeosTagify = tagify; // Also expose globally

            // Convert initial value
            var initialValue = $(input).val();
            if (initialValue) {
                if (initialValue.startsWith('[')) {
                    // It's JSON format
                    try {
                        var tags = JSON.parse(initialValue);
                        tagify.removeAllTags();
                        tagify.addTags(tags);
                    } catch (e) {
                        console.error('Failed to parse initial GEO tags:', e);
                    }
                } else if (initialValue.includes(',')) {
                    // It's comma-separated
                    var geos = initialValue.split(',').map(function(geo) {
                        return geo.trim();
                    }).filter(function(geo) {
                        return geo.length > 0;
                    });
                    tagify.removeAllTags();
                    tagify.addTags(geos);
                } else if (initialValue.trim()) {
                    // Single value
                    tagify.removeAllTags();
                    tagify.addTags([initialValue.trim()]);
                }
            }

            // Update group button states when tags change
            tagify.on('add remove', function() {
                updateGeoGroupButtonStates();
            });

            // Initial update of button states
            updateGeoGroupButtonStates();
        });
    }

    /**
     * Update the active state of geo group buttons based on current tags
     */
    function updateGeoGroupButtonStates() {
        if (!hftGeosTagify) return;

        const currentTags = hftGeosTagify.value.map(tag => tag.value.toUpperCase());

        $('.hft-geo-group-btn').each(function() {
            const $btn = $(this);
            const groupKey = $btn.data('group');
            const group = geoGroups[groupKey];

            if (!group) return;

            // Check if all countries in the group are present
            let allPresent = true;
            if (groupKey === 'GLOBAL') {
                allPresent = currentTags.includes('GLOBAL');
            } else {
                for (const country of group.countries) {
                    if (!currentTags.includes(country.toUpperCase())) {
                        allPresent = false;
                        break;
                    }
                }
            }

            if (allPresent && group.countries.length > 0) {
                $btn.addClass('active');
            } else {
                $btn.removeClass('active');
            }
        });
    }

    // Geo group button click handler
    $(document).on('click', '.hft-geo-group-btn', function(e) {
        e.preventDefault();

        if (!hftGeosTagify) {
            console.warn('Tagify not initialized');
            return;
        }

        const groupKey = $(this).data('group');
        const group = geoGroups[groupKey];

        if (!group || !group.countries) {
            console.warn('Invalid geo group:', groupKey);
            return;
        }

        const currentTags = hftGeosTagify.value.map(tag => tag.value.toUpperCase());

        // Check if this group is already fully added (toggle behavior)
        let allPresent = true;
        if (groupKey === 'GLOBAL') {
            allPresent = currentTags.includes('GLOBAL');
        } else {
            for (const country of group.countries) {
                if (!currentTags.includes(country.toUpperCase())) {
                    allPresent = false;
                    break;
                }
            }
        }

        if (allPresent) {
            // Remove all countries in this group
            group.countries.forEach(function(country) {
                hftGeosTagify.removeTag(country);
            });
        } else {
            // Add countries that aren't already present
            const newTags = group.countries.filter(function(country) {
                return !currentTags.includes(country.toUpperCase());
            });

            if (newTags.length > 0) {
                hftGeosTagify.addTags(newTags);
            }
        }

        // Update button states
        updateGeoGroupButtonStates();
    });

    // Clear all GEOs button
    $(document).on('click', '.hft-geo-clear-btn', function(e) {
        e.preventDefault();

        if (!hftGeosTagify) return;

        hftGeosTagify.removeAllTags();
        updateGeoGroupButtonStates();
    });
    
    // Test full scraper
    $testButton.on('click', function(e) {
        e.preventDefault();
        
        const url = $testUrl.val().trim();
        if (!url) {
            alert(hftScraperAdmin.strings.enterUrl || 'Please enter a test URL');
            return;
        }
        
        const scraperId = $('input[name="scraper_id"]').val() || 0;
        
        // Show loading state
        $testButton.prop('disabled', true).text(hftScraperAdmin.strings.testInProgress || 'Testing...');
        $testResults.html('<div class="hft-spinner"></div> Running test...');
        
        // Prepare data
        const data = {
            action: 'hft_test_scraper',
            nonce: hftScraperAdmin.nonce,
            scraper_id: scraperId,
            test_url: url
        };
        
        // If new scraper, include form data
        if (!scraperId) {
            data.domain = $('#domain').val();
            data.name = $('#name').val();
            data.use_base_parser = $('#use_base_parser').is(':checked') ? 1 : 0;
            data.use_curl = $('#use_curl').is(':checked') ? 1 : 0;
            data.use_scrapingrobot = $('#use_scrapingrobot').is(':checked') ? 1 : 0;
            data.scrapingrobot_render_js = $('#scrapingrobot_render_js').is(':checked') ? 1 : 0;
            
            // Add rules
            $('.hft-scraper-rule').each(function() {
                const fieldType = $(this).find('h3').text().toLowerCase();
                const xpath = $(this).find('[name*="[xpath]"]').val();
                
                if (xpath) {
                    data['rule_' + fieldType + '_xpath'] = xpath;
                    data['rule_' + fieldType + '_attribute'] = $(this).find('[name*="[attribute]"]').val();
                    
                    // Add post-processing
                    const pp = {};
                    $(this).find('[name*="[post_processing]"]:checked').each(function() {
                        pp[$(this).val()] = true;
                    });
                    data['rule_' + fieldType + '_post_processing'] = pp;
                }
            });
        }
        
        // Make AJAX request
        $.post(hftScraperAdmin.ajaxUrl, data)
            .done(function(response) {
                if (response.success) {
                    displayTestResults(response.data);
                } else {
                    displayError(response.data.message);
                }
            })
            .fail(function() {
                displayError(hftScraperAdmin.strings.testError || 'Test failed. Please check your settings.');
            })
            .always(function() {
                $testButton.prop('disabled', false).text(hftScraperAdmin.strings.testButton || 'Test Scraper');
            });
    });
    
    // View HTML source
    $viewSourceButton.on('click', function(e) {
        e.preventDefault();
        
        const url = $testUrl.val().trim();
        if (!url) {
            alert(hftScraperAdmin.strings.enterUrl || 'Please enter a test URL');
            return;
        }
        
        const scraperId = $('input[name="scraper_id"]').val() || 0;
        
        // Show loading state
        $viewSourceButton.addClass('loading').prop('disabled', true).text('Fetching...');
        
        // Make AJAX request
        $.post(hftScraperAdmin.ajaxUrl, {
            action: 'hft_view_source',
            nonce: hftScraperAdmin.nonce,
            scraper_id: scraperId,
            test_url: url
        })
        .done(function(response) {
            if (response.success) {
                displaySourceModal(response.data);
            } else {
                alert('Failed to fetch source: ' + (response.data.message || 'Unknown error'));
            }
        })
        .fail(function() {
            alert('Failed to fetch page source. Please try again.');
        })
        .always(function() {
            $viewSourceButton.removeClass('loading').prop('disabled', false).text('View Source');
        });
    });
    
    // Close modal handlers
    $('#hft-source-modal-close, .hft-modal').on('click', function(e) {
        if (e.target === this) {
            $sourceModal.hide();
        }
    });
    
    // Copy source to clipboard
    $('#hft-copy-source').on('click', function() {
        const sourceText = $('#hft-source-content').text();
        
        // Modern clipboard API
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(sourceText).then(function() {
                showCopyFeedback();
            }).catch(function() {
                fallbackCopyToClipboard(sourceText);
            });
        } else {
            fallbackCopyToClipboard(sourceText);
        }
    });
    
    // Download source as file
    $('#hft-download-source').on('click', function() {
        const sourceText = $('#hft-source-content').text();
        const url = $('#hft-source-url').text();
        const filename = 'source-' + new URL(url).hostname + '-' + Date.now() + '.html';
        
        const blob = new Blob([sourceText], { type: 'text/html' });
        const downloadUrl = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = downloadUrl;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(downloadUrl);
    });
    
    // Escape key closes modal
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $sourceModal.is(':visible')) {
            $sourceModal.hide();
        }
    });
    
    // Test individual selector
    $(document).on('click', '.test-selector', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const $section = $button.closest('.hft-scraper-rule');
        const fieldType = $section.find('h3').text().toLowerCase();
        const xpath = $section.find('[name*="[xpath]"]').val();
        const attribute = $section.find('[name*="[attribute]"]').val();
        const url = $testUrl.val().trim();
        
        if (!url) {
            alert('Please enter a test URL first');
            return;
        }
        
        if (!xpath) {
            alert('Please enter an XPath selector');
            return;
        }
        
        // Show loading
        const originalText = $button.text();
        $button.prop('disabled', true).html('<span class="hft-spinner"></span>');
        
        // Get post-processing options
        const postProcessing = {};
        $section.find('[name*="[post_processing]"]:checked').each(function() {
            postProcessing[$(this).val()] = true;
        });
        
        // Get scraper ID if available
        const scraperId = $('input[name="scraper_id"]').val() || 0;
        
        // Test selector
        $.post(hftScraperAdmin.ajaxUrl, {
            action: 'hft_test_selector',
            nonce: hftScraperAdmin.nonce,
            scraper_id: scraperId,
            test_url: url,
            xpath: xpath,
            attribute: attribute,
            field_type: fieldType,
            post_processing: postProcessing
        })
        .done(function(response) {
            if (response.success) {
                displaySelectorResult($section, response.data);
            } else {
                displaySelectorError($section, response.data.message);
            }
        })
        .fail(function() {
            displaySelectorError($section, 'Test failed');
        })
        .always(function() {
            $button.prop('disabled', false).text(originalText);
        });
    });
    
    // Quick test from list page
    $('.hft-test-scraper').on('click', function(e) {
        e.preventDefault();
        
        const scraperId = $(this).data('scraper-id');
        const url = prompt('Enter a product URL to test:');
        
        if (!url) {
            return;
        }
        
        const $row = $(this).closest('tr');
        const $statusCell = $row.find('.column-success_rate');
        const originalStatus = $statusCell.html();
        
        $statusCell.html('<span class="hft-spinner"></span>');
        
        $.post(hftScraperAdmin.ajaxUrl, {
            action: 'hft_test_scraper',
            nonce: hftScraperAdmin.nonce,
            scraper_id: scraperId,
            test_url: url
        })
        .done(function(response) {
            if (response.success) {
                const result = response.data;
                let message = 'Test Results:\n\n';
                
                if (result.data.price !== null) {
                    message += 'Price: ' + result.data.price + ' ' + (result.data.currency || '') + '\n';
                }
                if (result.data.status) {
                    message += 'Status: ' + result.data.status + '\n';
                }
                if (result.data.shipping_info) {
                    message += 'Shipping: ' + result.data.shipping_info + '\n';
                }
                if (result.data.error) {
                    message += '\nError: ' + result.data.error;
                }
                
                message += '\n\nExecution time: ' + result.execution_time + 's';
                
                alert(message);
            } else {
                alert('Test failed: ' + response.data.message);
            }
        })
        .fail(function() {
            alert('Test request failed');
        })
        .always(function() {
            $statusCell.html(originalStatus);
        });
    });
    
    // Display test results
    function displayTestResults(data) {
        let html = '<div class="hft-test-results ' + (data.success ? 'success' : 'error') + '">';
        html += '<h4>Test Results</h4>';
        
        if (data.data) {
            html += '<table class="widefat">';
            html += '<tr><th>Field</th><th>Value</th></tr>';
            
            if (data.data.price !== null) {
                html += '<tr><td>Price</td><td>' + data.data.price + '</td></tr>';
            }
            if (data.data.currency) {
                html += '<tr><td>Currency</td><td>' + data.data.currency + '</td></tr>';
            }
            if (data.data.status) {
                html += '<tr><td>Status</td><td>' + data.data.status + '</td></tr>';
            }
            if (data.data.shipping_info) {
                html += '<tr><td>Shipping</td><td>' + data.data.shipping_info + '</td></tr>';
            }
            if (data.data.error) {
                html += '<tr><td>Error</td><td class="error">' + data.data.error + '</td></tr>';
            }
            
            html += '</table>';
        }
        
        html += '<p>Execution time: ' + data.execution_time + ' seconds</p>';
        html += '</div>';
        
        $testResults.html(html);
    }
    
    // Display error
    function displayError(message) {
        const html = '<div class="hft-test-results error">' +
                    '<h4>Error</h4>' +
                    '<p>' + message + '</p>' +
                    '</div>';
        $testResults.html(html);
    }
    
    // Display selector test result
    function displaySelectorResult($section, data) {
        let html = '<div class="selector-test-result success">';
        
        if (data.value !== null) {
            html += '<strong>Extracted:</strong> ' + escapeHtml(data.value);
            
            if (data.raw_value !== data.value) {
                html += '<br><small>Raw: ' + escapeHtml(data.raw_value) + '</small>';
            }
        } else {
            html += '<strong>No value found</strong>';
        }
        
        if (data.match_count > 0) {
            html += '<br><small>Matches: ' + data.match_count + '</small>';
            
            if (data.all_values && data.all_values.length > 1) {
                html += '<br><small>Other values: ' + data.all_values.slice(1).map(escapeHtml).join(', ') + '</small>';
            }
        }
        
        html += '</div>';
        
        // Remove any existing result
        $section.find('.selector-test-result').remove();
        
        // Add new result after the test button's row
        $section.find('.test-selector').closest('tr').after('<tr><td colspan="2">' + html + '</td></tr>');
        
        // Auto-hide after 10 seconds
        setTimeout(function() {
            $section.find('.selector-test-result').fadeOut(function() {
                $(this).closest('tr').remove();
            });
        }, 10000);
    }
    
    // Display selector error
    function displaySelectorError($section, message) {
        const html = '<div class="selector-test-result error">' +
                    '<strong>Error:</strong> ' + escapeHtml(message) +
                    '</div>';
        
        $section.find('.selector-test-result').remove();
        $section.find('.test-selector').closest('tr').after('<tr><td colspan="2">' + html + '</td></tr>');
    }
    
    // Display source modal
    function displaySourceModal(data) {
        // Populate modal data
        $('#hft-source-url').text(data.url);
        $('#hft-source-method').text(data.fetch_method);
        $('#hft-source-time').text(data.execution_time);
        $('#hft-source-size').text(formatBytes(data.html_size));
        $('#hft-source-content').text(data.html);
        
        // Show modal
        $sourceModal.show();
        
        // Focus on close button for accessibility
        $('#hft-source-modal-close').focus();
    }
    
    // Format bytes to human readable
    function formatBytes(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    // Show copy feedback
    function showCopyFeedback() {
        const $button = $('#hft-copy-source');
        const originalText = $button.text();
        
        // Create or update feedback element
        let $feedback = $button.siblings('.hft-copy-feedback');
        if ($feedback.length === 0) {
            $feedback = $('<span class="hft-copy-feedback">Copied!</span>');
            $button.after($feedback);
        }
        
        // Show feedback
        $feedback.text('Copied!').addClass('show');
        $button.text('Copied!');
        
        // Reset after 2 seconds
        setTimeout(function() {
            $feedback.removeClass('show');
            $button.text(originalText);
        }, 2000);
    }
    
    // Fallback copy to clipboard for older browsers
    function fallbackCopyToClipboard(text) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            const successful = document.execCommand('copy');
            if (successful) {
                showCopyFeedback();
            } else {
                alert('Failed to copy to clipboard');
            }
        } catch (err) {
            console.error('Fallback copy failed:', err);
            alert('Copy to clipboard not supported by your browser');
        }
        
        document.body.removeChild(textArea);
    }
    
    // Escape HTML
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // ===========================================
    // Enhanced Rule UI - Dynamic Rule Management
    // ===========================================

    // Track rule indices per field type
    const ruleIndices = {};

    // Initialize rule indices from existing rules
    $('.hft-rules-container').each(function() {
        const fieldType = $(this).attr('id').replace('rules-', '');
        ruleIndices[fieldType] = $(this).find('.hft-scraper-rule').length;
    });

    // Add new rule button handler
    $(document).on('click', '.hft-add-rule', function(e) {
        e.preventDefault();

        const fieldType = $(this).data('field');
        const $container = $('#rules-' + fieldType);
        const template = $('#hft-rule-template').html();

        if (!template) {
            console.error('Rule template not found');
            return;
        }

        // Get next index
        const index = ruleIndices[fieldType] || 0;
        ruleIndices[fieldType] = index + 1;

        // Replace placeholders in template
        let newRule = template
            .replace(/__FIELD__/g, fieldType)
            .replace(/__INDEX__/g, index);

        // Set default priority based on number of existing rules
        const existingRules = $container.find('.hft-scraper-rule').length;
        const defaultPriority = (existingRules + 1) * 10;

        // Create element and set priority
        const $newRule = $(newRule);
        $newRule.find('input[name*="[priority]"]').val(defaultPriority);

        // Add to container
        $container.append($newRule);

        // Animate in
        $newRule.hide().slideDown();

        // Initialize mode toggle for new rule
        updateModeVisibility($newRule, false);
    });

    // Remove rule button handler
    $(document).on('click', '.hft-remove-rule', function(e) {
        e.preventDefault();

        const $rule = $(this).closest('.hft-scraper-rule');
        const $container = $rule.parent();

        // Don't remove if it's the last rule in the container
        if ($container.find('.hft-scraper-rule').length <= 1) {
            // Clear the fields instead
            $rule.find('input[type="text"], textarea').val('');
            $rule.find('input[type="checkbox"]').prop('checked', false);
            $rule.find('select').val('xpath');
            updateModeVisibility($rule);
            return;
        }

        // Animate out and remove
        $rule.slideUp(function() {
            $(this).remove();
        });
    });

    // Extraction mode change handler
    $(document).on('change', '.hft-extraction-mode-select', function() {
        const $rule = $(this).closest('.hft-scraper-rule');
        updateModeVisibility($rule, true);
    });

    // Update visibility based on extraction mode
    // animate=true for user interactions, false for initial page load
    function updateModeVisibility($rule, animate) {
        const mode = $rule.find('.hft-extraction-mode-select').val() || 'xpath';
        const showFn = animate ? 'slideDown' : 'show';
        const hideFn = animate ? 'slideUp' : 'hide';

        // Show/hide regex extraction rows
        const $regexRows = $rule.find('.hft-regex-extraction-row');
        const $booleanRow = $rule.find('.hft-boolean-row');

        if (mode === 'xpath_regex') {
            // Remove inline display:none first, then animate
            $regexRows.css('display', '').hide()[showFn]();
            $booleanRow.css('display', '').hide()[showFn]();
        } else {
            $regexRows[hideFn]();
            $booleanRow[hideFn]();
        }

        // Show/hide attribute row (hide for json_path)
        const $attrRow = $rule.find('.hft-attribute-row');
        if (mode === 'json_path') {
            $attrRow[hideFn]();
        } else {
            $attrRow.css('display', '').hide()[showFn]();
        }

        // Update mode hints
        $rule.find('.hft-mode-hint').hide();
        $rule.find('.hft-mode-' + mode).show();
    }

    // Boolean mode toggle handler
    $(document).on('change', '.hft-return-boolean-toggle', function() {
        const $values = $(this).closest('td').find('.hft-boolean-values');

        if ($(this).is(':checked')) {
            $values.slideDown();
        } else {
            $values.slideUp();
        }
    });

    // Post-processing regex toggle handler
    $(document).on('change', '.hft-post-regex-toggle', function() {
        const $options = $(this).closest('td').find('.hft-post-regex-options');

        if ($(this).is(':checked')) {
            $options.slideDown();
        } else {
            $options.slideUp();
        }
    });

    // Initialize mode visibility for existing rules on page load
    $('.hft-scraper-rule').each(function() {
        updateModeVisibility($(this), false);
    });

    // Updated test selector for new rule structure
    $(document).on('click', '.test-selector', function(e) {
        e.preventDefault();

        const $button = $(this);
        const $rule = $button.closest('.hft-scraper-rule');
        const $group = $button.closest('.hft-scraper-rule-group');
        const fieldType = $group.data('field');

        const xpath = $rule.find('input[name*="[xpath]"]').val();
        const attribute = $rule.find('input[name*="[attribute]"]').val();
        const extractionMode = $rule.find('select[name*="[extraction_mode]"]').val();
        const regexPattern = $rule.find('input[name*="[regex_pattern]"]').val();
        const url = $testUrl.val().trim();

        if (!url) {
            alert('Please enter a test URL first');
            return;
        }

        if (!xpath) {
            alert('Please enter a selector');
            return;
        }

        // Show loading
        const originalText = $button.text();
        $button.prop('disabled', true).html('<span class="hft-spinner"></span>');

        // Get scraper ID if available
        const scraperId = $('input[name="scraper_id"]').val() || 0;

        // Test selector with enhanced parameters
        $.post(hftScraperAdmin.ajaxUrl, {
            action: 'hft_test_selector',
            nonce: hftScraperAdmin.nonce,
            scraper_id: scraperId,
            test_url: url,
            xpath: xpath,
            attribute: attribute,
            field_type: fieldType,
            extraction_mode: extractionMode,
            regex_pattern: regexPattern
        })
        .done(function(response) {
            const $result = $rule.find('.hft-test-result');
            if (response.success) {
                let text = response.data.value !== null
                    ? 'Found: ' + response.data.value
                    : 'No match';
                $result.removeClass('error').addClass('success').text(text);
            } else {
                $result.removeClass('success').addClass('error').text('Error: ' + response.data.message);
            }

            // Clear result after 10 seconds
            setTimeout(function() {
                $result.text('').removeClass('success error');
            }, 10000);
        })
        .fail(function() {
            $rule.find('.hft-test-result').removeClass('success').addClass('error').text('Test failed');
        })
        .always(function() {
            $button.prop('disabled', false).text(originalText);
        });
    });

    // ===========================================
    // Shopify Markets UI
    // ===========================================

    const $shopifyMarketsCheckbox = $('#shopify_markets');
    const $shopifyOptions = $('.hft-shopify-option');
    const $shopifyApiOptions = $('.hft-shopify-api-option');
    const $shopifyMethodRadios = $('input[name="shopify_method"]');

    // Toggle Shopify Markets section visibility
    $shopifyMarketsCheckbox.on('change', function() {
        if ($(this).is(':checked')) {
            $shopifyOptions.slideDown();
            // Show/hide API fields based on current method
            updateShopifyApiVisibility();
        } else {
            $shopifyOptions.slideUp();
        }
    });

    // Toggle Storefront API fields based on method selection
    $shopifyMethodRadios.on('change', function() {
        updateShopifyApiVisibility();
    });

    function updateShopifyApiVisibility() {
        const method = $('input[name="shopify_method"]:checked').val();
        if (method === 'api') {
            $shopifyApiOptions.slideDown();
        } else {
            $shopifyApiOptions.slideUp();
        }
    }

    // Auto-detect Shopify API settings button handler
    $('#hft-autodetect-shopify').on('click', function(e) {
        e.preventDefault();

        var $button = $(this);
        var $status = $('#hft-autodetect-status');
        var domain = $('#domain').val().trim();

        if (!domain) {
            alert('Please enter a domain first.');
            return;
        }

        $button.prop('disabled', true);
        $status.html('<span class="spinner is-active" style="float: none; margin: 0;"></span> Detecting...');

        $.post(hftScraperAdmin.ajaxUrl, {
            action: 'hft_autodetect_shopify',
            nonce: hftScraperAdmin.nonce,
            domain: domain
        })
        .done(function(response) {
            if (response.success) {
                var messages = [];
                if (response.data.api_token) {
                    $('#shopify_storefront_token').val(response.data.api_token);
                    messages.push('Token found');
                }
                if (response.data.shop_domain) {
                    $('#shopify_shop_domain').val(response.data.shop_domain);
                    messages.push('Domain found');
                }
                $status.html('<span style="color: green;">' + escapeHtml(messages.join(', ')) + '</span>');
            } else {
                $status.html('<span style="color: red;">' + escapeHtml(response.data ? response.data.message : 'Detection failed') + '</span>');
            }
        })
        .fail(function() {
            $status.html('<span style="color: red;">Request failed</span>');
        })
        .always(function() {
            $button.prop('disabled', false);
            setTimeout(function() { $status.empty(); }, 10000);
        });
    });

    // Detect Markets button handler
    $('#hft-detect-markets').on('click', function(e) {
        e.preventDefault();

        const $button = $(this);
        const $resultsContainer = $('#hft-detect-markets-results');
        const $resultsBody = $('#hft-markets-table tbody');

        // Get test URL
        const testUrl = $testUrl.val().trim();
        if (!testUrl) {
            alert(hftScraperAdmin.strings.detectMarketsNoUrl);
            return;
        }

        // Get current GEOs from Tagify
        let geos = '';
        if (hftGeosTagify && hftGeosTagify.value.length > 0) {
            geos = hftGeosTagify.value.map(tag => tag.value).join(',');
        }
        if (!geos) {
            alert(hftScraperAdmin.strings.detectMarketsNoGeos);
            return;
        }

        // Get method and API settings
        const method = $('input[name="shopify_method"]:checked').val() || 'cookie';
        const apiToken = $('#shopify_storefront_token').val().trim();
        const shopDomain = $('#shopify_shop_domain').val().trim();

        // Validate API fields if API method
        if (method === 'api' && (!apiToken || !shopDomain)) {
            alert('Storefront API Token and Shop Domain are required for API method.');
            return;
        }

        // Show loading state
        $button.prop('disabled', true).text(hftScraperAdmin.strings.detectingMarkets);
        $resultsBody.empty();
        $resultsContainer.show();
        $resultsBody.html('<tr><td colspan="5" style="text-align: center;"><span class="spinner is-active" style="float: none;"></span> ' + hftScraperAdmin.strings.detectingMarkets + '</td></tr>');

        // Make AJAX request
        $.post(hftScraperAdmin.ajaxUrl, {
            action: 'hft_detect_shopify_markets',
            nonce: hftScraperAdmin.nonce,
            test_url: testUrl,
            method: method,
            geos: geos,
            api_token: apiToken,
            shop_domain: shopDomain
        })
        .done(function(response) {
            if (response.success && response.data.results) {
                renderMarketResults(response.data.results, $resultsBody);
            } else {
                $resultsBody.html('<tr><td colspan="5" class="error">' +
                    escapeHtml(response.data ? response.data.message : hftScraperAdmin.strings.detectMarketsFailed) +
                    '</td></tr>');
            }
        })
        .fail(function() {
            $resultsBody.html('<tr><td colspan="5" class="error">' +
                hftScraperAdmin.strings.detectMarketsFailed +
                '</td></tr>');
        })
        .always(function() {
            $button.prop('disabled', false).text(hftScraperAdmin.strings.detectMarkets);
        });
    });

    /**
     * Render market detection results into the table
     */
    function renderMarketResults(results, $tbody) {
        $tbody.empty();

        if (!results || results.length === 0) {
            $tbody.html('<tr><td colspan="5">No markets detected.</td></tr>');
            return;
        }

        results.forEach(function(market) {
            const isSuccess = market.success;
            const icon = isSuccess ? '<span style="color: green;">&#10003;</span>' : '<span style="color: red;">&#10007;</span>';
            const countriesList = (market.countries || []).join(', ');
            const priceDisplay = market.price ? (market.price + ' ' + (market.currency || '')) : '-';
            const statusClass = isSuccess ? '' : ' style="color: #999;"';

            let statusText = '';
            if (isSuccess) {
                statusText = '<span style="color: green;">Available</span>';
            } else {
                statusText = '<span style="color: red;">' + escapeHtml(market.error || 'Failed') + '</span>';
            }

            const row = '<tr' + statusClass + '>' +
                '<td>' + icon + '</td>' +
                '<td><strong>' + escapeHtml(market.expected_currency || '?') + '</strong></td>' +
                '<td>' + escapeHtml(countriesList) + '</td>' +
                '<td>' + escapeHtml(priceDisplay) + '</td>' +
                '<td>' + statusText + '</td>' +
                '</tr>';

            $tbody.append(row);
        });
    }
});