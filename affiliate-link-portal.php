<?php
/*
Plugin Name: Affiliate Link Portal
Description: Manage and use affiliate links with shortcodes.
Version: 1.0.0
Author: Kasiri
*/

// 画像リンクのURLを取得
$image_url = plugin_dir_url(__FILE__) . 'assets/img/prb.webp';
class Affiliate_Link_Portal {
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'affiliate_links';

        add_action('admin_menu', [$this, 'add_menu']);
        register_activation_hook(__FILE__, [$this, 'create_table']);

        if (is_admin()) {
            add_action('admin_post_add_affiliate_link', [$this, 'add_affiliate_link']);
            add_action('admin_post_edit_affiliate_link', [$this, 'edit_affiliate_link']);
            add_action('admin_menu', [$this, 'add_edit_page']);
            add_action('admin_post_delete_empty_records', [$this, 'delete_empty_records']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_styles']);
        }

        add_shortcode('affiliate_link', [$this, 'affiliate_link_shortcode']);
    }

    public function add_menu() {
        add_menu_page(
            'Affiliate Link Portal',
            'Affiliate Link Portal',
            'manage_options',
            'affiliate-link-portal-settings',
            [$this, 'settings_page'],
            'dashicons-admin-links',
            99
        );
    }

    public function add_edit_page() {
        add_submenu_page(
            'affiliate-link-portal-settings',
            'Edit Affiliate Link',
            '',
            'manage_options',
            'affiliate-link-portal-edit',
            [$this, 'edit_affiliate_link_page']
        );
    }

    public function settings_page() {
        global $wpdb;

        // 登録済みのリンク一覧テーブル
        $link_list = $this->get_link_list_html();

        // 設定ページのコンテンツを表示
        echo '<div class="wrap">';
        echo '<h1>Affiliate Link Portal Settings</h1>';

        // 新しいリンクの入力フォーム
        echo '
            <h2>Add New Link</h2>
            <form method="post" action="' . admin_url('admin-post.php') . '">
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>ASP</th>
                            <th>Affiliate Link</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><input type="text" name="link_title" placeholder="Title"></td>
                            <td><input type="text" name="link_asp" placeholder="ASP"></td>
                            <td><textarea name="link_affiliate" placeholder="Affiliate Link"></textarea></td>
                            <td><input type="submit" class="button button-primary" name="add_link" value="Save Link"></td>
                        </tr>
                    </tbody>
                </table>
                <input type="hidden" name="action" value="add_affiliate_link">
                ' . wp_nonce_field('add_affiliate_link', 'add_affiliate_link_nonce') . '
            </form>
        ';

        // 登録済みのリンク一覧テーブル
        echo '
            <h2>Registered Links</h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Title</th>
                        <th>ASP</th>
                        <th>Affiliate Link</th>
                        <th>Shortcode</th>
                        <th>Edit</th>
                    </tr>
                </thead>
                <tbody>
                    ' . $link_list . '
                </tbody>
            </table>
        ';

        // 空のレコードを削除するボタン
        echo '
            <h2>Maintenance</h2>
            <p>空のレコードを削除します。</p><p>誤削除を防ぐためリンクを削除したい場合はEditからデータを空にして保存してください。</p>
            <form method="post" action="' . admin_url('admin-post.php') . '">
                <input type="hidden" name="action" value="delete_empty_records">
                ' . wp_nonce_field('delete_empty_records', 'delete_empty_records_nonce') . '
                <p><input type="submit" class="button button-secondary" value="Delete Empty Records"></p>
            </form>
        ';

        $image_url = plugin_dir_url(__FILE__) . 'assets/img/bgt.png';
        echo '</div><hr><div><a href="https://basekix.com" target="_blank"><img src="' . $image_url . '"></a></div>';
    }

    public function get_link_list_html() {
        global $wpdb;

        $links = $wpdb->get_results("SELECT * FROM $this->table_name", ARRAY_A);

        // 登録済みのリンク一覧テーブル
        $link_list = '';
        $index = 1;
        foreach ($links as $link) {
            $shortcode = '[affiliate_link slug="' . $link['slug'] . '"]';
            $edit_link = add_query_arg(array('action' => 'edit', 'id' => $link['id']), admin_url('admin.php?page=affiliate-link-portal-edit'));
            $link_list .= '
                <tr>
                    <td>' . $index . '</td>
                    <td>' . $link['title'] . '</td>
                    <td>' . $link['asp'] . '</td>
                    <td>' . $link['affiliate_link'] . '</td>
                    <td>' . $shortcode . '</td>
                    <td><a href="' . $edit_link . '">Edit</a></td>
                </tr>
            ';
            $index++;
        }

        return $link_list;
    }

    public function edit_affiliate_link_page() {
        global $wpdb;

        $link_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $link = $this->get_link_by_id($link_id);

        if (!$link) {
            echo '<div class="wrap"><h1>Edit Affiliate Link</h1><p>Invalid link ID.</p></div>';
            return;
        }

        echo '<div class="wrap">';
        echo '<h1>Edit Affiliate Link</h1>';

        echo '
            <form method="post" action="' . admin_url('admin-post.php') . '">
                <table class="widefat">
                    <tbody>
                        <tr>
                            <th>Title</th>
                            <td><input type="text" name="link_title" placeholder="Title" value="' . esc_attr($link['title']) . '"></td>
                        </tr>
                        <tr>
                            <th>ASP</th>
                            <td><input type="text" name="link_asp" placeholder="ASP" value="' . esc_attr($link['asp']) . '"></td>
                        </tr>
                        <tr>
                            <th>Affiliate Link</th>
                            <td><textarea name="link_affiliate" placeholder="Affiliate Link">' . esc_textarea($link['affiliate_link']) . '</textarea></td>
                        </tr>
                    </tbody>
                </table>
                <input type="hidden" name="action" value="edit_affiliate_link">
                <input type="hidden" name="link_id" value="' . $link_id . '">
                ' . wp_nonce_field('edit_affiliate_link', 'edit_affiliate_link_nonce') . '
                <p><input type="submit" class="button button-primary" name="save_link" value="Save Link"> <a href="' . admin_url('admin.php?page=affiliate-link-portal-settings') . '">Cancel</a></p>
            </form>
        ';

        $image_url = plugin_dir_url(__FILE__) . 'assets/img/bgt.png';
        echo '</div><hr><div><a href="https://basekix.com" target="_blank"><img src="' . $image_url . '"></a></div>';
    }

    public function edit_affiliate_link() {
        // セキュリティチェック
        if (
            !isset($_POST['edit_affiliate_link_nonce']) ||
            !wp_verify_nonce($_POST['edit_affiliate_link_nonce'], 'edit_affiliate_link') ||
            !current_user_can('manage_options')
        ) {
            wp_die('Unauthorized access');
        }

        if (!isset($_POST['link_id'])) {
            wp_die('Invalid request');
        }

        global $wpdb;

        $link_id = intval($_POST['link_id']);
        $title = sanitize_text_field($_POST['link_title']);
        $asp = sanitize_text_field($_POST['link_asp']);
        $affiliate_link = wp_kses_post($_POST['link_affiliate']);

        $update_result = $wpdb->update(
            $this->table_name,
            array(
                'title' => $title,
                'asp' => $asp,
                'affiliate_link' => $affiliate_link,
            ),
            array('id' => $link_id),
            array(
                '%s',
                '%s',
                '%s'
            ),
            array('%d')
        );

        if ($update_result === false) {
            wp_die('Failed to update data in the database.');
        }

        wp_redirect(add_query_arg('message', 'success', admin_url('admin.php?page=affiliate-link-portal-settings')));
        exit;
    }

    public function get_link_by_id($link_id) {
        global $wpdb;

        $link = $wpdb->get_row($wpdb->prepare("SELECT * FROM $this->table_name WHERE id = %d", $link_id), ARRAY_A);

        return $link;
    }

    public function create_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $this->table_name (
            id INT(11) NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            asp VARCHAR(255) NOT NULL,
            affiliate_link LONGTEXT NOT NULL,
            slug VARCHAR(255) NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function add_affiliate_link() {
        // セキュリティチェック
        if (
            !isset($_POST['add_affiliate_link_nonce']) ||
            !wp_verify_nonce($_POST['add_affiliate_link_nonce'], 'add_affiliate_link') ||
            !current_user_can('manage_options')
        ) {
            wp_die('Unauthorized access');
        }

        global $wpdb;

        $title = sanitize_text_field($_POST['link_title']);
        $asp = sanitize_text_field($_POST['link_asp']);
        $affiliate_link = wp_kses_post($_POST['link_affiliate']);
        $slug = $this->generate_slug();

        $insert_result = $wpdb->insert(
            $this->table_name,
            array(
                'title' => $title,
                'asp' => $asp,
                'affiliate_link' => $affiliate_link,
                'slug' => $slug,
            ),
            array(
                '%s',
                '%s',
                '%s',
                '%s',
            )
        );

        if ($insert_result === false) {
            $wpdb_error_message = $wpdb->last_error;
            $wpdb_error_code = $wpdb->last_error_code;
            wp_die('Failed to insert data into the database. Error: ' . $wpdb_error_message . ' (' . $wpdb_error_code . ')');
        }

        wp_redirect(add_query_arg('message', 'success', admin_url('admin.php?page=affiliate-link-portal-settings')));
        exit;
    }

    private function generate_slug() {
        $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $slug = '';
        $length = 8;

        for ($i = 0; $i < $length; $i++) {
            $slug .= $characters[rand(0, strlen($characters) - 1)];
        }

        return $slug;
    }

    public function delete_empty_records() {
        // セキュリティチェック
        if (
            !isset($_POST['delete_empty_records_nonce']) ||
            !wp_verify_nonce($_POST['delete_empty_records_nonce'], 'delete_empty_records') ||
            !current_user_can('manage_options')
        ) {
            wp_die('Unauthorized access');
        }

        global $wpdb;

        $wpdb->query("DELETE FROM $this->table_name WHERE title = '' AND asp = '' AND affiliate_link = ''");

        wp_redirect(add_query_arg('message', 'success', admin_url('admin.php?page=affiliate-link-portal-settings')));
        exit;
    }

    public function affiliate_link_shortcode($atts) {
        $atts = shortcode_atts(
            array(
                'slug' => '',
            ),
            $atts
        );

        if (empty($atts['slug'])) {
            return '';
        }

        global $wpdb;
        $slug = sanitize_title($atts['slug']);

        $link = $wpdb->get_row($wpdb->prepare("SELECT * FROM $this->table_name WHERE slug = %s", $slug), ARRAY_A);

        if (!$link) {
            return '';
        }

        return $link['affiliate_link'];
    }

    public function enqueue_styles() {
        wp_enqueue_style('affiliate-link-portal-style', plugin_dir_url(__FILE__) . 'assets/css/affiliate-link-portal-style.css');
    }
}

new Affiliate_Link_Portal();
