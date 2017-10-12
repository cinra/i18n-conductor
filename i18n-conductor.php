<?php
/*
Plugin Name: I18n Conductor
Plugin URI: http://www.cinra.co.jp/
Description: i18n support package using ACF
Version: 1.0.0
Author: CINRA Inc.
Author URI: http://www.cinra.co.jp/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

define('I18N_CONDUCTOR_URL', plugin_dir_url(__FILE__));
define('I18N_CONDUCTOR_PATH', plugin_dir_path(__FILE__));

defined('I18N_CONDUCTOR_LANGUAGE_DIRECTORY') || define('I18N_CONDUCTOR_LANGUAGE_DIRECTORY', get_stylesheet_directory() . '/languages');
defined('I18N_CONDUCTOR_LANGUAGE_TAXONOMY') || define('I18N_CONDUCTOR_LANGUAGE_TAXONOMY', 'lang');
defined('I18N_CONDUCTOR_DEFAULT_LANG') || define('I18N_CONDUCTOR_DEFAULT_LANG', 'ja');
defined('I18N_CONDUCTOR_DOMAIN') || define('I18N_CONDUCTOR_DOMAIN', 'i18nc');
defined('I18N_CONDUCTOR_NOTFOUND_REDIRECT_SLUG') || define('I18N_CONDUCTOR_NOTFOUND_REDIRECT_SLUG', 'about');

class I18n_Conductor
{
  private $acf_exists = false;

  public function __construct()
  {
    add_action('plugins_loaded', array($this, 'initialize'));
  }

  public function initialize()
  {
    if (class_exists('acf'))
    {
      $this->acf_exists = true;
      include_once I18N_CONDUCTOR_PATH . 'acf-helper.php';
    }

    include_once I18N_CONDUCTOR_PATH . 'helper.php';

    add_action('init', array($this, 'load_textdomain'));
    add_action('home_url', array($this, 'home_url'), 10, 2);
    add_action('pre_get_posts', array($this, 'tax_query'));
    add_action('wp', array($this, 'notfound_redirect'));

    add_filter('locale', array($this, 'get_locale'));
    add_filter('the_title', array($this, 'title_filter'), 10, 2);
    add_filter('single_post_title', array($this, 'title_filter'), 10, 2);
    add_filter('the_content', array($this, 'content_filter'));
    add_filter('posts_clauses_request', array($this, 'post_filter'), 10, 2);
    add_filter('redirect_canonical', array($this, 'redirect_canonical'), 10, 2);
    add_filter('get_previous_post_where', array($this, 'adjacent_post_tax_where'));
    add_filter('get_next_post_where', array($this, 'adjacent_post_tax_where'));
    add_filter('get_previous_post_join', array($this, 'adjacent_post_tax_join'));
    add_filter('get_next_post_join', array($this, 'adjacent_post_tax_join'));
  }

  public function get_locale($locale = null)
  {
    if (is_admin() && $locale) return $locale;
    return defined('WPLANG') ? WPLANG : I18N_CONDUCTOR_DEFAULT_LANG;
  }

  public function load_textdomain()
  {
    $locale = $this->get_locale();
    load_textdomain(I18N_CONDUCTOR_DOMAIN, I18N_CONDUCTOR_LANGUAGE_DIRECTORY . '/' . $locale . '.mo');
  }

  public function title_filter($raw, $post_id)
  {
    if (is_admin() || !$this->acf_exists) return $raw;

    $title = get_i18n_field('title', $post_id);
    return !$title ? $raw : $title;
  }

  public function content_filter($raw)
  {
    if (is_admin() || !$this->acf_exists) return $raw;

    $content = get_i18n_field('content');
    return !$content ? $raw : $content;
  }

  public function home_url($url, $path)
  {
    if (is_admin() || !$path) return $url;

    if ($path !== '/') $url = user_trailingslashit($url);

    $locale = get_locale();
    if ($locale === I18N_CONDUCTOR_DEFAULT_LANG) return $url;

    $path = '/' . trim($path, '/');

    return $path === '/' ? trailingslashit($url) . $locale . '/' : str_replace($path, '/' . $locale . $path , $url);
  }

  public function tax_query($query)
  {
    if (is_admin()) return;

    $post_type = $query->get('post_type');
    $public_post_types = get_post_types(array('public' => true));

    if ($post_type === 'attachment') return;
    if ($post_type && (is_string($post_type) && !in_array($post_type, $public_post_types)
      || is_array($post_type) && !array_intersect($post_type, $public_post_types))) return;

    $locale = get_locale();

    $tax_query = $query->get('tax_query');

    if (isset($tax_query['relation']) && strtoupper($tax_query['relation']) === 'OR')
    {
      $new_tax_query = array(
        'relation' => 'AND',
        array(
         'taxonomy' => I18N_CONDUCTOR_LANGUAGE_TAXONOMY,
          'field'    => 'slug',
          'terms'    => $locale
        ),
        array($tax_query),
      );

      $query->set('tax_query', $new_tax_query);
    }
    else
    {
      if (!$tax_query) $tax_query = array();
      $tax_query[] = array(
        'taxonomy' => I18N_CONDUCTOR_LANGUAGE_TAXONOMY,
        'field'    => 'slug',
        'terms'    => $locale
      );

      $query->set('tax_query', $tax_query);
    }
  }

  public function adjacent_post_tax_join($join)
  {
    global $wpdb;

    $join .= " LEFT JOIN $wpdb->term_relationships AS i18n_tr ON p.ID = i18n_tr.object_id";
    $join .= " LEFT JOIN $wpdb->term_taxonomy AS i18n_tt ON i18n_tr.term_taxonomy_id = i18n_tt.term_taxonomy_id";

    return $join;
  }

  public function adjacent_post_tax_where($where)
  {
    global $wpdb;

    $term = get_term(get_term_by('slug', get_locale(), I18N_CONDUCTOR_LANGUAGE_TAXONOMY));

    if ($term) $where .= " AND i18n_tt.term_id IN ($term->term_id)";

    return $where;
  }

  public function post_filter($pieces, $query)
  {
    if (is_admin()) return $pieces;

    global $wpdb;

    if ($query->is_main_query() && ($query->is_single() || $query->is_page()))
    {
      $q = $query->query_vars;
      $query->parse_tax_query($q);
      $clauses = $query->tax_query->get_sql($wpdb->posts, 'ID');
      $pieces['where'] .= $clauses['where'];
      $pieces['join'] .= $clauses['join'];
    }

    return $pieces;
  }

  public function notfound_redirect()
  {
    global $wp_query;

    $is_single = ($wp_query->query_vars['p'] || $wp_query->query_vars['name'] || $wp_query->query_vars['pagename']);

    if (is_404() && $is_single && ($redirect_url = $this->get_redirect_url()))
    {
      wp_safe_redirect($redirect_url);
      exit;
    }
  }

  /*
  TODO: 要確認
  */
  public function redirect_canonical($redirect_url, $requested_url)
  {
    if (get_locale() === I18N_CONDUCTOR_DEFAULT_LANG) return $redirect_url;
    if (defined('WPLANG') && (is_front_page() || is_404())) return $requested_url;

    return $redirect_url;
  }

  public function get_redirect_url()
  {
    if (!defined('I18N_CONDUCTOR_NOTFOUND_REDIRECT_SLUG')) return false;

    $q = new WP_Query(array(
      'post_type' => 'page',
      'name'      => I18N_CONDUCTOR_NOTFOUND_REDIRECT_SLUG,
    ));

    return $q->have_posts() ? home_url(I18N_CONDUCTOR_NOTFOUND_REDIRECT_SLUG) : false;
  }
}

new I18n_Conductor;
