/**
 * This file is part of Tak-Me SalesReceiptManagement System.
 *
 * Copyright (c)2019 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */
const inputReceipt = document.querySelector('input[name=receipt]');

switch (document.readyState) {
    case 'loading' :
        window.addEventListener('DOMContentLoaded', initializeReceiptUpdater)
        break;
    case 'interactive':
    case 'complete':
        initializeReceiptUpdater();
        break;
}

function initializeReceiptUpdater(event) {
    const elements = document.querySelectorAll('[data-func-init]');
    elements.forEach(element => {
        if (typeof window[element.dataset.funcInit] === 'function') {
            window[element.dataset.funcInit].apply(window, [element]);
        }
    });
}

function initReceipt(element) {
    const parent = element.findParent('.extended-fields');
    if (parent && parent.classList.contains('linked')) {
        element.disabled = true;
        return;
    }

    if (element.form.draft.value !== '1') {
        element.addEventListener('keyup', updateReceipt);
        const relation = element.form.relation;
        if (relation) {
            relation.addEventListener('click', updateReceipt);
        }
        const cash = element.form.cash;
        if (cash) {
            cash.addEventListener('click', updateReceipt);
        }
    }
}

function updateReceipt(event) {
    const trigger = event.target;
    const form = inputReceipt.form;
    const relation = form.relation;
    const cash = form.cash;

    if (!form.receipt_number
        || !form.draft
        || form.draft.value === '1'
        || (event.type === 'click' && !relation.checked)
        || (event.type === 'keyup' && event.key !== 'Enter')
    ) {
        return;
    }

    if (event.target === relation && relation.checked) {
        if (!inputReceipt.value.match(/^[0-9]{4}[\-\/][0-9]{1,2}[\-\/][0-9]{1,2}$/)) {
            const value = prompt(relation.dataset.prompt ?? 'When is the deposit date?');
            if (value !== null) {
                inputReceipt.value = value;
            }
        }
    }

    let message = inputReceipt.dataset.confirm;
    message += (relation.checked) ? relation.dataset.checkedMessage : relation.dataset.uncheckedMessage;

    if (cash) {
        const prefix = (cash.checked) ? cash.dataset.checkedMessage : cash.dataset.uncheckedMessage;
        message = prefix + message;
    }

    if (relation.checked || inputReceipt.value.match(/^[0-9]{4}[\-\/][0-9]{1,2}[\-\/][0-9]{1,2}$/)) {
        if (confirm(message)) {
            updateReceiptExecution(form);
        }
    }
}

function updateReceiptExecution(form) {
    const data = new FormData();
    data.append('stub', form.stub.value);
    data.append('mode', 'srm.receipt.receive:update-receipt');
    data.append('issue_date', form.issue_date.value);
    data.append('receipt_number', form.receipt_number.value);
    data.append('receipt', form.receipt.value);
    data.append('draft', form.draft.value);

    data.append('company', form.company.value);
    data.append('subject', form.subject.value);

    let bankID = '';
    if (form.bank_id.options) {
        const i = form.bank_id.selectedIndex;
        if (i !== -1) {
            bankID = form.bank_id.options[i].value;
        }
    }
    data.append('bank_id', bankID);

    if (form.cash.checked) {
        data.append('cash', form.cash.value);
    }

    if (form.relation.checked) {
        data.append('relation', form.relation.value);
    }

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
            try {
                console.log(response.text());
            } catch(e) {
                //
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
        form.dataset.freeUnload = '1';
        location.href = json.url;
    })
    .catch((error) => {
        console.error(error);
    });
}
