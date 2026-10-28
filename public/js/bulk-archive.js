(() => {
    document.querySelectorAll('form[data-bulk-archive]').forEach((form) => {
        const formId = form.id;
        const items = [...document.querySelectorAll(`input[data-archive-item][form="${formId}"]`)];
        const selectAll = document.querySelector(`[data-archive-select-all="${formId}"]`);
        const button = form.querySelector('button[type="submit"]');
        const count = form.querySelector('[data-archive-selection-count]');
        if (!items.length || !button) return;

        const refresh = () => {
            const selected = items.filter((item) => item.checked).length;
            button.disabled = selected === 0;
            if (count) count.textContent = `${selected} selected`;
            if (selectAll) {
                selectAll.checked = selected === items.length;
                selectAll.indeterminate = selected > 0 && selected < items.length;
            }
        };

        items.forEach((item) => item.addEventListener('change', refresh));
        selectAll?.addEventListener('change', () => {
            items.forEach((item) => { item.checked = selectAll.checked; });
            refresh();
        });
        refresh();
    });
})();
