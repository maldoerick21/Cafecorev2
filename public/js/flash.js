// Automatically dismiss flash/error messages after 3.5 seconds.
document.querySelectorAll('.toast').forEach((toast) => {
    window.setTimeout(() => {
        toast.classList.add('toast-hide');
        toast.addEventListener('transitionend', () => toast.remove(), { once: true });
        window.setTimeout(() => toast.remove(), 500);
    }, 3500);
});
