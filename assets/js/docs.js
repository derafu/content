// docs.js - Simple component to generate a table of contents for a given content.

/**
 * Generates a table of contents for a given content.
 * @param {Object} options - The options for the table of contents.
 * @param {string} options.contentId - The id of the content to generate the table of contents for.
 * @param {string} options.tocId - The id of the table of contents to generate.
 * @param {number} options.startLevel - The level of the first heading to include in the table of contents.
 * @param {number} options.endLevel - The level of the last heading to include in the table of contents.
 */
function initDocTOC(options = {}) {
    const defaults = {
        contentId: 'toc-content',
        tocId: 'toc-navbar',
        startLevel: 2,
        endLevel: 3,
    };

    const config = { ...defaults, ...options };
    const content = document.getElementById(config.contentId);
    const navbar = document.getElementById(config.tocId);

    // If no content or navbar is found, we don't do anything.
    if (content === null || navbar === null) {
        return;
    }

    const selector = Array.from(
        { length: config.endLevel - config.startLevel + 1 },
        (_, i) => `h${i + config.startLevel}`
    ).join(', ');

    const headings = content.querySelectorAll(selector);

    headings.forEach((heading) => {
        const headingId = heading.querySelector('a').id;
        const link = document.createElement('a');

        link.href = `#${headingId}`;
        link.textContent = heading.getAttribute('toc_label') || heading.textContent;

        const level = parseInt(heading.tagName[1]);
        const indent = level - config.startLevel + 1;
        link.style.paddingLeft = `${indent * 16}px`;

        navbar.appendChild(link);
    });
}

// Export the functions for use in other modules.
export { initDocTOC };
