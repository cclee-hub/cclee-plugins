<?php
/**
 * Case Study Custom Post Type
 *
 * 注册 case-study CPT 和相关 meta 字段
 *
 * @package CCLEE_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 注册 Case Study CPT
 */
function cclee_toolkit_register_case_study_cpt() {
	$labels = array(
		'name'                  => __( 'Case Studies', 'cclee-toolkit' ),
		'singular_name'         => __( 'Case Study', 'cclee-toolkit' ),
		'menu_name'             => __( 'Case Studies', 'cclee-toolkit' ),
		'all_items'             => __( 'All Case Studies', 'cclee-toolkit' ),
		'add_new'               => __( 'Add New', 'cclee-toolkit' ),
		'add_new_item'          => __( 'Add New Case Study', 'cclee-toolkit' ),
		'edit_item'             => __( 'Edit Case Study', 'cclee-toolkit' ),
		'new_item'              => __( 'New Case Study', 'cclee-toolkit' ),
		'view_item'             => __( 'View Case Study', 'cclee-toolkit' ),
		'search_items'          => __( 'Search Case Studies', 'cclee-toolkit' ),
		'not_found'             => __( 'No case studies found', 'cclee-toolkit' ),
		'not_found_in_trash'    => __( 'No case studies found in Trash', 'cclee-toolkit' ),
	);

	$args = array(
		'labels'              => $labels,
		'public'              => true,
		'publicly_queryable'  => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'query_var'           => true,
		'rewrite'             => array( 'slug' => 'case-study' ),
		'capability_type'     => 'post',
		'has_archive'         => true,
		'hierarchical'        => false,
		'menu_position'       => 20,
		'menu_icon'           => 'dashicons-portfolio',
		'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
		'show_in_rest'        => true,
		'template'            => array(),
		'template_lock'       => false,
	);

	register_post_type( 'case-study', $args );

	// 注册行业分类
	register_taxonomy( 'case-industry', 'case-study', array(
		'labels' => array(
			'name'          => __( 'Industries', 'cclee-toolkit' ),
			'singular_name' => __( 'Industry', 'cclee-toolkit' ),
		),
		'hierarchical'      => true,
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'rewrite'           => array( 'slug' => 'case-industry' ),
	) );
}
add_action( 'init', 'cclee_toolkit_register_case_study_cpt' );

/**
 * 注册 Case Study Meta Fields
 */
function cclee_toolkit_register_case_study_meta() {
	register_post_meta( 'case-study', 'client_name', array(
		'show_in_rest'      => true,
		'single'            => true,
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_text_field',
	) );

	register_post_meta( 'case-study', 'client_logo', array(
		'show_in_rest'      => true,
		'single'            => true,
		'type'              => 'integer',
		'sanitize_callback' => 'absint',
	) );

	register_post_meta( 'case-study', 'project_duration', array(
		'show_in_rest'      => true,
		'single'            => true,
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_text_field',
	) );

	register_post_meta( 'case-study', 'client_size', array(
		'show_in_rest'      => true,
		'single'            => true,
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_text_field',
	) );

	// 成果数据（4个指标）
	for ( $i = 1; $i <= 4; $i++ ) {
		register_post_meta( 'case-study', "metric_{$i}_value", array(
			'show_in_rest'      => true,
			'single'            => true,
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		) );
		register_post_meta( 'case-study', "metric_{$i}_label", array(
			'show_in_rest'      => true,
			'single'            => true,
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		) );
	}

	// 客户评价
	register_post_meta( 'case-study', 'testimonial_content', array(
		'show_in_rest'      => true,
		'single'            => true,
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_textarea_field',
	) );
	register_post_meta( 'case-study', 'testimonial_author', array(
		'show_in_rest'      => true,
		'single'            => true,
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	register_post_meta( 'case-study', 'testimonial_title', array(
		'show_in_rest'      => true,
		'single'            => true,
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_text_field',
	) );
}
add_action( 'init', 'cclee_toolkit_register_case_study_meta' );

/**
 * 添加 Meta Box
 */
function cclee_toolkit_add_case_study_meta_box() {
	add_meta_box(
		'cclee_toolkit_case_study_details',
		__( 'Case Study Details', 'cclee-toolkit' ),
		'cclee_toolkit_render_case_study_meta_box',
		'case-study',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'cclee_toolkit_add_case_study_meta_box' );

/**
 * 渲染 Meta Box
 */
function cclee_toolkit_render_case_study_meta_box( $post ) {
	wp_nonce_field( 'cclee_toolkit_case_study_meta', 'cclee_toolkit_case_study_meta_nonce' );

	$client_name = get_post_meta( $post->ID, 'client_name', true );
	$project_duration = get_post_meta( $post->ID, 'project_duration', true );
	$client_size = get_post_meta( $post->ID, 'client_size', true );

	// Metrics
	$metrics = array();
	for ( $i = 1; $i <= 4; $i++ ) {
		$metrics[$i] = array(
			'value' => get_post_meta( $post->ID, "metric_{$i}_value", true ),
			'label' => get_post_meta( $post->ID, "metric_{$i}_label", true ),
		);
	}

	// Testimonial
	$testimonial_content = get_post_meta( $post->ID, 'testimonial_content', true );
	$testimonial_author = get_post_meta( $post->ID, 'testimonial_author', true );
	$testimonial_title = get_post_meta( $post->ID, 'testimonial_title', true );
	?>
	<style>
		.cclee-toolkit-meta-field { margin-bottom: 15px; }
		.cclee-toolkit-meta-field label { display: block; font-weight: 600; margin-bottom: 5px; }
		.cclee-toolkit-meta-field input[type="text"] { width: 100%; padding: 8px; }
		.cclee-toolkit-meta-field textarea { width: 100%; padding: 8px; min-height: 80px; }
		.cclee-toolkit-metrics-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
		.cclee-toolkit-metric-item { background: #f9f9f9; padding: 15px; border-radius: 4px; }
		.cclee-toolkit-metric-item h4 { margin: 0 0 10px 0; }
	</style>

	<h3><?php _e( 'Client Information', 'cclee-toolkit' ); ?></h3>
	<div class="cclee-toolkit-meta-field">
		<label for="client_name"><?php _e( 'Client Name', 'cclee-toolkit' ); ?></label>
		<input type="text" id="client_name" name="client_name" value="<?php echo esc_attr( $client_name ); ?>">
	</div>
	<div class="cclee-toolkit-meta-field">
		<label for="project_duration"><?php _e( 'Project Duration', 'cclee-toolkit' ); ?></label>
		<input type="text" id="project_duration" name="project_duration" value="<?php echo esc_attr( $project_duration ); ?>" placeholder="e.g., 6 months">
	</div>
	<div class="cclee-toolkit-meta-field">
		<label for="client_size"><?php _e( 'Company Size', 'cclee-toolkit' ); ?></label>
		<input type="text" id="client_size" name="client_size" value="<?php echo esc_attr( $client_size ); ?>" placeholder="e.g., 100-500 employees">
	</div>

	<h3><?php _e( 'Results Metrics', 'cclee-toolkit' ); ?></h3>
	<div class="cclee-toolkit-metrics-grid">
		<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
		<div class="cclee-toolkit-metric-item">
			<h4><?php printf( __( 'Metric %d', 'cclee-toolkit' ), $i ); ?></h4>
			<div class="cclee-toolkit-meta-field">
				<label><?php _e( 'Value', 'cclee-toolkit' ); ?></label>
				<input type="text" name="metric_<?php echo $i; ?>_value" value="<?php echo esc_attr( $metrics[$i]['value'] ); ?>" placeholder="e.g., +150%">
			</div>
			<div class="cclee-toolkit-meta-field">
				<label><?php _e( 'Label', 'cclee-toolkit' ); ?></label>
				<input type="text" name="metric_<?php echo $i; ?>_label" value="<?php echo esc_attr( $metrics[$i]['label'] ); ?>" placeholder="e.g., Revenue Growth">
			</div>
		</div>
		<?php endfor; ?>
	</div>

	<h3><?php _e( 'Client Testimonial', 'cclee-toolkit' ); ?></h3>
	<div class="cclee-toolkit-meta-field">
		<label for="testimonial_content"><?php _e( 'Testimonial', 'cclee-toolkit' ); ?></label>
		<textarea id="testimonial_content" name="testimonial_content"><?php echo esc_textarea( $testimonial_content ); ?></textarea>
	</div>
	<div class="cclee-toolkit-meta-field">
		<label for="testimonial_author"><?php _e( 'Author Name', 'cclee-toolkit' ); ?></label>
		<input type="text" id="testimonial_author" name="testimonial_author" value="<?php echo esc_attr( $testimonial_author ); ?>">
	</div>
	<div class="cclee-toolkit-meta-field">
		<label for="testimonial_title"><?php _e( 'Author Title', 'cclee-toolkit' ); ?></label>
		<input type="text" id="testimonial_title" name="testimonial_title" value="<?php echo esc_attr( $testimonial_title ); ?>" placeholder="e.g., CEO">
	</div>
	<?php
}

/**
 * 保存 Meta 数据
 */
function cclee_toolkit_save_case_study_meta( $post_id ) {
	if ( ! isset( $_POST['cclee_toolkit_case_study_meta_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( $_POST['cclee_toolkit_case_study_meta_nonce'], 'cclee_toolkit_case_study_meta' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// 保存字段
	$fields = array( 'client_name', 'project_duration', 'client_size', 'testimonial_content', 'testimonial_author', 'testimonial_title' );
	foreach ( $fields as $field ) {
		if ( isset( $_POST[$field] ) ) {
			update_post_meta( $post_id, $field, sanitize_text_field( $_POST[$field] ) );
		}
	}

	// 保存 metrics
	for ( $i = 1; $i <= 4; $i++ ) {
		if ( isset( $_POST["metric_{$i}_value"] ) ) {
			update_post_meta( $post_id, "metric_{$i}_value", sanitize_text_field( $_POST["metric_{$i}_value"] ) );
		}
		if ( isset( $_POST["metric_{$i}_label"] ) ) {
			update_post_meta( $post_id, "metric_{$i}_label", sanitize_text_field( $_POST["metric_{$i}_label"] ) );
		}
	}
}
add_action( 'save_post_case-study', 'cclee_toolkit_save_case_study_meta' );

/**
 * Helper functions for templates
 */
function cclee_toolkit_get_case_study_meta( $post_id, $key ) {
	return get_post_meta( $post_id, $key, true );
}

function cclee_toolkit_get_case_study_metrics( $post_id ) {
	$metrics = array();
	for ( $i = 1; $i <= 4; $i++ ) {
		$value = get_post_meta( $post_id, "metric_{$i}_value", true );
		$label = get_post_meta( $post_id, "metric_{$i}_label", true );
		if ( $value && $label ) {
			$metrics[] = array( 'value' => $value, 'label' => $label );
		}
	}
	return $metrics;
}
