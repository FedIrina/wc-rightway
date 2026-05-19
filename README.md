# RightWay for WooCommerce

WordPress-плагин для интеграции [WooCommerce](https://woocommerce.com/) с платформой лояльности **RightWay**: бонусные карты, списание и начисление бонусов на checkout, личный кабинет покупателя и синхронизация контактов через OTP.

**Версия:** 1.1.0  
**Лицензия:** [GPL-3.0](https://www.gnu.org/licenses/gpl-3.0.html)

---

> **Не универсальный плагин.** Это индивидуальная разработка под конкретные требования и конфигурацию проекта заказчика. Для других сайтов на WooCommerce для связки с платформой лояльности Rightway **не подходит** без переработки под их структуру страниц и бизнес-логику.

## Возможности

- **Checkout** — блок «Списать / начислить бонусы», расчёт скидки и передача данных в RW при оформлении заказа.
- **Личный кабинет → Мои бонусы** — информация о карте и балансе.
- **Личный кабинет → Данные покупателя** (`/my-account/edit-address/billing/`) — анкета (ФИО, дата рождения, пол), подтверждение телефона и email, привязка к покупателю RW.
- **Личный кабинет → Настройки коммуникаций** — согласия на SMS, email и маркетинговые рассылки с подтверждением по коду.
- **Админка WooCommerce** — вкладка настроек RightWay (API-ключи, brand ID, shop name и др.).

---

## Требования

- WordPress с установленным и активным **WooCommerce**
- Доступ к API RightWay (ключи и параметры бренда выдаёт RightWay)
- PHP с поддержкой cURL (для HTTP-запросов к API)

---

## Настройка

В разделе **WooCommerce → Настройки → RightWay** задаются:

| Параметр | Назначение |
|----------|------------|
| API Key | Ключ доступа к API RightWay |
| API Version | Версия API |
| Brand ID | Идентификатор бренда в RW |
| Shop Name | Имя магазина в RW |
| TSSA Key | Ключ TSSA |
| X-Processing-Key / Version | Заголовки обработки запросов RW |

Точный набор полей и значения согласуются с документацией RightWay для вашего бренда.

---

## Структура проекта

```
rightway/
├── rightway.php              # Точка входа плагина
├── classes/
│   ├── Plugin.php            # Хуки WC, AJAX, ЛК, checkout
│   └── API.php               # Клиент HTTP API RightWay
├── assets/
│   ├── js/                   # Модули сценариев (см. ниже)
│   └── css/
└── Docs/
    ├── CHANGELOG.md
    ├── REFACTORING-PLAN.txt
    └── REST-TRANSPORT-AB-PLAN.txt
```

### JavaScript-модули

| Файл | Назначение |
|------|------------|
| `rightway-core.js` | AJAX, blockUI, форматирование телефона, ошибки UI |
| `rightway-api.js` | Единый слой `RightwayApi` → `admin-ajax` |
| `rightway-otp-state.js` | FSM состояния OTP |
| `rightway-confirm-modal.js`, `rightway-confirm-code.js` | Модалка и ввод кода |
| `rightway-billing.js` | Сценарий «Данные покупателя» |
| `rightway-communication.js` | Сценарий «Настройки коммуникаций» |
| `rightway-checkout.js` | Бонусы на checkout |
| `rightway-checkout-otp.js` | OTP при создании аккаунта на checkout (отключён флагом) |
| `rightway-lk-shared.js` | Общая логика ЛК (анкета, email) |

Скрипты подключаются **условно** — только на нужных страницах (checkout, billing, communication-options).

---

## Документация

| Документ | Описание |
|----------|----------|
| [Docs/CHANGELOG.md](Docs/CHANGELOG.md) | История версий |
| [Docs/REFACTORING-PLAN.txt](Docs/REFACTORING-PLAN.txt) | План рефакторинга JS, контракты API |
| [Docs/REST-TRANSPORT-AB-PLAN.txt](Docs/REST-TRANSPORT-AB-PLAN.txt) | План перехода admin-ajax → REST |

---

## Известные ограничения (v1.1.0)

- OTP при оформлении заказа с созданием аккаунта **отключён** (`CHECKOUT_CREATE_ACCOUNT_OTP_ENABLED = false` в `rightway-checkout-otp.js`).
- Проверка уникальности телефона в RW до отправки SMS на billing временно отложена (см. `Docs/REFACTORING-PLAN.txt`).

---

## Разработка

Лог плагина (если включён): `rightway.log` в корне плагина — файл в `.gitignore`.

При изменении версии обновите заголовок в `rightway.php` и запись в `Docs/CHANGELOG.md`.

---

## Автор

Irina Feodorova
