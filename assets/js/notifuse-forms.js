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
        if (!form.classList.contains('snc-notifuse-form')) {
            return;
        }

        event.preventDefault();
        setStatus(form, 'Submitting...', 'pending');

        var submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
        }

        fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin'
        })
            .then(function (response) {
                return response.json().then(function (json) {
                    return { ok: response.ok, json: json };
                });
            })
            .then(function (result) {
                setStatus(form, result.json.message || (result.ok ? 'Success.' : 'Request failed.'), result.ok ? 'success' : 'error');
                if (result.ok) {
                    form.reset();
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