/**
 * RightWay: ввод кода в модалке и единая ссылка на «снять слушатель» для сценариев,
 * где модалку закрывают без успеха (Fancybox afterClose) или вешают новый обработчик.
 */
( function( global ) {
    'use strict';

    var communicationConfirmUnbind = null;

    /**
     * При полном вводе кода (codeIsEntered), если форма валидна — handler( confirm_code, unbind ).
     * Вызовите unbind() при успешном завершении сценария; при ошибке не вызывайте — можно ввести код снова.
     * @param {HTMLFormElement} confirmCodeForm
     * @param {function(string, function(): void): void} handler
     * @returns {function(): void} unbind
     */
    function onEnterConfirmCode( confirmCodeForm, handler ) {
        function unbindEnterConfirmCode() {
            confirmCodeForm.removeEventListener( 'codeIsEntered', onCodeEntered );
        }
        function onCodeEntered() {
            if( !confirmCodeForm.checkValidity() ) {
                confirmCodeForm.reportValidity();
                return;
            }
            handler( confirmCodeForm.elements.confirm_code.value, unbindEnterConfirmCode );
        }
        confirmCodeForm.addEventListener( 'codeIsEntered', onCodeEntered );
        return unbindEnterConfirmCode;
    }

    /**
     * Dispose-паттерн: снять предыдущий unbind (если был), сохранить новый или null.
     */
    function replaceCommunicationConfirmUnbind( newUnbind ) {
        if( typeof communicationConfirmUnbind === 'function' ) {
            communicationConfirmUnbind();
        }
        communicationConfirmUnbind = ( typeof newUnbind === 'function' ) ? newUnbind : null;
    }

    /**
     * После успешного unbind() внутри handler только обнулить ссылку (listener уже снят).
     */
    function clearCommunicationConfirmUnbind() {
        communicationConfirmUnbind = null;
    }

    /**
     * Ошибка в модалке ввода кода: предыдущие сообщения, сброс полей, фокус, снятие blockUI.
     * @param {HTMLFormElement|null} confirmCodeForm
     * @param {string} text
     * @returns {void}
     */
    function showConfirmModalError( confirmCodeForm, text ) {
        if( !confirmCodeForm ) {
            return;
        }
        confirmCodeForm.querySelectorAll( '.rightway-confirm-modal-error' ).forEach( function( node ) {
            node.remove();
        } );
        const msg = document.createElement( 'p' );
        msg.className = 'form-row form-row-wide woocommerce-error rightway-confirm-modal-error';
        msg.textContent = text;
        confirmCodeForm.appendChild( msg );
        confirmCodeForm.reset();
        const firstDigit = confirmCodeForm.querySelector( '.confirm-code-input' );
        if( firstDigit ) {
            firstDigit.focus();
        }
        if( typeof jQuery !== 'undefined' ) {
            jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
        }
    }

    var WRONG_CODE_RETRY_SUFFIX = ' Нажмите «Получить код повторно».';

    /**
     * Ошибка «Неверный» код: повторная отправка, сообщение в модалке, OTP → awaitingInput.
     * @param {{ err: *, form: HTMLFormElement|null, resend: function(): Promise<{success?: boolean}>, messageAfterResend: string, when?: function(): boolean }} options
     * @returns {boolean} true — обработано, вызывающий код может сделать return
     */
    function handleWrongConfirmCode( options ) {
        const OTP = global.RightwayOtp;
        const errStr = String( options.err );
        if( errStr.indexOf( 'Неверный' ) === -1 || !options.form || typeof options.resend !== 'function' ) {
            return false;
        }
        if( typeof options.when === 'function' && !options.when() ) {
            return false;
        }
        if( OTP ) {
            OTP.setError();
            OTP.setSending();
        }
        options.resend().then( function( sendCodeResponse ) {
            if( sendCodeResponse && sendCodeResponse.success ) {
                showConfirmModalError( options.form, options.messageAfterResend );
            } else {
                showConfirmModalError( options.form, errStr + WRONG_CODE_RETRY_SUFFIX );
            }
            if( OTP ) {
                OTP.setAwaiting();
            }
        } ).catch( function() {
            showConfirmModalError( options.form, errStr + WRONG_CODE_RETRY_SUFFIX );
            if( OTP ) {
                OTP.setAwaiting();
            }
        } );
        return true;
    }

    global.RightwayConfirmCode = {
        onEnterConfirmCode: onEnterConfirmCode,
        replaceCommunicationConfirmUnbind: replaceCommunicationConfirmUnbind,
        clearCommunicationConfirmUnbind: clearCommunicationConfirmUnbind,
        showConfirmModalError: showConfirmModalError,
        handleWrongConfirmCode: handleWrongConfirmCode
    };
}( typeof window !== 'undefined' ? window : this ) );
