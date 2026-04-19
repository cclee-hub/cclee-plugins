/**
 * CCLEE Toolkit — AI Editor Assistant
 *
 * 功能：编辑器侧边栏 AI 辅助面板
 * 仅在编辑器内加载，不影响前端性能
 *
 * @package CCLEE_Toolkit
 */

( function ( wp ) {
	const { registerPlugin } = wp.plugins;
	const { PluginSidebar } = wp.editPost;
	const { createElement, useState } = wp.element;
	const { TextareaControl, Button, Spinner, Notice } = wp.components;

	/**
	 * AI 辅助侧边栏组件
	 */
	function CCLEEToolkitAISidebar() {
		const [ prompt, setPrompt ] = useState( '' );
		const [ promptType, setPromptType ] = useState( 'paragraph' );
		const [ result, setResult ] = useState( '' );
		const [ loading, setLoading ] = useState( false );
		const [ error, setError ] = useState( '' );

		/**
		 * 调用 AI API（通过后端代理）
		 */
		async function generateContent() {
			if ( ! prompt.trim() ) {
				setError( 'Please enter a topic or prompt.' );
				return;
			}

			setLoading( true );
			setError( '' );
			setResult( '' );

			try {
				const apiRoot = window.wpApiSettings?.root || '/wp-json/';
				const nonce = window.wpApiSettings?.nonce || '';

				const response = await fetch( apiRoot + 'cclee-toolkit/v1/ai/generate', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': nonce,
					},
					credentials: 'same-origin',
					body: JSON.stringify( {
						prompt: prompt,
						type: promptType,
					} ),
				} );

				if ( ! response.ok ) {
					const errorData = await response.json().catch( () => ( {} ) );
					throw new Error( errorData.message || `API Error: ${ response.status }` );
				}

				const data = await response.json();
				const generatedText = data.content || 'No content generated.';

				setResult( generatedText );
			} catch ( err ) {
				setError( err.message || 'Failed to generate content.' );
			} finally {
				setLoading( false );
			}
		}

		/**
		 * 复制到剪贴板
		 */
		function copyToClipboard() {
			if ( result ) {
				navigator.clipboard.writeText( result );
			}
		}

		const promptTypes = [ 'paragraph', 'headline', 'list', 'cta', 'faq' ];

		return createElement(
			PluginSidebar,
			{
				name: 'cclee-toolkit-ai-sidebar',
				title: 'CCLEE AI Assistant',
				icon: 'editor-help',
			},
			createElement(
				'div',
				{ className: 'cclee-toolkit-ai-panel', style: { padding: '16px' } },

				// Prompt 类型选择
				createElement( 'div', { style: { marginBottom: '16px' } }, [
					createElement( 'strong', { key: 'label' }, 'Content Type' ),
					createElement(
						'select',
						{
							key: 'select',
							value: promptType,
							onChange: ( e ) => setPromptType( e.target.value ),
							style: {
								width: '100%',
								marginTop: '8px',
								padding: '8px',
							},
						},
						promptTypes.map( ( type ) =>
							createElement(
								'option',
								{ key: type, value: type },
								type.charAt( 0 ).toUpperCase() + type.slice( 1 )
							)
						)
					),
				] ),

				// Prompt 输入
				createElement( TextareaControl, {
					label: 'Topic / Prompt',
					value: prompt,
					onChange: setPrompt,
					placeholder: 'Enter your topic or specific prompt...',
					rows: 3,
				} ),

				// 生成按钮
				createElement(
					Button,
					{
						variant: 'primary',
						onClick: generateContent,
						disabled: loading,
						style: { marginTop: '8px', width: '100%' },
					},
					loading ? 'Generating...' : 'Generate Content'
				),

				// 错误提示
				error &&
					createElement(
						Notice,
						{
							key: 'error-notice',
							status: 'error',
							isDismissible: true,
							onRemove: () => setError( '' ),
							style: { marginTop: '16px' },
						},
						error
					),

				// 加载状态
				loading &&
					createElement(
						'div',
						{
							key: 'loading-spinner',
							style: {
								display: 'flex',
								justifyContent: 'center',
								padding: '24px',
							},
						},
						createElement( Spinner )
					),

				// 生成结果
				result &&
					createElement(
						'div',
						{ key: 'result-container', style: { marginTop: '16px' } },
						createElement( 'strong', null, 'Generated Content:' ),
						createElement(
							'div',
							{
								style: {
									background: '#f5f5f5',
									padding: '12px',
									borderRadius: '4px',
									marginTop: '8px',
									whiteSpace: 'pre-wrap',
									fontSize: '14px',
								},
							},
							result
						),
						createElement(
							Button,
							{
								variant: 'secondary',
								onClick: copyToClipboard,
								style: { marginTop: '8px' },
							},
							'Copy to Clipboard'
						)
					),

				// 使用说明
				createElement(
					'div',
					{
						key: 'usage-info',
						style: {
							marginTop: '24px',
							paddingTop: '16px',
							borderTop: '1px solid #ddd',
							fontSize: '12px',
							color: '#666',
						},
					},
					createElement( 'strong', null, 'Setup:' ),
					createElement(
						'p',
						null,
						'Configure API Key in Settings → CCLEE Toolkit.'
					)
				)
			)
		);
	}

	// 注册插件
	registerPlugin( 'cclee-toolkit-ai-assistant', {
		render: CCLEEToolkitAISidebar,
	} );
} )( window.wp );
