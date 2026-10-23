(() => {
    const filters = [...document.querySelectorAll('.pill[data-filter]')];
    const cards = [...document.querySelectorAll('.item-grid[data-pagination-list="home-menu"] .item-card')];
    const pager = document.querySelector('[data-pagination="home-menu"]');
    if (filters.length && cards.length && pager) {
        const pageSize = 12;
        let selected = 'all';
        let page = 1;
        const matches = () => cards.filter(card => selected === 'all' || card.dataset.category === selected);
        function render() {
            const filtered = matches();
            const pageCount = Math.max(1, Math.ceil(filtered.length / pageSize));
            page = Math.min(page, pageCount);
            cards.forEach(card => { card.hidden = true; });
            filtered.slice((page - 1) * pageSize, page * pageSize).forEach(card => { card.hidden = false; });
            pager.replaceChildren();
            if (filtered.length <= pageSize) return;
            const addButton = (label, target, disabled = false, active = false) => {
                const button = document.createElement('button');
                button.type = 'button'; button.className = 'page-button' + (active ? ' is-active' : '');
                button.textContent = label; button.disabled = disabled;
                if (active) button.setAttribute('aria-current', 'page');
                button.addEventListener('click', () => { page = target; render(); });
                pager.append(button);
            };
            addButton('‹', page - 1, page === 1);
            for (let n = 1; n <= pageCount; n++) addButton(String(n), n, false, n === page);
            addButton('›', page + 1, page === pageCount);
        }
        filters.forEach(button => button.addEventListener('click', () => {
            selected = button.dataset.filter; page = 1;
            filters.forEach(filter => filter.classList.toggle('is-active', filter === button));
            render();
        }));
        render();
    }
})();

document.querySelectorAll('.item-card[data-href]').forEach((card) => {
    const openCard = (event) => {
        if (event.target.closest('a, button, form, input')) return;
        window.location.href = card.dataset.href;
    };
    card.addEventListener('click', openCard);
    card.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            window.location.href = card.dataset.href;
        }
    });
});
