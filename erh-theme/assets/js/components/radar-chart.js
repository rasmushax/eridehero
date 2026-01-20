/**
 * Radar Chart Component
 *
 * SVG-based radar/spider chart for multi-dimensional product comparison.
 * Zero dependencies, smooth animations, interactive tooltips.
 *
 * @module components/radar-chart
 */

import { escapeHtml } from '../utils/dom.js';

// =============================================================================
// Configuration
// =============================================================================

const DEFAULTS = {
    size: 320,
    levels: 5,
    maxValue: 100,
    labelOffset: 24,
    animationDuration: 800,
    animationEasing: 'cubic-bezier(0.34, 1.56, 0.64, 1)',
    colors: [
        'var(--color-primary)',
        'var(--color-success)',
        'var(--color-info)',
        'var(--color-warning)',
        '#e91e63',
        '#00bcd4',
    ],
    fillOpacity: 0.15,
    strokeWidth: 2,
    dotRadius: 4,
};

// =============================================================================
// RadarChart Class
// =============================================================================

export class RadarChart {
    /**
     * Create a radar chart.
     *
     * @param {HTMLElement} container - Container element
     * @param {Object} options - Configuration options
     */
    constructor(container, options = {}) {
        this.container = container;
        this.options = { ...DEFAULTS, ...options };
        this.data = [];
        this.categories = [];
        this.tooltip = null;
        this.legendItems = new Map();
        this.hoveredProduct = null;
        this.hiddenProducts = new Set(); // Track which products are hidden

        this.init();
    }

    /**
     * Initialize the chart.
     */
    init() {
        this.container.classList.add('radar-chart');
        this.container.innerHTML = `
            <div class="radar-chart-wrapper">
                <svg class="radar-chart-svg" aria-hidden="true"></svg>
            </div>
            <div class="radar-chart-legend"></div>
            <div class="radar-chart-tooltip" aria-hidden="true"></div>
        `;

        this.svg = this.container.querySelector('.radar-chart-svg');
        this.legend = this.container.querySelector('.radar-chart-legend');
        this.tooltip = this.container.querySelector('.radar-chart-tooltip');

        this.setupResizeObserver();
    }

    /**
     * Set chart data and render.
     *
     * @param {Array<Object>} products - Array of product objects
     * @param {Array<Object>} categories - Array of category configs
     */
    setData(products, categories) {
        this.data = products;
        this.categories = categories;
        this.render();
    }

    /**
     * Render the chart.
     */
    render() {
        if (!this.categories.length || !this.data.length) return;

        const { size, levels, labelOffset } = this.options;
        const center = size / 2;
        const radius = (size / 2) - labelOffset - 10;
        const angleSlice = (Math.PI * 2) / this.categories.length;

        // Build SVG
        this.svg.setAttribute('viewBox', `0 0 ${size} ${size}`);
        this.svg.innerHTML = '';

        // Create groups for layering
        const gridGroup = this.createGroup('radar-grid');
        const axesGroup = this.createGroup('radar-axes');
        const labelsGroup = this.createGroup('radar-labels');
        const dataGroup = this.createGroup('radar-data');

        // Draw concentric circles (levels)
        for (let level = 1; level <= levels; level++) {
            const levelRadius = (radius / levels) * level;
            const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            circle.setAttribute('cx', center);
            circle.setAttribute('cy', center);
            circle.setAttribute('r', levelRadius);
            circle.classList.add('radar-level');
            if (level === levels) circle.classList.add('radar-level--outer');
            gridGroup.appendChild(circle);
        }

        // Draw axes and labels
        this.categories.forEach((cat, i) => {
            const angle = angleSlice * i - Math.PI / 2;
            const x = center + Math.cos(angle) * radius;
            const y = center + Math.sin(angle) * radius;

            // Axis line
            const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            line.setAttribute('x1', center);
            line.setAttribute('y1', center);
            line.setAttribute('x2', x);
            line.setAttribute('y2', y);
            line.classList.add('radar-axis');
            axesGroup.appendChild(line);

            // Label
            const labelX = center + Math.cos(angle) * (radius + labelOffset);
            const labelY = center + Math.sin(angle) * (radius + labelOffset);

            const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            text.setAttribute('x', labelX);
            text.setAttribute('y', labelY);
            text.setAttribute('text-anchor', this.getTextAnchor(angle));
            text.setAttribute('dominant-baseline', 'middle');
            text.classList.add('radar-label');
            text.textContent = cat.name;

            // Make label interactive
            text.dataset.category = cat.name;
            text.addEventListener('mouseenter', (e) => this.showCategoryTooltip(e, cat, i));
            text.addEventListener('mouseleave', () => this.hideTooltip());

            labelsGroup.appendChild(text);
        });

        // Draw product polygons
        this.data.forEach((product, productIndex) => {
            const color = this.options.colors[productIndex % this.options.colors.length];
            const points = this.calculatePoints(product, center, radius, angleSlice);

            // Create polygon group
            const productGroup = this.createGroup(`radar-product radar-product-${productIndex}`);
            productGroup.dataset.productId = product.id;

            // Polygon fill
            const polygon = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
            polygon.setAttribute('points', points.map(p => `${p.x},${p.y}`).join(' '));
            polygon.classList.add('radar-polygon');
            polygon.style.setProperty('--radar-color', color);
            polygon.style.setProperty('--radar-fill-opacity', this.options.fillOpacity);
            productGroup.appendChild(polygon);

            // Polygon stroke (separate for better animation control)
            const stroke = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
            stroke.setAttribute('points', points.map(p => `${p.x},${p.y}`).join(' '));
            stroke.classList.add('radar-stroke');
            stroke.style.setProperty('--radar-color', color);
            stroke.style.setProperty('--stroke-width', this.options.strokeWidth);
            productGroup.appendChild(stroke);

            // Data points (dots)
            points.forEach((point, i) => {
                const dot = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                dot.setAttribute('cx', point.x);
                dot.setAttribute('cy', point.y);
                dot.setAttribute('r', this.options.dotRadius);
                dot.classList.add('radar-dot');
                dot.style.setProperty('--radar-color', color);
                dot.dataset.value = point.value;
                dot.dataset.category = this.categories[i].name;
                dot.dataset.product = product.name;

                dot.addEventListener('mouseenter', (e) => this.showDotTooltip(e, point, this.categories[i], product));
                dot.addEventListener('mouseleave', () => this.hideTooltip());

                productGroup.appendChild(dot);
            });

            // Hover interactions
            productGroup.addEventListener('mouseenter', () => this.highlightProduct(productIndex));
            productGroup.addEventListener('mouseleave', () => this.unhighlightProduct());

            dataGroup.appendChild(productGroup);
        });

        // Append groups in order
        this.svg.appendChild(gridGroup);
        this.svg.appendChild(axesGroup);
        this.svg.appendChild(dataGroup);
        this.svg.appendChild(labelsGroup);

        // Render legend
        this.renderLegend();

        // Animate in
        requestAnimationFrame(() => this.animate());
    }

    /**
     * Calculate polygon points for a product.
     */
    calculatePoints(product, center, radius, angleSlice) {
        return this.categories.map((cat, i) => {
            const value = product.scores?.[cat.key] ?? 0;
            const normalizedValue = Math.min(value / this.options.maxValue, 1);
            const angle = angleSlice * i - Math.PI / 2;

            return {
                x: center + Math.cos(angle) * radius * normalizedValue,
                y: center + Math.sin(angle) * radius * normalizedValue,
                value: Math.round(value),
                rawValue: value,
            };
        });
    }

    /**
     * Get text anchor based on angle.
     */
    getTextAnchor(angle) {
        const deg = (angle * 180 / Math.PI + 360) % 360;
        if (deg > 45 && deg < 135) return 'middle';
        if (deg >= 135 && deg <= 225) return 'end';
        if (deg > 225 && deg < 315) return 'middle';
        return 'start';
    }

    /**
     * Create SVG group.
     */
    createGroup(className) {
        const g = document.createElementNS('http://www.w3.org/2000/svg', 'g');
        g.setAttribute('class', className);
        return g;
    }

    /**
     * Render legend with click-to-toggle functionality.
     * Note: Single-product charts (product pages) hide the legend via CSS/option.
     */
    renderLegend() {
        // Can toggle visibility only for multi-product charts
        const canToggle = this.data.length > 1;

        this.legend.innerHTML = this.data.map((product, i) => {
            const color = this.options.colors[i % this.options.colors.length];
            const isHidden = this.hiddenProducts.has(i);
            const hiddenClass = isHidden ? ' is-hidden' : '';
            const title = canToggle ? ' title="Click to toggle visibility"' : '';
            // Product name is escaped via escapeHtml imported from utils/dom.js
            return `
                <button class="radar-legend-item${hiddenClass}" data-product-index="${i}" type="button"${title}>
                    <span class="radar-legend-color" style="--radar-color: ${color}"></span>
                    <span class="radar-legend-name">${escapeHtml(product.name)}</span>
                </button>
            `;
        }).join('');

        // Legend interactions
        this.legend.querySelectorAll('.radar-legend-item').forEach(item => {
            const index = parseInt(item.dataset.productIndex, 10);

            // Hover to highlight (only if product is visible)
            item.addEventListener('mouseenter', () => {
                if (!this.hiddenProducts.has(index)) {
                    this.highlightProduct(index);
                }
            });
            item.addEventListener('mouseleave', () => this.unhighlightProduct());

            // Click to toggle visibility (only for multi-product charts)
            if (canToggle) {
                item.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.toggleProduct(index);
                });
            }
        });
    }

    /**
     * Toggle product visibility in the chart.
     *
     * @param {number} index - Product index to toggle
     */
    toggleProduct(index) {
        // Don't allow hiding all products - keep at least one visible
        const visibleCount = this.data.length - this.hiddenProducts.size;
        const isCurrentlyHidden = this.hiddenProducts.has(index);

        if (!isCurrentlyHidden && visibleCount <= 1) {
            // Can't hide the last visible product
            return;
        }

        // Toggle hidden state
        if (isCurrentlyHidden) {
            this.hiddenProducts.delete(index);
        } else {
            this.hiddenProducts.add(index);
        }

        // Update legend item state
        const legendItem = this.legend.querySelector(`[data-product-index="${index}"]`);
        if (legendItem) {
            legendItem.classList.toggle('is-hidden', !isCurrentlyHidden);
        }

        // Update chart visibility with animation
        const productGroup = this.svg.querySelector(`.radar-product-${index}`);
        if (productGroup) {
            productGroup.classList.toggle('is-hidden', !isCurrentlyHidden);
        }

        // Clear any active hover state
        this.unhighlightProduct();
    }

    /**
     * Animate chart in.
     */
    animate() {
        this.container.classList.add('is-animated');

        // Stagger polygon animations
        const polygons = this.svg.querySelectorAll('.radar-polygon, .radar-stroke');
        polygons.forEach((el, i) => {
            el.style.animationDelay = `${i * 100}ms`;
        });

        // Stagger dot animations
        const dots = this.svg.querySelectorAll('.radar-dot');
        dots.forEach((el, i) => {
            el.style.animationDelay = `${200 + i * 30}ms`;
        });
    }

    /**
     * Highlight a specific product.
     */
    highlightProduct(index) {
        this.hoveredProduct = index;
        this.container.classList.add('has-hover');

        this.svg.querySelectorAll('.radar-product').forEach((group, i) => {
            group.classList.toggle('is-active', i === index);
            group.classList.toggle('is-dimmed', i !== index);
        });

        this.legend.querySelectorAll('.radar-legend-item').forEach((item, i) => {
            item.classList.toggle('is-active', i === index);
        });
    }

    /**
     * Remove product highlighting.
     */
    unhighlightProduct() {
        this.hoveredProduct = null;
        this.container.classList.remove('has-hover');

        this.svg.querySelectorAll('.radar-product').forEach(group => {
            group.classList.remove('is-active', 'is-dimmed');
        });

        this.legend.querySelectorAll('.radar-legend-item').forEach(item => {
            item.classList.remove('is-active');
        });
    }

    /**
     * Show tooltip for a data point.
     */
    showDotTooltip(event, point, category, product) {
        const color = this.options.colors[this.data.indexOf(product) % this.options.colors.length];

        this.tooltip.innerHTML = `
            <div class="radar-tooltip-header">
                <span class="radar-tooltip-dot" style="--radar-color: ${color}"></span>
                <span class="radar-tooltip-product">${escapeHtml(product.name)}</span>
            </div>
            <div class="radar-tooltip-category">${category.name}</div>
            <div class="radar-tooltip-value">${point.value}<span>pts</span></div>
        `;

        this.positionTooltip(event);
    }

    /**
     * Show tooltip for a category axis.
     */
    showCategoryTooltip(event, category, categoryIndex) {
        const items = this.data.map((product, i) => {
            const color = this.options.colors[i % this.options.colors.length];
            const value = product.scores?.[category.key] ?? 0;
            return `
                <div class="radar-tooltip-row">
                    <span class="radar-tooltip-dot" style="--radar-color: ${color}"></span>
                    <span class="radar-tooltip-name">${escapeHtml(product.name)}</span>
                    <span class="radar-tooltip-score">${Math.round(value)}</span>
                </div>
            `;
        }).join('');

        this.tooltip.innerHTML = `
            <div class="radar-tooltip-title">${category.name}</div>
            ${items}
        `;

        this.positionTooltip(event);
    }

    /**
     * Position tooltip near cursor.
     */
    positionTooltip(event) {
        const rect = this.container.getBoundingClientRect();
        const x = event.clientX - rect.left;
        const y = event.clientY - rect.top;

        this.tooltip.style.left = `${x}px`;
        this.tooltip.style.top = `${y - 10}px`;
        this.tooltip.classList.add('is-visible');
    }

    /**
     * Hide tooltip.
     */
    hideTooltip() {
        this.tooltip.classList.remove('is-visible');
    }

    /**
     * Setup resize observer for responsive sizing.
     */
    setupResizeObserver() {
        if ('ResizeObserver' in window) {
            const ro = new ResizeObserver(entries => {
                for (const entry of entries) {
                    const width = entry.contentRect.width;
                    if (width < 300) {
                        this.container.classList.add('is-compact');
                    } else {
                        this.container.classList.remove('is-compact');
                    }
                }
            });
            ro.observe(this.container);
        }
    }

    // escapeHtml imported from utils/dom.js

    /**
     * Destroy the chart.
     */
    destroy() {
        this.container.innerHTML = '';
        this.container.classList.remove('radar-chart', 'is-animated', 'has-hover', 'is-compact');
        this.hiddenProducts.clear();
    }
}

export default RadarChart;
