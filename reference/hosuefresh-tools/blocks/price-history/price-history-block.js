/**
 * Price History Block JavaScript
 * 
 * This script handles:
 * 1. Loading Chart.js from CDN
 * 2. Detecting user's GEO location
 * 3. Fetching price history data via REST API
 * 4. Rendering the Chart.js line chart with tooltips
 */

document.addEventListener('DOMContentLoaded', function() {
    // Find all price history containers on the page
    const containers = document.querySelectorAll('.hft-price-history-container');
    
    if (containers.length === 0) {
        return;
    }

    // Check for dependencies
    if (typeof hft_frontend_data === 'undefined' || typeof hft_frontend_data.rest_url === 'undefined') {
        containers.forEach(container => {
            container.style.display = 'none';
        });
        return;
    }

    // Load Chart.js from CDN
    loadChartJS().then(() => {
        // Process each container
        containers.forEach(container => {
            initializePriceHistory(container);
        });
    }).catch(error => {
        containers.forEach(container => {
            container.style.display = 'none';
        });
    });
});

/**
 * Load Chart.js from CDN
 */
function loadChartJS() {
    return new Promise((resolve, reject) => {
        // Check if Chart.js is already loaded
        if (window.Chart) {
            resolve();
            return;
        }

        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js';
        script.onload = () => {
            // Load the time scale adapter for Chart.js
            const adapter = document.createElement('script');
            adapter.src = 'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js';
            adapter.onload = () => resolve();
            adapter.onerror = () => reject(new Error('Failed to load Chart.js time adapter'));
            document.head.appendChild(adapter);
        };
        script.onerror = () => reject(new Error('Failed to load Chart.js'));
        document.head.appendChild(script);
    });
}

/**
 * Initialize price history for a single container
 */
function initializePriceHistory(container) {
    const productId = container.dataset.hftProductId;
    const blockId = container.dataset.hftBlockId;
    
    if (!productId) {
        container.style.display = 'none';
        return;
    }

    // Create initial chart with placeholder data
    const chart = createInitialChart(container);

    // Show loading state
    showLoading(container);

    // First, get user's GEO location
    getUserGeo().then(targetGeo => {
        // Fetch price history data
        return fetchPriceHistoryData(productId, targetGeo);
    }).then(data => {
        if (data && data.links && data.links.length > 0) {
            updateChart(container, data, chart);
        } else {
            container.style.display = 'none';
        }
    }).catch(error => {
        container.style.display = 'none';
    });
}

/**
 * Create initial chart with empty data but proper styling
 */
function createInitialChart(container) {
    const canvas = container.querySelector('.hft-price-history-chart');
    const ctx = canvas.getContext('2d');
    
    // Initial chart configuration with no data
    const config = {
        type: 'line',
        data: {
            datasets: [] // Empty dataset - no line shown
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    enabled: false // Disable tooltips until we have data
                }
            },
            scales: {
                x: {
                    type: 'time',
                    time: {
                        unit: 'day',
                        displayFormats: {
                            day: 'MMM d',
                            week: 'MMM d',
                            month: 'MMM yyyy'
                        }
                    },
                    title: {
                        display: false
                    },
                    grid: {
                        display: false // No vertical grid lines
                    },
                    ticks: {
                        color: '#6b7280',
                        font: {
                            size: 12,
                            family: 'Inter'
                        },
                        maxTicksLimit: 8
                    }
                },
                y: {
                    beginAtZero: false,
                    suggestedMin: 0,
                    suggestedMax: 200, // Default range for empty chart
                    title: {
                        display: false
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                        lineWidth: 1
                    },
                    border: {
                        display: false
                    },
                    ticks: {
                        color: '#6b7280',
                        font: {
                            size: 12,
                            family: 'Inter'
                        },
                        callback: function(value) {
                            return '$' + value.toFixed(0);
                        }
                    }
                }
            }
        }
    };
    
    return new Chart(ctx, config);
}

/**
 * Get user's GEO location using shared function
 */
function getUserGeo() {
    // Use the shared HFT_Frontend.detectUserGeo function if available
    if (typeof window.HFT_Frontend !== 'undefined' && typeof window.HFT_Frontend.detectUserGeo === 'function') {
        return window.HFT_Frontend.detectUserGeo();
    }
    
    // Fallback for direct API call if shared function not available
    if (typeof hft_frontend_data === 'undefined' || typeof hft_frontend_data.rest_url === 'undefined') {
        return Promise.resolve('US');
    }
    
    const geoUrl = hft_frontend_data.rest_url + 'housefresh-tools/v1/detect-geo';
    
    // Create AbortController for timeout
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout
    
    return fetch(geoUrl, {
        signal: controller.signal
    })
        .then(response => {
            clearTimeout(timeoutId);
            return response.json();
        })
        .then(data => data.country_code || 'US')
        .catch(error => {
            clearTimeout(timeoutId);
            return 'US';
        });
}

/**
 * Fetch price history data from REST API
 */
function fetchPriceHistoryData(productId, targetGeo) {
    if (typeof hft_frontend_data === 'undefined' || typeof hft_frontend_data.rest_url === 'undefined') {
        return Promise.reject(new Error('hft_frontend_data.rest_url not available'));
    }
    
    const url = `${hft_frontend_data.rest_url}housefresh-tools/v1/product/${productId}/price-history-chart?target_geo=${targetGeo}`;
    
    // Create AbortController for timeout
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout
    
    return fetch(url, {
        signal: controller.signal
    })
        .then(response => {
            clearTimeout(timeoutId);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .catch(error => {
            clearTimeout(timeoutId);
            throw error;
        });
}

/**
 * Update the existing chart with real data
 */
function updateChart(container, data, chart) {
    // Process data to get lowest price at each date
    const lowestPriceData = createLowestPriceDataset(data);
    
    if (lowestPriceData.length === 0) {
        container.style.display = 'none';
        return;
    }

    // Get currency symbol from the first available link
    const currencySymbol = data.links.length > 0 ? data.links[0].currencySymbol : '$';

    // Add the dataset with real data
    chart.data.datasets = [{
        data: lowestPriceData,
        borderColor: '#3984ff',
        backgroundColor: 'rgba(57, 132, 255, 0.15)',
        borderWidth: 3,
        fill: true,
        tension: 0.2,
        pointRadius: 5,
        pointHoverRadius: 8,
        pointBackgroundColor: '#3984ff',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        pointHoverBackgroundColor: '#2563eb',
        pointHoverBorderColor: '#fff',
        pointHoverBorderWidth: 3
    }];

    // Update chart options
    chart.options.plugins.tooltip = {
        enabled: true,
        callbacks: {
            title: function(context) {
                const dataPoint = lowestPriceData[context[0].dataIndex];
                const price = context[0].parsed.y;
                return currencySymbol + price.toFixed(2);
            },
            label: function(context) {
                const dataPoint = lowestPriceData[context.dataIndex];
                const date = new Date(dataPoint.x);
                return date.toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric'
                });
            },
            afterLabel: function(context) {
                const dataPoint = lowestPriceData[context.dataIndex];
                let retailer = dataPoint.retailer || 'Unknown';
                retailer = retailer.replace(/\s*\([^)]+\)\s*/g, '').trim();
                return retailer;
            }
        },
        displayColors: false,
        padding: 10,
        bodySpacing: 6,
        titleFont: {
            size: 18,
            family: "Inter",
            weight: '600'
        },
        titleColor: "#1f2937",
        titleAlign: "center",
        bodyAlign: "center",
        bodyFont: {
            size: 13,
            family: "Inter"
        },
        bodyColor: "#6b7280",
        footerFont: {
            size: 12,
            family: "Inter"
        },
        footerColor: "#9ca3af",
        footerAlign: "center",
        backgroundColor: "white",
        borderColor: "#d0d5dd",
        borderWidth: 1,
        cornerRadius: 5,
        caretSize: 6,
        caretPadding: 8
    };

    // Update scales
    chart.options.scales.x.ticks.color = '#6b7280';
    chart.options.scales.y.ticks.color = '#6b7280';
    chart.options.scales.y.ticks.callback = function(value) {
        return currencySymbol + value.toFixed(0);
    };
    // Remove the suggested min/max now that we have real data
    delete chart.options.scales.y.suggestedMin;
    delete chart.options.scales.y.suggestedMax;

    // Update the chart
    chart.update('active');
    
    // Create stats table
    createStatsTable(container, data, currencySymbol);
    
    // Hide loading spinners
    hideLoading(container);
}

/**
 * Process multiple retailer data to create a single "lowest price" dataset
 * Ensures exactly one data point per day with gap filling
 */
function createLowestPriceDataset(data) {
    // Collect all price points from all retailers
    const allPricePoints = [];
    
    data.links.forEach(link => {
        link.history.forEach(point => {
            allPricePoints.push({
                x: point.x,
                y: point.y,
                date: point.date,
                status: point.status,
                retailer: link.retailerName
            });
        });
    });
    
    if (allPricePoints.length === 0) {
        return [];
    }
    
    // Group by date (day only, ignore time) and find lowest price
    const pricesByDay = {};
    
    allPricePoints.forEach(point => {
        // Convert to date string (YYYY-MM-DD) to group by day
        const date = new Date(point.x);
        const dayKey = date.getFullYear() + '-' + 
                      String(date.getMonth() + 1).padStart(2, '0') + '-' + 
                      String(date.getDate()).padStart(2, '0');
        
        if (!pricesByDay[dayKey] || point.y < pricesByDay[dayKey].y) {
            pricesByDay[dayKey] = {
                x: new Date(dayKey + 'T12:00:00').getTime(), // Set to noon for consistency
                y: point.y,
                date: dayKey,
                status: point.status,
                retailer: point.retailer
            };
        }
    });
    
    // Convert to array and sort by date
    const sortedDays = Object.keys(pricesByDay).sort();
    
    if (sortedDays.length === 0) {
        return [];
    }
    
    // Fill gaps between first and last date
    const firstDate = new Date(sortedDays[0]);
    const lastDate = new Date(sortedDays[sortedDays.length - 1]);
    const filledData = [];
    
    let currentDate = new Date(firstDate);
    let lastKnownPrice = null;
    
    while (currentDate <= lastDate) {
        const dayKey = currentDate.getFullYear() + '-' + 
                      String(currentDate.getMonth() + 1).padStart(2, '0') + '-' + 
                      String(currentDate.getDate()).padStart(2, '0');
        
        if (pricesByDay[dayKey]) {
            // We have actual data for this day
            filledData.push(pricesByDay[dayKey]);
            lastKnownPrice = pricesByDay[dayKey];
        } else if (lastKnownPrice) {
            // Fill gap with last known price
            filledData.push({
                x: new Date(dayKey + 'T12:00:00').getTime(),
                y: lastKnownPrice.y,
                date: dayKey,
                status: 'estimated', // Mark as estimated
                retailer: lastKnownPrice.retailer
            });
        }
        
        // Move to next day
        currentDate.setDate(currentDate.getDate() + 1);
    }
    
        return filledData;
}

/**
 * Create and populate the price stats table
 */
function createStatsTable(container, data, currencySymbol) {
    const tableContainer = container.querySelector('.hft-price-stats-table');
    
    if (!tableContainer || !data.links || data.links.length === 0) {
        return;
    }

    // Calculate stats for each retailer
    const retailerStats = [];
    
    data.links.forEach(link => {
        if (!link.history || link.history.length === 0) {
            return;
        }
        
        // Remove (ID) from retailer name
        let retailerName = link.retailerName || 'Unknown';
        retailerName = retailerName.replace(/\s*\([^)]+\)\s*/g, '').trim();
        
        // Find lowest and highest prices
        let lowestPrice = link.history[0];
        let highestPrice = link.history[0];
        
        link.history.forEach(point => {
            if (point.y < lowestPrice.y) {
                lowestPrice = point;
            }
            if (point.y > highestPrice.y) {
                highestPrice = point;
            }
        });
        
        retailerStats.push({
            name: retailerName,
            lowest: {
                price: lowestPrice.y,
                date: new Date(lowestPrice.x)
            },
            highest: {
                price: highestPrice.y,
                date: new Date(highestPrice.x)
            }
        });
    });
    
    if (retailerStats.length === 0) {
        return;
    }
    
    // Build table HTML
    let tableHTML = `
        <table>
            <thead>
                <tr>
                    <th>Retailer</th>
                    <th>Lowest Ever</th>
                    <th>Highest Ever</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    retailerStats.forEach(retailer => {
        const lowestFormatted = currencySymbol + retailer.lowest.price.toFixed(2);
        const highestFormatted = currencySymbol + retailer.highest.price.toFixed(2);
        
        const lowestDate = retailer.lowest.date.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        });
        
        const highestDate = retailer.highest.date.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        });
        
        tableHTML += `
            <tr>
                <td class="retailer-name">${retailer.name}</td>
                <td class="price-cell">
                    <div class="price-value">${lowestFormatted}</div>
                    <div class="price-date">${lowestDate}</div>
                </td>
                <td class="price-cell">
                    <div class="price-value">${highestFormatted}</div>
                    <div class="price-date">${highestDate}</div>
                </td>
            </tr>
        `;
    });
    
    tableHTML += `
            </tbody>
        </table>
    `;
    
    tableContainer.innerHTML = tableHTML;
}

/**
 * Show loading state
 */
function showLoading(container) {
    // Loading spinners are shown via CSS with is-loading classes
    // No need to show the old loading div
}

/**
 * Hide loading state
 */
function hideLoading(container) {
    // Remove loading classes from chart wrapper and table
    const chartWrapper = container.querySelector('.hft-price-history-chart-wrapper');
    const statsTable = container.querySelector('.hft-price-stats-table');
    
    if (chartWrapper) {
        chartWrapper.classList.remove('is-loading');
    }
    
    if (statsTable) {
        statsTable.classList.remove('is-loading');
    }
}

 