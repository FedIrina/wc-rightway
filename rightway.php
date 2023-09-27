<?php
/**
* Plugin Name: RightWay
* Plugin URI: https://ivannikitin-com.github.io/rightway/
* Description: Плагин для связи WooCommerce с платформой лояльности RightWay.
* Version: 1.0.0
* Author: Иван Никитин и партнеры
* Author URI: https://ivannikitin.com
* License:     GPL3
* License URI: https://www.gnu.org/licenses/gpl-3.0.html
* Text Domain: rightway
* Domain Path: /lang
* Namespace: RIGHTWAY

Copyright 2023  Irina Fedorova  (email: skywalker2718@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
defined( 'ABSPATH' ) or die( 'No script please!' );

/* Глобальные константы плагина */
define( 'RIGTWAY', 'rightway' );	// Text Domain

add_action( 'init', 'rightway_test_user' );

function rightway_test_user() {

        $current_user = wp_get_current_user();
/*         if ($current_user->exists() && !in_array($current_user->user_login, array('irinaf','skywalker27','skywalker2718')) ) {
                return;
        } */
        /* Файлы ядра плагина */
        require_once( 'classes/Plugin.php' );
        require_once( 'classes/API.php' );

        /* Запуск плагина */
        \RIGHTWAY\Plugin::init( 
            plugin_dir_path( __FILE__ ), 			// Путь к папке плагина
            plugin_dir_url( __FILE__ ), 			// URL папки плагина
            get_file_data( __FILE__, array(			// Мета-данные из заголовка плагина
                    'Name' 		=> 'Plugin Name',	// Название Плагина
                    'Version' 	=> 'Version',		// Версия плагина
                ) ) );
}

