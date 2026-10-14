document.querySelectorAll('.pill').forEach((pill) => {
    pill.addEventListener('click', () => {
        document.querySelectorAll('.pill').forEach((p) => p.classList.remove('is-active'));
        pill.classList.add('is-active');
        const filter = pill.dataset.filter;
        document.querySelectorAll('.item-card').forEach((card) => {
            card.style.display = filter === 'all' || card.dataset.category === filter ? '' : 'none';
        });
    });
});

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
