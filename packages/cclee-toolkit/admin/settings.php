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
 * 返回可用 tab 列表
 *
 * @return array<string, string> slug => label
 */
function cclee_toolkit_get_tabs(): array {
	return array(
		'general' => __( 'General', 'cclee-toolkit' ),
		'seo'    => __( 'SEO', 'cclee-toolkit' ),
		'woo'    => __( 'WooCommerce', 'cclee-toolkit' ),
	);
}

/**
 * 获取当前 tab，默认 general
 *
 * @return string
 */
function cclee_toolkit_get_current_tab(): string {
	// phpcs:ignore WordPress.Security.NonceVerification
	return isset( $_GET['tab'] ) && array_key_exists( sanitize_key( wp_unslash( $_GET['tab'] ) ), cclee_toolkit_get_tabs() )
		? sanitize_key( wp_unslash( $_GET['tab'] ) )
		: 'general';
}

/**
 * 注册设置（所有 tab 共用同一 option group）
 */
add_action( 'admin_init', function() {
	register_setting( 'cclee_toolkit', 'cclee_toolkit_ai_enabled' );
	register_setting( 'cclee_toolkit', 'cclee_toolkit_ai_api_key' );
	register_setting( 'cclee_toolkit', 'cclee_toolkit_ai_provider' );
	register_setting( 'cclee_toolkit', 'cclee_toolkit_ai_base_url' );
	register_setting( 'cclee_toolkit', 'cclee_toolkit_ai_model' );
	register_setting( 'cclee_toolkit', 'cclee_toolkit_seo_enabled' );
	register_setting( 'cclee_toolkit', 'cclee_toolkit_seo_og_enabled' );
	register_setting( 'cclee_toolkit', 'cclee_toolkit_seo_jsonld_enabled' );
	register_setting( 'cclee_toolkit', 'cclee_toolkit_case_study_enabled' );
	register_setting( 'cclee_toolkit', 'cclee_toolkit_woo_schema_enabled' );
} );

/**
 * 添加 CCLEE Toolkit 顶级菜单
 */
add_action( 'admin_menu', function() {
	add_menu_page(
		__( 'CCLEE Toolkit', 'cclee-toolkit' ),
		__( 'CCLEE Toolkit', 'cclee-toolkit' ),
		'manage_options',
		'cclee-toolkit',
		'cclee_toolkit_render_page',
		'dashicons-admin-plugins',
		80
	);
} );

/**
 * 渲染 Tab 导航
 *
 * @param string $current 当前激活的 tab slug
 */
function cclee_toolkit_render_nav( string $current ): void {
	$tabs = cclee_toolkit_get_tabs();
	echo '<nav class="nav-tab-wrapper" style="margin-bottom: 1em;">';
	foreach ( $tabs as $tab => $label ) {
		$url  = admin_url( 'admin.php?page=cclee-toolkit&tab=' . $tab );
		$cls  = $tab === $current ? 'nav-tab nav-tab-active' : 'nav-tab';
		printf(
			'<a href="%s" class="%s">%s</a>',
			esc_url( $url ),
			esc_attr( $cls ),
			esc_html( $label )
		);
	}
	echo '</nav>';
}

/**
 * 渲染设置页
 */
function cclee_toolkit_render_page(): void {
	$tab = cclee_toolkit_get_current_tab();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'CCLEE Toolkit', 'cclee-toolkit' ); ?></h1>
		<?php cclee_toolkit_render_nav( $tab ); ?>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'cclee_toolkit' );
			if ( 'general' === $tab ) {
				cclee_toolkit_render_general();
			} elseif ( 'seo' === $tab ) {
				cclee_toolkit_render_seo();
			} elseif ( 'woo' === $tab ) {
				cclee_toolkit_render_woo();
			}
			submit_button();
			?>
		</form>
	</div>
	<?php
}

/**
 * =====================
 * General Tab
 * =====================
 */
function cclee_toolkit_render_general(): void {
	?>
	<h2><?php esc_html_e( 'General Settings', 'cclee-toolkit' ); ?></h2>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'AI Assistant', 'cclee-toolkit' ); ?></th>
			<td>
				<fieldset>
					<label>
						<input type="checkbox" name="cclee_toolkit_ai_enabled" value="1"
							<?php checked( get_option( 'cclee_toolkit_ai_enabled', false ), true ); ?>>
						<?php esc_html_e( 'Enable AI content assistant in editor', 'cclee-toolkit' ); ?>
					</label>
				</fieldset>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'AI API Key', 'cclee-toolkit' ); ?></th>
			<td>
				<input type="password" name="cclee_toolkit_ai_api_key"
					value="<?php echo esc_attr( get_option( 'cclee_toolkit_ai_api_key', '' ) ); ?>"
					class="regular-text">
				<p class="description"><?php esc_html_e( 'API Key for the selected provider below.', 'cclee-toolkit' ); ?></p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'AI Provider', 'cclee-toolkit' ); ?></th>
			<td>
				<select name="cclee_toolkit_ai_provider" id="cclee_toolkit_ai_provider">
					<?php
					$providers = array(
						'openai'    => 'OpenAI',
						'deepseek'  => 'DeepSeek',
						'anthropic' => 'Anthropic (Claude)',
						'custom'    => 'Custom (OpenAI-compatible)',
					);
					$current = get_option( 'cclee_toolkit_ai_provider', 'openai' );
					foreach ( $providers as $key => $label ) {
						printf(
							'<option value="%s"%s>%s</option>',
							esc_attr( $key ),
							selected( $current, $key, false ),
							esc_html( $label )
						);
					}
					?>
				</select>
				<p class="description"><?php esc_html_e( 'Select AI service provider for content generation.', 'cclee-toolkit' ); ?></p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'API Base URL', 'cclee-toolkit' ); ?></th>
			<td>
				<input type="url" name="cclee_toolkit_ai_base_url"
					value="<?php echo esc_attr( get_option( 'cclee_toolkit_ai_base_url', '' ) ); ?>"
					class="regular-text" placeholder="https://api.example.com/v1">
				<p class="description">
					<?php esc_html_e( 'Only needed for Custom provider. Must end with', 'cclee-toolkit' ); ?>
					<code>/v1</code>.
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'AI Model', 'cclee-toolkit' ); ?></th>
			<td>
				<input type="text" name="cclee_toolkit_ai_model"
					value="<?php echo esc_attr( get_option( 'cclee_toolkit_ai_model', '' ) ); ?>"
					class="regular-text" placeholder="e.g. gpt-4o-mini">
				<p class="description">
					<?php esc_html_e( 'Leave empty for provider default. Examples: gpt-4o-mini, deepseek-chat, claude-haiku-4-5-20251001', 'cclee-toolkit' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Case Study CPT', 'cclee-toolkit' ); ?></th>
			<td>
				<fieldset>
					<label>
						<input type="checkbox" name="cclee_toolkit_case_study_enabled" value="1"
							<?php checked( get_option( 'cclee_toolkit_case_study_enabled', true ), true ); ?>>
						<?php esc_html_e( 'Enable Case Study custom post type and related blocks', 'cclee-toolkit' ); ?>
					</label>
				</fieldset>
			</td>
		</tr>
	</table>
	<?php
}

/**
 * =====================
 * SEO Tab
 * =====================
 */
function cclee_toolkit_render_seo(): void {
	?>
	<h2><?php esc_html_e( 'SEO Settings', 'cclee-toolkit' ); ?></h2>
	<p class="description" style="margin-bottom:1em;">
		<?php esc_html_e( 'Outputs Open Graph tags, Twitter Card meta, and JSON-LD Schema markup in frontend <code>&lt;head&gt;</code>.', 'cclee-toolkit' ); ?>
	</p>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Master Switch', 'cclee-toolkit' ); ?></th>
			<td>
				<fieldset>
					<label>
						<input type="checkbox" name="cclee_toolkit_seo_enabled" value="1"
							<?php checked( get_option( 'cclee_toolkit_seo_enabled', true ), true ); ?>>
						<?php esc_html_e( 'Enable SEO Enhancer module', 'cclee-toolkit' ); ?>
					</label>
				</fieldset>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Open Graph', 'cclee-toolkit' ); ?></th>
			<td>
				<fieldset>
					<label>
						<input type="checkbox" name="cclee_toolkit_seo_og_enabled" value="1"
							<?php checked( get_option( 'cclee_toolkit_seo_og_enabled', true ), true ); ?>>
						<?php esc_html_e( 'Output OG tags (og:title, og:description, og:image, og:url, og:type)', 'cclee-toolkit' ); ?>
					</label>
					<br>
					<label>
						<input type="checkbox" name="cclee_toolkit_seo_jsonld_enabled" value="1"
							<?php checked( get_option( 'cclee_toolkit_seo_jsonld_enabled', true ), true ); ?>>
						<?php esc_html_e( 'Output JSON-LD Schema (WebPage / Article structured data)', 'cclee-toolkit' ); ?>
					</label>
				</fieldset>
			</td>
		</tr>
	</table>
	<?php
}

/**
 * =====================
 * WooCommerce Tab
 * =====================
 */
function cclee_toolkit_render_woo(): void {
	?>
	<h2><?php esc_html_e( 'WooCommerce Settings', 'cclee-toolkit' ); ?></h2>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Product Schema', 'cclee-toolkit' ); ?></th>
			<td>
				<fieldset>
					<label>
						<input type="checkbox" name="cclee_toolkit_woo_schema_enabled" value="1"
							<?php checked( get_option( 'cclee_toolkit_woo_schema_enabled', true ), true ); ?>>
						<?php esc_html_e( 'Enable WooCommerce Product Schema (structured data for rich results)', 'cclee-toolkit' ); ?>
					</label>
				</fieldset>
				<p class="description"><?php esc_html_e( 'Outputs Product schema (name, image, SKU, GTIN, MPN, brand, price, availability, reviews) and BreadcrumbList on single product pages. Supports variable products.', 'cclee-toolkit' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}
