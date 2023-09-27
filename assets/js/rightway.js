'use strict';
//DEBUG = true;

// Подключаем jQuery BlockUI плагин
//jQuery( document ).ready( function( $ ) {
( function( $ ) {
    $( function() {
    if( typeof $.blockUI === 'undefined' ) {
        $.getScript( 'https://cdnjs.cloudflare.com/ajax/libs/jquery.blockUI/2.70/jquery.blockUI.min.js' );
    }    

// Событие возникает, когда введены все цифры кода, и является сигналом для дальнейшей обработки данных
const eventcodeIsEntered = new Event( 'codeIsEntered', { bubbles: true } );
const eventcodeIsNotValid = new Event( 'codeIsNotValid', { bubbles: true } );

let confirmBox = '';

/**
 * Форма ввода кода подтверждения
 */
function showConfirmCodeForm1( contact = '', sendCodeFunction, phone = '' ) {
    if( !contact ) {
        const comminicationDataForm = document.querySelector( '#woocommerce-edit-communication' );
        opacity: 0.6
        if( comminicationDataForm.elements.allowEmail.checked ) contact = 'Email';
        if( comminicationDataForm.elements.allowSms.checked ) contact = 'телефон';
    }

    /* В блок <div data-v-28034098="" class="o-sms-auth-modal-phone-sent-notifier__text">
        <p data-v-28034098="" >Код выслан на <span class="contact-sent">+7 (999) 999 99 99</span></p>
        </div> внутрь .contact-sent вставить contact
    */
    const contactSent = document.querySelector( '.o-sms-auth-modal-phone-sent-notifier__text .contact-sent' );
    contactSent.innerHTML = contact+' '+phone;
    confirmBox = $.fancybox.open(
        [ {
            src: '#confirm-modal-template',
            type: 'inline',
        } ]
    );
    const sendAgain = document.querySelector( '#send-again' );
    const timerBlock = document.querySelector( '#timer-block' );
    let fields = document.querySelectorAll( '.confirm-code-input' ); //store all fields ref
    let fieldsArray = [ ...fields ];
    const confirmCodeForm = document.querySelector( 'form#enter-confirm-code' );
    fields.forEach( ( el, index ) => {
        el.addEventListener( 'keydown', function( e ) {
            el.value = '';
        } );
        //Проверка на полноту ввода кода подтверждения и триггер события окончания ввода кода
        el.addEventListener( 'input', function( e ) {
            // Если было выведено какое-то сообщение об ошибке, удаляем его
            let errblock = confirmCodeForm.querySelector( '.woocommerce-error' );
            if( errblock ) errblock.remove();
            el.value = el.value.replace( /[^0-9]+/, "" );
            confirmCodeForm.elements.confirm_code.value = confirmCodeForm.elements.codeNumber0.value + confirmCodeForm.elements.codeNumber1.value + confirmCodeForm.elements.codeNumber2.value + confirmCodeForm.elements.codeNumber3.value;
            if( fieldsArray.filter( function( field ) {
                    return !!field.value;
                } ).length == fieldsArray.length ) { //all fields have values.
                /* confirmCodeForm.submit(); */
                jQuery( '#enter-confirm-code' ).addClass( 'processing' ).block( {
                    message: null,
                    overlayCSS: {
                        background: '#fff',
                        opacity: 0.6
                    }
                } );
                /* el.dispatchEvent(eventcodeIsEntered); */
                let myEventcodeIsEntered = new Event( 'codeIsEntered', { bubbles: true } );
                myEventcodeIsEntered.mycode = 'abc';
                el.dispatchEvent( myEventcodeIsEntered );
            } else {
                //Автоматическое перемещение курсора в следующее поле после заполнения текущего
                var ml = ~~el.getAttribute( 'maxlength' );
                if( ml && el.value.length >= ml ) {
                    fields[ index + 1 ].focus();
                }
            }
        } );
    } )
    // Запусткаем таймер для повторной отправки кода
    let mins = 1;
    let secs = mins * 60;
    let currentSeconds = 0;
    let currentMinutes = 0;
    let timer = document.getElementById( 'timerText' );
    setTimeout( decrement, 1000 );

    function decrement() {
        currentMinutes = Math.floor( secs / 60 );
        currentSeconds = secs % 60;
        if( currentSeconds <= 9 ) currentSeconds = "0" + currentSeconds;
        secs--;
        timer = document.getElementById( 'timerText' );
        if( !timer ) { return; }
        timer.innerHTML = currentMinutes + ':' + currentSeconds;
        if( secs !== -1 ) {
            setTimeout( decrement, 1000 );
        } else {
            timerBlock.style.display = "none";
            sendAgain.style.display = "block";
        }
    }
    sendAgain.addEventListener( 'click', function() {
        confirmCodeForm.reset();
        // Если было выведено какое-то сообщение об ошибке, удаляем его
        let errblock = confirmCodeForm.querySelector( '.woocommerce-error' );
        if( errblock ) errblock.remove();
        let result = sendCodeFunction( phone );
        result.then( ( data ) => {
            if( data.success ) {
                timerBlock.style.display = "block";
                sendAgain.style.display = "none";
                // Обнуляем таймер перед повторным запуском
                mins = 1;
                secs = mins * 60;
                currentSeconds = 0;
                currentMinutes = 0;
                setTimeout( decrement, 1000 );
            }
        } );
    } );

}

const commSubmitBtn = document.querySelector( '#woocommerce-edit-communication input[type=submit]' );
const addressSubmitBtn = document.querySelector( '.woocommerce-edit-address form input[name=save_address]' );
const accountSubmitBtn = document.querySelector( '.woocommerce-EditAccountForm input[type=submit]' );
const registerSubmitBtn = document.querySelector( '.woocommerce-form-register button[type=submit]' );
const placeOrderBtn = document.querySelector( '.woocommerce-checkout input#place_order' );

/**
 * Отправка обновленных настроек коммуникации пользователя в RW при редактировании в ЛК сайта
 */
const changeRwCommData = function( data, confirm_code, cardId ) {
    return new Promise( function( resolve, reject ) {
        let submitConfirmBtn = document.querySelector( '#submit-confirm-code' );
        const comminicationDataForm = document.querySelector( '#woocommerce-edit-communication' );
        const confirmCodeForm = document.querySelector( 'form#enter-confirm-code' );
        // Отправляем запрос на изменение настроек коммуникации кастомера в RW, используя введенный им проверочный код
        data.append( 'action', 'rightway_edit_communication_data' );
        data.append( 'nonce_code', rightway.nonce );
        data.append( 'confirmCode', confirm_code );
        data.append( 'cardId', cardId );
        fetch( rightway.ajax_url, {
                method: "POST",
                body: data
            } )
            .then( ( response ) => response.json() )
            .then( ( data ) => {
                if( data.success ) {
                    // Если данные в RW благополучно изменены, отправляем запрос на изменение данных на сайте
                    resolve();
                } else {
                    /*                 confirmCodeForm.insertAdjacentHTML('beforeend','<p class="form-row form-row-wide woocommerce-error">'+data.data+'</p>');
                                    console.log( 'Confirmation code input error!' );
                                    const confirmCodeInput = document.querySelector('#confirm_code');
                                    confirmCodeInput.addEventListener( 'focus', () => document.querySelector('.woocommerce-error').remove(), true ); */
                    reject( data.data );
                }
            } )
            .catch( ( error ) => {
                /*             confirmCodeForm.insertAdjacentHTML('beforeend', '<p class="form-row form-row-wide woocommerce-error">Неверный код.</p>)');
                            const confirmCodeInput = document.querySelector('#confirm_code');
                            confirmCodeInput.addEventListener( 'focus', () => document.querySelector('.woocommerce-error').remove(), true ); */
                reject( error );
            } );
    } );
}

/**
 * Изменение контакта (email или телефона) кастомера  в RW
 */
const changeRwContactData = function( contact, value, contactId, token ) {
    return new Promise( function( resolve, reject ) {
        let submitConfirmBtn = document.querySelector( '#submit-confirm-code' );
        const addressDataForm = document.querySelector( 'form.cabinet_data' );
        // Изменяем значение контакта в RW
        let data1 = new FormData();
        data1.append( 'action', 'rightway_edit_contact_data' );
        data1.append( 'nonce_code', rightway.nonce );
        data1.append( 'customerId', rightway.customerId );
        data1.append( 'contactId', contactId );
        data1.append( 'token', token );
        if( contact === 'phone' ) {
            data1.append( 'billing_phone', formatPhone( value ) );
        }
        if( contact === 'email' ) {
            data1.append( 'email', value );
        }
        fetch( rightway.ajax_url, {
                method: "POST",
                body: data1
            } )
            .then( ( response ) => response.json() )
            .then( ( data ) => {
                if( data.success == false ) {
                    reject( data.data )

                } else {
                    resolve();
                }
            } )
            .catch( ( error ) => {
                reject( error );
            } );
    } );
}

/**
 * Получение введенного пользователем кода подтверждения (функция устарела!)
 */
const getConfirmCode = function() {
    return new Promise( function( resolve, reject ) {
        const confirmCodeForm = document.querySelector( 'form#enter-confirm-code' );
        confirmCodeForm.addEventListener( 'codeIsEntered', function( e ) {
            let isFormValid = confirmCodeForm.checkValidity();
            if( !isFormValid ) {
                confirmCodeForm.reportValidity();
                reject();
            } else {
                resolve( confirm_code );
            }
        } );
    } );
}

/**
 * Запрос в RW наполучение токена подтверждения контакта с использованием проверочного кода
 */
const getRwToken = function( contactValue, confirmCode ) {
    const confirmCodeForm = document.querySelector( 'form#enter-confirm-code' );
    return new Promise( function( resolve, reject ) {
        let data = new FormData();
        // Отправляем запрос в RW на получение токена подтверждения контакта, используя введенный проверочный код
        data.append( 'action', 'rightway_get_contact_token' );
        data.append( 'nonce_code', rightway.nonce );
        data.append( 'contactValue', contactValue );
        data.append( 'confirmCode', confirmCode );
        fetch( rightway.ajax_url, {
                method: "POST",
                body: data
            } )
            .then( ( response ) => response.json() )
            .then( ( data ) => {
                if( data.success == true ) {
                    resolve( data.data );
                }
                if( data.success == false ) {
                    /* jQuery('#enter-confirm-code').removeClass( 'processing' ).unblock();
                    confirmCodeForm.insertAdjacentHTML('beforeend', '<p class="form-row form-row-wide woocommerce-error">' + data.data + '. Попробуйте зайти позже.</p>'); */
                    reject( data.data );
                }
                /*             if (data) {
                                console.log(data.data);
                                resolve( data );
                            } else {
                                reject();
                            } */
            } )
            .catch( ( error ) => {
                reject( 'Не удалось связаться с сервером. Попробуйте зайти позже.' + error );
                /* confirmCodeForm.insertAdjacentHTML('beforeend', '<p class="form-row form-row-wide woocommerce-error">Неверный код.</p>');
                const confirmCodeInput = document.querySelector('#confirm_code');
                confirmCodeInput.addEventListener( 'focus', () => document.querySelector('.woocommerce-error').remove(), true ); */
            } );
    } );
};

/**
 * Создание пользователя в RW
 */
const createRwCustomer = function( contact, value, firstName, lastName, token, birthDate, gender ) {
    return new Promise( function( resolve, reject ) {
        let data1 = new FormData();
        data1.append( 'action', 'rightway_create_customer' );
        data1.append( 'nonce_code', rightway.nonce );
        data1.append( 'firstName', firstName );
        data1.append( 'lastName', lastName );
        data1.append( 'contact', contact );
        data1.append( 'value', value );
        data1.append( 'token', token );
        data1.append( 'birthDate', birthDate );
        data1.append( 'gender', gender );

        fetch( rightway.ajax_url, {
                method: "POST",
                body: data1
            } )
            .then( ( response ) => response.json() )
            .then( ( data ) => {
                if( data.success ) {
                    resolve( data );
                } else {
                    reject( data.data )
                }
            } )
            .catch( ( error ) => {
                /* document.querySelector( 'form.woocommerce-form-register').insertAdjacentHTML('beforeend', '<p class="form-row form-row-wide woocommerce-error">Ошибка изменения данных контакта.</p>');
                console.log( 'Customer create error.' ); */
                reject( error );
            } );
    } );
};

/**
 * Добавление контакта пользователю в RW
 */
const createRwContact = function( value, token ) {
    return new Promise( function( resolve, reject ) {
        let data1 = new FormData();
        data1.append( 'action', 'rightway_create_contact' );
        data1.append( 'nonce_code', rightway.nonce );
        data1.append( 'customerId', rightway.customerId );
        data1.append( 'value', value );
        data1.append( 'token', token );

        fetch( rightway.ajax_url, {
                method: "POST",
                body: data1
            } )
            .then( ( response ) => response.json() )
            .then( ( data ) => {
                if( data.success ) {
                    resolve( data );
                } else {
                    reject( data.data )
                }
            } )
            .catch( ( error ) => {
                /* document.querySelector( 'form.woocommerce-form-register').insertAdjacentHTML('beforeend', '<p class="form-row form-row-wide woocommerce-error">Ошибка изменения данных контакта.</p>');
                console.log( 'Customer create error.' ); */
                reject( error );
            } );
    } );
}

/**
 * Создание пользователя в RW (старая версия)
 */
const createRwContactData = function( contact, value, needToCreate ) {
    return new Promise( function( resolve, reject ) {
        let submitConfirmBtn = document.querySelector( '#submit-confirm-code' );
        const addressDataForm = document.querySelector( 'form.cabinet_data' );
        const confirmCodeForm = document.querySelector( 'form#enter-confirm-code' );
        confirmCodeForm.addEventListener( 'codeIsEntered', function( e ) {
            let isFormValid = confirmCodeForm.checkValidity();
            if( !isFormValid ) {
                confirmCodeForm.reportValidity();
            } else {
                let confirm_code = confirmCodeForm.elements.confirm_code.value;
                /* console.log('confirm_code=' + confirm_code  ); */

                let data = new FormData();
                // Отправляем запрос в RW на получение токена подтверждения контакта, используя введенный проверочный код
                data.append( 'action', 'rightway_get_contact_token' );
                data.append( 'nonce_code', rightway.nonce );
                data.append( 'contactValue', value );
                data.append( 'confirmCode', confirm_code );
                fetch( rightway.ajax_url, {
                        method: "POST",
                        body: data
                    } )
                    .then( ( response ) => response.json() )
                    .then( ( data ) => {
                        if( data.success ) {
                            // Если токен благополучно получен, создаем пользователя в RW, если это нужно
                            if( needToCreate ) {
                                let data1 = new FormData();
                                data1.append( 'action', 'rightway_create_customer' );
                                data1.append( 'nonce_code', rightway.nonce );
                                if( contact === 'phone' ) {
                                    data1.append( 'billing_phone', formatPhone( value ) );
                                }
                                if( contact === 'email' ) {
                                    data1.append( 'email', value );
                                }
                                data1.append( 'token', data.data )

                                fetch( rightway.ajax_url, {
                                        method: "POST",
                                        body: data1
                                    } )
                                    .then( ( response ) => response.json() )
                                    .then( ( data ) => {
                                        resolve( data );
                                    } )
                                    .catch( ( error ) => {
                                        document.querySelector( 'form.woocommerce-form-register' ).insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide woocommerce-error">Ошибка изменения данных контакта.</p>' );
                                        return;
                                    } );
                            }
                        } else {
                            confirmCodeForm.insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide woocommerce-error">' + data.data + '</p>' );
                            const confirmCodeInput = document.querySelector( '#confirm_code' );
                            confirmCodeInput.addEventListener( 'focus', () => document.querySelector( '.woocommerce-error' ).remove(), true );
                        }
                    } )
                    .catch( ( error ) => {
                        confirmCodeForm.insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide woocommerce-error">Неверный код.</p>' );
                        const confirmCodeInput = document.querySelector( '#confirm_code' );
                        confirmCodeInput.addEventListener( 'focus', () => document.querySelector( '.woocommerce-error' ).remove(), true );
                    } );
            }
            resolve( { 'success': true } );
        } );
    } );
}

/**
 * Полчение данных карт пользователя в RW
 */
const getRwCustomerCards = function( customerId ) {
    return new Promise( function( resolve, reject ) {
        // Отправляем запрос на изменение данных кастомера в RW
        let data = new FormData();
        data.append( 'action', 'rightway_get_customer_cards' );
        data.append( 'customerId', customerId );
        fetch( rightway.ajax_url, {
                method: "POST",
                body: data
            } )
            .then( ( response ) => response.json() )
            .then( ( data ) => {
                if( data.success ) {
                    resolve( data );
                } else {
                    reject( data.data );
                }
            } )
            .catch( ( error ) => {
                reject( error );
            } );
    } );
}

/**
 * Отправка обновленных анкетных данных пользователя в RW при редактировании в ЛК сайта
 */
const changeRwPersData = function( customerId ) {
    return new Promise( function( resolve, reject ) {
        const submitConfirmBtn = document.querySelector( '#submit-confirm-code' );
        const addressDataForm = document.querySelector( 'form.cabinet_data' );
        // Отправляем запрос на изменение данных кастомера в RW
        let data = new FormData();
        data.append( 'action', 'rightway_edit_customer_data' );
        data.append( 'billing_first_name', addressDataForm.elements.billing_first_name.value );
        data.append( 'billing_last_name', addressDataForm.elements.billing_last_name.value );
        data.append( 'birthDate', addressDataForm.elements.birthDate.value );
        data.append( 'gender', addressDataForm.elements.gender.value );
        data.append( 'customerId', customerId );
        fetch( rightway.ajax_url, {
                method: "POST",
                body: data
            } )
            .then( ( response ) => response.json() )
            .then( ( data ) => {
                if( data.success ) {
                    resolve( data );
                } else {
                    reject( data.data );
                }
            } )
            .catch( ( error ) => {
                reject( error );
            } );
    } );
}

/**
 * Получение объекта с информацией по бонусной карте
 */
function getCardSummary() {
    let data = new FormData();
    data.append( 'action', 'rightway_get_card_summary' );
    data.append( 'nonce_code', rightway.nonce );
    let response = fetch( rightway.ajax_url, {
        method: "POST",
        body: data
    } );
    return response.then( ( response ) => response.json() );
}

/**
 * Получение объекта с информацией по бонусной карте
 */
function getCustomerContacts() {
    let data = new FormData();
    data.append( 'action', 'rightway_get_customer_contacts' );
    data.append( 'nonce_code', rightway.nonce );
    data.append( 'customerId', rightway.customerId );
    let response = fetch( rightway.ajax_url, {
        method: "POST",
        body: data
    } );
    return response.then( ( response ) => response.json() );
}

/**
 * Получение количества покупателей с указанным 
 */
function getCustomersQuantity( contact, value ) {
    let data = new FormData();
    data.append( 'action', 'rightway_get_customers_quantity' );
    data.append( 'nonce_code', rightway.nonce );
    data.append( contact, value );
    let response = fetch( rightway.ajax_url, {
        method: "POST",
        body: data
    } );
    return response.then( ( response ) => response.json() );
}

/**
 * Получение покупателей с указанным значением контакта
 */
function getCustomersContacts( contact, value, rwToken ) {
    let data = new FormData();
    data.append( 'action', 'rightway_get_customers' );
    data.append( 'nonce_code', rightway.nonce );
    data.append( contact, value );
    data.append( 'rwToken', rwToken );
    let response = fetch( rightway.ajax_url, {
        method: "POST",
        body: data
    } );
    return response.then( ( response ) => response.json() );
}

/**
 * Отправка кода подтвержения для редактирования анкетных данных или согласий по коммуникации
 */
function sendCode( contactId ) {
    let data = new FormData();
    data.append( 'action', 'rightway_send_confirm_code' );
    data.append( 'nonce_code', rightway.nonce );
    data.append( 'contactId', contactId );

    let response = fetch( rightway.ajax_url, {
        method: "POST",
        body: data
    } );
    return response.then( ( response ) => response.json() );
}

/**
 * Отправка кода подтвержения для редактирования анкетных данных или согласий по коммуникации
 */
function sendContactCode( contactValue ) {
    let data = new FormData();

    data.append( 'action', 'rightway_send_confirm_contact_code' );
    data.append( 'nonce_code', rightway.nonce );
    data.append( 'contactValue', contactValue );

    let response = fetch( rightway.ajax_url, {
        method: "POST",
        body: data
    } );
    return response.then( ( response ) => response.json() );
}

// Запрос кода подтверждения при сохранении настроек коммуникации пользователя в ЛК сайта
if( commSubmitBtn ) {
    commSubmitBtn.addEventListener( 'click', function( e ) {
        e.preventDefault();
        let cardSummary = '';
        // Получаем настройки карты от RW
        getCardSummary()
            .then( ( data ) => {
                if( data.success ) {
                    cardSummary = JSON.parse( data.data );
                    if( !rightway.contactId ) {
                        cardSummary.contacts.forEach( ( contact ) => {
                            if( contact.value.indexOf( '+' ) >= 0 && contact.confirmed ) {
                                rightway.contactId = contact.id;
                            }
                        } );
                    }
                    // Получаем настройки коммуникации из формы в ЛК
                    const comminicationDataForm = document.querySelector( '#woocommerce-edit-communication' );
                    let data1 = new FormData();
                    data1.append( 'allowSms', comminicationDataForm.elements.allowSms.checked );
                    data1.append( 'allowEmail', comminicationDataForm.elements.allowEmail.checked );
                    data1.append( 'allowMarketingCommunication', comminicationDataForm.elements.allowMarketingCommunication.checked );
                    // Сравнение данных RW с данными в форме в ЛК
                    if( data1.allowSms != cardSummary.communicationSettings.allowSms.toString() ||
                        data1.allowEmail != cardSummary.communicationSettings.allowEmail.toString() ||
                        data1.allowMarketingCommunication != cardSummary.communicationSettings.allowMarketingCommunication.toString() ) {
                        // Если данные отличаются, отправляем код подтверждения и изменяем данные в RW
                        let result = sendCode( rightway.contactId );
                        result.then( ( data ) => {
                            if( data.success ) {
                                showConfirmCodeForm1( '', sendCode );
                                const confirmCodeForm = document.querySelector( 'form#enter-confirm-code' );
                                // Получаем код подтверждения из формы
                                confirmCodeForm.addEventListener( 'codeIsEntered', function( e ) {
                                    let isFormValid = confirmCodeForm.checkValidity();
                                    if( !isFormValid ) {
                                        confirmCodeForm.reportValidity();
                    } else {
                        let confirm_code = confirmCodeForm.elements.confirm_code.value;
                        changeRwCommData( data1, confirm_code, cardSummary.id )
                                            .then( ( result ) => {
                                                jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                                confirmBox.close();
                                                comminicationDataForm.submit();
                                            } )
                                            .catch( err => {
                                                jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                                confirmBox.close();
                                                comminicationDataForm.insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide">' + err + '</p>' );
                                            } );
                                    }
                                } );
                            } else {
                                comminicationDataForm.insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide">' + data.data + '</p>' );
                            }
                        } );
                    }
                } else {
                    document.querySelector( '#woocommerce-edit-communication' ).insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide">' + data.data + '</p>' );
                }
            } )
            .catch( err => {
                document.querySelector( '#woocommerce-edit-communication' ).insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide">' + err + '</p>' );
            } );

    } );
}

// Обработка контакта нового пользователя при регистрации на сайте (ОТКЛЮЧЕНО)
if( registerSubmitBtn == false ) { // Блок временно отключен

    const registerDataForm = document.querySelector( '.woocommerce-form-register' );
    const EmailField = document.querySelector( '#reg_email' ).parentElement;
    const email = document.querySelector( '#reg_email' );
    const errMessage = document.querySelector( '.err-message' );

    EmailField.addEventListener( 'keydown', function( event ) {
        const errMessage = document.querySelector( '.err-message' );
        if( errMessage ) {
            EmailField.classList.remove( 'woocommerce-invalid' );
            errMessage.parentNode.removeChild( errMessage );
        }
    } );

    function registerSubmitBtnClick( e ) {
        e.preventDefault();
        getCustomersQuantity( 'email', email.value )
            .then( ( data ) => {
                if( data.success ) {
                    // Если в RW найдено больше одного такого Email, не позволяем пользователяю регистрироваться с таким Email
                    if( data.data > 1 ) {
                        if( !errMessage ) {
                            EmailField.insertAdjacentHTML( 'beforeend', '<span class="form-row form-row-wide err-message">' + email.value + ' занят. Укажите, пожалуйста, другой.</span>' );
                            EmailField.classList.add( 'woocommerce-invalid' );
                            email.scrollIntoView( { block: "center", inline: "center" } );
                            return;
                        }
                    }
                    // Если таких Email в RW нет, убеждаемся, что пользователь имеет к нему доступ, и создаем нового пользоватея в RW
                    if( data.data == 0 ) {
                        // Если Email уникальный, отправляем код подтвержения на новый Email
                        let result = sendContactCode( email.value );
                        result.then( ( data ) => {
                                if( data.success ) {
                                    // Если код отправлен, получаем его от пользователя и пытаемся создать нового покупателя в RW
                                    // Показываем форму ввода кода подтверждения
                                    showConfirmCodeForm1( 'Email', sendContactCode, email.value );
                                    const confirmCodeForm = document.querySelector( 'form#enter-confirm-code' );
                                    // Получаем код подтверждения из формы
                                    confirmCodeForm.addEventListener( 'codeIsEntered', function( e ) {
                                        let isFormValid = confirmCodeForm.checkValidity();
                                        if( !isFormValid ) {
                                            confirmCodeForm.reportValidity();
                                        } else {
                                            let confirm_code = confirmCodeForm.elements.confirm_code.value;
                                            // Проверяем правильность кода - пытаемся получить по нему токен
                                            getRwToken( email.value, confirm_code )
                                                .then( ( result ) => {
                                                    // Создаем пользователя в RW, используя токен
                                                    return createRwCustomer( 'email', email.value, result, '', '' );
                                                } )
                                                .then( ( result ) => {
                                                    jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                                    registerSubmitBtn.removeEventListener( 'click', registerSubmitBtnClick );
                                                    registerSubmitBtn.click();
                                                } )
                                                .catch( err => {
                                                    let errText = '';
                                                    jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                                    if( typeof err == Object ) {
                                                        if( err.hasOwnProperty( 'data' ) ) {
                                                            errText = err.data;
                                                        }
                                                        if( err.hasOwnProperty( 'error' ) ) {
                                                            errText = err.error;
                                                        }
                                                    } else {
                                                        errText = err;
                                                        document.querySelector( '#enter-confirm-code' ).insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide woocommerce-error">' + errText + '</p>' );
                                                    }
                                                    return;
                                                } );
                                        }
                                    } );

                                } else {
                                    registerDataForm.insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide">' + data.data + '</p>' );
                                }
                            } )
                            .catch( err => {
                            } );
                    }

                    if( data.data == 1 ) {
                        // Если Email в RW есть и он уникальный, отправляем код подтвержения на новый Email
                        let result = sendContactCode( email.value );
                        result.then( ( data ) => {
                                if( data.success ) {
                                    // Если код отправлен, получаем его от пользователя и пытаемся создать нового покупателя в RW
                                    // Показываем форму ввода кода подтверждения
                                    showConfirmCodeForm1( 'Email', sendContactCode, email.value );
                                    const confirmCodeForm = document.querySelector( 'form#enter-confirm-code' );
                                    // Получаем код подтверждения из формы, когда он полностью введен
                                    confirmCodeForm.addEventListener( 'codeIsEntered', function( e ) {
                                        let isFormValid = confirmCodeForm.checkValidity();
                                        if( !isFormValid ) {
                                            confirmCodeForm.reportValidity();
                                        } else {
                                            let confirm_code = confirmCodeForm.elements.confirm_code.value;
                                            // Проверяем правильность кода - пытаемся получить по нему токен
                                            getRwToken( email.value, confirm_code )
                                                .then( ( result ) => {
                                                    jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                                    // Если токен получен успешно, создаем пользователя с указанным Email в БД сайта
                                                    if( result.success == true ) {
                                                        registerSubmitBtn.removeEventListener( 'click', registerSubmitBtnClick );
                                                        registerSubmitBtn.click();
                                                    } else {
                                                        jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                                        document.querySelector( '#enter-confirm-code' ).insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide woocommerce-error">Введен неверный код.</p>' );
                                                    }
                                                } )
                                                .catch( err => {
                                                    let errText = '';
                                                    jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                                    if( typeof err == 'object' ) {
                                                        if( err.hasOwnProperty( 'data' ) ) {
                                                            errText = err.data;
                                                        }
                                                        if( err.hasOwnProperty( 'error' ) ) {
                                                            errText = err.error;
                                                        }
                                                    } else {
                                                        errText = err;
                                                    }
                                                    document.querySelector( '#enter-confirm-code' ).insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide woocommerce-error">' + errText + '</p>' );
                                                } )
                                        }
                                    } );
                                } else {
                                    registerDataForm.insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide">' + data.data + '</p>' );
                                }
                            } )
                            .catch( err => {
                            } );
                    }

                } else {
                    registerDataForm.insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide">' + data.data + '</p>' );
                }
            } )
            .catch( err => {
            } );

    }
    registerSubmitBtn.addEventListener( 'click', registerSubmitBtnClick );
}

// Сохранении контактных данных (Email) пользователя в RW и на сайте
if( accountSubmitBtn ) {

    const accountDataForm = document.querySelector( '.woocommerce-EditAccountForm' );
    const email = document.querySelector( '#account_email' );
    const EmailField = document.querySelector( '#account_email' ).parentElement;
    const errMessage = document.querySelector( '.err-message' );
    let contactsArray;

    EmailField.addEventListener( 'keydown', function( event ) {
        const errMessage = document.querySelector( '.err-message' );
        if( errMessage ) {
            EmailField.classList.remove( 'woocommerce-invalid' );
            errMessage.parentNode.removeChild( errMessage );
        }
    } );
    accountSubmitBtn.addEventListener( 'click', function( e ) {
        let rwToken, contactId = '';
        e.preventDefault();

        // Получаем customerId, сохраненный на сайте для текущего пользователя
        if( rightway.customerId ) {
            // Проверяем, есть ли у данного кастомера в RW контакт с email
            // по запросу GET /customers/{customerId}/contacts
            getCustomerContacts()
                .then( ( data ) => {
                    if( data.data ) {
                        let noRwUpdate = false;
                        /* contactsArray = JSON.parse(data.data); */
                        contactsArray = data.data;
                        contactsArray.forEach( ( contact ) => {
                            if( contact.value.indexOf( '@' ) >= 0 ) {
                                // Если контакт с email есть у данного покупателя в RW и email в RW совпадает с email на сайте,
                                // просто отправляем форму на сайте для обновления учетки
                                if( contact.value === email.value ) {
                                    noRwUpdate = true;
                                }
                                contactId = contact.id;
                                // Если контакт с email есть у данного покупателя в RW и email в RW не совпадает с email на сайте,
                                // проверяем и обновляем контакт в RW
                            }
                        } );
                        if( noRwUpdate ) {
                            accountDataForm.submit();
                            return;
                        }
                        sendContactCode( email.value )
                            .then( ( data ) => {
                                if( data.success ) {
                                    showConfirmCodeForm1( 'email', sendContactCode, email.value );
                                    const confirmCodeForm = document.querySelector( 'form#enter-confirm-code' );
                                    // Получаем код подтверждения из формы
                                    confirmCodeForm.addEventListener( 'codeIsEntered', function( e ) {
                                        let isFormValid = confirmCodeForm.checkValidity();
                                        if( !isFormValid ) {
                                            confirmCodeForm.reportValidity();
                                        } else {
                                            // Проверяем правильность кода - пытаемся получить по нему токен
                                            let confirm_code = confirmCodeForm.elements.confirm_code.value;
                                            getRwToken( email.value, confirm_code )
                                                .then( ( result ) => {
                                                    rwToken = result;
                                                    // Запрашиваем количество контактов с таким email
                                                    return getCustomersContacts( 'email', email.value );
                                                } )
                                                .then( ( data ) => {
                                                    if( data.success ) {
                                                        // Если новый номер не уникальный
                                                        if( data.data.length > 1 && !errMessage ) {
                                                            jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                                            confirmBox.close();
                                                            EmailField.insertAdjacentHTML( 'beforeend', '<span class="form-row form-row-wide err-message">Номер ' + phone + ' занят. Укажите, пожалуйста, другой.</span>' );
                                                            EmailField.classList.add( 'woocommerce-invalid' );
                                                            return;
                                                        }
                                                        if( data.data.length <= 1 ) {
                                                            jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                                            confirmBox.close();
                                                            if( contactId ) {
                                                                return changeRwContactData( 'email', email.value, contactId );
                                                            } else {
                                                                return createRwContact( email.value, rwToken );
                                                            }
                                                        }
                                                    }
                                                } )
                                                .then( ( result ) => {
                                                    // Сохраняем изменения в БД сайта                                
                                                    accountDataForm.submit();
                                                } )
                                                .catch( err => {
                                                    jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                                    confirmCodeForm.insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide">' + err + '</p>' );
                                                } );
                                        }
                                    } );
                                } else {
                                    jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                    accountDataForm.insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide">' + data.data + '</p>' );
                                }
                            } )
                            .catch( err => {
                                jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                accountDataForm.insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide">' + err + '</p>' );
                            } );

                    }

                } )
                .catch( err => {
                    accountDataForm.insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide">' + err + '</p>' );
                } );
            // Сверяем анкетные данные в форме на сайте с анкетными данными в RW
            // по запросу GET /customers/search/phone=номер_телефона
        } else {
            // Ничего не далаем, так как первоначальную регистрацию в RW надо делать на странице с телефонным номером
        }
    } );
}

// Сохранении контактных данных (телефона) пользователя в RW и запрос кода подтверждения
// для сохранения анкетных данных в RW при редактировании данных в ЛК сайта
if( addressSubmitBtn ) {

    const addressDataForm = document.querySelector( 'form.cabinet_data' );
    const billingPhoneField = document.querySelector( '#billing_phone_field' );
    const billingPhone = document.getElementById( 'billing_phone' );
    const firstName = document.querySelector( '#billing_first_name' );
    const lastName = document.querySelector( '#billing_last_name' );
    const birthDate = document.querySelector( '#birthDate' );
    const gender = document.querySelector( '#gender' );
    const errMessage = document.querySelector( '.err-message' );

    billingPhone.addEventListener( 'keydown', function( event ) {
        const errMessage = document.querySelector( '.err-message' );
        if( errMessage ) {
            billingPhoneField.classList.remove( 'woocommerce-invalid' );
            errMessage.parentNode.removeChild( errMessage );
        }
    } );
    addressSubmitBtn.addEventListener( 'click', function( e ) {
        jQuery( '.woocommerce-address-fields' ).addClass( 'processing' ).block( {
            message: null,
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            }
        } );
        e.preventDefault();
        let rwToken = '',
            contactId = '';
        let customersInfo = [],
            contactsArray = [];
        //let phone = addressDataForm.elements.billing_phone.value.replace( new RegExp( /[-() /\\]/g ), '' );
        let phone = formatPhone( addressDataForm.elements.billing_phone.value );
        // Получаем customerId, сохраненный на сайте для текущего пользователя
        if( rightway.customerId ) {
            getCustomerContacts( phone )
                .then( ( data ) => {
                    if( data.success ) {
                        data.data.forEach( ( contact ) => {
                            if( contact.value.indexOf( '+' ) >= 0 ) {
                                contactId = contact.id;
                            }
                        } );
                    }
                } )
                .then( ( data ) => {
                    // Убеждаемся, что в RW нет других участников ПЛ с указанным контактом
                    // с помощью запроса GET /customers/search/phone=номер_телефона                    
                    return getCustomersContacts( 'phone', phone );
                } )
                .then( ( data ) => {
                    if( data.success ) {
                        // Если такого телефона в RW нет, посылаем код подтверждения на телефон
                        if( data.data.length == 0 ) {
                            return sendContactCode( phone );
                        }
                        // Если такой телефон в RW есть, и он совпадает с телефоном в профиле на сайте, значит,
                        // телефон в RW обновлять не нужно. Обновляем только остальные анкетные данные в RW и на сайте.
                        if( data.data.length == 1 && data.data[ 0 ].id == rightway.customerId ) {
                            changeRwPersData( rightway.customerId )
                                .then( ( data ) => {
                                    jQuery( '.woocommerce-address-fields' ).removeClass( 'processing' ).unblock();
                                    addressDataForm.submit();
                                } );
                        } else {
                            reject();
                        }
                    } else {
                        reject();
                    }
                } )
                .then( ( data ) => {
                    if( !data ) {
                        return;
                    }
                    if( data.success ) {
                        showConfirmCodeForm1( 'телефон', sendContactCode, phone );
                        const confirmCodeForm = document.querySelector( 'form#enter-confirm-code' );
                        // Получаем код подтверждения из формы
                        confirmCodeForm.addEventListener( 'codeIsEntered', function( e ) {
                            let isFormValid = confirmCodeForm.checkValidity();
                            if( !isFormValid ) {
                                confirmCodeForm.reportValidity();
                            } else {
                                // Проверяем правильность кода - пытаемся получить по нему токен
                                let confirm_code = confirmCodeForm.elements.confirm_code.value;
                                getRwToken( phone, confirm_code )
                                    .then( ( result ) => {
                                        rwToken = result;
                                        return changeRwContactData( 'phone', phone, contactId, rwToken );
                                    } )
                                    .then( ( result ) => {
                                        jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                        if( confirmBox ) {
                                            confirmBox.close();
                                        }
                                        /*                              // Если контакт не удалось успешно изменить в RW
                                                                        if (result.success == false) {
                                                                            addressDataForm.insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide">'+result.data+'</p>');
                                                                        } */
                                        // Редактируем анкетные данные в RW в соответствии с указанными на сайте
                                        return changeRwPersData( rightway.customerId );
                                    } )
                                    .then( ( result ) => {
                                        // Если персональные данные не удалось успешно изменить в RW
                                        /*                              if (result.success == false) {
                                                                            addressDataForm.insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide">'+result.data+'</p>');
                                                                        }  */
                                        // Сохраняем изменения в БД сайта  
                                        jQuery( '.woocommerce-address-fields' ).removeClass( 'processing' ).unblock();
                                        addressDataForm.submit();
                                    } )
                                    .catch( err => {
                                        jQuery( '.woocommerce-address-fields' ).removeClass( 'processing' ).unblock();
                                        confirmCodeForm.insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide">' + err + '</p>' );
                                    } );
                            }
                        } );
                    } else {
                        jQuery( '.woocommerce-address-fields' ).removeClass( 'processing' ).unblock();
                        addressDataForm.insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide">' + data.data + '</p>' );
                    }
                } )
                .catch( err => {
                    jQuery( '.woocommerce-address-fields' ).removeClass( 'processing' ).unblock();
                    addressDataForm.insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide">' + err + '</p>' );
                } );


        } else {
            sendContactCode( phone )
                .then( ( data ) => {
                    if( data.success ) {
                        showConfirmCodeForm1( 'телефон', sendContactCode, phone );
                        const confirmCodeForm = document.querySelector( 'form#enter-confirm-code' );
                        // Получаем код подтверждения из формы
                        confirmCodeForm.addEventListener( 'codeIsEntered', function( e ) {
                            let isFormValid = confirmCodeForm.checkValidity();
                            if( !isFormValid ) {
                                confirmCodeForm.reportValidity();
                            } else {
                                // Проверяем правильность кода - пытаемся получить по нему токен
                                let confirm_code = confirmCodeForm.elements.confirm_code.value;
                                getRwToken( phone, confirm_code )
                                    .then( ( result ) => {
                                        rwToken = result;
                                        return getCustomersContacts( 'phone', phone, result );
                                    } )
                                    .then( ( data ) => {
                                        if( data.success ) {
                                            // Если новый номер не уникальный
                                            /* data.data.length=0// ВРЕМЕННО ДЛЯ ТЕСТА */

                                            if( data.data.length > 1 && !errMessage ) {
                                                jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                                confirmBox.close();
                                                billingPhoneField.insertAdjacentHTML( 'beforeend', '<span class="form-row form-row-wide err-message">Номер ' + phone + ' занят. Укажите, пожалуйста, другой.</span>' );
                                                billingPhoneField.classList.add( 'woocommerce-invalid' );
                                                jQuery( '.woocommerce-address-fields' ).removeClass( 'processing' ).unblock();
                                                return;
                                            }
                                            if( data.data.length == 0 ) {
                                                // Создаем контакт в RW
                                                return createRwCustomer( 'phone', phone, firstName.value, lastName.value, rwToken, birthDate.value, gender.value );
                                            }
                                            if( data.data.length == 1 ) {
                                                // Сохраняем анкетные данные, найденные для данного телефона в RW для последующей обработки
                                                customersInfo = data.data[ 0 ];

                                                // Редактируем анкетные данные в RW в соответствии с указанными на сайте
                                                return changeRwPersData( customersInfo.id );
                                            }
                                        }
                                    } )
                                    .then( ( result ) => {
                                        // Если в предыдущем действии создавали пользователя и карту в RW
                                        if( !customersInfo || customersInfo.length == 0 ) {
                                            let data = JSON.parse( result.data );
                                            // Добавляем в форму поля для привязки кастомера RW к покупателю на сайте                                
                                            if( data.id ) {
                                                addressDataForm.insertAdjacentHTML( 'beforeend', '<input type="hidden" name="cardId" value="' + data.id + '"/>' );
                                            }
                                            if( data.number ) {
                                                addressDataForm.insertAdjacentHTML( 'beforeend', '<input type="hidden" name="cardNumber" value="' + data.number + '"/>' );
                                            }
                                            if( data.customerId ) {
                                                addressDataForm.insertAdjacentHTML( 'beforeend', '<input type="hidden" name="customerId" value="' + data.customerId + '"/>' );
                                            }
                                            jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                            confirmBox.close();
                                            jQuery( '.woocommerce-address-fields' ).removeClass( 'processing' ).unblock();
                                            addressDataForm.submit();
                                        } else {
                                            // Если в предыдущем действии читали и обновляли данные пользователя и карты в RW
                                            if( customersInfo.id ) {
                                                // Получаем информацию о картах данного покупателя
                                                getRwCustomerCards( customersInfo.id )
                                                    .then( ( result ) => {
                                                        let cardsArray = [];
                                                        let cardId = '',
                                                            cardNumber = '';
                                                        cardsArray = JSON.parse( result.data );
                                                        // Выбираем идентификатор и номер карты для будущего сохранения в профиле пользователя на сайте
                                                        for( let key in cardsArray ) {;
                                                            if( !cardsArray[ key ].isBlocked ) {
                                                                cardId = cardsArray[ key ].id;
                                                                cardNumber = cardsArray[ key ].number;
                                                            }
                                                        }
                                                        if( cardId ) {
                                                            addressDataForm.insertAdjacentHTML( 'beforeend', '<input type="hidden" name="cardId" value="' + cardId + '"/>' );
                                                            addressDataForm.insertAdjacentHTML( 'beforeend', '<input type="hidden" name="cardNumber" value="' + cardNumber + '"/>' );
                                                        }
                                                        addressDataForm.insertAdjacentHTML( 'beforeend', '<input type="hidden" name="customerId" value="' + customersInfo.id + '"/>' );
                                                        jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                                        confirmBox.close();
                                                        jQuery( '.woocommerce-address-fields' ).removeClass( 'processing' ).unblock();
                                                        addressDataForm.submit();
                                                    } );
                                            }
                                        }
                                    } )
                                    .catch( err => {
                                        jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                        confirmCodeForm.insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide">' + err + '</p>' );
                                    } );
                            }
                        } );
                    } else {
                        jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                        jQuery( '.woocommerce-address-fields' ).removeClass( 'processing' ).unblock();
                        addressDataForm.insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide">' + data.data + '</p>' );
                    }
                } )
                .catch( err => {
                    jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                    jQuery( '.woocommerce-address-fields' ).removeClass( 'processing' ).unblock();
                    addressDataForm.insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide">' + err + '</p>' );
                } );
        }
    } );
}

// Регистрация пользователя в RW и создание бонусной карты, если пользователь
// пожелал зарегистрироваться при оформлении заказа
if( placeOrderBtn ) {
    const email = document.querySelector( '#billing_email' );
    const phoneElem = document.querySelector( '#billing_phone' );
    const firstName = document.querySelector( '#billing_first_name' );
    const lastName = document.querySelector( '#billing_last_name' );
    const checkoutDataForm = document.querySelector( 'form.woocommerce-checkout' );
    const EmailField = document.querySelector( '#billing_email' ).parentElement;
    const errMessage = document.querySelector( '.err-message' );
    let rwToken = '',
        contactId = '';

    const beforeCheckoutSubmit = function( e ) {
        if( e.target && e.target.id == 'place_order' ) {
            let customersInfo = [];
            let phone = formatPhone( phoneElem.value );
            e.preventDefault();
            if( rightway && rightway.customerId ) {
                document.querySelector( 'form.woocommerce-checkout' ).removeEventListener( 'click', beforeCheckoutSubmit );
                document.querySelector( '#place_order' ).click();
            } else {
                if( document.querySelector( '#createaccount' ) && document.querySelector( '#createaccount' ).checked ) {
                    sendContactCode( phone )
                        .then( ( data ) => {
                            if( data.success ) {
                                showConfirmCodeForm1( 'телефон', sendContactCode, phone );
                                const confirmCodeForm = document.querySelector( 'form#enter-confirm-code' );
                                // Получаем код подтверждения из формы
                                confirmCodeForm.addEventListener( 'codeIsEntered', function( e ) {
                                    let isFormValid = confirmCodeForm.checkValidity();
                                    if( !isFormValid ) {
                                        confirmCodeForm.reportValidity();
                                    } else {
                                        // Проверяем правильность кода - пытаемся получить по нему токен
                                        let confirm_code = confirmCodeForm.elements.confirm_code.value;
                                        getRwToken( phone, confirm_code )
                                            .then( ( result ) => {
                                                rwToken = result;
                                                return getCustomersContacts( 'phone', phone );
                                            } )
                                            .then( ( data ) => {
                                                if( data.success ) {
                                                    // Если новый номер не уникальный
                                                    if( data.data.length > 1 && !errMessage ) {
                                                        jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                                        confirmBox.close();
                                                        billingPhoneField.insertAdjacentHTML( 'beforeend', '<span class="form-row form-row-wide err-message">Номер ' + phone + ' занят. Укажите, пожалуйста, другой.</span>' );
                                                        billingPhoneField.classList.add( 'woocommerce-invalid' );
                                                        return;
                                                    }
                                                    if( data.data.length == 0 ) {
                                                        // Создаем контакт в RW
                                                        return createRwCustomer( 'phone', phone, firstName.value, lastName.value, rwToken, '', '' );
                                                    }
                                                    if( data.data.length == 1 ) {
                                                        jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                                        confirmBox.close();
                                                        // Сохраняем анкетные данные, найденные для данного телефона в RW для последующей обработки
                                                        customersInfo = data.data[ 0 ];
                                                        return;

                                                        // Редактируем анкетные данные в RW в соответствии с указанными на сайте
                                                        /* return changeRwPersData(customersInfo.id); */
                                                    }
                                                }
                                            } )
                                            .then( ( result ) => {
                                                jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                                confirmBox.close();

                                                if( !customersInfo ) {
                                                    let data = JSON.parse( result.data );
                                                    // Добавляем в форму поля для привязки кастомера RW к покупателю на сайте

                                                    if( data.id ) {
                                                        checkoutDataForm.insertAdjacentHTML( 'beforeend', '<input type="hidden" name="cardId" value="' + data.id + '" />' );
                                                    }
                                                    if( data.number ) {
                                                        checkoutDataForm.insertAdjacentHTML( 'beforeend', '<input type="hidden" name="cardNumber" value="' + data.number + '" />' );
                                                    }
                                                    if( data.customerId ) {
                                                        checkoutDataForm.insertAdjacentHTML( 'beforeend', '<input type="hidden" name="customerId" value="' + data.customerId + '" />' );
                                                    }
                                                    checkoutDataForm.removeEventListener( 'click', beforeCheckoutSubmit );
                                                    document.querySelector( '#place_order' ).click();
                                                } else {
                                                    if( customersInfo.id ) {
                                                        // Получаем информацию о картах данного покупателя
                                                        getRwCustomerCards( customersInfo.id )
                                                            .then( ( result ) => {
                                                                let cardsArray = [];
                                                                let cardId = '',
                                                                    cardNumber = '';
                                                                cardsArray = JSON.parse( result.data );
                                                                // Выбираем идентификатор и номер карты для будущего сохранения в профиле пользователя на сайте
                                                                for( let key in cardsArray ) {
                                                                    if( !cardsArray[ key ].isBlocked ) {
                                                                        cardId = cardsArray[ key ].id;
                                                                        cardNumber = cardsArray[ key ].number;
                                                                    }
                                                                }
                                                                if( cardId ) {
                                                                    checkoutDataForm.insertAdjacentHTML( 'beforeend', '<input type="hidden" name="cardId" value="' + cardId + '" />' );
                                                                    checkoutDataForm.insertAdjacentHTML( 'beforeend', '<input type="hidden" name="cardNumber" value="' + cardNumber + '" />' );
                                                                }
                                                                checkoutDataForm.insertAdjacentHTML( 'beforeend', '<input type="hidden" name="customerId" value="' + customersInfo.id + '" />' );
                                                                checkoutDataForm.removeEventListener( 'click', beforeCheckoutSubmit );
                                                                document.querySelector( '#place_order' ).click();
                                                            } );
                                                    }
                                                }

                                                /* checkoutDataForm.removeEventListener( 'click', beforeCheckoutSubmit); */
                                                /* checkoutDataForm.trigger('checkout_place_order'); */
                                                /* document.querySelector( 'form.woocommerce-checkout' ).removeEventListener( 'click', beforeCheckoutSubmit);*/
                                                //document.querySelector( '#place_order' ).click();

                                            } )
                                            .catch( err => {
                                                jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                                confirmCodeForm.insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide">' + err + '</p>' );
                                            } );
                                    }
                                } );
                            } else {
                                jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                checkoutDataForm.insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide">' + data.data + '</p>' );
                            }
                        } )
                        .catch( err => {
                            jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                            checkoutDataForm.insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide">' + err + '</p>' );
                        } );
                } else {
                    document.querySelector( 'form.woocommerce-checkout' ).removeEventListener( 'click', beforeCheckoutSubmit );
                    document.querySelector( '#place_order' ).click();
                }

            }
        }
    }
    checkoutDataForm.addEventListener( 'click', beforeCheckoutSubmit );
}

} );
} )( jQuery );

( function( $ ) {
    $( function() {
        const billingField = document.querySelector( '#billing_phone' );
        const bonusesBlock = document.querySelector( '#bonuses' );
        let useBonusesCheckbox = document.querySelector( '#billing_use_bonuses' );
        let addBonusesCheckbox = document.querySelector( '#billing_add_bonuses' );
        let paymentMethod = '';

        // Инициализация paymentMethod при загрузке страницы (если метод уже выбран)
        const initPaymentMethod = function() {
            const paymentInput = document.querySelector( 'input[name="payment_method"]:checked' );
            if( paymentInput ) {
                paymentMethod = paymentInput.value;
            }
        };
        // Вызываем сразу при загрузке
        initPaymentMethod();
        // Также вызываем после небольшой задержки на случай, если методы оплаты загружаются асинхронно
        setTimeout( initPaymentMethod, 100 );

        // Отслеживание изменений метода оплаты (должно быть ДО проверки bonusesBlock)
        let currentPaymentMethod = '';
        let paymentMethodInitialized = false;
        
        // Функция для получения текущего метода оплаты
        const getCurrentPaymentMethod = function() {
            const paymentInput = document.querySelector( 'input[name="payment_method"]:checked' );
            if( paymentInput ) {
                return paymentInput.value;
            }
            return '';
        };
        
        // Получения суммы скидки по акции для покупателя, у которого нет бонусной карты в RigtWay
        const getDiscount = function() {
            return new Promise( function( resolve, reject ) {
                // Обновляем paymentMethod перед использованием
                const paymentInput = document.querySelector( 'input[name="payment_method"]:checked' );
                if( paymentInput ) {
                    paymentMethod = paymentInput.value;
                }
                
                let data = new FormData();
                data.append( 'action', 'rightway_calculateActionDiscount' );
                if( paymentMethod ) {
                    data.append( 'payment_method', paymentMethod );
                }
                fetch( rightway.ajax_url, {
                        method: "POST",
                        body: data
                    } )
                    .then( ( response ) => response.json() )
                    .then( ( data ) => {
                        if( data.success ) {
                            let discountObj = data.data;
                            document.querySelector( '#rw_discount' ).value = discountObj.rw_discount;
                        }
                        resolve();
                    } )
                    .catch( ( error ) => {
                        reject();
                    } );
            } );
        };
        
        // Функция для обработки изменения метода оплаты
        const handlePaymentMethodChange = function() {
            const newPaymentMethod = getCurrentPaymentMethod();
            
            // Если метод изменился или это первая инициализация
            if( newPaymentMethod !== currentPaymentMethod || !paymentMethodInitialized ) {
                currentPaymentMethod = newPaymentMethod;
                paymentMethod = newPaymentMethod; // Обновляем глобальную переменную
                paymentMethodInitialized = true;
                
                // Вызываем соответствующую функцию в зависимости от наличия customerId
                if( rightway && rightway.customerId && typeof bonusesAction === 'function' ) {
                    bonusesAction();
                } else {
                    document.querySelector( '#place_order' ).disabled = true;
                    getDiscount()
                        .then( () => {
                            // Обновляем, чтобы пересчитать итоговую сумму заказа
                            $( document.body ).trigger( 'update_checkout', { update_shipping_method: false } );
                            document.querySelector( '#place_order' ).disabled = false;
                        } );
                }
            }
        };
        
        // Обработчик события payment_method_selected (стандартное событие WooCommerce)
        $( document.body ).on( 'payment_method_selected', function() {
            handlePaymentMethodChange();
        } );
        
        // Обработчик прямого изменения метода оплаты (клик пользователя) - для надежности
        $( 'form.checkout' ).on( 'change', 'input[name="payment_method"]', function() {
            handlePaymentMethodChange();
        } );
        
        // Обработчик updated_checkout - проверяем, изменился ли метод оплаты
        $( document.body ).on( 'updated_checkout', function() {
            // Используем небольшую задержку, чтобы DOM успел обновиться
            setTimeout( function() {
                const newPaymentMethod = getCurrentPaymentMethod();
                
                if( newPaymentMethod && newPaymentMethod !== currentPaymentMethod ) {
                    handlePaymentMethodChange();
                } else if( !paymentMethodInitialized && newPaymentMethod ) {
                    // Первая инициализация, если метод уже выбран
                    handlePaymentMethodChange();
                }
            }, 100 );
        } );
        
        // Инициализация при первой загрузке страницы (если метод уже выбран)
        setTimeout( function() {
            const initialPaymentMethod = getCurrentPaymentMethod();
            if( initialPaymentMethod && !paymentMethodInitialized ) {
                handlePaymentMethodChange();
            }
        }, 500 );

        // Если блока бонусов нет, то не выполняем функцию
        if( !bonusesBlock ) {
            return;
        }

        const getBonuses = function() {
            return new Promise( function( resolve, reject ) {
                /* function getBonuses() { */
                if( !bonusesBlock ) {
                    return;
                }
                
                // Обновляем paymentMethod перед использованием
                const paymentInput = document.querySelector( 'input[name="payment_method"]:checked' );
                if( paymentInput ) {
                    paymentMethod = paymentInput.value;
                }
                
                let data = new FormData();
                data.append( 'action', 'rightway_get_active_bonuses' );
                data.append( 'billing_phone', formatPhone( billingField.value ) );
                data.append( 'use_bonuses', useBonusesCheckbox.checked );
                data.append( 'add_bonuses', addBonusesCheckbox.checked );
                if( paymentMethod ) {
                    data.append( 'payment_method', paymentMethod );
                }
                fetch( rightway.ajax_url, {
                        method: "POST",
                        body: data
                    } )
                    .then( ( response ) => response.json() )
                    .then( ( data ) => {
                        if( data.success ) {
                            let bonusesObj = data.data;
                            if( bonusesObj.МаксимальнаяСуммаСписанияБонусов == 'undefined' ) {
                                bonusesObj.МаксимальнаяСуммаСписанияБонусов = 'Нет данных';
                            }
                            /* document.querySelector( '#bonuses' ).insertAdjacentHTML("afterbegin",'<p class="form-row form-row-wide">Доступно для списания бонусов: '+bonusesObj.МаксимальнаяСуммаСписанияБонусов + '</p>' ); */
                            if( useBonusesCheckbox ) {
                                if( useBonusesCheckbox.checked ) {
                                    document.querySelector( '#bonuses-available' ).textContent = 'Будет списано бонусов: ' + bonusesObj.МаксимальнаяСуммаСписанияБонусов;
                                } else {
                                    document.querySelector( '#bonuses-available' ).textContent = '';
                                }
                            }
                            if( addBonusesCheckbox ) {
                                if( addBonusesCheckbox.checked ) {
                                    let addbonusesSum = parseInt( bonusesObj.МинимальнаяСуммаНачисленияБонусов ) + parseInt( bonusesObj.СуммаНачисленияПромоБонусов ) + parseInt( bonusesObj.СуммаНачисленияБонусовМА );
                                    document.querySelector( '#bonuses-added' ).textContent = 'Будет начислено бонусов: ' + addbonusesSum;
                                } else {
                                    document.querySelector( '#bonuses-added' ).textContent = '';
                                }
                            }
                            document.querySelector( '#active_bonuses' ).value = bonusesObj.МаксимальнаяСуммаСписанияБонусов;
                            document.querySelector( '#rw_discount' ).value = bonusesObj.СуммаСкидки;
                            document.querySelector( '#rw_doc_number' ).value = bonusesObj.НомерДокумента;
                            document.querySelector( '#rw_card_number' ).value = rightway.cardNumber;
                            document.querySelector( '#rw_cheque' ).value = bonusesObj.strs;
                            resolve();
                        } else {
                            reject( data.data || 'Ошибка получения бонусов' );
                        }
                    } )
                    .catch( ( error ) => {
                        document.querySelector( '#bonuses-available' ).textContent = error + '. Не удалось получить информацию о доступных бонусах';
                        reject();
                    } );
            } );
        }
        /*         $( '#billing_phone' ).on( 'blur', function() {
                    getBonuses();
                } ); */
        $( '.woocommerce-address-fields #gender' ).selectWoo(); // Стилизация выпадающего списка выбора пола

        const bonusesAction = function( e ) {
            //let paymentMethod = '';
            if( useBonusesCheckbox && addBonusesCheckbox ) {
                if( !useBonusesCheckbox.checked && addBonusesCheckbox.checked ) {
                    useBonusesCheckbox.value = "0";
                    addBonusesCheckbox.value = "1";
                    document.querySelector( '#rw_card_operation' ).value = 'Начисление';
                }
                if( useBonusesCheckbox.checked && !addBonusesCheckbox.checked ) {
                    useBonusesCheckbox.value = "1";
                    addBonusesCheckbox.value = "0";
                    document.querySelector( '#rw_card_operation' ).value = 'Списание';
                }
                if( useBonusesCheckbox.checked && addBonusesCheckbox.checked ) {
                    useBonusesCheckbox.value = "1";
                    addBonusesCheckbox.value = "1";
                    document.querySelector( '#rw_card_operation' ).value = 'СписаниеИНачисление';
                }
                if( !useBonusesCheckbox.checked && !addBonusesCheckbox.checked ) {
                    useBonusesCheckbox.value = "0";
                    addBonusesCheckbox.value = "0";
                    document.querySelector( '#rw_card_operation' ).value = '';
                }
            }

            // Блокируем aside.order_section-aside перед выполнением getBonuses
            const asideElement = $( '.order_section-aside' );

            asideElement.block( {
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            } );

            getBonuses()
                .then( () => {
                    // Задержка перед обновлением checkout, чтобы дать время WooCommerce завершить предыдущие операции
                    // и избежать ошибки NS_BINDING_ABORTED
                    setTimeout( () => {
                        // Разблокируем элемент перед обновлением checkout
                        asideElement.unblock();
                        // Обновляем, чтобы пересчитать итоговую сумму заказа
                        $( document.body ).trigger( 'update_checkout', { update_shipping_method: false } );
                        document.querySelector( '#place_order' ).disabled = false;
                    }, 300 );
                } )
                .catch( () => {
                    // В случае ошибки также разблокируем элемент
                    asideElement.unblock();
                    document.querySelector( '#place_order' ).disabled = false;
                } );

        }
        // Устанавливаем обработчики на сами чекбоксы, а не на контейнеры, и используем событие 'change' вместо 'click'
        // Это предотвратит двойной вызов из-за всплытия событий
        if( useBonusesCheckbox ) {
            useBonusesCheckbox.addEventListener( 'change', () => setTimeout( bonusesAction, 2000 ) );
        }
        if( addBonusesCheckbox ) {
            addBonusesCheckbox.addEventListener( 'change', () => setTimeout( bonusesAction, 2000 ) );
        }

        let billing_first_name = document.querySelector( '#billing_first_name' );
        if( billing_first_name ) {
            billing_first_name.addEventListener( 'change', filterField );
        }

        let billing_last_name = document.querySelector( '#billing_last_name' );
        if( billing_last_name ) {
            billing_last_name.addEventListener( 'change', filterField );
        }

        function filterField( e ) {
            let t = e.target;
            let badValues = /[^a-zA-Zа-яА-Я ]/gi;
            t.value = t.value.replace( badValues, '' );
        }

        $( '#gender' ).select2();

    } );
} )( jQuery );

// Функция для форматирования телефона: оставляет только цифры и лидирующий "+7"
function formatPhone( phone ) {
    // Удаляем все символы кроме цифр и плюса
    let cleaned = phone.replace( /[^\d+]/g, '' );
    
    // Удаляем все плюсы кроме первого
    let parts = cleaned.split( '+' );
    if( parts.length > 1 ) {
        cleaned = '+' + parts.filter( p => p ).join( '' );
    }
    
    // Удаляем плюсы не в начале строки
    cleaned = cleaned.replace( /^(\+?)(.*)$/, function( match, plus, rest ) {
        return plus + rest.replace( /\+/g, '' );
    } );
    
    // Оставляем только цифры (плюс уберем временно для обработки)
    let hasPlus = cleaned.startsWith( '+' );
    let digitsOnly = cleaned.replace( /\D/g, '' );
    
    // Проверяем, начинается ли с 7
    if( digitsOnly.startsWith( '7' ) ) {
        return '+' + digitsOnly;
    } else {
        // Если не начинается с 7, добавляем +7
        return '+7' + digitsOnly;
    }
}