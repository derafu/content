// blog.js - Simple component to consume blog API and display cards.

/**
 * Initializes blog cards by consuming the specified API.
 * @param {HTMLElement} container - Container where cards will be displayed.
 * The container must have a data-api attribute with the API URL.
 * The container can have a data-base-path attribute with the base path for images and links.
 * The container can have a data-read-more attribute with the text for the read more button.
 */
function initBlogCards(container) {
    // Get the API URL and base path from the container.
    const apiUrl = container.dataset.api;
    const basePath = container.dataset.basePath || '';
    const readMore = container.dataset.readMore || 'Read more';

    // Validate required parameters.
    if (!apiUrl) {
        console.error('apiUrl is required to initialize blog cards.');
        return;
    }

    // Fetch data from API
    fetch(apiUrl)
        .then(response => {
            if (!response.ok) {
                throw new Error('API response error.');
            }
            return response.json();
        })
        .then(data => {
            // Render the cards.
            renderBlogCards(container, data.data || data, basePath, readMore);
        })
        .catch(error => {
            console.error('Error loading data:', error);
            container.innerHTML = `
                <div class="col-12">
                    <div class="alert alert-danger">Error loading data. Please try again later.</div>
                </div>
            `;
    });
}

/**
 * Renders blog cards in the specified container.
 * @param {HTMLElement} container - Container where cards will be displayed.
 * @param {Array} posts - Array of blog posts.
 * @param {string} basePath - Base path for images and links.
 * @param {string} readMore - Text for the read more button.
 */
function renderBlogCards(container, posts, basePath, readMore) {
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
        const publishDate = new Date(post.published);
        const formattedDate = publishDate.toLocaleDateString(undefined, {
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
                        By ${post.author.name} on ${formattedDate} • ${post.time} min read
                    </div>
                    <p class="card-text">${post.summary}</p>
                </div>
                <div class="card-footer bg-transparent border-top-0">
                    <a href="${basePath}/blog/${post.uri}" class="btn btn-primary w-100">${readMore}</a>
                </div>
            </div>
        `;

        // Add column to row.
        row.appendChild(col);
    });

    // Add row to container.
    container.appendChild(row);
}

// Export the functions for use in other modules
export { initBlogCards, renderBlogCards };
