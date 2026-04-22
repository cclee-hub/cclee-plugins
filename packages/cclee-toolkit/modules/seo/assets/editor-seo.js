/**
 * CCLEE Toolkit — SEO Meta Editor Sidebar
 *
 * 逐页 SEO 字段编辑器：Meta Title、Meta Description、Robots Meta、AI 一键生成。
 *
 * @package CCLEE_Toolkit
 */

( function ( wp ) {
	const { registerPlugin } = wp.plugins;
	const { PluginSidebar } = wp.editPost;
	const { createElement, useState } = wp.element;
	const { TextControl, TextareaControl, CheckboxControl, Button, Spinner, Notice } = wp.components;
	const { useSelect, useDispatch } = wp.data;

	const el = createElement;

	/** AI 开关，由后端 wp_localize_script 注入 */
	const aiEnabled = window.ccleeToolkitSEOConfig && window.ccleeToolkitSEOConfig.aiEnabled;

	/** Meta Description 推荐长度上限 */
	var DESC_LIMIT = 155;

	/**
	 * SEO Meta 侧边栏组件
	 */
	function CCLEEToolkitSEOSidebar() {
		var _useDispatch = useDispatch( 'core/editor' );
		var editPost = _useDispatch.editPost;

		// 读取当前 post meta
		var meta = useSelect( function ( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
		}, [] );

		// 读取当前 post title + content（供 AI 调用）
		var postData = useSelect( function ( select ) {
			var editor = select( 'core/editor' );
			return {
				title: editor.getEditedPostAttribute( 'title' ),
				content: editor.getEditedPostAttribute( 'content' ),
				postId: editor.getCurrentPostId(),
			};
		}, [] );

		var title = meta.cclee_seo_meta_title || '';
		var description = meta.cclee_seo_meta_description || '';
		var noindex = meta.cclee_seo_robots_noindex || false;
		var nofollow = meta.cclee_seo_robots_nofollow || false;

		// AI 状态
		var _useState = useState( false );
		var aiLoading = _useState[ 0 ];
		var setAiLoading = _useState[ 1 ];
		var _useState2 = useState( '' );
		var aiError = _useState2[ 0 ];
		var setAiError = _useState2[ 1 ];

		// AI Content Analysis 状态
		var _useState3 = useState( false );
		var analyzeLoading = _useState3[ 0 ];
		var setAnalyzeLoading = _useState3[ 1 ];
		var _useState4 = useState( '' );
		var analyzeError = _useState4[ 0 ];
		var setAnalyzeError = _useState4[ 1 ];
		var _useState5 = useState( null );
		var analyzeResult = _useState5[ 0 ];
		var setAnalyzeResult = _useState5[ 1 ];

		// Internal Link Suggestions 状态
		var _useState6 = useState( false );
		var linksLoading = _useState6[ 0 ];
		var setLinksLoading = _useState6[ 1 ];
		var _useState7 = useState( '' );
		var linksError = _useState7[ 0 ];
		var setLinksError = _useState7[ 1 ];
		var _useState8 = useState( null );
		var linksResult = _useState8[ 0 ];
		var setLinksResult = _useState8[ 1 ];

		/**
		 * 更新单个 meta 字段
		 */
		function updateMeta( key, value ) {
			editPost( { meta: Object.assign( {}, meta, obj( key, value ) ) } );
		}
		function obj( key, value ) {
			var o = {};
			o[ key ] = value;
			return o;
		}

		/**
		 * AI 一键生成
		 */
		async function aiGenerate() {
			setAiLoading( true );
			setAiError( '' );

			try {
				var config = window.ccleeToolkitSEOConfig || {};
				var restUrl = config.restUrl || '/wp-json/cclee-toolkit/v1/seo/meta';
				var nonce = config.nonce || '';

				var response = await fetch( restUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': nonce,
					},
					credentials: 'same-origin',
					body: JSON.stringify( {
						post_id: postData.postId,
						title: postData.title || '',
						content: postData.content || '',
					} ),
				} );

				if ( ! response.ok ) {
					var errorData = await response.json().catch( function () { return {}; } );
					throw new Error( errorData.message || 'API Error: ' + response.status );
				}

				var data = await response.json();

				if ( data.title || data.description ) {
					editPost( {
						meta: Object.assign( {}, meta, {
							cclee_seo_meta_title: data.title || title,
							cclee_seo_meta_description: data.description || description,
						} ),
					} );
				}
			} catch ( err ) {
				setAiError( err.message || 'AI generation failed.' );
			} finally {
				setAiLoading( false );
			}
		}

		/**
		 * AI 内容分析
		 */
		async function analyzeContent() {
			setAnalyzeLoading( true );
			setAnalyzeError( '' );
			setAnalyzeResult( null );

			try {
				var config = window.ccleeToolkitSEOConfig || {};
				var analyzeUrl = ( config.restUrl || '/wp-json/cclee-toolkit/v1/seo/meta' ).replace( /\/meta$/, '/analyze' );
				var nonce = config.nonce || '';

				var response = await fetch( analyzeUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': nonce,
					},
					credentials: 'same-origin',
					body: JSON.stringify( {
						post_id: postData.postId,
						title: postData.title || '',
						content: postData.content || '',
						meta_title: title,
						meta_description: description,
					} ),
				} );

				if ( ! response.ok ) {
					var errorData = await response.json().catch( function () { return {}; } );
					throw new Error( errorData.message || 'API Error: ' + response.status );
				}

				var data = await response.json();
				setAnalyzeResult( data );
			} catch ( err ) {
				setAnalyzeError( err.message || 'Analysis failed.' );
			} finally {
				setAnalyzeLoading( false );
			}
		}

		/**
		 * AI 内部链接建议
		 */
		async function fetchLinks() {
			setLinksLoading( true );
			setLinksError( '' );
			setLinksResult( null );

			try {
				var config = window.ccleeToolkitSEOConfig || {};
				var linksUrl = ( config.restUrl || '/wp-json/cclee-toolkit/v1/seo/meta' ).replace( /\/meta$/, '/internal-links' );
				var nonce = config.nonce || '';

				var response = await fetch( linksUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': nonce,
					},
					credentials: 'same-origin',
					body: JSON.stringify( {
						post_id: postData.postId,
						title: postData.title || '',
						content: postData.content || '',
					} ),
				} );

				if ( ! response.ok ) {
					var errorData = await response.json().catch( function () { return {}; } );
					throw new Error( errorData.message || 'API Error: ' + response.status );
				}

				var data = await response.json();
				setLinksResult( data );
			} catch ( err ) {
				setLinksError( err.message || 'Failed to fetch link suggestions.' );
			} finally {
				setLinksLoading( false );
			}
		}

		// 字符计数颜色
		var descLen = ( description || '' ).length;
		var countColor = descLen > DESC_LIMIT ? '#d63638' : descLen > DESC_LIMIT - 20 ? '#dba617' : '#007cba';

		return el(
			PluginSidebar,
			{
				name: 'cclee-toolkit-seo-sidebar',
				title: 'SEO',
				icon: 'search',
			},
			el(
				'div',
				{ style: { padding: '16px' } },

				// Meta Title
				el( TextControl, {
					label: 'Meta Title',
					value: title,
					onChange: function ( v ) { updateMeta( 'cclee_seo_meta_title', v ); },
					placeholder: postData.title || 'Enter meta title...',
					help: ( title || postData.title || '' ).length + ' / 60 characters',
				} ),

				// Meta Description
				el( TextareaControl, {
					label: 'Meta Description',
					value: description,
					onChange: function ( v ) { updateMeta( 'cclee_seo_meta_description', v ); },
					placeholder: 'Enter meta description...',
					rows: 3,
				} ),
				el( 'span', {
					style: {
						display: 'block',
						marginTop: '-8px',
						marginBottom: '16px',
						fontSize: '12px',
						color: countColor,
					},
				}, descLen + ' / ' + DESC_LIMIT ),

				// Robots Meta
				el( 'div', { style: { marginBottom: '16px' } },
					el( 'strong', { style: { display: 'block', marginBottom: '8px' } }, 'Robots Meta' ),
					el( CheckboxControl, {
						label: 'noindex',
						checked: noindex,
						onChange: function ( v ) { updateMeta( 'cclee_seo_robots_noindex', v ); },
					} ),
					el( CheckboxControl, {
						label: 'nofollow',
						checked: nofollow,
						onChange: function ( v ) { updateMeta( 'cclee_seo_robots_nofollow', v ); },
					} )
				),

				// AI Generate
				aiEnabled && el(
					'div',
					{ style: { borderTop: '1px solid #ddd', paddingTop: '16px' } },
					el( 'strong', { style: { display: 'block', marginBottom: '8px' } }, 'AI Generate' ),
					el( Button, {
						variant: 'secondary',
						onClick: aiGenerate,
						disabled: aiLoading,
						style: { width: '100%' },
					}, aiLoading ? 'Generating...' : 'Generate Meta Title & Description' ),
					aiLoading && el(
						'div',
						{ style: { display: 'flex', justifyContent: 'center', padding: '16px' } },
						el( Spinner )
					),
					aiError && el(
						Notice,
						{
							status: 'error',
							isDismissible: true,
							onRemove: function () { setAiError( '' ); },
							style: { marginTop: '12px' },
						},
						aiError
					)
				),

				// AI Content Analysis
				aiEnabled && el(
					'div',
					{ style: { borderTop: '1px solid #ddd', paddingTop: '16px', marginTop: '16px' } },
					el( 'strong', { style: { display: 'block', marginBottom: '8px' } }, 'AI Content Analysis' ),
					el( Button, {
						variant: 'secondary',
						onClick: analyzeContent,
						disabled: analyzeLoading,
						style: { width: '100%' },
					}, analyzeLoading ? 'Analyzing...' : 'Analyze Content' ),
					analyzeLoading && el(
						'div',
						{ style: { display: 'flex', justifyContent: 'center', padding: '16px' } },
						el( Spinner )
					),
					analyzeError && el(
						Notice,
						{
							status: 'error',
							isDismissible: true,
							onRemove: function () { setAnalyzeError( '' ); },
							style: { marginTop: '12px' },
						},
						analyzeError
					),
					analyzeResult && el(
						'div',
						{ style: { marginTop: '16px' } },
						el( 'div',
							{ style: { marginBottom: '12px' } },
							el( 'strong', null, 'Score: ' ),
							el( 'strong', {
								style: {
									fontSize: '18px',
									color: analyzeResult.score >= 80 ? '#00a32a' : analyzeResult.score >= 60 ? '#dba617' : '#d63638',
								},
							}, analyzeResult.score + '/100' )
						),
						el( 'div', { style: { marginBottom: '12px' } },
							el( 'strong', { style: { display: 'block', marginBottom: '4px' } }, 'Keyword Coverage' ),
							el( 'p', { style: { margin: '0', fontSize: '13px', lineHeight: '1.5' } }, analyzeResult.keyword_coverage )
						),
						el( 'div', { style: { marginBottom: '12px' } },
							el( 'strong', { style: { display: 'block', marginBottom: '4px' } }, 'Title Quality' ),
							el( 'p', { style: { margin: '0', fontSize: '13px', lineHeight: '1.5' } }, analyzeResult.title_quality )
						),
						el( 'div',
							el( 'strong', { style: { display: 'block', marginBottom: '4px' } }, 'Description Suggestion' ),
							el( 'p', { style: { margin: '0', fontSize: '13px', lineHeight: '1.5' } }, analyzeResult.description_suggestion )
						)
					)
				),

				// Internal Link Suggestions
				aiEnabled && el(
					'div',
					{ style: { borderTop: '1px solid #ddd', paddingTop: '16px', marginTop: '16px' } },
					el( 'strong', { style: { display: 'block', marginBottom: '8px' } }, 'Internal Link Suggestions' ),
					el( Button, {
						variant: 'secondary',
						onClick: fetchLinks,
						disabled: linksLoading,
						style: { width: '100%' },
					}, linksLoading ? 'Loading...' : 'Suggest Internal Links' ),
					linksLoading && el(
						'div',
						{ style: { display: 'flex', justifyContent: 'center', padding: '16px' } },
						el( Spinner )
					),
					linksError && el(
						Notice,
						{
							status: 'error',
							isDismissible: true,
							onRemove: function () { setLinksError( '' ); },
							style: { marginTop: '12px' },
						},
						linksError
					),
					linksResult !== null && linksResult.length > 0 && el(
						'div',
						{ style: { marginTop: '16px' } },
						linksResult.map( function ( item, i ) {
							return el(
								'div',
								{
									key: i,
									style: {
										marginBottom: i < linksResult.length - 1 ? '12px' : '0',
										paddingBottom: i < linksResult.length - 1 ? '12px' : '0',
										borderBottom: i < linksResult.length - 1 ? '1px solid #eee' : 'none',
									},
								},
								el( 'a', {
									href: item.url,
									target: '_blank',
									rel: 'noopener noreferrer',
									style: { fontWeight: '600', textDecoration: 'none' },
								}, item.title ),
								el( 'p', {
									style: { margin: '4px 0 0', fontSize: '12px', lineHeight: '1.5', color: '#666' },
								}, item.reason )
							);
						} )
					),
					linksResult !== null && linksResult.length === 0 && el(
						'p',
						{ style: { marginTop: '12px', fontSize: '13px', color: '#666' } },
						'No suggestions found.'
					)
				)
			)
		);
	}

	registerPlugin( 'cclee-toolkit-seo-meta', {
		render: CCLEEToolkitSEOSidebar,
	} );
} )( window.wp );
