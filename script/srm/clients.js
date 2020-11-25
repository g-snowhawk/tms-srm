/**
 * This file is part of Tak-Me SalesReceiptManagement System.
 *
 * Copyright (c)2020 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */
switch (document.readyState) {
    case 'loading' :
        window.addEventListener('DOMContentLoaded', clientListInit)
        break;
    case 'interactive':
    case 'complete':
        clientListInit();
        break;
}

function clientListInit(event) {
    const elements = document.querySelectorAll('input[name^=no_suggestion]');
    elements.forEach((element) => {
        element.addEventListener('click', updateClientProperties);
    });
}

function updateClientProperties(event) {
    const element = event.target;
    const form = element.form;

    const data = new FormData(form);
    data.append('client_id', element.dataset.clientId);

    fetch(form.action, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: data
    })
    .then((response) => {
        if (response.ok) {
            let contentType = response.headers.get("content-type");
            if (contentType.match(/^application\/json/)) {
                return response.json();
            }
            throw new Error("Unexpected response");
        } else {
            throw new Error("Server Error");
        }
    })
    .then((json) => {
        if (json.status !== 0) {
            throw new Error(json.message);
        }
        // some functions
    })
    .catch((error) => {
        console.error(error);
    });
}
