'use strict';

( function( $ ) {
    $( function() {
        var RCC = window.RightwayConfirmCode;
        var OTP = window.RightwayOtp;
        var RCM = window.RightwayConfirmModal;
        var API = window.RightwayApi;
        var Lk = window.RightwayLkShared;

        var addressSubmitBtn = document.querySelector( '.woocommerce-edit-address form input[name=save_address]' );
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
                e.preventDefault();
                let $billingRwBlock = resolveBillingRwBlock( addressDataForm );
                $billingRwBlock.addClass( 'processing' ).block( rightwayRwAwaitBlockOptions() );
                /**
                 * Синхронизация email из формы billing с RW для указанного покупателя.
                 * @param {string} customerIdForEmail
                 * @returns {Promise<void>}
                 */
                /**
                 * @param {string} customerIdForEmail
                 * @param {Array<{id?: *, value?: string}>|undefined} [contactsFromRw]
                 * @returns {Promise<void>}
                 */
                function billingEmailSync( customerIdForEmail, contactsFromRw ) {
                    return window.RightwayLkShared.ensureRwBillingEmailContact(
                        customerIdForEmail,
                        addressDataForm.elements.billing_email ? addressDataForm.elements.billing_email.value : '',
                        contactsFromRw
                    );
                }
                /**
                 * Снимает оверлей ожидания RW и отправляет форму адреса WooCommerce.
                 * @returns {void}
                 */
                function unblockAndSubmitBilling() {
                    $billingRwBlock.removeClass( 'processing' ).unblock();
                    addressDataForm.submit();
                }
                let rwToken = '';
                let customersInfo = [],
                    contactsArray = [];
                //let phone = addressDataForm.elements.billing_phone.value.replace( new RegExp( /[-() /\\]/g ), '' );
                let phone = formatPhone( addressDataForm.elements.billing_phone.value );
                // Получаем customerId, сохраненный на сайте для текущего пользователя
                if( rightway.customerId ) {
                    API.getCustomerContacts()
                        .then( function( data ) {
                            if( !data.success ) {
                                throw new Error( data.data || 'Ошибка загрузки контактов RW' );
                            }
                            const contacts = data.data || [];
                            let phoneContactId = '';
                            let rwPhoneNorm = '';
                            let rwEmailNorm = '';
                            contacts.forEach( function( contact ) {
                                if( contact.value && contact.value.indexOf( '+' ) >= 0 ) {
                                    phoneContactId = contact.id;
                                    rwPhoneNorm = formatPhone( contact.value );
                                }
                                if( contact.value && contact.value.indexOf( '@' ) >= 0 ) {
                                    rwEmailNorm = String( contact.value ).trim().toLowerCase();
                                }
                            } );
                            const formEmailNorm = addressDataForm.elements.billing_email
                                ? String( addressDataForm.elements.billing_email.value ).trim().toLowerCase()
                                : '';
                            const phoneMatchesRw = rwPhoneNorm !== '' && rwPhoneNorm === phone;
                            const emailMatchesRw = rwEmailNorm !== '' && rwEmailNorm === formEmailNorm;

                            /**
                             * Модалка SMS: после ввода кода меняет телефон в RW (`changeRwContactData`).
                             * @param {string} phoneValue
                             * @param {string} rwPhoneContactId
                             * @returns {Promise<void>}
                             */
                            function confirmBillingPhoneModalPromise( phoneValue, rwPhoneContactId ) {
                                return new Promise( function( resolve, reject ) {
                                    $billingRwBlock.removeClass( 'processing' ).unblock();
                                    if( RCM ) {
                                        RCM.open( {
                                            channel: 'телефон',
                                            address: phoneValue,
                                            resend: function() {
                                                return API.sendContactCode( phoneValue );
                                            }
                                        } );
                                    }
                                    const confirmCodeForm = document.querySelector( 'form#enter-confirm-code' );
                                    RCC.replaceCommunicationConfirmUnbind(
                                        RCC.onEnterConfirmCode( confirmCodeForm, function( confirm_code, unbind ) {
                                        API.getRwToken( phoneValue, confirm_code )
                                            .then( function( rwTok ) {
                                                return API.editContactData( 'phone', phoneValue, rwPhoneContactId, rwTok );
                                            } )
                                            .then( function() {
                                                if( OTP ) {
                                                    OTP.setSuccess();
                                                }
                                                jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                                if( RCM ) {
                                                    RCM.close();
                                                }
                                                unbind();
                                                RCC.clearCommunicationConfirmUnbind();
                                                resolve();
                                            } )
                                            .catch( function( err ) {
                                                jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                                if( RCC.handleWrongConfirmCode( {
                                                    err: err,
                                                    form: confirmCodeForm,
                                                    resend: function() {
                                                        return API.sendContactCode( phoneValue );
                                                    },
                                                    messageAfterResend: 'Код введён неверно. На телефон отправлен новый код — введите цифры из последнего SMS.'
                                                } ) ) {
                                                    return;
                                                }
                                                if( OTP ) {
                                                    OTP.setError();
                                                    OTP.setAwaiting();
                                                }
                                                reject( err );
                                            } );
                                    } ) );
                                } );
                            }

                            /**
                             * Сохраняет анкету в RW и при расхождении email с RW — синхронизирует email через `ensureRwBillingEmailContact`, затем submit формы.
                             * @returns {Promise<void>}
                             */
                            function rwPersThenEmailThenSubmit() {
                                return window.RightwayLkShared.changeRwPersData( rightway.customerId )
                                    .then( function() {
                                        if( emailMatchesRw ) {
                                            return Promise.resolve();
                                        }
                                        return billingEmailSync( rightway.customerId, contacts );
                                    } )
                                    .then( function() {
                                        unblockAndSubmitBilling();
                                    } );
                            }

                            /**
                             * Код и смена телефона в RW, затем `rwPersThenEmailThenSubmit`.
                             * @returns {Promise<void>}
                             */
                            function runPhoneVerificationThenPersAndEmail() {
                                /* Временно отключено: поиск других покупателей RW с этим телефоном (rightway_get_customers).
                                return API.getCustomersContacts( 'phone', phone ).then( function( searchData ) {
                                    if( !searchData.success ) {
                                        throw new Error( searchData.data || 'Ошибка поиска по телефону в RW' );
                                    }
                                    const raw = searchData.data;
                                    const list = Array.isArray( raw )
                                        ? raw.filter( function( row ) {
                                            return row && row.id != null && row.id !== '';
                                        } )
                                        : [];
                                    if( list.length === 0 ) {
                                        if( OTP ) {
                                            OTP.setSending();
                                        }
                                        return API.sendContactCode( phone ).then( function( sendCodeResponse ) {
                                            if( !sendCodeResponse.success ) {
                                                if( OTP ) {
                                                    OTP.reset();
                                                }
                                                throw new Error( sendCodeResponse.data || 'Не удалось отправить код на телефон' );
                                            }
                                            return confirmBillingPhoneModalPromise( phone, phoneContactId );
                                        } );
                                    }
                                    if( list.length > 1 ) {
                                        throw new Error( 'Найдено несколько участников программы лояльности с этим телефоном. Обратитесь в поддержку.' );
                                    }
                                    if( String( list[ 0 ].id ) !== String( rightway.customerId ) ) {
                                        throw new Error( 'Этот телефон уже привязан к другой записи в программе лояльности.' );
                                    }
                                    return Promise.resolve();
                                } ).then( function() {
                                    return rwPersThenEmailThenSubmit();
                                } );
                                */
                                if( OTP ) {
                                    OTP.setSending();
                                }
                                return API.sendContactCode( phone ).then( function( sendCodeResponse ) {
                                    if( !sendCodeResponse.success ) {
                                        if( OTP ) {
                                            OTP.reset();
                                        }
                                        throw new Error( sendCodeResponse.data || 'Не удалось отправить код на телефон' );
                                    }
                                    return confirmBillingPhoneModalPromise( phone, phoneContactId );
                                } ).then( function() {
                                    return rwPersThenEmailThenSubmit();
                                } );
                            }

                            if( phoneMatchesRw ) {
                                return rwPersThenEmailThenSubmit();
                            }
                            return runPhoneVerificationThenPersAndEmail();
                        } )
                        .catch( function( err ) {
                            $billingRwBlock.removeClass( 'processing' ).unblock();
                            if( RCM ) {
                                try {
                                    RCM.close();
                                } catch( ignoreClose ) {}
                            }
                            rightwayShowBillingFormError( addressDataForm, err );
                        } );


                } else {
                    if( OTP ) {
                        OTP.setSending();
                    }
                    API.sendContactCode( phone )
                        .then( ( data ) => {
                            if( data.success ) {
                                $billingRwBlock.removeClass( 'processing' ).unblock();
                                if( RCM ) {
                                    RCM.open( {
                                        channel: 'телефон',
                                        address: phone,
                                        resend: function() {
                                            return API.sendContactCode( phone );
                                        }
                                    } );
                                }
                                const confirmCodeForm = document.querySelector( 'form#enter-confirm-code' );
                                RCC.onEnterConfirmCode( confirmCodeForm, function( confirm_code, unbind ) {
                                        API.getRwToken( phone, confirm_code )
                                            .then( ( result ) => {
                                                rwToken = result;
                                                return API.getCustomersContacts( 'phone', phone, result );
                                            } )
                                            .then( ( data ) => {
                                                if( data.success ) {
                                                    // Если новый номер не уникальный
                                                    /* data.data.length=0// ВРЕМЕННО ДЛЯ ТЕСТА */

                                                    if( data.data.length > 1 && !errMessage ) {
                                                        jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                                        if( RCM ) {
                                                            RCM.close();
                                                        }
                                                        billingPhoneField.insertAdjacentHTML( 'beforeend', '<span class="form-row form-row-wide err-message">Номер ' + phone + ' занят. Укажите, пожалуйста, другой.</span>' );
                                                        billingPhoneField.classList.add( 'woocommerce-invalid' );
                                                        $billingRwBlock.removeClass( 'processing' ).unblock();
                                                        return;
                                                    }
                                                    if( data.data.length === 0 ) {
                                                        // Создаем контакт в RW
                                                        return API.createCustomer( 'phone', phone, firstName.value, lastName.value, rwToken, birthDate.value, gender.value );
                                                    }
                                                    if( data.data.length === 1 ) {
                                                        // Сохраняем анкетные данные, найденные для данного телефона в RW для последующей обработки
                                                        customersInfo = data.data[ 0 ];

                                                        // Редактируем анкетные данные в RW в соответствии с указанными на сайте
                                                        return window.RightwayLkShared.changeRwPersData( customersInfo.id );
                                                    }
                                                }
                                            } )
                                            .then( ( result ) => {
                                                // Если в предыдущем действии создавали пользователя и карту в RW
                                                if( !customersInfo || customersInfo.length === 0 ) {
                                                    let data = JSON.parse( result.data );
                                                    const cidMail = data.customerId || '';
                                                    appendRwHiddenFields( addressDataForm, {
                                                        cardId: data.id,
                                                        cardNumber: data.number,
                                                        customerId: data.customerId
                                                    } );
                                                    billingEmailSync( cidMail ).then( function() {
                                                        if( OTP ) {
                                                            OTP.setSuccess();
                                                        }
                                                        unbind();
                                                        jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                                        if( RCM ) {
                                                            RCM.close();
                                                        }
                                                        unblockAndSubmitBilling();
                                                    } ).catch( function( err ) {
                                                        if( OTP ) {
                                                            OTP.setError();
                                                            OTP.setAwaiting();
                                                        }
                                                        jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                                        $billingRwBlock.removeClass( 'processing' ).unblock();
                                                        confirmCodeForm.insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide">' + err + '</p>' );
                                                    } );
                                                } else {
                                                    // Если в предыдущем действии читали и обновляли данные пользователя и карты в RW
                                                    if( customersInfo.id ) {
                                                        API.getRwCustomerCards( customersInfo.id )
                                                            .then( ( result ) => {
                                                                const card = pickFirstActiveRwCard( result.data );
                                                                appendRwHiddenFields( addressDataForm, {
                                                                    cardId: card.cardId,
                                                                    cardNumber: card.cardNumber,
                                                                    customerId: customersInfo.id
                                                                } );
                                                                return billingEmailSync( customersInfo.id );
                                                            } )
                                                            .then( function() {
                                                                if( OTP ) {
                                                                    OTP.setSuccess();
                                                                }
                                                                unbind();
                                                                jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                                                if( RCM ) {
                                                            RCM.close();
                                                        }
                                                                unblockAndSubmitBilling();
                                                            } )
                                                            .catch( function( err ) {
                                                                if( OTP ) {
                                                                    OTP.setError();
                                                                    OTP.setAwaiting();
                                                                }
                                                                jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                                                $billingRwBlock.removeClass( 'processing' ).unblock();
                                                                confirmCodeForm.insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide">' + err + '</p>' );
                                                            } );
                                                    }
                                                }
                                            } )
                                            .catch( err => {
                                                jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                                if( RCC.handleWrongConfirmCode( {
                                                    err: err,
                                                    form: confirmCodeForm,
                                                    resend: function() {
                                                        return API.sendContactCode( phone );
                                                    },
                                                    messageAfterResend: 'Код введён неверно. На телефон отправлен новый код — введите цифры из последнего SMS.'
                                                } ) ) {
                                                    return;
                                                }
                                                if( OTP ) {
                                                    OTP.setError();
                                                    OTP.setAwaiting();
                                                }
                                                confirmCodeForm.insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide">' + err + '</p>' );
                                            } );
                                } );
                            } else {
                                if( OTP ) {
                                    OTP.reset();
                                }
                                jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                $billingRwBlock.removeClass( 'processing' ).unblock();
                                rightwayShowBillingFormError( addressDataForm, data.data );
                            }
                        } )
                        .catch( err => {
                            if( OTP ) {
                                OTP.reset();
                            }
                            jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                            $billingRwBlock.removeClass( 'processing' ).unblock();
                            rightwayShowBillingFormError( addressDataForm, err );
                        } );
                }
            } );
        }
    } );
} )( jQuery );
