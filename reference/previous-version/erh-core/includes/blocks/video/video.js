/**
 * ERH Video Block
 *
 * Lazy-loads video on click for better pagespeed.
 * Shows thumbnail until user interacts.
 */
(function() {
    'use strict';

    function initVideoBlocks() {
        const videos = document.querySelectorAll('[data-erh-video]');

        videos.forEach(container => {
            const video = container.querySelector('video');
            const thumbnail = container.querySelector('.erh-video-thumbnail, .erh-video-placeholder');

            if (!video) return;

            // Handle click to play/pause
            container.addEventListener('click', () => handleClick(container, video, thumbnail));

            // Handle keyboard (Enter/Space)
            container.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    handleClick(container, video, thumbnail);
                }
            });

            // Handle video ready to play
            video.addEventListener('canplaythrough', () => {
                container.classList.remove('is-loading');
                container.classList.add('is-playing');

                if (thumbnail) {
                    thumbnail.style.display = 'none';
                }

                video.style.display = 'block';
                video.play();
            });

            // Handle video ended (for non-looping videos)
            video.addEventListener('ended', () => {
                container.classList.remove('is-playing');
            });
        });
    }

    function handleClick(container, video, thumbnail) {
        const videoSrc = container.dataset.src;

        // First click - load video
        if (!video.src && videoSrc) {
            container.classList.add('is-loading');
            video.src = videoSrc;
            video.load();
            return;
        }

        // Video still loading
        if (container.classList.contains('is-loading')) {
            return;
        }

        // Toggle play/pause
        if (video.paused) {
            video.play();
            container.classList.add('is-playing');
            container.setAttribute('aria-label', container.getAttribute('aria-label').replace('Play', 'Pause'));
        } else {
            video.pause();
            container.classList.remove('is-playing');
            container.setAttribute('aria-label', container.getAttribute('aria-label').replace('Pause', 'Play'));
        }
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initVideoBlocks);
    } else {
        initVideoBlocks();
    }

    // Re-init for dynamic content (e.g., AJAX loaded blocks)
    const observer = new MutationObserver(mutations => {
        mutations.forEach(mutation => {
            mutation.addedNodes.forEach(node => {
                if (node.nodeType === 1) {
                    if (node.matches('[data-erh-video]')) {
                        initVideoBlocks();
                    } else if (node.querySelector('[data-erh-video]')) {
                        initVideoBlocks();
                    }
                }
            });
        });
    });

    observer.observe(document.body, { childList: true, subtree: true });
})();
