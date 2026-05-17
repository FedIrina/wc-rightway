/**
 * RightWay OTP: явные этапы сценария (конечный автомат).
 * Используется: коммуникации ЛК, billing (email/телефон).
 * Не используется / не тестировать: checkout «Создать аккаунт» + код (CHECKOUT_CREATE_ACCOUNT_OTP_ENABLED в rightway-checkout-otp.js).
 */
( function( global ) {
    'use strict';

    var S = {
        IDLE: 'idle',
        SENDING: 'sendingCode',
        AWAITING: 'awaitingInput',
        VERIFYING: 'verifying',
        SUCCESS: 'success',
        ERROR: 'error'
    };

    var state = S.IDLE;

    function set( next ) {
        state = next;
    }

    global.RightwayOtp = {
        S: S,
        get: function() {
            return state;
        },
        isIdle: function() {
            return state === S.IDLE;
        },
        canSendCode: function() {
            return state === S.IDLE || state === S.AWAITING || state === S.ERROR;
        },
        canVerify: function() {
            return state === S.AWAITING || state === S.ERROR;
        },
        /**
         * Переход к проверке кода (ввод всех цифр). false — повторный вызов или неверный этап.
         * @returns {boolean}
         */
        ensureVerifying: function() {
            if( state === S.VERIFYING ) {
                return false;
            }
            if( state !== S.AWAITING && state !== S.ERROR ) {
                return false;
            }
            set( S.VERIFYING );
            return true;
        },
        setSending: function() {
            set( S.SENDING );
        },
        setAwaiting: function() {
            set( S.AWAITING );
        },
        setVerifying: function() {
            set( S.VERIFYING );
        },
        setSuccess: function() {
            set( S.SUCCESS );
        },
        setError: function() {
            set( S.ERROR );
        },
        reset: function() {
            set( S.IDLE );
        }
    };
}( typeof window !== 'undefined' ? window : this ) );
