document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.clean-sweep-form');
    if (!form) return;

    const countEl = form.querySelector('.clean-sweep-selected-count');

    function updateCount() {
        const checked = form.querySelectorAll('input[name="cs_items[]"]:checked').length;
        if (countEl) {
            countEl.textContent = checked > 0
                ? clean_sweep_i18n.selected.replace('%d', checked)
                : '';
        }
    }

    const cards = form.querySelectorAll('.clean-sweep-card');
    cards.forEach(function(card) {
        const checkbox = card.querySelector('input[type="checkbox"]');
        if (!checkbox) return;

        card.addEventListener('click', function(e) {
            if (e.target === checkbox || e.target.tagName === 'A' || e.target.tagName === 'BUTTON' || e.target.closest('a') || e.target.closest('button')) {
                return;
            }
            if (!checkbox.disabled) {
                checkbox.checked = !checkbox.checked;
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        checkbox.addEventListener('change', updateCount);
    });

    const selectAll = document.createElement('button');
    selectAll.type = 'button';
    selectAll.className = 'button clean-sweep-select-all';
    selectAll.textContent = clean_sweep_i18n.selectAll;

    const actions = form.querySelector('.clean-sweep-actions');
    if (actions) {
        actions.appendChild(selectAll);
    }

    let allChecked = false;
    selectAll.addEventListener('click', function() {
        allChecked = !allChecked;
        const boxes = form.querySelectorAll('input[name="cs_items[]"]:not(:disabled)');
        boxes.forEach(function(box) {
            box.checked = allChecked;
            box.dispatchEvent(new Event('change', { bubbles: true }));
        });
        selectAll.textContent = allChecked ? clean_sweep_i18n.deselectAll : clean_sweep_i18n.selectAll;
    });

    form.addEventListener('submit', function() {
        const checked = form.querySelectorAll('input[name="cs_items[]"]:checked').length;
        if (checked === 0) {
            alert(clean_sweep_i18n.nothingSelected);
            return false;
        }
    });
});
