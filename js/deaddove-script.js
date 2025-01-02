document.addEventListener('DOMContentLoaded', function () {
    const modalWrappers = document.querySelectorAll('.deaddove-modal-wrapper');

    modalWrappers.forEach(function (wrapper) {
        const modal = wrapper.querySelector('.deaddove-modal');
        const blurredContent = wrapper.querySelector('.deaddove-blurred-content');
        const settingsLink = wrapper.querySelector('.deaddove-settings-link');

        // Open the modal when blurred content is clicked
        if (blurredContent) {
            blurredContent.addEventListener('click', function () {
                const blurredRect = blurredContent.getBoundingClientRect();
                const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;
                modal.style.top = `${blurredRect.top + scrollTop}px`;
                modal.style.left = `${blurredRect.left + scrollLeft}px`;
                modal.style.display = 'flex';
            });
        }

        // Use event delegation for modal buttons
        wrapper.addEventListener('click', function (event) {
            if (event.target.classList.contains('deaddove-show-content-btn')) {
                // User agreed to view the content
                modal.style.display = 'none';
                blurredContent.classList.remove('deaddove-blur');
                blurredContent.style.pointerEvents = 'none';
            } 
            else if (event.target.classList.contains('deaddove-hide-content-btn')) {
                // User chose to keep the content hidden
                modal.style.display = 'none';
            }
        });

        // Handle the settings link correctly
        if (settingsLink) {
            settingsLink.addEventListener('click', function (event) {
                event.preventDefault();  // Prevent default link behavior
                window.location.href = '/wp-admin/profile.php#deaddove-warning-settings';  // Navigate to the settings page
            });
        }
    });
});
