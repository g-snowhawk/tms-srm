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
    }
}

function updateReceipt(event) {
    if (event.type === 'keyup' && event.key !== 'Enter') {
        return;
    }

    const form = inputReceipt.form;
    const relation = form.relation;

    if (event.target === relation && relation.checked) {
        if (!inputReceipt.value.match(/^[0-9]{4}[\-\/][0-9]{1,2}[\-\/][0-9]{1,2}$/)) {
            const value = prompt('ha?');
            if (value !== null) {
                inputReceipt.value = value;
            }
        }
    }

    let message = inputReceipt.dataset.confirm;
    message += (relation.checked) ? relation.dataset.checkedMessage : relation.dataset.uncheckedMessage;

    if (inputReceipt.value.match(/^[0-9]{4}[\-\/][0-9]{1,2}[\-\/][0-9]{1,2}$/)) {
        if (confirm(message)) {
            for (let i = 0; i < form.elements.length; i++) {
                const element = form.elements[i];
                element.disabled = false;
            }

            const hiddens = {
                's1_submit': 'via JS',
                'faircopy': '0',
                'create-pdf': 'none',
            };
            for (let name in hiddens) {
                let hidden = form.appendChild(document.createElement('input'));
                hidden.type = 'hidden';
                hidden.name = name;
                hidden.value = hiddens[name];
            }

            form.submit();
        }
    } else if (relation.checked) {
        if (confirm(message)) {
            for (let i = 0; i < form.elements.length; i++) {
                const element = form.elements[i];
                element.disabled = false;
            }

            const hiddens = {
                's1_submit': 'via JS',
                'faircopy': '0',
                'create-pdf': 'none',
            };
            for (let name in hiddens) {
                let hidden = form.appendChild(document.createElement('input'));
                hidden.type = 'hidden';
                hidden.name = name;
                hidden.value = hiddens[name];
            }

            form.submit();
        }
    }
}
