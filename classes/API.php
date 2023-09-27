<?php
/**
 * Взаимодействие интернет-магазина с платформой лояльности RightWay
 */
namespace RIGHTWAY;

class RightWay {

    const LOGFILE = 'rightway.log';

    public $brandId;
    public $shopName;
    private $customerRWBonuses;

    /**
     * Параметры удаленного сервера и подключения
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
    private $rwApiUrlContacts;
    private $rwProcessingUrl;

    public function __construct($wc_rightway_brand_id, $wc_rightway_shop_name, $wc_rightway_api_key, $wc_rightway_api_version, $wc_rightway_tssa_key, $wc_rightway_x_processing_version, $wc_rightway_x_processing_key) {
        $this->wc_rightway_api_key = $wc_rightway_api_key;
        $this->wc_rightway_api_version = $wc_rightway_api_version;
        $this->wc_rightway_tssa_key = $wc_rightway_tssa_key;
        $this->wc_rightway_x_processing_key = $wc_rightway_x_processing_key;
        $this->wc_rightway_x_processing_version = $wc_rightway_x_processing_version;
        $this->brandId = $wc_rightway_brand_id;
        $this->shopName = $wc_rightway_shop_name;
        $this->brandId = 165;
        $this->shopName = "Медкнигасервис";
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
     * Получает идентификатор клиента в платформе RightWay
     * @param string $user_login Username.
     * @param WP_User WP_User object of the logged-in user.
     * @return int $customerId
     */
    public function getCustomerId($user) {  //ИЗБЫТОЧНЫЙ МЕТОД! CustomerId можно получить из инфы,
                                            // возвращаемой методом gerCustomerInfo
        $customerId = 0;
        $parameters = 'email='.urlencode($user->user_email).'&lastId=1&resultsPerPage=100';
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
        Plugin::get()->log( __( 'Поиск покупателя', RIGHTWAY ) . ': ' . wp_remote_retrieve_body( $response ) );
        /* file_put_contents('/var/www/medknigaservis.ru/dev/wp-content/themes/medknigaservis/inc/'.self::LOGFILE, .PHP_EOL,FILE_APPEND); */
        
        if (200 == wp_remote_retrieve_response_code($response)) {
            $bodyArray = json_decode( wp_remote_retrieve_body( $response ),true);
            if (count($bodyArray) !=0) {
                $customerId = (int)$bodyArray['0']['id'];
            }       
        } else {
            throw new \Exception(wp_remote_retrieve_response_code($response).' '.wp_remote_retrieve_response_message($response));
        }
        Plugin::get()->log( __( 'Ответ RW на запрос поиска клиента', RIGHTWAY ) . ': ' . $customerId );
        return $customerId;
    }

/**
     * Получение покупателя по номеру телефона (может использоваться, например, при проверке номера на уникальность при редактировании)
     * @param string $phone 
     * @return iarray $customersArray Массив данных покупателей
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
        Plugin::get()->log( __( 'Поиск покупателей', RIGHTWAY ) . ': ' . wp_remote_retrieve_body( $response ) );

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
     * Получение покупателя по Email (может использоваться, например, при проверке Email на уникальность при редактировании)
     * @param string $email 
     * @return iarray $customersArray Массив данных покупателей
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
        Plugin::get()->log( __( 'Поиск покупателей', RIGHTWAY ) . ': ' . wp_remote_retrieve_body( $response ) );

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
     * Получает информацию о клиенте в платформе RightWay
     * @param string $user_login Username.
     * @param WP_User WP_User object of the logged-in user.
     * @return array $customerInfo
     */
    public function getCustomerInfo($user) {

        $customerId = 0;
        $parameters = 'email='.urlencode($user->user_email).'&lastId=1&resultsPerPage=100';
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
     * Получает идентификатор контакта в платформе RightWay
     * @param WP_User WP_User object of the logged-in user.
     * @return int $contactId
     */
    public function getContactId($user) {   //ИЗБЫТОЧНЫЙ МЕТОД! CustomerId можно получить из инфы,
                                            // возвращаемой методом gerCustomerInfo

        $contactId = 0;
        $parameters = 'email='.urlencode($user->user_email).'&lastId=1&resultsPerPage=100';
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
        Plugin::get()->log( __( 'Ответ RW на запрос поиска контакта', RIGHTWAY ) . ': ' . $contactId );
        return $contactId;
    }    

    /**
     * Получает данные карты клиента в платформе RightWay
     * @param string $phone Номер телефона пользователя
     * @return array $bodyArray Массив объектов карт
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
        /* Plugin::get()->log( __( 'Тело ответа на запрос поиска карты', RIGHTWAY ) . ': ' . wp_remote_retrieve_body( $response ) ); */
        
        if (200 == wp_remote_retrieve_response_code($response)) {
            $bodyArray = json_decode( wp_remote_retrieve_body( $response ),true);       
        } else {
            throw new \Exception(wp_remote_retrieve_response_code($response).' '.wp_remote_retrieve_response_message($response));
        }

        return $bodyArray;
    }

    /**
     * Получает информацию о карте клиента в платформе RightWay
     * @param WP_User WP_User object of the logged-in user.
     * @return int $cardInfo
     */
    public function getCardInfo($user) {

        $cardId = 0;
        $parameters = 'brandId='.$this->brandId.'&email='.urlencode($user->user_email).'&lastId=1&resultsPerPage=100';
        
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
        /* Plugin::get()->log( __( 'Тело ответа на запрос поиска карты', RIGHTWAY ) . ': ' . wp_remote_retrieve_body( $response ) ); */
        
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
     * Определяет, является ли пользователь клиентом платформы RightWay
     * @param WP_User WP_User object of the logged-in user.
     * @return bool
     */
    public function getRWCard($user) {

        $cardId = 0;
        $parameters = 'brandId='.$this->brandId.'&email='.urlencode($user->user_email);
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
        Plugin::get()->log( __( 'Тело ответа на запрос аккаунта', RIGHTWAY ) . ': ' . wp_remote_retrieve_body( $response ) );
        
        if (200 == wp_remote_retrieve_response_code($response)) {
            $bodyArray = json_decode( wp_remote_retrieve_body( $response ),true);
            ob_start();
            var_dump($bodyArray);
            Plugin::get()->log( ob_get_clean() );
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
     * Получает идентификатор клиента платформы RightWay
     * @param WP_User WP_User object of the logged-in user.
     * @return bool
     */
    public function getRWCustomerId($user) {

        $cardId = 0;
        $parameters = 'brandId='.$this->brandId.'&email='.urlencode($user->user_email);
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
        Plugin::get()->log( __( 'Тело ответа на запрос аккаунта', RIGHTWAY ) . ': ' . wp_remote_retrieve_body( $response ) );
        
        if (200 == wp_remote_retrieve_response_code($response)) {
            $bodyArray = json_decode( wp_remote_retrieve_body( $response ),true);
            ob_start();
            var_dump($bodyArray);
            Plugin::get()->log( ob_get_clean() );
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
     * Авторизует пользователя на платформе RightWay
     * @param WP_User WP_User object of the logged-in user.
     * @return bool Возращает true, если авторизация прошла успешно, и false в случае неудачи.
     */
    public function authorizeRWClient($user) {
        $customerId = $this->getCustomerId($user);
        if ($customerId) {
            $contactId = $this->getContactId($user);
            /* $confirmtionCodeSended = $this->sendConfirmationCode( $customerId,$contactId ); */
        }
    }

    /**
     * Регистрирует пользователя на платформе RightWay
     * @param WP_User WP_User object of the logged-in user.
     * @return bool Возращает true, если регистрация прошла успешно, и false в случае неудачи.
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
                Plugin::get()->log( __( 'Тело ответа', RIGHTWAY ) . ': ' . wp_remote_retrieve_body( $response ) );
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
     * Создает бонусную карту для указанного пользователя на платформе RightWay
     * @param WP_User WP_User object of the logged-in user.
     * @return bool Возращает true, если создание прошло успешно, и false в случае неудачи.
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
        Plugin::get()->log( __( 'Запрос на создание бонусной карты: ', RIGHTWAY ) . ': ' . $this->rwApiUrlCard.$parameters );
        Plugin::get()->log( json_encode($args['body'], JSON_UNESCAPED_UNICODE) );
        switch ($responseCode) {
            case 200:
                Plugin::get()->log( __( 'Тело ответа', RIGHTWAY ) . ': ' . wp_remote_retrieve_body( $response ) );
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
     * Получаем количество доступных для списания бонусов на платформе RightWay.
     * @param string Идентификатор карты
     * @return int Возращает количество бонусов, если у клиента на карте они есть, и 0, если карта пустая.
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
        Plugin::get()->log( __( 'Тело ответа на запрос бонусов на карте', RIGHTWAY ) . ': ' . wp_remote_retrieve_body( $response ) );
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
     * Получаем персональную информацию о владельце карты и информацию о состоянии карты на платформе RightWay.
     * @param string Идентификатор карты
     * @return array Возращает данные по карте
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
        Plugin::get()->log( __( 'Тело ответа на запрос summary карты', RIGHTWAY ) . ': ' . wp_remote_retrieve_body( $response ) );
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
     * Получаем код подтверждения для обновления анкетных данных или работы с бонусами при покупке на платформе RightWay.
     * @return int Возращает код подтверждения.
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
     * Получаем код подтверждения для обновления контакта на платформе RightWay.
     */
    public function sendContactConfirmationCode($contactValue) {
        $parameters = '/send-confirmation-code/'.$contactValue.'/'.$this->brandId;
        
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
     * Получает контакта покупателя с указанным customerId на платформе RightWay
     * @param string $customerId CustomerId in RW database.
     * @return array Возращает массив контактов пользователя.
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
        Plugin::get()->log( __( 'Запрос контактов покупателя с customerId='.$customerId, RIGHTWAY ) );
        Plugin::get()->log($this->rwApiUrlCustomers . $parameters);

        // Отправляем запрос
        $response = wp_remote_get( $this->rwApiUrlCustomers . $parameters, $args );
        Plugin::get()->log( __( 'Контакты покупателя с customerId='.$customerId, RIGHTWAY ) . ': ' . wp_remote_retrieve_body($response) );

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
     * Обновляет данные покупателя на платформе RightWay
     * @param array $customData Updated Customer Data.
     * @param string $customerId CustomerId in RW database.
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
        Plugin::get()->log( __( 'Ответ на обновление', RIGHTWAY ) . ': ' . wp_remote_retrieve_response_code($response) );

        $responseCode = wp_remote_retrieve_response_code($response);
        switch ($responseCode) {
            case 204:
                return json_decode(wp_remote_retrieve_body($response),true);
                /* break; */
            case 400:
                throw new \Exception(wp_remote_retrieve_response_code($response).' '.wp_remote_retrieve_response_message($response));
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
     * Получаем подтверждение контакта пользователя на платформе RightWay.
     */
    public function getToken($contactValue, $confirmCode) {
        $parameters = '/confirm/'.$contactValue.'/'.$this->brandId;
        
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
     * Добавляет контакт покупателю на платформе RightWay
     * @param array $customData Updated Customer Data.
     * @param string $customerId CustomerId in RW database.
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
        Plugin::get()->log( __( 'Добавление контакта покупателю', RIGHTWAY ) . ': ' . wp_remote_retrieve_response_code($response) );

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
     * Обновляет данные покупателя на платформе RightWay
     * @param array $customData Updated Customer Data.
     * @param string $customerId CustomerId in RW database.
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
        Plugin::get()->log( __( 'Редактирование контакта покупателя', RIGHTWAY ) . ': ' . wp_remote_retrieve_response_code($response) );

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
     * Объединяет карты на платформе RightWay
     * @param array $customData Updated Customer Data.
     * @param string $customerId CustomerId in RW database.
     */
    public function mergeCards($cardsData) {
        $parameters = '/'.$contactId.'?customerId='.$customerId;
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
        Plugin::get()->log($this->rwApiUrlMerge);
        Plugin::get()->log($args['body']);

        // Отправляем запрос
        $response = wp_remote_post( $this->rwApiUrlMerge, $args );
        Plugin::get()->log( __( 'Объединение карт', RIGHTWAY ) . ': ' . wp_remote_retrieve_response_code($response) );

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
     * Обновляем настройки коммуникации для данной карты на платформе RightWay.
     * @param array $communicationData Updated Communication Data.
     * @param string $cardId CardId in RW database.
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

        // Отправляем запрос
        $response = wp_remote_get( $this->rwApiUrlCard . '/'.$parameters, $args );
        $responseCode = wp_remote_retrieve_response_code($response);
        Plugin::get()->log( 'Обновление данных коммуникации: '.$this->rwApiUrlCard . '/'.$parameters);
        Plugin::get()->log( $args['body']);
        switch ($responseCode) {
            case 204:
                Plugin::get()->log( 'Данные коммуникации успешно обновлены' );
                break;            
            case 400:
                throw new \Exception(wp_remote_retrieve_response_code($response).' '.wp_remote_retrieve_response_message($response));
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
     * Получаем данные карты покупателя на платформе RightWay.
     * @param string $customerId customerId in RW database.
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
     * Предварительный расчет количества бонусов к списанию и/или начислению
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
     * Отправка данных о заказе для списания и/или начисления бонусов
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
     * Предварительный расчет количества бонусов к возврату
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
     * Возврат бонусов
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
     * Получение скидки при отсутствии бонусной карты
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