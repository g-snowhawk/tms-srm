/**
 * This file is part of Tak-Me SalesReceiptManagement System.
 *
 * Copyright (c)2019 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */
'use strict';

const inputReceipt = document.querySelector('input[name=receipt]');
const inputRelation = document.querySelector('input[name=relation]');

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
        this[element.dataset.funcInit].apply(this, [element]);
    });
}

function initReceipt(element) {
    const parent = element.findParent('.extended-fields');
    if (parent && parent.classList.contains('linked')) {
        element.disabled = true;
        return;
    }

    if (element.form.draft.value !== '1') {
        element.addEventListener('keyup', update);
    }
}

function update(event) {
    if (event.type === 'keyup' && event.key !== 'Enter') {
        return;
    }
    if (inputReceipt.value.match(/^[0-9]{4}[^0-9][0-9]{1,2}[^0-9][0-9]{1,2}$/)) {

        const form = inputReceipt.form;

        let message = '入金日を更新します。';
        message += (form.relation.checked) ? '伝票に記帳されます' : '伝票には記帳されません';

        if (confirm(message)) {
            const form = inputReceipt.form;
            for (let i = 0; i < form.elements.length; i++) {
                const element = form.elements[i];
                element.disabled = false;
            }

            const hiddens = { 's1_submit': 'via JS', 'faircopy': '0' };
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
