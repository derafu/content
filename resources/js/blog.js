// blog.js - Simple component to consume blog API and display cards.

/**
 * Texts of the component, in English. The template blog/_cards.html.twig draws
 * the container with the texts already translated. Each one can be replaced with the JSON
 * of the data-messages attribute of the container, for example
 * data-messages='{"readMore": "Leer más"}', or with the messages argument of
 * initBlogCards(). The text "byline" has the placeholders {authors}, {date} and
 * {time}.
 */
const defaultMessages = {
    loadError: 'Error loading data. Please try again later.',
    readMore: 'Read more',
    byline: 'By {authors} on {date} • {time} min read',
};

/**
 * Gets the texts of the component: the defaults, then the JSON of the
 * data-messages attribute of the container and then the messages that were
 * given.
 * @param {HTMLElement} container - Container of the component.
 * @param {Object} messages - Texts that replace the others.
 * @returns {Object}
 */
function getMessages(container, messages = {}) {
    let fromContainer = {};
    if (container.dataset.messages !== undefined) {
        try {
            fromContainer = JSON.parse(container.dataset.messages);
        } catch (error) {
            console.error('Attribute data-messages must be a JSON object.', error);
        }
    }

    return { ...defaultMessages, ...fromContainer, ...messages };
}

/**
 * Replaces the placeholders ({name}) of a text with their values.
 * @param {string} text - Text with placeholders.
 * @param {Object} values - Value of each placeholder.
 * @returns {string}
 */
function format(text, values) {
    return text.replace(/\{(\w+)\}/g, (match, name) => (name in values ? values[name] : match));
}

/**
 * Escapes a text to be put in HTML.
 * @param {string} text - Text to escape.
 * @returns {string}
 */
function escapeHtml(text) {
    const element = document.createElement('div');
    element.textContent = text;

    return element.innerHTML;
}

/**
 * Initializes blog cards by consuming the specified API.
 * @param {HTMLElement} container - Container where cards will be displayed.
 * The container must have a data-api attribute with the API URL.
 * The container can have a data-base-path attribute with the base path for images and links.
 * The container can have a data-messages attribute with the texts of the
 * component as JSON (see defaultMessages).
 * @param {Object} messages - Texts of the component (see defaultMessages).
 */
function initBlogCards(container, messages = {}) {
    // If no container is provided, use the default container.
    if (container === undefined) {
        container = document.getElementById('blog-posts');
    }

    // If no container is found, we don't do anything.
    if (container === null) {
        return;
    }

    // Get the API URL and base path from the container.
    const apiUrl = container.dataset.api;
    const basePath = container.dataset.basePath || '';
    const texts = getMessages(container, messages);

    // Validate required parameters.
    if (!apiUrl) {
        console.error('Attribute data-api is required to initialize blog cards.');
        return;
    }

    // Fetch data from API.
    fetch(apiUrl)
        .then(response => {
            if (!response.ok) {
                throw new Error('API response error.');
            }
            return response.json();
        })
        .then(data => {
            // Render the cards.
            renderBlogCards(container, data.data || data, basePath, texts);
        })
        .catch(error => {
            console.error('Error loading data:', error);
            container.innerHTML = `
                <div class="col-12">
                    <div class="alert alert-danger">${escapeHtml(texts.loadError)}</div>
                </div>
            `;
    });
}

/**
 * Renders blog cards in the specified container.
 * @param {HTMLElement} container - Container where cards will be displayed.
 * @param {Array} posts - Array of blog posts.
 * @param {string} basePath - Base path for images and links.
 * @param {Object|string} messages - Texts of the component (see defaultMessages),
 * or just the text for the read more button.
 */
function renderBlogCards(container, posts, basePath, messages = {}) {
    const texts = {
        ...defaultMessages,
        ...(typeof messages === 'string' ? { readMore: messages } : messages),
    };

    // Clear the container.
    container.innerHTML = '';

    // Create row for cards.
    const row = document.createElement('div');
    row.className = 'row';

    // Iterate over posts and create each card.
    posts.forEach(post => {
        // Create column with responsive layout (1 on mobile, 2 on medium, 4 on large).
        const col = document.createElement('div');
        col.className = 'col-12 col-md-6 mb-4';

        // Format date.
        const date = new Date(post.date);
        const formattedDate = date.toLocaleDateString(undefined, {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });

        // Create card HTML using Bootstrap 5.3.
        col.innerHTML = `
            <div class="card h-100">
                <img src="${basePath}${post.image || '/img/content/blog/default-cover.png'}" class="card-img-top" alt="${post.title}">
                <div class="card-body">
                    <h5 class="card-title">${post.title}</h5>
                    <div class="text-muted small mb-2">
                        ${escapeHtml(format(texts.byline, {
                            authors: post.authors.map(author => author.name).join(', '),
                            date: formattedDate,
                            time: post.time,
                        }))}
                    </div>
                    <p class="card-text">${post.description}</p>
                </div>
                <div class="card-footer bg-transparent border-top-0">
                    <a href="${basePath}/blog/${post.uri}" class="btn btn-primary w-100">${escapeHtml(texts.readMore)}</a>
                </div>
            </div>
        `;

        // Add column to row.
        row.appendChild(col);
    });

    // Add row to container.
    container.appendChild(row);
}

// Export the functions for use in other modules.
export { initBlogCards, renderBlogCards };
