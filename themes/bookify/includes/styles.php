<?php

declare( strict_types=1 );

namespace Blockify\Theme;

use const GLOB_ONLYDIR;
use function add_action;
use function add_editor_style;
use function add_filter;
use function apply_filters;
use function array_flip;
use function array_merge;
use function basename;
use function class_exists;
use function dirname;
use function file_exists;
use function file_get_contents;
use function filemtime;
use function glob;
use function is_a;
use function is_admin;
use function is_admin_bar_showing;
use function is_array;
use function str_contains;
use function str_replace;
use function trim;
use function wp_add_inline_style;
use function wp_dequeue_style;
use function wp_enqueue_style;
use function wp_get_global_settings;
use function wp_get_global_styles;
use function wp_register_style;

add_action( 'enqueue_block_editor_assets', NS . 'enqueue_styles', 8 );
add_action( 'wp_enqueue_scripts', NS . 'enqueue_styles', 0 );
/**
 * Enqueues styles.
 *
 * @since 0.4.0
 *
 * @return void
 */
function enqueue_styles(): void {
	wp_dequeue_style( 'wp-block-library-theme' );
	wp_register_style( SLUG, '' );
	wp_enqueue_style( SLUG );
	wp_add_inline_style(
		SLUG,
		apply_filters(
			'blockify_inline_css',
			'',
			get_page_content()
		)
	);

	if ( ! is_admin() ) {
		array_unshift( wp_styles()->queue, SLUG );
	}
}

add_filter( 'blockify_inline_css', NS . 'add_dynamic_custom_properties', 10, 2 );
/**
 * Add dynamic custom properties.
 *
 * @since 0.9.19
 *
 * @param string $css Inline styles.
 *
 * @return string
 */
function add_dynamic_custom_properties( string $css ): string {
	$element        = is_admin() ? 'body,.editor-styles-wrapper' : 'body';
	$settings       = wp_get_global_settings();
	$global_styles  = wp_get_global_styles();
	$custom         = $settings['custom'] ?? [];
	$border_width   = $custom['border']['width'] ?? '1px';
	$border_style   = $custom['border']['style'] ?? 'solid';
	$border_color   = $custom['border']['color'] ?? '#ddd';
	$bodyBackground = $global_styles['color']['background'] ?? null;
	$body_color     = $global_styles['color']['text'] ?? null;
	$box_shadow     = $custom['boxShadow'] ?? [];

	// Button.
	$button_block         = $global_styles['blocks']['core/button'] ?? [];
	$button_element       = $global_styles['elements']['button'] ?? [];
	$button_text          = $button_element['color']['text'] ?? $button_block['color']['text'] ?? null;
	$button_background    = $button_element['color']['background'] ?? $button_block['color']['background'] ?? null;
	$button_border_radius = $button_element['border']['radius'] ?? $button_block['border']['radius'] ?? null;
	$button_border_width  = $button_element['border']['width'] ?? $button_block['border']['width'] ?? null;
	$button_font_size     = $button_element['typography']['fontSize'] ?? $button_block['typography']['fontSize'] ?? null;
	$button_font_weight   = $button_element['typography']['fontWeight'] ?? $button_block['typography']['fontWeight'] ?? null;
	$button_line_height   = $button_element['typography']['lineHeight'] ?? $button_block['typography']['lineHeight'] ?? null;
	$button_padding       = $button_element['spacing']['padding'] ?? $button_block['spacing']['padding'] ?? null;

	// Also used by b, strong elements and legend element.
	$heading_font_weight = $global_styles['elements']['heading']['typography']['fontWeight'] ?? null;
	$heading_font_family = $global_styles['elements']['heading']['typography']['fontFamily'] ?? null;

	$all = [
		'--scrollbar-width'                    => '15px',
		'--wp--custom--border'                 => "$border_width $border_style $border_color",
		'--wp--custom--body--background'       => $bodyBackground,
		'--wp--custom--body--color'            => $body_color,
		'--wp--custom--heading--font-weight'   => $heading_font_weight,
		'--wp--custom--heading--font-family'   => $heading_font_family,

		// Used by .button.
		'--wp--custom--button--background'     => $button_background,
		'--wp--custom--button--color'          => $button_text,
		'--wp--custom--button--padding-top'    => $button_padding['top'] ?? null,
		'--wp--custom--button--padding-right'  => $button_padding['right'] ?? null,
		'--wp--custom--button--padding-bottom' => $button_padding['bottom'] ?? null,
		'--wp--custom--button--padding-left'   => $button_padding['left'] ?? null,
		'--wp--custom--button--border-radius'  => $button_border_radius,
		'--wp--custom--button--border-width'   => $button_border_width,
		'--wp--custom--button--font-size'      => $button_font_size,
		'--wp--custom--button--font-weight'    => $button_font_weight,
		'--wp--custom--button--line-height'    => $button_line_height,
	];

	if ( $box_shadow ) {
		if ( is_array( $box_shadow ) ) {
			$inset      = $box_shadow['inset'] ?? ' ';
			$x          = $box_shadow['x'] ?? null;
			$y          = $box_shadow['y'] ?? null;
			$blur       = $box_shadow['blur'] ?? null;
			$spread     = $box_shadow['spread'] ?? null;
			$color      = $box_shadow['color'] ?? null;
			$box_shadow = "$inset $x $y $blur $spread $color";
		}

		$all = array_merge(
			$all,
			[
				'--wp--custom--box-shadow' => $box_shadow,
			]
		);
	}

	return $css . $element . '{' . css_array_to_string( $all ) . '}';
}

add_filter( 'enqueue_block_editor_assets', NS . 'add_block_styles' );
add_filter( 'wp_enqueue_scripts', NS . 'add_block_styles' );
/**
 * Adds conditional block styles.
 *
 * @since 0.9.19
 *
 * @return void
 */
function add_block_styles(): void {
	global $wp_styles;

	if ( ! is_a( $wp_styles, 'WP_Styles' ) ) {
		return;
	}

	$handles = array_flip( $wp_styles->queue );

	foreach ( $wp_styles->registered as $handle => $style ) {
		if ( ! isset( $handles[ $handle ] ) ) {
			continue;
		}

		$slug = str_replace( 'wp-block-', '', $handle );
		$file = DIR . 'assets/css/blocks/' . $slug . '.css';

		if ( ! file_exists( $file ) ) {
			continue;
		}

		if ( ! is_admin() ) {
			wp_add_inline_style( $handle, file_get_contents( $file ) );
		}
	}
}

/**
 * Get all stylesheets to load conditionally.
 *
 * @since 0.9.10
 *
 * @return array
 */
function get_conditional_stylesheets(): array {
	return [
		...( is_admin() ? glob( DIR . 'assets/css/blocks/*.css' ) : [] ),
		...glob( DIR . 'assets/css/elements/*.css' ),
		...glob( DIR . 'assets/css/components/*.css' ),
		...glob( DIR . 'assets/css/formats/*.css' ),
		...glob( DIR . 'assets/css/extensions/*.css' ),
		...glob( DIR . 'assets/css/block-styles/*.css' ),
		...glob( DIR . 'assets/css/utility/*.css' ),
		...glob( DIR . 'assets/css/plugins/*.css' ),
	];
}

/**
 * Adds split styles.
 *
 * @since 0.0.27
 *
 * @param array  $stylesheets Stylesheets to load.
 * @param string $content     Page content.
 *
 * @return array
 */
function get_stylesheet_conditions( array $stylesheets, string $content ): array {
	$conditions = [];

	// Load all block CSS in admin.
	foreach ( $stylesheets as $stylesheet ) {
		$dir  = basename( dirname( $stylesheet ) );
		$file = basename( $stylesheet, '.css' );

		if ( $dir === 'blocks' ) {
			$conditions['blocks'][ $file ] = true;
		}
	}

	$conditions['block-styles'] = [
		'button-outline'   => str_contains( $content, 'is-style-outline' ),
		'button-secondary' => str_contains( $content, 'is-style-secondary' ),
		'checklist-circle' => str_contains( $content, 'is-style-checklist-circle' ),
		'checklist'        => str_contains( $content, 'is-style-checklist' ),
		'curved-text'      => str_contains( $content, 'is-style-curved-text' ),
		'divider-angle'    => str_contains( $content, 'is-style-angle' ),
		'divider-curve'    => str_contains( $content, 'is-style-curve' ),
		'divider-fade'     => str_contains( $content, 'is-style-fade' ),
		'divider-round'    => str_contains( $content, 'is-style-round' ),
		'divider-wave'     => str_contains( $content, 'is-style-wave' ),
		'mega-menu'        => str_contains( $content, 'is-style-mega-menu' ),
		'notice'           => str_contains( $content, 'is-style-notice' ),
		'numbered-list'    => str_contains( $content, 'is-style-numbered' ),
		'search-toggle'    => str_contains( $content, 'is-style-toggle' ),
		'square-list'      => str_contains( $content, 'is-style-square' ),
		'sub-heading'      => str_contains( $content, 'is-style-sub-heading' ),
		'surface'          => str_contains( $content, 'is-style-surface' ),
	];

	$conditions['elements'] = [
		'all'        => true,
		'big'        => str_contains( $content, '<big' ),
		'blockquote' => str_contains( $content, '<blockquote' ),
		'body'       => true,
		'button'     => str_contains_any(
			$content,
			[
				'<button',
				'type="button"',
				'type="submit"',
				'type="reset"',
				'nf-form',
			]
		),
		'cite'       => str_contains( $content, '<cite' ),
		'code'       => str_contains( $content, '<code' ),
		'hr'         => str_contains( $content, '<hr' ),
		'form'       => str_contains_any(
			$content,
			[
				'<fieldset',
				'<form',
				'nf-form',
			]
		),
		'html'       => true,
		'link'       => str_contains( $content, '<a' ),
		'list'       => str_contains( $content, '<list' ),
		'mark'       => str_contains( $content, '<mark' ),
		'pre'        => str_contains( $content, '<pre' ),
		'small'      => str_contains( $content, '<small' ),
		'strong'     => str_contains( $content, '<strong' ),
		'sub'        => str_contains( $content, '<sub' ),
		'sup'        => str_contains( $content, '<sup' ),
		'svg'        => str_contains( $content, '<svg' ),
		'table'      => str_contains( $content, '<table' ),
	];

	$conditions['components'] = [
		'admin-bar'          => is_admin_bar_showing(),
		'border'             => str_contains( $content, 'border-width:' ),
		'drop-cap'           => str_contains( $content, 'has-drop-cap' ),
		'placeholder-image'  => str_contains( $content, 'is-placeholder' ),
		'screen-reader-text' => true,
		'site-blocks'        => true,
	];

	$conditions['extensions'] = [
		'animation'  => str_contains_any( $content, [ 'has-animation', 'will-animate' ] ),
		'accordion'  => str_contains( $content, 'is-style-accordion' ),
		'box-shadow' => str_contains( $content, 'has-box-shadow' ),
		'counter'    => str_contains( $content, 'is-style-counter' ),
		'dark-mode'  => str_contains( $content, 'toggle-switch' ),
		'filter'     => str_contains( $content, 'has-filter' ),
		'icon'       => str_contains( $content, 'is-style-icon' ),
		'marquee'    => str_contains( $content, 'is-marquee' ),
		'transform'  => str_contains( $content, 'has-transform' ),
	];

	$conditions['formats'] = [
		'arrow'      => str_contains( $content, 'is-underline-arrow' ),
		'brush'      => str_contains( $content, 'is-underline-brush' ),
		'circle'     => str_contains( $content, 'is-underline-circle' ),
		'gradient'   => str_contains( $content, 'has-text-gradient' ),
		'highlight'  => str_contains( $content, 'has-inline-color' ),
		'underline'  => str_contains( $content, 'has-text-underline' ),
		'font-size'  => str_contains( $content, 'has-custom-font-size' ),
		'inline-svg' => str_contains( $content, 'inline-svg' ),
		'outline'    => str_contains( $content, 'has-text-outline' ),
	];

	$conditions['utility'] = [
		'button-width' => str_contains( $content, 'wp-block-button__width-' ),
		'margin'       => str_contains( $content, ' margin-auto' ),
	];

	$conditions['plugins'] = [
		'ninja-forms'                    => str_contains( $content, 'nf-form' ),
		'syntax-highlighting-code-block' => defined( 'Syntax_Highlighting_Code_Block\\PLUGIN_VERSION' ),
		'edd'                            => class_exists( 'EDD_Requirements_Check' ),
		'gravity-forms'                  => class_exists( 'GFForms' ),
		'woocommerce'                    => class_exists( 'WooCommerce' ),
	];

	return $conditions;
}

add_filter( 'blockify_inline_css', NS . 'add_conditional_stylesheets', 10, 2 );
/**
 * Adds conditional stylesheets inline.
 *
 * @since 0.0.27
 *
 * @param string $css     The CSS to add to.
 * @param string $content Block content.
 *
 * @return string
 */
function add_conditional_stylesheets( string $css, string $content ): string {
	$stylesheets = get_conditional_stylesheets();
	$conditions  = get_stylesheet_conditions( $stylesheets, $content );

	foreach ( $stylesheets as $stylesheet ) {
		$dir       = basename( dirname( $stylesheet ) );
		$condition = $conditions[ $dir ][ basename( $stylesheet, '.css' ) ];

		if ( $condition || $content === '' ) {
			$css .= trim( file_get_contents( $stylesheet ) );
		}

		if ( is_admin() ) {
			add_editor_style( 'assets/css/' . $dir . DS . basename( $stylesheet ) );
		}
	}

	return $css;
}

add_action( 'admin_init', NS . 'add_editor_stylesheets' );
/**
 * Adds editor only styles.
 *
 * @since 0.9.10
 *
 * @return void
 */
function add_editor_stylesheets() {
	$dirs = glob( DIR . 'assets/css/*', GLOB_ONLYDIR );

	foreach ( $dirs as $dir ) {
		$files = glob( $dir . '/*.css' );

		foreach ( $files as $file ) {
			$stylesheet = 'assets/css/' . basename( $dir ) . DS . basename( $file );

			add_editor_style( $stylesheet );
		}
	}
}

add_action( 'admin_enqueue_scripts', NS . 'enqueue_editor_only_styles', 9 );
/**
 * Enqueues editor assets.
 *
 * @since 0.3.3
 *
 * @return void
 */
function enqueue_editor_only_styles(): void {
	wp_dequeue_style( 'wp-block-library-theme' );

	wp_register_style(
		'blockify-editor',
		get_url() . 'assets/css/editor.css',
		[],
		filemtime( DIR . 'assets/css/editor.css' )
	);

	wp_enqueue_style( 'blockify-editor' );
}

add_filter( 'wp_theme_json_data_theme', NS . 'fix_editor_layout_sizes' );
/**
 * Changes layout size unit from vw to % in editor.
 *
 * @todo  Move layout settings to separate file.
 *
 * @since 0.4.2
 *
 * @param mixed $theme_json WP_Theme_JSON_Data | WP_Theme_JSON_Data_Gutenberg.
 *
 * @return mixed
 */
function fix_editor_layout_sizes( $theme_json ) {
	$default      = $theme_json->get_data();
	$new          = [];
	$content_size = $default['settings']['layout']['contentSize'] ?? 'min(calc(100vw - 3rem), 800px)';
	$wide_size    = $default['settings']['layout']['wideSize'] ?? 'min(calc(100vw - 3rem), 1200px)';

	if ( is_admin() ) {
		$content_size = str_replace( 'vw', '%', $content_size );
		$wide_size    = str_replace( 'vw', '%', $wide_size );
	}

	$new['settings']['layout']['contentSize'] = $content_size;
	$new['settings']['layout']['wideSize']    = $wide_size;

	$theme_json->update_with( array_merge( $default, $new ) );

	return $theme_json;
}
