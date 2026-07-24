document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.clean-sweep-form');
    if (!form) return;

    const cards = form.querySelectorAll('.clean-sweep-card');
    cards.forEach(function(card) {
        const checkbox = card.querySelector('input[type="checkbox"]');
        if (!checkbox) return;

        card.addEventListener('click', function(e) {
            if (e.target === checkbox || e.target.tagName === 'A' || e.target.tagName === 'BUTTON') {
                return;
            }
            if (!checkbox.disabled) {
                checkbox.checked = !checkbox.checked;
            }
        });
    });

    const selectAll = document.createElement('button');
    selectAll.type = 'button';
    selectAll.className = 'button clean-sweep-select-all';
    selectAll.textContent = clean_sweep_i18n.selectAll || 'Select All';

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
        });
        selectAll.textContent = allChecked ? (clean_sweep_i18n.deselectAll || 'Deselect All') : (clean_sweep_i18n.selectAll || 'Select All');
    });
});
