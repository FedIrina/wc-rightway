'use strict';

( function( $ ) {
    function initCheckoutBonusesAndPayment( $ ) {
        const billingField = document.querySelector( '#billing_phone' );
        const bonusesBlock = document.querySelector( '#bonuses' );
        let useBonusesCheckbox = document.querySelector( '#billing_use_bonuses' );
        let addBonusesCheckbox = document.querySelector( '#billing_add_bonuses' );
        let paymentMethod = '';

        /** Читает выбранный метод оплаты в DOM и записывает в замыкание `paymentMethod`.
         * @returns {void}
         */
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

        /**
         * Текущий выбранный на странице способ оплаты WooCommerce.
         * @returns {string}
         */
        const getCurrentPaymentMethod = function() {
            const paymentInput = document.querySelector( 'input[name="payment_method"]:checked' );
            if( paymentInput ) {
                return paymentInput.value;
            }
            return '';
        };

        /**
         * Запрос скидки по акции для гостя без `rightway.customerId` (учитывает текущий способ оплаты).
         * @returns {Promise<void>} при успехе обновляет `#rw_discount`; при ошибке сети или ответа — `reject`.
         */
        const getDiscount = function() {
            return new Promise( function( resolve, reject ) {
                // Обновляем paymentMethod перед использованием
                const paymentInput = document.querySelector( 'input[name="payment_method"]:checked' );
                if( paymentInput ) {
                    paymentMethod = paymentInput.value;
                }

                let data = new FormData();
                data.append( 'action', 'rightway_calculateActionDiscount' );
                data.append( 'nonce_code', rightway.nonce_code );
                if( paymentMethod ) {
                    data.append( 'payment_method', paymentMethod );
                }
                rightwayFetchJson( data )
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

        /**
         * Реакция на смену способа оплаты: для участника RW — `bonusesAction`, иначе пересчёт скидки и `update_checkout`.
         * @returns {void}
         */
        const handlePaymentMethodChange = function() {
            const newPaymentMethod = getCurrentPaymentMethod();

            // Если метод изменился или это первая инициализация
            if( newPaymentMethod !== currentPaymentMethod || !paymentMethodInitialized ) {
                currentPaymentMethod = newPaymentMethod;
                paymentMethod = newPaymentMethod; // Обновляем глобальную переменную
                paymentMethodInitialized = true;

                // Вызываем соответствующую функцию в зависимости от наличия customerId
                if( rightway && rightway.customerId ) {
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

        /**
         * AJAX `rightway_get_active_bonuses`: обновляет подписи бонусов и скрытые поля формы checkout.
         * @returns {Promise<void>}
         */
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
                data.append( 'nonce_code', rightway.nonce_code );
                data.append( 'billing_phone', formatPhone( billingField.value ) );
                data.append( 'use_bonuses', useBonusesCheckbox.checked );
                data.append( 'add_bonuses', addBonusesCheckbox.checked );
                if( paymentMethod ) {
                    data.append( 'payment_method', paymentMethod );
                }
                rightwayFetchJson( data )
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
        };
        /*         $( '#billing_phone' ).on( 'blur', function() {
                getBonuses();
            } ); */
        $( '.woocommerce-address-fields #gender' ).selectWoo(); // Стилизация выпадающего списка выбора пола

        /**
         * По чекбоксам бонусов выставляет скрытое `#rw_card_operation`, запрашивает бонусы и дергает `update_checkout`.
         * @param {Event} [e] — необязательно (вызывается и по таймеру).
         * @returns {void}
         */
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

        /**
         * Оставляет в значении поля только буквы (латиница/кириллица) и пробелы.
         * @param {Event} e
         * @returns {void}
         */
        function filterField( e ) {
            let t = e.target;
            let badValues = /[^a-zA-Zа-яА-Я ]/gi;
            t.value = t.value.replace( badValues, '' );
        }

        $( '#gender' ).select2();
    }

    $( function() {
        initCheckoutBonusesAndPayment( $ );
    } );
} )( jQuery );
