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
