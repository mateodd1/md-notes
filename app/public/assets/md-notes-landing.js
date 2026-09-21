(() => {
    const statusUrl = document.documentElement.dataset.sessionStatusUrl;
    const sessionLinks = document.querySelectorAll('[data-session-link]');

    if (!statusUrl || sessionLinks.length === 0) {
        return;
    }

    fetch(statusUrl, {
        method: 'GET',
        credentials: 'include',
        cache: 'no-store',
        headers: {
            Accept: 'application/json',
        },
    })
        .then((response) => response.ok ? response.json() : null)
        .then((status) => {
            if (!status?.authenticated) {
                return;
            }

            sessionLinks.forEach((link) => {
                link.href = link.dataset.authenticatedHref;
                const label = link.querySelector('[data-session-label]');

                if (label) {
                    label.textContent = link.dataset.authenticatedLabel;
                }
            });
        })
        .catch(() => {
            // The regular login and signup links remain available if the check fails.
        });
})();
