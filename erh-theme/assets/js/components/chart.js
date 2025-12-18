/**
 * ERideHero Chart Module
 *
 * Lightweight, custom SVG chart library optimized for price data.
 * Supports line charts (with area fill) and bar charts.
 * Features visibility-triggered animations and interactive tooltips.
 *
 * @module components/chart
 */

const DEFAULTS = {
    // Dimensions
    height: 180,

    // Margins (minimal for maximum chart area)
    margin: { top: 2, right: 0, bottom: 2, left: 0 },

    // Chart type: 'line' or 'bar'
    type: 'line',

    // Colors
    lineColor: '#5e2ced',
    areaColor: 'rgba(94, 44, 237, 0.1)',
    barColor: '#5e2ced',
    gridColor: '#f3f2f5',
    textColor: '#6f768f',

    // Line options
    lineWidth: 2,
    showArea: true,          // Fill area under line
    showDots: false,         // Show data point dots
    dotRadius: 4,
    curveType: 'linear',     // 'linear' (sharp) or 'smooth'

    // Bar options
    barRadius: 4,            // Border radius on bars
    barGap: 0.2,             // Gap between bars (0-1)

    // Axis options
    showXAxis: true,
    showYAxis: true,         // Y-axis labels inside chart
    yAxisInside: true,       // Position Y labels inside chart area (PriceRunner style)
    showGrid: false,
    xAxisHeight: 16,         // Just enough for labels (~13px text + 3px padding)
    yAxisWidth: 45,
    yTickCount: 5,           // Number of Y-axis ticks

    // Animation
    animate: true,
    animationDuration: 1000, // ms
    animationEasing: 'ease-out',
    animateOnVisible: true,  // Use Intersection Observer

    // Tooltip
    showTooltip: true,
    formatValue: (v) => `$${v}`,
    formatLabel: (l) => l,

    // Responsive
    responsive: true
};

/**
 * Main Chart class
 */
class ERideHeroChart {
    constructor(container, options = {}) {
        this.container = typeof container === 'string'
            ? document.querySelector(container)
            : container;

        if (!this.container) {
            console.warn('Chart container not found');
            return;
        }

        this.options = { ...DEFAULTS, ...options };
        this.data = null;
        this.svg = null;
        this.tooltip = null;
        this.hasAnimated = false;
        this.resizeObserver = null;
        this.intersectionObserver = null;

        this._init();
    }

    /**
     * Initialize the chart
     */
    _init() {
        // Create SVG element
        this._createSVG();

        // Create tooltip if enabled
        if (this.options.showTooltip) {
            this._createTooltip();
        }

        // Set up responsive behavior
        if (this.options.responsive) {
            this._setupResponsive();
        }

        // Set up visibility observer for animation
        if (this.options.animateOnVisible && this.options.animate) {
            this._setupVisibilityObserver();
        }
    }

    /**
     * Create the SVG element
     */
    _createSVG() {
        const width = this.container.clientWidth;
        const height = this.options.height;

        this.svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        this.svg.setAttribute('width', '100%');
        this.svg.setAttribute('height', height);
        this.svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
        this.svg.setAttribute('preserveAspectRatio', 'none');
        this.svg.classList.add('erh-chart');

        // Add defs for gradients
        const defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
        defs.innerHTML = `
            <linearGradient id="areaGradient-${this._uid}" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="${this.options.lineColor}" stop-opacity="0.15"/>
                <stop offset="100%" stop-color="${this.options.lineColor}" stop-opacity="0"/>
            </linearGradient>
        `;
        this.svg.appendChild(defs);

        this.container.appendChild(this.svg);

        // Store dimensions
        this.width = width;
        this.height = height;
    }

    /**
     * Unique ID for this chart instance (for gradient refs)
     */
    get _uid() {
        if (!this.__uid) {
            this.__uid = Math.random().toString(36).substr(2, 9);
        }
        return this.__uid;
    }

    /**
     * Create tooltip element
     */
    _createTooltip() {
        this.tooltip = document.createElement('div');
        this.tooltip.className = 'erh-chart-tooltip';
        this.tooltip.style.cssText = `
            position: absolute;
            pointer-events: none;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.15s ease, visibility 0.15s ease;
            z-index: 100;
        `;
        this.container.style.position = 'relative';
        this.container.appendChild(this.tooltip);
    }

    /**
     * Set up responsive resize handling
     */
    _setupResponsive() {
        this.resizeObserver = new ResizeObserver(() => {
            this._handleResize();
        });
        this.resizeObserver.observe(this.container);
    }

    /**
     * Handle container resize
     */
    _handleResize() {
        const newWidth = this.container.clientWidth;
        if (newWidth !== this.width && newWidth > 0) {
            this.width = newWidth;
            this.svg.setAttribute('viewBox', `0 0 ${this.width} ${this.height}`);
            if (this.data) {
                this._render(false); // Re-render without animation
            }
        }
    }

    /**
     * Set up Intersection Observer for visibility-triggered animation
     */
    _setupVisibilityObserver() {
        this.intersectionObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !this.hasAnimated && this.data) {
                    this._render(true);
                    this.hasAnimated = true;
                }
            });
        }, { threshold: 0.2 });

        this.intersectionObserver.observe(this.container);
    }

    /**
     * Set data and render the chart
     * @param {Object} data - Chart data { labels: [], values: [] }
     */
    setData(data) {
        this.data = data;

        // If animateOnVisible is enabled and chart isn't visible yet, wait
        if (this.options.animateOnVisible && this.options.animate && !this.hasAnimated) {
            // Observer will trigger render when visible
            return this;
        }

        this._render(this.options.animate && !this.hasAnimated);
        this.hasAnimated = true;
        return this;
    }

    /**
     * Update data with new values
     * @param {Object} data - New chart data
     */
    update(data) {
        this.data = data;
        this._render(false);
        return this;
    }

    /**
     * Main render function
     * @param {boolean} animate - Whether to animate
     */
    _render(animate = false) {
        if (!this.data || !this.data.values || !this.data.values.length) return;

        // Clear existing content (except defs)
        const defs = this.svg.querySelector('defs');
        this.svg.innerHTML = '';
        if (defs) this.svg.appendChild(defs);

        // Calculate scales
        const { xScale, yScale, chartArea } = this._calculateScales();

        // Render based on type
        if (this.options.type === 'line') {
            this._renderLineChart(xScale, yScale, chartArea, animate);
        } else if (this.options.type === 'bar') {
            this._renderBarChart(xScale, yScale, chartArea, animate);
        }

        // Render axes
        if (this.options.showXAxis) {
            this._renderXAxis(xScale, chartArea);
        }
        if (this.options.showYAxis) {
            this._renderYAxis(yScale, chartArea);
        }

        // Render grid
        if (this.options.showGrid) {
            this._renderGrid(yScale, chartArea);
        }

        // Add interaction layer
        if (this.options.showTooltip) {
            this._addInteractionLayer(xScale, yScale, chartArea);
        }
    }

    /**
     * Calculate scales based on data and dimensions
     */
    _calculateScales() {
        const { margin, showYAxis, yAxisInside, yAxisWidth, showXAxis, xAxisHeight } = this.options;

        // When yAxisInside is true, don't reserve space for Y axis - chart goes edge to edge
        const chartArea = {
            left: margin.left + (showYAxis && !yAxisInside ? yAxisWidth : 0),
            right: this.width - margin.right,
            top: margin.top,
            bottom: this.height - margin.bottom - (showXAxis ? xAxisHeight : 0)
        };
        chartArea.width = chartArea.right - chartArea.left;
        chartArea.height = chartArea.bottom - chartArea.top;

        const values = this.data.values;
        const minVal = Math.min(...values);
        const maxVal = Math.max(...values);
        const range = maxVal - minVal;
        // Add breathing room around the line (7% padding)
        const padding = range * 0.07 || maxVal * 0.07;

        // Store computed min/max with padding for Y axis
        this._yMin = minVal - padding;
        this._yMax = maxVal + padding;

        // X scale - line goes full edge-to-edge
        // Handle single data point (avoid division by zero)
        const xScale = (index) => {
            if (values.length <= 1) {
                return chartArea.left + chartArea.width / 2; // Center single point
            }
            return chartArea.left + (index / (values.length - 1)) * chartArea.width;
        };

        const yScale = (value) => {
            const range = this._yMax - this._yMin;
            return chartArea.bottom - ((value - this._yMin) / range) * chartArea.height;
        };

        // Store for tooltip
        this._scales = { xScale, yScale, chartArea, minVal, maxVal };

        return { xScale, yScale, chartArea };
    }

    /**
     * Render line chart with optional area fill
     */
    _renderLineChart(xScale, yScale, chartArea, animate) {
        const { values } = this.data;
        const { lineColor, lineWidth, showArea, showDots, dotRadius, curveType } = this.options;

        // Build path
        let pathD = '';
        const points = values.map((v, i) => ({
            x: xScale(i),
            y: yScale(v)
        }));

        if (curveType === 'smooth' && points.length > 2) {
            pathD = this._smoothPath(points);
        } else {
            pathD = points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`).join(' ');
        }

        // Create group for chart elements
        const group = document.createElementNS('http://www.w3.org/2000/svg', 'g');
        group.classList.add('erh-chart-content');

        // Area fill
        if (showArea) {
            const areaPath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            const areaD = `${pathD} L ${points[points.length - 1].x} ${chartArea.bottom} L ${points[0].x} ${chartArea.bottom} Z`;
            areaPath.setAttribute('d', areaD);
            areaPath.setAttribute('fill', `url(#areaGradient-${this._uid})`);
            areaPath.classList.add('erh-chart-area');

            if (animate) {
                areaPath.style.opacity = '0';
                areaPath.style.transition = `opacity ${this.options.animationDuration}ms ${this.options.animationEasing}`;
                setTimeout(() => areaPath.style.opacity = '1', 50);
            }

            group.appendChild(areaPath);
        }

        // Line
        const linePath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        linePath.setAttribute('d', pathD);
        linePath.setAttribute('fill', 'none');
        linePath.setAttribute('stroke', lineColor);
        linePath.setAttribute('stroke-width', lineWidth);
        linePath.setAttribute('stroke-linecap', 'round');
        linePath.setAttribute('stroke-linejoin', 'round');
        linePath.classList.add('erh-chart-line');

        // Animate line drawing from left to right
        if (animate) {
            const length = linePath.getTotalLength ? linePath.getTotalLength() : this._estimatePathLength(points);
            linePath.style.strokeDasharray = length;
            linePath.style.strokeDashoffset = length;
            linePath.style.transition = `stroke-dashoffset ${this.options.animationDuration}ms ${this.options.animationEasing}`;

            // Trigger animation
            requestAnimationFrame(() => {
                linePath.style.strokeDashoffset = '0';
            });
        }

        group.appendChild(linePath);

        // Dots
        if (showDots) {
            const dotsGroup = document.createElementNS('http://www.w3.org/2000/svg', 'g');
            dotsGroup.classList.add('erh-chart-dots');

            points.forEach((p, i) => {
                const dot = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                dot.setAttribute('cx', p.x);
                dot.setAttribute('cy', p.y);
                dot.setAttribute('r', dotRadius);
                dot.setAttribute('fill', lineColor);
                dot.classList.add('erh-chart-dot');

                if (animate) {
                    dot.style.opacity = '0';
                    dot.style.transition = `opacity 0.2s ease`;
                    setTimeout(() => {
                        dot.style.opacity = '1';
                    }, (i / points.length) * this.options.animationDuration + this.options.animationDuration * 0.5);
                }

                dotsGroup.appendChild(dot);
            });

            group.appendChild(dotsGroup);
        }

        this.svg.appendChild(group);
    }

    /**
     * Generate smooth curve path using cardinal spline
     */
    _smoothPath(points) {
        if (points.length < 2) return '';

        let path = `M ${points[0].x} ${points[0].y}`;

        for (let i = 0; i < points.length - 1; i++) {
            const p0 = points[Math.max(0, i - 1)];
            const p1 = points[i];
            const p2 = points[i + 1];
            const p3 = points[Math.min(points.length - 1, i + 2)];

            const cp1x = p1.x + (p2.x - p0.x) / 6;
            const cp1y = p1.y + (p2.y - p0.y) / 6;
            const cp2x = p2.x - (p3.x - p1.x) / 6;
            const cp2y = p2.y - (p3.y - p1.y) / 6;

            path += ` C ${cp1x} ${cp1y}, ${cp2x} ${cp2y}, ${p2.x} ${p2.y}`;
        }

        return path;
    }

    /**
     * Estimate path length for animation
     */
    _estimatePathLength(points) {
        let length = 0;
        for (let i = 1; i < points.length; i++) {
            const dx = points[i].x - points[i-1].x;
            const dy = points[i].y - points[i-1].y;
            length += Math.sqrt(dx * dx + dy * dy);
        }
        return length;
    }

    /**
     * Render bar chart
     */
    _renderBarChart(xScale, yScale, chartArea, animate) {
        const { values } = this.data;
        const { barColor, barRadius, barGap } = this.options;

        const barWidth = (chartArea.width / values.length) * (1 - barGap);
        const gap = (chartArea.width / values.length) * barGap;

        const group = document.createElementNS('http://www.w3.org/2000/svg', 'g');
        group.classList.add('erh-chart-bars');

        values.forEach((v, i) => {
            const x = chartArea.left + (i * (barWidth + gap)) + gap / 2;
            const y = yScale(v);
            const height = chartArea.bottom - y;

            const bar = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
            bar.setAttribute('x', x);
            bar.setAttribute('width', barWidth);
            bar.setAttribute('fill', barColor);
            bar.setAttribute('rx', barRadius);
            bar.classList.add('erh-chart-bar');

            if (animate) {
                // Animate from bottom up
                bar.setAttribute('y', chartArea.bottom);
                bar.setAttribute('height', 0);
                bar.style.transition = `y ${this.options.animationDuration}ms ${this.options.animationEasing},
                                       height ${this.options.animationDuration}ms ${this.options.animationEasing}`;

                setTimeout(() => {
                    bar.setAttribute('y', y);
                    bar.setAttribute('height', height);
                }, i * 50); // Stagger animation
            } else {
                bar.setAttribute('y', y);
                bar.setAttribute('height', height);
            }

            group.appendChild(bar);
        });

        this.svg.appendChild(group);
    }

    /**
     * Render X axis with evenly spaced, adaptive labels
     */
    _renderXAxis(xScale, chartArea) {
        const { dates } = this.data;
        const { textColor, xAxisHeight } = this.options;

        const group = document.createElementNS('http://www.w3.org/2000/svg', 'g');
        group.classList.add('erh-chart-x-axis');

        if (!dates || dates.length < 2) {
            this.svg.appendChild(group);
            return;
        }

        // Responsive label count: ~1 label per 70px, min 4, max 10
        const labelCount = Math.max(4, Math.min(10, Math.floor(chartArea.width / 70)));

        // Calculate spacing: half-gap at edges, full gap between labels
        const totalPoints = dates.length;
        const spacing = chartArea.width / labelCount;
        const edgeOffset = spacing / 2;

        // Determine date range for format selection
        const firstDate = this._parseDate(dates[0]);
        const lastDate = this._parseDate(dates[dates.length - 1]);
        const dayRange = this._daysBetween(firstDate, lastDate);

        // Generate labels at evenly spaced positions
        for (let i = 0; i < labelCount; i++) {
            // Target X: start at half-spacing, then full spacing between each
            const targetX = chartArea.left + edgeOffset + (spacing * i);

            // Find data index closest to this X position
            const index = Math.round(((targetX - chartArea.left) / chartArea.width) * (totalPoints - 1));
            const clampedIndex = Math.max(0, Math.min(totalPoints - 1, index));

            // Position label at the actual data point's X (centered under the correct point)
            const actualX = xScale(clampedIndex);
            const dateStr = dates[clampedIndex];
            const label = this._formatDateLabel(dateStr, dayRange);

            const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            text.setAttribute('x', actualX);
            text.setAttribute('y', chartArea.bottom + xAxisHeight - 4);
            text.setAttribute('text-anchor', 'middle');
            text.setAttribute('fill', textColor);
            text.setAttribute('font-size', '11');
            text.textContent = label;

            group.appendChild(text);
        }

        this.svg.appendChild(group);
    }

    /**
     * Parse date string "Mon D, YYYY" to object
     */
    _parseDate(dateStr) {
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const match = dateStr.match(/(\w+)\s+(\d+),\s+(\d+)/);
        if (!match) return null;
        return {
            month: months.indexOf(match[1]),
            day: parseInt(match[2]),
            year: parseInt(match[3])
        };
    }

    /**
     * Calculate days between two dates
     */
    _daysBetween(d1, d2) {
        if (!d1 || !d2) return 0;
        const date1 = new Date(d1.year, d1.month, d1.day);
        const date2 = new Date(d2.year, d2.month, d2.day);
        return Math.abs((date2 - date1) / (1000 * 60 * 60 * 24));
    }

    /**
     * Format date label based on range
     */
    _formatDateLabel(dateStr, dayRange) {
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const parsed = this._parseDate(dateStr);
        if (!parsed) return dateStr;

        if (dayRange <= 90) {
            // Short range (3M): "12 Nov"
            return `${parsed.day} ${months[parsed.month]}`;
        } else if (dayRange <= 180) {
            // Medium range (6M): "Nov"
            return months[parsed.month];
        } else {
            // Long range (1Y, All): "Nov '24"
            return `${months[parsed.month]} '${String(parsed.year).slice(-2)}`;
        }
    }

    /**
     * Render Y axis
     */
    _renderYAxis(yScale, chartArea) {
        const { textColor, yAxisWidth, yAxisInside, yTickCount } = this.options;

        const group = document.createElementNS('http://www.w3.org/2000/svg', 'g');
        group.classList.add('erh-chart-y-axis');

        // Generate nice tick values
        const ticks = this._generateTicks(this._yMin, this._yMax, yTickCount);

        ticks.forEach((tick) => {
            const y = yScale(tick);

            // Skip if too close to edges (would overlap with X-axis or clip at top)
            if (y > chartArea.bottom - 12 || y < chartArea.top + 2) return;

            const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');

            if (yAxisInside) {
                // Position inside chart at left edge (PriceRunner style)
                text.setAttribute('x', chartArea.left + 8);
                text.setAttribute('y', y + 4); // Vertically center on the line
                text.setAttribute('text-anchor', 'start');
            } else {
                // Traditional positioning outside chart
                text.setAttribute('x', yAxisWidth - 8);
                text.setAttribute('y', y + 4);
                text.setAttribute('text-anchor', 'end');
            }

            text.setAttribute('fill', textColor);
            text.setAttribute('font-size', '11');
            text.setAttribute('opacity', '0.7');
            text.textContent = this.options.formatValue(Math.round(tick));

            group.appendChild(text);
        });

        this.svg.appendChild(group);
    }

    /**
     * Render grid lines
     */
    _renderGrid(yScale, chartArea) {
        const { gridColor } = this.options;
        const { minVal, maxVal } = this._scales;

        const group = document.createElementNS('http://www.w3.org/2000/svg', 'g');
        group.classList.add('erh-chart-grid');

        const ticks = this._generateTicks(minVal, maxVal, 4);

        ticks.forEach(tick => {
            const y = yScale(tick);
            const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            line.setAttribute('x1', chartArea.left);
            line.setAttribute('x2', chartArea.right);
            line.setAttribute('y1', y);
            line.setAttribute('y2', y);
            line.setAttribute('stroke', gridColor);
            line.setAttribute('stroke-dasharray', '2,4');

            group.appendChild(line);
        });

        // Insert grid behind chart content
        const firstChild = this.svg.querySelector('.erh-chart-content, .erh-chart-bars');
        if (firstChild) {
            this.svg.insertBefore(group, firstChild);
        } else {
            this.svg.appendChild(group);
        }
    }

    /**
     * Generate nice tick values
     */
    _generateTicks(min, max, count) {
        const range = max - min;
        const step = range / (count - 1);
        const ticks = [];

        for (let i = 0; i < count; i++) {
            ticks.push(Math.round(min + step * i));
        }

        return ticks;
    }

    /**
     * Add interaction layer for tooltips
     */
    _addInteractionLayer(xScale, yScale, chartArea) {
        const { values, labels } = this.data;

        // Create invisible overlay for mouse tracking
        const overlay = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
        overlay.setAttribute('x', chartArea.left);
        overlay.setAttribute('y', chartArea.top);
        overlay.setAttribute('width', chartArea.width);
        overlay.setAttribute('height', chartArea.height);
        overlay.setAttribute('fill', 'transparent');
        overlay.classList.add('erh-chart-overlay');
        overlay.style.cursor = 'crosshair';

        // Hover indicator line
        const hoverLine = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        hoverLine.setAttribute('y1', chartArea.top);
        hoverLine.setAttribute('y2', chartArea.bottom);
        hoverLine.setAttribute('stroke', this.options.lineColor);
        hoverLine.setAttribute('stroke-width', '1');
        hoverLine.setAttribute('stroke-dasharray', '4,4');
        hoverLine.style.opacity = '0';
        hoverLine.style.transition = 'opacity 0.15s ease';
        hoverLine.classList.add('erh-chart-hover-line');

        // Hover dot
        const hoverDot = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        hoverDot.setAttribute('r', 5);
        hoverDot.setAttribute('fill', '#fff');
        hoverDot.setAttribute('stroke', this.options.lineColor);
        hoverDot.setAttribute('stroke-width', '2');
        hoverDot.style.opacity = '0';
        hoverDot.style.transition = 'opacity 0.15s ease';
        hoverDot.classList.add('erh-chart-hover-dot');

        this.svg.appendChild(hoverLine);
        this.svg.appendChild(hoverDot);
        this.svg.appendChild(overlay);

        // Mouse events
        overlay.addEventListener('mousemove', (e) => {
            const rect = this.svg.getBoundingClientRect();
            const mouseX = e.clientX - rect.left;

            // Find closest data point
            const index = Math.round(((mouseX - chartArea.left) / chartArea.width) * (values.length - 1));
            const clampedIndex = Math.max(0, Math.min(values.length - 1, index));

            const x = xScale(clampedIndex);
            const y = yScale(values[clampedIndex]);

            // Update hover elements
            hoverLine.setAttribute('x1', x);
            hoverLine.setAttribute('x2', x);
            hoverLine.style.opacity = '0.5';

            hoverDot.setAttribute('cx', x);
            hoverDot.setAttribute('cy', y);
            hoverDot.style.opacity = '1';

            // Update tooltip
            this._showTooltip(
                x,
                y,
                clampedIndex,
                rect
            );
        });

        overlay.addEventListener('mouseleave', () => {
            hoverLine.style.opacity = '0';
            hoverDot.style.opacity = '0';
            this._hideTooltip();
        });
    }

    /**
     * Show tooltip at position
     * @param {number} x - X position
     * @param {number} y - Y position
     * @param {number} index - Data point index
     * @param {DOMRect} svgRect - SVG bounding rect
     */
    _showTooltip(x, y, index, svgRect) {
        if (!this.tooltip) return;

        const value = this.data.values[index];
        const formattedValue = this.options.formatValue(value);

        // Get date - prefer dates array, fall back to labels
        const date = this.data.dates?.[index] || this.data.labels?.[index] || '';
        const formattedDate = this.options.formatLabel(date);

        // Get store name if available
        const store = this.data.stores?.[index] || '';

        let tooltipHTML = `<div class="erh-chart-tooltip-value">${formattedValue}</div>`;
        if (store) {
            tooltipHTML += `<div class="erh-chart-tooltip-store">at ${store}</div>`;
        }
        if (formattedDate) {
            tooltipHTML += `<div class="erh-chart-tooltip-date">${formattedDate}</div>`;
        }

        this.tooltip.innerHTML = tooltipHTML;

        // Position tooltip
        const tooltipRect = this.tooltip.getBoundingClientRect();
        let left = x - tooltipRect.width / 2;
        let top = y - tooltipRect.height - 12;

        // Keep within bounds
        left = Math.max(0, Math.min(this.width - tooltipRect.width, left));
        if (top < 0) top = y + 12;

        this.tooltip.style.left = `${left}px`;
        this.tooltip.style.top = `${top}px`;
        this.tooltip.style.visibility = 'visible';
        this.tooltip.style.opacity = '1';
    }

    /**
     * Hide tooltip
     */
    _hideTooltip() {
        if (this.tooltip) {
            this.tooltip.style.opacity = '0';
            this.tooltip.style.visibility = 'hidden';
        }
    }

    /**
     * Destroy the chart and clean up
     */
    destroy() {
        if (this.resizeObserver) {
            this.resizeObserver.disconnect();
        }
        if (this.intersectionObserver) {
            this.intersectionObserver.disconnect();
        }
        if (this.svg) {
            this.svg.remove();
        }
        if (this.tooltip) {
            this.tooltip.remove();
        }
    }
}

/**
 * Factory function to create charts
 * @param {string|HTMLElement} container
 * @param {Object} options
 * @returns {ERideHeroChart}
 */
export function createChart(container, options = {}) {
    return new ERideHeroChart(container, options);
}

/**
 * Initialize a single chart container
 */
function initChartContainer(container) {
    // Skip if already initialized
    if (container._erhChart) return;

    const chartId = container.dataset.erhChart || 'default';
    const chartConfig = window.ERideHero?.chartData?.[chartId];

    if (chartConfig) {
        const chart = createChart(container, chartConfig.options || {});
        chart.setData(chartConfig.data);

        // Store reference
        container._erhChart = chart;

        // Set up period toggles if present
        if (chartConfig.periods) {
            setupPeriodToggles(container, chart, chartConfig.periods);
        }
    }
}

/**
 * Auto-initialize charts from data attributes
 * Handles both sync and async data loading (listens for erhPriceDataReady event)
 */
export function autoInit() {
    const containers = document.querySelectorAll('[data-erh-chart]');

    // Try to initialize immediately if data is available
    containers.forEach(container => initChartContainer(container));

    // Also listen for async data ready event
    window.addEventListener('erhPriceDataReady', () => {
        containers.forEach(container => initChartContainer(container));
    });
}

/**
 * Set up period toggle buttons
 */
export function setupPeriodToggles(container, chart, periodsData) {
    const section = container.closest('.price-intel-history') || container.parentElement;
    const toggles = section?.querySelectorAll('.price-intel-chart-period button');

    if (!toggles?.length) return;

    // Find stat elements to update
    const statElements = {
        avgLabel: section?.querySelector('[data-period-label]'),
        avgValue: section?.querySelector('[data-period-avg]'),
        lowLabel: section?.querySelector('[data-period-low-label]'),
        lowValue: section?.querySelector('[data-period-low]'),
        lowMeta: section?.querySelector('[data-period-low-meta]')
    };

    toggles.forEach(btn => {
        btn.addEventListener('click', () => {
            const period = btn.textContent.trim().toLowerCase();
            const data = periodsData[period];

            if (data) {
                toggles.forEach(b => b.classList.remove('is-active'));
                btn.classList.add('is-active');
                chart.update(data);

                // Update the dynamic stats
                updatePeriodStats(period, data, statElements);
            }
        });
    });
}

/**
 * Update the period-based stats (average and low)
 */
function updatePeriodStats(period, data, elements) {
    if (!data?.values?.length) return;

    const { avgLabel, avgValue, lowLabel, lowValue, lowMeta } = elements;

    // Period label mapping
    const periodLabels = {
        '3m': { avg: '3-month avg', low: '3-month low' },
        '6m': { avg: '6-month avg', low: '6-month low' },
        '1y': { avg: '1-year avg', low: '1-year low' },
        'all': { avg: 'All-time avg', low: 'All-time low' }
    };

    const labels = periodLabels[period] || { avg: `${period} avg`, low: `${period} low` };

    // Calculate and update average
    if (avgLabel && avgValue) {
        const sum = data.values.reduce((a, b) => a + b, 0);
        const avg = Math.round(sum / data.values.length);
        avgLabel.textContent = labels.avg;
        avgValue.textContent = `$${avg}`;
    }

    // Find and update low
    if (lowLabel && lowValue) {
        const minValue = Math.min(...data.values);
        const minIndex = data.values.indexOf(minValue);

        lowLabel.textContent = labels.low;
        lowValue.textContent = `$${minValue}`;

        // Update meta (date · store) if available
        if (lowMeta && data.dates && data.stores) {
            const date = data.dates[minIndex] || '';
            const store = data.stores[minIndex] || '';
            // Format date to shorter form (e.g., "Dec 2023")
            const shortDate = formatShortDate(date);
            lowMeta.textContent = store ? `${shortDate} · ${store}` : shortDate;
        }
    }
}

/**
 * Format date string to short form (e.g., "Nov 15, 2024" → "Nov 2024")
 */
function formatShortDate(dateStr) {
    if (!dateStr) return '';
    const match = dateStr.match(/(\w+)\s+\d+,\s+(\d+)/);
    return match ? `${match[1]} ${match[2]}` : dateStr;
}

export default {
    create: createChart,
    autoInit,
    setupPeriodToggles
};
