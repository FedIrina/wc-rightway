'use strict';

( function( $ ) {
    $( function() {
        var RCC = window.RightwayConfirmCode;
        var OTP = window.RightwayOtp;
        var RCM = window.RightwayConfirmModal;
        var API = window.RightwayApi;
        var commSubmitBtn = document.querySelector( '#woocommerce-edit-communication input[type=submit]' );
        function rwCommFlagIsOn( value ) {
            return value === true || value === 'true' || value === 1 || value === '1';
        }

        /**
         * Контакт RW для sendConfirmationCode при сохранении коммуникаций.
         * По умолчанию — подтверждённый телефон, иначе email; при {@code preferEmail} — наоборот (включение рассылки по email).
         * @param {Array<{id?: *, value?: string, confirmed?: boolean}>|undefined} contacts
         * @param {{ preferEmail?: boolean }} [options]
         * @returns {{ id: string, channel: 'sms'|'email', address: string }|null}
         */
        function pickRwConfirmedContactForCommCode( contacts, options ) {
            if( !contacts || !contacts.length ) {
                return null;
            }
            const preferEmail = !!( options && options.preferEmail );
            let confirmedPhone = null;
            let confirmedEmail = null;
            for( let i = 0; i < contacts.length; i++ ) {
                const rwContact = contacts[ i ];
                if( !rwContact || !rwContact.confirmed || rwContact.id === null || rwContact.id === '' || !rwContact.value ) {
                    continue;
                }
                const contactValue = String( rwContact.value ).trim();
                if( contactValue.indexOf( '+' ) >= 0 && !confirmedPhone ) {
                    confirmedPhone = { id: String( rwContact.id ), channel: 'sms', address: contactValue };
                }
                if( contactValue.indexOf( '@' ) >= 0 && !confirmedEmail ) {
                    confirmedEmail = { id: String( rwContact.id ), channel: 'email', address: contactValue };
                }
            }
            if( preferEmail && confirmedEmail ) {
                return confirmedEmail;
            }
            return confirmedPhone || confirmedEmail || null;
        }

        /**
         * Удаляет ранее выведенные под формой коммуникации сообщения (чтобы не копились при повторных ошибках).
         * @param {HTMLFormElement|null} form
         * @returns {void}
         */
        function clearCommunicationFormNotices( form ) {
            if( !form ) {
                return;
            }
            form.querySelectorAll( '.rightway-communication-notice, p.form-row.form-row-wide' ).forEach( function( el ) {
                el.remove();
            } );
        }

        // Запрос кода подтверждения при сохранении настроек коммуникации пользователя в ЛК сайта
        if( commSubmitBtn ) {
            commSubmitBtn.addEventListener( 'click', function( e ) {
                e.preventDefault();
                const rwWaitMount = document.querySelector( '.cabinet_section' ) || document.querySelector( '.woocommerce-MyAccount-content' ) || document.querySelector( '#woocommerce-edit-communication' );
                const $rwWait = rwWaitMount && rwWaitMount.nodeType ? jQuery( rwWaitMount ) : null;
                /** Снимает blockUI с контейнера ожидания коммуникаций. @returns {void} */
                function rwCommUnblock() {
                    rightwayReleaseRwWaitOverlays();
                }
                if( $rwWait && $rwWait.length ) {
                    $rwWait.addClass( 'rightway-rw-waiting' ).block( rightwayRwAwaitBlockOptions() );
                }
                let cardSummary = '';
                // Получаем настройки карты от RW
                API.getCardSummary()
                    .then( ( data ) => {
                        if( data.success ) {
                            cardSummary = JSON.parse( data.data );
                            const rwCom = cardSummary.communicationSettings || {};
                            const communicationDataForm = document.querySelector( '#woocommerce-edit-communication' );
                            const allowSmsChecked = communicationDataForm.elements.allowSms.checked;
                            const allowEmailChecked = communicationDataForm.elements.allowEmail.checked;
                            const allowMarketingChecked = communicationDataForm.elements.allowMarketingCommunication.checked;
                            const hasEmailContact = cardSummary.contacts && cardSummary.contacts.some( function( rwContact ) {
                                return rwContact.value && String( rwContact.value ).indexOf( '@' ) !== -1;
                            } );
                            const allowEmailForRw = !!( hasEmailContact && allowEmailChecked );
                            const enablingEmail = allowEmailForRw && !rwCommFlagIsOn( rwCom.allowEmail );
                            const hasRwChanges = rwCommFlagIsOn( allowSmsChecked ) !== rwCommFlagIsOn( rwCom.allowSms )
                                || rwCommFlagIsOn( allowEmailForRw ) !== rwCommFlagIsOn( rwCom.allowEmail )
                                || rwCommFlagIsOn( allowMarketingChecked ) !== rwCommFlagIsOn( rwCom.allowMarketingCommunication );
                            const commRwCodeContact = pickRwConfirmedContactForCommCode( cardSummary.contacts || [], {
                                preferEmail: enablingEmail
                            } );
                            if( !rightway.contactId && commRwCodeContact && commRwCodeContact.id ) {
                                rightway.contactId = commRwCodeContact.id;
                            }
                            const commRwFlags = {
                                allowSms: allowSmsChecked,
                                allowEmail: allowEmailForRw,
                                allowMarketingCommunication: allowMarketingChecked
                            };
                            // Сравнение RW с тем, что реально можно отправить (без allowEmail, если в RW нет email-контакта)
                            if( hasRwChanges ) {
                                if( allowEmailChecked && !hasEmailContact ) {
                                    clearCommunicationFormNotices( communicationDataForm );
                                    communicationDataForm.insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide rightway-communication-notice">В RightWay нельзя включить рассылку по email без email-контакта. Добавьте email в «Данные покупателя». Остальные изменения сохраняются после ввода кода подтверждения.</p>' );
                                }
                                // RightWay требует confirmationCode при любом изменении настроек (в т.ч. отключении канала)
                                if( !commRwCodeContact || !commRwCodeContact.id ) {
                                    rwCommUnblock();
                                    clearCommunicationFormNotices( communicationDataForm );
                                    communicationDataForm.insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide rightway-communication-notice">В карте лояльности нет подтверждённого телефона или email — отправить код нельзя. Проверьте контакты в программе лояльности или обратитесь в поддержку.</p>' );
                                    return;
                                }
                                if( OTP ) {
                                    OTP.setSending();
                                }
                                API.sendCode( commRwCodeContact.id ).then( function( sendCodeResponse ) {
                                    if( sendCodeResponse.success ) {
                                        rwCommUnblock();
                                        const commSentLabel = commRwCodeContact.channel === 'email' ? 'Email на' : 'SMS на';
                                        if( RCM ) {
                                            RCM.open( {
                                                channel: commSentLabel,
                                                address: commRwCodeContact.address,
                                                resend: function() {
                                                    return API.sendCode( commRwCodeContact.id );
                                                }
                                            } );
                                        }
                                        const confirmCodeForm = document.querySelector( 'form#enter-confirm-code' );
                                        RCC.replaceCommunicationConfirmUnbind(
                                            RCC.onEnterConfirmCode( confirmCodeForm, function( confirm_code, unbind ) {
                                                API.editCommunicationData( commRwFlags, confirm_code )
                                                    .then( function( result ) {
                                                        if( OTP ) {
                                                            OTP.setSuccess();
                                                        }
                                                        unbind();
                                                        RCC.clearCommunicationConfirmUnbind();
                                                        jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
                                                        if( RCM ) {
                                                            RCM.close();
                                                        }
                                                        if( !hasEmailContact && communicationDataForm.elements.allowEmail ) {
                                                            communicationDataForm.elements.allowEmail.checked = false;
                                                        }
                                                        communicationDataForm.submit();
                                                    } )
                                                    .catch( function( err ) {
                                                        if( OTP ) {
                                                            OTP.setError();
                                                        }
                                                        const errStr = String( err );
                                                        const commWrongCodeHint = commRwCodeContact.channel === 'email'
                                                            ? 'Код введён неверно. На email отправлен новый код — введите цифры из последнего письма.'
                                                            : 'Код введён неверно. На телефон отправлен новый код подтверждения — введите цифры из последнего SMS.';
                                                        if( RCC.handleWrongConfirmCode( {
                                                            err: err,
                                                            form: confirmCodeForm,
                                                            resend: function() {
                                                                return API.sendCode( commRwCodeContact.id );
                                                            },
                                                            messageAfterResend: commWrongCodeHint,
                                                            when: function() {
                                                                return !!( commRwCodeContact && commRwCodeContact.id );
                                                            }
                                                        } ) ) {
                                                            return;
                                                        }
                                                        RCC.showConfirmModalError( confirmCodeForm, errStr + ' Введите тот же код ещё раз или нажмите «Получить код повторно».' );
                                                        if( OTP ) {
                                                            OTP.setAwaiting();
                                                        }
                                                    } );
                                            } )
                                        );
                                    } else {
                                        if( OTP ) {
                                            OTP.reset();
                                        }
                                        rwCommUnblock();
                                        clearCommunicationFormNotices( communicationDataForm );
                                        communicationDataForm.insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide rightway-communication-notice">' + sendCodeResponse.data + '</p>' );
                                    }
                                } );
                            } else {
                                rwCommUnblock();
                                communicationDataForm.submit();
                            }
                        } else {
                            rwCommUnblock();
                            const commFormErr = document.querySelector( '#woocommerce-edit-communication' );
                            if( commFormErr ) {
                                clearCommunicationFormNotices( commFormErr );
                                commFormErr.insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide rightway-communication-notice">' + data.data + '</p>' );
                            }
                        }
                    } )
                    .catch( err => {
                        rwCommUnblock();
                        const commFormErr = document.querySelector( '#woocommerce-edit-communication' );
                        if( commFormErr ) {
                            clearCommunicationFormNotices( commFormErr );
                            commFormErr.insertAdjacentHTML( 'beforeend', '<p class="form-row form-row-wide rightway-communication-notice">' + err + '</p>' );
                        }
                    } );

            } );
        }


        // Сохранение email в учётной записи WooCommerce; синхронизация контакта email с RightWay — на странице billing.
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
                e.preventDefault();

                // Получаем customerId, сохраненный на сайте для текущего пользователя
                if( rightway.customerId ) {
                    // Проверяем, есть ли у данного кастомера в RW контакт с email
                    // по запросу GET /customers/{customerId}/contacts
                    API.getCustomerContacts()
                        .then( ( data ) => {
                            if( data.data ) {
                                let noRwUpdate = false;
                                /* contactsArray = JSON.parse(data.data); */
                                contactsArray = data.data;
                                const rwBillingEmail = ( typeof rightway.billing_email_saved === 'string' ) ? rightway.billing_email_saved.trim() : '';
                                contactsArray.forEach( ( contact ) => {
                                    if( contact.value.indexOf( '@' ) >= 0 ) {
                                        if( rwBillingEmail !== '' && contact.value === rwBillingEmail ) {
                                            noRwUpdate = true;
                                        }
                                    }
                                } );
                                if( noRwUpdate ) {
                                    accountDataForm.submit();
                                    return;
                                }
                                /* Создание/обновление email-контакта в RightWay выполняется при сохранении «Данные покупателя» (billing). */
                                accountDataForm.submit();
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
    } );
} )( jQuery );
