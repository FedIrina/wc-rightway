/**
 * Модалка ввода кода подтверждения (Fancybox, поля, таймер, «отправить снова»).
 * Сценарии RW подключаются снаружи через RightwayConfirmCode.onEnterConfirmCode.
 */
( function( global ) {
    'use strict';

    /** @type {{ active: boolean, timerId: number|null, sendAgain: HTMLElement|null, onSendAgainClick: function(): void|null, onFormPaste: function(ClipboardEvent): void|null, fieldBindings: Array<{ el: HTMLElement, onKeydown: function(KeyboardEvent): void, onInput: function(): void }> }|null} */
    var confirmModalSession = null;

    /** @type {*} экземпляр $.fancybox.open */
    var fancyboxInstance = null;

    /**
     * Закрытие крестиком / ESC / кликом по фону запрещено, пока идёт проверка кода (OTP verifying).
     * @returns {boolean}
     */
    function isOtpCloseLocked() {
        const OTP = global.RightwayOtp;
        return !!( OTP && OTP.get() === OTP.S.VERIFYING );
    }

    /**
     * @returns {void}
     */
    function teardownConfirmCodeModalSession() {
        if( !confirmModalSession ) {
            return;
        }
        const session = confirmModalSession;
        session.active = false;
        if( session.timerId != null ) {
            clearTimeout( session.timerId );
            session.timerId = null;
        }
        if( session.sendAgain && session.onSendAgainClick ) {
            session.sendAgain.removeEventListener( 'click', session.onSendAgainClick );
        }
        const confirmCodeFormEl = document.querySelector( 'form#enter-confirm-code' );
        if( confirmCodeFormEl && session.onFormPaste ) {
            confirmCodeFormEl.removeEventListener( 'paste', session.onFormPaste );
        }
        session.fieldBindings.forEach( function( binding ) {
            binding.el.removeEventListener( 'keydown', binding.onKeydown );
            binding.el.removeEventListener( 'input', binding.onInput );
        } );
        if( fancyboxInstance && fancyboxInstance.$refs && fancyboxInstance.$refs.container ) {
            fancyboxInstance.$refs.container.off( 'click.rightwayOtpLock' );
        }
        confirmModalSession = null;
    }

    /**
     * @returns {void}
     */
    function close() {
        if( fancyboxInstance ) {
            fancyboxInstance.close();
            return;
        }
        if( jQuery.fancybox ) {
            jQuery.fancybox.close();
        }
    }

    /**
     * @param {{ channel?: string, address?: string, resend?: function(): Promise<{success: boolean, data?: *}> } }} options
     * @returns {void}
     */
    function open( options ) {
        options = options || {};
        let channel = options.channel != null ? String( options.channel ) : '';
        const address = options.address != null ? String( options.address ) : '';
        const resend = options.resend;
        const OTP = global.RightwayOtp;
        const RCC = global.RightwayConfirmCode;

        teardownConfirmCodeModalSession();

        if( !channel ) {
            const communicationDataForm = document.querySelector( '#woocommerce-edit-communication' );
            if( communicationDataForm && communicationDataForm.elements.allowEmail && communicationDataForm.elements.allowEmail.checked ) {
                channel = 'Email';
            }
            if( communicationDataForm && communicationDataForm.elements.allowSms && communicationDataForm.elements.allowSms.checked ) {
                channel = channel ? ( channel + ' / телефон' ) : 'телефон';
            }
        }

        const contactSent = document.querySelector( '.o-sms-auth-modal-phone-sent-notifier__text .contact-sent' );
        if( contactSent ) {
            contactSent.innerHTML = [ channel, address ].filter( function( part ) {
                return part != null && String( part ).trim() !== '';
            } ).join( ' ' );
        }

        if( typeof jQuery === 'undefined' || !jQuery.fancybox ) {
            return;
        }

        fancyboxInstance = jQuery.fancybox.open(
            {
                src: '#confirm-modal-template',
                type: 'inline'
            },
            {
                beforeClose: function() {
                    if( isOtpCloseLocked() ) {
                        return false;
                    }
                },
                clickOutside: function() {
                    return !isOtpCloseLocked();
                },
                clickSlide: function() {
                    return !isOtpCloseLocked() ? 'close' : false;
                },
                afterClose: function() {
                    fancyboxInstance = null;
                    teardownConfirmCodeModalSession();
                    OTP.reset();
                    rightwayReleaseRwWaitOverlays();
                    RCC.replaceCommunicationConfirmUnbind( null );
                }
            }
        );

        if( fancyboxInstance && fancyboxInstance.$refs && fancyboxInstance.$refs.container ) {
            fancyboxInstance.$refs.container.on( 'click.rightwayOtpLock', '.fancybox-button--close', function( e ) {
                if( isOtpCloseLocked() ) {
                    e.stopImmediatePropagation();
                    return false;
                }
            } );
        }

        const sendAgain = document.querySelector( '#send-again' );
        const timerBlock = document.querySelector( '#timer-block' );
        const fields = document.querySelectorAll( '.confirm-code-input' );
        const fieldsArray = [ ...fields ];
        const confirmCodeForm = document.querySelector( 'form#enter-confirm-code' );
        if( !confirmCodeForm ) {
            return;
        }

        const session = {
            active: true,
            timerId: null,
            sendAgain: sendAgain,
            onSendAgainClick: null,
            onFormPaste: null,
            fieldBindings: []
        };
        confirmModalSession = session;

        rightwayReleaseRwWaitOverlays();

        if( timerBlock ) {
            timerBlock.style.display = 'block';
        }
        if( sendAgain ) {
            sendAgain.style.display = 'none';
        }

        jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
        confirmCodeForm.reset();
        confirmCodeForm.querySelectorAll( 'p.form-row.form-row-wide' ).forEach( function( p ) {
            var st = p.getAttribute( 'style' ) || '';
            if( st.indexOf( 'display' ) !== -1 && st.indexOf( 'none' ) !== -1 ) {
                return;
            }
            p.remove();
        } );
        if( fields[ 0 ] ) {
            fields[ 0 ].focus();
        }

        let mins = 1;
        let secs = mins * 60;

        function decrement() {
            if( !session.active ) {
                return;
            }
            let currentMinutes = Math.floor( secs / 60 );
            let currentSeconds = secs % 60;
            if( currentSeconds <= 9 ) {
                currentSeconds = '0' + currentSeconds;
            }
            secs--;
            const timer = document.getElementById( 'timerText' );
            if( !timer ) {
                return;
            }
            timer.innerHTML = currentMinutes + ':' + currentSeconds;
            if( secs !== -1 ) {
                scheduleDecrementTick();
            } else {
                session.timerId = null;
                if( timerBlock ) {
                    timerBlock.style.display = 'none';
                }
                if( sendAgain ) {
                    sendAgain.style.display = 'block';
                }
            }
        }

        function scheduleDecrementTick() {
            if( !session.active ) {
                return;
            }
            session.timerId = setTimeout( decrement, 1000 );
        }

        /**
         * @returns {void}
         */
        function syncConfirmCodeHidden() {
            confirmCodeForm.elements.confirm_code.value = fieldsArray.map( function( field ) {
                return field.value;
            } ).join( '' );
        }

        /**
         * @returns {boolean}
         */
        function allOtpFieldsFilled() {
            return fieldsArray.every( function( field ) {
                return field.value.length > 0;
            } );
        }

        /**
         * @param {HTMLElement} sourceEl
         * @returns {void}
         */
        function submitOtpIfComplete( sourceEl ) {
            if( !allOtpFieldsFilled() ) {
                return;
            }
            if( OTP && !OTP.ensureVerifying() ) {
                return;
            }
            jQuery( '#enter-confirm-code' ).addClass( 'processing' ).block( {
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            } );
            sourceEl.dispatchEvent( new Event( 'codeIsEntered', { bubbles: true } ) );
        }

        /**
         * Вставка из буфера: строка → только цифры → по символу в поля слева направо.
         * @param {string} rawText
         * @returns {boolean} true, если в буфере были цифры
         */
        function applyPastedOtpText( rawText ) {
            const digitsOnly = String( rawText ).replace( /[^0-9]/g, '' );
            if( !digitsOnly ) {
                return false;
            }
            const digitChars = digitsOnly.split( '' );
            fieldsArray.forEach( function( field, i ) {
                field.value = digitChars[ i ] != null ? digitChars[ i ] : '';
            } );
            syncConfirmCodeHidden();
            const focusIndex = Math.min( digitChars.length, fieldsArray.length - 1 );
            fieldsArray[ focusIndex ].focus();
            if( allOtpFieldsFilled() ) {
                submitOtpIfComplete( fieldsArray[ fieldsArray.length - 1 ] );
            }
            return true;
        }

        session.onFormPaste = function( e ) {
            if( !session.active ) {
                return;
            }
            if( OTP && OTP.get() === OTP.S.VERIFYING ) {
                e.preventDefault();
                return;
            }
            const clipboard = e.clipboardData || window.clipboardData;
            if( !clipboard ) {
                return;
            }
            const pastedText = clipboard.getData( 'text' ) || clipboard.getData( 'text/plain' ) || '';
            if( !applyPastedOtpText( pastedText ) ) {
                return;
            }
            e.preventDefault();
            const errblock = confirmCodeForm.querySelector( '.woocommerce-error' );
            if( errblock ) {
                errblock.remove();
            }
        };
        confirmCodeForm.addEventListener( 'paste', session.onFormPaste );

        fields.forEach( function( el, index ) {
            function clearOtpErrorNotice() {
                const errblock = confirmCodeForm.querySelector( '.woocommerce-error' );
                if( errblock ) {
                    errblock.remove();
                }
            }

            function onKeydown( e ) {
                if( !session.active ) {
                    return;
                }
                if( OTP && OTP.get() === OTP.S.VERIFYING ) {
                    e.preventDefault();
                    return;
                }
                if( e.key === 'Backspace' || e.key === 'Delete' ) {
                    e.preventDefault();
                    clearOtpErrorNotice();
                    if( el.value ) {
                        el.value = '';
                        syncConfirmCodeHidden();
                        return;
                    }
                    if( fields[ index - 1 ] ) {
                        fields[ index - 1 ].focus();
                        fields[ index - 1 ].value = '';
                        syncConfirmCodeHidden();
                    }
                    return;
                }
                if( e.key === 'ArrowLeft' && fields[ index - 1 ] ) {
                    e.preventDefault();
                    fields[ index - 1 ].focus();
                    return;
                }
                if( e.key === 'ArrowRight' && fields[ index + 1 ] ) {
                    e.preventDefault();
                    fields[ index + 1 ].focus();
                }
            }

            function onInput() {
                if( !session.active ) {
                    return;
                }
                if( OTP && OTP.get() === OTP.S.VERIFYING ) {
                    return;
                }
                clearOtpErrorNotice();
                const digit = el.value.replace( /[^0-9]/g, '' ).slice( -1 );
                if( !digit ) {
                    el.value = '';
                    syncConfirmCodeHidden();
                    return;
                }
                el.value = digit;
                syncConfirmCodeHidden();
                if( fields[ index + 1 ] ) {
                    fields[ index + 1 ].focus();
                }
                submitOtpIfComplete( el );
            }

            el.addEventListener( 'keydown', onKeydown );
            el.addEventListener( 'input', onInput );
            session.fieldBindings.push( { el: el, onKeydown: onKeydown, onInput: onInput } );
        } );

        scheduleDecrementTick();

        session.onSendAgainClick = function() {
            if( !session.active || typeof resend !== 'function' ) {
                return;
            }
            if( OTP && !OTP.canSendCode() ) {
                return;
            }
            confirmCodeForm.reset();
            let errblock = confirmCodeForm.querySelector( '.woocommerce-error' );
            if( errblock ) {
                errblock.remove();
            }
            if( OTP ) {
                OTP.setSending();
            }
            resend().then( function( sendCodeResponse ) {
                if( !session.active ) {
                    return;
                }
                if( sendCodeResponse && sendCodeResponse.success ) {
                    if( OTP ) {
                        OTP.setAwaiting();
                    }
                    if( timerBlock ) {
                        timerBlock.style.display = 'block';
                    }
                    if( sendAgain ) {
                        sendAgain.style.display = 'none';
                    }
                    mins = 1;
                    secs = mins * 60;
                    if( session.timerId != null ) {
                        clearTimeout( session.timerId );
                    }
                    scheduleDecrementTick();
                } else if( OTP ) {
                    OTP.setAwaiting();
                }
            } );
        };
        if( sendAgain ) {
            sendAgain.addEventListener( 'click', session.onSendAgainClick );
        }

        if( OTP ) {
            OTP.setAwaiting();
        }
    }

    global.RightwayConfirmModal = {
        open: open,
        close: close,
        isOpen: function() {
            return !!fancyboxInstance;
        }
    };
}( typeof window !== 'undefined' ? window : this ) );
