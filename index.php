<?php
/*
 * Plugin Name: getarticles
 * Description: Get articles from RSS 
 * Version: 0.1
 * Author: Leaf-hide Inc.
 * Author URI: http://llp.leaf-hide.jp/
 * License: GPL2
 * */
// デバッグ
// openlog("getArticles", LOG_PID | LOG_PERROR, LOG_LOCAL0);
define( 'GET_ARTICLES_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
require_once(GET_ARTICLES_PLUGIN_PATH.'menu.php');
require_once(GET_ARTICLES_PLUGIN_PATH.'options.php');
require_once(GET_ARTICLES_PLUGIN_PATH.'http_build_url.php');
require_once(ABSPATH.'wp-admin/includes/taxonomy.php');
$get_articles = new GetArticles();

class GetArticles {
  private $menu_title = 'getarticles';
  private $page_title = 'Get articles from RSS';
  private $capability = 'manage_options';
  private $menu_slug = 'getarticles/menu.php';

  public function __construct() {
    if (function_exists('register_activation_hook')) {
      register_activation_hook(__FILE__, array(&$this, 'activationHook'));
      add_action('get_articles_schedule', array(&$this, 'get_articles'));
    }
    if (function_exists('register_deactivation_hook')) {
      register_deactivation_hook(__FILE__, array(&$this, 'deactivationHook'));
    }
    add_action( 'admin_menu', array(&$this, 'set_plugin_menu' ) );
    add_action( 'before_delete_post', array(&$this, 'article_hash_delete' ) );
  }

  /**
   * プラグインが有効化されたときに実行されるメソッド
   * 参考：http://dev.classmethod.jp/server-side/wordpress-plugin-classdef/
   * @return void
   */
  public function activationHook() {
    // 有効化時処理
    $no_index_url = get_option( GetArticlesOption::POST_STATUS );
    if( empty($no_index_url) ) {
      update_option( GetArticlesOption::POST_STATUS,  'draft');
    }
    // テーブル作成
    $this->init_table();
    // cronをセット
    if ( !wp_next_scheduled( 'get_articles_schedule' ) ) {
      wp_schedule_event(time(), 'hourly', 'get_articles_schedule');
    }
  }

  /**
   * プラグインが停止されたときに実行されるメソッド
   *
   * @return void
   */
  public function deactivationHook() {
    // 停止時処理
    // cronを止める
    wp_clear_scheduled_hook('get_articles_schedule');
  }

  /**
   * 記事hash確認用のテーブル
   */
  public function init_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . GetArticlesOption::TABLE_SUFFIX;
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
      hash varchar(55) DEFAULT '' NOT NULL UNIQUE,
      time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL
    ) $charset_collate;";
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
  }

  /**
   * 記事が削除された際には記事hashも削除
   */
  public function article_hash_delete($post_id){
    global $wpdb;
    $table_name = $wpdb->prefix . GetArticlesOption::TABLE_SUFFIX;
    $hash = get_post_meta($post_id, 'hash', true);
    if (!empty($hash)) {
      $wpdb->delete( $table_name, array( 'hash' => $hash ) );
    }
  }

  /**
   * 設定にプラグインのメニューを追加
   */
  public function set_plugin_menu() {
    add_options_page( $this->page_title, $this->menu_title, $this->capability, 'masaki-waktake-0809', array(&$this, 'set_plugin_options' ) );
  }

  public function set_plugin_options() {
    if ( !current_user_can( $this->capability ) )  {
          wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
    echo create_menu();
    // error_log(date("Y-m-d H:i:s", time()), 3, "/tmp/log/php/php_errors.log");
    // デバッグ（メニューにアクセスしたら記事取得）
    // $this->get_articles();
  }

  /**
   * デバッグログ生成時のタイムスタンプ取得用
   */
  public function get_timestamp(){
    date_default_timezone_set('Asia/Tokyo');
    return date(" Y-m-d H:i:s", time())."\n";
  }

  /**
   * RSSで記事を取得する
   */
  public function get_articles() {
    $url = $this->generate_basic_auth_url( get_option (GetArticlesOption::NO_INDEX_SITE_URL) );
    $xml = simplexml_load_file( $url );
    $this->rss_to_articles($xml->channel);
  }

  /**
   * BASIC認証のURLを返す
   * @return string
   */
  public function generate_basic_auth_url($url) {
    $id = get_option (GetArticlesOption::BASIC_AUTH_ID);
    $pw = get_option (GetArticlesOption::BASIC_AUTH_PW);
    if (!empty($id) && !empty($pw)) {
      $return = http_build_url($url,
        array(
          "user" => $id,
          "pass" => $pw
        )
      );
      return $return;
    }
    return $url;
  }

  /**
   * RSSで取得した記事をWordPressに保存する
   */
  public function rss_to_articles($articles){
    global $wpdb;
    $table_name = $wpdb->prefix . GetArticlesOption::TABLE_SUFFIX;
    $i = 0;
    foreach ($articles->item as $article) {
      $results = $wpdb->get_results("SELECT hash FROM $table_name WHERE hash = '$article->hash' LIMIT 1");
      if(count($results) >= 1) {
        $i++;
        continue;
      }
      $image_url_array = $this->get_image_url_array($article->description_all);
      $post_content = $this->normalize_html($article->description_all, $image_url_array);
      $categories = $this->get_category_id_array($article->tag);
      $tags = $this->get_tags_string($article->tag);
      $new_post = array(
        'post_title' => $article->title,
        'post_content'  => $post_content,
        'post_excerpt' => $article->description_summary,
        'post_category' => $categories,
        'tags_input' => $tags,
        'post_author'   => 1, // デフォルトはログインユーザー、wp_cronの場合ユーザーIDの数字を指定する必要がある。
        'post_status'   => get_option (GetArticlesOption::POST_STATUS),
      );
      // wp_insert_post() http://wpdocs.osdn.jp/%E9%96%A2%E6%95%B0%E3%83%AA%E3%83%95%E3%82%A1%E3%83%AC%E3%83%B3%E3%82%B9/wp_insert_post
      $post_id = wp_insert_post( $new_post, true);
      add_post_meta( $post_id, 'hash', (string) $article->hash );
      $wpdb->insert(
        $table_name,
        array(
          'hash' => (string) $article->hash,
          'time' => current_time( 'mysql' ),
        )
      );
      $url = $this->generate_basic_auth_url($image_url_array[1][0]);
      $this->set_eyecatch_image( $post_id, $url );
      $i++;
      // 直近10記事を取得
      if ($i > 9) { return; }
    }
  }

  /** 
   * htmlを標準化する 
   **/
  public function normalize_html($post_content, $image_url_array) {
    $post_content = $this->detail_tag_to_h3($post_content);
    $post_content = $this->block_tag_to_blockquote($post_content);
    for($i = 0; $i < count($image_url_array[1]); $i++){
      $url = $this->generate_basic_auth_url($image_url_array[1][$i]);
      $local_image_url = $this->get_local_image_url($post_content, $url);
      $post_content = str_replace($image_url_array[1][$i], $local_image_url, $post_content);
    }
    return $post_content;
  }

  /**
   * カテゴリIDの配列を返す
   */
  public function get_category_id_array($tag) {
    $categories = explode(",", $tag);
    if (count($categories) > 3) {
      $category_id = wp_create_category($categories[2]);
      $return = array();
      array_push($return, $category_id);
      return $return;
    }
    return "";
  }

  /**
   * タグ文字列を返す
   */
  public function get_tags_string($tag) {
    $tags_string = explode(",", $tag);
    if (count($tags_string) > 3) {
      return $tags_string[1];
    }
    return "";
  }

  /**
   * 記事内の画像URLを配列で返す
   */
  public function get_image_url_array($post_content){
    $pattern = '/<img src="(.*?)" class="news_detail_img2">/';
    preg_match_all($pattern, $post_content, $out, PREG_PATTERN_ORDER);
    return $out;
  }

  public function get_local_image_url($post_content, $image_url){
    $image_hash = explode("/", $image_url);
    $filename = $image_hash[count($image_hash) - 1];
    $uploaddir = wp_upload_dir();
    $uploadfile = $uploaddir['path'].$filename;
    $contents = file_get_contents($image_url, FILE_BINARY);
    file_put_contents($uploadfile, $contents);
    $wp_filetype = wp_check_filetype(basename($filename), null );
    $attachment = array(
      'post_mime_type' => $wp_filetype['type'],
      'post_title' => $filename,
      'post_content' => '',
      'post_status' => 'inherit'
    );
    $attach_id = wp_insert_attachment( $attachment, $uploadfile );

    // Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
    require_once( ABSPATH . 'wp-admin/includes/image.php' );

    // Generate the metadata for the attachment, and update the database record.
    $attach_data = wp_generate_attachment_metadata( $attach_id, $contents );
    wp_update_attachment_metadata( $attach_id, $attach_data );
    return $local_image_url = wp_get_attachment_url($attach_id);
  }

  public function set_eyecatch_image($post_id, $image_url) {
    $image_hash = explode("/", $image_url);
    $filename = $image_hash[count($image_hash) - 1];
    $uploaddir = wp_upload_dir();
    $uploadfile = $uploaddir['path'].$filename;
    $contents = file_get_contents($image_url, FILE_BINARY);
    file_put_contents($uploadfile, $contents);
    $wp_filetype = wp_check_filetype(basename($filename), null );
    $attachment = array(
      'post_mime_type' => $wp_filetype['type'],
      'post_title' => $filename,
      'post_content' => '',
      'post_status' => 'inherit'
    );
    $attach_id = wp_insert_attachment( $attachment, $uploadfile );

    // Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
    require_once( ABSPATH . 'wp-admin/includes/image.php' );

    // Generate the metadata for the attachment, and update the database record.
    $attach_data = wp_generate_attachment_metadata( $attach_id, $contents );
    wp_update_attachment_metadata( $attach_id, $attach_data );
    set_post_thumbnail( $post_id, $attach_id );
  }

  public function detail_tag_to_h3($post_content) {
    $pattern = '/<div class="news_detail_line_(red|orange|green|blue|gray)">(.*?)<\/div>/';
    $replacement = '<h3 style="border-bottom: 1px solid #f1688d; border-left: 10px solid #f1688d; padding: 7px;">$2</h3>';
    $return = preg_replace($pattern, $replacement, $post_content);
    return $return;
  }

  public function block_tag_to_blockquote($post_content) {
    $pattern = '/<div class="news_block">(.*?)<\/div>/';
    $replacement = '<blockquote>$1</blockquote>';
    $return = preg_replace($pattern, $replacement, $post_content);
    return $return;
  }

}



?>
