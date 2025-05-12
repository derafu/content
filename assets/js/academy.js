// academy.js - Simple component to consume test from JSON and display a form.

/**
 * Initializes test form by consuming the specified API.
 * @param {HTMLElement} container - Container where form will be displayed.
 * The container must have a data-api attribute with the API URL.
 */
function initTestForm(container) {
    // Get the API URL from the container.
    const apiUrl = container.dataset.api;

    // Validate required parameters.
    if (!apiUrl) {
        console.error('Attribute data-api is required to initialize test form.');
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
            // Render the form.
            renderTestForm(container, data.data || data);
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
 * Renders test form in the specified container.
 * @param {HTMLElement} container - Container where form will be displayed.
 * @param {Object} data - Object with the test.
 */
async function renderTestForm(container, data) {
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
                    { id: 'true', text: 'True / Verdadero', is_correct: q.answer === true },
                    { id: 'false', text: 'False / Falso', is_correct: q.answer === false }
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
    submitBtn.innerHTML = '<i class="fas fa-check-circle me-2"></i>Review answers';

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
                feedback.innerHTML = '<i class="fas fa-check-circle me-1 text-success"></i>Correct';
                feedback.classList.add('text-success');
                correct++;
            } else {
                feedback.innerHTML = '<i class="fas fa-times-circle me-1 text-danger"></i>Incorrect';
                feedback.classList.add('text-danger');
                if (q.explanation) {
                    const small = document.createElement('div');
                    small.classList.add('text-muted', 'fst-italic');
                    small.textContent = q.explanation;
                    feedback.appendChild(small);
                }
            }
        });

        scoreEl.textContent = `Result: ${correct} / ${total} (${Math.round((correct / total) * 100)}%)`;
    });

    form.appendChild(submitBtn);
    form.appendChild(scoreEl);
    container.innerHTML = '';
    container.appendChild(form);
}

// Export the functions for use in other modules.
export { initTestForm, renderTestForm };
