<?php
/**
 * CCLEE Toolkit 设置页面
 *
 * @package CCLEE_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 添加设置菜单
 */
add_action( 'admin_menu', function() {
	add_options_page(
		'CCLEE Toolkit',
		'CCLEE Toolkit',
		'manage_options',
		'cclee-toolkit',
		'cclee_toolkit_settings_page'
	);
} );

/**
 * 注册设置
 */
add_action( 'admin_init', function() {
	// 注册设置
	register_setting( 'cclee_toolkit', 'cclee_toolkit_ai_enabled' );
	register_setting( 'cclee_toolkit', 'cclee_toolkit_ai_api_key' );
	register_setting( 'cclee_toolkit', 'cclee_toolkit_seo_enabled' );
	register_setting( 'cclee_toolkit', 'cclee_toolkit_case_study_enabled' );

	// 设置区域
	add_settings_section(
		'cclee_toolkit_main',
		'Module Settings',
		null,
		'cclee-toolkit'
	);

	// AI 模块开关
	add_settings_field(
		'cclee_toolkit_ai_enabled',
		'AI Assistant',
		function() {
			$value = get_option( 'cclee_toolkit_ai_enabled', false );
			echo '<label><input type="checkbox" name="cclee_toolkit_ai_enabled" value="1" ' . checked( $value, true, false ) . '> Enable AI content assistant in editor</label>';
		},
		'cclee-toolkit',
		'cclee_toolkit_main'
	);

	// AI API Key
	add_settings_field(
		'cclee_toolkit_ai_api_key',
		'AI API Key',
		function() {
			$value = get_option( 'cclee_toolkit_ai_api_key', '' );
			echo '<input type="password" name="cclee_toolkit_ai_api_key" value="' . esc_attr( $value ) . '" class="regular-text">';
			echo '<p class="description">OpenAI API key (or compatible)</p>';
		},
		'cclee-toolkit',
		'cclee_toolkit_main'
	);

	// SEO 模块开关
	add_settings_field(
		'cclee_toolkit_seo_enabled',
		'SEO Enhancer',
		function() {
			$value = get_option( 'cclee_toolkit_seo_enabled', true );
			echo '<label><input type="checkbox" name="cclee_toolkit_seo_enabled" value="1" ' . checked( $value, true, false ) . '> Enable OG tags and JSON-LD Schema</label>';
		},
		'cclee-toolkit',
		'cclee_toolkit_main'
	);

	// Case Study 模块开关
	add_settings_field(
		'cclee_toolkit_case_study_enabled',
		'Case Study CPT',
		function() {
			$value = get_option( 'cclee_toolkit_case_study_enabled', true );
			echo '<label><input type="checkbox" name="cclee_toolkit_case_study_enabled" value="1" ' . checked( $value, true, false ) . '> Enable Case Study custom post type</label>';
		},
		'cclee-toolkit',
		'cclee_toolkit_main'
	);
} );

/**
 * 设置页面回调
 */
function cclee_toolkit_settings_page() {
	?>
	<div class="wrap">
		<h1>CCLEE Toolkit</h1>
		<p class="description">B端企业官网增强工具包，每个模块可独立启用/禁用。</p>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'cclee_toolkit' );
			do_settings_sections( 'cclee-toolkit' );
			submit_button();
			?>
		</form>
	</div>
	<?php
}
