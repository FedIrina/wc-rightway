<?php
/**
 * HTTP-клиент к REST API RightWay и к XML processing (бонусы, чеки).
 *
 * Используется из {@see \RIGHTWAY\Plugin} как {@see \RIGHTWAY\Plugin::$rightWayAPI}.
 *
 * @package RIGHTWAY
 */
namespace RIGHTWAY;

class RightWay {

    const LOGFILE = 'rightway.log';

    /** @var int|string Идентификатор бренда в RW. */
    public $brandId;
    /** @var string Название магазина для запросов. */
    public $shopName;
    /** @var mixed Кэш бонусов (если используется). */
    private $customerRWBonuses;

    /**
     * Ключи API, базовые URL эндпоинтов RightWay.
     */
    private $wc_rightway_api_key;
    private $wc_rightway_api_version;
    private $wc_rightway_tssa_key;
    private $wc_rightway_x_processing_key;
    private $wc_rightway_x_processing_version;
    private $rwApiUrlCustomers;
    private $rwApiUrlCustomersSearch;
    private $rwApiUrlCardSearch;
    private $rwApiUrlAuth;
    private $rwApiUrlRegister;
    private $rwApiUrlCard;
    /** @var string URL POST card/merge. */
    private $rwApiUrlCardMerge;
    private $rwApiUrlContacts;
    private $rwProcessingUrl;

    /**
     * Сохраняет учётные данные API и инициализирует URL сервисов RW.
     *
     * @param string|int $wc_rightway_brand_id Идентификатор бренда.
     * @param string     $wc_rightway_shop_name Название магазина.
     * @param string     $wc_rightway_api_key Ключ X-API-Key.
     * @param string     $wc_rightway_api_version Версия X-API-Version.
     * @param string     $wc_rightway_tssa_key Ключ для запросов от имени клиента (TSSA).
     * @param string     $wc_rightway_x_processing_version Версия processing API.
     * @param string     $wc_rightway_x_processing_key Ключ processing API.
     * @return void
     */
    public function __construct($wc_rightway_brand_id, $wc_rightway_shop_name, $wc_rightway_api_key, $wc_rightway_api_version, $wc_rightway_tssa_key, $wc_rightway_x_processing_version, $wc_rightway_x_processing_key) {
        $this->wc_rightway_api_key = $wc_rightway_api_key;
        $this->wc_rightway_api_version = $wc_rightway_api_version;
        $this->wc_rightway_tssa_key = $wc_rightway_tssa_key;
        $this->wc_rightway_x_processing_key = $wc_rightway_x_processing_key;
        $this->wc_rightway_x_processing_version = $wc_rightway_x_processing_version;
        $this->brandId = $wc_rightway_brand_id;
        $this->shopName = $wc_rightway_shop_name;
        $this->rwApiUrlCustomers = 'https://rightway-api.omnichannel.ru/externalApi/customers';
        $this->rwApiUrlCustomersSearch = 'https://rightway-api.omnichannel.ru/externalApi/customers/search';
        $this->rwApiUrlCardSearch = 'https://rightway-api.omnichannel.ru/externalApi/card/search';
        $this->rwApiUrlCardMerge = 'https://rightway-api.omnichannel.ru/externalApi/card/merge';
        $this->rwApiUrlAuth = 'https://rightway-api.omnichannel.ru/externalApi/auth';
        $this->rwApiUrlRegister = 'https://rightway-api.omnichannel.ru/externalApi/register';
        $this->rwApiUrlCard = 'https://rightway-api.omnichannel.ru/externalApi/card';
        $this->rwApiUrlContacts = 'https://rightway-api.omnichannel.ru/externalApi/contacts';
        $this->rwProcessingUrl = 'https://rightway-processing.omnichannel.ru';
    }

    /**
     * Email для поиска клиента/карты в RW — billing_email (как на checkout).
     *
     * @param \WP_User $user
     * @return string
     */
    private function customerEmailForRw( $user ) {
        if ( ! $user || empty( $user->ID ) ) {
            return '';
        }
        return trim( (string) \get_user_meta( $user->ID, 'billing_email', true ) );
    }

    /**
     * Поиск customerId в RW по email из billing пользователя (устаревший сценарий; см. {@see getCustomerInfo()}).
     *
     * @param \WP_User $user Пользователь WordPress.
     * @return int Идентификатор покупателя RW или 0.
     * @throws \Exception При HTTP-ошибке ответа RW.
     */
    public function getCustomerId($user) {  //ИЗБЫТОЧНЫЙ МЕТОД! CustomerId можно получить из инфы,
                                            // возвращаемой методом gerCustomerInfo
        $customerId = 0;
        $parameters = 'email='.urlencode( $this->customerEmailForRw( $user ) ).'&lastId=1&resultsPerPage=100';
        //$parameters = 'email=05abdul%40mail.ru&lastId=1&resultsPerPage=100';
        $args = array( 
            'headers'   => array(
                'timeout'   => 60,
                'Accept' => 'application/json',
                'X-API-Version' => $this->wc_rightway_api_version,
                'X-API-Key' => $this->wc_rightway_api_key
            )
        );

        // Отправляем запрос
        $response = wp_remote_get( $this->rwApiUrlCustomersSearch . '?'.$parameters, $args );
        $responseCode = wp_remote_retrieve_response_code($response);
        Plugin::get()->log( sprintf( __('Поиск покупателя: %s', 'rightway' ), wp_remote_retrieve_body( $response ) ) );
        /* file_put_contents('/var/www/medknigaservis.ru/dev/wp-content/themes/medknigaservis/inc/'.self::LOGFILE, .PHP_EOL,FILE_APPEND); */
        
        if (200 == wp_remote_retrieve_response_code($response)) {
            $bodyArray = json_decode( wp_remote_retrieve_body( $response ),true);
            if (count($bodyArray) !=0) {
                $customerId = (int)$bodyArray['0']['id'];
            }       
        } else {
            throw new \Exception(wp_remote_retrieve_response_code($response).' '.wp_remote_retrieve_response_message($response));
        }
        Plugin::get()->log( sprintf( __('Ответ RW на запрос поиска клиента: %s', 'rightway' ), $customerId ) );
        return $customerId;
        }

    /**
     * Поиск покупателей RW по телефону (GET customers/search).
     *
     * @param string $phone Нормализованный номер телефона.
     * @return array<int, array<string, mixed>>|int Декодированный JSON или 0 до запроса; при ошибке — исключение.
     * @throws \Exception При кодах ответа 400, 401, 403 и др.
     */
    public function getCustomersByPhone($phone) {
        $customersArray = 0;
        $parameters = 'phone='.urlencode($phone).'&resultsPerPage=100';
        $args = array( 
        'headers'   => array(
        'timeout'   => 60,
        'Accept' => 'application/json',
        'X-API-Version' => $this->wc_rightway_api_version,
        'X-API-Key' => $this->wc_rightway_api_key
        )
        );

        // Отправляем запрос
        $response = wp_remote_get( $this->rwApiUrlCustomersSearch . '?'.$parameters, $args );
        $responseCode = wp_remote_retrieve_response_code($response);
        Plugin::get()->log($this->rwApiUrlCustomersSearch . '?'.$parameters);
        Plugin::get()->log( sprintf( __('Поиск покупателей: %s', 'rightway' ), wp_remote_retrieve_body( $response ) ) );

        switch ($responseCode) {
            case 200:
                $customersArray = json_decode( wp_remote_retrieve_body( $response ),true);
                break;
            case 400:
                throw new \Exception('В запросе переданы неверные данные '.wp_remote_retrieve_response_message($response));
                break;
            case 401:
                throw new \Exception('Ошибка авторизации');
                break;
            case 403:
                throw new \Exception('Доступ запрещен');
                break;
            default:
                throw new \Exception(wp_remote_retrieve_response_code($response).' '.wp_remote_retrieve_response_message($response));
        }

        return $customersArray;
    }

    /**
     * Поиск покупателей RW по email (GET customers/search).
     *
     * @param string $email Email.
     * @return array<int, array<string, mixed>>|int Декодированный JSON или 0 до запроса.
     * @throws \Exception При ошибке HTTP.
     */
    public function getCustomersByEmail($email) {
        $customersArray = 0;
        $parameters = 'email='.urlencode($email).'&resultsPerPage=100';
        $args = array( 
        'headers'   => array(
        'timeout'   => 60,
        'Accept' => 'application/json',
        'X-API-Version' => $this->wc_rightway_api_version,
        'X-API-Key' => $this->wc_rightway_api_key
        )
        );

        // Отправляем запрос
        $response = wp_remote_get( $this->rwApiUrlCustomersSearch . '?'.$parameters, $args );
        $responseCode = wp_remote_retrieve_response_code($response);
        Plugin::get()->log($this->rwApiUrlCustomersSearch . '?'.$parameters);
        Plugin::get()->log( sprintf( __('Поиск покупателей: %s', 'rightway' ), wp_remote_retrieve_body( $response ) ) );

        switch ($responseCode) {
            case 200:
                $customersArray = json_decode( wp_remote_retrieve_body( $response ),true);
                break;
            case 400:
                throw new \Exception('В запросе переданы неверные данные '.wp_remote_retrieve_response_message($response));
                break;
            case 401:
                throw new \Exception('Ошибка авторизации');
                break;
            case 403:
                throw new \Exception('Доступ запрещен');
                break;
            default:
                throw new \Exception(wp_remote_retrieve_response_code($response).' '.wp_remote_retrieve_response_message($response));
        }

        return $customersArray;
    }

    /**
     * Поиск анкет покупателей в RW по billing_email пользователя.
     *
     * @param \WP_User $user Пользователь WordPress.
     * @return array<int, array<string, mixed>>|false Массив записей RW или false, если список пуст.
     * @throws \Exception При ошибке HTTP.
     */
    public function getCustomerInfo($user) {

        $customerId = 0;
        $parameters = 'email='.urlencode( $this->customerEmailForRw( $user ) ).'&lastId=1&resultsPerPage=100';
        //$parameters = 'email=05abdul%40mail.ru&lastId=1&resultsPerPage=100';
        $args = array( 
            'headers'   => array(
                'timeout'   => 60,
                'Accept' => 'application/json',
                'X-API-Version' => $this->wc_rightway_api_version,
                'X-API-Key' => $this->wc_rightway_api_key
            )
        );

        // Отправляем запрос
        $response = wp_remote_get( $this->rwApiUrlCustomersSearch . '?'.$parameters, $args );
        $responseCode = wp_remote_retrieve_response_code($response);
        switch ($responseCode) {
            case 200:
                $bodyArray = json_decode( wp_remote_retrieve_body( $response ),true);
                if (count($bodyArray) !=0) {
                    $customerInfo = $bodyArray;
                    return $customerInfo;
                } else {
                    //Что делать в этом случае?
                    //throw new \Exception('Пользователь '.$user->user_email.' в базе RightWay не найден.'.wp_remote_retrieve_response_message($response));
                }            
                break;
            case 400:
                throw new \Exception('В запросе переданы неверные данные '.wp_remote_retrieve_response_message($response));
                break;
            case 401:
                throw new \Exception('Ошибка авторизации');
                break;
            case 403:
                throw new \Exception('Доступ запрещен');
                break;
            default:
                throw new \Exception(wp_remote_retrieve_response_code($response).' '.wp_remote_retrieve_response_message($response));
        }        
        return false;
    }    

    /**
     * Идентификатор первого контакта (по email-поиску анкеты), устаревший вспомогательный метод.
     *
     * @param \WP_User $user Пользователь WordPress.
     * @return int ID контакта в RW или 0.
     * @throws \Exception При ошибке HTTP.
     */
    public function getContactId($user) {   //ИЗБЫТОЧНЫЙ МЕТОД! CustomerId можно получить из инфы,
                                            // возвращаемой методом gerCustomerInfo

        $contactId = 0;
        $parameters = 'email='.urlencode( $this->customerEmailForRw( $user ) ).'&lastId=1&resultsPerPage=100';
        //$parameters = 'email=05abdul%40mail.ru&lastId=1&resultsPerPage=100';
        $args = array( 
            'headers'   => array(
                'timeout'   => 60,
                'Accept' => 'application/json',
                'X-API-Version' => $this->wc_rightway_api_version,
                'X-API-Key' => $this->wc_rightway_api_key
            )
        );

        // Отправляем запрос
        $response = wp_remote_get( $this->rwApiUrlCustomersSearch . '?'.$parameters, $args );
        $responseCode = wp_remote_retrieve_response_code($response);
        
        if (200 == wp_remote_retrieve_response_code($response)) {
            $bodyArray = json_decode( wp_remote_retrieve_body( $response ),true);
            if (count($bodyArray) !=0) {
                if (count($bodyArray['0']['contacts'])) {
                    $contactId = (int)$bodyArray['0']['contacts']['0']['id'];
                }
            }       
        } else {
            throw new \Exception(wp_remote_retrieve_response_code($response).' '.wp_remote_retrieve_response_message($response));
        }
        Plugin::get()->log( sprintf( __('Ответ RW на запрос поиска контакта: %s', 'rightway' ), $contactId ) );
        return $contactId;
    }    

    /**
     * Поиск карт по телефону и brandId (GET card/search).
     *
     * @param string $phone Телефон.
     * @return array<int, array<string, mixed>> Декодированный JSON массива карт.
     * @throws \Exception При ошибке HTTP.
     */
    public function getCardsData($phone) {

        $parameters = 'brandId='.$this->brandId.'&phone='.urlencode($phone).'&lastId=1&resultsPerPage=100';
        
        $args = array( 
            'headers'   => array(
                'timeout'   => 60,
                'Accept' => 'application/json',
                'X-API-Version' => $this->wc_rightway_api_version,
                'X-API-Key' => $this->wc_rightway_api_key
            )
        );

        // Отправляем запрос
        $response = wp_remote_get( $this->rwApiUrlCardSearch . '?'.$parameters, $args );
        $responseCode = wp_remote_retrieve_response_code($response);
        Plugin::get()->log($this->rwApiUrlCardSearch . '?'.$parameters);
        /* Plugin::get()->log( sprintf( __('Тело ответа на запрос поиска карты: %s', 'rightway' ), wp_remote_retrieve_body( $response ) ) ); */
        
        if (200 == wp_remote_retrieve_response_code($response)) {
            $bodyArray = json_decode( wp_remote_retrieve_body( $response ),true);       
        } else {
            throw new \Exception(wp_remote_retrieve_response_code($response).' '.wp_remote_retrieve_response_message($response));
        }

        return $bodyArray;
    }

    /**
     * Первая карта из поиска по email пользователя (billing_email).
     *
     * @param \WP_User $user Пользователь WordPress.
     * @return array<string, mixed>|null Элемент карты или null, если не найдено.
     * @throws \Exception При ошибке HTTP.
     */
    public function getCardInfo($user) {

        $cardId = 0;
        $parameters = 'brandId='.$this->brandId.'&email='.urlencode( $this->customerEmailForRw( $user ) ).'&lastId=1&resultsPerPage=100';
        
        $args = array( 
            'headers'   => array(
                'timeout'   => 60,
                'Accept' => 'application/json',
                'X-API-Version' => $this->wc_rightway_api_version,
                'X-API-Key' => $this->wc_rightway_api_key
            )
        );

        // Отправляем запрос
        $response = wp_remote_get( $this->rwApiUrlCardSearch . '?'.$parameters, $args );
        $responseCode = wp_remote_retrieve_response_code($response);
        /* Plugin::get()->log( sprintf( __('Тело ответа на запрос поиска карты: %s', 'rightway' ), wp_remote_retrieve_body( $response ) ) ); */
        
        if (200 == wp_remote_retrieve_response_code($response)) {
            $bodyArray = json_decode( wp_remote_retrieve_body( $response ),true);
            if (count($bodyArray) !=0) {
                $cardInfo = $bodyArray['0'];
            }       
        } else {
            throw new \Exception(wp_remote_retrieve_response_code($response).' '.wp_remote_retrieve_response_message($response));
        }

        return $cardInfo;
    }    

    /**
     * Проверяет наличие карты у пользователя по email; возвращает id карты или false.
     *
     * @param \WP_User $user Пользователь WordPress.
     * @return int|string|false ID карты RW или false, если карт нет.
     * @throws \Exception При ошибке HTTP.
     */
    public function getRWCard($user) {

        $cardId = 0;
        $parameters = 'brandId='.$this->brandId.'&email='.urlencode( $this->customerEmailForRw( $user ) );
        //$parameters = 'brandId='.$this->brandId.'&email=05abdul%40mail.ru';
        
        $args = array( 
            'headers'   => array(
                'timeout'   => 60,
                'Accept' => 'application/json',
                'X-API-Version' => $this->wc_rightway_api_version,
                'X-API-Key' => $this->wc_rightway_api_key
            )
        );

        // Отправляем запрос
        $response = wp_remote_get( $this->rwApiUrlCardSearch . '?'.$parameters, $args );
        $responseCode = wp_remote_retrieve_response_code($response);
        Plugin::get()->log( sprintf( __('Тело ответа на запрос аккаунта: %s', 'rightway' ), wp_remote_retrieve_body( $response ) ) );
        
        if (200 == wp_remote_retrieve_response_code($response)) {
            $bodyArray = json_decode( wp_remote_retrieve_body( $response ),true);
            Plugin::get()->log( wp_json_encode( $bodyArray, JSON_UNESCAPED_UNICODE ) );
            //Делаем дополнительную проверку на непустой body, так как на практике встретился случай, когда для неклиента был получен код 200
            if (count($bodyArray) !=0) {
                return $bodyArray[0]['id']; // Возвращаем идентификатор карты (что делать, если карт несколько?)
            }       
        } else {
            throw new \Exception(wp_remote_retrieve_response_code($response).' '.wp_remote_retrieve_response_message($response));
        }

        return false;
    }

    /**
     * CustomerId первой карты, найденной по email пользователя.
     *
     * @param \WP_User $user Пользователь WordPress.
     * @return int|string|false customerId в RW или false.
     * @throws \Exception При ошибке HTTP.
     */
    public function getRWCustomerId($user) {

        $cardId = 0;
        $parameters = 'brandId='.$this->brandId.'&email='.urlencode( $this->customerEmailForRw( $user ) );
        //$parameters = 'brandId='.$this->brandId.'&email=05abdul%40mail.ru';
        
        $args = array( 
            'headers'   => array(
                'timeout'   => 60,
                'Accept' => 'application/json',
                'X-API-Version' => $this->wc_rightway_api_version,
                'X-API-Key' => $this->wc_rightway_api_key
            )
        );

        // Отправляем запрос
        $response = wp_remote_get( $this->rwApiUrlCardSearch . '?'.$parameters, $args );
        $responseCode = wp_remote_retrieve_response_code($response);
        Plugin::get()->log( sprintf( __('Тело ответа на запрос аккаунта: %s', 'rightway' ), wp_remote_retrieve_body( $response ) ) );
        
        if (200 == wp_remote_retrieve_response_code($response)) {
            $bodyArray = json_decode( wp_remote_retrieve_body( $response ),true);
            Plugin::get()->log( wp_json_encode( $bodyArray, JSON_UNESCAPED_UNICODE ) );
            //Делаем дополнительную проверку на непустой body, так как на практике встретился случай, когда для неклиента был получен код 200
            if (count($bodyArray) !=0) {
                return $bodyArray[0]['customerId']; // Возвращаем идентификатор клиента
            }       
        } else {
            throw new \Exception(wp_remote_retrieve_response_code($response).' '.wp_remote_retrieve_response_message($response));
        }

        return false;
    }    

    /**
     * Заготовка авторизации клиента RW (customerId/contactId); ответ не возвращается.
     *
     * @param \WP_User $user Пользователь WordPress.
     * @return void
     */
    public function authorizeRWClient($user) {
        $customerId = $this->getCustomerId($user);
        if ($customerId) {
            $contactId = $this->getContactId($user);
            /* $confirmtionCodeSended = $this->sendConfirmationCode( $customerId,$contactId ); */
        }
    }

    /**
     * Регистрация покупателя в RW (POST register).
     *
     * @param string $contact Канал основного контакта: «phone» или «email».
     * @param string $value   Телефон или email (в зависимости от $contact).
     * @param string $token   Второй контакт (email или телефон).
     * @param string $firstName Имя.
     * @param string $lastName  Фамилия.
     * @param string $birthDate Дата рождения (опционально).
     * @param string $gender    Пол (опционально).
     * @return string|false Тело ответа JSON при 200 или false при прочих ветках.
     * @throws \Exception При кодах 400, 402, 422 и др.
     */
    public function registerRWClient($contact, $value, $token, $firstName='', $lastName='', $birthDate='', $gender='') {
        
        $bodyArray = array(
            'firstName'     => $firstName,
            'middleName'    => '',
            'lastName'      => $lastName,
            'gender'        => $gender,
            'phone'         => ($contact=='phone')?$value:$token,
            'email'         => ($contact=='email')?$value:$token
        );
        if ($birthDate) {
            $bodyArray['birthDate'] = $birthDate;
        }
        $args = array( 
            'headers'   => array(
                'timeout'           => 60,
                'Accept'            => 'application/json',
                'Content-Type'      => 'application/json',
                'X-API-Version'     => $this->wc_rightway_api_version,
                'X-API-Key'         => $this->wc_rightway_api_key
            ),
            'body'      => json_encode( $bodyArray )
        );

        // Отправляем запрос
        $response = wp_remote_post( $this->rwApiUrlRegister, $args );
        $responseCode = wp_remote_retrieve_response_code($response);
        Plugin::get()->log( $this->rwApiUrlRegister);
        Plugin::get()->log( json_encode($args, JSON_UNESCAPED_UNICODE));
        switch ($responseCode) {
            case 200:
                Plugin::get()->log( sprintf( __('Тело ответа: %s', 'rightway' ), wp_remote_retrieve_body( $response ) ) );
                $bodyArray = json_decode( wp_remote_retrieve_body( $response ),true);
                return wp_remote_retrieve_body( $response );
/*                 if (count($bodyArray) !=0) {
                    return wp_remote_retrieve_body( $response );
                } */
                break;
            case 400:
                throw new \Exception('В запросе переданы неверные данные'. json_encode( $response ));
                break;
            case 402:
                throw new \Exception('Необходимо подтвердить один из контактов');
                break;
            case 422:
                throw new \Exception('Отсутствует номер телефона в карте');
                break;                
            default:
                throw new \Exception(wp_remote_retrieve_response_code($response).' '.wp_remote_retrieve_response_message($response));                                                                                  
        }
        return false;
        
    }

    /**
     * Создание бонусной карты для покупателя RW (POST card).
     *
     * @param int|string $customerId Идентификатор покупателя в RW.
     * @return string|false Тело ответа JSON при успехе или false.
     * @throws \Exception При 400, 402, 404, 424 и др.
     */
    public function createRWCard($customerId) {
        $parameters = '?customerId='.$customerId;
        $args = array( 
            'headers'   => array(
                'timeout'           => 60,
                'Accept'            => 'application/json',
                'Content-type'      => 'application/json',
                'X-API-Version'     => $this->wc_rightway_api_version,
                'X-API-Key'         => $this->wc_rightway_api_key
            ),
            'body'      => json_encode( array(
                "allowSms"                      => true,
                "allowEmail"                    => false,
                "allowViber"                    => false,
                "allowMarketingCommunication"   => true,
                "brandId"                       => $this->brandId,
                "friendCardNumber"              => "",
                "shopId"                        => '0000'
            ) )
        );

        // Отправляем запрос
        $response = wp_remote_post( $this->rwApiUrlCard.$parameters, $args );
        $responseCode = wp_remote_retrieve_response_code($response);
        Plugin::get()->log( sprintf( __('Запрос на создание бонусной карты: %s', 'rightway' ), $this->rwApiUrlCard . $parameters ) );
        Plugin::get()->log( json_encode($args['body'], JSON_UNESCAPED_UNICODE) );
        switch ($responseCode) {
            case 200:
                Plugin::get()->log( sprintf( __('Тело ответа: %s', 'rightway' ), wp_remote_retrieve_body( $response ) ) );
                $bodyArray = json_decode( wp_remote_retrieve_body( $response ),true);
                if (count($bodyArray) !=0) {
                    return wp_remote_retrieve_body( $response );
                }
            case 400:
                throw new \Exception('В запросе переданы неверные данные: '.wp_remote_retrieve_body( $response ));
                break;
            case 402:
                throw new \Exception('Необходимо подтвердить один из контактов');
                break;
            case 404:
                throw new \Exception('Не найдена карта покупателя');
                break;
            case 424:
                throw new \Exception('Нет активного магазина в бренде, обратитесь в службу поддержки');
                break;                
            default:
                throw new \Exception(wp_remote_retrieve_response_code($response).' '.wp_remote_retrieve_response_message($response));                                                                                  
        }
        return false;
        
    }    

    /**
     * Пакеты бонусов по карте (GET card/bonus-packs/{cardId}).
     *
     * @param int|string $cardId Идентификатор карты в RW.
     * @return array<string, mixed> Декодированный JSON ответа.
     * @throws \Exception При 401, 403, 404 и др.
     */
    public function getCustomerRWBonuses($cardId) {
        $parameters = 'bonus-packs/'.$cardId;
        
        $args = array( 
            'headers'   => array(
                'timeout'   => 60,
                'Accept' => 'application/json',
                'X-API-Version' => $this->wc_rightway_api_version,
                'X-API-Key' => $this->wc_rightway_api_key
            )
        );

        // Отправляем запрос
        $response = wp_remote_get( $this->rwApiUrlCard . '/'.$parameters, $args );
        $responseCode = wp_remote_retrieve_response_code($response);
        Plugin::get()->log( 'Запрос бонусов на карте: '.$this->rwApiUrlCard . '/'.$parameters);
        Plugin::get()->log( sprintf( __('Тело ответа на запрос бонусов на карте: %s', 'rightway' ), wp_remote_retrieve_body( $response ) ) );
        switch ($responseCode) {
            case 200:
                $bodyArray = json_decode( wp_remote_retrieve_body( $response ),true);
                return $bodyArray;
            case 401:
                throw new \Exception('Переданный токен невалиден');
                break;
            case 403:
                throw new \Exception('Доступ запрещен');
                break;
            case 404:
                throw new \Exception('Карта не найдена');
                break;
            default:
                throw new \Exception(wp_remote_retrieve_response_code($response).' '.wp_remote_retrieve_response_message($response));                                                                                  
        }
    }

    /**
     * Сводка по карте (GET card/summary/{cardId}), тело ответа как строка JSON.
     *
     * @param int|string $cardId Идентификатор карты в RW.
     * @return string Тело ответа.
     * @throws \Exception При 401, 403, 404 и др.
     */
    public function getCardSummary($cardId) {
        $parameters = 'summary/'.$cardId;
        
        $args = array( 
            'headers'   => array(
                'timeout'   => 60,
                'Accept' => 'application/json',
                'X-API-Version' => $this->wc_rightway_api_version,
                'X-API-Key' => $this->wc_rightway_api_key
            )
        );

        // Отправляем запрос
        $response = wp_remote_get( $this->rwApiUrlCard . '/'.$parameters, $args );
        $responseCode = wp_remote_retrieve_response_code($response);
        Plugin::get()->log( 'Запрос summary карты: '.$this->rwApiUrlCard . '/'.$parameters);
        Plugin::get()->log( sprintf( __('Тело ответа на запрос summary карты: %s', 'rightway' ), wp_remote_retrieve_body( $response ) ) );
        switch ($responseCode) {
            case 200:
                /* $bodyArray = json_decode( wp_remote_retrieve_body( $response ),true);
                return $bodyArray; */
                $body = wp_remote_retrieve_body( $response );
                return $body;
            case 401:
                throw new \Exception('Переданный токен невалиден');
                break;
            case 403:
                throw new \Exception('Доступ запрещен');
                break;
            case 404:
                throw new \Exception('Карта не найдена');
                break;
            default:
                throw new \Exception(wp_remote_retrieve_response_code($response).' '.wp_remote_retrieve_response_message($response));                                                                                  
        }
    }    

    /**
     * Запрос кода подтверждения для контакта (GET contacts/{id}/send-confirmation-code/...).
     *
     * @param int|string $contactId Идентификатор контакта в RW.
     * @return void При 204 тело пустое, значение не возвращается.
     * @throws \Exception При 403, 404, 409, 429 и др.
     */
    public function sendConfirmationCode($contactId) {
        $parameters = '/'.$contactId.'/send-confirmation-code/'.$this->brandId;
        
        $args = array( 
            'headers'   => array(
                'timeout'   => 180,
                'Accept' => 'application/json',
                'X-API-Version' => $this->wc_rightway_api_version,
                'X-API-Key' => $this->wc_rightway_api_key
            )
        );

        // Отправляем запрос
        $response = wp_remote_get( $this->rwApiUrlContacts.$parameters, $args );
        $responseCode = wp_remote_retrieve_response_code($response);
        switch ($responseCode) {
            case 204:
                $bodyArray = json_decode( wp_remote_retrieve_body( $response ),true);
                break;
            case 403:
                throw new \Exception('Доступ запрещен. Карта заблокирована');
                break;
            case 404:
                throw new \Exception('Контакт не найден. Карта не найдена');
                break;
            case 409:
                throw new \Exception('Переданный контакт не принадлежит карте');
                break;
            case 429:
                throw new \Exception('Клиент попытался отправить слишком много запросов за короткое время');
                break;
            default:
                throw new \Exception(wp_remote_retrieve_response_code($response).' '.wp_remote_retrieve_response_message($response));                                                                                  
        }
    }

    /**
     * Запрос кода подтверждения по значению контакта (телефон/email).
     *
     * @param string $contactValue Нормализованное значение контакта в URL RW.
     * @return void При 204 ответ без тела.
     * @throws \Exception При 400, 403, 404, 409, 429 и др.
     */
    public function sendContactConfirmationCode($contactValue) {
        $parameters = '/send-confirmation-code/'.rawurlencode( $contactValue ).'/'.$this->brandId;
        
        $args = array( 
            'headers'   => array(
                'timeout'   => 180,
                'Accept' => 'application/json',
                'X-API-Version' => $this->wc_rightway_api_version,
                'X-API-Key' => $this->wc_rightway_api_key
            )
        );

        // Отправляем запрос
        $response = wp_remote_get( $this->rwApiUrlContacts.$parameters, $args );
        $responseCode = wp_remote_retrieve_response_code($response);
        Plugin::get()->log( $this->rwApiUrlContacts.$parameters );
        Plugin::get()->log(wp_remote_retrieve_body( $response ));
        switch ($responseCode) {
            case 204:
                $bodyArray = json_decode( wp_remote_retrieve_body( $response ),true);
                break;
            case 400:
                throw new \Exception('В запросе переданы неверные данные');
                break;                
            case 403:
                throw new \Exception('Доступ запрещен. Карта заблокирована');
                break;
            case 404:
                throw new \Exception('Карта не найдена');
                break;
            case 409:
                throw new \Exception('Переданный контакт не принадлежит карте');
                break;
            case 429:
                throw new \Exception('Клиент попытался отправить слишком много запросов за короткое время');
                break;
            default:
                throw new \Exception(wp_remote_retrieve_response_code($response).' '.wp_remote_retrieve_response_message($response));                                                                                  
        }
    }

    /**
     * Список контактов покупателя (GET customers/{customerId}/contacts, заголовок TSSA).
     *
     * @param int|string $customerId Идентификатор покупателя в RW.
     * @return array<int, array<string, mixed>> Массив контактов.
     * @throws \Exception При 401 и др.
     */
    public function getCustomerContacts($customerId) {
        $parameters = '/'.$customerId.'/contacts';
        $args = array(            
            'headers'   => array(
                'timeout'   => 60,
                'Accept' => 'application/json',
                'X-API-Version' => $this->wc_rightway_api_version,
                'X-API-Key' => $this->wc_rightway_tssa_key
            )

        );
        /* translators: %s: customer ID. */
        Plugin::get()->log( sprintf( __( 'Запрос контактов покупателя с customerId=%s', 'rightway' ), $customerId ) );
        Plugin::get()->log($this->rwApiUrlCustomers . $parameters);

        // Отправляем запрос
        $response = wp_remote_get( $this->rwApiUrlCustomers . $parameters, $args );
        /* translators: %1$s: customer ID, %2$s: response body. */
        Plugin::get()->log( sprintf( __( 'Контакты покупателя с customerId=%1$s: %2$s', 'rightway' ), $customerId, wp_remote_retrieve_body( $response ) ) );

        $responseCode = wp_remote_retrieve_response_code($response);
        switch ($responseCode) {
            case 200:
                $bodyArray = json_decode( wp_remote_retrieve_body( $response ),true);
                if (count($bodyArray) !=0) {
                    $customerInfo = $bodyArray;
                    return $customerInfo;
                } else {
                    return $bodyArray;
                }    
                break;
            case 401:
                throw new \Exception('Ошибка авторизации');
                break;
            default:
                throw new \Exception(wp_remote_retrieve_response_code($response).' '.wp_remote_retrieve_response_message($response));
        }
    }    

    /**
     * Обновление анкеты покупателя (PUT customers?customerId=...).
     *
     * @param array<string, mixed> $customData Поля анкеты для RW.
     * @param int|string           $customerId Идентификатор покупателя в RW.
     * @return array<string, mixed>|null Результат json_decode тела ответа при 204 (часто null).
     * @throws \Exception При 400, 403, 422 и др.
     */
    public function updateCustomerData($customData,$customerId) {
        $parameters = 'customerId='.$customerId;
        $args = array( 
            'body' => json_encode($customData, JSON_UNESCAPED_UNICODE),            
            'method'      => 'PUT',
            'headers'   => array(
                'timeout'   => 60,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json; charset=utf-8',
                'X-API-Version' => $this->wc_rightway_api_version,
                'X-API-Key' => $this->wc_rightway_tssa_key
            )

        );
        Plugin::get()->log('Обновление анкетных данных');
        Plugin::get()->log($this->rwApiUrlCustomers . '?'.$parameters);
        Plugin::get()->log(json_encode($customData, JSON_UNESCAPED_UNICODE));

        // Отправляем запрос
        $response = wp_remote_post( $this->rwApiUrlCustomers . '?'.$parameters, $args );
        Plugin::get()->log( sprintf( __('Ответ на обновление: %s', 'rightway' ), wp_remote_retrieve_response_code( $response ) ) );

        $responseCode = wp_remote_retrieve_response_code($response);
        switch ($responseCode) {
            case 204:
                return json_decode(wp_remote_retrieve_body($response),true);
                /* break; */
            case 400:
                $body400 = wp_remote_retrieve_body( $response );
                Plugin::get()->log( 'Тело ответа RW (400), обновление анкеты: ' . $body400 );
                $user_message = $this->formatRw400ErrorMessage( $body400 );
                if ( $user_message !== null ) {
                    throw new \Exception( $user_message );
                }
                throw new \Exception( wp_remote_retrieve_response_code( $response ) . ' ' . wp_remote_retrieve_response_message( $response ) );
            case 403:
                throw new \Exception('Доступ запрещен');
                break;
            case 422:
                throw new \Exception('Неверный код');
                break;
            default:
                throw new \Exception(wp_remote_retrieve_response_code($response).' '.wp_remote_retrieve_response_message($response));
        }
    }

    /**
     * Сообщение для пользователя из JSON-тела ответа RW при HTTP 400.
     *
     * @param string $body Тело ответа.
     * @return string|null Текст ошибки или null, если разобрать не удалось.
     */
    private function formatRw400ErrorMessage( $body ) {
        if ( $body === '' ) {
            return null;
        }
        $decoded = json_decode( $body, true );
        if ( ! is_array( $decoded ) ) {
            return null;
        }
        if ( ! empty( $decoded['message'] ) && is_string( $decoded['message'] ) ) {
            return $this->translateRwApiErrorText( $decoded['message'] );
        }
        if ( empty( $decoded['errors'] ) || ! is_array( $decoded['errors'] ) ) {
            return null;
        }
        $parts = array();
        foreach ( $decoded['errors'] as $field_error ) {
            if ( ! is_array( $field_error ) ) {
                continue;
            }
            $field_name  = isset( $field_error['fieldName'] ) ? (string) $field_error['fieldName'] : '';
            $errors_list = ( isset( $field_error['errors'] ) && is_array( $field_error['errors'] ) )
                ? $field_error['errors']
                : array();
            $msgs = array();
            foreach ( $errors_list as $err_text ) {
                if ( is_string( $err_text ) && $err_text !== '' ) {
                    $msgs[] = $this->translateRwApiErrorText( $err_text );
                }
            }
            if ( empty( $msgs ) ) {
                continue;
            }
            $parts[] = $this->getRwCustomerFieldLabel( $field_name ) . ': ' . implode( ' ', $msgs );
        }
        if ( empty( $parts ) ) {
            return null;
        }
        return implode( ' ', $parts );
    }

    /**
     * @param string $field_name Имя поля в ответе RW.
     * @return string Подпись поля для сообщения пользователю.
     */
    private function getRwCustomerFieldLabel( $field_name ) {
        $labels = array(
            'firstName'  => __( 'Имя', 'rightway' ),
            'lastName'   => __( 'Фамилия', 'rightway' ),
            'middleName' => __( 'Отчество', 'rightway' ),
            'birthDate'  => __( 'Дата рождения', 'rightway' ),
            'gender'     => __( 'Пол', 'rightway' ),
        );
        if ( isset( $labels[ $field_name ] ) ) {
            return $labels[ $field_name ];
        }
        return $field_name !== '' ? $field_name : __( 'Поле анкеты', 'rightway' );
    }

    /**
     * @param string $text Текст ошибки из RW (англ.).
     * @return string Сообщение на русском или исходный текст.
     */
    private function translateRwApiErrorText( $text ) {
        $text   = trim( $text );
        $known  = array(
            'This value is not valid.' => __( 'указано недопустимое значение. Уберите спецсимволы: скобки, восклицательные знаки и т. п.', 'rightway' ),
        );
        if ( isset( $known[ $text ] ) ) {
            return $known[ $text ];
        }
        return $text;
    }
    
    /**
     * Подтверждение контакта кодом; возвращает токен для последующих операций.
     *
     * @param string $contactValue Значение контакта в сегменте URL RW.
     * @param string|int $confirmCode Код из SMS/email.
     * @return string Ключ confirmedContactToken из ответа.
     * @throws \Exception При 400, 422, 429 и др.
     */
    public function getToken($contactValue, $confirmCode) {
        $parameters = '/confirm/'.rawurlencode( $contactValue ).'/'.$this->brandId;
        
        $args = array( 
            'headers'   => array(
                'timeout'   => 180,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-API-Version' => $this->wc_rightway_api_version,
                'X-API-Key' => $this->wc_rightway_api_key
            ),
            'body'      => json_encode( array( 'code' => (string)$confirmCode ), JSON_UNESCAPED_UNICODE )
            
        );

        // Отправляем запрос
        $response = wp_remote_post( $this->rwApiUrlContacts.$parameters, $args );
        $responseCode = wp_remote_retrieve_response_code($response);
        Plugin::get()->log( 'Запрос на токен' );
        Plugin::get()->log( $this->rwApiUrlContacts.$parameters );
        Plugin::get()->log( $args['body'] );
        switch ($responseCode) {
            case 200:
                $bodyArray = json_decode( wp_remote_retrieve_body( $response ),true);
                Plugin::get()->log( wp_remote_retrieve_body( $response ) );
                return $bodyArray['confirmedContactToken'];
                break;
            case 400:
                throw new \Exception('В запросе переданы неверные данные '.$response);
                break;
            case 422:
                throw new \Exception('Неверный код');
                break;
            case 429:
                throw new \Exception('Клиент попытался отправить слишком много запросов за короткое время');
                break;
            default:
                throw new \Exception(wp_remote_retrieve_response_code($response).' '.wp_remote_retrieve_response_message($response));                                                                                  
        }
    }

    /**
     * Добавление контакта покупателю (POST contacts/add?customerId=...).
     *
     * @param array<string, mixed> $contactData Тело запроса (тип контакта, значение и т.д.).
     * @param int|string           $customerId  Идентификатор покупателя в RW.
     * @param string               $token       Зарезервировано (в коде не подставляется в заголовок).
     * @return void При успехе код 204 без тела.
     * @throws \Exception При 400, 403 и др.
     */
    public function createContactData($contactData, $customerId, $token) {
        $parameters = '/add?customerId='.$customerId;
        $args = array( 
            'body' => json_encode($contactData, JSON_UNESCAPED_UNICODE),            
            'headers'   => array(
                'timeout'   => 1200,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-API-Version' => $this->wc_rightway_api_version,
                'X-API-Key' => $this->wc_rightway_api_key
                /* 'X-API-Key' => $token */
            )

        );
        Plugin::get()->log($this->rwApiUrlContacts . $parameters);
        Plugin::get()->log($args['body']);

        // Отправляем запрос
        $response = wp_remote_post( $this->rwApiUrlContacts.$parameters, $args );
        Plugin::get()->log( sprintf( __('Добавление контакта покупателю: %s', 'rightway' ), wp_remote_retrieve_response_code( $response ) ) );

        $responseCode = wp_remote_retrieve_response_code($response);
        switch ($responseCode) {
            case 204: 
                break;
            case 400:
                throw new \Exception(wp_remote_retrieve_response_code($response).' '.wp_remote_retrieve_response_message($response));
                break;
            case 403:
                throw new \Exception('Доступ запрещен');
                break;
            default:
                throw new \Exception(wp_remote_retrieve_response_code($response).' '.wp_remote_retrieve_response_message($response));
        }
    }

    /**
     * Изменение контакта (PUT contacts/{contactId}?customerId=...).
     *
     * @param array<string, mixed> $contactData Поля контакта.
     * @param int|string             $contactId   Идентификатор контакта в RW.
     * @param int|string             $customerId  Идентификатор покупателя в RW.
     * @return void При успехе 204.
     * @throws \Exception При 400, 403, 404 и др.
     */
    public function updateContactData($contactData,$contactId,$customerId) {
        $parameters = '/'.$contactId.'?customerId='.$customerId;
        $args = array( 
            'body' => json_encode($contactData, JSON_UNESCAPED_UNICODE),            
            'method'      => 'PUT',
            'headers'   => array(
                'timeout'   => 1200,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json; charset=utf-8',
                'X-API-Version' => $this->wc_rightway_api_version,
                'X-API-Key' => $this->wc_rightway_api_key
            )

        );
        Plugin::get()->log($this->rwApiUrlContacts . $parameters);
        Plugin::get()->log($args['body']);

        // Отправляем запрос
        $response = wp_remote_post( $this->rwApiUrlContacts.$parameters, $args );
        Plugin::get()->log( sprintf( __('Редактирование контакта покупателя: %s', 'rightway' ), wp_remote_retrieve_response_code( $response ) ) );

        $responseCode = wp_remote_retrieve_response_code($response);
        switch ($responseCode) {
            case 204: 
                break;
            case 400:
                throw new \Exception(wp_remote_retrieve_response_code($response).' '.wp_remote_retrieve_response_message($response));
                break;
            case 403:
                throw new \Exception('Доступ запрещен');
                break;
            case 404:
                throw new \Exception('Контакт не найден');
                break;
            default:
                throw new \Exception(wp_remote_retrieve_response_code($response).' '.wp_remote_retrieve_response_message($response));
        }
    }

    /**
     * Объединение карт (POST card/merge).
     *
     * @param array<string, mixed> $cardsData JSON-тело запроса объединения.
     * @return void
     * @throws \Exception При 400, 404 и др.
     */
    public function mergeCards($cardsData) {
        $args = array( 
            'body' => json_encode($cardsData, JSON_UNESCAPED_UNICODE),            
            'method'      => 'POST',
            'headers'   => array(
                'timeout'   => 1200,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json; charset=utf-8',
                'X-API-Version' => $this->wc_rightway_api_version,
                'X-API-Key' => $this->wc_rightway_api_key
            )

        );
        Plugin::get()->log($this->rwApiUrlCardMerge);
        Plugin::get()->log($args['body']);

        // Отправляем запрос
        $response = wp_remote_post( $this->rwApiUrlCardMerge, $args );
        Plugin::get()->log( sprintf( __('Объединение карт: %s', 'rightway' ), wp_remote_retrieve_response_code( $response ) ) );

        $responseCode = wp_remote_retrieve_response_code($response);
        $responseCode = 200;
        switch ($responseCode) {
            case 200:
                Plugin::get()->log('Карты успешно объединены');
                break;
            case 400:
                throw new \Exception(wp_remote_retrieve_response_code($response).' '.wp_remote_retrieve_response_message($response));
                break;
            case 404:
                throw new \Exception('Карта не найдена');
                break;
            default:
                throw new \Exception(wp_remote_retrieve_response_code($response).' '.wp_remote_retrieve_response_message($response));
        }
    }    
    
    /**
     * Настройки коммуникаций по карте (PUT card/{cardId}).
     *
     * @param array<string, mixed> $communicationData Флаги каналов и рассылок для RW.
     * @param int|string           $cardId            Идентификатор карты в RW.
     * @return void При успехе 204.
     * @throws \Exception С разбором типичных сообщений RW при 400 и при прочих кодах.
     */
    public function updateCommunicationData($communicationData, $cardId) {
        $parameters = $cardId;
        
        $args = array(
            'method' => 'PUT',
            'headers'   => array(
                'timeout'   => 120,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-API-Version' => $this->wc_rightway_api_version,
                'X-API-Key' => $this->wc_rightway_api_key
            ),
            'body' => json_encode($communicationData, JSON_UNESCAPED_UNICODE)
        );

        // Отправляем запрос (явный request + PUT — не смешивать с GET).
        $response = wp_remote_request( $this->rwApiUrlCard . '/'.$parameters, $args );
        $responseCode = wp_remote_retrieve_response_code($response);
        Plugin::get()->log( 'Обновление данных коммуникации: '.$this->rwApiUrlCard . '/'.$parameters);
        Plugin::get()->log( $args['body']);
        switch ($responseCode) {
            case 204:
                Plugin::get()->log( 'Данные коммуникации успешно обновлены' );
                break;            
            case 400:
                $body400 = wp_remote_retrieve_body( $response );
                Plugin::get()->log( 'Тело ответа RW (400): ' . $body400 );
                $decoded400 = json_decode( $body400, true );
                if ( is_array( $decoded400 ) && ! empty( $decoded400['message'] ) ) {
                    $rwMsg = $decoded400['message'];
                    if ( stripos( $rwMsg, 'empty contact data' ) !== false && stripos( $rwMsg, 'Email' ) !== false ) {
                        throw new \Exception( __( 'Нельзя включить рассылку по email: в программе лояльности нет email-контакта. Укажите email в анкете или отключите канал Email.', 'rightway' ) );
                    }
                    if ( stripos( $rwMsg, 'empty contact data' ) !== false && stripos( $rwMsg, 'Sms' ) !== false ) {
                        throw new \Exception( __( 'Нельзя включить SMS: в программе лояльности нет подтверждённого телефона.', 'rightway' ) );
                    }
                    throw new \Exception( $rwMsg );
                }
                throw new \Exception( wp_remote_retrieve_response_code( $response ) . ' ' . wp_remote_retrieve_response_message( $response ) );
            case 401:
                throw new \Exception('Переданный токен невалиден');
                break;
            case 403:
                throw new \Exception('Доступ запрещен');
                break;
            case 422:
                throw new \Exception('Неверный код');
                break;
            default:
                throw new \Exception(wp_remote_retrieve_response_code($response).' '.wp_remote_retrieve_response_message($response));                                                                                  
        }
    }

    /**
     * Карты покупателя по бренду (GET customers/{customerId}/cards?brandId=...).
     *
     * @param int|string $customerId Идентификатор покупателя в RW.
     * @return string JSON-тело ответа.
     * @throws \Exception При кодах ответа вне 200.
     */
    public function getCustomerCardData($customerId) {
        $parameters = '/'.$customerId.'/cards?brandId='.$this->brandId;
        
        $args = array(
            'headers'   => array(
                'timeout'   => 120,
                'Accept' => 'application/json',
                'X-API-Version' => $this->wc_rightway_api_version,
                'X-API-Key' => $this->wc_rightway_api_key
            )
        );

        // Отправляем запрос
        $response = wp_remote_get( $this->rwApiUrlCustomers.$parameters, $args );
        $responseCode = wp_remote_retrieve_response_code($response);
        Plugin::get()->log( 'Получение карт пользователя: '.$this->rwApiUrlCustomers.$parameters);
        switch ($responseCode) {
            case 200:
                Plugin::get()->log( wp_remote_retrieve_body( $response ) );
                return wp_remote_retrieve_body( $response );           
            default:
                throw new \Exception(wp_remote_retrieve_response_code($response).' '.wp_remote_retrieve_response_message($response));                                                                                  
        }
    }    

    /**
     * Предварительный расчёт бонусов по чеку (XML processing calculateSale).
     *
     * @param string $cheque XML тела запроса для RW processing.
     * @return \SimpleXMLElement|false Разобранный ответ или false при ошибке разбора.
     * @throws \Exception Если {@see wp_remote_post} не вернул ответ.
     */
    public function calculateSale($cheque) {
        $parameters = '/calculateSale';
        $args = array( 
            'headers' => array(
                'Content-Type'  => 'text/xml',
                'Accept'        => 'text/xml',
                'X-Processing-Version' => $this->wc_rightway_x_processing_version,
                'X-Processing-Key' => $this->wc_rightway_x_processing_key
            ),
                'body'         => $cheque
        );
        Plugin::get()->log('Запрос на бонусную оценку');
        Plugin::get()->log($args['body']);
        Plugin::get()->log($this->rwProcessingUrl.$parameters);
        $response = wp_remote_post( $this->rwProcessingUrl.$parameters, $args );        

        if ($response) {
            $bodyArray = simplexml_load_string( wp_remote_retrieve_body( $response ));
/*             $result = array();
            foreach($bodyArray as $delivery) {
                $result[ (string) $delivery->attributes()->delivery_type[0] ] = (string) $delivery->attributes()->delivery_name[0];
            } */

            Plugin::get()->log('Ответ на calculateSale');
            Plugin::get()->log($response);
            return $bodyArray;
        
        } else {
            //throw new Exception(wp_remote_retrieve_response_code($response).' '.wp_remote_retrieve_response_message($response));
            throw new Exception("Ошибка");
        }
    }

    /**
     * Списание/начисление бонусов по чеку (XML processing applyPurchase).
     *
     * @param string $cheque XML тела запроса.
     * @return \SimpleXMLElement|false
     * @throws \Exception Если {@see wp_remote_post} не вернул ответ.
     */
    public function applyPurchase($cheque) {
        $parameters = '/applyPurchase';
        $args = array( 
            'headers' => array(
                'Content-Type'  => 'text/xml',
                'Accept'        => 'text/xml',
                'X-Processing-Version' => $this->wc_rightway_x_processing_version,
                'X-Processing-Key' => $this->wc_rightway_x_processing_key
            ),
                'body'         => $cheque
        );
        Plugin::get()->log('Запрос на списание/начисление бонусов');
        Plugin::get()->log($args['body']);
        Plugin::get()->log($this->rwProcessingUrl.$parameters);
        $response = wp_remote_post( $this->rwProcessingUrl.$parameters, $args );        

        if ($response) {
            $bodyArray = simplexml_load_string( wp_remote_retrieve_body( $response ));
/*             $result = array();
            foreach($bodyArray as $delivery) {
                $result[ (string) $delivery->attributes()->delivery_type[0] ] = (string) $delivery->attributes()->delivery_name[0];
            } */
            Plugin::get()->log('Ответ на applyPurchase');
            Plugin::get()->log($response);
            return $bodyArray;
        
        } else {
            //throw new Exception(wp_remote_retrieve_response_code($response).' '.wp_remote_retrieve_response_message($response));
            throw new Exception("Ошибка");
        }
    } 
    
    /**
     * Предварительный расчёт возврата бонусов (XML processing calculateReturn).
     *
     * @param string $cheque XML тела запроса.
     * @return \SimpleXMLElement|false
     * @throws \Exception Если {@see wp_remote_post} не вернул ответ.
     */
    public function calculateReturn($cheque) {
        $parameters = '/calculateReturn';
        $args = array( 
            'headers' => array(
                'Content-Type'  => 'text/xml',
                'Accept'        => 'text/xml',
                'X-Processing-Version' => $this->wc_rightway_x_processing_version,
                'X-Processing-Key' => $this->wc_rightway_x_processing_key
            ),
                'body'         => $cheque
        );
        Plugin::get()->log('Запрос на оценку возврата бонусов');
        Plugin::get()->log($args['body']);
        Plugin::get()->log($this->rwProcessingUrl.$parameters);
        $response = wp_remote_post( $this->rwProcessingUrl.$parameters, $args );        

        if ($response) {
            $bodyArray = simplexml_load_string( wp_remote_retrieve_body( $response ));
/*             $result = array();
            foreach($bodyArray as $delivery) {
                $result[ (string) $delivery->attributes()->delivery_type[0] ] = (string) $delivery->attributes()->delivery_name[0];
            } */

            Plugin::get()->log('Ответ на calculateReturn');
            Plugin::get()->log($response);
            return $bodyArray;
        
        } else {
            //throw new Exception(wp_remote_retrieve_response_code($response).' '.wp_remote_retrieve_response_message($response));
            throw new Exception("Ошибка");
        }
    } 
    
    /**
     * Применение возврата бонусов (XML processing applyReturn).
     *
     * @param string $cheque XML тела запроса.
     * @return \SimpleXMLElement|false
     * @throws \Exception Если {@see wp_remote_post} не вернул ответ.
     */
    public function applyReturn($cheque) {
        $parameters = '/applyReturn';
        $args = array( 
            'headers' => array(
                'Content-Type'  => 'text/xml',
                'Accept'        => 'text/xml',
                'X-Processing-Version' => $this->wc_rightway_x_processing_version,
                'X-Processing-Key' => $this->wc_rightway_x_processing_key
            ),
                'body'         => $cheque
        );
        Plugin::get()->log('Возврат бонусов');
        Plugin::get()->log($args['body']);
        Plugin::get()->log($this->rwProcessingUrl.$parameters);
        $response = wp_remote_post( $this->rwProcessingUrl.$parameters, $args );        

        if ($response) {
            $bodyArray = simplexml_load_string( wp_remote_retrieve_body( $response ));
/*             $result = array();
            foreach($bodyArray as $delivery) {
                $result[ (string) $delivery->attributes()->delivery_type[0] ] = (string) $delivery->attributes()->delivery_name[0];
            } */

            Plugin::get()->log('Ответ на applyReturn');
            Plugin::get()->log($response);
            return $bodyArray;
        
        } else {
            //throw new Exception(wp_remote_retrieve_response_code($response).' '.wp_remote_retrieve_response_message($response));
            throw new Exception("Ошибка");
        }
    }
    
    /**
     * Расчёт скидки/бонусов без карты (XML processing calculateSaleWithoutCard).
     *
     * @param string $cheque XML тела запроса.
     * @return \SimpleXMLElement|false
     * @throws \Exception Если {@see wp_remote_post} не вернул ответ.
     */
    public function calculateSaleWithoutCard($cheque) {
        $parameters = '/calculateSaleWithoutCard';
        $args = array( 
            'headers' => array(
                'Content-Type'  => 'text/xml',
                'Accept'        => 'text/xml',
                'X-Processing-Version' => $this->wc_rightway_x_processing_version,
                'X-Processing-Key' => $this->wc_rightway_x_processing_key
            ),
                'body'         => $cheque
        );
        Plugin::get()->log('Запрос на получение скидки без карты');
        Plugin::get()->log($args['body']);
        Plugin::get()->log($this->rwProcessingUrl.$parameters);
        $response = wp_remote_post( $this->rwProcessingUrl.$parameters, $args );        

        if ($response) {
            $bodyArray = simplexml_load_string( wp_remote_retrieve_body( $response ));
/*             $result = array();
            foreach($bodyArray as $delivery) {
                $result[ (string) $delivery->attributes()->delivery_type[0] ] = (string) $delivery->attributes()->delivery_name[0];
            } */

            Plugin::get()->log('Ответ на calculateSaleWithoutCard');
            Plugin::get()->log($response);
            return $bodyArray;
        
        } else {
            //throw new Exception(wp_remote_retrieve_response_code($response).' '.wp_remote_retrieve_response_message($response));
            throw new Exception("Ошибка");
        }
    }    
}