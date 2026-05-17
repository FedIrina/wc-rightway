'use strict';

/**
 * POST FormData на admin-ajax и разбор ответа как JSON.
 * @param {FormData} formData
 * @returns {Promise<any>}
 */
function rightwayFetchJson( formData ) {
    return fetch( rightway.ajax_url, {
        method: 'POST',
        body: formData
    } ).then( function( response ) {
        return response.json();
    } );
}

/**
 * Настройки jQuery BlockUI: полупрозрачный оверлей + спиннер и текст (по духу ожидания на checkout WooCommerce).
 * @returns {{message: string, css: Object, overlayCSS: Object, fadeIn: number, fadeOut: number}}
 */
/**
 * Снимает blockUI «обмен с программой лояльности» и processing с формы кода (без submit страницы).
 * @returns {void}
 */
function rightwayReleaseRwWaitOverlays() {
    if( typeof jQuery === 'undefined' ) {
        return;
    }
    jQuery( '.rightway-rw-waiting' ).removeClass( 'rightway-rw-waiting' ).unblock();
    jQuery( '.woocommerce-address-fields.processing, .cabinet_section.processing, form.cabinet_data.processing' ).removeClass( 'processing' ).unblock();
    jQuery( '#enter-confirm-code' ).removeClass( 'processing' ).unblock();
}

/**
 * Контейнер для blockUI при сохранении billing в ЛК (адрес / cabinet).
 * @param {HTMLFormElement|null} addressDataForm — `form.cabinet_data`.
 * @returns {jQuery}
 */
function resolveBillingRwBlock( addressDataForm ) {
    let $billingRwBlock = jQuery( '.woocommerce-address-fields' );
    if( !$billingRwBlock.length ) {
        $billingRwBlock = jQuery( '.cabinet_section' );
    }
    if( !$billingRwBlock.length && addressDataForm && addressDataForm.closest ) {
        const cabWrap = addressDataForm.closest( '.cabinet_section' );
        if( cabWrap ) {
            $billingRwBlock = jQuery( cabWrap );
        }
    }
    if( !$billingRwBlock.length && addressDataForm ) {
        $billingRwBlock = jQuery( addressDataForm );
    }
    return $billingRwBlock;
}

/**
 * Текстовое сообщение об ошибке под формой billing (без HTML из строки ошибки).
 * @param {HTMLFormElement|null} form
 * @param {string} message
 * @param {string} [extraClass]
 * @returns {void}
 */
/**
 * Первая незаблокированная карта из ответа RW (`get_customer_cards`).
 * @param {string|Array<{id?: *, number?: *, isBlocked?: boolean}>} cardsData JSON-строка или массив карт.
 * @returns {{ cardId: string, cardNumber: string }}
 */
function pickFirstActiveRwCard( cardsData ) {
    let cardsArray = cardsData;
    if( typeof cardsData === 'string' ) {
        cardsArray = JSON.parse( cardsData );
    }
    let cardId = '';
    let cardNumber = '';
    if( !cardsArray || typeof cardsArray !== 'object' ) {
        return { cardId: cardId, cardNumber: cardNumber };
    }
    for( let key in cardsArray ) {
        if( !Object.prototype.hasOwnProperty.call( cardsArray, key ) ) {
            continue;
        }
        const card = cardsArray[ key ];
        if( card && !card.isBlocked ) {
            cardId = card.id != null ? String( card.id ) : '';
            cardNumber = card.number != null ? String( card.number ) : '';
        }
    }
    return { cardId: cardId, cardNumber: cardNumber };
}

/**
 * Скрытые поля привязки покупателя RW к форме WooCommerce.
 * @param {HTMLFormElement|null} form
 * @param {{ cardId?: string, cardNumber?: string, customerId?: string }} fields
 * @returns {void}
 */
function appendRwHiddenFields( form, fields ) {
    if( !form || !fields ) {
        return;
    }
    [ 'cardId', 'cardNumber', 'customerId' ].forEach( function( name ) {
        const val = fields[ name ];
        if( val === undefined || val === null || val === '' ) {
            return;
        }
        const input = document.createElement( 'input' );
        input.type = 'hidden';
        input.name = name;
        input.value = String( val );
        form.appendChild( input );
    } );
}

/**
 * Преобразование ошибки в строку для отображения пользователю.
 * @param {*} err
 * @returns {string}
 */
function rightwayUserErrorMessage( err ) {
    if( err == null || err === '' ) {
        return '';
    }
    if( typeof err === 'string' ) {
        return err;
    }
    if( err instanceof Error && err.message ) {
        return err.message;
    }
    return String( err );
}

function rightwayShowBillingFormError( form, message, extraClass ) {
    if( !form ) {
        return;
    }
    const p = document.createElement( 'p' );
    p.className = 'form-row form-row-wide' + ( extraClass ? ' ' + extraClass : '' );
    p.textContent = rightwayUserErrorMessage( message );
    form.appendChild( p );
}

function rightwayRwAwaitBlockOptions() {
    return {
        message: '<div class="rightway-rw-await" role="status" aria-live="polite"><span class="rightway-rw-await__spinner" aria-hidden="true"></span><p class="rightway-rw-await__text">Подождите, идёт обмен с программой лояльности…</p></div>',
        css: {
            border: 'none',
            padding: '20px 28px',
            width: 'auto',
            backgroundColor: '#fff',
            boxShadow: '0 2px 10px rgba(0,0,0,.12)',
            borderRadius: '4px',
            cursor: 'wait',
            textAlign: 'center'
        },
        overlayCSS: {
            background: '#fff',
            opacity: 0.72,
            cursor: 'wait'
        },
        fadeIn: 80,
        fadeOut: 80
    };
}

/**
 * Нормализация телефона: только цифры и ведущий «+7».
 * @param {string} phone
 * @returns {string}
 */
function formatPhone( phone ) {
    let cleaned = phone.replace( /[^\d+]/g, '' );

    let parts = cleaned.split( '+' );
    if( parts.length > 1 ) {
        cleaned = '+' + parts.filter( function( p ) { return p; } ).join( '' );
    }

    cleaned = cleaned.replace( /^(\+?)(.*)$/, function( match, plus, rest ) {
        return plus + rest.replace( /\+/g, '' );
    } );

    let digitsOnly = cleaned.replace( /\D/g, '' );

    if( digitsOnly.startsWith( '7' ) ) {
        return '+' + digitsOnly;
    }
    return '+7' + digitsOnly;
}

/**
 * POST на admin-ajax: action, nonce_code и плоские поля (строки / числа / boolean → строка).
 * @param {string} action Имя действия WordPress (например rightway_get_contact_token).
 * @param {Object<string, string|number|boolean|undefined|null>} fields Дополнительные поля FormData.
 * @returns {Promise<any>}
 */
function rightwayAjaxPost( action, fields ) {
    const fd = new FormData();
    fd.append( 'action', action );
    fd.append( 'nonce_code', rightway.nonce_code );
    if( fields && typeof fields === 'object' ) {
        Object.keys( fields ).forEach( function( key ) {
            const val = fields[ key ];
            if( val === undefined || val === null ) {
                return;
            }
            if( typeof val === 'boolean' ) {
                fd.append( key, val ? 'true' : 'false' );
            } else {
                fd.append( key, String( val ) );
            }
        } );
    }
    return rightwayFetchJson( fd );
}
