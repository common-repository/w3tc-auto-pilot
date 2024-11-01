<?php
/**
 * Plugin Name: W3TC Auto Pilot
 * Plugin URI: https://wordpress.org/plugins/w3tc-auto-pilot/
 * Description: Put W3 Total Cache on auto pilot. This plugin allows you to control W3 Total Cache in such a manner that no one knows you're using it, not even your admins. Either network activate it or activate it per site.
 * Version: 1.1.7.1
 * Author: Sybre Waaijer
 * Author URI: https://cyberwire.nl/
 * License: GPLv2 or later
 * Text Domain: w3tc-auto-pilot
 * Domain Path: /language
 */

add_action( 'plugins_loaded', 'w3tc_auto_pilot_locale' );
/**
 * Plugin locale 'w3tc-auto-pilot'
 *
 * File located in plugin folder w3tc-auto-pilot/language/
 *
 * @since 1.0.0
 */
function w3tc_auto_pilot_locale() {
	load_plugin_textdomain( 'w3tc-auto-pilot', false, basename( dirname(__FILE__) ) . '/language/' );
}

/**
 * Initialize plugin when W3 Instance is found.
 */
if ( function_exists( 'w3_instance' ) )
	new W3TC_Auto_Pilot();

/**
 * Class W3TC_Auto_Pilot
 *
 * @since 1.1.4
 */
class W3TC_Auto_Pilot {

	/**
	 * Constructor. Init actions.
	 *
	 * @since 1.0.4
	 */
	public function __construct() {

		/**
		 * Adds advanced flushing on update of certain items (especially related to object cache)
		 *
		 * @uses customize_save_after	=> Affter Custumizer has been saved
		 * @uses wp_update_nav_menu		=> After Nav Menu has been saved
		 * @uses sidebar_admin_setup	=> When Widget Page is loaded.
		 * @uses switch_theme		=> After Theme Switch has been saved
		 */
		add_action( 'customize_save_after', array( $this, 'w3tc_flush_all' ), 10 ); // This one works.
		add_action( 'wp_update_nav_menu', array( $this, 'w3tc_flush_menu' ), 11, 1 ); // Works.
		add_action( 'sidebar_admin_setup', array( $this, 'w3tc_flush_all_widget' ), 10 ); // works
		add_action( 'switch_theme', array( $this, 'w3tc_flush_all_theme' ), 10, 2 ); // works but needs a better call.

		/**
		 * Ajax Flushes.
		 *
		 * @uses wp_ajax_widgets-order - Ajax Widget Reordering
		 */
		add_action( 'wp_ajax_widgets-order', array( $this, 'w3tc_flush_all_ajax_widget' ), 1 ); // works.

		/**
		 * Added functionality to prevent cache bugs with Domain Mapping (by WPMUdev)
		 */
		add_action( 'save_post', array( $this, 'wpmudev_dm_flush' ), 10 ); //multisite

		/**
		 * Removal of a lot of W3TC Items, with focus on multisite.
		 * In action order:
		 *
		 * Removes admin bar entry of W3 Total Cache
		 * Removes admin bar entry of W3 Total Cache
		 * Removes W3TC admin menu entry of W3 Total Cache
		 * Removes W3TC admin menu popup script
		 * Removes W3TC admin styles
		 * Removes W3TC "Purge From Cache" link above the "publish/save" button on posts/pages, Also removes the "Purge From Cache" link in post/pages lists
		 * Removes W3TC Debug scripts
		 * Removes admin notices for non-super-admins
		 * Adds a redirect notice if a user still tries to access the w3tc dashboard.
		 */
		add_action( 'admin_bar_menu', array( $this, 'remove_w3tc_adminbar' ), 10 );
		add_action( 'wp_before_admin_bar_render', array( $this, 'remove_w3tc_adminbar' ), 10 );
		add_action( 'admin_menu', array( $this, 'remove_w3tc_adminmenu' ), 10 );
		add_action( 'init', array( $this, 'remove_w3tc_popup_script' ), 10 );
		add_action( 'admin_init', array( $this, 'remove_w3tc_admin_styles' ), 10 );
		add_action( 'admin_init', array( $this, 'remove_w3tc_inpost_flush' ), 10 );
		add_filter( 'w3tc_can_print_comment', '__return_false', 10 );
		add_action( 'admin_init', array( $this, 'remove_w3tc_admin_notices' ), 10 );
		add_action( 'admin_init', array( $this, 'wap_die_notice' ), 10 );

	}

	/**
	 * Limit the flush to X amount of pages/posts
	 *
	 * @applies filters wap_max_flush, the integer for maximum page/post flushes
	 *
	 * @since 1.1.4
	 */
	public function limit_flush() {

		static $flush = null;

		if ( isset( $flush ) )
			return $flush;

		$flush = (int) apply_filters( 'wap_limit_flush', 20 );

		return $flush = abs( $flush );
	}

	/**
	 * Forces an extra flush on mapped domain.
	 *
	 * @requires plugin Domain Mapping by WPMUdev
	 *
	 * @since 1.0.2
	 */
	public function wpmudev_dm_flush() {
		//* Check for domain-mapping plugin
		if ( is_multisite() ) {
			if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'domain-mapping/domain-mapping.php' ) ) {
				global $wpdb,$blog_id;

				$ismapped = wp_cache_get( 'w3tc_wpmudev_dm_flush_' . $blog_id, 'domain_mapping' );
				if ( false === $ismapped ) {
					$ismapped = $wpdb->get_var( $wpdb->prepare( "SELECT domain FROM {$wpdb->base_prefix}domain_mapping WHERE blog_id = %d", $blog_id ) ); //string
					wp_cache_set('w3tc_wpmudev_dm_flush_' . $blog_id, $ismapped, 'domain_mapping', 3600 ); // 1 hour
				}

				// We shouldn't flush the object cache here, otherwise the wp_cache_set is useless above this.
				// However, we will flush the object cache if you use domain mapping.
				if ( ! empty( $ismapped ) ) {
					add_action( 'save_post', array( $this, 'w3tc_flush_all' ), 11 ); // We just flush it entirely. But only if the domain is mapped.
				//	add_action( 'save_post', 'wap_w3tc_flush_page_mapped', 21 ); // Unfortunately, not working. I'll try to discuss this with Frederick.
				}
			}
		}
	}

	/**
	 * Flushes entire page cache & the specific page on both mapped / non mapped url.
	 *
	 * Fixes Domain Mapping mixed cache
	 *
	 * @param array $post_ID 	the updated post_ID
	 *
	 * @since 1.1.0
	 * @unused, for now.
	 */
	public function wap_w3tc_flush_page_mapped( $post_ID ) {
		//* Check if subdomain install, else just flush all
		// This should actually work on subdirectory installations too. Needs testing.
		if ( ( defined('SUBDOMAIN_INSTALL') && SUBDOMAIN_INSTALL) || ( defined('VHOST') && VHOST == 'yes' ) ) {
			global $wpdb, $blog_id, $current_blog;

			$post = get_post( $post_ID );

			$originaldomain = $current_blog->domain;

			//* Get mapped domain
			$mappeddomain = wp_cache_get('wap_mapped_domain_' . $blog_id, 'domain_mapping' );
			if ( false === $mappeddomain ) {
				$mappeddomain = $wpdb->get_var( $wpdb->prepare( "SELECT domain FROM {$wpdb->base_prefix}domain_mapping WHERE blog_id = %d", $blog_id ) ); //string
				wp_cache_set('wap_mapped_domain_' . $blog_id, $mappeddomain, 'domain_mapping', 3600 ); // 1 hour
			}

			//* Get scheme setting of mapped domain
			$mappedscheme = wp_cache_get('wap_mapped_scheme_' . $blog_id, 'domain_mapping' );
			if ( false === $mappedscheme ) {
				$mappedscheme = $wpdb->get_var( $wpdb->prepare( "SELECT scheme FROM {$wpdb->base_prefix}domain_mapping WHERE blog_id = %d", $blog_id ) ); //bool
				wp_cache_set('wap_mapped_scheme_' . $blog_id, $mappedscheme, 'domain_mapping', 3600 ); // 1 hour
			}

			//* Get scheme of mapped domain
			if ( '1' === $mappedscheme ) {
				$scheme_mapped = 'https://';
			} else if ( '0' === $mappedscheme ) {
				$scheme_mapped = 'http://';
			}

			//* Get scheme of orginal domain
			if ( method_exists( 'Domainmap_Plugin', 'instance' ) ) {
				$domainmap_instance = Domainmap_Plugin::instance();
				$schemeoriginal = $domainmap_instance->get_option( "map_force_frontend_ssl" ) ? 'https://' : 'http://';
			} else {
				$schemeoriginal = is_ssl() ? 'https://' : 'http://'; //Fallback, not too reliable.
			}

			$relative_url_slash_it = wp_make_link_relative( trailingslashit( get_permalink( $post_ID ) ) );
			$relative_url = wp_make_link_relative( get_permalink( $post_ID ) );

			if ( $post->ID == get_option( 'page_on_front' ) ) {
				$geturls = array(
					$mappeddomain, // example: mappedomain.com
					$scheme_mapped . $mappeddomain, // example: http://mappedomain.com
					$originaldomain, // example: subdomain.maindomain.com
					$schemeoriginal . $originaldomain, // example: https://subdomain.maindomain.com
				);
			} else {
				$geturls = array (
					$mappeddomain . $relative_url, // example: mappedomain.com/some-post(/)
					$mappeddomain . $relative_url_slash_it, // example: mappedomain.com/some-post/

					$scheme_mapped .  $mappeddomain . $relative_url, // example: http://mappedomain.com/some-post(/)
					$scheme_mapped .  $mappeddomain . $relative_url_slash_it, // example: http://mappedomain.com/some-post/

					$originaldomain . $relative_url, // example: subdomain.maindomain.com/some-post(/)
					$originaldomain . $relative_url_slash_it, // example: subdomain.maindomain.com/some-post/

					$schemeoriginal . $originaldomain . $relative_url, // example: https://subdomain.maindomain.com/some-post(/)
					$schemeoriginal . $originaldomain . $relative_url_slash_it, // example: https://subdomain.maindomain.com/some-post/
				);
			}

			//* Flush both mapped and original
			foreach ( $geturls as $key => $url ) {
				if ( function_exists( 'w3tc_pgcache_flush_url' ) )
					w3tc_pgcache_flush_url( $url );

				if ( function_exists( 'w3tc_pgcache_flush_post' ) ) {
					$this->w3tc_flush_all( $post_ID );
				}

				$w3_pgcache = w3_instance('W3_CacheFlush');

				return $w3_pgcache->prime_post($post_ID);
			}

		} else {

			// Purge the entire page cache (current domain)
			// This should be more elaborated, but I don't have the resources or time to apply this, for now.
			$this->w3tc_pgcache_flush();

		}
	}

	/**
	 * Runs all flushes
	 *
	 * @param int $post_id The Single Post ID to flush (excluding the Home Page)
	 * @since 1.0.0
	 */
	public function w3tc_flush_all( $post_id = null ) {

		// Purge the entire db cache
		if ( function_exists( 'w3tc_dbcache_flush' ) )
			w3tc_dbcache_flush();

		// Purge the entire object cache
		if ( function_exists( 'w3tc_objectcache_flush' ) )
			w3tc_objectcache_flush();

		if ( empty( $post_id ) ) {

			/**
			 * Filter wap_flush_all
			 *
			 * Set to true if you wish to flush all pages, regardless.
			 * Else, it will flush only a number of pages defined in $this->limit_flush();
			 *
			 * @since 1.1.4
			 */
			$flush_all = (bool) apply_filters( 'wap_flush_all', false );

			if ( $flush_all ) {
				// Flush all
				if ( function_exists( 'w3tc_flush_all' ) )
					return w3tc_flush_all();
			} else {
				global $wpdb,$blog_id;

				$limit = $this->limit_flush();
				$home_id = (array) get_option( 'page_on_front' );

				$post_type = esc_sql( array( 'post', 'page' ) );
				$post_type_in_string = "'" . implode( "','", $post_type ) . "'";

				// There's no use caching this since the cache is being flushed. We could however perform a static variable with the cache, but it might counteract.
				$sql = $wpdb->prepare( "SELECT ID
					FROM $wpdb->posts
					WHERE post_type IN ($post_type_in_string)
					AND post_date < NOW()
					AND post_status = %s
					ORDER BY post_date DESC
					LIMIT %d
					", 'publish', $limit );

				$page_ids = $wpdb->get_results( $sql, ARRAY_A );

				$id = array();
				foreach ( $page_ids as $array_values ) {
					$id[] = $array_values["ID"];
				}

				// Don't flush home twice.
				$ids = array_unique( array_merge( $id, $home_id ) );

				/**
				 * This will flush the latest 20 posts
				 * And the home page. Making up for a total of 20 or 21.
				 */
				foreach ( $ids as $post_id ) {
					w3tc_pgcache_flush_post( $post_id );
				}

			}
		} else {
			$home_id = get_option( 'page_on_front' );

			if ( $post_id === $home_id ) {
				w3tc_pgcache_flush_post( $post_id );
			} else {
				w3tc_pgcache_flush_post( $home_id );
				w3tc_pgcache_flush_post( $post_id );
			}
		}

	}

	/**
	 * Runs all flushes
	 *
	 * @param array $nav_menu_selected_id, the nav menu id's
	 *
	 * @since 1.1.0
	 */
	public function w3tc_flush_menu( $nav_menu_selected_id = array() ) {
		$this->w3tc_flush_all();
	}

	/**
	 * Runs all flushes when widget is saved or removed.
	 *
	 * @since 1.1.4
	 */
	public function w3tc_flush_all_widget() {

		if ( isset( $_POST['savewidget'] ) || isset( $_POST['removewidget'] ) )
			$this->w3tc_flush_all();

	}

	/**
	 * Runs all flushes when theme is switched
	 *
	 * @since 1.1.4
	 */
	public function w3tc_flush_all_theme() {

		if ( isset( $_GET['action'] ) && $_GET['action'] == 'activate' )
			$this->w3tc_flush_all();

	}

	/**
	 * Runs all flushes
	 *
	 * @param $object the widget object
	 * @since 1.0.0
	 *
	 * Security is handled by function wp_ajax_widgets_order() in ajax-actions.php
	 */
	public function w3tc_flush_all_ajax_widget() {

		if ( isset( $_POST['sidebars'] ) && is_array( $_POST['sidebars'] ) )
			$this->w3tc_flush_all();

	}

	/**
	 * Runs single post flush
	 *
	 * @unused
	 *
	 * @since 1.0.2
	 */
	public function w3tc_flush_single_post() {

		// Purge the single page cache
		if ( function_exists( 'w3tc_pgcache_flush_post' ) ) {
			global $post;
			$post_id = $post->ID;

			w3tc_pgcache_flush_post( $post_id );
		}

	}

	/**
	 * Flushes entire object cache
	 *
	 * @unused
	 *
	 * @since 1.0.0
	 */
	public function w3tc_flush_object() {

		// Purge the entire object cache
		if ( function_exists( 'w3tc_objectcache_flush' ) )
			w3tc_objectcache_flush();

	}

	/**
	 * Removes the Performance admin bar
	 *
	 * @param array $wp_admin_bar 	the admin bar id's or names
	 *
	 * @since 1.0.0
	 */
	public function remove_w3tc_adminbar() {

		// Remove admin menu
		if ( $this->is_not_superadmin() ) {
			global $wp_admin_bar;

			$wp_admin_bar->remove_menu( 'w3tc' );
			$wp_admin_bar->remove_node( 'w3tc' );
		}

	}

	/**
	 * Removes the popup admin script for non-super-admins.
	 *
	 * @since 1.0.4
	 */
	public function remove_w3tc_popup_script() {

		if ( $this->is_not_superadmin() ) {
			$w3_plugin = w3_instance( 'W3_Plugin_TotalCache' );

			// Remove popupadmin script
			remove_action( 'wp_print_scripts', array(
				$w3_plugin,
				'popup_script'
				), 10 );
		}
	}

	/**
	 * Removes inline css and javascript printed by w3tc in the admin dashboard
	 *
	 * @since 1.1.1
	 */
	public function remove_w3tc_admin_styles() {

		if ( $this->is_not_superadmin() ) {
			$w3_plugin = w3_instance( 'W3_Plugin_TotalCacheAdmin' );

			// Remove image styles
			remove_action( 'admin_head', array(
				$w3_plugin,
				'admin_head'
				), 10 );
		}

	}

	/**
	 * Removes the Performance admin menu
	 *
	 * @global array $submenu
	 * @global array $menu
	 *
	 * @since 1.0.0
	 */
	public function remove_w3tc_adminmenu() {

		if ( $this->is_not_superadmin() ) {
			global $submenu,$menu;

			if ( $menu ) {
				foreach( $menu as $key => $submenuitem ) {
					if ( __( 'Performance' ) === __( $submenuitem[0] ) || 'w3tc_dashboard' === $submenuitem[2] ) {
						unset( $menu[$key] );
						unset( $submenu[ 'w3tc_dashboard' ] );
						break;
					}
				}
			}

			//* Adds redirect to dashboard home with error if query arg contains w3tc_
			if ( false !== stripos( $_SERVER['REQUEST_URI'],'admin.php?page=w3tc_' ) ) {
				wp_redirect( get_option('siteurl') . '/wp-admin/index.php?w3tc_permission_denied=true');
			}

		}

	}

	/**
	 * Adds a notice after the redirect takes place
	 *
	 * @since 1.1.0
	 */
	public function wap_die_notice() {

		if ( $this->is_not_superadmin() ) {
			if ( isset( $_GET['w3tc_permission_denied'] ) && $_GET['w3tc_permission_denied'] ) {
				add_action('admin_notices', array( $this, 'no_permissions_admin_notice' ) );
			}
		}

	}

	/**
	 * The redirect notice
	 *
	 * @since 1.1.0
	 *
	 * @return string The Admin Notice
	 */
	public function no_permissions_admin_notice() {
		echo "<div id='permissions-warning' class='error fade'><p><strong>".__( "You do not have the right permissions to access this page.", 'w3tc-auto-pilot' )."</strong></p></div>";
	}

	/**
	 * Removes the post_row_actions and post_submitbox_start "Purge from cache" links
	 *
	 * @uses remove_w3tc_edit_row
	 *
	 * @since 1.0.0
	 */
	public function remove_w3tc_inpost_flush() {

		if ( $this->is_not_superadmin() ) {
			$w3_actions = w3_instance( 'W3_GeneralActions' );

			// Within /wp-admin/edit.php
			add_filter( 'post_row_actions', array( $this, 'remove_w3tc_edit_row' ) );

			// Within /wp-admin/edit.php?post_type=page
			add_filter( 'page_row_actions', array( $this, 'remove_w3tc_edit_row' ) );

			// Within /wp-admin/post.php?post=xxxx&action=edit
			remove_action( 'post_submitbox_start', array(
				$w3_actions,
				'post_submitbox_start'
				), 10);
		}

	}

	/**
	 * Removes the post_row_actions "Purge from cache" links
	 *
	 * @param array $actions The post_row_actions array
	 *
	 * @since 1.0.0
	 */
	public function remove_w3tc_edit_row( $actions ) {

		unset( $actions['pgcache_purge'] );

		return $actions;
	}

	/**
	 * Removes the w3tc_errors and notices from non-super-admins / non-admins (single)
	 *
	 * @since 1.0.1
	 */
	public function remove_w3tc_admin_notices() {

		if ( $this->is_not_superadmin() ) {
			add_filter( 'w3tc_errors', '__return_empty_array' );
			add_filter( 'w3tc_notes', '__return_empty_array' );
		}

	}

	/**
	 * Caches the is_multisite and negatory is_super_admin() call.
	 *
	 * @since 1.1.7
	 * @staticvar $cache
	 *
	 * @return bool Whether is not superadmin.
	 */
	public function is_not_superadmin() {

		static $cache = null;

		if ( isset( $cache ) )
			return $cache;

		if ( is_super_admin() ) {
			return $cache = false;
		}

		return $cache = true;
	}
}
