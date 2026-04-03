<?php
/**
 * Plugin Name: CCLEE Toolkit
 * Plugin URI: https://github.com/cclee-hub/cclee-toolkit
 * Description: B端企业官网增强工具包：AI内容辅助、SEO优化、案例展示CPT。
 * Version: 1.0.0
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: CCLEE
 * Author URI: https://github.com/cclee-hub
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cclee-toolkit
 *
 * @package CCLEE_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CCLEE_TOOLKIT_VERSION', '1.0.0' );
define( 'CCLEE_TOOLKIT_PATH', plugin_dir_path( __FILE__ ) );
define( 'CCLEE_TOOLKIT_URL', plugin_dir_url( __FILE__ ) );

// 加载管理后台
require_once CCLEE_TOOLKIT_PATH . 'admin/settings.php';

// 条件加载模块
add_action( 'plugins_loaded', function() {
	// AI Assistant
	if ( get_option( 'cclee_toolkit_ai_enabled', false ) ) {
		require_once CCLEE_TOOLKIT_PATH . 'modules/ai/ai.php';
	}

	// SEO Enhancer
	if ( get_option( 'cclee_toolkit_seo_enabled', true ) ) {
		require_once CCLEE_TOOLKIT_PATH . 'modules/seo/seo.php';
	}

	// Case Study CPT
	if ( get_option( 'cclee_toolkit_case_study_enabled', true ) ) {
		require_once CCLEE_TOOLKIT_PATH . 'modules/case-study/case-study.php';
	}
} );
