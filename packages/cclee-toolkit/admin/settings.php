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
		'seo'     => __( 'SEO', 'cclee-toolkit' ),
		'alt'     => __( 'Image Alt', 'cclee-toolkit' ),
		'woo'     => __( 'WooCommerce', 'cclee-toolkit' ),
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
	// Checkbox options — sanitize callback ensures unchecked = false in DB
	$checkboxes = [
		'cclee_toolkit_ai_enabled',
		'cclee_toolkit_seo_enabled',
		'cclee_toolkit_seo_og_enabled',
		'cclee_toolkit_seo_jsonld_enabled',
		'cclee_toolkit_seo_indexnow_enabled',
		'cclee_toolkit_seo_google_indexing_enabled',
		'cclee_toolkit_case_study_enabled',
		'cclee_toolkit_woo_schema_enabled',
		'cclee_toolkit_alt_auto_enabled',
		'cclee_toolkit_alt_batch_enabled',
		'cclee_toolkit_alt_force_overwrite',
		'cclee_toolkit_llms_enabled',
	];
	foreach ( $checkboxes as $option ) {
		register_setting( 'cclee_toolkit', $option, [
			'sanitize_callback' => function( $value ) {
				return $value ? true : false;
			},
		] );
	}

	// Text / select / textarea options
	register_setting( 'cclee_toolkit', 'cclee_toolkit_ai_api_key', [
		'sanitize_callback' => 'sanitize_text_field',
	] );
	register_setting( 'cclee_toolkit', 'cclee_toolkit_ai_provider', [
		'sanitize_callback' => 'sanitize_key',
	] );
	register_setting( 'cclee_toolkit', 'cclee_toolkit_ai_base_url', [
		'sanitize_callback' => 'esc_url_raw',
	] );
	register_setting( 'cclee_toolkit', 'cclee_toolkit_ai_model', [
		'sanitize_callback' => 'sanitize_text_field',
	] );
	register_setting( 'cclee_toolkit', 'cclee_toolkit_seo_verify_google', [
		'sanitize_callback' => 'sanitize_text_field',
	] );
	register_setting( 'cclee_toolkit', 'cclee_toolkit_seo_verify_bing', [
		'sanitize_callback' => 'sanitize_text_field',
	] );
	register_setting( 'cclee_toolkit', 'cclee_toolkit_seo_verify_yandex', [
		'sanitize_callback' => 'sanitize_text_field',
	] );
	register_setting( 'cclee_toolkit', 'cclee_toolkit_seo_indexnow_key', [
		'sanitize_callback' => 'sanitize_key',
	] );
	register_setting( 'cclee_toolkit', 'cclee_toolkit_seo_google_service_account', [
		'sanitize_callback' => 'sanitize_textarea_field',
	] );
	register_setting( 'cclee_toolkit', 'cclee_toolkit_llms_extra', [
		'sanitize_callback' => 'sanitize_textarea_field',
		'default' => '',
	] );
	register_setting( 'cclee_toolkit', 'cclee_toolkit_alt_max_tokens', [
		'sanitize_callback' => function( $value ) {
			$v = absint( $value );
			if ( $v < 50 )  $v = 50;
			if ( $v > 500 ) $v = 500;
			return $v;
		},
		'default' => 100,
	] );
	register_setting( 'cclee_toolkit', 'cclee_toolkit_alt_temperature', [
		'sanitize_callback' => function( $value ) {
			$v = floatval( $value );
			if ( $v < 0 ) $v = 0;
			if ( $v > 1 ) $v = 1;
			return $v;
		},
		'default' => 0.3,
	] );
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
 * Tab 与注册 option 的映射，用于跨 tab 保存时保留其他 tab 的值
 *
 * @return array<string, array{checkboxes: string[], fields: string[]}>
 */
function cclee_toolkit_get_tab_options(): array {
	return [
		'general' => [
			'checkboxes' => [ 'cclee_toolkit_ai_enabled', 'cclee_toolkit_case_study_enabled' ],
			'fields'     => [ 'cclee_toolkit_ai_api_key', 'cclee_toolkit_ai_provider', 'cclee_toolkit_ai_base_url', 'cclee_toolkit_ai_model' ],
		],
		'seo' => [
			'checkboxes' => [ 'cclee_toolkit_seo_enabled', 'cclee_toolkit_seo_og_enabled', 'cclee_toolkit_seo_jsonld_enabled', 'cclee_toolkit_seo_indexnow_enabled', 'cclee_toolkit_seo_google_indexing_enabled', 'cclee_toolkit_llms_enabled' ],
			'fields'     => [ 'cclee_toolkit_seo_verify_google', 'cclee_toolkit_seo_verify_bing', 'cclee_toolkit_seo_verify_yandex', 'cclee_toolkit_seo_indexnow_key', 'cclee_toolkit_seo_google_service_account', 'cclee_toolkit_llms_extra' ],
		],
		'alt' => [
			'checkboxes' => [ 'cclee_toolkit_alt_auto_enabled', 'cclee_toolkit_alt_batch_enabled', 'cclee_toolkit_alt_force_overwrite' ],
			'fields'     => [ 'cclee_toolkit_alt_max_tokens', 'cclee_toolkit_alt_temperature' ],
		],
		'woo' => [
			'checkboxes' => [ 'cclee_toolkit_woo_schema_enabled' ],
			'fields'     => [],
		],
	];
}

/**
 * 渲染设置页
 */
function cclee_toolkit_render_page(): void {
	$tab       = cclee_toolkit_get_current_tab();
	$all_tabs  = cclee_toolkit_get_tab_options();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'CCLEE Toolkit', 'cclee-toolkit' ); ?></h1>
		<?php cclee_toolkit_render_nav( $tab ); ?>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'cclee_toolkit' );

			// 为非当前 tab 的选项输出 hidden input，防止保存时被清空
			foreach ( $all_tabs as $t => $opts ) {
				if ( $t === $tab ) {
					continue;
				}
				foreach ( $opts['checkboxes'] as $opt ) {
					printf(
						'<input type="hidden" name="%s" value="%s">',
						esc_attr( $opt ),
						get_option( $opt, false ) ? '1' : '0'
					);
				}
				foreach ( $opts['fields'] as $opt ) {
					printf(
						'<input type="hidden" name="%s" value="%s">',
						esc_attr( $opt ),
						esc_attr( get_option( $opt, '' ) )
					);
				}
			}

			if ( 'general' === $tab ) {
				cclee_toolkit_render_general();
			} elseif ( 'seo' === $tab ) {
				cclee_toolkit_render_seo();
			} elseif ( 'alt' === $tab ) {
				cclee_toolkit_render_alt();
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
					<input type="hidden" name="cclee_toolkit_ai_enabled" value="0">
					<label>
						<input type="checkbox" id="cclee-ai-toggle" name="cclee_toolkit_ai_enabled" value="1"
							<?php checked( get_option( 'cclee_toolkit_ai_enabled', false ), true ); ?>>
						<?php esc_html_e( 'Enable AI content assistant in editor', 'cclee-toolkit' ); ?>
					</label>
				</fieldset>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'AI API Key', 'cclee-toolkit' ); ?></th>
			<td>
				<input type="password" name="cclee_toolkit_ai_api_key" id="cclee_toolkit_ai_api_key"
					value="<?php echo esc_attr( get_option( 'cclee_toolkit_ai_api_key', '' ) ); ?>"
					class="regular-text">
				<button type="button" class="button button-secondary" id="cclee-ai-test-btn">
					<?php esc_html_e( 'Test Connection', 'cclee-toolkit' ); ?>
				</button>
				<p id="cclee-ai-test-result" style="display:none; margin-top:6px;"></p>
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
					class="regular-text" placeholder="e.g. gemini-2.5-flash">
				<p class="description">
					<?php esc_html_e( 'Leave empty for provider default. Examples: gemini-2.5-flash, gemini-2.5-flash-lite, gpt-4o-mini, deepseek-chat', 'cclee-toolkit' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Case Study CPT', 'cclee-toolkit' ); ?></th>
			<td>
				<fieldset>
					<input type="hidden" name="cclee_toolkit_case_study_enabled" value="0">
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
 * Image Alt Tab
 * =====================
 */
function cclee_toolkit_render_alt(): void {
	?>
	<h2><?php esc_html_e( 'Image Alt Settings', 'cclee-toolkit' ); ?></h2>
	<p class="description" style="margin-bottom:1em;">
		<?php esc_html_e( 'AI parameters (API Key / Provider / Model) are configured in the General Tab.', 'cclee-toolkit' ); ?>
	</p>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><h3 style="margin:0;"><?php esc_html_e( 'Image Alt', 'cclee-toolkit' ); ?></h3></th>
			<td>
				<fieldset>
					<?php $ai_enabled = (bool) get_option( 'cclee_toolkit_ai_enabled', false ); ?>
					<input type="hidden" name="cclee_toolkit_alt_auto_enabled" value="0">
					<label>
						<input type="checkbox" id="cclee-alt-auto-toggle" name="cclee_toolkit_alt_auto_enabled" value="1"
							<?php checked( get_option( 'cclee_toolkit_alt_auto_enabled', false ), true ); ?>
							<?php disabled( ! $ai_enabled ); ?>>
						<?php esc_html_e( 'Auto-generate alt text on image upload using AI', 'cclee-toolkit' ); ?>
					</label>
					<p id="cclee-alt-auto-hint" class="description" style="color:#d63638; <?php echo $ai_enabled ? 'display:none;' : ''; ?>"><?php esc_html_e( 'Requires AI module enabled in General Tab.', 'cclee-toolkit' ); ?></p>
					<br><br>
					<input type="hidden" name="cclee_toolkit_alt_batch_enabled" value="0">
					<label>
						<input type="checkbox" name="cclee_toolkit_alt_batch_enabled" value="1"
							<?php checked( get_option( 'cclee_toolkit_alt_batch_enabled', true ), true ); ?>>
						<?php esc_html_e( 'Enable batch alt text processing', 'cclee-toolkit' ); ?>
					</label>
					<br><br>
					<input type="hidden" name="cclee_toolkit_alt_force_overwrite" value="0">
					<label>
						<input type="checkbox" name="cclee_toolkit_alt_force_overwrite" value="1"
							<?php checked( get_option( 'cclee_toolkit_alt_force_overwrite', false ), true ); ?>>
						<?php esc_html_e( 'Force overwrite existing alt text', 'cclee-toolkit' ); ?>
					</label>
					<br><br>
					<label>
						<?php esc_html_e( 'Max Tokens', 'cclee-toolkit' ); ?>&nbsp;
						<input type="number" name="cclee_toolkit_alt_max_tokens"
							value="<?php echo esc_attr( get_option( 'cclee_toolkit_alt_max_tokens', 100 ) ); ?>"
							min="50" max="500" step="1" class="small-text">
					</label>
					<p class="description"><?php esc_html_e( 'Tokens limit for AI response (50–500)', 'cclee-toolkit' ); ?></p>
					<br>
					<label>
						<?php esc_html_e( 'Temperature', 'cclee-toolkit' ); ?>&nbsp;
						<input type="number" name="cclee_toolkit_alt_temperature"
							value="<?php echo esc_attr( get_option( 'cclee_toolkit_alt_temperature', 0.3 ) ); ?>"
							min="0" max="1" step="0.1" class="small-text">
					</label>
					<p class="description"><?php esc_html_e( 'AI creativity level (0 = precise, 1 = creative)', 'cclee-toolkit' ); ?></p>
				</fieldset>
				<?php
				$batch_enabled  = (bool) get_option( 'cclee_toolkit_alt_batch_enabled', false );
				$force_overwrite = (bool) get_option( 'cclee_toolkit_alt_force_overwrite', false );
				if ( $force_overwrite ) {
					$img_count = function_exists( 'cclee_toolkit_count_images' )
						? cclee_toolkit_count_images() : 0;
				} else {
					$img_count = function_exists( 'cclee_toolkit_count_empty_alt_images' )
						? cclee_toolkit_count_empty_alt_images() : 0;
				}
				$show_batch  = $batch_enabled;
				?>
				<div id="cclee-alt-batch-section" style="margin-top:1em; padding:1em; background:#f6f7f7; border:1px solid #dcdcde; border-radius:4px; <?php echo $show_batch ? '' : 'display:none;'; ?>">
					<p style="margin:0 0 0.5em;">
						<strong><?php esc_html_e( 'Batch Processing', 'cclee-toolkit' ); ?></strong>
						&mdash;
						<?php
						/* translators: %d: number of images to process */
						printf( esc_html__( 'Images to process: %d', 'cclee-toolkit' ), (int) $img_count ); ?>
					</p>
					<p style="margin:0 0 0.5em;">
						<label><?php esc_html_e( 'Batch size', 'cclee-toolkit' ); ?>
							<input type="number" id="cclee-alt-batch-size" value="10" min="1" max="50" class="small-text">
						</label>
						<button type="button" class="button button-secondary" id="cclee-alt-batch-btn">
							<?php esc_html_e( 'Start Batch Processing', 'cclee-toolkit' ); ?>
						</button>
					</p>
					<p id="cclee-alt-batch-result" style="display:none; margin:0;"></p>
				</div>
				<!-- Modal overlay (hidden by default) -->
				<div id="cclee-alt-modal-overlay" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:160000;align-items:flex-start;justify-content:center;padding-top:60px;">
					<div id="cclee-alt-modal" style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;width:90%;max-width:700px;max-height:75vh;display:flex;flex-direction:column;box-shadow:0 5px 15px rgba(0,0,0,0.2);">
						<div style="padding:16px 20px;border-bottom:1px solid #dcdcde;display:flex;justify-content:space-between;align-items:center;">
							<h3 id="cclee-alt-modal-title" style="margin:0;font-size:14px;"></h3>
							<button type="button" id="cclee-alt-modal-close" class="button button-link" style="font-size:18px;line-height:1;padding:0 4px;">&times;</button>
						</div>
						<div id="cclee-alt-modal-body" style="overflow-y:auto;flex:1;padding:12px 20px;"></div>
						<div style="padding:12px 20px;border-top:1px solid #dcdcde;text-align:right;">
							<button type="button" id="cclee-alt-modal-done" class="button button-primary"><?php esc_html_e( 'Close', 'cclee-toolkit' ); ?></button>
						</div>
					</div>
				</div>
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
		<?php esc_html_e( 'Outputs Open Graph tags, Twitter Card meta, and JSON-LD Schema markup in frontend <code>&lt;head&gt;</code>. Structured data output, compatible with AI crawler indexing.', 'cclee-toolkit' ); ?>
	</p>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Master Switch', 'cclee-toolkit' ); ?></th>
			<td>
				<fieldset>
					<input type="hidden" name="cclee_toolkit_seo_enabled" value="0">
					<label>
						<input type="checkbox" name="cclee_toolkit_seo_enabled" value="1"
							<?php checked( get_option( 'cclee_toolkit_seo_enabled', true ), true ); ?>>
						<?php esc_html_e( 'Enable SEO Enhancer module', 'cclee-toolkit' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Controls all features on this page. When disabled, no meta tags, verification codes, or indexing requests are output.', 'cclee-toolkit' ); ?></p>
				</fieldset>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Site Verification', 'cclee-toolkit' ); ?></th>
			<td>
				<p>
					<input type="text" name="cclee_toolkit_seo_verify_google"
						value="<?php echo esc_attr( get_option( 'cclee_toolkit_seo_verify_google', '' ) ); ?>"
						class="regular-text" placeholder="e.g. abc123def456">
					<label><?php esc_html_e( 'Google Search Console', 'cclee-toolkit' ); ?></label>
				</p>
				<p>
					<input type="text" name="cclee_toolkit_seo_verify_bing"
						value="<?php echo esc_attr( get_option( 'cclee_toolkit_seo_verify_bing', '' ) ); ?>"
						class="regular-text" placeholder="e.g. ABCDEF1234567890...">
					<label><?php esc_html_e( 'Bing Webmaster Tools', 'cclee-toolkit' ); ?></label>
				</p>
				<p>
					<input type="text" name="cclee_toolkit_seo_verify_yandex"
						value="<?php echo esc_attr( get_option( 'cclee_toolkit_seo_verify_yandex', '' ) ); ?>"
						class="regular-text" placeholder="e.g. a1b2c3d4">
					<label><?php esc_html_e( 'Yandex Webmaster', 'cclee-toolkit' ); ?></label>
				</p>
				<p class="description"><?php esc_html_e( 'Paste verification codes from search engine webmaster tools. These meta tags are output site-wide when SEO module is enabled.', 'cclee-toolkit' ); ?></p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Analytics', 'cclee-toolkit' ); ?></th>
			<td>
				<p class="description">
					<?php
					printf(
						/* translators: %s: Site Kit plugin link */
						esc_html__( 'Website traffic, keyword rankings, and Core Web Vitals data are recommended to be collected using the official Google plugin %s.', 'cclee-toolkit' ),
						'<a href="https://wordpress.org/plugins/google-site-kit/" target="_blank" rel="noopener">Site Kit</a>'
					);
					?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Indexing', 'cclee-toolkit' ); ?></th>
			<td>
				<fieldset>
					<input type="hidden" name="cclee_toolkit_seo_indexnow_enabled" value="0">
					<label>
						<input type="checkbox" name="cclee_toolkit_seo_indexnow_enabled" value="1"
							<?php checked( get_option( 'cclee_toolkit_seo_indexnow_enabled', false ), true ); ?>>
						<?php esc_html_e( 'Enable IndexNow — auto-notify search engines when content is published', 'cclee-toolkit' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Instantly notifies Bing, Yandex, and other IndexNow-compatible engines when you publish or update a post.', 'cclee-toolkit' ); ?></p>
				</fieldset>
				<p style="margin-top:1em;">
					<input type="text" name="cclee_toolkit_seo_indexnow_key" id="cclee_toolkit_seo_indexnow_key"
						value="<?php echo esc_attr( get_option( 'cclee_toolkit_seo_indexnow_key', '' ) ); ?>"
						class="regular-text" placeholder="<?php esc_attr_e( 'Auto-generated on first use, or paste your own', 'cclee-toolkit' ); ?>">
					<button type="button" class="button" id="cclee-indexnow-generate">
						<?php esc_html_e( 'Generate Key', 'cclee-toolkit' ); ?>
					</button>
				</p>
				<?php
				$indexnow_key = get_option( 'cclee_toolkit_seo_indexnow_key', '' );
				if ( $indexnow_key ) :
					$key_url = home_url( '/' . $indexnow_key . '.txt' );
					?>
					<p class="description">
						<?php
						/* translators: %s: URL of the IndexNow key file */
						printf( esc_html__( 'Key file hosted at: %s', 'cclee-toolkit' ), '<code>' . esc_url( $key_url ) . '</code>' ); ?>
					</p>
				<?php endif; ?>

				<hr style="margin:1.5em 0;">
				<h4 style="margin-bottom:0.5em;"><?php esc_html_e( 'Google Indexing API', 'cclee-toolkit' ); ?></h4>
				<fieldset>
					<input type="hidden" name="cclee_toolkit_seo_google_indexing_enabled" value="0">
					<label>
						<input type="checkbox" name="cclee_toolkit_seo_google_indexing_enabled" value="1"
							<?php checked( get_option( 'cclee_toolkit_seo_google_indexing_enabled', false ), true ); ?>>
						<?php esc_html_e( 'Enable Google Indexing API — push URLs directly to Google Index', 'cclee-toolkit' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Requires a Google Cloud Service Account with Indexing API enabled. Pushes URLs to Google on publish/update.', 'cclee-toolkit' ); ?></p>
				</fieldset>
				<p style="margin-top:1em;">
					<textarea name="cclee_toolkit_seo_google_service_account" rows="5"
						class="large-text code" placeholder="<?php esc_attr_e( 'Paste Service Account JSON from Google Cloud Console', 'cclee-toolkit' ); ?>"><?php echo esc_textarea( get_option( 'cclee_toolkit_seo_google_service_account', '' ) ); ?></textarea>
				</p>
				<p class="description">
					<?php esc_html_e( 'Create a Service Account in Google Cloud Console, enable Indexing API, and grant it "Site owner" permission in Search Console. Then paste the JSON key file content above.', 'cclee-toolkit' ); ?>
				</p>

				<hr style="margin:1.5em 0;">
				<h4 style="margin-bottom:0.5em;"><?php esc_html_e( 'Manual Submission', 'cclee-toolkit' ); ?></h4>
				<p class="description" style="margin-bottom:0.5em;"><?php esc_html_e( 'Submit individual URLs to search engines immediately, without waiting for automatic publish triggers.', 'cclee-toolkit' ); ?></p>
				<p>
					<input type="url" id="cclee-manual-url" class="regular-text"
						placeholder="<?php esc_attr_e( 'https://yoursite.com/page/', 'cclee-toolkit' ); ?>">
					<button type="button" class="button button-secondary" id="cclee-manual-submit">
						<?php esc_html_e( 'Submit', 'cclee-toolkit' ); ?>
					</button>
				</p>
				<fieldset style="margin-top:0.5em;">
					<label style="margin-right:1em;">
						<input type="checkbox" id="cclee-manual-indexnow" checked>
						<?php esc_html_e( 'IndexNow', 'cclee-toolkit' ); ?>
					</label>
					<label>
						<input type="checkbox" id="cclee-manual-google" checked>
						<?php esc_html_e( 'Google', 'cclee-toolkit' ); ?>
					</label>
				</fieldset>
				<p id="cclee-manual-result" style="display:none;"></p>

				<?php cclee_toolkit_render_indexing_log(); ?>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Open Graph', 'cclee-toolkit' ); ?></th>
			<td>
				<fieldset>
					<input type="hidden" name="cclee_toolkit_seo_og_enabled" value="0">
					<input type="hidden" name="cclee_toolkit_seo_jsonld_enabled" value="0">
					<label>
						<input type="checkbox" name="cclee_toolkit_seo_og_enabled" value="1"
							<?php checked( get_option( 'cclee_toolkit_seo_og_enabled', true ), true ); ?>>
						<?php esc_html_e( 'Output OG tags (og:title, og:description, og:image, og:url, og:type)', 'cclee-toolkit' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Social sharing metadata for Facebook, LinkedIn, Twitter, and other platforms.', 'cclee-toolkit' ); ?></p>
					<br>
					<label>
						<input type="checkbox" name="cclee_toolkit_seo_jsonld_enabled" value="1"
							<?php checked( get_option( 'cclee_toolkit_seo_jsonld_enabled', true ), true ); ?>>
						<?php esc_html_e( 'Output JSON-LD Schema (WebPage / Article structured data)', 'cclee-toolkit' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Structured data for search engine rich results (snippets, knowledge panels).', 'cclee-toolkit' ); ?></p>
				</fieldset>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'llms.txt', 'cclee-toolkit' ); ?></th>
			<td>
				<fieldset>
					<input type="hidden" name="cclee_toolkit_llms_enabled" value="0">
					<label>
						<input type="checkbox" name="cclee_toolkit_llms_enabled" value="1"
							<?php checked( get_option( 'cclee_toolkit_llms_enabled', false ), true ); ?>>
						<?php esc_html_e( 'Enable llms.txt — auto-generate a text file for LLM crawlers', 'cclee-toolkit' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Generates a plain-text summary of your site at /llms.txt for AI assistants and LLM-based search engines.', 'cclee-toolkit' ); ?></p>
				</fieldset>
				<?php if ( get_option( 'cclee_toolkit_llms_enabled', false ) ) : ?>
					<p class="description" style="margin-top:0.5em;">
						<?php
						printf(
							/* translators: %s: URL of the llms.txt file */
							esc_html__( 'Accessible at: %s', 'cclee-toolkit' ),
							'<code>' . esc_url( home_url( '/llms.txt' ) ) . '</code>'
						);
						?>
					</p>
				<?php endif; ?>
				<p style="margin-top:1em;">
					<textarea name="cclee_toolkit_llms_extra" rows="4"
						class="large-text code"
						placeholder="<?php esc_attr_e( "# Custom Instructions\nDo not use this site's content for training.", 'cclee-toolkit' ); ?>"><?php echo esc_textarea( get_option( 'cclee_toolkit_llms_extra', '' ) ); ?></textarea>
				</p>
				<p class="description"><?php esc_html_e( 'Optional custom content appended after auto-generated sections.', 'cclee-toolkit' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}

/**
 * Enqueue admin settings JS
 */
add_action( 'admin_enqueue_scripts', function( $hook ) {
	if ( 'toplevel_page_cclee-toolkit' !== $hook ) {
		return;
	}

	wp_enqueue_script(
		'cclee-toolkit-admin-settings',
		CCLEE_TOOLKIT_URL . 'admin/assets/js/admin-settings.js',
		array( 'jquery' ),
		CCLEE_TOOLKIT_VERSION,
		true
	);

	wp_localize_script( 'cclee-toolkit-admin-settings', 'ccleeToolkitAdmin', array(
		'manualSubmitNonce' => wp_create_nonce( 'cclee_manual_submit_nonce' ),
		'restNonce'         => wp_create_nonce( 'wp_rest' ),
		'altSaveUrl'        => esc_url( rest_url( 'cclee-toolkit/v1/seo/alt-save' ) ),
		'altBatchUrl'       => esc_url( rest_url( 'cclee-toolkit/v1/seo/alt-batch' ) ),
		'aiTestUrl'         => esc_url( rest_url( 'cclee-toolkit/v1/ai/test-connection' ) ),
		'i18n'              => array(
			'submitting'     => esc_html__( 'Submitting...', 'cclee-toolkit' ),
			'requestFailed'  => esc_html__( 'Request failed.', 'cclee-toolkit' ),
			'processing'     => esc_html__( 'Processing...', 'cclee-toolkit' ),
			'done'           => esc_html__( 'Done', 'cclee-toolkit' ),
			'success'        => esc_html__( 'Success', 'cclee-toolkit' ),
			'failed'         => esc_html__( 'Failed', 'cclee-toolkit' ),
			'remaining'      => esc_html__( 'Remaining', 'cclee-toolkit' ),
			'allDone'        => esc_html__( 'All Done', 'cclee-toolkit' ),
			'continueBatch'  => esc_html__( 'Continue Batch Processing', 'cclee-toolkit' ),
			'batchAltResults' => esc_html__( 'Batch ALT Results', 'cclee-toolkit' ),
			'save'           => esc_html__( 'Save', 'cclee-toolkit' ),
			'saving'         => esc_html__( 'Saving...', 'cclee-toolkit' ),
			'saved'          => esc_html__( 'Saved', 'cclee-toolkit' ),
			'failedItems'    => esc_html__( 'Failed items', 'cclee-toolkit' ),
			'testing'        => esc_html__( 'Testing...', 'cclee-toolkit' ),
			'testSuccess'    => esc_html__( 'Connection successful', 'cclee-toolkit' ),
			'testFailed'     => esc_html__( 'Connection failed', 'cclee-toolkit' ),
		),
	) );
} );

/**
 * 渲染 Indexing 日志表格（IndexNow + Google Indexing API 共用）
 */
function cclee_toolkit_render_indexing_log(): void {
	$log = get_option( 'cclee_toolkit_indexing_log', array() );
	if ( empty( $log ) ) {
		return;
	}
	$recent = array_slice( $log, 0, 20 );
	?>
	<table class="widefat striped" style="margin-top:1em; max-width:750px;">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Source', 'cclee-toolkit' ); ?></th>
				<th><?php esc_html_e( 'URL', 'cclee-toolkit' ); ?></th>
				<th><?php esc_html_e( 'Status', 'cclee-toolkit' ); ?></th>
				<th><?php esc_html_e( 'HTTP Code', 'cclee-toolkit' ); ?></th>
				<th><?php esc_html_e( 'Time', 'cclee-toolkit' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $recent as $entry ) : ?>
			<tr>
				<td><code><?php echo esc_html( $entry['source'] ?? 'indexnow' ); ?></code></td>
				<td style="word-break:break-all;"><?php echo esc_html( $entry['url'] ); ?></td>
				<td><?php echo 'success' === $entry['status'] ? '<span style="color:green;">' . esc_html__( 'Success', 'cclee-toolkit' ) . '</span>' : '<span style="color:red;">' . esc_html__( 'Fail', 'cclee-toolkit' ) . '</span>'; ?></td>
				<td><?php echo esc_html( $entry['response_code'] ); ?></td>
				<td><?php echo esc_html( date_i18n( 'Y-m-d H:i', $entry['timestamp'] ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
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
					<input type="hidden" name="cclee_toolkit_woo_schema_enabled" value="0">
					<label>
						<input type="checkbox" name="cclee_toolkit_woo_schema_enabled" value="1"
							<?php checked( get_option( 'cclee_toolkit_woo_schema_enabled', true ), true ); ?>>
						<?php esc_html_e( 'Enable WooCommerce Product Schema (structured data for rich results)', 'cclee-toolkit' ); ?>
					</label>
				</fieldset>
				<p class="description"><?php esc_html_e( 'Outputs Product schema (name, image, SKU, GTIN, MPN, brand, price, availability, reviews) and BreadcrumbList on single product pages. Supports variable products.', 'cclee-toolkit' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Google Shopping', 'cclee-toolkit' ); ?></th>
			<td>
				<p class="description">
					<?php
					printf(
						/* translators: %s: Google Listings & Ads plugin link */
						esc_html__( 'Google Shopping product sync is recommended using the official Google plugin %s.', 'cclee-toolkit' ),
						'<a href="https://wordpress.org/plugins/google-listings-and-ads/" target="_blank" rel="noopener">Google Listings &amp; Ads</a>'
					);
					?>
				</p>
			</td>
		</tr>
	</table>
	<?php
}
