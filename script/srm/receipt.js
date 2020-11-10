/**
 * This file is part of Tak-Me SalesReceiptManagement System.
 *
 * Copyright (c)2019 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */
let searchQueries = [];
let clearSearches = [];
let searchTimer = undefined;

switch (document.readyState) {
    case 'loading' :
        window.addEventListener('DOMContentLoaded', initializeReceiptList)
        break;
    case 'interactive':
    case 'complete':
        initializeReceiptList();
        break;
}

function initializeReceiptList(event) {
    document.querySelectorAll('input.search-query').forEach((element) => {
        element.form.dataset.freeUnload = "1";
        element.dataset.previous = element.value;
        element.addEventListener('compositionstart', execSearchReceipt);
        element.addEventListener('compositionend', execSearchReceipt);
        element.addEventListener('keyup', execSearchReceipt);

        searchQueries.push(element);

        let next = element.nextSibling;
        while (next.nodeType != Node.ELEMENT_NODE) {
            next = next.nextSibling;
        }
        if (next.classList.contains('clear-search')) {
            next.style.visibility = (element.value.length === 0) ? 'hidden' : 'visible';
            next.addEventListener('click', execSearchReceipt);
        }
        clearSearches.push(next);
    });

    document.querySelectorAll('a.run-mailer').forEach((element) => {
        element.addEventListener('click', runMailerForReceipt);
    });
}

function getSearchURI(keywords) {
    let query = location.search.replace(/[&\?]?[qp]=[^=&\?]+/g, '');
    const separator = query.length > 0 ? '&' : '?';
    query += separator + "p=1";
    if (keywords !== undefined && keywords.length > 0) {
        query += "&q=" + encodeURIComponent(element.value);
    }
    return location.pathname + query;
}

function execSearchReceipt(event) {
    const element = event.target;
    switch (event.type) {
        case "click":
            event.stopPropagation();
            let prev = element.previousSibling;
            while (prev.nodeType != Node.ELEMENT_NODE) {
                prev = prev.previousSibling;
            }
            if (prev.nodeName.toLowerCase() === 'input') {
                prev.value = '';
                location.href = getSearchURI() + '&q=';
            }
            return;
        case "compositionend":
            element.dataset.composing = "end";
            return;
        case "compositionstart":
            element.dataset.composing = "start";
            return;
        case "keydown":
        case "keyup":
            if (searchTimer > 0) {
                clearTimeout(searchTimer);
                element.disabled = false;
                element.dataset.previous = "";
                searchTimer = undefined;
            }

            let i = searchQueries.indexOf(element);
            const next = clearSearches[i];
            if (next.classList.contains('clear-search')) {
                next.style.visibility = (element.value.length === 0) ? 'hidden' : 'visible';
            }

            if (event.key !== "Enter" || element.value === element.dataset.previous) {
                return;
            }
            if (element.dataset.composing === "end") {
                delete element.dataset.composing;
                return;
            }
            break;
        default:
            return;
    }

    if (element.dataset.composing) {
        console.warn(element.dataset.composing);
        return;
    }

    element.dataset.previous = element.value;
    element.disabled = true;
    searchTimer = setTimeout((url) => {
        location.href = url;
    }, 300, getSearchURI(element.value));
}

function runMailerForReceipt(event) {
    event.preventDefault();
    const element = event.target;

    fetch(element.href, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
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
            throw new ApplicationError(json.message);
        }
        openMailer(json);
    })
    .catch((error) => {
        if (error.name === 'ApplicationError') {
            alert(error.message);
        }
        console.error(error);
    });
}

function openMailer(data) {
    const template = document.getElementById('mailer-template');
    if (!template) return;

    document.body.appendChild(template.content.cloneNode(true));
    const mailer = document.querySelector('#mailer-container');

    const token = mailer.querySelector('input[name=stub]');
    token.value = data.token;

    for (let key in data.headers) {
        const element = mailer.querySelector('input[name=' + key + ']');
        if (element) {
            element.value = data.headers[key];
        }
    }

    const pdfPath = mailer.querySelector('input[name=pdf_path]');
    const attachmentName = mailer.querySelector('input[name=attachment_name]');
    if (data.pdf) {
        pdfPath.value = data.pdf.path;
        attachmentName.value = data.pdf.attachment_name;
        const text = document.createTextNode(data.pdf.attachment_name + ' ( ' + data.pdf.size + 'bytes )');
        pdfPath.parentNode.insertBefore(text, pdfPath);
    } else {
        pdfPath.parentNode.parentNode.removeChild(pdfPath.parentNode);
    }

    const textarea = mailer.querySelector('textarea');
    textarea.value = data.template;

    const canceller = mailer.querySelector('input[type=reset]');
    canceller.addEventListener('click', closeMailer);

    const submit = mailer.querySelector('input[type=submit]');
    submit.addEventListener('click', sendMail);
}

function closeMailer(event) {
    event.preventDefault();
    const mailer = document.querySelector('#mailer-container');
    mailer.parentNode.removeChild(mailer);
}

function sendMail(event) {
    event.preventDefault();
    const element = event.target;
    const form = element.form;

    const formData = new FormData(form);

    fetch(form.action, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
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
            throw new ApplicationError(json.message);
        }
        // some functions
        closeMailer(event);
    })
    .catch((error) => {
        if (error.name === 'ApplicationError') {
            alert(error.message);
        }
        console.error(error);
    });
}


class ApplicationError extends Error {
    constructor(message) {
        super(message);
        this.name = 'ApplicationError';
    }
}
