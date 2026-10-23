// academy.js - Simple component to consume test from JSON and display a form.

/**
 * Texts of the component, in English. Each one can be replaced with the JSON
 * of the data-messages attribute of the container, for example
 * data-messages='{"reviewAnswers": "Revisar respuestas"}', or with the
 * messages argument of initTestForm(). A text with data has placeholders:
 * {correct}, {total} and {percent} in "result".
 */
const defaultMessages = {
    loadError: 'Error loading data. Please try again later.',
    true: 'True',
    false: 'False',
    reviewAnswers: 'Review answers',
    correct: 'Correct',
    incorrect: 'Incorrect',
    result: 'Result: {correct} / {total} ({percent}%)',
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
 * Initializes test form by consuming the specified API.
 * @param {HTMLElement} container - Container where form will be displayed.
 * The container must have a data-api attribute with the API URL.
 * @param {Object} messages - Texts of the component (see defaultMessages).
 */
function initTestForm(container, messages = {}) {
    // If no container is provided, use the default container.
    if (container === undefined) {
        container = document.getElementById('academy-test-container');
    }

    // If no container is found, we don't do anything.
    if (container === null) {
        return;
    }

    // Get the API URL from the container.
    const apiUrl = container.dataset.api;

    // Validate required parameters.
    if (!apiUrl) {
        console.error('Attribute data-api is required to initialize test form.');
        return;
    }

    // Texts of the component.
    const texts = getMessages(container, messages);

    // Fetch data from API.
    fetch(apiUrl)
        .then(response => {
            if (!response.ok) {
                throw new Error('API response error.');
            }
            return response.json();
        })
        .then(data => {
            // Render the form.
            renderTestForm(container, data.data || data, texts);
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
 * Renders test form in the specified container.
 * @param {HTMLElement} container - Container where form will be displayed.
 * @param {Object} data - Object with the test.
 * @param {Object} messages - Texts of the component (see defaultMessages).
 */
async function renderTestForm(container, data, messages = {}) {
    const texts = { ...defaultMessages, ...messages };
    const form = document.createElement('form');

    const title = document.createElement('h2');
    title.classList.add('mb-3');
    title.textContent = data.title;
    form.appendChild(title);

    const description = document.createElement('p');
    description.classList.add('text-muted');
    description.textContent = data.description;
    form.appendChild(description);

    let correctAnswers = {};

    data.questions.forEach((q, index) => {
        const fieldset = document.createElement('fieldset');
        fieldset.classList.add('mb-4');

        const legend = document.createElement('legend');
        legend.classList.add('fw-semibold');
        legend.textContent = `${index + 1}. ${q.text}`;
        fieldset.appendChild(legend);

        const options = q.type === 'true_false'
            ? [
                    { id: 'true', text: texts.true, is_correct: q.answer === true },
                    { id: 'false', text: texts.false, is_correct: q.answer === false }
                ]
            : q.options;

        correctAnswers[q.id] = options.filter(o => o.is_correct).map(o => o.id);

        options.forEach(opt => {
            const div = document.createElement('div');
            div.classList.add('form-check');

            const input = document.createElement('input');
            input.classList.add('form-check-input');
            input.type = (q.allow_multiple || q.type === 'multiple_choice') ? 'checkbox' : 'radio';
            input.name = `q_${q.id}`;
            input.value = opt.id;
            input.id = `q_${q.id}_${opt.id}`;

            const label = document.createElement('label');
            label.classList.add('form-check-label');
            label.setAttribute('for', input.id);
            label.textContent = opt.text;

            div.appendChild(input);
            div.appendChild(label);
            fieldset.appendChild(div);
        });

        // Div for posterior feedback.
        const feedback = document.createElement('div');
        feedback.id = `feedback_${q.id}`;
        feedback.classList.add('mt-2', 'feedback');
        fieldset.appendChild(feedback);

        form.appendChild(fieldset);
    });

    // Button to review answers.
    const submitBtn = document.createElement('button');
    submitBtn.type = 'button';
    submitBtn.classList.add('btn', 'btn-primary');
    submitBtn.innerHTML = '<i class="fas fa-check-circle me-2"></i>' + escapeHtml(texts.reviewAnswers);

    // Result score.
    const scoreEl = document.createElement('div');
    scoreEl.classList.add('mt-4', 'fw-bold');

    submitBtn.addEventListener('click', () => {
        let total = data.questions.length;
        let correct = 0;

        data.questions.forEach(q => {
            const inputs = form.querySelectorAll(`input[name="q_${q.id}"]:checked`);
            const selected = Array.from(inputs).map(i => i.value);
            const correctSet = new Set(correctAnswers[q.id]);
            const selectedSet = new Set(selected);

            const isCorrect = selectedSet.size === correctSet.size &&
                                                [...selectedSet].every(x => correctSet.has(x));

            const feedback = form.querySelector(`#feedback_${q.id}`);
            feedback.innerHTML = '';
            feedback.classList.remove('text-success', 'text-danger');

            if (isCorrect) {
                feedback.innerHTML = '<i class="fas fa-check-circle me-1 text-success"></i>' + escapeHtml(texts.correct);
                feedback.classList.add('text-success');
                correct++;
            } else {
                feedback.innerHTML = '<i class="fas fa-times-circle me-1 text-danger"></i>' + escapeHtml(texts.incorrect);
                feedback.classList.add('text-danger');
                if (q.explanation) {
                    const small = document.createElement('div');
                    small.classList.add('text-muted', 'fst-italic');
                    small.textContent = q.explanation;
                    feedback.appendChild(small);
                }
            }
        });

        scoreEl.textContent = format(texts.result, {
            correct,
            total,
            percent: Math.round((correct / total) * 100),
        });
    });

    form.appendChild(submitBtn);
    form.appendChild(scoreEl);
    container.innerHTML = '';
    container.appendChild(form);
}

// Export the functions for use in other modules.
export { initTestForm, renderTestForm };
