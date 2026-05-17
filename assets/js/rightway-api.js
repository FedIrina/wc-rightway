/**
 * RightWay: AJAX к admin-ajax (действия rightway_*).
 * Шаг 1.1: отправка и проверка кода подтверждения.
 * Шаг 1.2: чтение карты, контактов, покупателей, карт.
 * Шаг 1.3: запись коммуникаций, контактов, покупателя, анкеты.
 */
( function( global ) {
    'use strict';

    /**
     * @param {string} action
     * @param {Object<string, string|number|boolean|undefined|null>} [fields]
     * @returns {Promise<{success: boolean, data?: *}>}
     */
    function rwPostOrReject( action, fields ) {
        return rightwayAjaxPost( action, fields )
            .then( function( ajaxResponse ) {
                if( ajaxResponse.success ) {
                    return ajaxResponse;
                }
                return Promise.reject( ajaxResponse.data );
            } );
    }

    /**
     * @param {string} contactId
     * @returns {Promise<{success: boolean, data?: *}>}
     */
    function sendCode( contactId ) {
        return rightwayAjaxPost( 'rightway_send_confirm_code', { contactId: contactId } );
    }

    /**
     * @param {string} contactValue
     * @returns {Promise<{success: boolean, data?: *}>}
     */
    function sendContactCode( contactValue ) {
        return rightwayAjaxPost( 'rightway_send_confirm_contact_code', { contactValue: contactValue } );
    }

    /**
     * @param {string} contactValue
     * @param {string} confirmCode
     * @returns {Promise<string>}
     */
    function getRwToken( contactValue, confirmCode ) {
        return rightwayAjaxPost( 'rightway_get_contact_token', {
            contactValue: contactValue,
            confirmCode: confirmCode
        } )
            .then( function( ajaxResponse ) {
                if( ajaxResponse.success === true ) {
                    return ajaxResponse.data;
                }
                if( ajaxResponse.success === false ) {
                    return Promise.reject( ajaxResponse.data );
                }
            } )
            .catch( function( error ) {
                return Promise.reject( 'Не удалось связаться с сервером. Попробуйте зайти позже.' + error );
            } );
    }

    /**
     * @returns {Promise<{success: boolean, data: string}>}
     */
    function getCardSummary() {
        return rightwayAjaxPost( 'rightway_get_card_summary', {} );
    }

    /**
     * @param {string} [customerIdOverride]
     * @returns {Promise<{success: boolean, data?: Array}>}
     */
    function getCustomerContacts( customerIdOverride ) {
        return rightwayAjaxPost( 'rightway_get_customer_contacts', {
            customerId: customerIdOverride || rightway.customerId
        } );
    }

    /**
     * @param {'phone'|'email'} contact
     * @param {string} value
     * @param {string} [rwToken]
     * @returns {Promise<{success: boolean, data?: Array}>}
     */
    function getCustomersContacts( contact, value, rwToken ) {
        const fields = {};
        fields[ contact ] = value;
        fields.rwToken = rwToken;
        return rightwayAjaxPost( 'rightway_get_customers', fields );
    }

    /**
     * @param {string} customerId
     * @returns {Promise<{success: boolean, data: string}>}
     */
    function getRwCustomerCards( customerId ) {
        return rwPostOrReject( 'rightway_get_customer_cards', { customerId: customerId } );
    }

    /**
     * @param {{ allowSms: boolean, allowEmail: boolean, allowMarketingCommunication: boolean }} commFlags
     * @param {string} confirmCode
     * @returns {Promise<{success: boolean, data?: *}>}
     */
    function editCommunicationData( commFlags, confirmCode ) {
        return rwPostOrReject( 'rightway_edit_communication_data', {
            allowSms: commFlags.allowSms,
            allowEmail: commFlags.allowEmail,
            allowMarketingCommunication: commFlags.allowMarketingCommunication,
            confirmCode: confirmCode
        } );
    }

    /**
     * @param {'phone'|'email'} contact
     * @param {string} value
     * @param {string} contactId
     * @param {string} token
     * @returns {Promise<void>}
     */
    function editContactData( contact, value, contactId, token ) {
        const fields = {
            contactId: contactId,
            token: token
        };
        if( contact === 'phone' ) {
            fields.billing_phone = formatPhone( value );
        }
        if( contact === 'email' ) {
            fields.email = value;
        }
        return rwPostOrReject( 'rightway_edit_contact_data', fields ).then( function() {} );
    }

    /**
     * @param {'phone'|'email'} contact
     * @param {string} value
     * @param {string} firstName
     * @param {string} lastName
     * @param {string} token
     * @param {string} birthDate
     * @param {string} gender
     * @returns {Promise<{success: boolean, data?: *}>}
     */
    function createCustomer( contact, value, firstName, lastName, token, birthDate, gender ) {
        return rwPostOrReject( 'rightway_create_customer', {
            firstName: firstName,
            lastName: lastName,
            contact: contact,
            value: value,
            token: token,
            birthDate: birthDate,
            gender: gender
        } );
    }

    /**
     * @param {string} value
     * @param {string} token
     * @returns {Promise<{success: boolean, data?: *}>}
     */
    function createContact( value, token ) {
        return rwPostOrReject( 'rightway_create_contact', {
            value: value,
            token: token
        } );
    }

    /**
     * @param {string} customerId
     * @param {{ billing_first_name: string, billing_last_name: string, birthDate: string, gender: string }} persFields
     * @returns {Promise<{success: boolean, data?: *}>}
     */
    function editCustomerData( customerId, persFields ) {
        return rwPostOrReject( 'rightway_edit_customer_data', {
            billing_first_name: persFields.billing_first_name,
            billing_last_name: persFields.billing_last_name,
            birthDate: persFields.birthDate,
            gender: persFields.gender,
            customerId: customerId
        } );
    }

    global.RightwayApi = {
        sendCode: sendCode,
        sendContactCode: sendContactCode,
        getRwToken: getRwToken,
        getCardSummary: getCardSummary,
        getCustomerContacts: getCustomerContacts,
        getCustomersContacts: getCustomersContacts,
        getRwCustomerCards: getRwCustomerCards,
        editCommunicationData: editCommunicationData,
        editContactData: editContactData,
        createCustomer: createCustomer,
        createContact: createContact,
        editCustomerData: editCustomerData
    };
}( typeof window !== 'undefined' ? window : this ) );
