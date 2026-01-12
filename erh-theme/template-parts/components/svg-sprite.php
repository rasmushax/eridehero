<?php
/**
 * SVG Icon Sprite
 *
 * Inline SVG sprite for all icons used in the theme.
 * This is loaded once in header.php and icons are referenced via #icon-name
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true">
    <!-- Search -->
    <symbol id="icon-search" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="11" cy="11" r="8"></circle>
        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
    </symbol>

    <!-- Book -->
    <symbol id="icon-book" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
    </symbol>

    <!-- Star -->
    <symbol id="icon-star" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
    </symbol>

    <!-- Chevrons -->
    <symbol id="icon-chevron-down" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="6 9 12 15 18 9"></polyline>
    </symbol>
    <symbol id="icon-chevron-left" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="15 18 9 12 15 6"></polyline>
    </symbol>
    <symbol id="icon-chevron-right" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="9 18 15 12 9 6"></polyline>
    </symbol>

    <!-- List (for TOC) -->
    <symbol id="icon-list" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="8" y1="6" x2="21" y2="6"></line>
        <line x1="8" y1="12" x2="21" y2="12"></line>
        <line x1="8" y1="18" x2="21" y2="18"></line>
        <line x1="3" y1="6" x2="3.01" y2="6"></line>
        <line x1="3" y1="12" x2="3.01" y2="12"></line>
        <line x1="3" y1="18" x2="3.01" y2="18"></line>
    </symbol>

    <!-- Lightning / Zap -->
    <symbol id="icon-zap" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
    </symbol>

    <!-- Arrow Right -->
    <symbol id="icon-arrow-right" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="5" y1="12" x2="19" y2="12"></line>
        <polyline points="12 5 19 12 12 19"></polyline>
    </symbol>

    <!-- Check -->
    <symbol id="icon-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="20 6 9 17 4 12"></polyline>
    </symbol>

    <!-- Tag -->
    <symbol id="icon-tag" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
        <line x1="7" y1="7" x2="7.01" y2="7"></line>
    </symbol>

    <!-- Grid -->
    <symbol id="icon-grid" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="3" width="7" height="7"></rect>
        <rect x="14" y="3" width="7" height="7"></rect>
        <rect x="14" y="14" width="7" height="7"></rect>
        <rect x="3" y="14" width="7" height="7"></rect>
    </symbol>

    <!-- Columns (for table column selector) -->
    <symbol id="icon-columns" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="3" width="18" height="18" rx="2"></rect>
        <line x1="9" y1="3" x2="9" y2="21"></line>
        <line x1="15" y1="3" x2="15" y2="21"></line>
    </symbol>

    <!-- Arrow Up -->
    <symbol id="icon-arrow-up" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="12" y1="19" x2="12" y2="5"></line>
        <polyline points="5 12 12 5 19 12"></polyline>
    </symbol>

    <!-- Arrow Down -->
    <symbol id="icon-arrow-down" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="12" y1="5" x2="12" y2="19"></line>
        <polyline points="19 12 12 19 5 12"></polyline>
    </symbol>

    <!-- Sort (neutral - both arrows) -->
    <symbol id="icon-sort" viewBox="0 0 320 512" fill="currentColor">
        <path d="M137.4 41.4c12.5-12.5 32.8-12.5 45.3 0l128 128c9.2 9.2 11.9 22.9 6.9 34.9s-16.6 19.8-29.6 19.8L32 224c-12.9 0-24.6-7.8-29.6-19.8s-2.2-25.7 6.9-34.9l128-128zm0 429.3l-128-128c-9.2-9.2-11.9-22.9-6.9-34.9s16.6-19.8 29.6-19.8l256 0c12.9 0 24.6 7.8 29.6 19.8s2.2 25.7-6.9 34.9l-128 128c-12.5 12.5-32.8 12.5-45.3 0z"></path>
    </symbol>

    <!-- Sort Up (ascending - single triangle, rotate 180deg for descending) -->
    <symbol id="icon-sort-up" viewBox="0 0 320 512" fill="currentColor">
        <path d="M279 224H41c-21.4 0-32.1-25.9-17-41L143 64c9.4-9.4 24.6-9.4 33.9 0l119 119c15.2 15.1 4.5 41-16.9 41z"></path>
    </symbol>

    <!-- Sliders / Filters -->
    <symbol id="icon-sliders" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="4" x2="4" y1="21" y2="14"></line>
        <line x1="4" x2="4" y1="10" y2="3"></line>
        <line x1="12" x2="12" y1="21" y2="12"></line>
        <line x1="12" x2="12" y1="8" y2="3"></line>
        <line x1="20" x2="20" y1="21" y2="16"></line>
        <line x1="20" x2="20" y1="12" y2="3"></line>
        <line x1="2" x2="6" y1="14" y2="14"></line>
        <line x1="10" x2="14" y1="8" y2="8"></line>
        <line x1="18" x2="22" y1="16" y2="16"></line>
    </symbol>

    <!-- X / Close -->
    <symbol id="icon-x" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="18" y1="6" x2="6" y2="18"></line>
        <line x1="6" y1="6" x2="18" y2="18"></line>
    </symbol>

    <!-- Edit / Pencil -->
    <symbol id="icon-edit" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 20h9"></path>
        <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path>
    </symbol>

    <!-- Trending Up -->
    <symbol id="icon-trending-up" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline>
        <polyline points="16 7 22 7 22 13"></polyline>
    </symbol>

    <!-- Trending Down -->
    <symbol id="icon-trending-down" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="22 17 13.5 8.5 8.5 13.5 2 7"></polyline>
        <polyline points="16 17 22 17 22 11"></polyline>
    </symbol>

    <!-- User -->
    <symbol id="icon-user" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
        <circle cx="12" cy="7" r="4"></circle>
    </symbol>

    <!-- Percent -->
    <symbol id="icon-percent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="19" y1="5" x2="5" y2="19"></line>
        <circle cx="6.5" cy="6.5" r="2.5"></circle>
        <circle cx="17.5" cy="17.5" r="2.5"></circle>
    </symbol>

    <!-- Refresh / Sync -->
    <symbol id="icon-refresh" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="23 4 23 10 17 10"></polyline>
        <polyline points="1 20 1 14 7 14"></polyline>
        <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
    </symbol>

    <!-- Database -->
    <symbol id="icon-database" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
        <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path>
        <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path>
    </symbol>

    <!-- Bell -->
    <symbol id="icon-bell" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
    </symbol>

    <!-- Plus -->
    <symbol id="icon-plus" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="12" y1="5" x2="12" y2="19"></line>
        <line x1="5" y1="12" x2="19" y2="12"></line>
    </symbol>

    <!-- Minus -->
    <symbol id="icon-minus" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="5" y1="12" x2="19" y2="12"></line>
    </symbol>

    <!-- External Link -->
    <symbol id="icon-external-link" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
        <polyline points="15 3 21 3 21 9"></polyline>
        <line x1="10" y1="14" x2="21" y2="3"></line>
    </symbol>

    <!-- Info -->
    <symbol id="icon-info" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"></circle>
        <line x1="12" y1="16" x2="12" y2="12"></line>
        <line x1="12" y1="8" x2="12.01" y2="8"></line>
    </symbol>

    <!-- Clipboard Check -->
    <symbol id="icon-clipboard-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
        <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
        <path d="m9 14 2 2 4-4"></path>
    </symbol>

    <!-- Play -->
    <symbol id="icon-play" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polygon points="5 3 19 12 5 21 5 3"></polygon>
    </symbol>

    <!-- Pause -->
    <symbol id="icon-pause" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="6" y="4" width="4" height="16"></rect>
        <rect x="14" y="4" width="4" height="16"></rect>
    </symbol>

    <!-- Loader -->
    <symbol id="icon-loader" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="12" y1="2" x2="12" y2="6"></line>
        <line x1="12" y1="18" x2="12" y2="22"></line>
        <line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line>
        <line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line>
        <line x1="2" y1="12" x2="6" y2="12"></line>
        <line x1="18" y1="12" x2="22" y2="12"></line>
        <line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line>
        <line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line>
    </symbol>

    <!-- Social Icons -->
    <symbol id="icon-youtube" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z"></path>
        <polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02"></polygon>
    </symbol>

    <symbol id="icon-instagram" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
        <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
        <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
    </symbol>

    <symbol id="icon-facebook" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>
    </symbol>

    <symbol id="icon-twitter" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M4 4l11.733 16h4.267l-11.733 -16z"></path>
        <path d="M4 20l6.768 -6.768m2.46 -2.46l6.772 -6.772"></path>
    </symbol>

    <symbol id="icon-linkedin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path>
        <rect x="2" y="9" width="4" height="12"></rect>
        <circle cx="4" cy="4" r="2"></circle>
    </symbol>

    <symbol id="icon-reddit" viewBox="0 0 16 16" fill="currentColor" stroke="none">
        <path d="M6.167 8a.83.83 0 0 0-.83.83c0 .459.372.84.83.831a.831.831 0 0 0 0-1.661m1.843 3.647c.315 0 1.403-.038 1.976-.611a.23.23 0 0 0 0-.306.213.213 0 0 0-.306 0c-.353.363-1.126.487-1.67.487-.545 0-1.308-.124-1.671-.487a.213.213 0 0 0-.306 0 .213.213 0 0 0 0 .306c.564.563 1.652.61 1.977.61zm.992-2.807c0 .458.373.83.831.83s.83-.381.83-.83a.831.831 0 0 0-1.66 0z"></path>
        <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.828-1.165c-.315 0-.602.124-.812.325-.801-.573-1.9-.945-3.121-.993l.534-2.501 1.738.372a.83.83 0 1 0 .83-.869.83.83 0 0 0-.744.468l-1.938-.41a.2.2 0 0 0-.153.028.2.2 0 0 0-.086.134l-.592 2.788c-1.24.038-2.358.41-3.17.992-.21-.2-.496-.324-.81-.324a1.163 1.163 0 0 0-.478 2.224q-.03.17-.029.353c0 1.795 2.091 3.256 4.669 3.256s4.668-1.451 4.668-3.256c0-.114-.01-.238-.029-.353.401-.181.688-.592.688-1.069 0-.65-.525-1.165-1.165-1.165"></path>
    </symbol>

    <symbol id="icon-google" viewBox="0 0 488 512" fill="currentColor" stroke="none">
        <path d="M488 261.8C488 403.3 391.1 504 248 504 110.8 504 0 393.2 0 256S110.8 8 248 8c66.8 0 123 24.5 166.3 64.9l-67.5 64.9C258.5 52.6 94.3 116.6 94.3 256c0 86.5 69.1 156.6 153.7 156.6 98.2 0 135-70.4 140.8-106.9H248v-85.3h236.1c2.3 12.7 3.9 24.9 3.9 41.4z"></path>
    </symbol>

    <!-- YouTube Logo (full color) -->
    <symbol id="icon-youtube-logo" viewBox="0 0 28.57 20">
        <path d="M27.9727 3.12324C27.6435 1.89323 26.6768 0.926623 25.4468 0.597366C23.2197 2.24288e-07 14.285 0 14.285 0C14.285 0 5.35042 2.24288e-07 3.12323 0.597366C1.89323 0.926623 0.926623 1.89323 0.597366 3.12324C2.24288e-07 5.35042 0 10 0 10C0 10 2.24288e-07 14.6496 0.597366 16.8768C0.926623 18.1068 1.89323 19.0734 3.12323 19.4026C5.35042 20 14.285 20 14.285 20C14.285 20 23.2197 20 25.4468 19.4026C26.6768 19.0734 27.6435 18.1068 27.9727 16.8768C28.5701 14.6496 28.5701 10 28.5701 10C28.5701 10 28.5677 5.35042 27.9727 3.12324Z" fill="#FF0000"></path>
        <path d="M11.4253 14.2854L18.8477 10.0004L11.4253 5.71533V14.2854Z" fill="white"></path>
    </symbol>

    <!-- Settings / Gear -->
    <symbol id="icon-settings" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path>
        <circle cx="12" cy="12" r="3"></circle>
    </symbol>

    <!-- Trash / Delete -->
    <symbol id="icon-trash" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 6h18"></path>
        <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
        <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
        <line x1="10" y1="11" x2="10" y2="17"></line>
        <line x1="14" y1="11" x2="14" y2="17"></line>
    </symbol>

    <!-- Log Out -->
    <symbol id="icon-log-out" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
        <polyline points="16 17 21 12 16 7"></polyline>
        <line x1="21" y1="12" x2="9" y2="12"></line>
    </symbol>

    <!-- More Vertical (three dots menu) -->
    <symbol id="icon-more-vertical" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="1"></circle>
        <circle cx="12" cy="5" r="1"></circle>
        <circle cx="12" cy="19" r="1"></circle>
    </symbol>

    <!-- Eye (show password) -->
    <symbol id="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
        <circle cx="12" cy="12" r="3"></circle>
    </symbol>

    <!-- Eye Off (hide password) -->
    <symbol id="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"></path>
        <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"></path>
        <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"></path>
        <line x1="2" y1="2" x2="22" y2="22"></line>
    </symbol>

    <!-- Vehicle Icons (Custom - filled style) -->
    <symbol id="icon-escooter" viewBox="0 0 24 24" fill="currentColor">
        <path d="m20.724,17.023l-3.033-13.147c-.527-2.282-2.53-3.875-4.872-3.875h-.818c-.553,0-1,.448-1,1s.447,1,1,1h.818c1.405,0,2.607.956,2.923,2.325l1.794,7.774-3.109,3.8c-.571.7-1.418,1.101-2.321,1.101H1c-.553,0-1,.448-1,1s.447,1,1,1h.351c-.219.456-.351.961-.351,1.5,0,1.93,1.57,3.5,3.5,3.5s3.5-1.57,3.5-3.5c0-.539-.133-1.044-.351-1.5h4.456c1.506,0,2.916-.668,3.869-1.834l2.13-2.602.671,2.91c-1.054.604-1.775,1.727-1.775,3.027,0,1.93,1.57,3.5,3.5,3.5s3.5-1.57,3.5-3.5c0-1.853-1.452-3.359-3.276-3.477Zm-14.724,3.477c0,.827-.673,1.5-1.5,1.5s-1.5-.673-1.5-1.5.673-1.5,1.5-1.5,1.5.673,1.5,1.5Zm14.5,1.5c-.827,0-1.5-.673-1.5-1.5s.673-1.5,1.5-1.5,1.5.673,1.5,1.5-.673,1.5-1.5,1.5Z"/>
    </symbol>

    <symbol id="icon-ebike" viewBox="0 0 512 512" fill="currentColor">
        <path d="M407.531,206.527c-13.212,0-25.855,2.471-37.501,6.966c-9.124-20.276-17.007-41.719-20.944-61.668c-6.323-32.038-34.634-55.291-67.318-55.291c-8.284,0-15,6.716-15,15s6.716,15,15,15c3.569,0,7.044,0.498,10.355,1.423c10.063,2.812,18.602,9.618,23.582,18.758c-0.403,0.512-0.787,1.043-1.128,1.618l-4.66,7.845l-23.576,39.69H160.377l-7.16-18.021h2.972c8.284,0,15-6.716,15-15s-6.716-15-15-15H104.47c-8.284,0-15,6.716-15,15s6.716,15,15,15h16.466l13.09,32.944c-9.376-2.77-19.294-4.265-29.556-4.265C46.865,206.527,0,253.392,0,310.996s46.865,104.469,104.469,104.469c52.511,0,96.091-38.946,103.388-89.469h27.547c5.292,0,10.193-2.789,12.896-7.339l78.827-132.706c4.624,14.31,10.412,28.648,16.651,42.346c-24.747,19.122-40.716,49.079-40.716,82.7c0,57.604,46.865,104.469,104.469,104.469S512,368.601,512,310.997S465.135,206.527,407.531,206.527z M104.469,325.996h72.951c-6.96,33.897-37.025,59.469-72.951,59.469C63.407,385.464,30,352.058,30,310.996c0-41.062,33.407-74.469,74.469-74.469c35.926,0,65.991,25.572,72.951,59.469h-72.951c-8.284,0-15,6.716-15,15S96.185,325.996,104.469,325.996z M226.867,295.996h-19.01c-3.481-24.099-15.216-45.561-32.241-61.421c-0.156-0.602-0.337-1.202-0.573-1.795l-2.746-6.911h96.225L226.867,295.996z M407.531,385.464c-41.063,0-74.469-33.407-74.469-74.469c0-21.753,9.378-41.355,24.301-54.983c18.448,35.256,36.467,61.538,37.823,63.504c2.911,4.217,7.594,6.48,12.358,6.48c2.938,0,5.907-0.862,8.508-2.657c6.818-4.706,8.53-14.048,3.824-20.866c-0.323-0.468-18.475-26.939-36.652-61.853c7.624-2.641,15.797-4.095,24.307-4.095c41.062,0,74.469,33.407,74.469,74.469C482,352.056,448.593,385.464,407.531,385.464z"/>
    </symbol>

    <symbol id="icon-euc" viewBox="306 261 450 472" fill="none" stroke="currentColor" stroke-width="35" stroke-miterlimit="10">
        <path d="M586,446.71v212.29c0,31.2-24.56,56.49-54.87,56.49s-54.87-25.29-54.87-56.49v-212.29c0-31.2,24.56-56.49,54.87-56.49,15.16,0,28.87,6.32,38.8,16.54,9.93,10.23,16.07,24.35,16.07,39.95Z"/>
        <path d="M646.42,417.22v198.04c0,1.15-.93,2.08-2.08,2.08h-58.34v-170.63c0-15.6-6.14-29.72-16.07-39.95-9.93-10.22-23.64-16.54-38.8-16.54-30.31,0-54.87,25.29-54.87,56.49v170.63h-58.34c-1.15,0-2.08-.93-2.08-2.08v-198.04c0-48.32,39.17-87.5,87.5-87.5h55.58c48.33,0,87.5,39.18,87.5,87.5Z"/>
        <path d="M486.462,278.365h89.336c8.386,0,15.194,6.808,15.194,15.194v36.165h-119.725v-36.165c0-8.386,6.808-15.194,15.194-15.194Z"/>
        <path d="M415.839,547.147l-59.86-9.338c-7.651-1.193-13.305-7.964-13.305-15.93v-31.061h73.165v56.329Z"/>
        <line x1="415.839" y1="490.818" x2="323.52" y2="490.818" stroke-linecap="round"/>
        <path d="M646.421,547.147l59.86-9.338c7.651-1.193,13.305-7.964,13.305-15.93v-31.061s-73.165,0-73.165,0v56.329Z"/>
        <line x1="646.421" y1="490.818" x2="738.74" y2="490.818" stroke-linecap="round"/>
    </symbol>

    <symbol id="icon-hoverboard" viewBox="0 0 525 525" fill="currentColor">
        <path d="M462.424,180.951h-.027c-25.395.098-47.959,16.297-56.149,40.31-1.4,4.106-4.797,7.426-9.087,8.881-11.199,3.798-11.664,3.833-49.622,3.577-8.68-.059-19.263-.13-32.312-.167-9.294-.026-17.979-1.57-25.812-4.587-17.27-6.651-36.287-6.651-53.553,0-7.834,3.018-16.519,4.561-25.812,4.587-13.208.037-23.903.109-32.66.167q-38.32.255-49.604-3.641c-4.2-1.45-7.481-4.67-9.002-8.834-9.829-26.906-37.499-42.997-65.787-38.258-28.15,4.713-49.061,28.844-49.721,57.378l-.002,63.253c0,7.171,4.331,13.349,10.515,16.055v35.69c0,13.371,5.504,26.439,15.101,35.854,9.474,9.295,21.837,14.268,34.914,14.027,26.526-.507,48.106-22.512,48.106-49.052v-3.514c22.649.006,40.198.04,53.827.066q49.689.098,53.723-2.84c.081-.06.167-.124.263-.188l14.035-9.34c8.583-5.711,18.57-8.73,28.88-8.73s20.296,3.02,28.88,8.731l13.954,9.284c.091.062.167.123.239.177q3.968,3.002,53.664,2.905c13.659-.026,31.259-.061,53.989-.066v2.684c0,13.371,5.504,26.439,15.101,35.854,9.249,9.073,21.283,14.036,33.98,14.036.311,0,.622-.003.934-.009,26.526-.507,48.106-22.512,48.106-49.052v-36.521c6.185-2.706,10.516-8.884,10.516-16.055v-63.091c0-32.85-26.726-59.576-59.576-59.576ZM476.454,321.133v35.061c0,7.736-6.294,14.03-14.03,14.03s-14.03-6.294-14.03-14.03v-35.061h28.061ZM76.879,321.133v35.061c0,7.736-6.294,14.03-14.03,14.03s-14.03-6.294-14.03-14.03v-35.061h28.061ZM409.891,317.648h-83.874c-3.596,0-7.068-1.071-10.042-3.098l-2.667-1.818c-23.195-15.812-23.639-16.115-29.641-16.115h-42.061c-5.714,0-7.499,1.211-21.394,10.634-2.95,2-6.525,4.425-10.898,7.363-2.956,1.984-6.396,3.034-9.948,3.034h-83.984c4.272-3.197,7.042-8.296,7.042-14.031v-38.657c10.727,3.461,11.148,3.597,14.03,3.597,23.428,0,41.194.039,54.707.067q45.197.1,49.3-2.823c.091-.065.187-.134.302-.211,13.288-8.878,30.458-8.878,43.731-.011,2.858,1.921,6.189,2.951,9.688,2.978,23.812,0,41.873.093,55.061.161,41.214.211,43.199.223,53.606-3.528v38.427c0,5.734,2.77,10.833,7.042,14.031ZM38.289,240.084c-.415-6.56,1.749-12.889,6.094-17.822,4.345-4.932,10.351-7.877,16.911-8.293,6.553-.414,12.889,1.748,17.822,6.094,4.88,4.299,7.815,10.224,8.279,16.703v49.336h-49.091v-45.576l-.014-.442ZM486.97,240.527v45.576h-49.091v-49.099c1.754-12.112,12.175-21.127,24.488-21.022h.057c13.534,0,24.546,11.011,24.546,24.545Z"/>
    </symbol>

    <symbol id="icon-eskate" viewBox="0 0 525 525" fill="none" stroke="currentColor" stroke-width="31" stroke-miterlimit="10">
        <circle cx="113.924" cy="276.711" r="46.114"/>
        <path d="M295.069,230.597H87.943c-15.9,0-31.491-4.395-45.049-12.7l-25.67-15.723" stroke-linecap="round"/>
        <circle cx="411.076" cy="276.711" r="46.114"/>
        <path d="M229.931,230.597h207.126c15.9,0,31.491-4.395,45.049-12.7l25.67-15.723" stroke-linecap="round"/>
    </symbol>

    <!-- Archive (discontinued) -->
    <symbol id="icon-archive" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="21 8 21 21 3 21 3 8"/>
        <rect x="1" y="3" width="22" height="5"/>
        <line x1="10" y1="12" x2="14" y2="12"/>
    </symbol>

    <!-- Globe Search (global research) -->
    <symbol id="icon-globe-search" viewBox="0 0 24 24" fill="currentColor">
        <path d="m20.167 18.753c.524-.791.833-1.736.833-2.753 0-2.757-2.243-5-5-5s-5 2.243-5 5 2.243 5 5 5c1.017 0 1.962-.309 2.753-.833l3.54 3.54c.195.195.451.293.707.293s.512-.098.707-.293c.391-.391.391-1.023 0-1.414zm-7.167-2.753c0-1.654 1.346-3 3-3s3 1.346 3 3-1.346 3-3 3-3-1.346-3-3zm-.029 7.145c.084-.321-.092-.655-.304-.89-.052-.046-5.167-4.691-5.167-10.255 0-1.039.18-2.047.472-3h13.567c.299.948.461 1.955.461 3 0 .552.447 1 1 1s1-.448 1-1c0-6.596-5.35-11.964-11.939-11.997-6.631-.038-12.065 5.359-12.061 11.997 0 6.617 5.383 12 12 12 .478.002.925-.38.971-.855zm-7.068-8.145h-3.442c-.299-.948-.461-1.955-.461-3s.163-2.052.461-3h3.442c-.246.956-.403 1.958-.403 3s.157 2.044.403 3zm6.098-12.586c.814.864 2.207 2.506 3.229 4.586h-6.452c1.025-2.082 2.411-3.724 3.223-4.586zm8.646 4.586h-3.223c-.789-1.879-1.879-3.476-2.819-4.644 2.572.696 4.733 2.389 6.041 4.644zm-11.259-4.642c-.94 1.167-2.024 2.767-2.81 4.642h-3.225c1.308-2.253 3.466-3.945 6.035-4.642zm-6.035 14.642h3.225c.787 1.875 1.87 3.475 2.81 4.642-2.569-.697-4.728-2.39-6.035-4.642z"/>
    </symbol>

    <!-- Mail -->
    <symbol id="icon-mail" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
        <polyline points="22,6 12,13 2,6"></polyline>
    </symbol>

    <!-- Spec Icons for Listicle Items -->

    <!-- Dashboard / Speedometer (tested speed) -->
    <symbol id="icon-dashboard" viewBox="0 0 32 32" fill="currentColor">
        <path d="M23.525 12.964c-0.369-0.369-0.967-0.369-1.336 0l-5.096 5.096c-0.334-0.151-0.704-0.236-1.093-0.236-1.469 0-2.664 1.196-2.664 2.665 0 1.47 1.195 2.665 2.664 2.665s2.665-1.195 2.665-2.665c0-0.389-0.084-0.759-0.236-1.093l5.096-5.097c0.369-0.368 0.369-0.966 0-1.335zM16 11.736c-4.826 0-8.752 3.927-8.752 8.752 0 0.522-0.423 0.945-0.945 0.945s-0.944-0.423-0.944-0.945c0-5.868 4.774-10.642 10.642-10.642 1.262 0 2.497 0.219 3.671 0.65 0.49 0.18 0.74 0.723 0.561 1.212s-0.723 0.741-1.213 0.561c-0.964-0.354-1.98-0.534-3.019-0.534zM28.569 23.533c-0.131 0.543-0.595 0.908-1.153 0.908h-22.831c-0.558 0-1.022-0.364-1.153-0.908-0.24-0.991-0.361-2.015-0.361-3.044 0-7.129 5.8-12.93 12.929-12.93s12.93 5.801 12.93 12.93c0 1.029-0.121 2.053-0.361 3.044zM26.479 10.011c-2.799-2.799-6.52-4.341-10.479-4.341s-7.679 1.541-10.478 4.341c-2.799 2.799-4.341 6.52-4.341 10.478 0 1.179 0.139 2.352 0.414 3.489 0.335 1.385 1.564 2.353 2.99 2.353h22.831c1.425 0 2.654-0.968 2.989-2.353 0.275-1.137 0.414-2.311 0.414-3.489 0-3.958-1.541-7.679-4.34-10.478z"></path>
    </symbol>

    <!-- Range / Map Pins (tested range) -->
    <symbol id="icon-range" viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.86" stroke-linejoin="miter" stroke-linecap="butt" stroke-miterlimit="10">
        <path d="M24.363 18.501h2.393c1.045 0 1.892 0.847 1.892 1.892v0c0 1.045-0.847 1.892-1.892 1.892h-7.304c-1.072 0-1.941 0.869-1.941 1.941v0c0 1.072 0.869 1.941 1.941 1.941h3.556c0.978 0 1.77 0.793 1.77 1.77v0c0 0.978-0.793 1.77-1.77 1.77h-15.247"></path>
        <path d="M7.761 29.57c0 0-6.829-4.059-6.829-9.263 0-3.766 3.063-6.829 6.829-6.829s6.829 3.063 6.829 6.829c0 5.204-6.829 9.263-6.829 9.263z"></path>
        <path d="M10.406 20.228c0 1.461-1.184 2.645-2.645 2.645s-2.645-1.184-2.645-2.645c0-1.461 1.184-2.645 2.645-2.645s2.645 1.184 2.645 2.645z"></path>
        <path d="M24.239 18.355c0 0-6.829-4.045-6.829-9.248 0-3.766 3.063-6.829 6.829-6.829s6.829 3.063 6.829 6.829c0 5.204-6.829 9.248-6.829 9.248z"></path>
        <path d="M26.884 9.028c0 1.461-1.184 2.645-2.645 2.645s-2.645-1.184-2.645-2.645c0-1.461 1.184-2.645 2.645-2.645s2.645 1.184 2.645 2.645z"></path>
    </symbol>

    <!-- Weight / Scale Bag (weight) -->
    <symbol id="icon-weight" viewBox="0 0 32 32" fill="currentColor">
        <path d="M5.535 12.363c0.445-1.781 2.045-3.030 3.881-3.030h13.169c1.835 0 3.435 1.249 3.881 3.030l3.333 13.333c0.631 2.525-1.278 4.97-3.881 4.97h-19.836c-2.602 0-4.512-2.446-3.881-4.97zM9.416 12c-0.612 0-1.145 0.416-1.294 1.010l-3.333 13.333c-0.21 0.841 0.426 1.657 1.294 1.657h19.836c0.867 0 1.504-0.815 1.294-1.657l-3.333-13.333c-0.148-0.594-0.682-1.010-1.294-1.010z"></path>
        <path d="M16 4c-1.473 0-2.667 1.194-2.667 2.667s1.194 2.667 2.667 2.667 2.667-1.194 2.667-2.667-1.194-2.667-2.667-2.667zM10.667 6.667c0-2.946 2.388-5.333 5.333-5.333s5.333 2.388 5.333 5.333-2.388 5.333-5.333 5.333c-2.946 0-5.333-2.388-5.333-5.333z"></path>
    </symbol>

    <!-- Weight Scale / Max Load -->
    <symbol id="icon-weight-scale" viewBox="0 0 32 32" fill="currentColor">
        <path d="M25 2h-18c-2.761 0-5 2.239-5 5v0 18c0 2.761 2.239 5 5 5v0h18c2.761 0 5-2.239 5-5v0-18c0-2.761-2.239-5-5-5v0zM28 25c0 1.657-1.343 3-3 3v0h-18c-1.657 0-3-1.343-3-3v0-18c0-1.657 1.343-3 3-3v0h18c1.657 0 3 1.343 3 3v0zM25.78 10.82c-1.374-1.6-3.060-2.889-4.969-3.782l-0.091-0.038c-1.393-0.613-3.017-0.969-4.725-0.969s-3.332 0.357-4.802 1l0.077-0.030c-1.977 0.948-3.643 2.245-4.982 3.828l-0.018 0.022c-0.135 0.169-0.217 0.386-0.217 0.622 0 0.042 0.003 0.083 0.007 0.123l-0-0.005c0.031 0.272 0.168 0.508 0.368 0.668l0.002 0.002 4.5 3.55c0.162 0.136 0.373 0.219 0.603 0.219 0.066 0 0.131-0.007 0.193-0.020l-0.006 0.001c0.289-0.051 0.531-0.221 0.678-0.456l0.002-0.004c0.721-1.288 2.052-2.159 3.592-2.22l0.008-0c1.562 0.036 2.914 0.9 3.639 2.169l0.011 0.021c0.149 0.239 0.391 0.409 0.674 0.459l0.006 0.001c0.026 0.005 0.055 0.008 0.085 0.008s0.059-0.003 0.088-0.008l-0.003 0c0.236-0.001 0.452-0.084 0.622-0.222l-0.002 0.002 4.5-3.55c0.202-0.162 0.339-0.398 0.37-0.665l0-0.005c0.003-0.030 0.005-0.065 0.005-0.1 0-0.235-0.081-0.451-0.217-0.622l0.002 0.002zM20.69 13.57c-0.918-1.1-2.197-1.872-3.652-2.134l-0.038-0.006v-1.43c0-0.552-0.448-1-1-1s-1 0.448-1 1v0 1.43c-1.493 0.268-2.772 1.040-3.682 2.13l-0.008 0.010-2.85-2.25c1.022-1.012 2.215-1.854 3.532-2.477l0.078-0.033c1.159-0.509 2.51-0.805 3.93-0.805s2.771 0.296 3.994 0.83l-0.064-0.025c1.395 0.656 2.588 1.498 3.611 2.511l-0.001-0.001z"></path>
    </symbol>

    <!-- Battery Charging -->
    <symbol id="icon-battery-charging" viewBox="0 0 24 24" fill="currentColor">
        <path d="M5 17h-2c-0.276 0-0.525-0.111-0.707-0.293s-0.293-0.431-0.293-0.707v-8c0-0.276 0.111-0.525 0.293-0.707s0.431-0.293 0.707-0.293h3.19c0.552 0 1-0.448 1-1s-0.448-1-1-1h-3.19c-0.828 0-1.58 0.337-2.121 0.879s-0.879 1.293-0.879 2.121v8c0 0.828 0.337 1.58 0.879 2.121s1.293 0.879 2.121 0.879h2c0.552 0 1-0.448 1-1s-0.448-1-1-1zM15 7h2c0.276 0 0.525 0.111 0.707 0.293s0.293 0.431 0.293 0.707v8c0 0.276-0.111 0.525-0.293 0.707s-0.431 0.293-0.707 0.293h-3.19c-0.552 0-1 0.448-1 1s0.448 1 1 1h3.19c0.828 0 1.58-0.337 2.121-0.879s0.879-1.293 0.879-2.121v-8c0-0.828-0.337-1.58-0.879-2.121s-1.293-0.879-2.121-0.879h-2c-0.552 0-1 0.448-1 1s0.448 1 1 1zM24 13v-2c0-0.552-0.448-1-1-1s-1 0.448-1 1v2c0 0.552 0.448 1 1 1s1-0.448 1-1zM10.168 5.445l-4 6c-0.306 0.46-0.182 1.080 0.277 1.387 0.172 0.115 0.367 0.169 0.555 0.168h4.131l-2.964 4.445c-0.306 0.46-0.182 1.080 0.277 1.387s1.080 0.182 1.387-0.277l4-6c0.106-0.156 0.169-0.348 0.169-0.555 0-0.552-0.448-1-1-1h-4.131l2.964-4.445c0.306-0.46 0.182-1.080-0.277-1.387s-1.080-0.182-1.387 0.277z"></path>
    </symbol>

    <!-- Motor / Engine -->
    <symbol id="icon-motor" viewBox="0 0 32 32" fill="currentColor">
        <path d="M18.35 2.35h-12.613c-0.573 0-1.037 0.464-1.037 1.037s0.464 1.037 1.037 1.037h5.389c-1.137 0.712-2.162 1.587-3.043 2.591h-7.047c-0.573 0-1.037 0.464-1.037 1.037s0.464 1.037 1.037 1.037h5.547c-1.196 2.028-1.884 4.391-1.884 6.912 0 2.933 0.93 5.652 2.51 7.88h-2.942c-0.573 0-1.037 0.464-1.037 1.037s0.464 1.037 1.037 1.037h4.751c0.646 0.606 1.352 1.151 2.106 1.623h-9.982c-0.573 0-1.037 0.464-1.037 1.037s0.464 1.037 1.037 1.037h17.206c7.527 0 13.65-6.123 13.65-13.65s-6.123-13.65-13.65-13.65zM18.35 27.576c-6.383 0-11.576-5.193-11.576-11.576s5.193-11.576 11.576-11.576 11.576 5.193 11.576 11.576-5.193 11.577-11.576 11.577z"></path>
        <path d="M18.35 7.532c-4.669 0-8.468 3.799-8.468 8.468 0 2.785 1.351 5.259 3.432 6.804 0.019 0.016 0.038 0.032 0.059 0.047 0.023 0.017 0.047 0.032 0.071 0.047 1.385 0.988 3.079 1.57 4.907 1.57s3.521-0.582 4.907-1.57c0.024-0.015 0.048-0.030 0.071-0.047 0.020-0.015 0.040-0.031 0.059-0.047 2.081-1.544 3.432-4.019 3.432-6.804 0-4.669-3.799-8.468-8.468-8.468zM19.387 9.69c2.023 0.331 3.73 1.615 4.643 3.375l-2.685 0.872c-0.466-0.675-1.154-1.185-1.958-1.424v-2.823zM19.915 16c0 0.863-0.702 1.565-1.565 1.565s-1.565-0.702-1.565-1.565c0-0.863 0.702-1.565 1.565-1.565s1.565 0.702 1.565 1.565zM17.313 9.69v2.823c-0.804 0.239-1.492 0.749-1.958 1.424l-2.685-0.872c0.913-1.76 2.62-3.043 4.643-3.375zM13.804 20.493c-1.142-1.156-1.849-2.743-1.849-4.493 0-0.328 0.025-0.65 0.073-0.964l2.686 0.873c-0.001 0.030-0.002 0.061-0.002 0.091 0 0.831 0.281 1.597 0.751 2.211l-1.658 2.282zM18.35 22.395c-1.031 0-2.006-0.246-2.87-0.681l1.658-2.283c0.379 0.134 0.787 0.208 1.211 0.208s0.832-0.074 1.211-0.208l1.659 2.283c-0.863 0.435-1.838 0.681-2.87 0.681zM22.896 20.493l-1.658-2.282c0.471-0.613 0.751-1.38 0.751-2.211 0-0.031-0.002-0.061-0.002-0.091l2.686-0.873c0.048 0.315 0.073 0.636 0.073 0.964 0 1.75-0.706 3.337-1.849 4.493z"></path>
    </symbol>

</svg>
