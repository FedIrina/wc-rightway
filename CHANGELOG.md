# Changelog

Формат версий: [Semantic Versioning](https://semver.org/lang/ru/).

## [1.1.0] — 2026-05-17

### JavaScript

- Монолитный `rightway.js` разбит на модули по страницам: `rightway-communication.js`, `rightway-billing.js`, `rightway-checkout-otp.js`, `rightway-lk-shared.js`; файл `rightway.js` удалён.
- Слой `rightway-api.js`: чтение и запись RW через `RightwayApi` (`rwPostOrReject`, без прямых `rightwayAjaxPost` в сценариях).
- OTP: конечный автомат (`rightway-otp-state.js`), модалка и ввод кода (`rightway-confirm-modal.js`, `rightway-confirm-code.js`).
- Ввод OTP: замена цифры без ручного стирания, Backspace/стрелки, вставка кода из буфера (только цифры, по полям слева направо).
- Модалку нельзя закрыть во время проверки кода (`verifying`).
- Checkout OTP по-прежнему отключён флагом `CHECKOUT_CREATE_ACCOUNT_OTP_ENABLED` в `rightway-checkout-otp.js`.

### PHP / подключение ресурсов

- Версия скриптов и стилей берётся из заголовка плагина (`$this->version`) — сброс кэша браузера при обновлении.
- Условный `wp_enqueue`: сценарии только на checkout, «Настройки коммуникаций», «Данные покупателя»; на «Мои бонусы» — только CSS.
- Edit-account: обработчик RW убран (сохранение аккаунта WC без RW; синхронизация email — на billing).
- Merge карт RW: при нескольких customer без незаблокированных карт не требовать `rwToken`.
- Сообщения об ошибках: `rightwayUserErrorMessage`, без префикса `Error:` в UI.

### Известные ограничения

- Проверка телефона через `getCustomersContacts` до SMS на billing отложена (см. `REFACTORING-PLAN.txt`).
- Checkout «Создать аккаунт» + OTP не включён — нужен отдельный релиз после тестов.

## [1.0.0]

- Первая зафиксированная версия интеграции WooCommerce с RightWay.
