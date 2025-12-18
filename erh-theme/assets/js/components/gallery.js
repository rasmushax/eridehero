/**
 * Gallery Component
 *
 * A modular, reusable image gallery with:
 * - Thumbnail navigation with scroll-snap
 * - Scroll arrows when thumbnails overflow
 * - Keyboard navigation (arrow keys)
 * - Pinned video thumbnail with lightbox
 *
 * Usage:
 *   // Auto-init (default): galleries with [data-gallery] are automatically initialized
 *
 *   // Manual init:
 *   import { Gallery } from './components/gallery.js';
 *   const gallery = new Gallery(element, options);
 *
 * HTML Structure:
 *   <div class="gallery" data-gallery tabindex="0">
 *     <div class="gallery-main">
 *       <img src="..." alt="...">
 *     </div>
 *     <div class="gallery-thumbs-wrapper">
 *       <div class="gallery-thumbs-scroll">
 *         <button class="gallery-arrow gallery-arrow--prev">...</button>
 *         <div class="gallery-thumbs">
 *           <button class="gallery-thumb is-active" data-img="...">...</button>
 *           <button class="gallery-thumb" data-img="...">...</button>
 *         </div>
 *         <button class="gallery-arrow gallery-arrow--next">...</button>
 *       </div>
 *       <div class="gallery-thumb-video-wrapper">
 *         <button class="gallery-thumb-video" data-video="youtube-id">...</button>
 *       </div>
 *     </div>
 *   </div>
 */

const SELECTORS = {
  gallery: '[data-gallery]',
  mainImage: '.gallery-main img',
  mainContainer: '.gallery-main',
  thumbsWrapper: '.gallery-thumbs-wrapper',
  thumbsScroll: '.gallery-thumbs-scroll',
  thumbs: '.gallery-thumbs',
  thumb: '.gallery-thumb',
  videoThumb: '.gallery-video-card',
  arrowPrev: '.gallery-arrow--prev',
  arrowNext: '.gallery-arrow--next',
  lightbox: '.gallery-lightbox',
};

const CLASSES = {
  active: 'is-active',
  lightboxOpen: 'lightbox-open',
  lightboxVisible: 'is-visible',
  canScrollLeft: 'can-scroll-left',
  canScrollRight: 'can-scroll-right',
};

/**
 * Video Lightbox
 * Singleton lightbox for video playback across all galleries
 */
class VideoLightbox {
  constructor() {
    if (VideoLightbox.instance) {
      return VideoLightbox.instance;
    }

    this.element = null;
    this.videoContainer = null;
    this.isOpen = false;
    this.boundHandleKeydown = this.handleKeydown.bind(this);

    VideoLightbox.instance = this;
  }

  /**
   * Create lightbox DOM if it doesn't exist
   */
  create() {
    if (this.element) return;

    this.element = document.createElement('div');
    this.element.className = 'gallery-lightbox';
    this.element.setAttribute('role', 'dialog');
    this.element.setAttribute('aria-modal', 'true');
    this.element.setAttribute('aria-label', 'Video player');

    this.element.innerHTML = `
      <div class="gallery-lightbox-backdrop"></div>
      <div class="gallery-lightbox-content">
        <button class="gallery-lightbox-close" aria-label="Close video">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>
        <div class="gallery-lightbox-video"></div>
      </div>
    `;

    document.body.appendChild(this.element);

    this.videoContainer = this.element.querySelector('.gallery-lightbox-video');

    // Event listeners
    this.element.querySelector('.gallery-lightbox-backdrop').addEventListener('click', () => this.close());
    this.element.querySelector('.gallery-lightbox-close').addEventListener('click', () => this.close());
  }

  /**
   * Open lightbox with YouTube video
   * @param {string} videoId - YouTube video ID
   */
  open(videoId) {
    this.create();

    // Create YouTube embed with autoplay
    this.videoContainer.innerHTML = `
      <iframe
        src="https://www.youtube.com/embed/${videoId}?autoplay=1&rel=0"
        title="Video player"
        frameborder="0"
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
        allowfullscreen>
      </iframe>
    `;

    // Show lightbox
    document.body.classList.add(CLASSES.lightboxOpen);
    this.element.classList.add(CLASSES.lightboxVisible);
    this.isOpen = true;

    // Keyboard support
    document.addEventListener('keydown', this.boundHandleKeydown);

    // Focus trap - focus close button
    this.element.querySelector('.gallery-lightbox-close').focus();
  }

  /**
   * Close lightbox and cleanup
   */
  close() {
    if (!this.isOpen) return;

    // Remove video to stop playback
    this.videoContainer.innerHTML = '';

    // Hide lightbox
    document.body.classList.remove(CLASSES.lightboxOpen);
    this.element.classList.remove(CLASSES.lightboxVisible);
    this.isOpen = false;

    // Remove keyboard listener
    document.removeEventListener('keydown', this.boundHandleKeydown);
  }

  /**
   * Handle keyboard events
   * @param {KeyboardEvent} e
   */
  handleKeydown(e) {
    if (e.key === 'Escape') {
      this.close();
    }
  }
}

// Singleton instance
const lightbox = new VideoLightbox();

/**
 * Gallery Class
 * Handles image switching, keyboard nav, scroll arrows, and video lightbox
 */
export class Gallery {
  /**
   * @param {HTMLElement} element - The gallery container element
   * @param {Object} options - Configuration options
   */
  constructor(element, options = {}) {
    this.element = element;
    this.options = {
      scrollAmount: 160, // Pixels to scroll when clicking arrows
      ...options
    };

    // Cache DOM elements
    this.mainImage = this.element.querySelector(SELECTORS.mainImage);
    this.thumbsScroll = this.element.querySelector(SELECTORS.thumbsScroll);
    this.thumbsContainer = this.element.querySelector(SELECTORS.thumbs);
    this.thumbs = this.element.querySelectorAll(SELECTORS.thumb);
    this.videoThumb = this.element.querySelector(SELECTORS.videoThumb);
    this.arrowPrev = this.element.querySelector(SELECTORS.arrowPrev);
    this.arrowNext = this.element.querySelector(SELECTORS.arrowNext);

    // Single image with no thumbnails is valid - just skip JS initialization
    if (!this.mainImage || !this.thumbs.length) {
      return;
    }

    this.currentIndex = 0;
    this.boundHandleKeydown = this.handleKeydown.bind(this);
    this.boundUpdateScrollState = this.updateScrollState.bind(this);

    this.init();
  }

  /**
   * Initialize gallery event listeners
   */
  init() {
    // Thumbnail clicks
    this.thumbs.forEach((thumb, index) => {
      thumb.addEventListener('click', (e) => this.handleThumbClick(e, thumb, index));
    });

    // Video thumbnail click
    if (this.videoThumb) {
      this.videoThumb.addEventListener('click', () => {
        const videoId = this.videoThumb.dataset.video;
        if (videoId) {
          lightbox.open(videoId);
        }
      });
    }

    // Keyboard navigation
    this.element.addEventListener('keydown', this.boundHandleKeydown);

    // Scroll arrows
    if (this.arrowPrev && this.arrowNext && this.thumbsContainer) {
      this.arrowPrev.addEventListener('click', () => this.scrollThumbs('prev'));
      this.arrowNext.addEventListener('click', () => this.scrollThumbs('next'));

      // Update scroll state on scroll and resize
      this.thumbsContainer.addEventListener('scroll', this.boundUpdateScrollState);
      window.addEventListener('resize', this.boundUpdateScrollState);

      // Initial scroll state check
      this.updateScrollState();
    }

    // Make gallery focusable for keyboard nav
    if (!this.element.hasAttribute('tabindex')) {
      this.element.setAttribute('tabindex', '0');
    }

    // Mark as initialized
    this.element.setAttribute('data-gallery-initialized', 'true');
  }

  /**
   * Handle keyboard navigation
   * @param {KeyboardEvent} e
   */
  handleKeydown(e) {
    // Only handle if gallery or child is focused
    if (!this.element.contains(document.activeElement) && document.activeElement !== this.element) {
      return;
    }

    switch (e.key) {
      case 'ArrowLeft':
        e.preventDefault();
        this.prev();
        break;
      case 'ArrowRight':
        e.preventDefault();
        this.next();
        break;
    }
  }

  /**
   * Handle thumbnail click
   * @param {Event} e - Click event
   * @param {HTMLElement} thumb - Clicked thumbnail
   * @param {number} index - Thumbnail index
   */
  handleThumbClick(e, thumb, index) {
    e.preventDefault();

    const newSrc = thumb.dataset.img;
    if (!newSrc) return;

    this.setActiveImage(newSrc, index);
  }

  /**
   * Set active image and thumbnail
   * @param {string} src - New image source
   * @param {number} index - Thumbnail index
   */
  setActiveImage(src, index) {
    // Update main image with fade effect
    this.mainImage.style.opacity = '0';

    // Preload new image
    const img = new Image();
    img.onload = () => {
      this.mainImage.src = src;
      this.mainImage.style.opacity = '1';
    };
    img.src = src;

    // Update active states
    this.thumbs.forEach((t, i) => {
      t.classList.toggle(CLASSES.active, i === index);
    });

    this.currentIndex = index;

    // Scroll active thumb into view
    this.scrollThumbIntoView(index);
  }

  /**
   * Scroll thumbnail into view if needed
   * @param {number} index - Thumbnail index
   */
  scrollThumbIntoView(index) {
    const thumb = this.thumbs[index];
    if (!thumb || !this.thumbsContainer) return;

    thumb.scrollIntoView({
      behavior: 'smooth',
      block: 'nearest',
      inline: 'nearest'
    });
  }

  /**
   * Go to next image
   */
  next() {
    const nextIndex = (this.currentIndex + 1) % this.thumbs.length;
    const thumb = this.thumbs[nextIndex];
    if (thumb?.dataset.img) {
      this.setActiveImage(thumb.dataset.img, nextIndex);
    }
  }

  /**
   * Go to previous image
   */
  prev() {
    const prevIndex = (this.currentIndex - 1 + this.thumbs.length) % this.thumbs.length;
    const thumb = this.thumbs[prevIndex];
    if (thumb?.dataset.img) {
      this.setActiveImage(thumb.dataset.img, prevIndex);
    }
  }

  /**
   * Scroll thumbnails left or right
   * @param {'prev'|'next'} direction
   */
  scrollThumbs(direction) {
    if (!this.thumbsContainer) return;

    const scrollAmount = direction === 'prev'
      ? -this.options.scrollAmount
      : this.options.scrollAmount;

    this.thumbsContainer.scrollBy({
      left: scrollAmount,
      behavior: 'smooth'
    });
  }

  /**
   * Update scroll state classes for showing/hiding arrows and fade masks
   */
  updateScrollState() {
    if (!this.thumbsContainer || !this.thumbsScroll) return;

    const { scrollLeft, scrollWidth, clientWidth } = this.thumbsContainer;
    const canScrollLeft = scrollLeft > 1; // 1px tolerance
    const canScrollRight = scrollLeft + clientWidth < scrollWidth - 1;

    this.thumbsScroll.classList.toggle(CLASSES.canScrollLeft, canScrollLeft);
    this.thumbsScroll.classList.toggle(CLASSES.canScrollRight, canScrollRight);
  }

  /**
   * Destroy gallery instance and cleanup
   */
  destroy() {
    this.element.removeEventListener('keydown', this.boundHandleKeydown);

    if (this.thumbsContainer) {
      this.thumbsContainer.removeEventListener('scroll', this.boundUpdateScrollState);
    }
    window.removeEventListener('resize', this.boundUpdateScrollState);

    this.element.removeAttribute('data-gallery-initialized');
  }
}

/**
 * Initialize all galleries on the page
 * @param {string} selector - Gallery container selector
 * @returns {Gallery[]} Array of gallery instances
 */
export function initGalleries(selector = SELECTORS.gallery) {
  const galleries = document.querySelectorAll(`${selector}:not([data-gallery-initialized])`);
  return Array.from(galleries).map(el => new Gallery(el));
}

/**
 * Auto-initialize on DOM ready
 */
export function autoInit() {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => initGalleries());
  } else {
    initGalleries();
  }
}

// Auto-init by default
autoInit();

export default Gallery;
