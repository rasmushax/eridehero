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

    <!-- YouTube Logo (full color) -->
    <symbol id="icon-youtube-logo" viewBox="0 0 28.57 20">
        <path d="M27.9727 3.12324C27.6435 1.89323 26.6768 0.926623 25.4468 0.597366C23.2197 2.24288e-07 14.285 0 14.285 0C14.285 0 5.35042 2.24288e-07 3.12323 0.597366C1.89323 0.926623 0.926623 1.89323 0.597366 3.12324C2.24288e-07 5.35042 0 10 0 10C0 10 2.24288e-07 14.6496 0.597366 16.8768C0.926623 18.1068 1.89323 19.0734 3.12323 19.4026C5.35042 20 14.285 20 14.285 20C14.285 20 23.2197 20 25.4468 19.4026C26.6768 19.0734 27.6435 18.1068 27.9727 16.8768C28.5701 14.6496 28.5701 10 28.5701 10C28.5701 10 28.5677 5.35042 27.9727 3.12324Z" fill="#FF0000"></path>
        <path d="M11.4253 14.2854L18.8477 10.0004L11.4253 5.71533V14.2854Z" fill="white"></path>
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
</svg>
