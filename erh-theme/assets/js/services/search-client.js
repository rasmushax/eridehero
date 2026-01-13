/**
 * Search Client Service
 * Loads search data and provides filtering functionality
 */

let searchData = null;
let loadPromise = null;

/**
 * Load search data (lazy-loaded, cached)
 * @returns {Promise<Array>} Search items array
 */
export async function loadSearchData() {
    if (searchData) return searchData;
    if (loadPromise) return loadPromise;

    const url = window.erhData?.searchJsonUrl;
    if (!url) {
        console.warn('[Search] searchJsonUrl not configured');
        return [];
    }

    loadPromise = fetch(url)
        .then(response => {
            if (!response.ok) throw new Error(`Failed to load search data: ${response.status}`);
            return response.json();
        })
        .then(data => {
            searchData = data;
            loadPromise = null;
            return searchData;
        })
        .catch(error => {
            console.error('[Search] Failed to load search data:', error);
            loadPromise = null;
            return [];
        });

    return loadPromise;
}

/**
 * Search items by query (case-insensitive contains)
 * @param {string} query - Search query
 * @returns {Promise<Array>} Matching items
 */
export async function search(query) {
    const data = await loadSearchData();
    if (!query || query.length < 2) return [];

    const normalizedQuery = query.toLowerCase().trim();
    const words = normalizedQuery.split(/\s+/).filter(w => w.length > 0);

    return data.filter(item => {
        const title = item.title.toLowerCase();
        // All words must appear in title
        return words.every(word => title.includes(word));
    });
}

/**
 * Get type badge label
 * @param {Object} item - Search item
 * @returns {Object} Type info with label
 */
export function getTypeInfo(item) {
    const type = item.type || 'Article';

    switch (type) {
        case 'Product':
            return { label: item.product_type || 'Product' };
        case 'Tool':
            return { label: 'Tool' };
        default:
            return { label: 'Article' };
    }
}
