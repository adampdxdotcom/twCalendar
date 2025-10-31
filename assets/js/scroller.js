document.addEventListener('DOMContentLoaded', function() {
    const scroller = document.querySelector('.horizontal-scroller');
    if (!scroller) return;

    const viewport = scroller.querySelector('.scroller-viewport');
    const wrapper = scroller.querySelector('.scroller-wrapper');
    const prevBtn = scroller.querySelector('.scroller-arrow--prev');
    const nextBtn = scroller.querySelector('.scroller-arrow--next');
    const items = Array.from(scroller.querySelectorAll('.scroller-item'));
    const gap = parseInt(getComputedStyle(wrapper).getPropertyValue('gap')) || 0;

    if (!items.length) return;

    // On mobile, native scroll is used, so we exit.
    if (window.getComputedStyle(prevBtn).display === 'none') {
        return;
    }

    let itemsPerPage = 0;
    let currentIndex = 0;

    const calculateLayout = () => {
        const itemWidth = items[0].offsetWidth;
        // Calculate how many full items can fit
        itemsPerPage = Math.floor((viewport.clientWidth + gap) / (itemWidth + gap));
        
        // Hide arrows if no scrolling is needed
        if (wrapper.scrollWidth <= viewport.clientWidth) {
            prevBtn.style.display = 'none';
            nextBtn.style.display = 'none';
        } else {
            prevBtn.style.display = 'flex';
            nextBtn.style.display = 'flex';
        }
        updateArrowState();
    };

    const updateArrowState = () => {
        prevBtn.disabled = currentIndex === 0;
        // The last possible "page" starts at this index
        const lastPageStartIndex = items.length - itemsPerPage;
        nextBtn.disabled = currentIndex >= lastPageStartIndex;
    };
    
    const scrollToItem = (index) => {
        // Ensure index is within bounds
        const targetIndex = Math.max(0, Math.min(index, items.length - itemsPerPage));
        
        // Get the target item
        const targetItem = items[targetIndex];
        
        if (targetItem) {
            // Calculate the precise scroll position
            const scrollLeft = targetItem.offsetLeft - items[0].offsetLeft;
            
            viewport.scrollTo({
                left: scrollLeft,
                behavior: 'smooth'
            });
            
            currentIndex = targetIndex;
            updateArrowState();
        }
    };

    nextBtn.addEventListener('click', () => {
        scrollToItem(currentIndex + itemsPerPage);
    });

    prevBtn.addEventListener('click', () => {
        scrollToItem(currentIndex - itemsPerPage);
    });
    
    // Use a ResizeObserver to automatically recalculate on size change
    new ResizeObserver(() => {
        calculateLayout();
        // Snap to the current item's page on resize
        scrollToItem(currentIndex);
    }).observe(viewport);

    // Initial setup
    calculateLayout();
});
