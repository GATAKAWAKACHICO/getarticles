<?php
/**
 * プラグインアンインストール時に利用するコード
 * 
 * @author     Masaki Wakatake
 */
define( 'GET_ARTICLES_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
require_once(GET_ARTICLES_PLUGIN_PATH.'options.php');

//if uninstall not called from WordPress exit
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) 
  exit();

delete_option( GetArticlesOption::NO_INDEX_SITE_URL );
delete_option( GetArticlesOption::BASIC_AUTH_ID );
delete_option( GetArticlesOption::BASIC_AUTH_PW );
delete_option( GetArticlesOption::POST_STATUS );

// Drop a custom db table
global $wpdb;
$table_name = $wpdb->prefix . GetArticlesOption::TABLE_SUFFIX;
$wpdb->query( "DROP TABLE IF EXISTS $table_name" );
