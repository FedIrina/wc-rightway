<?php
/**
 * Ядро плагина RightWay для WooCommerce: хуки, AJAX, личный кабинет, checkout, настройки.
 *
 * Singleton: один раз {@see self::init()}, затем {@see self::get()}.
 *
 * @package RIGHTWAY
 */
namespace RIGHTWAY;

class Plugin
{
	/** @var string Абсолютный путь к каталогу плагина. */
	public $path;

	/** @var string URL каталога плагина. */
	public $url;

	/** @var string Имя плагина (из заголовка главного файла). */
	public $name;

	/** @var string Версия плагина. */
	public $version;

	/** @var RightWay|null Клиент API RightWay. */
	public $rightWayAPI = null;

	/** @var string|int Идентификатор покупателя RW (кэш на время запроса). */
	private $customerId = '';

	/** @var string|int Идентификатор контакта RW. */
	private $contactId = '';

	/** @var string Номер бонусной карты RW. */
	private $cardNumber = '';

	/** @var string Опция WooCommerce wc_rightway_api_key. */
	private $wc_rightway_api_key;
	/** @var string Опция wc_rightway_api_version. */
	private $wc_rightway_api_version;
	/** @var string Опция wc_rightway_tssa_key. */
	private $wc_rightway_tssa_key;
	/** @var string Опция wc_rightway_x_processing_key. */
	private $wc_rightway_x_processing_key;
	/** @var string Опция wc_rightway_x_processing_version. */
	private $wc_rightway_x_processing_version;
	/** @var string|int Опция wc_rightway_brand_id. */
	private $wc_rightway_brand_id;
	/** @var string Опция wc_rightway_shop_name. */
	private $wc_rightway_shop_name;

	/** @var self|null Единственный экземпляр плагина. */
	private static $instance = null;

	/**
	 * Инициализация singleton (вызывать один раз при загрузке плагина).
	 *
	 * @param string              $path Путь к каталогу плагина.
	 * @param string              $url  URL каталога плагина.
	 * @param array<string, string> $meta Результат {@see get_file_data()}: Name, Version и др.
	 * @return void
	 */
	public static function init( $path, $url, $meta )
	{
		if ( static::$instance !== null ) {
			return;
		}

		static::$instance = new static( $path, $url, $meta );
	}

	/**
	 * Текущий экземпляр плагина.
	 *
	 * @return self
	 * @throws \Exception Если {@see init()} ещё не вызывали.
	 */
	public static function get()
	{
		if ( static::$instance === null )
			throw new \Exception( __('Объект Plugin не инициализирован!', RIGHTWAY) ); 
			
		return static::$instance;
	}
	
	/**
	 * Регистрация хуков WooCommerce/WordPress и создание {@see $rightWayAPI}.
	 *
	 * @param string              $path См. {@see init()}.
	 * @param string              $url  См. {@see init()}.
	 * @param array<string, string> $meta См. {@see init()}.
	 * @return void
	 */
	private function __construct( $path, $url, $meta )
	{
		// Инициализация свойств
		$this->path 	= $path;
		$this->url 		= $url;
		$this->name 	= $meta[ 'Name' ];
		$this->version 	= $meta[ 'Version' ];
		$this->wc_rightway_api_key = \WC_Admin_Settings::get_option('wc_rightway_api_key');
		$this->wc_rightway_api_version = \WC_Admin_Settings::get_option('wc_rightway_api_version');
		$this->wc_rightway_tssa_key = \WC_Admin_Settings::get_option('wc_rightway_tssa_key');
		$this->wc_rightway_x_processing_key = \WC_Admin_Settings::get_option('wc_rightway_x_processing_key');
		$this->wc_rightway_x_processing_version = \WC_Admin_Settings::get_option('wc_rightway_x_processing_version');
		$this->wc_rightway_brand_id = \WC_Admin_Settings::get_option('wc_rightway_brand_id');
		$this->wc_rightway_shop_name = \WC_Admin_Settings::get_option('wc_rightway_shop_name');


		$this->rightWayAPI = new RightWay($this->wc_rightway_brand_id, $this->wc_rightway_shop_name, $this->wc_rightway_api_key, $this->wc_rightway_api_version, $this->wc_rightway_tssa_key, $this->wc_rightway_x_processing_version, $this->wc_rightway_x_processing_key);
		
		// Хуки
		add_action( 'wp_enqueue_scripts', array( $this, 'add_scripts' ), 10000 );
		add_action( 'wp_ajax_rightway_create_customer', array( $this, 'create_RW_customer' ) );		
		add_action( 'wp_ajax_nopriv_rightway_create_customer', array( $this, 'create_RW_customer' ) );
		add_action( 'wp_ajax_rightway_send_confirm_code', array( $this, 'send_confirm_code' ) );
		add_action( 'wp_ajax_rightway_send_confirm_contact_code', array( $this, 'send_confirm_contact_code' ) );
		add_action( 'wp_ajax_nopriv_rightway_send_confirm_contact_code', array( $this, 'send_confirm_contact_code' ) );
		add_action( 'wp_ajax_rightway_get_contact_token', array( $this, 'get_contact_token' ) );
		add_action( 'wp_ajax_nopriv_rightway_get_contact_token', array( $this, 'get_contact_token' ) );
		add_action( 'wp_ajax_nopriv_rightway_send_confirm_code', array( $this, 'send_confirm_code' ) );
		add_action( 'wp_ajax_rightway_edit_customer_data', array( $this, 'edit_RW_customer_data' ) );
		add_action( 'wp_ajax_rightway_get_customer_cards', array( $this, 'get_RW_customer_cards' ) );
		add_action( 'wp_ajax_nopriv_rightway_get_customer_cards', array( $this, 'get_RW_customer_cards' ) );
		add_action( 'wp_ajax_rightway_get_customers_quantity', array( $this, 'get_RW_customers_quantity' ) );
		add_action( 'wp_ajax_nopriv_rightway_get_customers_quantity', array( $this, 'get_RW_customers_quantity' ) );
		add_action( 'wp_ajax_rightway_get_customers', array( $this, 'get_RW_customers' ) );
		add_action( 'wp_ajax_nopriv_rightway_get_customers', array( $this, 'get_RW_customers' ) );
		add_action( 'wp_ajax_rightway_create_contact', array( $this, 'create_RW_contact_data' ) );
		add_action( 'wp_ajax_rightway_edit_contact_data', array( $this, 'edit_RW_contact_data' ) );
		add_action( 'wp_ajax_rightway_get_card_summary', array( $this, 'get_card_summary' ) );
		add_action( 'wp_ajax_rightway_get_customer_contacts', array( $this, 'get_customer_contacts' ) );
		add_action( 'wp_ajax_rightway_edit_communication_data', array( $this, 'edit_RW_communiction_data' ) );
		add_action( 'wp_ajax_rightway_get_active_bonuses', array( $this, 'get_active_bonuses' ) );
		add_action( 'wp_ajax_rightway_calculateActionDiscount', array( $this, 'calculateActionDiscount' ) );
		add_action( 'wp_ajax_nopriv_rightway_calculateActionDiscount', array( $this, 'calculateActionDiscount' ) );

		add_action( 'init', array( $this, 'wp_init' ) );
		/* add_action( 'wp_login', array( $this, 'getRwAuth' ), 10); */
		add_action( 'show_user_profile', array( $this, 'additional_user_profile_fields' ), 999 );
		add_action( 'edit_user_profile', array( $this, 'additional_user_profile_fields' ), 999 );
		add_action( 'personal_options_update', array( $this, 'save_additional_user_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_additional_user_profile_fields' ) );
		add_action( 'woocommerce_review_order_before_submit', array ($this,'output_bonuses_checkbox'),10 );
		//add_action( 'woocommerce_checkout_after_order_review', array ($this,'output_bonuses_checkbox'),10 );
		add_action( 'woocommerce_review_order_before_submit', array( $this,'add_checkout_hidden_field'), 20, 1 );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this,'save_custom_checkout_hidden_field'), 10, 1 );
		add_action( 'woocommerce_checkout_update_user_meta', array( $this,'save_custom_user_hidden_field'), 10, 2 );
		add_action( 'woocommerce_cart_calculate_fees', array( $this,'add_bonuses_discount'), 25 );
		add_action( 'woocommerce_checkout_update_order_review', array( $this,'woocommmerce_set_session' ));
		add_action( 'woocommerce_order_status_processing', array( $this,'process_bonuses_after_order_payment') );
		add_action( 'woocommerce_order_status_refunded', array( $this,'process_bonuses_after_order_return') );
		add_action( 'woocommerce_order_status_cancelled', array( $this,'process_bonuses_after_order_return') );		
		add_action( 'wp_logout', array($this,'my_end_session') );
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50 );
		add_action( 'woocommerce_settings_wc_rightway', array( $this, 'settings_tab' ) );
		add_action( 'woocommerce_update_options_wc_rightway', array( $this, 'update_settings' ) );
		add_action( 'woocommerce_account_dashboard', array( $this, 'check_customer_data' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'bonuses_link' ), 25 );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'options_link' ), 26 );
		add_action( 'init', array( $this, 'my_bonuses_add_endpoint' ), 25 );
		add_action( 'init', array( $this, 'communication_options_add_endpoint' ), 26 );
		add_action( 'woocommerce_account_my-bonuses_endpoint', array( $this, 'my_bonuses_content' ), 25 );
		add_action( 'woocommerce_account_communication-options_endpoint', array( $this, 'communication_options_content' ), 25 );
		add_action( "woocommerce_after_edit_address_form_billing",  array( $this, 'add_custom_user_fields' ), 10 );
		add_action( 'woocommerce_customer_save_address', array( $this,'save_custom_user_fields' ), 10, 2 );
		add_filter( 'woocommerce_cart_get_total', array( $this, 'filter_cart_get_total' ), 10, 1 );
		add_filter( 'woocommerce_cart_get_fee_taxes', array( $this, 'filter_cart_get_fee_taxes'), 10, 1 );
		add_filter( 'woocommerce_cart_totals_fee_html', array( $this, 'filter_cart_totals_fee_html'), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_fee_item', array( $this, 'alter_checkout_create_order_fee_item'), 10, 4 );
		/* add_action( 'woocommerce_after_save_address_validation', array( $this, 'edit_RW_customer_data' ), 1, 4); */
		//add_filter( 'woocommerce_billing_fields', array( $this, 'add_custom_billing_fields' ), 999, 1 );
		add_action( 'wp_footer', array( $this, 'add_confirm_modal_template' ), 9999 );

		$this->plugins_loaded();
	}

	/**
	 * Регистрация и подключение JS/CSS на checkout и на страницах ЛК с OTP/RW, локализация для AJAX.
	 *
	 * @return void
	 */
	public function add_scripts() {
		wp_register_script(
			'rightway-confirm-code',
			$this->url . 'assets/js/rightway-confirm-code.js',
			array(),
			$this->version,
			true
		);
		wp_register_script(
			'rightway-core',
			$this->url . 'assets/js/rightway-core.js',
			array(),
			$this->version,
			true
		);
		wp_register_script(
			'rightway-otp-state',
			$this->url . 'assets/js/rightway-otp-state.js',
			array(),
			$this->version,
			true
		);
		wp_register_script(
			'rightway-api',
			$this->url . 'assets/js/rightway-api.js',
			array( 'rightway-core' ),
			$this->version,
			true
		);
		wp_register_script(
			'rightway-confirm-modal',
			$this->url . 'assets/js/rightway-confirm-modal.js',
			array( 'jquery', 'rightway-core', 'rightway-otp-state', 'rightway-confirm-code' ),
			$this->version,
			true
		);
		wp_register_script(
			'rightway-lk-shared',
			$this->url . 'assets/js/rightway-lk-shared.js',
			array( 'rightway-api', 'rightway-confirm-modal' ),
			$this->version,
			true
		);
		wp_register_script(
			'rightway-communication',
			$this->url . 'assets/js/rightway-communication.js',
			array( 'jquery', 'rightway-api', 'rightway-confirm-modal' ),
			$this->version,
			true
		);
		wp_register_script(
			'rightway-billing',
			$this->url . 'assets/js/rightway-billing.js',
			array( 'jquery', 'rightway-lk-shared' ),
			$this->version,
			true
		);
		wp_register_script(
			'rightway-checkout-otp',
			$this->url . 'assets/js/rightway-checkout-otp.js',
			array( 'wc-checkout', 'rightway-api', 'rightway-confirm-modal' ),
			$this->version,
			true
		);

		if ( $this->should_enqueue_rightway_frontend_scripts() ) {
			$this->load_rightway_user_meta_for_current_user();

			wp_enqueue_style( 'rigtway-css', $this->url . 'assets/css/style.css', array(), $this->version );

			if ( $this->is_account_endpoint_active( 'my-bonuses' ) ) {
				return;
			}

			$fancybox_handle = $this->enqueue_fancybox_if_needed();
			$this->enqueue_wc_jquery_blockui();

			$is_checkout  = $this->is_rightway_checkout_page();
			$dependencies = array( 'wc-jquery-blockui', 'rightway-confirm-code', 'rightway-core', 'rightway-api', 'rightway-otp-state', 'rightway-confirm-modal' );
			if ( $is_checkout ) {
				$dependencies = array_merge( array( 'wc-checkout' ), $dependencies );
			} else {
				$dependencies = array_merge( array( 'jquery' ), $dependencies );
			}
			if ( $fancybox_handle ) {
				$dependencies[] = $fancybox_handle;
			}

			$rightway_localize = array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce_code' => wp_create_nonce( 'rigtway-nonce' ),
				'customerId'	=> $this->customerId,
				'contactId'	=> $this->contactId,
				'cardNumber'	=> $this->cardNumber,
				'billing_email_saved' => ( is_user_logged_in() ) ? trim( (string) get_user_meta( get_current_user_id(), 'billing_email', true ) ) : '',
			);

			if ( $is_checkout ) {
				wp_enqueue_script( 'rightway-checkout-otp', $this->url . 'assets/js/rightway-checkout-otp.js', $dependencies, $this->version, true );
				wp_localize_script( 'rightway-checkout-otp', 'rightway', $rightway_localize );
				wp_enqueue_script(
					'rightway-checkout',
					$this->url . 'assets/js/rightway-checkout.js',
					array( 'rightway-checkout-otp' ),
					$this->version,
					true
				);
			} elseif ( $this->is_account_endpoint_active( 'communication-options' ) ) {
				wp_enqueue_script( 'rightway-communication', $this->url . 'assets/js/rightway-communication.js', $dependencies, $this->version, true );
				wp_localize_script( 'rightway-communication', 'rightway', $rightway_localize );
			} elseif ( $this->is_account_endpoint_active( 'edit-address', 'billing' ) ) {
				wp_enqueue_script( 'rightway-lk-shared', $this->url . 'assets/js/rightway-lk-shared.js', $dependencies, $this->version, true );
				wp_localize_script( 'rightway-lk-shared', 'rightway', $rightway_localize );
				wp_enqueue_script( 'rightway-billing', $this->url . 'assets/js/rightway-billing.js', array( 'rightway-lk-shared' ), $this->version, true );
			}
		}
	}

	/**
	 * Активен ли эндпоинт ЛК WooCommerce (в т.ч. кастомные {@see add_rewrite_endpoint}, не только ядро WC).
	 *
	 * @param string      $endpoint Имя query var, например `my-bonuses`.
	 * @param string|null $value    Ожидаемое значение (для `edit-address` → `billing`).
	 * @return bool
	 */
	private function is_account_endpoint_active( $endpoint, $value = null ) {
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return false;
		}
		global $wp;
		if ( ! isset( $wp->query_vars[ $endpoint ] ) ) {
			return false;
		}
		if ( null === $value ) {
			return true;
		}
		return (string) $wp->query_vars[ $endpoint ] === (string) $value;
	}

	/**
	 * Подключать ли сценарные скрипты RW: checkout, «Настройки», «Данные покупателя», «Мои бонусы».
	 *
	 * @return bool
	 */
	private function should_enqueue_rightway_frontend_scripts() {
		if ( $this->is_rightway_checkout_page() ) {
			return true;
		}
		if ( $this->is_account_endpoint_active( 'communication-options' ) ) {
			return true;
		}
		if ( $this->is_account_endpoint_active( 'my-bonuses' ) ) {
			return true;
		}
		if ( $this->is_account_endpoint_active( 'edit-address', 'billing' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @return bool
	 */
	private function is_rightway_checkout_page() {
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return true;
		}
		return is_page( 'checkout' );
	}

	/**
	 * Мета RW текущего пользователя для wp_localize_script.
	 *
	 * @return void
	 */
	private function load_rightway_user_meta_for_current_user() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$user = wp_get_current_user();
		$this->contactId  = get_user_meta( $user->ID, 'contactId', true );
		$this->customerId = get_user_meta( $user->ID, 'customerId', true );
		$this->cardNumber = get_user_meta( $user->ID, 'cardNumber', true );
	}

	/**
	 * Регистрирует и подключает jquery.blockUI из каталога WooCommerce (тот же файл, что и у WC).
	 *
	 * @return void
	 */
	private function enqueue_wc_jquery_blockui() {
		if ( ! defined( 'WC_PLUGIN_FILE' ) ) {
			return;
		}
		if ( ! wp_script_is( 'wc-jquery-blockui', 'registered' ) ) {
			$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
			wp_register_script(
				'wc-jquery-blockui',
				plugins_url( 'assets/js/jquery-blockui/jquery.blockUI' . $suffix . '.js', WC_PLUGIN_FILE ),
				array( 'jquery' ),
				defined( 'WC_VERSION' ) ? WC_VERSION : '1.0',
				true
			);
		}
		wp_enqueue_script( 'wc-jquery-blockui' );
	}

	/**
	 * Проверка nonce для AJAX RightWay: POST-параметр nonce_code, действие rigtway-nonce.
	 *
	 * @return void При ошибке завершает запрос (стандартный ответ WordPress для AJAX).
	 */
	private function verify_rightway_ajax_nonce() {
		check_ajax_referer( 'rigtway-nonce', 'nonce_code' );
	}

	/**
	 * Флаг настроек коммуникации RW в ответе API (boolean или строка/число).
	 *
	 * @param mixed $value Значение из communicationSettings.
	 * @return bool
	 */
	private function rw_communication_flag_is_on( $value ) {
		return true === $value || 'true' === $value || 1 === $value || '1' === $value;
	}

	/**
	 * Проверяет, что контакт RW с данным id принадлежит указанному в RW покупателю.
	 *
	 * @param int|string $contact_id Идентификатор контакта в RW.
	 * @param int|string $customer_id Идентификатор покупателя в RW.
	 * @return void
	 * @throws \Exception Если контакт не найден в списке или запрос к API не удался.
	 */
	private function assert_rw_contact_belongs_to_customer( $contact_id, $customer_id ) {
		$contacts = $this->rightWayAPI->getCustomerContacts( $customer_id );
		if ( ! is_array( $contacts ) ) {
			throw new \Exception( 'Не удалось получить список контактов.' );
		}
		foreach ( $contacts as $c ) {
			if ( isset( $c['id'] ) && (string) $c['id'] === (string) $contact_id ) {
				return;
			}
		}
		throw new \Exception( 'Контакт не принадлежит текущему покупателю в программе лояльности.' );
	}

	/**
	 * Подключает FancyBox из плагина, если тема/другие плагины ещё не подключили известный handle.
	 *
	 * @return string|null Handle скрипта для зависимостей или null, если FancyBox уже в очереди.
	 */
	private function enqueue_fancybox_if_needed() {
		// Проверяем распространенные имена handle для FancyBox
		$fancybox_handles = array(
			'fancybox',
			'jquery-fancybox',
			'jquery.fancybox',
			'fancybox-js',
			'fancybox3',
			'@fancyapps/ui'
		);
		
		$is_fancybox_registered = false;
		
		// Проверяем, зарегистрирован или подключен ли какой-либо из вариантов FancyBox
		foreach ( $fancybox_handles as $handle ) {
			if ( wp_script_is( $handle, 'enqueued' ) ) {
				$is_fancybox_registered = true;
				error_log("Найден правильный handle fancybox: " . $handle);
				break;
			}
		}
		
		// Если FancyBox не найден, подключаем наш
		if ( ! $is_fancybox_registered ) {
			wp_enqueue_style( 'fancybox-css', $this->url.'assets/css/jquery.fancybox.min.css', array(), '3.5.7' );
			wp_enqueue_script( 'fancybox', $this->url.'assets/js/jquery.fancybox.min.js', array('jquery'), '3.5.7', true );
			return 'fancybox-js';
		}
		
		return null;
	}

	/**
	 * Поля RW (customerId, cardId, cardNumber) в профиле пользователя в админке.
	 *
	 * @param \WP_User $user Редактируемый пользователь.
	 * @return void
	 */
	public function additional_user_profile_fields( $user ) {
		echo '<table class="form-table">';
		echo '<h3>Программа лояльности</h3>';
		$customerId = get_user_meta($user->ID, 'customerId', true);
		$cardId = get_user_meta($user->ID, 'cardId', true);
		$cardNumber = get_user_meta($user->ID, 'cardNumber', true);
		echo '<tr><th><label for="city">Идентификатор пользователя</label></th>
 	<td><input type="text" name="customerId" id="customerId" value="' . esc_attr( $customerId ) . '" class="regular-text" /></td>
	</tr>';
		echo '<tr><th><label for="city">Идентификатор бонусной карты</label></th>
	<td><input type="text" name="cardId" id="cardId" value="' . esc_attr( $cardId ) . '" class="regular-text" /></td>
   </tr>';
   		echo '<tr><th><label for="city">Номер бонусной карта</label></th>
   <td><input type="text" name="cardNumber" id="cardNumber" value="' . esc_attr( $cardNumber ) . '" class="regular-text" /></td>
  </tr>';
		echo '</table>';

	}

	/**
	 * Сохранение полей RW из формы профиля в админке.
	 *
	 * @param int $user_id ID пользователя.
	 * @return void
	 */
	public function save_additional_user_profile_fields( $user_id ) {
		update_user_meta( $user_id, 'customerId', sanitize_text_field( $_POST[ 'customerId' ] ) );
		update_user_meta( $user_id, 'cardId', sanitize_text_field( $_POST[ 'cardId' ] ) );
		update_user_meta( $user_id, 'cardNumber', sanitize_text_field( $_POST[ 'cardNumber' ] ) );
	}

	/**
	 * Загрузка текстового домена плагина (вызывается из {@see __construct()}).
	 *
	 * @return void
	 */
	public function plugins_loaded()
	{
		// Локализация
		load_plugin_textdomain( RIGHTWAY, false, basename( dirname( __FILE__ ) ) . '/lang' );
	}
	
	/**
	 * Хук {@see 'init'}: проверка активности WooCommerce, иначе предупреждение в админке.
	 *
	 * @return void
	 */
	public function wp_init()
	{
		// Проверка наличия WC		
		if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) 
		{
			add_action( 'admin_notices', array( $this, 'showNoticeNoWC' ) );
			return;
		}

	}

	/**
	 * Хук {@see 'wp_logout'}: зарезервировано под очистку сессии RW.
	 *
	 * @return void
	 */
	public function my_end_session(){
		/* session_destroy(); */
	}
	
	/**
	 * Админ-уведомление: WooCommerce не активен.
	 *
	 * @return void
	 */
	public function showNoticeNoWC()
	{
		echo '<div class="notice notice-warning no-woocommerce"><p>';
		printf( 
			esc_html__( 'Для работы плагина "%s" требуется установить и активировать плагин WooCommerce.', RIGHTWAY ), 
			$this->name . ' ' . $this->version  
		);
		_e( 'В настоящий момент все функции плагина деактивированы.', RIGHTWAY );
		echo '</p></div>';
	}

	/**
	 * Имя файла лога в каталоге плагина по умолчанию.
	 */
	const LOGFILE = 'rightway.log';

	/**
	 * Пишет строку или дамп массива/объекта в файл лога плагина или в {@see error_log()}.
	 *
	 * @param string|array|object $message Сообщение или структура для print_r.
	 * @param string              $logfile Имя файла в каталоге плагина; пустая строка — только error_log для строк.
	 * @return void
	 */
	public function log( $message, $logfile = self::LOGFILE )
	{
		//if ( WP_DEBUG )
		//{
			if ( !empty( $logfile ) ) {
				$logfile = $this->path . $logfile;
			}
			if (is_array($message) || is_object($message)) 
			{
				if ( empty( $logfile ) )
				{

				} else
				{
					file_put_contents( $logfile, 
						'[' . date('d.m.Y H:i:s') . '] ' . ': ' . print_r( $message, true ) . PHP_EOL, 
						FILE_APPEND );	
				}
			} 
			else 
			{
				if ( empty( $logfile ) )
				{
					error_log( RIGHTWAY . ': ' . $message );
				}
				else
				{
					file_put_contents( $logfile, 
						'[' . date('d.m.Y H:i:s') . '] ' . ': ' . $message . PHP_EOL, 
						FILE_APPEND );	
				}				
			}			
		//}
	}

	/**
	 * Загрузка переводов (дополнительный метод; основной домен подключается в {@see plugins_loaded()}).
	 *
	 * @return void
	 */
	public function load_plugin_textdomain() {
		$locale = is_admin() && function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
		$locale = apply_filters( 'plugin_locale', $locale, RIGHTWAY );

		load_textdomain( RIGHTWAY, trailingslashit( WP_LANG_DIR ) . 'rightway/'.RIGHTWAY.'-' . $locale . '.mo' );
		load_plugin_textdomain( 'wc-whatsapp-notification', false, plugin_basename( dirname( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Выбор contactId в RW для отправки кода: по разрешённым каналам и типу контакта (Sms/Email).
	 *
	 * @param \WP_User $user         Пользователь.
	 * @param string   $mainContact Принудительно «Sms» или «Email»; пусто — по мета allowSms/allowEmail.
	 * @return int|string|null ID контакта RW или null, если не найден.
	 */
	public function getCurrentContactId( $user, $mainContact='' ) {
		if (!$mainContact) {
			// Определеяем, какие каналы коммуникации разрешил пользователь
			// Если и sms, и Email, то преимущество имеет sms
			$allowSms = get_user_meta($user->ID, 'allowSms', true);
			$allowEmail = get_user_meta($user->ID, 'allowEmail', true);
			$mainContact=($allowSms)?'Sms':'Email';
		}
		$customerRWInfo  = $this->rightWayAPI->getCustomerInfo($user);
/* 		ob_start();
		var_dump($customerRWInfo);               
		Plugin::get()->log( __( 'Ответ RW на запрос информации о клиенте RW', RIGHTWAY ) . ': ' .ob_get_clean() ); */				
		// Выбираем контакт для отправки подтверждающего кода
		if (isset($customerRWInfo[0]['contacts'])) {
			foreach ($customerRWInfo[0]['contacts'] as $customerContact) {
				if (strpos($customerContact['value'], '+') !== false && $mainContact == 'Sms' ) {
					$contactId = $customerContact['id'];
					break;
				}
				if (strpos($customerContact['value'], '@') !== false && $mainContact == 'Email') {
					$contactId = $customerContact['id'];
				}

			}
		}
		return $contactId;
	}

	/**
	 * AJAX: запрос кода подтверждения для контакта по полю POST `contactId`.
	 *
	 * @return void Ответ через {@see wp_send_json_success()} / {@see wp_send_json_error()}.
	 */
	public function send_confirm_code() {
		$this->verify_rightway_ajax_nonce();
		try {
			$this->rightWayAPI->sendConfirmationCode($_POST['contactId']);
			Plugin::get()->log( 'Код отправлен на contactId='+$_POST['contactId'] );
			wp_send_json_success('Код отправлен на contactId='+$_POST['contactId']); // ВРЕМЕННО ДЛЯ ТЕСТИРОВАНИЯ
		} catch (\Exception $e) {
			Plugin::get()->log( $e->getMessage() );
			wp_send_json_error($e->getMessage());
		} 
	}

	/**
	 * AJAX: отправка кода подтверждения на значение контакта (телефон/email).
	 *
	 * @return void
	 */
	public function send_confirm_contact_code() {	
		$this->verify_rightway_ajax_nonce();
		try {
			$result = $this->rightWayAPI->sendContactConfirmationCode($_POST['contactValue']);
			wp_send_json_success($result); // ВРЕМЕННО ДЛЯ ТЕСТИРОВАНИЯ
		} catch (\Exception $e) {
			Plugin::get()->log( $e->getMessage() );
			wp_send_json_error($e->getMessage());
		} 
	}

	/**
	 * AJAX: подтверждение кода и получение токена контакта ({@see RightWay::getToken()}).
	 *
	 * @return void
	 */
	public function get_contact_token() {	
		$this->verify_rightway_ajax_nonce();
		try {
			$result = $this->rightWayAPI->getToken($_POST['contactValue'], $_POST['confirmCode']);
			wp_send_json_success($result);
		} catch (\Exception $e) {
			Plugin::get()->log( $e->getMessage() );
			wp_send_json_error($e->getMessage());
		} 
	}	

	/* Закомментировано: проверка клиента RW по карте.
	public function getRwAuth($user = false) {
		if (!$user) {
			$user = wp_get_current_user();
		}
		if ( !empty( $user->roles ) && is_array( $user->roles ) ) {
			//Запрашиваем данные у платформы RW только в том случае, если пользователь customer
			if (in_array('customer',$user->roles)) {

				try {
					$isRWClient=$this->rightWayAPI->getRWCard($user);
					return $isRWClient;
				} catch (\Exception $e) {
					Plugin::get()->log( $e->getMessage() );
					return;
				}

			}
		}
	} */

	/**
	 * Блок чекбоксов «Списать / начислить бонусы» на checkout (перед кнопкой оформления).
	 *
	 * @return void
	 */
	public function output_bonuses_checkbox() {

		// Если это страница на оплаты заказа, то не выводим блок управления бонусами
		if (isset($_GET['pay_for_order']) && $_GET['pay_for_order']) {
			return;
		}
		
		if ( is_user_logged_in() ) {
			// Перебираем купоны и проверяем их ID. Если найден хотя бы один не rp_wcdpd, 
			// чекбоксы для управления бонусами не показываем
			$hide_bonuses_with_coupon = get_option('wc_rightway_hide_bonuses_with_coupon', 'no');
			if ($hide_bonuses_with_coupon === 'yes') {
			foreach (WC()->cart->get_coupons() as $code => $coupon) {
					if (strpos($code, 'rp_wcdpd') !== 0) {
						return;
					}
				}
			}
			$user = wp_get_current_user();
			$cardId = get_user_meta($user->ID, 'cardId', true);
			// Если у текущего пользователя в профиле не указан идентификатор бонусной карты, не выводим блок управления бонусами.
			// Вместо этого предлагаем заполнить анкету и подтвердить контакт.
			if (!$cardId) { ?>
					<div class="woocommerce-notices-wrapper">
						<div class="woocommerce-attension">
							<?php _e('Для того, чтобы воспользоваться преимуществами нашей бонусной программы, рекомендуется перейти в Личный кабинет в раздел <a href="/my-account/edit-address/billing/">"Данные покупателя"</a>, заполнить поля анкеты актуальными данными и подтвердить контакт. <b>Чтобы бонусы можно было списать и/или начислить уже для данного заказа, обновите страницу в браузере</b>.', 'medknigaservis'); ?>
						</div>
						<div class="notice">Ознакомиться с бонусной программой Вы можете, перейдя по <a href="https://medknigaservis.ru/news/2024/03/bonusnaya-programma/"> ссылке.</a></div>
					</div>
				<?php return;
			}
			if ( !empty( $user->roles ) && is_array( $user->roles ) ) {
				// Выводим блок управления бонусами только в том случае, если пользователь customer
				//if (in_array('customer',$user->roles) ) { 
					$billing_use_bonuses = WC()->session->get( 'billing_use_bonuses' );
					$billing_add_bonuses = WC()->session->get( 'billing_add_bonuses' );
					?>
					<div class="bonuses_wrapper">
						<div id="bonuses">
							<div id="bonuses-available"></div>
							<div id="bonuses-added"></div>
							<div id="bonuses-actions">							
								<div id="billing_use_bonuses_field" data-priority="">
									<label class="checkbox_button checkbox">
										<input type="checkbox" class="input-checkbox " name="billing_use_bonuses" id="billing_use_bonuses" value="1" checked="<?php echo $billing_use_bonuses; ?>">
										<div class="checkbox_button-check"></div>
										<div class="title">Списать бонусы</div>
									</label>
								</div>
								<div class="woocommerce-input-wrapper" id="billing_add_bonuses_field" data-priority="">
									<label class="checkbox_button checkbox">
										<input type="checkbox" class="input-checkbox " name="billing_add_bonuses" id="billing_add_bonuses" value="1" checked="<?php echo $billing_add_bonuses; ?>">
										<div class="checkbox_button-check"></div>
										<div class="title">Начислить бонусы</div>
									</label>
								</div>
							</div>
						</div>
					</div>
					<?php
				//}
			}
		} else { ?>
			<div class="woocommerce-notices-wrapper">
			<div class="woocommerce-attension">
				<?php _e('Для того, чтобы воспользоваться преимуществами нашей бонусной программы, рекомендуется зарегистрироваться на сайте, перейти в Личный кабинет в раздел <a href="/my-account/edit-address/billing/">"Данные покупателя"</a>, заполнить поля анкеты актуальными данными и подтвердить контакт. <b>Чтобы бонусы можно было списать и/или начислить уже для данного заказа, обновите страницу в браузере</b>.', 'medknigaservis'); ?>
			</div>
			<div class="notice">Ознакомиться с бонусной программой Вы можете, перейдя по <a href="https://medknigaservis.ru/news/2024/03/bonusnaya-programma/"> ссылке.</a></div>
		</div>
		<?php }
	}

	/**
	 * Добавляет в корзину WooCommerce fee: списание бонусов и/или скидка по акции из сессии.
	 *
	 * @param \WC_Cart $cart Корзина.
	 * @return void
	 */
	public function add_bonuses_discount( $cart ) {

		// ничего не делаем в админке и если не AJAX-запрос
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
	
		// получаем данные из сессий
		$billing_use_bonuses = WC()->session->get( 'billing_use_bonuses' );
		$active_bonuses = WC()->session->get( 'active_bonuses' );
		$rw_discount = WC()->session->get( 'rw_discount' );
		/* Plugin::get()->log('billing_use_bonuses = '.$billing_use_bonuses);
		Plugin::get()->log('active_bonuses = '.$active_bonuses); */
	
		// добавляем соответствующие скидки
		if ( $billing_use_bonuses && $active_bonuses ) {
			WC()->cart->add_fee( 'Использованные бонусы', -$active_bonuses, false );
		}
		if ( $rw_discount ) {
			WC()->cart->add_fee( 'Скидка по акции', -$rw_discount, false );
		}


	}

	/**
	 * Корректирует итог корзины с учётом отрицательного налога по ненаоблагаемым fee (бонусы).
	 *
	 * @param string|float $total Строка или число итога.
	 * @return string|float
	 */
	public function filter_cart_get_total( $total ) {
		$tax_amount = 0;
	
		foreach( WC()->cart->get_fees() as $fee ) {
			if( ! $fee->taxable && $fee->tax < 0 ) {
				$tax_amount -= $fee->tax;
			}
		}
	
		if( $tax_amount != 0 ) {
			$total += $tax_amount;
		}
		return $total;
	}

	/**
	 * Пересчёт агрегированных налогов по fee корзины (для согласованности с бонусными скидками).
	 *
	 * @param array<string, float> $fee_taxes Входящее значение фильтра (в методе сбрасывается).
	 * @return array<string, float>
	 */
	function filter_cart_get_fee_taxes( $fee_taxes ) {
		$fee_taxes = array();
		
		foreach( WC()->cart->get_fees() as $fee ) {
			if( $fee->taxable ) {
				foreach( $fee->tax_data as $tax_key => $tax_amount ) {
					if( isset($fee_taxes[$tax_key]) ) {
						$fee_taxes[$tax_key] += $tax_amount;
					} else {
						$fee_taxes[$tax_key] = $tax_amount;
					}
				}
			}
		}
		return $fee_taxes;
	}

	/**
	 * HTML строки fee: для ненаоблагаемых отрицательных fee показывать только сумму без налоговой части.
	 *
	 * @param string   $fee_html Разметка по умолчанию.
	 * @param \stdClass $fee     Объект fee.
	 * @return string
	 */
	public function filter_cart_totals_fee_html( $fee_html, $fee ) {
		if( ! $fee->taxable && $fee->tax < 0 ) {
			return wc_price( $fee->total );
		}
		return $fee_html;
	}

	/**
	 * При создании позиции fee в заказе обнуляет налоги для ненаоблагаемых отрицательных fee.
	 *
	 * @param \WC_Order_Item_Fee $item    Позиция fee.
	 * @param string               $fee_key Ключ fee.
	 * @param \stdClass             $fee     Источник fee из корзины.
	 * @param \WC_Order             $order   Заказ.
	 * @return void
	 */
	function alter_checkout_create_order_fee_item( $item, $fee_key, $fee, $order ) {
		if ( ! $fee->taxable && $fee->tax < 0 ) {
			$item->set_taxes(['total' => []]);
			$item->set_total_tax(0);
		}
	}

	/**
	 * Сохраняет в сессию WooCommerce флаги списания/начисления бонусов и служебные поля из POST checkout.
	 *
	 * @param string $posted_data Строка query string из {@see 'woocommerce_checkout_update_order_review'}.
	 * @return void
	 */
	public function woocommmerce_set_session( $posted_data ) {
		Plugin::get()->log( json_encode($posted_data) );
		parse_str( $posted_data, $output );
 
		if ( isset( $output[ 'billing_use_bonuses' ] ) ){
			WC()->session->set( 'billing_use_bonuses', $output[ 'billing_use_bonuses' ] );
		} else {
			WC()->session->set( 'billing_use_bonuses', null);
		}

		if ( isset( $output[ 'billing_add_bonuses' ] ) ){
			WC()->session->set( 'billing_add_bonuses', $output[ 'billing_add_bonuses' ] );
		} else {
			WC()->session->set( 'billing_add_bonuses', null);
		}

		if ( isset( $output[ 'active_bonuses' ] ) ){
			WC()->session->set( 'active_bonuses', $output[ 'active_bonuses' ] );
		} else {
			WC()->session->set( 'active_bonuses', null);
		}
		if ( isset( $output[ 'rw_discount' ] ) ){
			WC()->session->set( 'rw_discount', $output[ 'rw_discount' ] );
		} else {
			WC()->session->set( 'rw_discount', null);
		}		
				

		if ( isset( $output[ 'rw_cheque' ] ) ){
			WC()->session->set( 'rw_cheque', $output[ 'rw_cheque' ]);
		} else {
			WC()->session->set( 'rw_cheque', null);
		}		
	}

	/**
	 * AJAX: расчёт доступных бонусов и скидок по корзине через {@see RightWay::calculateSale()}.
	 *
	 * @return void
	 */
	public function get_active_bonuses() {
		$this->verify_rightway_ajax_nonce();
		if ( !isset($_POST['billing_phone']) ) {
			wp_send_json_error('Для доступа к бонусам нужно указать номер телефона.');
		}
		$useBonuses =  ($_POST['use_bonuses'] === 'true')?"Да":"Нет";
		$addBonuses =  ($_POST['add_bonuses'] === 'true')?"Да":"Нет";
		Plugin::get()->log('$useBonuses='.$useBonuses);
		Plugin::get()->log('$addBonuses='.$addBonuses);
		Plugin::get()->log('Способ оплаты: ');
		Plugin::get()->log($_POST['payment_method']);
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			//if ( !empty( $user->roles ) && is_array( $user->roles ) ) {
				//Запрашиваем данные у платформы RW только в том случае, если пользователь customer
				//if (in_array('customer',$user->roles)) {
				// Подготовка данных для запроса доступных бонусов

					if (isset($_POST['payment_method']) && $_POST['payment_method'] == 'cod') {
						$paymentType = "Кредит";
					} else {
						$paymentType = "Наличный";
					}
					$orderDate = date('c');

					$cardId = get_user_meta( $user->ID, 'cardId', true );
					$ignore = '"Нет"';
					$chequeStr='';
					foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
						$_product   = $cart_item['data'];
						$sku = $_product->get_sku();
						$product_name = $this->sanitize_string($_product->get_name());
						$quantity = $cart_item['quantity'];
						$price = $_product->get_price();
						$summ = (float) $price * (float) $quantity;
						$chequeStr .= '<Стр Код="'.$sku.'" Артикул="'.$sku.'" Наименование="'.$product_name.'" Колво="'.$quantity.'" Цена="'.$price.'" Сумма="'.$summ.'" НачислениеБонусов="'.$addBonuses.'" СписаниеБонусов="'.$useBonuses.'" ТипПродажи="online" Игнорировать='.$ignore.'/>';
					}
					$cardNumber = get_user_meta( $user->ID, 'cardNumber', true );
					//$billing_phone = str_replace(array(' ', '(' , ')', '-'), '', get_user_meta( $user->ID, 'billing_phone', true ));
					$billing_phone = str_replace(array(' ', '(' , ')', '-'), '', $_POST['billing_phone']);
					$docNumber = $cardInfo['id'].date(YmdHms); //Должен быть сохранен в сессии до момента оформления данного заказа
					$brandId = $this->rightWayAPI->brandId;
					$brandId = 'MEDKNIGASERVIS';
					$shopId = '0000';
					$shopName = "Internet";
					//$promoCode = ' ПромоКод="RTY56MN789AS4"';
					$promoCode = '';
					//$productActioncode = ' КодПродуктовойАкции="adscv78878sdcvvj3v2r23rb8cdd"';
					$productActioncode = '';
					$createCard =''; // ' СоздатьКартуСТипом="0"';
					$PLId = ''; // 'ИдентификаторПЛ="123"';
					$cheque='<?xml version="1.0" encoding="UTF-8"?>
					<Данные>
						<Чек ДатаДок="'.$orderDate.'" НомерКартыКлиента="'.$cardNumber.'" НомерТелефонаКлиента="" НомерДокумента="'.$docNumber.'" ОперацияДокумента="Продажа" Бренд="'.$brandId.'"  МагазинКод="'.$shopId.'" МагазинНаименование="'.$shopName.'" СпособОплаты="'.$paymentType.'" '.$promocode.$productActioncode.$createCard.$PLId.'>'.$chequeStr.'</Чек>
					</Данные>';
					try {
						$result = $this->rightWayAPI->calculateSale($cheque);
						$bonusesInfoArr = array();
						foreach($result->Чек->attributes() as $key => $value) {
							$bonusesInfoArr[(string)$key] = (string)$value[0];
						}
						$productStrings = '';
						$strs = '';
						$rwActionDiscount = 0; //Общая сумма скидки для всего заказа, если действует акция
						foreach($result->Чек->Стр as $productStr) {
							$strs .= '<Стр Код="'.$productStr->attributes()->Код[0].'" Артикул="'.$productStr->attributes()->Артикул[0].'" Сумма="'.$productStr->attributes()->Сумма[0].'" СуммаСписанияБонусов="'.$productStr->attributes()->МаксимальнаяСуммаСписанияБонусов[0].'" СуммаСкидки="'.$productStr->attributes()->СуммаСкидки[0].'"/>';
							$productStrings .= $productStr->asXML();
							$rwActionDiscount += $productStr->attributes()->СуммаСкидки[0];
						}
						$bonusesInfoArr['cheque'] = json_encode( $productStrings, JSON_UNESCAPED_UNICODE);
						$bonusesInfoArr['СуммаСкидки'] = $rwActionDiscount;
						$bonusesInfoArr['strs'] = $strs;
						/* Plugin::get()->log( $bonusesInfoArr ); */
						wp_send_json_success( $bonusesInfoArr );
					}	catch (\Exception $e) {
						/* Plugin::get()->log( $e->getMessage() ); */
						wp_send_json_error( $e->getMessage() );
					}
				//}
			//}
		}
	}

	/**
	 * AJAX: скидка по акции без карты — {@see RightWay::calculateSaleWithoutCard()}.
	 *
	 * @return void
	 */
	  public function calculateActionDiscount() {
		$this->verify_rightway_ajax_nonce();
		// Подготовка данных для запроса скидки
		$chequeStr='';
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$_product   = $cart_item['data'];
			$sku = $_product->get_sku();
			$product_name = $_product->get_name();
			$quantity = $cart_item['quantity'];
			$price = $_product->get_price();
			$summ = (float) $price * (float) $quantity;
			$chequeStr .= '<Стр Артикул="'.$sku.'" Наименование="'.$product_name.'" Колво="'.$quantity.'" Цена="'.$price.'" Сумма="'.$summ.'" ТипПродажи="online"/>';
		}
		$docNumber = $cardInfo['id'].date(YmdHms); //Должен быть сохранен в сессии до момента оформления данного заказа
		//$brandId = $this->rightWayAPI->brandId;
		$brandId = 'MEDKNIGASERVIS';
		$shopId = '0000';
		$shopName = "Internet";
		if (isset($_POST['payment_method']) && $_POST['payment_method'] == 'cod') {
			$paymentType = "Кредит";
		} else {
			$paymentType = "Наличный";
		}

		//$promoCode = ' ПромоКод="RTY56MN789AS4"';
		$promoCode = '';
		//$productActioncode = ' КодПродуктовойАкции="adscv78878sdcvvj3v2r23rb8cdd"';
		$productActioncode = '';
		$cheque='<?xml version="1.0" encoding="UTF-8"?>
		<Данные>
			<Чек Бренд="'.$brandId.'" НомерДокумента="'.$docNumber.'" МагазинКод="'.$shopId.'" МагазинНаименование="'.$shopName.'" СпособОплаты="'.$paymentType.'" '. ' ОперацияДокумента="Продажа"' .$promocode.$productActioncode.'>'.$chequeStr.'</Чек>
		</Данные>';
		try {
			$result = $this->rightWayAPI->calculateSaleWithoutCard($cheque);
			$bonusesInfoArr = array();
			foreach($result->Чек->attributes() as $key => $value) {
				$bonusesInfoArr[(string)$key] = (string)$value[0];
			}
			$productStrings = '';
			$totalDiscount = '';
			foreach($result->Чек->Стр as $productStr) {
				$totalDiscount += $productStr->attributes()->СуммаСкидки[0];
			}
			/* $bonusesInfoArr['cheque'] = json_encode( $productStrings, JSON_UNESCAPED_UNICODE); */
			$bonusesInfoArr['rw_discount'] = $totalDiscount;
			Plugin::get()->log( 'Суммарная скидка по заказу:'.$bonusesInfoArr['rw_discount'] );
			wp_send_json_success( $bonusesInfoArr );
		}	catch (\Exception $e) {
			/* Plugin::get()->log( $e->getMessage() ); */
			wp_send_json_error( $e->getMessage() );
		}
	}	

	/**
	 * Удаляет кавычки из строки для безопасной подстановки в XML чека RW.
	 *
	 * @param string $inString Исходная строка.
	 * @return string
	 */
	public function sanitize_string($inString) {
		$substringsToRemove = ['\'', '"'];
		$outString = str_replace($substringsToRemove, "", $inString);
		return $outString;
	}

	/**
	 * После оплаты заказа: отправка чека в RW через {@see RightWay::applyPurchase()} при наличии метаданных расчёта.
	 *
	 * @param int $order_id ID заказа WooCommerce.
	 * @return void
	 */
	public function process_bonuses_after_order_payment($order_id){
		$order = wc_get_order( $order_id );
		$order_items           = $order->get_items();
		foreach ( $order_items as $item_id => $item ) {
		}
		if ( ! empty( $_POST['rw_card_number'] ) ) {
			update_post_meta( $order_id, 'rw_card_number', sanitize_text_field( $_POST['rw_card_number'] ) );
		}

		$rw_card_operation = get_post_meta($order_id, 'rw_card_operation', true);
		$rw_doc_number = get_post_meta($order_id, 'rw_doc_number', true);
		$rw_card_number = get_post_meta($order_id, 'rw_card_number', true);
		if (!$rw_doc_number) {
			return;
		}
		
		$check_attributs = 'НомерКартыКлиента="'.$rw_card_number.'" НомерДокумента="'.$rw_doc_number.'" ОперацияДокумента="Продажа" ОперацияКарты="'.$rw_card_operation.'" Бренд="MEDKNIGASERVIS">';
		$rw_cheque = stripslashes( get_post_meta($order_id, 'rw_cheque', true)) ;
		$rw_cheque = trim(str_replace('>n', '>', $rw_cheque) ,'"'); 
		$rw_cheque = str_replace('  ','', $rw_cheque);
		$cheque = '<?xml version="1.0" encoding="UTF-8"?>
		<Данные>
			<Чек 
			'.$check_attributs.'
			'.$rw_cheque.'
			</Чек>
		</Данные>';

		try {
			$result = $this->rightWayAPI->applyPurchase($cheque);
		}	catch (\Exception $e) {
			Plugin::get()->log( $e->getMessage() );
		}
	}

	/**
	 * Возврат/отмена заказа: {@see RightWay::calculateReturn()} и {@see RightWay::applyReturn()} по метаданным заказа.
	 *
	 * @param int $order_id ID заказа WooCommerce.
	 * @return void
	 */
	  public function process_bonuses_after_order_return($order_id){
		$order = wc_get_order( $order_id );
		$order_items           = $order->get_items();
		$cheque_str = '';
		$shopId = '0000';
		$shopName = "Internet";
		Plugin::get()->log($order_items);
		foreach ( $order_items as $item_id => $item ) {
			$product = wc_get_product( $item->get_product_id() );
			$cheque_str .= '<Стр Код="'.$product->get_sku().'" Артикул="'.$product->get_sku().'" Наименование="'.$item->get_name().'" Колво="'.$item->get_quantity().'" Цена="'.round(($item->get_subtotal()+$item->get_subtotal_tax())/$item->get_quantity(), 0).'" Сумма="'.round($item->get_total() +  $item->get_total_tax(), 0).'"/>' ;
		}
/* 		if ( ! empty( $_POST['rw_card_number'] ) )
        update_post_meta( $order_id, 'rw_card_number', sanitize_text_field( $_POST['rw_card_number'] ) ); */

		$rw_card_operation = get_post_meta($order_id, 'rw_card_operation', true);
		$rw_doc_number = get_post_meta($order_id, 'rw_doc_number', true);
		$rw_card_number = get_post_meta($order_id, 'rw_card_number', true);
		if (!$rw_doc_number) {
			return;
		}
		
		$check_attributs = 'ДатаДок="'.date('Y-m-d\TH:i:sP').'" НомерКартыКлиента="'.$rw_card_number.'" НомерДокумента="'.$rw_doc_number.'_1" НомерДокументаПродажи="'.$rw_doc_number.'" ОперацияДокумента="Возврат" ОперацияКарты="'.$rw_card_operation.'" Бренд="MEDKNIGASERVIS" МагазинКод="'.$shopId.'" МагазинНаименование="'.$shopName.'"';
		$cheque_str = trim(str_replace('>n', '>', $cheque_str) ,'"'); 
		$cheque_str = str_replace('  ','', $cheque_str);
		$cheque = '<?xml version="1.0" encoding="UTF-8"?>';
		$cheque .= '<Данные>';
		$cheque .= '<Чек '.$check_attributs.'>';
		$cheque .= $cheque_str;
		$cheque .= '</Чек>';
		$cheque .= '</Данные>';

		$chequeToApply = '<?xml version="1.0" encoding="UTF-8"?>';
		$chequeToApply .= '<Данные>';
		$chequeToApply .= '<Чек НомерКартыКлиента="'.$rw_card_number.'" НомерДокумента="'.$rw_doc_number.'_1" ОперацияДокумента="Возврат" ОперацияКарты="Начисление" Бренд="MEDKNIGASERVIS"/>';
		$chequeToApply .= '</Данные>';

/* 		$testCheque = '<?xml version="1.0" encoding="UTF-8"?>
		<Данные>
		  <Чек ДатаДок="2014-05-30T13:45:32+04:00" НомерКартыКлиента="8008100711132" НомерДокумента="20240302190304_1" НомерДокументаПродажи="20240302190304" ОперацияДокумента="Возврат" ОперацияКарты="Начисление" Бренд="MEDKNIGASERVIS" МагазинКод="0000" МагазинНаименование="Internet">
			<Стр Код="NF0026137" Артикул="NF0026137" Наименование="Избранные научно-популярные труды. В 4-х книгах. Книга 3. Беседы в кафе: мысли, анекдоты, откровения" Колво="1" Цена="1130" Сумма="1107"/>
		  </Чек>
		</Данные>'; */	

		Plugin::get()->log($cheque);
		try {
			$result = $this->rightWayAPI->calculateReturn($cheque);
			$result = $this->rightWayAPI->applyReturn($chequeToApply);
/* 			 */
		}	catch (\Exception $e) {
			Plugin::get()->log( $e->getMessage() );
		}
	}	

	/**
	 * Вкладка настроек WooCommerce «Программа лояльности».
	 *
	 * @param array<string, string> $settings_tabs Вкладки настроек WC.
	 * @return array<string, string>
	 */
	public function add_settings_tab( $settings_tabs ) {
		$settings_tabs['wc_rightway'] = _x( 'Программа лояльности', 'WooCommerce Settings Tab', RIGTWAY );
		return $settings_tabs;
	}

	/**
	 * Вывод полей вкладки настроек RW в админке WooCommerce.
	 *
	 * @return void
	 */
	public function settings_tab() {
		//echo \WC_Admin_Settings::get_option('wc_rightway_api_key');
		woocommerce_admin_fields( self::get_settings() ); 
	}
	
	/**
	 * Определение полей настроек для {@see woocommerce_admin_fields()}.
	 *
	 * @return array<string, array<string, mixed>> Структура настроек WC.
	 */
	public function get_settings() {

		$settings=[];
		$selected_options = woocommerce_settings_get_option('wc-rightway');	
		$settings['section_title'] = array(
				'name'     => __( 'Настройки программы лояльности', RIGTWAY ),
				'type'     => 'title',
				'desc'     => '',
				'id'       => 'do_section_title'
		);

		$settings['wc-rightway-brand-id'] = array(
			'name'     => __( 'Идентификатор бренда', RIGTWAY ),
			'id'		=> 'wc_rightway_brand_id',
			'type'    	=> 'text',
			'default' 	=> '',
			'desc'		=> ''
		);

		$settings['wc-rightway-shop-name'] = array(
			'name'     => __( 'Наименование магазина', RIGTWAY ),
			'id'		=> 'wc_rightway_shop_name',
			'type'    	=> 'text',
			'default' 	=> '',
			'desc'		=> ''
		);		

		$settings['wc-rightway-api-key'] = array(
				'name'     => __( 'X-API key', RIGTWAY ),
				'id'		=> 'wc_rightway_api_key',
				'type'    	=> 'text',
				'default' 	=> ''
		);

		$settings['wc-rightway-api-virsion'] = array(
			'name'     => __( 'X-API version', RIGTWAY ),
			'id'		=> 'wc_rightway_api_version',
			'type'    	=> 'text',
			'default' 	=> ''
		);

		$settings['wc-rightway-tssa-key'] = array(
			'name'     => __( 'Ключ TSSA', RIGTWAY ),
			'id'		=> 'wc_rightway_tssa_key',
			'type'    	=> 'text',
			'default' 	=> '',
			'desc'		=> ''
		);

		$settings['wc-rightway-x-processing-version'] = array(
			'name'     => __( 'Версия процессинга', RIGTWAY ),
			'id'		=> 'wc_rightway_x_processing_version',
			'type'    	=> 'text',
			'default' 	=> '',
			'desc'		=> ''
		);

		$settings['wc-rightway-x-processing-key'] = array(
			'name'     => __( 'Ключ процессинга', RIGTWAY ),
			'id'		=> 'wc_rightway_x_processing_key',
			'type'    	=> 'text',
			'default' 	=> '',
			'desc'		=> ''
		);

		$settings['wc-rightway-hide-bonuses-with-coupon'] = array(
			'name'     => __( 'Не показывать блок бонусов при применении купона', RIGTWAY ),
			'id'		=> 'wc_rightway_hide_bonuses_with_coupon',
			'type'    	=> 'checkbox',
			'default' 	=> 'no',
			'desc'		=> __( 'Если включено, блок управления бонусами не будет отображаться на checkout странице, когда применен любой купон', RIGTWAY )
		);
		
		$settings['section_end'] = array(
			'type' => 'sectionend',
			'id' => 'do_section_end'
		);

		return apply_filters( 'woocommerce_get_settings_wc_rightway', $settings );
	}

	/**
	 * Сохранение опций вкладки RW при сабмите настроек WooCommerce.
	 *
	 * @return void
	 */
	public function update_settings() {
		woocommerce_update_options( self::get_settings() );
	}

	/**
	 * Дашборд ЛК: сверка billing-данных с {@see RightWay::getCardSummary()}, предупреждения при расхождении.
	 *
	 * @return void
	 */
	public function check_customer_data() {
		$user = wp_get_current_user();
		PLUGIN::get()->log('check_customer_data');
		if ( !empty( $user->roles ) && is_array( $user->roles ) ) {
			//Запрашиваем данные у платформы RW только в том случае, если пользователь customer
			//if (in_array('customer',$user->roles)) {
				
				$customerId = get_user_meta( $user->ID, 'customerId', true );
				if ($customerId) {
					// Сверяем данные покупателя на сайте с данными покупателя в RW, если у покупателя на сайте есть customerId	
					try {
					/* $customerRWInfo = $this->rightWayAPI->getCustomerInfo($user)[0]; */
					$cardId = get_user_meta( $user->ID, 'cardId', true );
					$cardSummary = $this->rightWayAPI->getCardSummary($cardId);
					$cardSummaryArray = json_decode( $cardSummary, true );
					PLUGIN::get()->log($cardSummaryArray);
					/* $customerRWInfo = $this->rightWayAPI->getCustomerContacts($customerId); */
					$notMatch = false;
					$billing_email_rw = trim( (string) get_user_meta( $user->ID, 'billing_email', true ) );
					foreach( $cardSummaryArray['contacts'] as $customerContact ) {
						$billing_phone = get_user_meta( $user->ID, 'billing_phone', true );
						if (!preg_match('/^\+7\d{10}$/', $billing_phone)) {
							$billing_phone = '+'.$billing_phone;
						}
						if (strpos($customerContact['value'], '+') !== false && $customerContact['value'] !== $billing_phone ) {
							ob_start();
							var_dump($customerContact['value']);
							var_dump($billing_phone);
							$mismatchedData = ob_get_clean();
							$contactId = $customerContact['id'];
							$notMatch = true;
							break;
						}
						if (strpos($customerContact['value'], '@') !== false && $customerContact['value'] !== $billing_email_rw) {
							ob_start();
							var_dump($customerContact['value']);
							var_dump($billing_email_rw);
							$mismatchedData = ob_get_clean();
							$contactId = $customerContact['id'];
							$notMatch = true;
							break;
						}
					}
					if ($cardSummaryArray['owner']['firstName'] !== get_user_meta( $user->ID, 'billing_first_name', true ) || 
						$cardSummaryArray['owner']['lastName'] !== get_user_meta( $user->ID, 'billing_last_name', true ) || 
						$cardSummaryArray['owner']['birthDate'] !== get_user_meta( $user->ID, 'birthDate', true) || 
						$cardSummaryArray['owner']['gender'] !== get_user_meta( $user->ID, 'gender', true) )
					{ 
						$notMatch = true;
					}
					if ( $notMatch ) { ?>
					<div class="woocommerce-notices-wrapper">
						<ul class="woocommerce-attension">
							<li><?php _e( 'Анкетные данные на сайте не совпадают с анкетными данными бонусной карты или не заполнены. Для того, чтобы воспользоваться преимуществами нашей бонусной программы, рекомендуется перейти в раздел <a href="/my-account/edit-address/billing/">"Данные покупателя"</a> и заполнить поля анкеты актуальными данными.', RIGHTWAY ); ?> </li>
						</ul>
						<div class="mismatched-data"><?php //echo $mismatchedData; ?></div>
						<div class="notice">Ознакомиться с бонусной программой Вы можете, перейдя по <a href="https://medknigaservis.ru/news/2024/03/bonusnaya-programma/"> ссылке.</a></div>
					</div>						
					<?php 
					}

					} catch (\Exception $e) {
						Plugin::get()->log( $e->getMessage() ); ?>
						<div class="woocommerce-notices-wrapper">
							<ul class="woocommerce-error" role="alert">
								<li><?php _e( 'Не удалось получить информацию о лояльности клиента.', RIGHTWAY ); ?> </li>
								<li><?php echo $e->getMessage(); ?></li>
							</ul>
						</div>
					<?php
						return;
					}
				} else {
					// Предлагаем участвовать в программе лояльности, указав свой номер телефона ?>
					<div class="woocommerce-notices-wrapper">
						<div class="woocommerce-attension">
							<?php _e( 'Для того, чтобы воспользоваться преимуществами нашей бонусной программы, рекомендуется перейти в раздел <a href="/my-account/edit-address/billing/">"Данные покупателя"</a> и заполнить поля анкеты актуальными данными. При этом на Ваш номер телефона придет код подтверджения. <b>Чтобы бонусы можно было списать и/или начислить уже для данного заказа, обновите страницу в браузере</b>.', RIGHTWAY ); ?>
						</div>
						<div class="notice">Ознакомиться с бонусной программой Вы можете, перейдя по <a href="https://medknigaservis.ru/news/2024/03/bonusnaya-programma/"> ссылке.</a></div>
					</div>
				<?php 
				}
				
			//}
		}
	}	

	/**
	 * Добавляет пункт «Мои бонусы» в меню личного кабинета при наличии customerId в мета.
	 *
	 * @param array<string, string> $menu_links Пункты меню WC.
	 * @return array<string, string>
	 */
	public function bonuses_link( $menu_links ){
		/* $menu_links[ 'my-bonuses' ] = 'Мои бонусы'; */
		$user = wp_get_current_user();
		if ( !empty( $user->roles ) && is_array( $user->roles ) ) {
			//Запрашиваем данные у платформы RW только в том случае, если пользователь customer
			/* if (in_array('customer',$user->roles) && get_user_meta( $user->ID, 'customerId', true )) { */
			if (get_user_meta( $user->ID, 'customerId', true )) {
				$menu_links = array_slice( $menu_links, 0, 5, true ) + array( 'my-bonuses' => 'Мои бонусы' ) + array_slice( $menu_links, 5, NULL, true );
			}
		}
		return $menu_links;
	}

	/**
	 * Регистрация rewrite-эндпоинта {@see 'my-bonuses'} для страницы бонусов в ЛК.
	 *
	 * @return void
	 */
	public function my_bonuses_add_endpoint() {
	 
		add_rewrite_endpoint( 'my-bonuses', EP_PAGES );
	 
	}
	 
	/**
	 * Контент вкладки «Мои бонусы»: таблица пакетов из {@see RightWay::getCustomerRWBonuses()}.
	 *
	 * @return void
	 */
	public function my_bonuses_content() {
		$user = wp_get_current_user();
		$cardId = get_user_meta( $user->ID, 'cardId', true );
		$bonusesArray = $this->rightWayAPI->getCustomerRWBonuses($cardId);
		echo '<h3>'.__( 'Мои бонусы', RIGHTWAY ).'</h3>'; 
		if (count($bonusesArray) !=0) :
			// Перебираем все пакеты бонусов и суммируем все активные на данный момент бонусы
			$activeBonusesSum = 0;
			foreach($bonusesArray as $bonusPack) {
				$activeBonusesSum += $bonusPack['active'];
			} ?>
		<div class="bonuses">
		<div class="bonuses__info">
		  <span class="bonuses__txt"><?php _e('На сегодня Вам доступно:', RIGHTWAY); ?></span><span class="
			bonuses__total"><?php echo $activeBonusesSum; ?>&nbsp;бонусов</span>
		</div>

		<h4 class="bonuses__subtitle"><?php _e('История начисления бонусов:', RIGHTWAY); ?></h4>
		<!-- ================ -->

		<table class="table">
		  <tbody>
			<?php foreach($bonusesArray as $purchase) { ?>
			<tr>
			  <td>
				<div class="td__label"><?php _e('Начислены:', RIGHTWAY); ?></div>
				<div class="td__value"><?php echo $purchase['createdOn']; ?></div>
			  </td>
			  <td>
				<div class="td__label"><?php _e('Сгорят', RIGHTWAY); ?>:</div>
				<div class="td__value"><?php echo $purchase['expiredOn']; ?></div>
			  </td>
			  <td>
				<div class="td__label"><?php _e('Активны', RIGHTWAY); ?>:</div>
				<div class="td__value"><?php echo $purchase['active']; ?></div>
			  </td>
			  <td>
				<div class="td__label"><?php _e('Использованы:', RIGHTWAY); ?></div>
				<div class="td__value"><?php echo $purchase['used']; ?></div>
			  </td>
			  <td>
				<div class="td__label"><?php _e('Тип бонусов:', RIGHTWAY); ?></div>
				<div class="td__value"><?php echo $purchase['bonusType']; ?></div>
			  </td>
			</tr>
			<?php } ?>
		  </tbody>
		</table>
		<?php else: ?>
			<!-- Если нет бонусов -->
			<div class="bonuses__not"><?php _e('Вы пока не получали бонусов', RIGHTWAY); ?></div>
		<?php endif; ?>

	  </div>

	  <?php
	 
	}

	/**
	 * Добавляет пункт «Настройки» (communication-options) в меню ЛК.
	 *
	 * @param array<string, string> $menu_links Пункты меню WC.
	 * @return array<string, string>
	 */
	public function options_link( $menu_links ){
		/* $menu_links[ 'communication-options' ] = 'Настройки'; */
		$user = wp_get_current_user();
		if ( !empty( $user->roles ) && is_array( $user->roles ) ) {
			//Запрашиваем данные у платформы RW только в том случае, если пользователь customer
			//if (in_array('customer',$user->roles) && get_user_meta( $user->ID, 'customerId', true )) {
				$menu_links = array_slice( $menu_links, 0, 5, true ) + array( 'communication-options' => 'Настройки' ) + array_slice( $menu_links, 6, NULL, true );
			//}
		}
		return $menu_links;
	}

	/**
	 * Регистрация rewrite-эндпоинта {@see 'communication-options'}.
	 *
	 * @return void
	 */
	public function communication_options_add_endpoint() {
	 
		add_rewrite_endpoint( 'communication-options', EP_PAGES );
	 
	}
	 

	/**
	 * Форма настроек каналов коммуникации и маркетинга (user meta + POST).
	 *
	 * @return void
	 */
	public function communication_options_content() {
		$user = wp_get_current_user();
		$user_id = $user->ID;
		if (isset($_POST['change_meta'])) {
			update_user_meta( $user_id, 'allowSms', (isset($_POST['allowSms']))?1:0);
			update_user_meta( $user_id, 'allowEmail', (isset($_POST['allowEmail']))?1:0);
			update_user_meta( $user_id, 'allowMarketingCommunication', (isset($_POST['allowMarketingCommunication']))?1:0);
		}
		$allowEmail = get_user_meta( $user_id, 'allowEmail', true );
		$allowSms = get_user_meta( $user_id, 'allowSms', true );
		$allowMarketingCommunication = get_user_meta( $user_id, 'allowMarketingCommunication', true );
		if (!$allowEmail && !$allowSms) {
			$allowSms = true;
		}
		?>
		<form id="woocommerce-edit-communication" method="post">
		<h3><?php _e( 'Настройки коммуникации', RIGHTWAY ); ?></h3>
		<p><?php _e( 'Каналы коммуникации (для отправки кода подтверждения при операциях с бонусами):', RIGHTWAY); ?></p>
		<div class="communication-item">
			<label class="checkbox_button checkbox">
				<input type="checkbox" class="input-checkbox " name="allowSms" id="allowSms" value="1" <?php checked( $allowSms, 1 ); ?>>
				<div class="checkbox_button-check"></div>
				<div class="title"><?php _e( 'SMS', RIGHTWAY); ?></div>
			</label>
		</div>
		<div class="communication-item">
			<label class="checkbox_button checkbox">
				<input type="checkbox" class="input-checkbox " name="allowEmail" id="allowEmail" value="1" <?php checked( $allowEmail, 1 ); ?>>
				<div class="checkbox_button-check"></div>
				<div class="title"><?php _e( 'Email', RIGHTWAY); ?></div>
			</label>
		</div>
		<div class="communication-item">	
			<label class="checkbox_button checkbox">
				<input type="checkbox" class="input-checkbox " name="allowMarketingCommunication" id="allowMarketingCommunication" value="1" <?php checked( $allowMarketingCommunication, 1 ); ?>>
				<div class="checkbox_button-check"></div>
				<div class="title"><?php _e( 'Разрешение на маркетинговые коммуникации', RIGHTWAY); ?></div>
			</label>
		</div>			
		<?php
/* 		woocommerce_form_field( 
			'allowSms', 
			array(
				'type'        	=> 'checkbox',
				'required'    	=> false,
				'class'       	=> array('form-row-wide'),
				'label'       	=> __( 'sms', RIGHTWAY),
				'value'			=> '',
				'default'		=> 1,
				'description' => '',
			), 
			$allowSms
		);

		woocommerce_form_field( 
			'allowEmail', 
			array(
				'type'        	=> 'checkbox',
				'required'    	=> false,
				'class'       	=> array('form-row-wide'),
				'label'       	=> __( 'Email', RIGHTWAY),
				'value'			=> '',
				'default'		=> 0,
				'description' => '',
			), 
			$allowEmail
		);
		woocommerce_form_field( 
			'allowMarketingCommunication', 
			array(
				'type'        	=> 'checkbox',
				'required'    	=> false,
				'class'       	=> array('form-row-wide'),
				'clear'			=> true,
				'label'       	=> __( 'Разрешение на маркетинговые коммуникации', RIGHTWAY),
				'value'			=> '',
				'default'		=> 0,
				'description' => '',
			), 
			get_user_meta( $user_id, 'allowMarketingCommunication', true )
		); */ ?>
		<input type="hidden" class="button" name="change_meta" value="change">
		
		<div class="cabinet_data-button">
			<input type="submit" class="form-submit" name="save_address" value="<?php _e('Сохранить', RIGHTWAY); ?>">
			</div>
		</form>
		<?php
/* 		if ( !empty( $user->roles ) && is_array( $user->roles ) ) {
			//Запрашиваем данные у платформы RW только в том случае, если пользователь customer
			if (in_array('customer',$user->roles)) {	
				$cardId = $this->rightWayAPI->getRWCard($user);
			}
		} */
	}

	/**
	 * AJAX: синхронизация флагов коммуникаций с RW по карте текущего пользователя ({@see RightWay::updateCommunicationData()}); идентификатор карты — мета `cardId`.
	 *
	 * @return void
	 */
	public function edit_RW_communiction_data() {
		$this->verify_rightway_ajax_nonce();
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( 'Требуется авторизация.' );
		}
		$cardId = get_user_meta( $user_id, 'cardId', true );
		if ( ! $cardId ) {
			wp_send_json_error( 'У учётной записи не указана карта лояльности.' );
		}

		$new_sms       = ( isset( $_POST['allowSms'] ) && 'true' === $_POST['allowSms'] );
		$new_email     = ( isset( $_POST['allowEmail'] ) && 'true' === $_POST['allowEmail'] );
		$new_marketing = ( isset( $_POST['allowMarketingCommunication'] ) && 'true' === $_POST['allowMarketingCommunication'] );
		$confirm_code  = isset( $_POST['confirmCode'] ) ? sanitize_text_field( wp_unslash( $_POST['confirmCode'] ) ) : '';

		try {
			$summaryBody = $this->rightWayAPI->getCardSummary( $cardId );
			$summaryArr  = json_decode( $summaryBody, true );
			$rw_com      = ( is_array( $summaryArr ) && isset( $summaryArr['communicationSettings'] ) && is_array( $summaryArr['communicationSettings'] ) )
				? $summaryArr['communicationSettings']
				: array();

			$rw_sms_on       = $this->rw_communication_flag_is_on( $rw_com['allowSms'] ?? false );
			$rw_email_on     = $this->rw_communication_flag_is_on( $rw_com['allowEmail'] ?? false );
			$rw_marketing_on = $this->rw_communication_flag_is_on( $rw_com['allowMarketingCommunication'] ?? false );
			$has_rw_changes  = ( $new_sms !== $rw_sms_on ) || ( $new_email !== $rw_email_on ) || ( $new_marketing !== $rw_marketing_on );

			if ( $has_rw_changes && '' === $confirm_code ) {
				wp_send_json_error( 'Отсутствует код подтверждения' );
			}

			Plugin::get()->log( 'allowSms=' . ( $new_sms ? 'true' : 'false' ) );
			Plugin::get()->log( 'allowEmail=' . ( $new_email ? 'true' : 'false' ) );
			Plugin::get()->log( 'allowMarketingCommunication=' . ( $new_marketing ? 'true' : 'false' ) );

			$communicationData = array(
				'allowSms'                      => $new_sms,
				'allowEmail'                    => $new_email,
				'allowMarketingCommunication'   => $new_marketing,
			);
			if ( $has_rw_changes ) {
				$communicationData['confirmationCode'] = $confirm_code;
			}

			$has_email   = false;
			if ( is_array( $summaryArr ) && ! empty( $summaryArr['contacts'] ) && is_array( $summaryArr['contacts'] ) ) {
				foreach ( $summaryArr['contacts'] as $c ) {
					if ( ! empty( $c['value'] ) && false !== strpos( $c['value'], '@' ) ) {
						$has_email = true;
						break;
					}
				}
			}
			if ( ! empty( $communicationData['allowEmail'] ) && ! $has_email ) {
				Plugin::get()->log( 'allowEmail сброшен: у карты в RW нет email-контакта.' );
				$communicationData['allowEmail'] = false;
			}
			$this->rightWayAPI->updateCommunicationData($communicationData, $cardId);
			wp_send_json_success('RW Custom Data are updated successfully.');
		}	catch (\Exception $e) {
			Plugin::get()->log( $e->getMessage() );
			wp_send_json_error($e->getMessage());
		}
	}

	/**
	 * AJAX: добавление контакта покупателю ({@see RightWay::createContactData()}).
	 *
	 * @return void
	 */
	  public function create_RW_contact_data() {
		$this->verify_rightway_ajax_nonce();
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( 'Требуется авторизация.' );
		}
		$customer_id = get_user_meta( $user_id, 'customerId', true );
		if ( ! $customer_id ) {
			wp_send_json_error( 'У учётной записи не привязан покупатель RightWay.' );
		}

		// Подготавливаем данные контакта в RW
		$contactValue = isset($_POST['value'])?str_replace(array(' ', '(' , ')', '-'), '', $_POST['value']):'';
		$contactData = array(
			"value" => $contactValue
		);

		try {
			$this->rightWayAPI->createContactData($contactData, $customer_id, $_POST['token']);
			wp_send_json_success( true );
		}	catch (\Exception $e) {
			Plugin::get()->log( $e->getMessage() );
			wp_send_json_error($e->getMessage(), 500);
		}
	}

	/**
	 * AJAX: обновление телефона и email контактов в RW из POST billing.
	 * Идентификатор покупателя RW берётся из user meta; contactId сверяется со списком контактов RW.
	 *
	 * @param mixed $contactType Зарезервировано; при вызове через {@see add_action()} может передаваться служебный аргумент WP.
	 * @return void
	 */
	public function edit_RW_contact_data($contactType) {
		$this->verify_rightway_ajax_nonce();

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( 'Требуется авторизация.' );
		}
		$customer_id = get_user_meta( $user_id, 'customerId', true );
		if ( ! $customer_id ) {
			wp_send_json_error( 'У учётной записи не привязан покупатель RightWay.' );
		}
		if ( ! isset( $_POST['contactId'] ) || '' === (string) $_POST['contactId'] ) {
			wp_send_json_error( 'Не указан идентификатор контакта.' );
		}
		$contact_id = sanitize_text_field( wp_unslash( $_POST['contactId'] ) );

		try {
			$this->assert_rw_contact_belongs_to_customer( $contact_id, $customer_id );
		} catch ( \Exception $e ) {
			Plugin::get()->log( $e->getMessage() );
			wp_send_json_error( $e->getMessage(), 500 );
		}

		$contactPhone = isset( $_POST['billing_phone'] ) ? str_replace( array( ' ', '(', ')', '-' ), '', wp_unslash( $_POST['billing_phone'] ) ) : '';
		$updated      = false;

		if ( $contactPhone ) {
			try {
				$contactData = array( 'value' => $contactPhone );
				$this->rightWayAPI->updateContactData( $contactData, $contact_id, $customer_id );
				$updated = true;
			} catch ( \Exception $e ) {
				Plugin::get()->log( $e->getMessage() );
				wp_send_json_error( $e->getMessage(), 500 );
			}
		}

		$contactEmail = isset( $_POST['email'] ) ? str_replace( array( ' ', '(', ')', '-' ), '', wp_unslash( $_POST['email'] ) ) : '';

		if ( $contactEmail ) {
			try {
				$contactData = array( 'value' => $contactEmail );
				$this->rightWayAPI->updateContactData( $contactData, $contact_id, $customer_id );
				$updated = true;
			} catch ( \Exception $e ) {
				Plugin::get()->log( $e->getMessage() );
				wp_send_json_error( $e->getMessage(), 500 );
			}
		}

		if ( ! $updated ) {
			wp_send_json_error( 'Не указаны телефон или email для обновления.', 400 );
		}
		wp_send_json_success( true );
	}



	/**
	 * AJAX: обновление анкеты покупателя в RW ({@see RightWay::updateCustomerData()}).
	 *
	 * @return void
	 */
	public function edit_RW_customer_data() {
		$this->verify_rightway_ajax_nonce();
		
		$user = wp_get_current_user();

		Plugin::get()->log( 'Inside edit_RW_customer_data function' );
		$updatedCustomerRWInfo = array();

		// Подготавливаем данные пользователя для обновления в RW
		$updatedCustomerRWInfo['firstName'] = (isset($_POST['billing_first_name']))? $_POST['billing_first_name']:'';
		$updatedCustomerRWInfo['middleName'] = '';
		$updatedCustomerRWInfo['lastName'] = (isset($_POST['billing_last_name']))? $_POST['billing_last_name']:'';
		$updatedCustomerRWInfo['gender'] =  (isset($_POST['gender']))?$_POST['gender']:'';
		$updatedCustomerRWInfo['birthDate'] = (isset($_POST['birthDate']))?$_POST['birthDate']:'';
		ob_start();
		print_r( $updatedCustomerRWInfo );
		Plugin::get()->log(ob_get_clean());
		/* $updatedCustomerRWInfo['confirmationCode'] = ''; */ // Вместо кода используем TSSA

		// Обновляем данные пользователя в RW
		try {
			$this->rightWayAPI->updateCustomerData( $updatedCustomerRWInfo, $_POST['customerId'] );
			wp_send_json_success('Данные покупателя обновлены благополучно');
		}	catch (\Exception $e) {
			Plugin::get()->log( $e->getMessage() );
			wp_send_json_error($e->getMessage());
		}
	}

	/**
	 * Поля даты рождения и пола на форме billing-адреса в ЛК.
	 *
	 * @param array<string, mixed> $fields Поля WC (не используются).
	 * @return void
	 */
	public function add_custom_user_fields($fields) {
		$user_id = get_current_user_id();
		echo '<div class="cabinet_data-form">';
		woocommerce_form_field( 
			'birthDate', 
			array(
				'type'        	=> 'date',
				'required'    	=> true,
				'class'       	=> array('form-row-first'),
				'clear'			=> false,
				'label'       	=>  __( 'Дата рождения', RIGHTWAY),
				'description' 	=> '',
			), 
			get_user_meta( $user_id, 'birthDate', true )
		);
		woocommerce_form_field( 
			'gender', 
			array(
				'type'        	=> 'select',
				'required'    	=> true,
				'class'       	=> array('form-row-first'),
				'clear'			=> true,
				'label'       	=>  __( 'Пол', RIGHTWAY),
				'options'     	=> array(
					'm' => __('мужской'),
					'f' => __('женский')),
				'default'		=> 'm',
				'description' => '',
			), 
			get_user_meta( $user_id, 'gender', true )
		);
		echo '</div>';		
	}

	/**
	 * Сохранение birthDate, gender, customerId, cardId, cardNumber при сохранении billing-адреса.
	 *
	 * @param int    $user_id       ID пользователя.
	 * @param string $load_address  Группа адреса WC (`billing` / `shipping`).
	 * @return void
	 */
	public function save_custom_user_fields($user_id, $load_address) {
		if ('billing' !== $load_address) {
			return;
		}
		if ( isset( $_POST['birthDate'] ) ) {
			update_user_meta( $user_id, 'birthDate', $_POST['birthDate']);
		}
		if ( isset( $_POST['gender'] ) ) {
			update_user_meta( $user_id, 'gender', $_POST['gender']);
		}
		if ( isset( $_POST['customerId'] ) ) {
			update_user_meta( $user_id, 'customerId', $_POST['customerId']);
		}
		if ( isset( $_POST['cardId'] ) ) {
			update_user_meta( $user_id, 'cardId', $_POST['cardId']);
		}
		if ( isset( $_POST['cardNumber'] ) ) {
			update_user_meta( $user_id, 'cardNumber', $_POST['cardNumber']);
		}									
	}

	/**
	 * AJAX: JSON summary карты текущего пользователя ({@see RightWay::getCardSummary()}).
	 *
	 * @return void
	 */
	public function get_card_summary() {	
		$this->verify_rightway_ajax_nonce();
		$user = wp_get_current_user();
		$cardId = get_user_meta( $user->ID, 'cardId', true );
		$cardNumber = get_user_meta( $user->ID, 'cardNumber', true );
		$contactId = get_user_meta( $user->ID, 'contactId', true );
		$phone = str_replace( array(' ', '(' , ')', '-'), '', get_user_meta( $user->ID, 'billing_phone', true ) );
		if (strpos($phone,'+') === false ) { $phone = '+'.$phone; }

		// Получаем персональную информацию о владельце карты и информацию о состоянии карты
		try {
			$cardSummary = $this->rightWayAPI->getCardSummary($cardId);
			$cardSummaryArray = json_decode( $cardSummary, true );
			Plugin::get()->log( $cardSummary );
			wp_send_json_success($cardSummary);
		}	catch (\Exception $e) {
			Plugin::get()->log( $e->getMessage() );
			wp_send_json_error($e->getMessage());
		}
	}

	/**
	 * AJAX: список контактов покупателя RW по полю POST `customerId`.
	 *
	 * @return void
	 */
	public function get_customer_contacts() {
		$this->verify_rightway_ajax_nonce();
		if (!isset($_POST['customerId'])) {
			wp_send_json_error('Не указан идентификатор пользователя!');
		}

		try {
			$customerСontacts = $this->rightWayAPI->getCustomerContacts($_POST['customerId']);
			wp_send_json_success($customerСontacts);
		}	catch (\Exception $e) {
			Plugin::get()->log( $e->getMessage() );
			wp_send_json_error($e->getMessage());
		}
	}

	/**
	 * AJAX: число записей покупателей RW с тем же телефоном или email (проверка уникальности).
	 *
	 * @return void
	 */
	public function get_RW_customers_quantity() {
		$this->verify_rightway_ajax_nonce();
		if ( !isset($_POST['phone']) && !isset($_POST['email']) ) {
			wp_send_json_error('Не указан контакт!');
		}

		if ( isset($_POST['phone']) ) {
			try {
				$customersArray = $this->rightWayAPI->getCustomersByPhone($_POST['phone']);
				wp_send_json_success(count($customersArray));
			}	catch (\Exception $e) {
				Plugin::get()->log( $e->getMessage() );
				wp_send_json_error($e->getMessage());
			}
		}

		if ( isset($_POST['email']) ) {
			try {
				$customersArray = $this->rightWayAPI->getCustomersByEmail($_POST['email']);
				wp_send_json_success(count($customersArray));
			}	catch (\Exception $e) {
				Plugin::get()->log( $e->getMessage() );
				wp_send_json_error($e->getMessage());
			}
		}

	}

	/**
	 * AJAX: поиск покупателей/карт в RW по телефону или email; при нескольких картах — логика merge.
	 *
	 * @return void
	 */
	  public function get_RW_customers() {
		$this->verify_rightway_ajax_nonce();
		if ( !isset($_POST['phone']) && !isset($_POST['email']) ) {
			wp_send_json_error('Не указан контакт!');
		}

		if ( isset($_POST['phone']) ) {
			try {
				//Значение $_POST['phone'] должен соотвествоваь шаблону '^\\+7\\d{10}$'. Нужно проверять, и если не соответствует,
				//то добавлять +7 в начало и передавать в запрос
				if (!preg_match('/^\+7\d{10}$/', $_POST['phone'])) {
					$_POST['phone'] = '+7'.$_POST['phone'];
				}
				$customersArray = $this->rightWayAPI->getCustomersByPhone($_POST['phone']);
				Plugin::get()->log( "Все пользователи с контактом ".$_POST['phone'] );
				Plugin::get()->log( $customersArray );
				if (count($customersArray) > 1) {
					//Запрашиваем данные карт с таким номером телефона
					try {
						$allCardsArray = $this->rightWayAPI->getCardsData($_POST['phone']);
						Plugin::get()->log( "У пользователя в RW несколько карт:");
						Plugin::get()->log( $allCardsArray );

						//Отсеиваем заблокированные карты
						$cardsArray = array();
						foreach ($allCardsArray as $key => $card) {
							if (!$card['isBlocked']) {
								$cardsArray[] = $card;
							}
						}
						Plugin::get()->log( "Незаблокированные карты:");
						Plugin::get()->log( $cardsArray );

						if (count($cardsArray)) {
							$cardsToMerge = array();
							//Выбираем карту с наибольшим номером и все остальные незаблокированные объединяем с выбранной
							$maxCardId = $cardsArray['0']['id'];
							foreach ($cardsArray as $key => $card) {
								if ($card['id'] >= $maxCardId) {
									$maxCardId = $card['id'];
									$customerId = $card['customerId']; //Запоминаем id кастомера, чью карту будем оставлять активной
								} else {
									$cardsToMerge[] = $card['id'];
								}
							}
							Plugin::get()->log( "Карта с максимальным номером: ".$maxCardId );

							$customerArray = array(); // Массив данных кастомера, чью карту будем оставлять иктивной
							foreach($customersArray as $customer) {
								if ( $customer['id'] == $customerId ) {
									$customerArray = $customer;
								}
							}
							Plugin::get()->log( "Данные кастомера, чью карту оставляем активной: " );
							Plugin::get()->log( array($customerArray) );

							// Если нет незаблокированных карт для объединения, возвращаем данные пользователя активной карты
							if (count($cardsToMerge) == 0) {
								Plugin::get()->log( "Нет незаблокированных карт для объединения" );
								$out = array( $customerArray );
								Plugin::get()->log( '[rightway_get_customers] outgoing JSON (phone, merge branch, cardsToMerge empty): ' . wp_json_encode( $out, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ) );
								wp_send_json_success( $out );
							}

							Plugin::get()->log( "Незаблокированные карты с контактом ".$_POST['phone'].":" );
							Plugin::get()->log( $cardsArray );
							Plugin::get()->log( "Карты, которые нужно объединить: " );
							Plugin::get()->log( $cardsToMerge );
							if (!isset($_POST['rwToken']) || !$_POST['rwToken']){
								wp_send_json_error('Не получен токен для выполнения объединения карт');
							}
							$cardsData = array (
								"resultCardId"=> $maxCardId,
								"mergeCardsIds"=> $cardsToMerge,
								"tokens" => array($_POST['rwToken'])
							);
							Plugin::get()->log( "body запроса на объединение: " );
							Plugin::get()->log( $cardsData );
							try {
								$this->rightWayAPI->mergeCards($cardsData);
								$out = array( $customerArray );
								Plugin::get()->log( '[rightway_get_customers] outgoing JSON (phone, after mergeCards): ' . wp_json_encode( $out, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ) );
								wp_send_json_success( $out );
							} catch (\Exception $e) {
								Plugin::get()->log( $e->getMessage() );
								wp_send_json_error($e->getMessage());
							}
						} else {
							Plugin::get()->log( 'Несколько покупателей с телефоном '.$_POST['phone'].', незаблокированных карт нет — ответ списком покупателей' );
						}
					} catch (\Exception $e) {
						Plugin::get()->log( $e->getMessage() );
						wp_send_json_error($e->getMessage());
					}
				}

				Plugin::get()->log( '[rightway_get_customers] outgoing JSON (phone, default branch): ' . wp_json_encode( $customersArray, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ) );
				wp_send_json_success($customersArray);
			}	catch (\Exception $e) {
				Plugin::get()->log( $e->getMessage() );
				wp_send_json_error($e->getMessage());
			}
		}

		if ( isset($_POST['email']) ) {
			try {
				$customersArray = $this->rightWayAPI->getCustomersByEmail($_POST['email']);
				Plugin::get()->log( '[rightway_get_customers] outgoing JSON (email): ' . wp_json_encode( $customersArray, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ) );
				wp_send_json_success($customersArray);
			}	catch (\Exception $e) {
				Plugin::get()->log( $e->getMessage() );
				wp_send_json_error($e->getMessage());
			}
		}

	}

	/**
	 * AJAX: JSON карт покупателя по полю POST `customerId` ({@see RightWay::getCustomerCardData()}).
	 *
	 * @return void
	 */
	  public function get_RW_customer_cards() {
		$this->verify_rightway_ajax_nonce();
		if ( !isset($_POST['customerId']) && !$_POST['customerId']) {
			wp_send_json_error('Не указан customerId!');
		}

		try {
			$cardsArray = $this->rightWayAPI->getCustomerCardData($_POST['customerId']);
			wp_send_json_success($cardsArray);
		}	catch (\Exception $e) {
			Plugin::get()->log( $e->getMessage() );
			wp_send_json_error($e->getMessage());
		}

	}	

	/**
	 * AJAX: регистрация в RW и создание карты ({@see RightWay::registerRWClient()}, {@see RightWay::createRWCard()}).
	 *
	 * @return void
	 */
	public function create_RW_customer() {
		$this->verify_rightway_ajax_nonce();
		Plugin::get()->log( json_encode($_POST ));
		if ( !isset($_POST['contact']) && !isset($_POST['value'] ) ) {
			wp_send_json_error('Не указан телефон или email!');
		}	

		if ( !isset($_POST['token']) ) {
			wp_send_json_error('Не указан token!');
		}

		$firstName = (isset($_POST['firstName']))?$_POST['firstName']:'';
		$lastName = (isset($_POST['lastName']))?$_POST['lastName']:'';
		$birthDate = (isset($_POST['birthDate']))?$_POST['birthDate']:'';
		$gender = (isset($_POST['gender']))?$_POST['gender']:'';

		$result = false;
		

		// ВРЕМЕННО ДЛЯ ТЕСТА!!!
		/********************* */
/* 		$resultArray = array(
			'id' => '74634842',
			'brandId' => '165',
			'number' => '8008100711194',
			'customerId' => '82677582'
		);
		wp_send_json_success(json_encode($resultArray)); */
		/**********************/

		// Отправляем запрос на регистрацию пользователя
		try {
			$result = $this->rightWayAPI->registerRWClient($_POST['contact'],$_POST['value'], $_POST['token'], $firstName, $lastName, $birthDate, $gender);
			Plugin::get()->log( $result );
		}	catch (\Exception $e) {
			Plugin::get()->log( $e->getMessage() );
			wp_send_json_error($e->getMessage());
		}

		if ($result) {
			$resultArray = json_decode($result, true);
			$customerId = $resultArray[ 'id' ];
			Plugin::get()->log( 'customerId='.$customerId );
		// Отправляем запрос на создание бонусной карты
			try {
				$result = $this->rightWayAPI->createRWCard($customerId);
				$resultArray = json_decode($result, true);
				$resultArray['customerId'] = $customerId;
				$result = json_encode($resultArray);
				ob_start();
				var_dump($result);
				Plugin::get()->log( ob_get_clean() );
				wp_send_json_success($result);
			}	catch (\Exception $e) {
				Plugin::get()->log( $e->getMessage() );
				wp_send_json_error($e->getMessage());
			}
		}
	}
	
	/**
	 * Скрытые поля checkout для номера карты, документа RW, операции, бонусов и XML-чека.
	 *
	 * @return void
	 */
	public function add_checkout_hidden_field(){
		echo '<div id="rw_doc_number_hidden_checkout_field">
		<input type="hidden" class="input-hidden" name="rw_card_number" id="rw_card_number" value="">
		<input type="hidden" class="input-hidden" name="rw_doc_number" id="rw_doc_number" value="">
		<input type="hidden" class="input-hidden" name="rw_card_operation" id="rw_card_operation" value="СписаниеИНачисление">
		<input type="hidden" class="input-hidden" name="active_bonuses" id="active_bonuses" value="">
		<input type="hidden" class="input-hidden" name="rw_discount" id="rw_discount" value="">
		<input type="hidden" class="input-hidden" name="rw_cheque" id="rw_cheque" value="">
		</div>';
	}

	/**
	 * Сохранение скрытых полей RW в метаданные заказа при оформлении.
	 *
	 * @param int $order_id ID заказа.
	 * @return void
	 */
	  public function save_custom_checkout_hidden_field($order_id){
		if ( ! empty( $_POST['rw_card_number'] ) ) {
			update_post_meta( $order_id, 'rw_card_number', sanitize_text_field( $_POST['rw_card_number'] ) );
		}
		if ( ! empty( $_POST['rw_doc_number'] ) ) {
			update_post_meta( $order_id, 'rw_doc_number', sanitize_text_field( $_POST['rw_doc_number'] ) );
		}
		if ( ! empty( $_POST['rw_card_operation'] ) ) {
			update_post_meta( $order_id, 'rw_card_operation', sanitize_text_field( $_POST['rw_card_operation'] ) );
		}
		if ( ! empty( $_POST['rw_cheque'] ) ) {
			update_post_meta( $order_id, 'rw_cheque', ( $_POST['rw_cheque'] ) );
		}
	}

	/**
	 * При создании учётной записи на checkout: сохранение customerId, cardId, cardNumber из POST.
	 *
	 * @param int   $customer_id ID нового пользователя.
	 * @param array $posted      Данные формы checkout.
	 * @return void
	 */
	public function save_custom_user_hidden_field($customer_id, $posted){
		Plugin::get()->log( json_encode($posted) );
		if (!isset($posted['createaccount'])) {
			return;
		}
		if (isset($_POST['customerId'])) {
			update_user_meta( $customer_id, 'customerId', sanitize_text_field( $_POST['customerId'] ) );
		}
		if (isset($_POST['cardId'])) {
			update_user_meta( $customer_id, 'cardId', sanitize_text_field( $_POST[ 'cardId' ] ) );
		}
		if (isset($_POST['cardNumber'])) {
			update_user_meta( $customer_id, 'cardNumber', sanitize_text_field( $_POST[ 'cardNumber' ] ) );
		}
	}

	/**
	 * Разметка модального окна ввода кода подтверждения в подвале страниц ЛК.
	 *
	 * @return void
	 */
	public function add_confirm_modal_template() {
		// Проверяем, что это страница личного кабинета WooCommerce
		if ( is_account_page() ) {
			?>
			<!-- Добавьте в footer или куда-то в шаблон -->
			<div id="confirm-modal-template" class="confirm-modal-template modal "style="display: none;">
				<div class="fancybox-content-wrapper">
					<h2>Подтверждение</h2>
					<div class="o-sms-auth-modal-phone-sent-notifier__container check_code">
					<div data-v-28034098="" class="o-sms-auth-modal-phone-sent-notifier__wrapper">
						<div data-v-28034098="" class="o-sms-auth-modal-phone-sent-notifier__text">
							<p data-v-28034098="" >Код выслан на <span class="contact-sent">+7 (999) 999 99 99</span></p>
						</div>
					</div>
					</div>
					<form id="enter-confirm-code" method="POST">
					<div data-v-033d26ce="" class="o-sms-auth-modal-inputs__container">
						<div data-v-033d26ce="" class="o-sms-auth-modal-inputs__wrapper sms">
							<div data-v-450698ef="" data-v-033d26ce="" class="o-multi-field-code__container o-sms-auth-modal-inputs__checkbox-wrapper">
								<div data-v-450698ef="" class="o-multi-field-code__inputs-wrapper">
									<div data-v-450698ef="" class="o-multi-field-code__input-wrapper">
										<input data-v-450698ef="" id="code-input-0" class="confirm-code-input" type="text" name="codeNumber0" tabindex="1" inputmode="numeric" autocomplete="off" maxlength="1">
									</div>
									<div data-v-450698ef="" class="o-multi-field-code__input-wrapper">
										<input data-v-450698ef="" id="code-input-1" class="confirm-code-input" type="text" name="codeNumber1"  tabindex="2" inputmode="numeric" autocomplete="off" maxlength="1">
									</div>
									<div data-v-450698ef="" class="o-multi-field-code__input-wrapper">
										<input data-v-450698ef="" id="code-input-2" class="confirm-code-input" type="text" name="codeNumber2" tabindex="3" inputmode="numeric" autocomplete="off" maxlength="1">
									</div>
									<div data-v-450698ef="" class="o-multi-field-code__input-wrapper">
										<input data-v-450698ef="" id="code-input-3" class="confirm-code-input" type="text" name="codeNumber3" tabindex="4" inputmode="numeric" autocomplete="off" maxlength="1">
									</div>
									<ul data-v-450698ef="" class="o-multi-field-code__messages"></ul>
								</div>
							</div>
						</div>
					</div>
					<div data-v-7e394bb2="" id="timer-block" class="o-sms-auth-modal-sms-sent-notifier__container check_code">
						<div data-v-7e394bb2="" class="o-sms-auth-modal-sms-sent-notifier__message link">
							<p data-v-7e394bb2="">Повторная отправка доступна через <span id="timerText"></span></p>
						</div>
					</div>
					<div data-v-7e394bb2="" id="send-again" class="o-sms-auth-modal-sms-sent-notifier__container check_code" style="display:none">
						<div data-v-7e394bb2="" class="o-sms-auth-modal-sms-sent-notifier__message link">
							<p data-v-7e394bb2="">Получить код повторно</p>
						</div>
					</div>
					<input type="hidden" class="input-text" name="confirm_code" id="confirm_code" placeholder="" value="" autocomplete="" required>
					<p class="form-row form-row-wide woocommerce-error" style="display: none;"></p>
					</form>
				</div>
			</div>
			<?php
		}
	}

}