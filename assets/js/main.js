// assets/js/main.js
document.addEventListener('DOMContentLoaded', () => {
    // Budget Bar Animation
    const bars = document.querySelectorAll('.budget-bar-fill');
    bars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0%';
        setTimeout(() => {
            bar.style.width = width;
        }, 100);
    });

    // Simple confirmation for deletions
    const deleteButtons = document.querySelectorAll('.delete-btn');
    deleteButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            if (!confirm("Are you sure you want to delete this item?")) {
                e.preventDefault();
            }
        });
    });
});
