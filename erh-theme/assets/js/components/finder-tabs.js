/**
 * Finder Type Selector Component
 * Handles ride type selection for the Quick Finder tool
 */

export function initFinderTabs() {
    const types = document.querySelectorAll('.finder-type');

    if (!types.length) return null;

    function selectType(typeBtn) {
        // Update type states
        types.forEach(t => t.classList.remove('active'));
        typeBtn.classList.add('active');
    }

    // Event listeners
    types.forEach(typeBtn => {
        typeBtn.addEventListener('click', () => selectType(typeBtn));

        // Keyboard navigation
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

    // Public API
    return {
        selectType: (type) => {
            const typeBtn = document.querySelector(`.finder-type[data-type="${type}"]`);
            if (typeBtn) selectType(typeBtn);
        },
        getSelectedType: () => {
            const active = document.querySelector('.finder-type.active');
            return active ? active.dataset.type : null;
        }
    };
}
