document.addEventListener('DOMContentLoaded', function() {
    const calendarContainer = document.querySelector('.calendar-container');
    if (!calendarContainer) return;

    const modalOverlay = calendarContainer.querySelector('.modal-overlay');
    const modalContentInner = calendarContainer.querySelector('.modal-content-inner');
    const modalCloseButton = calendarContainer.querySelector('.modal-close-button');
    
    function openModal(targetSelector) {
        const contentSource = document.querySelector(targetSelector);
        if (contentSource) {
            modalContentInner.innerHTML = contentSource.innerHTML;
            modalOverlay.style.display = 'flex';
        }
    }

    function closeModal() {
        modalOverlay.style.display = 'none';
        modalContentInner.innerHTML = '';
    }

    calendarContainer.addEventListener('click', function(e) {
        const link = e.target.closest('.event-link');
        
        // This logic is now correct because the href points to the real page.
        if (link && window.innerWidth > 1000) { 
            e.preventDefault(); // On DESKTOP, stop the link and show the modal.
            
            const modalTarget = link.getAttribute('data-modal-target');
            if (modalTarget) {
                openModal(modalTarget);
            }
        }
        // On MOBILE (<= 1000px), this 'if' block is skipped, and the link
        // is allowed to navigate to its 'href', which is now the correct event page.
    });

    modalCloseButton.addEventListener('click', closeModal);
    modalOverlay.addEventListener('click', function(e) {
        if (e.target === modalOverlay) {
            closeModal();
        }
    });
});
