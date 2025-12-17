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
    
    // Initialize Tagify for GEO selection (existing functionality)
    if (typeof Tagify !== 'undefined' && $('.hft-geos-tagify').length) {
        $('.hft-geos-tagify').each(function() {
            var input = this;
            var tagify = new Tagify(input, {
                whitelist: hftScraperAdmin.geoWhitelist || [],
                enforceWhitelist: true,
                dropdown: {
                    enabled: 0, // Show dropdown on focus
                    closeOnSelect: false,
                    maxItems: 20,
                    searchKeys: ['value', 'name']
                },
                editTags: false // Prevent editing after tag creation
            });
            
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
        });
    }
    
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
});