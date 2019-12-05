<?php
/**
 * 設定画面を表示・保存するクラス
 *
 * @author     Masaki Wakatake
 */

require_once(GET_ARTICLES_PLUGIN_PATH.'options.php');

function create_menu(){
  // データベースから既存の設定値を読み込む
  $opt_val_no_index_site_url = get_option( GetArticlesOption::NO_INDEX_SITE_URL );
  $opt_val_basic_auth_id = get_option( GetArticlesOption::BASIC_AUTH_ID );
  $opt_val_basic_auth_pw = get_option( GetArticlesOption::BASIC_AUTH_PW );
  $opt_val_post_status = get_option( GetArticlesOption::POST_STATUS );

  if( isset($_POST[ GetArticlesOption::NO_INDEX_SITE_URL ])) {
    update_option( GetArticlesOption::NO_INDEX_SITE_URL,  $_POST[GetArticlesOption::NO_INDEX_SITE_URL] );
    update_option( GetArticlesOption::BASIC_AUTH_ID, $_POST[GetArticlesOption::BASIC_AUTH_ID] );
    update_option( GetArticlesOption::BASIC_AUTH_PW, $_POST[GetArticlesOption::BASIC_AUTH_PW] );
    update_option( GetArticlesOption::POST_STATUS, $_POST[GetArticlesOption::POST_STATUS] );
?>
  <div class="updated"><p><strong>変更を保存しました。</strong></p></div>
<?php } ?>
  <div class="wrap">
    <form name="menu-form" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
      <p>no index サイトのRSS<br/>
        <input type="text" name="<?php echo GetArticlesOption::NO_INDEX_SITE_URL; ?>" value="<?php echo get_option( GetArticlesOption::NO_INDEX_SITE_URL ); ?>" size="40"><br/>
        例：http://example.jp/rss2all.xml
      </p>
      <p>BASIC認証ID:<br/>
        <input type="text" name="<?php echo GetArticlesOption::BASIC_AUTH_ID; ?>" value="<?php echo get_option( GetArticlesOption::BASIC_AUTH_ID ); ?>" size="20">
      </p>
      <p>BASIC認証パスワード:<br/>
        <input type="text" name="<?php echo GetArticlesOption::BASIC_AUTH_PW; ?>" value="<?php echo get_option( GetArticlesOption::BASIC_AUTH_PW ); ?>" size="20">
      </p>
      <p>記事取得時公開ステータス:<br/>
        <input type="radio" name="<?php echo GetArticlesOption::POST_STATUS; ?>" value="draft" <?php echo $checked = get_option( GetArticlesOption::POST_STATUS ) == 'draft' ? "checked": ""; ?>> 下書き
        <input type="radio" name="<?php echo GetArticlesOption::POST_STATUS; ?>" value="publish" <?php echo $checked = get_option( GetArticlesOption::POST_STATUS ) == 'publish' ? "checked": ""; ?>> 公開設定
        <input type="radio" name="<?php echo GetArticlesOption::POST_STATUS; ?>" value="future" <?php echo $checked = get_option( GetArticlesOption::POST_STATUS ) == 'future' ? "checked": ""; ?>> 予約済み
      </p>
      <p>次回記事取得時刻:<br/>
        <?php $time = wp_next_scheduled( 'get_articles_schedule' );
          date_default_timezone_set('Asia/Tokyo');
          echo date('Y年m月d日 h:i:s', $time);
        ?>
      </p>
      <p class="submit">
        <input type="submit" name="Submit" value="変更を保存" class="button button-primary" />
      </p>
    </form>
    <div>
      <p>動作確認環境: <br>
        WordPress 4.3, PHP( 5.3.29 (cgi-fcgi), 5.5.9-1ubuntu4.5 ), <br>
        WordPress 4.8, PHP( 5.6.25, CentOS 6.2 )<br>
        WordPress 5.0, PHP( 7.1 )<br>
        依存ライブラリ:
        <a href="https://wpdocs.osdn.jp/%E9%96%A2%E6%95%B0%E3%83%AA%E3%83%95%E3%82%A1%E3%83%AC%E3%83%B3%E3%82%B9/wp_cron" target="_blank">wp_cron</a>,
        <a href="http://php.net/manual/ja/function.simplexml-load-file.php" target="_blank">simplexml_load_file</a>
      </p>
      <p>※本プラグインは限られた動作環境において有効です。必ずしも全てのお客様のご利用環境にて動作を保証するものではありません。</p>
    </div>
  </div>
<?php
}
