jQuery(function ($) {
    console.log('Single Product Page JS loaded');
    function toggleAiWarning() {
        const warning = document.querySelector('.ai-notice');
        const descriptionTab = document.querySelector('#tab-title-description');

        if (!warning || !descriptionTab) {
            return;
        }

        warning.style.display = descriptionTab.classList.contains('active')
            ? 'block'
            : 'none';
    }

    // Initial state
    toggleAiWarning();

    // Watch for tab changes
    const tabs = document.querySelector('.wc-tabs');

    new MutationObserver(toggleAiWarning).observe(tabs, {
        subtree: true,
        attributes: true,
        attributeFilter: ['class']
    });

});