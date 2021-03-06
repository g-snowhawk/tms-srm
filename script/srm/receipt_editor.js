/**
 * This file is part of Tak-Me SalesReceiptManagement System.
 *
 * Copyright (c)2019 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

const debugLevel = 0;

const formatter = new Intl.NumberFormat('ja-JP');
const displaySubtotal = document.getElementById('subtotal');
const displayTotal = document.getElementById('total');
const displayTax = document.getElementById('tax');
const inputAPrice = document.querySelector('input[name=additional_1_price]');
const inputBPrice = document.querySelector('input[name=additional_2_price]');
const inputBillingDate = document.querySelector('input[name=billing_date]');
const inputCompany = document.querySelector('input[name=company]');
const buttonDelete = document.querySelector('input[name=s1_delete]');
const buttonAvailable = document.querySelector('a[class*=availables]');
const buttonAddPage = document.querySelector('input[name=s1_addpage]');
const buttonPreview = document.querySelector('input[name=s2_preview]');

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
let fetchCanceller = new AbortController();
let valueAtKeyDown = undefined;
let isComposing = false;
let isFetching = false;
let previewContainer = undefined;

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
    inputCompany.addEventListener('compositionstart', switchComposing);
    inputCompany.addEventListener('compositionend', switchComposing);
    inputCompany.addEventListener('focus', listenerForSuggestion);

    if (buttonDelete) {
        buttonDelete.addEventListener('click', confirmDeletion);
    }

    if (buttonAvailable) {
        buttonAvailable.addEventListener('click', available);
    }

    if (buttonAddPage) {
        buttonAddPage.addEventListener('click', cancelConfirm);
    }

    if (buttonPreview) {
        buttonPreview.addEventListener('click', previewReceipt);
    }

    const pageButtons = document.querySelectorAll('button.page-button');
    pageButtons.forEach(element => {
        element.addEventListener('click', cancelConfirm);
    });

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
    const bankId = document.getElementsByName('bank_id');
    for (let i = 0; i < bankId.length; i++) {
        const element = bankId[i];
        element.addEventListener('change', selectedPulldown);
        element.dataset.currentIndex = element.selectedIndex;

        const selectedOption = element.options[element.selectedIndex];
        if (selectedOption.value === '') {
            element.classList.add('default');
        } else {
            element.classList.remove('default');
        }
    }

    if (document.documentElement.classList.contains("unavailable-receipt")) {
        const receipt = document.querySelector('.receipt-form');
        if (receipt) {
            receipt.style.position = 'relative';
            const styles = window.getComputedStyle(receipt);
            const lMargin = parseInt(styles["margin-left"])
            const rMargin = parseInt(styles["margin-right"]);
            const tMargin = parseInt(styles["margin-top"]);
            const bMargin = parseInt(styles["margin-bottom"]);
            const width = receipt.clientWidth + lMargin + rMargin;
            const height = receipt.clientHeight + tMargin + bMargin;
            const svg = document.createElementNS('http://www.w3.org/2000/svg','svg');
            receipt.appendChild(svg);
            svg.style.zIndex = "10000";
            svg.style.position = "absolute";
            svg.style.top    = '0px';
            svg.style.right  = '0px';
            svg.style.bottom = '0px';
            svg.style.left   = '0px';
            svg.style.marginTop    = (tMargin * -1) + 'px';
            svg.style.marginRight  = (rMargin * -1) + 'px';
            svg.style.marginBottom = (bMargin * -1) + 'px';
            svg.style.marginLeft   = (lMargin * -1) + 'px';
            svg.setAttribute("viewBox", "0 0 " + width + " "  + height);

            const line1 = document.createElementNS('http://www.w3.org/2000/svg','line');
            line1.setAttribute("x1","0");
            line1.setAttribute("y1","0");
            line1.setAttribute("x2",svg.clientWidth);
            line1.setAttribute("y2",svg.clientHeight);
            line1.setAttribute("stroke","red");
            line1.setAttribute("stroke-width","2");
            svg.appendChild(line1);

            const line2 = document.createElementNS('http://www.w3.org/2000/svg','line');
            line2.setAttribute("x1",svg.clientWidth);
            line2.setAttribute("y1","0");
            line2.setAttribute("x2","0");
            line2.setAttribute("y2",svg.clientHeight);
            line2.setAttribute("stroke","red");
            line2.setAttribute("stroke-width","2");
            svg.appendChild(line2);
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
        const quantityElement = document.querySelector('input[name=quantity\\[' + n + '\\]]');
        if (!quantityElement) {
            continue;
        }
        let quantity = parseInt(quantityElement.value);

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

    for (const key in anchor.dataset) {
        if (form[key]) {
            const element = form[key];
            if (element.nodeName.toLowerCase() === 'select') {
                for (let i = 0; i < element.options.length; i++) {
                    const option = element.options[i];
                    if (option.dataset.other === 'isOther') {
                        continue;
                    }
                    option.selected = (option.value === anchor.dataset[key]);
                }
                if (element.selectedIndex && parseInt(element.dataset.currentIndex) !== element.selectedIndex) {
                    element.classList.remove('default');
                    element.dataset.currentIndex = element.selectedIndex;
                }
            } else {
                element.value = anchor.dataset[key];
            }
        }
    }

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

function displaySuggestionList(source, checkCurrent) {

    if (debugLevel > 0) {
        console.log(source);
        console.log(suggestionListLock);
    }

    if (!suggestionListContainer) return;

    let list = document.getElementById(suggestionListID);
    if (list && !suggestionListLock) {
        list.parentNode.removeChild(list);
        window.removeEventListener('mouseup', hideSuggestionList);
        window.removeEventListener('keydown', moveFocusSuggestionList);
    }
    if (source === '') {
        if (!checkCurrent) {
            return;
        }
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
    window.addEventListener('keydown', moveFocusSuggestionList);
}

function moveFocusSuggestionList(event) {
    if (event.key !== 'ArrowDown'
        && event.key !== 'ArrowUp'
        && event.key !== 'Tab'
        && event.key !== ' '
        && event.key !== 'Enter'
        && event.key !== 'Escape'
    ) {
        return;
    }

    const list = document.getElementById(suggestionListID);
    if (!list) {
        return;
    }
    const anchors = list.getElementsByTagName('a');

    let current = document.activeElement;
    if (!current.findParent('#' + suggestionListID)) {
        if (event.key !== 'Enter') {
            current = anchors[0];
            current.focus();
        }
        return;
    }

    event.preventDefault();
    switch (event.key) {
        case 'ArrowDown':
        case 'Tab':
        case ' ':
            for (let i = 0; i < anchors.length; i++) {
                if (anchors[i] === current) {
                    const next = anchors[(i+1)];
                    if (next) {
                        next.focus();
                        return;
                    }
                }
            }
            if (event.key !== 'ArrowDown') {
                anchors[0].focus();
            }
            break;
        case 'ArrowUp':
            for (let i = 0; i < anchors.length; i++) {
                if (anchors[i] === current) {
                    const next = anchors[(i-1)];
                    if (next) {
                        next.focus();
                        return;
                    }
                }
            }
            inputCompany.focus();
            break;
        case 'Enter':
            current.click();
            break;
        case 'Escape':
            displaySuggestionList('');
            inputCompany.focus();
            break;
    }
}

function suggestClient() {
    if (inputCompany.value === '') {
        displaySuggestionList('');
        return;
    }

    if (debugLevel > 0) {
        console.log(inputCompany.value);
    }

    if (document.getElementById(suggestionListID)) {
    }

    const form = inputCompany.form;

    let data = new FormData();
    data.append('stub', form.stub.value);
    data.append('keyword', inputCompany.value);
    data.append('mode', 'srm.receipt.receive:suggest-client');

    if (isFetching) {
        fetchCanceller.abort();
        isFetching = false;
    }

    isFetching = true;
    fetch(form.action, {
        signal: fetchCanceller.signal,
        method: 'POST',
        credentials: 'same-origin',
        body: data,
    }).then(response => {
        if (response.ok) {
            let contentType = response.headers.get("content-type");
            if (contentType.match(/^application\/json/)) {
                return response.json();
            }
            throw new Error('Unexpected response'.translate());
        } else {
            throw new Error('Server Error'.translate());
        }
    }).then(json => {
        if (json.status === 0) {
            displaySuggestionList(json.source);
        } else {
            throw new Error(json.message);
        }
    }).catch(error => {
        if (error.name === 'AbortError') {
            console.warn("Aborted!!");
            fetchCanceller = new AbortController()
        } else {
            console.error(error)
        }
    }).then(() => {
        isFetching = false;
    });
}

function switchComposing(event) {
    isComposing = false; /*(event.type !== 'compositionend');*/
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
            displaySuggestionList('', true);
            break;
        case 'keyup':
            if (event.key === 'ArrowDown'
                || event.key === inputedValue
                || (!isComposing && valueAtKeyDown !== inputedValue)
            ) {
                suggestClient();
            }
        case 'focus':
            valueAtKeyDown = inputedValue;
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

            if (inputBillingDate) {
                inputBillingDate.addEventListener('keydown', updateBillingDate);
            }
        }
    }
}

function confirmDeletion(event) {
    const element = event.target;
    if (element.dataset.confirm) {
        if (confirm(element.dataset.confirm)) {
            element.form.dataset.confirm = '';
        }
        else {
            event.preventDefault();
        }
    }

    const form = element.form;
    const elements = form.querySelectorAll("*[required]");
    elements.forEach(element => {
        element.removeAttribute('required');
    });
}

function available(event) {
    event.preventDefault();

    const form = inputCompany.form;
    const element = event.target;
    const params = [...new URLSearchParams(element.search).entries()].reduce((obj, e) => ({...obj, [e[0]]: e[1]}), {});

    let data = new FormData();
    data.append('stub', form.stub.value);
    data.append('returntype', 'json');
    data.append('mode', params.mode);
    data.append('id', params.id);

    if (params.mode.match(/:unavailable$/)) {
        const message = element.dataset.prompt ? element.dataset.prompt : "Input reason for available";
        const reason = prompt(message);
        if (reason == null) {
            return;
        }
        if (reason === '') {
            const message = element.dataset.alert ? element.dataset.alert : "Reason for available is required";
            alert(message);
            return;
        }
        data.append('reason', reason);
    }

    if (isFetching) {
        fetchCanceller.abort();
        isFetching = false;
    }

    isFetching = true;
    fetch(form.action, {
        signal: fetchCanceller.signal,
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: data,
    }).then(response => { 
        if (response.ok) {
            let contentType = response.headers.get("content-type");
            if (contentType.match(/^application\/json/)) {
                return response.json();
            }
            throw new Error('Unexpected response'.translate());
        } else {
            throw new Error('Server Error'.translate());
        }
    })
    .then(json => {
        if (json.status == 0) {
            switch (json.response.type) {
                case "redirect":
                    location.href = json.response.source;
                    break;
                default:
                    location.reload();
                    break;
            }
        }
        alert(json.message);
    }).catch(error => {
        if (error.name === 'AbortError') {
            console.warn("Aborted!!");
            fetchCanceller = new AbortController()
        } else {
            console.error(error)
        }
    }).then(() => {
        isFetching = false;
    });
}

function cancelConfirm(event) {
    const element = event.target;
    delete element.form.dataset.confirm;
}

function updateBillingDate(event) {
    const element = event.target;

    if (event.key === 'Enter') {
        if (element.value.match(/^[0-9]{4}[^0-9][0-9]{1,2}[^0-9][0-9]{1,2}[0-9]?$/)) {
            if (!confirm(element.dataset.confirm)) {
                return;
            }
            const form = element.form;
            const params = [...new URLSearchParams(window.location.search).entries()].reduce((obj, e) => ({...obj, [e[0]]: e[1]}), {});

            let data = new FormData();
            data.append('stub', form.stub.value);
            data.append('returntype', 'json');
            data.append('mode', 'srm.receipt.receive:update-billing-date');
            data.append('id', params.id);
            data.append('billing_date', element.value);

            if (isFetching) {
                fetchCanceller.abort();
                isFetching = false;
            }

            isFetching = true;
            fetch(form.action, {
                signal: fetchCanceller.signal,
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: data,
            }).then(response => { 
                if (response.ok) {
                    let contentType = response.headers.get("content-type");
                    if (contentType.match(/^application\/json/)) {
                        return response.json();
                    }
                    throw new Error('Unexpected response'.translate());
                } else {
                    throw new Error('Server Error'.translate());
                }
            })
            .then(json => {
                alert(json.message);
            }).catch(error => {
                if (error.name === 'AbortError') {
                    console.warn("Aborted!!");
                    fetchCanceller = new AbortController()
                } else {
                    console.error(error)
                }
            }).then(() => {
                isFetching = false;
            });
        } else if (element.value === '') {
            if (!confirm(element.dataset.clearConfirm)) {
                return;
            }
            const form = element.form;
            const params = [...new URLSearchParams(window.location.search).entries()].reduce((obj, e) => ({...obj, [e[0]]: e[1]}), {});

            let data = new FormData();
            data.append('stub', form.stub.value);
            data.append('returntype', 'json');
            data.append('mode', 'srm.receipt.receive:clear-billing-date');
            data.append('id', params.id);
            data.append('billing_date', element.value);

            if (isFetching) {
                fetchCanceller.abort();
                isFetching = false;
            }

            isFetching = true;
            fetch(form.action, {
                signal: fetchCanceller.signal,
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: data,
            }).then(response => { 
                if (response.ok) {
                    let contentType = response.headers.get("content-type");
                    if (contentType.match(/^application\/json/)) {
                        return response.json();
                    }
                    throw new Error('Unexpected response'.translate());
                } else {
                    throw new Error('Server Error'.translate());
                }
            })
            .then(json => {
                alert(json.message);
            }).catch(error => {
                if (error.name === 'AbortError') {
                    console.warn("Aborted!!");
                    fetchCanceller = new AbortController()
                } else {
                    console.error(error)
                }
            }).then(() => {
                isFetching = false;
            });
        }
    }
}

function previewReceipt(event) {
    const element = event.target;
    const form = element.form;

    let data = new FormData(form);
    data.set('mode', 'srm.receipt.receive:preview');

    if (isFetching) {
        fetchCanceller.abort();
        isFetching = false;
    }

    isFetching = true;
    fetch(form.action, {
        signal: fetchCanceller.signal,
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: data,
    }).then(response => { 
        if (response.ok) {
            const contentType = response.headers.get("content-type");
            switch(contentType.toLowerCase()) {
                case 'image/jpeg':
                case 'image/png':
                case 'application/pdf':
                    return response.blob();
                default:
                    console.log(response.text());
            }
        }
        throw new Error('Server Error'.translate());
    }).then(blob => {
        const tag = (blob.type === 'application/pdf') ? 'object' : 'img';
        const elm = previewContainer.querySelector(tag + '.preview-content');
        const atr = (elm.nodeName.toLowerCase() === 'object') ? 'data' : 'src';
        elm.onload = function() {
            previewContainer.classList.add('active');
            if (this.nodeName.toLowerCase() === 'img') {
                this.dataset.natualWidth = this.width;
                this.width = Math.round(this.width * 0.5);
                this.addEventListener('click', zoomPreviewImage);
            }
        }

        elm[atr] = URL.createObjectURL(blob);

        const className = tag + '-box';
        previewContainer.classList.add(className);
    }).catch(error => {
        if (error.name === 'AbortError') {
            console.warn("Aborted!!");
            fetchCanceller = new AbortController()
        } else {
            console.error(error)
        }
    }).then(() => {
        isFetching = false;
    });

    try {
        document.body.appendChild(document.getElementById('preview-block').content.cloneNode(true));
        previewContainer = document.getElementById('preview-container');
        previewContainer.querySelectorAll('.close-button').forEach(button => {
            button.addEventListener('click', endPreview);
        });
    } catch(e) {
        console.error(e);
    }
}

function endPreview(event) {
    event.preventDefault();
    previewContainer.parentNode.removeChild(previewContainer);
    previewContainer = undefined;
}

function zoomPreviewImage(event) {
    const element = event.target;
    element.classList.toggle('zoom-in');

    if (element.dataset.natualWidth === element.width.toString()) {
        element.width = Math.round(element.width * 0.5);
    } else {
        element.width = element.dataset.natualWidth;
    }
}
