/**
 * Validates a form based on custom rules.
 * @param formEl
 * @returns {boolean}
 */
export function validateForm(formEl) {
    const errors = [];

    // Beispiel-Regeln:
    const title = formEl.querySelector('[name="title"]');
    if (!title.value.trim()) {
        errors.push({field: title, message: 'Titel ist erforderlich'});
    }

    const fromdate = formEl.querySelector('[name="fromdate"]');
    if (!fromdate.value) {
        errors.push({field: fromdate, message: 'Startdatum ist erforderlich'});
    }

    const todate = formEl.querySelector('[name="todate"]');
    if (todate.value && fromdate.value && todate.value < fromdate.value) {
        errors.push({field: todate, message: 'Enddatum darf nicht vor Startdatum liegen'});
    }

    const organizer = formEl.querySelector('[name="organizer"]');
    if (!organizer.value.trim()) {
        errors.push({field: organizer, message: 'Veranstalter ist erforderlich'});
    }

    const email = formEl.querySelector('[name="email"]');
    // Reset previous error styles
    formEl.querySelectorAll('.wpem-error').forEach(el => el.classList.remove('wpem-error'));
    formEl.querySelectorAll('.wpem-error-msg').forEach(el => el.remove());

    // Mark fields
    errors.forEach(err => {
        err.field.classList.add('wpem-error');
        const span = document.createElement('span');
        span.className = 'wpem-error-msg';
        span.textContent = err.message;
        err.field.insertAdjacentElement('afterend', span);
    });

    return errors.length === 0;
}
