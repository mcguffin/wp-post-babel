<?php


/**
 *	Append language selector to WP settings
 */
if ( ! class_exists( 'GlottyBotGeneralSettings' ) ):
class GlottyBotGeneralSettings {
	private static $_instance = null;
	
	private $optionset = 'general'; // general | writing | reading | discussion | media | permalink

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
		add_action( 'admin_init' , array( &$this , 'register_settings' ) );
		add_action( "load-options-{$this->optionset}.php" , array( &$this , 'enqueue_assets' ) );
		add_option( 'glottybot_translations' , '' , '' , false );
		add_option( 'glottybot_hide_untranslated' , true );
// 		add_action( 'update_option_WPLANG' , array( &$this , 'update_system_language' ) , 10 , 2 );
	}
	
	/**
	 *	Make sure additional langs do not contain WPLANG.
	 *	Hooks into wp action `update_option_WPLANG`
	 *	
	 *	@see wp filter update_option_{$option}
	 */
	function update_system_language( $old , $new ) {
		if ( $old == '' )
			$old = 'en_US';
		if ( $new == '' )
			$new = 'en_US';
		$additional_langs = get_option( 'glottybot_translations' );
// 		$additional_langs[] = glottybot_language_code_sep( $old , '_' );
// 		$additional_langs = array_unique( array_diff( $additional_langs , array( $new ) ) );
		update_option( 'glottybot_translations' , $additional_langs );
	}

	/**
	 * Enqueue options Assets when loading options page.
	 */
	function enqueue_assets() {
		require_once( ABSPATH . 'wp-admin/includes/translation-install.php' );
		wp_enqueue_style( 'glottybot-settings' , plugins_url( '/css/glottybot-settings.css' , dirname(__FILE__) ));
		wp_enqueue_style( 'glottybot-settings' , plugins_url( '/css/glottybot-settings.css' , dirname(__FILE__) ));

		wp_enqueue_script( 'glottybot-settings' , plugins_url( 'js/glottybot-settings.js' , dirname(__FILE__) ) , array('jquery-ui-sortable') );
		wp_localize_script('glottybot-settings' , 'glottybot_settings' , array(
			'l10n' => array(
				'no_results_text' => __( 'No result' , 'wp-glottybot' ),
			)
		) );
		
		wp_enqueue_style( 'chosen' , plugins_url( '/css/chosen/chosen.min.css' , dirname(__FILE__) ));
		wp_enqueue_script( 'chosen-jquery' , plugins_url( '/js/chosen/chosen.jquery.min.js' , dirname(__FILE__) ));
		
	}
	


	/**
	 * Setup options page.
	 */
	function register_settings() {
		$settings_section = 'glottybot_settings';
		// more settings go here ...
		register_setting( $this->optionset , 'glottybot_translations' , array( &$this , 'sanitize_setting_translations' ) );
	//	register_setting( $this->optionset , 'glottybot_hide_untranslated' , 'intval' );

		add_settings_section( $settings_section, __( 'Multilingual',  'wp-glottybot' ), array( &$this, 'multilingual_description' ), $this->optionset );
		// ... and here
		add_settings_field(
			'glottybot_translations',
			__( 'Translations',  'wp-glottybot' ),
			array( $this, 'translations_ui' ),
			$this->optionset,
			$settings_section
		);
		/* // nope.
		add_settings_field(
			'glottybot_hide_untranslated',
			__( 'Hide untranslated',  'wp-glottybot' ),
			array( $this, 'input_checkbox' ),
			$this->optionset,
			$settings_section,
			array( 
				'label' => __('Hide untranslated contents','wp-glottybot'),
				'description' => __( 'If checked only posts and pages in the current language will show up on your website.' , 'wp-glottybot' ),
				'option_name' => 'glottybot_hide_untranslated',
			)
		);
		*/
	}

	/**
	 * Print some documentation for the optionset
	 */
	public function multilingual_description() {
		?>
		<div class="inside">
			<p><?php _e( 'Foo bar baz quux.' , 'wp-glottybot' ); ?></p>
		</div>
		<?php
	}
	
	public function input_checkbox( $args ) {
		$args = wp_parse_args( $args , array( 
				'label' => false,
				'description' => false,
				'option_name' => false,
		));
		extract($args);
		$option_value = get_option( $option_name );
		?><label for="input-<?php echo $option_name ?>"><?php
			?><input <?php checked( $option_value , true , true ); ?> id="input-<?php echo $option_name ?>" type="checkbox" name="<?php echo $option_name ?>" value="1"><?php
			echo $label;
		?></label><?php
		if ( $description ) {
			?><p class="description"><?php
				echo $description;
			?></p><?php
		}
	}
	
	/**
	 * UI for additional language selection
	 */
	public function translations_ui() {
		
		$setting_name = 'glottybot_translations';
		$translations = $this->sanitize_setting_translations( (array) get_option($setting_name) );
		$default_locale = get_option('glottybot_default_locale');
		$system_language = get_option( 'WPLANG' );
		if ( ! $system_language )
			$system_language = 'en_US';
		
		?><div class="glottybot-add-translation"><?php
			?><label><?php _e( 'Add translation:' , 'wp-glottybot' ); ?></label> <?php
		echo GlottyBotTemplate::glottybot_select_locale( array(
			'name'			=> 'glottybot-locale',
			'id'			=> 'add_language',
			'selected'		=> '',
		) );
		?><button id="add_language_button" disabled class="button secondary"><?php 
			_e('+');
		?></button><?php

		?></div><?php
		
		$template = '<tr id="translation-item-%locale%" class="translation-item ui-sortable-handle">';
		$template .= 	'<td>';
		$template .= 		'<span class="i18n-item" data-language="%language_code%" %country_attr%></span>';
		$template .= 	'</td>';
		$template .= 	'<td>';
		$template .= 		'<span class="language-name"><strong>%language_name%</strong> %country_name%</span>';
		$template .= 	'</td>';
		$template .= 	'<td>';
		$template .= 		'<span>%locale%</span>';
		$template .= 	'</td>';
		$template .= 	'<td>';
		$template .= 		'<input type="text" class="glottybot-slug" name="'.$setting_name.'[%locale%][slug]" value="%slug%" />';
		$template .= 		'<span class="glottybot-slug-placeholder">'.__('(Default Language)','wp-glottybot').'</span>';
		$template .= 	'</td>';
		$template .= 	'<td>';
		$template .= 		'<input type="text" class="glottybot-locale-name" name="'.$setting_name.'[%locale%][name]" value="%name%" />';
		$template .= 	'</td>';
		$template .= 	'<td>';
		$template .= 		'<button class="remove button secondary">' . __('—') . '</button>';
		$template .= 	'</td>';
		$template .= '</tr>';
		
		?><div id="translations"><?php
			?><table id="glottybot-translations" class="wp-list-table widefat"><?php
				
				?><thead><?php
					?><tr><?php
						?><th class="icon manage-column column-title"><?php _e('','wp-glottybot') ?></th><?php
						?><th class="manage-column"><?php _e('Language','wp-glottybot') ?></th><?php
						?><th class="manage-column"><?php _e('Code','wp-glottybot') ?></th><?php
						?><th class="manage-column"><?php _e('Permalink slug','wp-glottybot') ?></th><?php
						?><th class="manage-column"><?php _e('Locale name','wp-glottybot') ?></th><?php
						?><th class="manage-column"><?php _e('Remove','wp-glottybot') ?></th><?php
					?></tr><?php
				?></thead><?php
				?><tbody class="ui-sortable"><?php
				
			foreach ( $translations as $code => $trans ) {
				$locale = GlottyBotLocales::get_locale_object($code);
				$lang_country = GlottyBotLocales::get_language_country($code);
				$language = array(
					'%locale%' 			=> $code,
					'%language_code%' 	=> $locale->language,
					'%country_code%' 	=> $locale->country,
					'%country_attr%'	=> $locale->country ? 'data-country="'.$locale->country.'"' : '',
					'%language_name%'	=> $lang_country->language->name,
					'%country_name%' 	=> $lang_country->country ? '('.$lang_country->country->name.')':'',
					'%slug%' 			=> $trans['slug'],
					'%name%'			=> $trans['name'] ? $trans['name'] : $lang_country->language->name . ($lang_country->country ? ' ('.$lang_country->country->name.')':''),
					'%checked%'			=> checked($default_locale,$code,false),
				);
				echo strtr( $template , $language );
			}
				?></tbody><?php
			?></table><?php
		?></div><?php
		?><script type="text/template" id="translation-item-template"><?php
		echo $template;
		?></script><?php
	}
	

	/**
	 * Sanitize Additioanla languages
	 *
	 * @param $value array containing additional languages
	 * @return string sanitized value
	 */
	function sanitize_setting_translations( $value ) {
		$value = (array) $value;
		$value = array_filter($value);
		foreach ( $value as $locale => $translation ) {
			$value[$locale] = wp_parse_args( (array) $translation , array(
				'slug' => $locale,
				'enabled' => true,
			));
			$value[$locale]['slug'] = sanitize_title($value[$locale]['slug']);
			$value[$locale]['name'] = esc_attr($value[$locale]['name']);
		}
		return $value;
	}
	
}

endif;