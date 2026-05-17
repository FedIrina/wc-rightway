'use strict';

( function( global ) {
    var API = global.RightwayApi;
    var RCC = global.RightwayConfirmCode;
    var OTP = global.RightwayOtp;
    var RCM = global.RightwayConfirmModal;

    /**
     * Поля анкеты из формы `cabinet_data`.
     * @returns {{ billing_first_name: string, billing_last_name: string, birthDate: string, gender: string }}
     */
    function getCabinetPersFields() {
        const addressDataForm = document.querySelector( 'form.cabinet_data' );
        return {
            billing_first_name: addressDataForm.elements.billing_first_name.value,
            billing_last_name: addressDataForm.elements.billing_last_name.value,
            birthDate: addressDataForm.elements.birthDate.value,
            gender: addressDataForm.elements.gender.value
        };
    }

    /**
     * Сохранение ФИО, даты рождения и пола покупателя в RW.
     * @param {string} customerId
     * @returns {Promise<{success: boolean, data?: *}>}
     */
    function changeRwPersData( customerId ) {
        return API.editCustomerData( customerId, getCabinetPersFields() );
    }

    /**
     * После успешной работы с телефоном на billing: синхронизация email с RW (код на почту), если у customerId ещё нет такого контакта.
     * @param {string} customerId
     * @param {string} billingEmail
     * @param {Array<{id?: *, value?: string}>|undefined} [contactsSnapshot]
     * @returns {Promise<void>}
     */
    function ensureRwBillingEmailContact( customerId, billingEmail, contactsSnapshot ) {
        return new Promise( function( resolve, reject ) {
            if( !customerId || !billingEmail || String( billingEmail ).trim().indexOf( '@' ) === -1 ) {
                resolve();
                return;
            }
            const email = String( billingEmail ).trim();
            const emailNorm = email.toLowerCase();

            function runBillingEmailSync( contactsArray ) {
                let noRwUpdate = false;
                let contactId = '';
                contactsArray.forEach( function( contact ) {
                    if( contact.value && contact.value.indexOf( '@' ) >= 0 ) {
                        if( String( contact.value ).trim().toLowerCase() === emailNorm ) {
                            noRwUpdate = true;
                        }
                        contactId = contact.id;
                    }
                } );
                if( noRwUpdate ) {
                    resolve();
                    return;
                }
                if( OTP ) {
                    OTP.setSending();
                }
                API.sendContactCode( email ).then( function( sendCodeResponse ) {
                    if( !sendCodeResponse.success ) {
                        if( OTP ) {
                            OTP.reset();
                        }
                        return Promise.reject( sendCodeResponse.data || 'Не удалось отправить код на email' );
                    }
                    if( RCM ) {
                        RCM.open( {
                            channel: 'Email',
                            address: email,
                            resend: function() {
                                return API.sendContactCode( email );
                            }
                        } );
                    }
                    const confirmCodeForm = document.querySelector( 'form#enter-confirm-code' );
                    RCC.replaceCommunicationConfirmUnbind(
                        RCC.onEnterConfirmCode( confirmCodeForm, function( confirm_code, unbind ) {
                            API.getRwToken( email, confirm_code )
                                .then( function( rwToken ) {
                                    if( OTP ) {
                                        OTP.setSuccess();
                                    }
                                    jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                    if( RCM ) {
                                        RCM.close();
                                    }
                                    if( contactId ) {
                                        return API.editContactData( 'email', email, contactId, rwToken );
                                    }
                                    return API.createContact( email, rwToken );
                                } )
                                .then( function() {
                                    unbind();
                                    resolve();
                                } )
                                .catch( function( err ) {
                                    if( RCC.handleWrongConfirmCode( {
                                        err: err,
                                        form: confirmCodeForm,
                                        resend: function() {
                                            return API.sendContactCode( email );
                                        },
                                        messageAfterResend: 'Код введён неверно. На email отправлен новый код — введите цифры из последнего письма.'
                                    } ) ) {
                                        return;
                                    }
                                    jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                    reject( err );
                                } );
                        } )
                    );
                } ).catch( reject );
            }

            if( contactsSnapshot && Array.isArray( contactsSnapshot ) ) {
                runBillingEmailSync( contactsSnapshot );
                return;
            }
            API.getCustomerContacts( customerId ).then( function( data ) {
                if( !data.success ) {
                    reject( data.data || 'Ошибка загрузки контактов RW' );
                    return;
                }
                runBillingEmailSync( data.data || [] );
            } ).catch( reject );
        } );
    }

    global.RightwayLkShared = {
        getCabinetPersFields: getCabinetPersFields,
        changeRwPersData: changeRwPersData,
        ensureRwBillingEmailContact: ensureRwBillingEmailContact
    };
}( typeof window !== 'undefined' ? window : this ) );
