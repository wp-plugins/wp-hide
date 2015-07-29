<?php
/*
Plugin Name: Wp-Hide
Plugin URI: http://www.weberge.com/blog/wp-hide/
Description: Hide Wordpress plugin.
Author: Weberge
Author URI: http://www.weberge.com/
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: wp-hide
Version: 0.0.1 beta
*/

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'wp_hide' ) ) :

	final class wp_hide {

		function __construct() {
			automatic_feed_links();
			add_action('init', array($this,'remove_junk'));
			add_filter('show_admin_bar', '__return_false');
			add_filter('redirect_canonical', array($this,'stop_guessing'));
			add_action( 'template_redirect', array($this,'remove_author_pages_page' ));
			add_filter( 'author_link', array($this,'remove_author_pages_link'));

			register_activation_hook( __FILE__, array($this,'on_activate') );
			add_action( 'template_redirect', array($this,'on_render_frontend') );
		}


		function on_activate() {

			function get_ext($file) {
				$extension = end(explode(".", $file));
				return $extension ? strtolower($extension) : false;
			}


			global $wp_get_files_list, $domain, $site_url, $home_path, $assets_dir;

			$ssl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? 'https://' : 'http://';
			$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : @getenv('HTTP_HOST');

			$wp_get_files_list =  array();
			$wp_get_files_list['html_encode'] = array();
			$wp_get_files_list['htacess_decode'] = array();

			$domain = $ssl . $host;
			$site_url  = get_site_url() . '/';
			$home_path = get_home_path();
			$assets_dir   =  'public/';
			$upload_dir   =  'upload/';


			function wp_get_files($dir) {
				global $wp_get_files_list, $domain, $site_url, $home_path, $assets_dir;

				if(is_dir($dir)) {
					if($dh = opendir($dir)){
						$file_id = 1;
						while($file = readdir($dh)){
							if($file != '.' && $file != '..'){
								if(is_dir($dir . $file)){
									wp_get_files($dir . $file . '/');
								}else{
									if(get_ext($file) == 'js' || get_ext($file) == 'css' || get_ext($file) == 'jpg' || get_ext($file) == 'jpeg' || get_ext($file) == 'gif' || get_ext($file) == 'png' || get_ext($file) == 'apng' || get_ext($file) == 'tiff' || get_ext($file) == 'svg' || get_ext($file) == 'pdf' || get_ext($file) == 'css' || get_ext($file) == 'bmp' ){                    	
										$rand_code = rand(99,999);	
										$wp_get_files_list['html_encode'][str_replace($home_path, $site_url, $dir . $file)] = $site_url  . $assets_dir .  $file_id . $rand_code . '.' . get_ext($file);
										$wp_get_files_list['htacess_decode'][ $file_id . $rand_code . '.' . get_ext($file)] = str_replace($domain ,'',str_replace($home_path, $site_url, $dir . $file));
									}

								}
							}
							$file_id++;
						}
					}
					closedir($dh);         
				}
			}

			$wp_template_dir =  get_template_directory() . '/';
			$wp_plugin_dir   =  WP_PLUGIN_DIR . '/';
			$wp_upload_dir_array = wp_upload_dir();
			$wp_upload_dir = $wp_upload_dir_array['basedir'] . '/';
			$wp_upload_url = $wp_upload_dir_array['baseurl'] . '/';
			$wp_include_dir = ABSPATH . WPINC . '/';


			wp_get_files($wp_template_dir);
			wp_get_files($wp_plugin_dir);
			wp_get_files($wp_include_dir);

			$wp_get_files_list['html_encode'][$wp_upload_url] = $site_url . $assets_dir . $upload_dir;
			$wp_get_files_list['htacess_decode'][ $upload_dir ] = str_replace($domain ,'',str_replace($home_path, $site_url, $wp_upload_url));


			if (!file_exists($home_path . $assets_dir)) {
				mkdir($home_path . $assets_dir, 0777, true);
			}

			$htaccess_content .= '<IfModule mod_rewrite.c> ' . PHP_EOL;
			$htaccess_content .= 'Options -Indexes' . PHP_EOL;
			$htaccess_content .= 'RewriteEngine on '. PHP_EOL;

			foreach ($wp_get_files_list['htacess_decode'] as $path_frm => $path_to){

				$htaccess_content .= 'RewriteRule ^' . str_replace($assets_dir,'',$path_frm) . '(.*)$ ' . $path_to . '$1 [L] ' . PHP_EOL;
			}

			$htaccess_content .= '</IfModule>';

			@file_put_contents($home_path . $assets_dir . '.htaccess', $htaccess_content);

			update_option( 'wp_hide_html_encode', $wp_get_files_list['html_encode']);

		}


		function on_render_frontend(){

			ob_start(function($html){

				$wp_hide_html_encode = get_option('wp_hide_html_encode');

				if(is_array( $wp_hide_html_encode )){
					$html = strtr($html, $wp_hide_html_encode);
				}

				return str_replace(array("\n","\r","\t"),'',$html);
				
			});

		}

		function remove_junk() {
			remove_action('wp_head', 'rsd_link');
			remove_action('wp_head', 'wp_generator');
			remove_action('wp_head', 'feed_links', 2);
			remove_action('wp_head', 'index_rel_link');
			remove_action('wp_head', 'wlwmanifest_link');
			remove_action('wp_head', 'feed_links_extra', 3);
			remove_action('wp_head', 'start_post_rel_link', 10, 0);
			remove_action('wp_head', 'parent_post_rel_link', 10, 0);
			remove_action('wp_head', 'adjacent_posts_rel_link', 10, 0);
		}

		function remove_author_pages_page() {
			if ( is_author() ) {
				global $wp_query;
				$wp_query->set_404();
				status_header( 404 );
			}
		}

		function remove_author_pages_link( $content ) {
			return get_option( 'home' );
		}	

		function stop_guessing($url) {
			if (is_404()) {
				return false;
			}
			return $url;
		}


	}

	new wp_hide();

	endif;