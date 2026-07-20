/**
 * Every submit button reports that it is working.
 *
 * A form that posts gives no feedback until the server responds. On a slow
 * connection — the normal case for our users — that silence reads as "nothing
 * happened", so people click again and submit twice. This puts a spinner on the
 * button, disables it, and swaps the label for the duration of the request.
 *
 * Applied at the document level rather than per button, so a button added to any
 * page later is covered without anyone remembering to opt in.
 *
 * Opt out with `data-no-loading` on the form or the button.
 */

const SPINNER = `
<svg class="submit-spinner" viewBox="0 0 24 24" fill="none" aria-hidden="true">
    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity="0.25"/>
    <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
</svg>`;

function markBusy(button) {
    if (button.dataset.submitBusy === '1') {
        return;
    }

    button.dataset.submitBusy = '1';
    button.dataset.originalHtml = button.innerHTML;

    // The spinner joins the existing label rather than replacing it. Swapping the text
    // for "Working…" threw away the one piece of information the user actually wanted —
    // which action they had just pressed — and made the button resize mid-click.
    button.insertAdjacentHTML('afterbegin', SPINNER);

    // aria-disabled rather than disabled: a disabled button is dropped from the
    // submitted payload, which would lose the value of a named submit button.
    button.setAttribute('aria-disabled', 'true');
    button.classList.add('is-submitting');
}

function releaseBusy(button) {
    if (button.dataset.submitBusy !== '1') {
        return;
    }

    button.innerHTML = button.dataset.originalHtml ?? button.innerHTML;
    delete button.dataset.submitBusy;
    delete button.dataset.originalHtml;
    button.removeAttribute('aria-disabled');
    button.classList.remove('is-submitting');
}

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-no-loading')) {
        return;
    }

    // A cancelled submit (failed validation, or a confirm() the user declined)
    // must not leave the button spinning forever.
    if (event.defaultPrevented) {
        return;
    }

    const button = form.querySelector(
        'button[type="submit"]:not([data-no-loading]), button:not([type]):not([data-no-loading])'
    );

    if (button) {
        markBusy(button);
    }
});
// Bubble phase deliberately: an inline `onsubmit="return confirm(...)"` runs on the
// form itself, so listening here means a declined confirm has already set
// defaultPrevented and the button never starts spinning.

// Returning via the back/forward cache restores the old DOM, spinner and all.
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        document.querySelectorAll('[data-submit-busy="1"]').forEach(releaseBusy);
    }
});
