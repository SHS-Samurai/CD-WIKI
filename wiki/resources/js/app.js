

import './wiki-editor';

document.addEventListener('submit', event => {
    const message = event.target.dataset.confirm;
    if (message && !window.confirm(message)) event.preventDefault();
});

document.addEventListener('click', event => {
    const opener = event.target.closest('[data-open-modal]');
    if (opener) {
        event.preventDefault();
        document.getElementById(opener.dataset.openModal)?.showModal();
    }

    const closer = event.target.closest('[data-close-modal]');
    if (closer) closer.closest('dialog')?.close();
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('dialog[data-initial-open]').forEach(dialog => dialog.showModal());
    document.querySelectorAll('[data-subject-select]').forEach(select => {
        const update = () => document.querySelectorAll('[data-subject-panel]').forEach(panel => {
            panel.hidden = panel.dataset.subjectPanel !== select.value;
        });
        select.addEventListener('change', update);
        update();
    });
});
