'use strict';

( function( $ ) {
    $( function() {
        var RCC = window.RightwayConfirmCode;
        var OTP = window.RightwayOtp;
        var RCM = window.RightwayConfirmModal;
        var API = window.RightwayApi;

        /**
         * Checkout: «Создать аккаунт» + код на телефон + регистрация в RW.
         * Сейчас НЕ РАБОТАЕТ — не тестировать. При false заказ оформляется без перехвата RW.
         * @type {boolean}
         */
        const CHECKOUT_CREATE_ACCOUNT_OTP_ENABLED = false;

        var placeOrderBtn = document.querySelector( '.woocommerce-checkout input#place_order' );
        if( placeOrderBtn ) {
            const email = document.querySelector( '#billing_email' );
            const phoneElem = document.querySelector( '#billing_phone' );
            const firstName = document.querySelector( '#billing_first_name' );
            const lastName = document.querySelector( '#billing_last_name' );
            const checkoutDataForm = document.querySelector( 'form.woocommerce-checkout' );
            const checkoutPhoneField = document.querySelector( '#billing_phone_field' );
            const errMessage = document.querySelector( '.err-message' );
            let rwToken = '',
                contactId = '';

            /**
             * Ответ `sendContactCode` на checkout: ошибка отправки SMS.
             * @param {{ success: boolean, data?: * }} data
             * @returns {void}
             */
            function onCheckoutSendContactCodeFailed( data ) {
                if( OTP ) {
                    OTP.reset();
                }
                jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                checkoutDataForm.insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide">' + rightwayUserErrorMessage( data.data ) + '</p>' );
            }

            /**
             * Модалка кода на checkout после успешной отправки SMS.
             * @param {string} phone
             * @param {{ customersInfo: Array|Object }} otpState
             * @returns {void}
             */
            function openCheckoutConfirmCodeModal( phone, otpState ) {
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
                        .then( function( result ) {
                            rwToken = result;
                            return API.getCustomersContacts( 'phone', phone );
                        } )
                        .then( function( data ) {
                            if( data.success ) {
                                if( data.data.length > 1 && !errMessage ) {
                                    jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                    if( RCM ) {
                                        RCM.close();
                                    }
                                    if( checkoutPhoneField ) {
                                        checkoutPhoneField.insertAdjacentHTML( 'beforeend', '<span class="form-row form-row-wide err-message">Номер ' + phone + ' занят. Укажите, пожалуйста, другой.</span>' );
                                        checkoutPhoneField.classList.add( 'woocommerce-invalid' );
                                    }
                                    return;
                                }
                                if( data.data.length === 0 ) {
                                    return API.createCustomer( 'phone', phone, firstName.value, lastName.value, rwToken, '', '' );
                                }
                                if( data.data.length === 1 ) {
                                    jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                    if( RCM ) {
                                        RCM.close();
                                    }
                                    otpState.customersInfo = data.data[ 0 ];
                                    return;
                                }
                            }
                        } )
                        .then( function( result ) {
                            if( OTP ) {
                                OTP.setSuccess();
                            }
                            jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                            if( RCM ) {
                                RCM.close();
                            }

                            if( !otpState.customersInfo || otpState.customersInfo.length === 0 ) {
                                unbind();
                                let data = JSON.parse( result.data );
                                appendRwHiddenFields( checkoutDataForm, {
                                    cardId: data.id,
                                    cardNumber: data.number,
                                    customerId: data.customerId
                                } );
                                checkoutDataForm.removeEventListener( 'click', beforeCheckoutSubmit );
                                document.querySelector( '#place_order' ).click();
                            } else if( otpState.customersInfo.id ) {
                                API.getRwCustomerCards( otpState.customersInfo.id )
                                    .then( function( result ) {
                                        const card = pickFirstActiveRwCard( result.data );
                                        appendRwHiddenFields( checkoutDataForm, {
                                            cardId: card.cardId,
                                            cardNumber: card.cardNumber,
                                            customerId: otpState.customersInfo.id
                                        } );
                                        unbind();
                                        checkoutDataForm.removeEventListener( 'click', beforeCheckoutSubmit );
                                        document.querySelector( '#place_order' ).click();
                                    } );
                            }
                        } )
                        .catch( function( err ) {
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
                            confirmCodeForm.insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide">' + rightwayUserErrorMessage( err ) + '</p>' );
                        } );
                } );
            }

            /**
             * @param {{ success: boolean, data?: * }} data
             * @param {string} phone
             * @param {{ customersInfo: Array|Object }} otpState
             * @returns {void}
             */
            function onCheckoutSendContactCodeResponse( data, phone, otpState ) {
                if( !data.success ) {
                    onCheckoutSendContactCodeFailed( data );
                    return;
                }
                openCheckoutConfirmCodeModal( phone, otpState );
            }

            /**
             * Перехват клика по «Оформить заказ»: привязка к RW по телефону при «Создать аккаунт» или пропуск, если уже есть `rightway.customerId`.
             * @param {MouseEvent} e
             * @returns {void}
             */
            const beforeCheckoutSubmit = function( e ) {
                if( e.target && e.target.id === 'place_order' ) {
                    const otpState = { customersInfo: [] };
                    let phone = formatPhone( phoneElem.value );
                    e.preventDefault();
                    if( rightway && rightway.customerId ) {
                        document.querySelector( 'form.woocommerce-checkout' ).removeEventListener( 'click', beforeCheckoutSubmit );
                        document.querySelector( '#place_order' ).click();
                    } else {
                        if( document.querySelector( '#createaccount' ) && document.querySelector( '#createaccount' ).checked ) {
                            if( !CHECKOUT_CREATE_ACCOUNT_OTP_ENABLED ) {
                                checkoutDataForm.removeEventListener( 'click', beforeCheckoutSubmit );
                                document.querySelector( '#place_order' ).click();
                                return;
                            }
                            if( OTP ) {
                                OTP.setSending();
                            }
                            API.sendContactCode( phone )
                                .then( function( data ) {
                                    onCheckoutSendContactCodeResponse( data, phone, otpState );
                                } )
                                .catch( function( err ) {
                                    if( OTP ) {
                                        OTP.reset();
                                    }
                                    jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                    checkoutDataForm.insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide">' + rightwayUserErrorMessage( err ) + '</p>' );
                                } );
                        } else {
                            document.querySelector( 'form.woocommerce-checkout' ).removeEventListener( 'click', beforeCheckoutSubmit );
                            document.querySelector( '#place_order' ).click();
                        }
                    }
                }
            };

            checkoutDataForm.addEventListener( 'click', beforeCheckoutSubmit );
        }
    } );
} )( jQuery );
