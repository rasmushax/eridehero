/**
 * Finder Type Selector Component
 * Handles ride type selection and form submission for the Quick Finder tool.
 *
 * Translates quick finder inputs (type, budget preset, priority) into
 * proper finder URL params (type, price min/max, sort).
 *
 * Both budget and priority dropdowns are type-aware — options swap when
 * the user picks a different product type (e.g. e-bike budgets are higher).
 */

/**
 * Budget preset → price range mapping.
 * Keys match the option values in the per-type budget config.
 */
const BUDGET_MAP = {
    // Escooter / E-skateboard
    'under-500':  { min: 0, max: 500 },
    '500-1000':   { min: 500, max: 1000 },
    '1000-2000':  { min: 1000, max: 2000 },
    '2000-plus':  { min: 2000, max: null },
    // E-bike
    'under-1500': { min: 0, max: 1500 },
    '1500-3000':  { min: 1500, max: 3000 },
    '3000-5000':  { min: 3000, max: 5000 },
    '5000-plus':  { min: 5000, max: null },
    // EUC
    'under-1000': { min: 0, max: 1000 },
    '2000-3000':  { min: 2000, max: 3000 },
    '3000-plus':  { min: 3000, max: null },
    // Hoverboard
    'under-100':  { min: 0, max: 100 },
    '100-200':    { min: 100, max: 200 },
    '200-300':    { min: 200, max: 300 },
    '300-plus':   { min: 300, max: null },
};

/** Priority value → finder sort param mapping. */
const PRIORITY_MAP = {
    'speed':       'speed-desc',
    'range':       'range-desc',
    'lightweight': 'weight-asc',
    'deals':       'deals',
    'power':       'torque-desc',
    'popular':     'popularity',
};

export function initFinderTabs() {
    const types = document.querySelectorAll('.finder-type');
    const hiddenInput = document.getElementById('finder-type-input');
    const form = document.querySelector('.finder-form');

    if (!types.length) return null;

    // Per-type dropdown options (injected from PHP).
    const budgetOptionsMap = JSON.parse(form?.dataset.budgetOptions || '{}');
    const priorityOptionsMap = JSON.parse(form?.dataset.priorityOptions || '{}');

    /**
     * Rebuild a custom select's options for a new product type.
     * Updates native <select>, then syncs the CustomSelect UI.
     */
    function updateSelectOptions(name, optionsMap, type) {
        const options = optionsMap[type];
        if (!options) return;

        const select = form.querySelector(`[name="${name}"]`);
        if (!select) return;

        // Rebuild native <option> elements.
        select.innerHTML = '';
        for (const [value, label] of Object.entries(options)) {
            const opt = document.createElement('option');
            opt.value = value;
            opt.textContent = label;
            select.appendChild(opt);
        }

        // Reset to empty (first "Any" option).
        select.value = '';

        // Sync CustomSelect UI.
        const customSelect = select._customSelect;
        if (customSelect) {
            customSelect.renderOptions();
            customSelect.syncFromNative();
        }
    }

    function selectType(typeBtn) {
        types.forEach(t => t.classList.remove('active'));
        typeBtn.classList.add('active');

        const type = typeBtn.dataset.type;

        // Sync hidden input.
        if (hiddenInput) {
            hiddenInput.value = type;
        }

        // Swap dropdown options for this product type.
        updateSelectOptions('budget', budgetOptionsMap, type);
        updateSelectOptions('priority', priorityOptionsMap, type);
    }

    // Type button click/keyboard events.
    types.forEach(typeBtn => {
        typeBtn.addEventListener('click', () => selectType(typeBtn));

        typeBtn.addEventListener('keydown', (e) => {
            const typesArray = Array.from(types);
            const currentIndex = typesArray.indexOf(typeBtn);
            let newIndex;

            switch (e.key) {
                case 'ArrowLeft':
                    e.preventDefault();
                    newIndex = currentIndex > 0 ? currentIndex - 1 : typesArray.length - 1;
                    typesArray[newIndex].focus();
                    selectType(typesArray[newIndex]);
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    newIndex = currentIndex < typesArray.length - 1 ? currentIndex + 1 : 0;
                    typesArray[newIndex].focus();
                    selectType(typesArray[newIndex]);
                    break;
                case 'Home':
                    e.preventDefault();
                    typesArray[0].focus();
                    selectType(typesArray[0]);
                    break;
                case 'End':
                    e.preventDefault();
                    typesArray[typesArray.length - 1].focus();
                    selectType(typesArray[typesArray.length - 1]);
                    break;
            }
        });
    });

    // Intercept form submit to build proper finder URL.
    if (form) {
        // Type → finder page URL map (injected from PHP via CategoryConfig).
        const finderUrls = JSON.parse(form.dataset.finderUrls || '{}');

        form.addEventListener('submit', (e) => {
            e.preventDefault();

            const type = hiddenInput?.value || 'escooter';
            const base = finderUrls[type] || `${window.erhData?.siteUrl || ''}/finder/`;
            const params = new URLSearchParams();

            // Budget → price range.
            const budgetSelect = form.querySelector('[name="budget"]');
            const budget = budgetSelect?.value;
            if (budget && BUDGET_MAP[budget]) {
                const { min, max } = BUDGET_MAP[budget];
                params.set('price', `${min}-${max !== null ? max : 99999}`);
            }

            // Priority → sort.
            const prioritySelect = form.querySelector('[name="priority"]');
            const priority = prioritySelect?.value;
            if (priority && PRIORITY_MAP[priority]) {
                params.set('sort', PRIORITY_MAP[priority]);
            }

            const qs = params.toString();
            window.location.href = qs ? `${base}?${qs}` : base;
        });
    }

    // Public API.
    return {
        selectType: (type) => {
            const typeBtn = document.querySelector(`.finder-type[data-type="${type}"]`);
            if (typeBtn) selectType(typeBtn);
        },
        getSelectedType: () => {
            const active = document.querySelector('.finder-type.active');
            return active ? active.dataset.type : null;
        },
    };
}
