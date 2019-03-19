/**
 * This file is part of Tak-Me SalesReceiptManagement System.
 *
 * Copyright (c)2019 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */
'use strict';

const debugLevel = 0;

const formatter = new Intl.NumberFormat('ja-JP');
const displaySubtotal = document.getElementById('subtotal');
const displayTotal = document.getElementById('total');
const displayTax = document.getElementById('tax');
const inputAPrice = document.querySelector('input[name=additional_1_price]');
const inputBPrice = document.querySelector('input[name=additional_2_price]');
const inputCompany = document.querySelector('input[name=company]');

const displayTaxRate = document.getElementById('tax-rate');
const taxRate = (displayTaxRate) ? parseFloat(displayTaxRate.dataset.rate) : NaN;
if (isNaN(taxRate)) {
    console.warn('Tax rate has novalue');
}

const displayReducedTaxRate = document.getElementById('reduced-tax-rate');
const reducedTaxRate = (displayReducedTaxRate) ? parseFloat(displayReducedTaxRate.dataset.rate) : NaN;

const suggestionListContainer = document.getElementById('client-data');
const suggestionListID = 'suggestion-list';
const suggestionDataClassName = 'client-data';

let suggestionListLock = false;
let suggestionTimer = 0;
let suggestionTimeout = 800;
let suggestionBrowser = new AbortController();
let valueAtKeyDown = undefined;
let isComposing = false;

switch (document.readyState) {
    case 'loading' :
        window.addEventListener('DOMContentLoaded', initializeReceiptEditor)
        break;
    case 'interactive':
    case 'complete':
        initializeReceiptEditor();
        break;
}

function initializeReceiptEditor(event) {
    fixedSizeToParent();
    setEventListener();
    culculateSubTotals(event);
    lockForm();

    inputAPrice.addEventListener('keyup', culculateTotals);
    inputBPrice.addEventListener('keyup', culculateTotals);

    inputCompany.addEventListener('keydown', listenerForSuggestion);
    inputCompany.addEventListener('keyup', listenerForSuggestion);
    inputCompany.addEventListener('compostionstart', switchComposing);
    inputCompany.addEventListener('compostionend', switchComposing);
    inputCompany.addEventListener('blur', listenerForSuggestion);

    const pulldowns = document.getElementsByClassName('with-other');
    for (let i = 0; i < pulldowns.length; i++) {
        const element = pulldowns[i];
        element.addEventListener('change', selectedPulldown);
        element.dataset.currentIndex = element.selectedIndex;

        const selectedOption = element.options[element.selectedIndex];
        if (selectedOption.value === '') {
            element.classList.add('default');
        } else {
            element.classList.remove('default');
        }
    }
}

function selectedPulldown(event) {
    const element = event.target;
    let selectedOption = element.options[element.selectedIndex];

    if (selectedOption.dataset.other === 'isOther') {
        let newOption = window.prompt('Enter ');
        while (newOption === '') {
            newOption = window.prompt('Enter ');
        }

        if (newOption === null) {
            element.options[element.dataset.currentIndex].selected = true;
            return;
        }

        let i;
        for (i = 0; i < element.options.length; i++) {
            let option = element.options[i];
            if (option.value === newOption) {
                option.selected = true;
                selectedOption = option;
                break;
            }

            if (option === selectedOption) {
                const newElement = document.createElement('option');
                newElement.value = newOption;
                newElement.innerHTML = newOption;
                newElement.selected = true;
                selectedOption = element.insertBefore(newElement, selectedOption);
                break;
            }
        }
    }

    element.dataset.currentIndex = element.selectedIndex;
    if (selectedOption.value === '') {
        element.classList.add('default');
    } else {
        element.classList.remove('default');
    }
}

function fixedSizeToParent() {
    let i;
    let elements = document.getElementsByClassName('fixed-size-to-parent');
    for (i = 0; i < elements.length; i++) {
        let element = elements[i];
        let parent = element.parentNode;
        element.style.height = parent.offsetHeight + 'px';
        element.style.width = parent.offsetWidth + 'px';
    }
}

function setEventListener() {
    let i;
    let elements = document.querySelectorAll('input[name^=price\\[], input[name^=quantity\\[]');
    for (i = 0; i < elements.length; i++) {
        let element = elements[i];
        element.addEventListener('keyup', culculateSubTotals);
    }
}

function culculateSubTotals(event) {
    let i;
    let prices = document.querySelectorAll('input[name^=price\\[]');
    let subtotal = 0;
    let taxtotal = 0;

    const carryForward = document.querySelector('input[name=carry_forward]');
    if (carryForward) {
        subtotal += parseInt(carryForward.value);
        const displaySum = document.getElementById('sum-0');
        displaySum.innerHTML = (subtotal === 0) ? '' : formatter.format(subtotal);

        const carryForwardTax = document.querySelector('input[name=carry_forward_tax]');
        taxtotal += parseInt(carryForwardTax.value);
    }

    for (i = 0; i < prices.length; i++) {
        let price = parseInt(prices[i].value);
        let n = i + 1;
        let quantity = parseInt(document.querySelector('input[name=quantity\\[' + n + '\\]]').value);

        const displaySum = document.getElementById('sum-' + n);
        if (isNaN(price) || isNaN(quantity)) {
            displaySum.innerHTML = '';
            continue;
        }

        let sum = price * quantity;
        displaySum.innerHTML = (sum === 0) ? '' : formatter.format(sum);
        subtotal += sum;

        let inputReducedTaxRate = document.querySelector('input[name=reduced_tax_rate\\[' + n + '\\]]');
        let rate = (inputReducedTaxRate.checked) ? reducedTaxRate : taxRate;
        if (!isNaN(rate)) {
            taxtotal += sum * rate;
        }
    }
    displaySubtotal.innerHTML = (subtotal === 0) ? '' : formatter.format(subtotal);
    displayTax.innerHTML = (taxtotal === 0) ? '' : formatter.format(Math.ceil(taxtotal));

    culculateTotals(event);
}

function culculateTotals(event) {
    let subtotal = parseInt(displaySubtotal.innerHTML.replace(/,/g, ''));
    let taxtotal = parseInt(displayTax.innerHTML.replace(/,/g, ''));
    let additional_1_price = parseInt(inputAPrice.value.replace(/,/g, ''));
    let additional_2_price = parseInt(inputBPrice.value.replace(/,/g, ''));

    if (isNaN(subtotal)) subtotal = 0;
    if (isNaN(taxtotal)) taxtotal = 0;
    if (isNaN(additional_1_price)) additional_1_price = 0;
    if (isNaN(additional_2_price)) additional_2_price = 0;

    let total = subtotal + taxtotal + additional_1_price + additional_2_price;
    displayTotal.innerHTML = (total === 0) ? '' : formatter.format(total);
}

function autoFillClientData(event) {
    const anchor = event.currentTarget;
    const form = inputCompany.form;

    const nodeList = anchor.getElementsByClassName(suggestionDataClassName);

    for (let node of nodeList) {
        let key = node.id;
        if (form[key]) {
            form[key].value = node.innerHTML;
        }
    }

    const list = document.getElementById(suggestionListID);
    if (list) list.parentNode.removeChild(list);
}

function hideSuggestionList(event) {
    const element = event.target;
    if (element === inputCompany
        || element.childOf(suggestionListContainer) !== -1
    ) {
        return;
    }

    suggestionListLock = false;
    displaySuggestionList('');
}

function displaySuggestionList(source) {

    if (debugLevel > 0) {
        console.log(source);
        console.log(suggestionListLock);
    }

    if (!suggestionListContainer) return;

    let list = document.getElementById(suggestionListID);
    if (list && !suggestionListLock) {
        list.parentNode.removeChild(list);
        window.removeEventListener('mouseup', hideSuggestionList);
    }
    if (source === '') {
        return;
    }

    list = suggestionListContainer.appendChild(document.createElement('div'));
    list.id = suggestionListID;
    list.innerHTML = source;

    let i;
    let anchors = list.getElementsByTagName('a');
    for (i = 0; i < anchors.length; i++) {
        anchors[i].addEventListener('mousedown', switchSuggestionListLock);
        anchors[i].addEventListener('mouseup', switchSuggestionListLock);
        anchors[i].addEventListener('click', autoFillClientData);
    }

    window.addEventListener('mouseup', hideSuggestionList);
}

function suggestComplete(response) {
    response.json().then(function(data){

        if (debugLevel > 0) {
            console.log(data);
        }

        if (data.status === 0) {
            displaySuggestionList(data.source);
        }
    }).catch(error => console.error(error));
}

function suggestClient() {
    if (inputCompany.value === '') {
        displaySuggestionList('');
        return;
    }

    if (debugLevel > 0) {
        console.log(inputCompany.value);
    }

    const form = inputCompany.form;

    let data = new FormData();
    data.append('stub', form.stub.value);
    data.append('keyword', inputCompany.value);
    data.append('mode', 'srm.receipt.receive:suggest-client');

    suggestionBrowser.abort();
    const signal = suggestionBrowser.signal;

    fetch(form.action, {
        method: 'POST',
        credentials: 'same-origin',
        body: data,
    }).then(
        suggestComplete
    ).catch(error => console.error(error));
}

function switchComposing(event) {
    isComposing = (event.type === 'compostionstart');
}

function switchSuggestionListLock(event) {
    suggestionListLock = (event.type === 'mousedown');;
}

function listenerForSuggestion(event) {

    if (debugLevel > 0) {
        console.log(event.type);
    }

    let inputedValue = event.target.value;
    switch (event.type) {
        case 'blur':
            displaySuggestionList('');
            break;
        case 'keydown':
            valueAtKeyDown = inputedValue;
            break;
        case 'keyup':
            if (event.key === 'ArrowDown'
                || (!isComposing && valueAtKeyDown !== inputedValue)
            ) {
                clearTimeout(suggestionTimer);
                suggestionTimer = setTimeout(suggestClient, suggestionTimeout);
            }

            else {
                if (debugLevel > 0) {
                    console.log(event.key);
                }
            }

            valueAtKeyDown = undefined;
            break;
    }
}

function lockForm() {
    const form = inputCompany.form;
    if (form) {
        const isDraft = (form.draft) ? form.draft.value : '1';
        if (isDraft === '0') {
            let i;
            for (i = 0; i < form.elements.length; i++) {
                const element = form.elements[i];
                if (!element.classList.contains('never')) {
                    element.disabled = true;
                }
            }
        }
    }
}
