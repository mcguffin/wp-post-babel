<?php

/**
 *	Edit Posts translations
 */
if ( ! class_exists('GlottyBotEditPosts') ) :
class GlottyBotEditPosts {
	private static $_instance = null;
	
	private static $lang_col_prefix = 'glottybot_translation-';
	
	private $optionset = 'glottybot_options'; // writing | reading | discussion | media | permalink

	private $clone_post_action_name = 'glottybot_clone_post';
	
	private $set_post_locale_action_name = 'set_post_locale';
	
	/**
	 * Getting a singleton.
	 *
	 * @return object single instance of GlottyBotSettings
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) )
			self::$_instance = new self();
		return self::$_instance;
	}

	/**
	 *	Prevent cloning
	 */
	private function __clone() {}

	/**
	 * Private constructor
	 */
	private function __construct() {
		// edit post
		add_action('add_meta_boxes' , array( &$this , 'add_meta_boxes' ) , 10 , 2 );
		add_action( 'wp_ajax_' . $this->clone_post_action_name , array( &$this , 'ajax_clone_post' ) );
		add_action( 'wp_ajax_' . $this->set_post_locale_action_name , array( &$this , 'ajax_set_post_locale' ) );
		add_action( 'load-edit.php' , array( &$this , 'maybe_clone_post' ) );

		add_action( 'admin_init' , array( &$this , 'admin_register_scripts' ) );
		add_action( 'load-edit.php' , array( &$this , 'add_post_type_columns' ) );
		add_action( 'load-upload.php' , array( &$this , 'add_post_type_columns' ) );

		add_action( 'load-post.php' , array( &$this , 'enqueue_script_style' ) );
		add_action( 'load-post-new.php' , array( &$this , 'enqueue_script_style' ) );
		add_action( 'load-edit.php' , array( &$this , 'enqueue_script_style' ) );
		add_action( 'load-upload.php' , array( &$this , 'enqueue_script_style' ) );
		
		add_action( 'load-edit.php' , array( &$this , 'bulk_actions' ) );

		add_filter( 'wp_insert_post_data', array( &$this , 'filter_insert_post_data' ) , 10 , 2 );
		add_filter( 'wp_insert_attachment_data', array( &$this , 'filter_insert_post_data' ), 10 , 2 );
		
		add_action( 'page_row_actions' , array( &$this , 'row_actions' ) , 10 , 2 );
		add_action( 'post_row_actions' , array( &$this , 'row_actions' ) , 10 , 2 );
		
		// y?
// 		add_filter( 'redirect_post_location', array( &$this , 'redirect_post_location' ) , 10 , 2 );
	}
	
	/**
	 *	Perform bulk actions
	 *	
	 *	@action 'load-edit.php'
	 */
	function bulk_actions() {
		if ( isset( $_REQUEST['action'] ) ) {
			$action = $_REQUEST['action'];
			switch ( $action ) {
				case 'glottybot_trash_translation_group':
					if ( isset( $_REQUEST['translation_group'] ) ) {
						$sendback = admin_url('edit.php');
						$translation_group = intval($_REQUEST['translation_group']);
						$nonce_name = $action . '-' . $translation_group;
						check_admin_referer( $nonce_name );
						$post_ids = array();
						$translations = GlottyBotPost::get_translation_group($translation_group);
						foreach ( $translations as $locale => $translated_post ) {
							// check caps
							if ( ! current_user_can( 'delete_post', $translated_post->ID ) )
								continue;
								
							// check post lock!
							if ( wp_check_post_lock( $translated_post->ID ) )
								continue;
							
							if ( wp_trash_post( $translated_post->ID ) )
								$post_ids[] = $translated_post->ID;
						}
						wp_redirect( add_query_arg( array('trashed' => 1, 'ids' => implode( ',' , $post_ids ) ), $sendback ) );
						exit();
					}
				case 'glottybot_clone_missing_translations':
					break;
			}

		}
	}
	/**
	 *	@action 'admin_init'
	 */
	function admin_register_scripts() {
		wp_register_style( 'glottybot-editpost' , plugins_url('css/glottybot-editpost.css', dirname(__FILE__)) );
		wp_register_script( 'glottybot-editpost' , plugins_url('js/glottybot-editpost.js', dirname(__FILE__)) , array( 'jquery' ) );
	}
	
	//
	//	Cloning
	//

// 	/**
// 	 *	URL to post cloning
// 	 *
// 	 *	@param $source_id
// 	 *	@param $target_locale
// 	 *	@return string URL
// 	 */
// 	function clone_post_url( $source_id , $target_locale ){
// 		return add_query_arg( 
// 			$this->clone_post_url_args(  $source_id , $target_locale ),
// 			admin_url('edit.php')
// 		);
// 		
// 	}
// 
// 	/**
// 	 *	Ajax URL to post cloning
// 	 *
// 	 *	@param $source_id
// 	 *	@param $target_locale
// 	 *	@return string URL
// 	 */
// 	function ajax_clone_post_url( $source_id , $target_locale ) {
// 		return add_query_arg( 
// 			$this->clone_post_url_args(  $source_id , $target_locale ),
// 			admin_url('admin-ajax.php')
// 		);
// 	}
// 	
// 	/**
// 	 *	@param $source_id
// 	 *	@param $target_locale
// 	 *	@return array
// 	 */
// 	function clone_post_url_args(  $source_id , $target_locale ) {
// 		if ( ! intval( $source_id ) )
// 			return false;
// 		if ( ! $target_locale )
// 			return false;
// 		if ( ! current_user_can( 'edit_post' , $source_id ) )
// 			return false;
// 		
// 		$action		= $this->clone_post_action_name;
// 		$nonce_name	= sprintf('%s-%s-%d' , $action , $target_locale , $source_id );
// 		$nonce		= wp_create_nonce( $nonce_name );
// 		return array(
// 			'action' => $action,
// 			'_wpnonce' => $nonce,
// 			'source_id' => intval( $source_id ),
// 			'target_locale'	=> $target_locale,
// 		);
// 	}
// 	
	
	/**
	 *	@param $source_id
	 *	@param $target_locale
	 *	@return object GlottyBotPost | object WP_Error | bool false
	 */
	function get_post_to_clone( ) {
		if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == $this->clone_post_action_name ) {
			if ( isset( $_REQUEST['post_locale'] , $_REQUEST['post_id'] , $_REQUEST['_wpnonce'] ) ) {
				
				// $source_id set?
				$source_id		= intval($_REQUEST['post_id']);
				if ( ! $source_id )
					return new WP_Error( __('Bad request') );

				// $target_locale installed?
				$target_locale	= $_REQUEST['post_locale'];
				if ( ! in_array( $target_locale , GlottyBot()->get_locales() ) )
					return new WP_Error( __('Requested Locale inactive') );
				
				// permissions okay?
				$nonce_name		= sprintf('%s-%s-%d' , $this->clone_post_action_name , $target_locale , $source_id );
				if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'] , $nonce_name ) || ! current_user_can( 'edit_post' , $source_id ) )
					return new WP_Error( __('Insufficient permission') );
				
				$post = GlottyBotPost( $source_id );
				if ( $post->ID && $target_locale == $post->post_locale )
					return new WP_Error( __( 'Post translation exists' ) );
				
				// post exists
				return $post;
			} else {
				return new WP_Error( __('Bad request') );
			}
		}
		return false;
	}

	/**
	 *	@action load-edit.php
	 */
	function maybe_clone_post() {
		$post = $this->get_post_to_clone( );
		if ( $post !== false ) {
			if ( is_wp_error( $post ) ) {
				wp_die( $post );
			} else if ( $post instanceof GlottyBotPost ) {
				// do clone
				$translated_post_id = $post->clone_for_translation( $_REQUEST['target_locale'] );
				if ( is_wp_error( $translated_post_id ) )
					wp_die( $translated_post_id );
				
				$redirect = get_edit_post_link( $translated_post_id , 'redirect' );
				wp_redirect( $redirect );
				exit();
			}
		}
	}

	
	/**
	 *	@action 'wp_ajax_'.$this->clone_post_action_name
	 */
	function ajax_clone_post() {
		header('Content-Type: application/json');
		$post = $this->get_post_to_clone( );
		$response = array(
			'success' 			=> false,
			'error'				=> '',
			'post_id'			=> 0,
			'post_edit_uri'		=> '',
			'post_edit_link'	=> '',
			'post_status'		=> '',
		);
		
		if ( $post !== false ) {
			if ( is_wp_error( $post ) ) {
				$response['error'] = $post;
			} else if ( $post instanceof GlottyBotPost ) {
				// do clone
				$translated_post_id = $post->clone_for_translation( $_REQUEST['post_locale'] );
				if ( is_wp_error( $translated_post_id ) ) {
					$response['error'] = $translated_post_id;
				} else {
					$new_post = get_post( $translated_post_id );
					$edit_post_uri = get_edit_post_link( $translated_post_id , '' );
		
					$edit_post_link = sprintf( '<a class="lang-action edit translated" href="%s">%s<span title="%s" class="dashicons dashicons-%s"></span></a>' , 
						$edit_post_uri , 
// 						$translations[$locale]->post_title,
						GlottyBotTemplate::i18n_item( $new_post->post_locale ),
						$new_post->post_title , 
						'edit'  // could be hammer -> draft
					);
					$response = array(
						'success' 			=> true,
						'post_id'			=> $new_post->ID,
						'post_edit_uri'		=> $edit_post_uri,
						'post_edit_link'	=> $edit_post_link,
						'post_status'		=> $new_post->post_status,
					);
				}
			}
		}
		echo json_encode( $response );
		die;
	}
	
	function ajax_set_post_locale(){
		header('Content-Type: application/json');
		$response = array(
			'success' 				=> true,
			'error'					=> '',
			'translationButtonHtml'	=> '',
		);
			
		if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == $this->set_post_locale_action_name ) {
			if ( isset( $_REQUEST['locale'] , $_REQUEST['post_id'] , $_REQUEST['target_post_id'] , $_REQUEST['_wpnonce'] ) 
					&& ($post_ID = intval($_REQUEST['post_id']) )
					&& ($target_post_ID = intval($_REQUEST['target_post_id']) )
				) {
				
				// permissions okay?
				$nonce_name = $this->set_post_locale_action_name . '-' . $post_ID;
				if ( wp_verify_nonce( $_REQUEST['_wpnonce'] , $nonce_name ) && current_user_can( 'edit_post' , $post_ID ) ) {
					
					// $target_locale installed?
					$target_locale	= $_REQUEST['locale'];
					if ( in_array( $target_locale , GlottyBot()->get_locales() ) ) {
						// check if 
						$target_post = GlottyBotPost( $target_post_ID );
						if ( ! $target_post->get_translation( $target_locale ) ) {
							$update_post_data = array(
								'ID' => $post_ID,
								'post_translation_group' => $target_post->post_translation_group,
								'post_locale' => $target_locale
							);
							// post exists
							$update_result = wp_update_post( $update_post_data , true );
							if ( !is_wp_error( $update_result ) ) {
								$response['translationButtonHtml'] = 
									sprintf( '<a data-post-id="%d" data-post-locale="%s" data-ajax-nonce="%s" class="%s" href="%s" title="%s">%s <span class="dashicons dashicons-%s"></span> </a>' , 
										$post_ID,
										$target_locale,
										wp_create_nonce( $nonce_name ),
										'lang-action edit translated ui-draggable',
										get_edit_post_link( $post_ID ), 
										$edit_post_title,
										GlottyBotTemplate::i18n_item( $target_locale ),
										'edit'
									);
							} else {
								$response['error'] = $update_result;
								$response['success'] = false;
							}
						} else {
							$response['error'] = new WP_Error( 'glottybot' , __( 'Post translation exists','wp-glottybot' ) );
							$response['success'] = false;
						}
					} else {
						$response['error'] = new WP_Error( 'glottybot' , __('Invalid Locale','wp-glottybot') );
						$response['success'] = false;
					}
				} else {
					$response['error'] = new WP_Error( 'glottybot' , __('Insufficient permission') );
					$response['success'] = false;
				}
				
			} else {
				$response['error'] = new WP_Error( 'glottybot' , __('Bad request') );
				$response['success'] = false;
			}
		}
		echo json_encode( $response );
		die;
	}
	
	
	/**
	 *	@filter 'wp_insert_post_data'
	 *	@filter 'wp_insert_attachment_data'
	 */
	function filter_insert_post_data( $data , $postarr ) {
		if ( isset( $postarr['post_locale'] ) && ! isset( $data['post_locale'] ) )
			$data['post_locale'] = $postarr['post_locale'];
		else if ( ! isset( $data['post_locale'] ) )
			$data['post_locale'] = GlottyBotAdmin()->get_locale(); // get admin language!
		
		if ( isset( $postarr['post_translation_group'] ) && ! isset( $data['post_translation_group'] ) ) {
			$data['post_translation_group'] = $postarr['post_translation_group'];
		} else if ( ! isset( $data['post_translation_group'] ) ) {
			$data['post_translation_group'] = 0; // get admin language!
			add_action( 'add_attachment' , array( &$this , 'set_post_translation_group' ) ); // $post_ID alw
			add_action( 'wp_insert_post' , array( &$this , 'set_post_translation_group' ) , 10 , 3 ); // $post_ID alw
		}
		return $data;
	}
	
	/**
	 *	@action 'add_attachment'
	 *	@action 'wp_insert_post'
	 */
	function set_post_translation_group( $post_ID , $post = null , $update = false ) {
		global $wpdb;
		if ( ! $update ) {
			$wpdb->update( $wpdb->posts , array( 'post_translation_group' => $post_ID ) , array( 'ID' => $post_ID ) );
		}
	}
	

	/**
	 *	@action 'load-post.php'
	 *	@action 'load-post-new.php'
	 */
	function enqueue_script_style() {
		wp_enqueue_script( 'glottybot-editpost' );
		wp_enqueue_style( 'glottybot-editpost' );
	}
	
	// --------------------------------------------------
	// add meta boxes to all post content
	// --------------------------------------------------

	/**
	 *	@action 'add_meta_boxes'
	 */
	function add_meta_boxes( $post_type , $post ) {
		global $wp_post_types;
// 		if ( $post->post_status == 'auto-draft' ) 
// 			return;
		foreach ( array_keys($wp_post_types) as $post_type ) {
			$post_type_object = get_post_type_object( $post_type );
			if ( $post_type_object->public )
				add_meta_box( 'glottybot-post-language' , __('Multilingual','wp-glottybot') , array(&$this,'language_metabox') , $post_type , 'side' , 'high' );
		}
	}

	// --------------------------------------------------
	// edit post - the meta box
	// --------------------------------------------------
	/**
	 *	@callback_arg add_meta_box()
	 */
	function language_metabox() {
		global $wp_roles;
		$post 				= GlottyBotPost( get_the_ID() );
		$post_type_object 	= get_post_type_object($post->post_type);
		$translations		= $post->get_translations();

		$system_langs		= GlottyBot()->get_locale_names();
		$locale = GlottyBotLocales::get_locale_names( $post->post_locale );
		
		?><div class="post_locale misc-pub-section"><?php
			?><strong><?php _e( 'Language:' , 'wp-glottybot' ); ?> </strong><?php
			echo GlottyBotTemplate::i18n_item( $post->post_locale );
			echo $locale[$post->post_locale];
			
		?></div><?php
		// show translations here.
		if ( $post->post_status != 'auto-draft' ) {
			$translatable_langs	= $system_langs;
			unset( $translatable_langs[ $post->post_locale ] );

			if ( $translatable_langs ) {
				?><div class="add-post_locales misc-pub-section"><?php
				
					?><h4><?php _e('Translations:','wp-glottybot') ?></h4><?php
					?><table><?php
						foreach ( $translatable_langs as $locale => $language_name ) {
							?><tr><td><?php
							if ( isset( $translations[$locale] ) && $translations[$locale] ) {
								echo $this->_edit_post_button( $translations[$locale] );
								
								echo edit_post_link( $translations[$locale]->post_title , null , null, $translations[$locale]->ID ); 
							} else {
								// clone button
								echo $this->_clone_post_button( $post , $locale );
								printf( _x('Copy this %s','%s post type','wp-glottybot') , $post_type_object->labels->singular_name );
							}
						?></td></tr><?php
					}
					?></table><?php
				?></div><?php
			}
		}
	}
	
	private function _clone_post_button( $post , $locale ) {
		if ( is_numeric($post) )
			$post = get_post($post);
		if ( ! $post )
			return;
		
		$nonce_name = sprintf('%s-%s-%d' , $this->clone_post_action_name , $locale , $post->ID );
		$output = '';
		$output .= '<span class="spinner"></span>';
		$output .= sprintf('<button class="lang-action add untranslated copy-post"
			data-ajax-action="%s" 
			data-ajax-nonce="%s" 
			data-post-locale="%s" 
			data-post-id="%d" >%s<span class="dashicons dashicons-plus"></span></button>',
			
				$this->clone_post_action_name,
				wp_create_nonce( $nonce_name ),
				$locale,
				$post->ID,
				GlottyBotTemplate::i18n_item( $locale )
			);
			return $output;
	}

	private function _edit_post_button( $post ) {
		if ( is_numeric($post) )
			$post = get_post($post);
		if ( ! $post )
			return;
		
		// edit translation
		// icons: @private dashicons-lock | @trash dashicons-trash | @public dashicons-edit | @pending dashicons-backup | @draft dashicons-hammer
		$class = array();
		$edit_post_uri = get_edit_post_link( $post->ID );
		switch ( $post->post_status ) {
			case 'private':
				$dashicon = 'lock';
				$title = __('Privately Published');
				$class[] = 'translated';
				break;
			case 'trash':
				$dashicon = 'trash';
				$title = __('Trashed');
				$class[] = 'untranslated';
				// untrash action
				break;
			case 'future':
				$dashicon = 'backup';
				$title = __('Pending');
				$class[] = 'translated';
				break;
			case 'draft':
				$dashicon = 'hammer';
				$title = __('Draft');
				$class[] = 'translated';
				break;
			case 'pending':
				$dashicon = 'clock';
				$title = __('Pending Review');
				$class[] = 'translated';
				break;
			case 'publish':
			default:
				$dashicon = 'edit';
				$title = __('Edit');
				$class[] = 'translated';
				break;
		}
		$output = sprintf( '<a class="lang-action edit %s" href="%s">%s<span title="%s" class="dashicons dashicons-%s"></span></a>' , 
			implode(' ',$class) ,
			$edit_post_uri , 
			GlottyBotTemplate::i18n_item( $post->post_locale ),
			$title , 
			$dashicon 
		);
		return $output;
	}
	
/*
ajax:
	check nonce, check caps, clone.
*/
	
	
	/**
	 * 	Custom columns
	 *	@action 'load-edit.php'
	 *	@action 'load-upload.php'
	 */
	function add_post_type_columns() {
		$current_post_type = isset( $_REQUEST['post_type'] ) ? $_REQUEST['post_type'] : ( GlottyBotAdmin()->is_admin_page( 'upload.php' ) ? 'attachment' : 'post' );
		switch ( $current_post_type ) {
			case 'post':
				// posts
				add_filter('manage_posts_columns' , array( &$this , 'add_language_column') );
				add_action('manage_posts_custom_column' , array( &$this , 'manage_language_column') , 10 ,2 );
				break;
			case 'page':
				add_filter('manage_pages_columns' , array( &$this , 'add_language_column') );
				add_action('manage_pages_custom_column' , array( &$this , 'manage_language_column') , 10 ,2 );
				break;
			case 'attachment':
				add_filter('manage_media_columns' , array( &$this , 'add_language_column') );
				add_action('manage_media_custom_column' , array( &$this , 'manage_language_column') , 10 ,2 );
				break;
			default:
				if ( GlottyBot()->is_post_type_translatable( $current_post_type ) ) {
					add_filter( "manage_{$current_post_type}_posts_columns" , array( &$this , 'add_language_column'));
					add_filter( "nav_menu_items_{$current_post_type}", array(&$this,'nav_menu_items_posts') , 10 , 3 );
				}
		}
		if ( GlottyBot()->is_post_type_translatable( $current_post_type ) ) {
			add_filter( "nav_menu_items_post", array(&$this,'nav_menu_items_posts') , 10 , 3 );
			add_filter( "nav_menu_items_page", array(&$this,'nav_menu_items_posts') , 10 , 3 );
			add_filter( 'get_edit_post_link' , array(&$this,'edit_post_link') , 10 , 3 );
			add_filter( 'post_class', array(&$this , 'post_class' ) , 10 , 3 );
		}
	}
	
	/**
	 *	@filter 'post_class'
	 */
	function post_class( $classes, $class, $post_ID ) {
		$post = GlottyBotPost($post_ID);
		if ( ! $translated_post = $post->get_translation( GlottyBotAdmin()->get_locale() ) ) {
			$classes[] = 'untranslated';
		}
		return $classes;
	}
	
	/**
	 *	@filter 'the_title'
	 */
	function edit_post_link( $link , $post_ID , $context ) {
		$locale = GlottyBotAdmin()->get_locale();
		$post = GlottyBotPost( $post_ID );
		if ( $post->post_locale == $locale ) {
			return $link;
		} else if ( $translated_post = $post->get_translation( $locale ) ) {
			return get_edit_post_link( $translated_post->ID );
		} else {
	//		return $this->clone_post_url( $post_ID , GlottyBotAdmin()->get_locale() ); 
		}
		return $link;
	}
	/**
	 *	@filter 'the_title'
	 */
	function post_title( $title , $post_ID ) {
		$post = GlottyBotPost($post_ID);
		if ( $translated_post = $post->get_translation( GlottyBotAdmin()->get_locale() ) ) {
			return $title;
		} else {
			return sprintf( __( 'Clone "%s"' , 'wp-glottybot' ) , $title );
		}
	}
	
	/**
	 *	@filter 'post_row_actions'
	 *	@filter 'page_row_actions'
	 */
	function row_actions( $actions , $post ) {
		$post = GlottyBotPost( $post );
		if ( $translated_post = $post->get_translation( GlottyBotAdmin()->get_locale() ) ) {
			if ( current_user_can( 'delete_post' , $translated_post->ID ) ) {
				if ( $translated_post->post_status != 'trash' ) {
					$action = 'glottybot_trash_translation_group';
					$nonce_name = $action . '-' . $translated_post->post_translation_group;
					$link = add_query_arg( array( 
						'action' => $action,
						'translation_group' => $translated_post->post_translation_group,
					) , admin_url('edit.php') );
					$link = wp_nonce_url( $link , $nonce_name );
					$link_tpl = '<a class="submitdelete" title="%s" href="%s">%s</a>';
					$actions['trash trash_translation_group'] = sprintf( $link_tpl , 
							__('Move all translations to the Trash.','wp-glottybot') , 
							$link,
							__('Trash all translations'));
				}
			}
		} else {
// 			$edit_post_uri = $this->clone_post_url( $post->ID , GlottyBotAdmin()->get_locale() ); 
// 			$edit_post_uri = add_query_arg( 'language' , $post->post_locale , $edit_post_uri );
// 			$edit_post_link = sprintf( '<a href="%s">%s</a>' , 
// 				$edit_post_uri , 
// 				sprintf( __('Clone Post to %s','wp-glottybot') , GlottyBotLocales::get_locale_name( GlottyBotAdmin()->get_locale() )  )
// 			);
// 
			$actions = array(
// 				'edit' => $edit_post_link,
			);
			
		}
		return $actions;
	}
	
	function nav_menu_items_posts( $posts, $args, $post_type ) {
		$args['suppress_filters'] = false;
		
		$get_posts = new WP_Query;
		$new_posts = $get_posts->query( $args );
		
		if ( 'page' == $post_type ) {
			$front_page = 'page' == get_option('show_on_front') ? (int) get_option( 'page_on_front' ) : 0;
			if ( ! empty( $front_page ) ) {
				$front_page_obj = get_post( $front_page );
				$front_page_obj->front_or_home = true;
				array_unshift( $new_posts, $front_page_obj );
			} else {
				$_nav_menu_placeholder = ( 0 > $_nav_menu_placeholder ) ? intval($_nav_menu_placeholder) - 1 : -1;
				array_unshift( $new_posts, (object) array(
					'front_or_home' => true,
					'ID' => 0,
					'object_id' => $_nav_menu_placeholder,
					'post_content' => '',
					'post_excerpt' => '',
					'post_parent' => '',
					'post_title' => _x('Home', 'nav menu home label'),
					'post_type' => 'nav_menu_item',
					'type' => 'custom',
					'url' => home_url('/'),
				) );
			}
		}
		return $new_posts;
	}
	function add_language_column( $columns ) {
		global $post_type;
		$post_type_object = get_post_type_object( $post_type );
		
		$locales = GlottyBot()->get_locales();
		usort($locales,array( $this , '_sort_locales_current_first' ) );
		$cols = array();
		// check after which column to insert access col
		$afters = array('title','cb');
	
		foreach ( $afters as $after )
			if ( isset($columns[$after] ) )
				break;
		$column_name = 'language';
		foreach ($columns as $k=>$v) {
			$cols[$k] = $v;
			if ($k == $after ) {
				foreach ( $locales as $locale )
					$cols[self::$lang_col_prefix.$locale] = '';//GlottyBotTemplate::i18n_item( $lang );//__('Translations','wp-glottybot');
			}
		}
		$columns = $cols;
		return $columns;
	}
	
	private function _sort_locales_current_first( $a , $b ) {
		$loc = GlottyBotAdmin()->get_locale();
		if ( $a == $loc )
			return -1;
		else if ( $b == $loc )
			return 1;
		else 
			return 0;
	}
	
	function manage_language_column($column, $post_ID) {
		remove_filter( 'get_edit_post_link' , array(&$this,'edit_post_link') , 10 );
		$post = GlottyBotPost($post_ID);
		self::$lang_col_prefix = 'glottybot_translation-';

		if ( strpos( $column , self::$lang_col_prefix ) !== false ) {
			$locale = str_replace( self::$lang_col_prefix , '' , $column );
			$class = array('lang-action');
			if ( $translated_post = $post->get_translation( $locale ) ) {
				echo $this->_edit_post_button( $translated_post );
			} else {
				echo $this->_clone_post_button( $post , $locale );
			}
		}
		add_filter( 'get_edit_post_link' , array(&$this,'edit_post_link') , 10 , 3 );
	}
}
endif;
