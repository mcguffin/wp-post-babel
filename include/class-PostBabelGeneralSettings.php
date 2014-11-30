<?php


if ( ! class_exists( 'PostBabelGeneralSettings' ) ):
class PostBabelGeneralSettings {
	private static $_instance = null;
	
	private $optionset = 'general'; // general | writing | reading | discussion | media | permalink

	/**
	 * Getting a singleton.
	 *
	 * @return object single instance of PostBabelSettings
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) )
			self::$_instance = new self();
		return self::$_instance;
	}

	/**
	 * Private constructor
	 */
	private function __construct() {
		add_action( 'admin_init' , array( &$this , 'register_settings' ) );

		add_action( "load-options-{$this->optionset}.php" , array( &$this , 'enqueue_assets' ) );
		/*
		Options / General:
		- additional languages (default language = blog langugae)
		
		Options / Multilanguage:
		- Search: 
			(•) Search in any Language 
			( ) Search only in current language
			
		- Show: only translated content / all content
			(•) Only show translated Content
			( ) Show all content
			
		- Translatable post types
			(Select only from public post types)
			[√] Posts
			[√] Pages
			[√] Media

		*/
		add_option( 'post_babel_additional_languages' , '' , '' , False );
	}

	/**
	 * Enqueue options Assets
	 */
	function enqueue_assets() {
		require_once( ABSPATH . 'wp-admin/includes/translation-install.php' );
		wp_enqueue_style( 'post_babel-settings' , plugins_url( '/css/post_babel-settings.css' , dirname(__FILE__) ));

		wp_enqueue_script( 'post_babel-settings' , plugins_url( 'js/post_babel-settings.js' , dirname(__FILE__) ) );
		wp_localize_script('post_babel-settings' , 'post_babel_settings' , array(
			'available_translations'    => postbabel_wp_get_available_translations(),
		) );
	}
	


	/**
	 * Setup options page.
	 */
	function register_settings() {
		$settings_section = 'post_babel_settings';
		// more settings go here ...
		register_setting( $this->optionset , 'post_babel_additional_languages' , array( &$this , 'sanitize_setting_additional_languages' ) );

		add_settings_section( $settings_section, __( 'Multilingual',  'wp-post-babel' ), array( &$this, 'multilingual_description' ), $this->optionset );
		// ... and here
		add_settings_field(
			'post_babel_additional_languages',
			__( 'Additional Languages',  'wp-post-babel' ),
			array( $this, 'additional_languages_ui' ),
			$this->optionset,
			$settings_section
		);
	}

	/**
	 * Print some documentation for the optionset
	 */
	public function multilingual_description() {
		?>
		<div class="inside">
			<p><?php _e( 'You can make more languages available through the Site Language option above.' , 'wp-post-babel' ); ?></p>
		</div>
		<?php
	}
	
	/**
	 * Output Theme selectbox
	 */
	public function additional_languages_ui(){
		$setting_name = 'post_babel_additional_languages';
		$additional_languages = $this->sanitize_setting_additional_languages( (array) get_option($setting_name) );
		$l = array_diff( postbabel_wp_get_available_languages() , array( get_option( 'WPLANG' ) ) );
		postbabel_dropdown_languages( array(
			'name'			=> '',
			'id'			=> 'add_language',
			'selected'		=> '',
			'languages'		=> array_diff( postbabel_wp_get_available_languages() , array( get_option( 'WPLANG' ) ) ),
			'disabled'		=> $additional_languages,
		) );
		
		$translations = postbabel_wp_get_available_translations();
		
		$template = '<span class="language-item">';
		$template .= 	'<input type="hidden" name="'.$setting_name.'[]" value="%language_code%" />';
		$template .= 	'<span class="language-name"><span class="english-name">%english_name%</span> / <span class="native-name">%native_name%</span></span>';
		$template .= 	'<button class="remove button secondary">' . __('—') . '</button>';
		$template .= '</span>';
		
		?><button id="add_language_button" class="button secondary"><?php 
			_e('+');
		?></button><?php
		?><div id="additional-languages"><?php
			foreach ( $additional_languages as $lang ) {
				$language = array( 
					'%language_code%' => $lang , 
					'%english_name%' => $translations[$lang]['english_name'],
					'%native_name%' => $translations[$lang]['native_name'],
				);
				echo strtr( $template , $language );
			}
		?></div><?php
		?><script type="text/template" id="language-item-template"><?php
		echo $template;
		?></script><?php
	}
	

	/**
	 * Sanitize value of setting_1
	 *
	 * @return string sanitized value
	 */
	function sanitize_setting_additional_languages( $value ) {
		$value = (array) $value;
		$value = array_filter($value);
		$value = array_unique($value);
		return array_diff( array_intersect( $value , postbabel_wp_get_available_languages() ) , array( get_option( 'WPLANG' ) ) );
	}
}

endif;