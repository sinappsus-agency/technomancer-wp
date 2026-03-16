(function () {
    function setStatus(form, message, state) {
        var status = form.querySelector('.snc-notifuse-status');
        if (!status) {
            return;
        }

        status.textContent = message || '';
        status.className = 'snc-notifuse-status is-' + state;
    }

    function submitForm(event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.classList.contains('snc-notifuse-form')) {
            return;
        }

        event.preventDefault();
        setStatus(form, 'Submitting...', 'pending');

        var actionUrl = form.getAttribute('action') || window.location.href;

        var submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
        }

        fetch(actionUrl, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin'
        })
            .then(function (response) {
                return response.text().then(function (text) {
                    var json;

                    try {
                        json = JSON.parse(text);
                    } catch (error) {
                        json = {
                            message: text && text.trim() ? text.trim() : null
                        };
                    }

                    return { ok: response.ok, json: json };
                });
            })
            .then(function (result) {
                setStatus(form, result.json.message || (result.ok ? 'Success.' : 'Request failed.'), result.ok ? 'success' : 'error');
                if (result.ok) {
                    var redirectUrl = form.getAttribute('data-success-redirect');

                    form.reset();

                    if (redirectUrl) {
                        window.location.assign(redirectUrl);
                    }
                }
            })
            .catch(function () {
                setStatus(form, 'Request failed. Please try again.', 'error');
            })
            .finally(function () {
                if (submitButton) {
                    submitButton.disabled = false;
                }
            });
    }

    document.addEventListener('submit', submitForm);
})();