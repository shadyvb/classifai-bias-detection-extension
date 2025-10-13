/**
 * ClassifAI Bias Check Extension - Editor Integration
 *
 * Registers the bias check sidebar in the WordPress block editor.
 *
 * @package ClassifAIBiasCheckExtension
 * @since 1.0.0
 */

import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { Button, Spinner, Notice, PanelBody } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

console.log('ClassifAIBiasCheckSidebar');

const biasCategories = {
	race: __('Race/Ethnicity', 'classifai-bias-check-extension'),
	gender: __('Gender', 'classifai-bias-check-extension'),
	socioeconomic: __('Socioeconomic', 'classifai-bias-check-extension'),
	age: __('Age', 'classifai-bias-check-extension'),
	religion: __('Religion', 'classifai-bias-check-extension'),
	disability: __('Disability', 'classifai-bias-check-extension'),
	sexual_orientation: __('Sexual Orientation', 'classifai-bias-check-extension'),
	nationality: __('Nationality', 'classifai-bias-check-extension'),
	body_appearance: __('Body/Appearance', 'classifai-bias-check-extension'),
};

const ClassifAIBiasCheckSidebar = () => {
	const [isLoading, setIsLoading] = useState(false);
	const [results, setResults] = useState(null);
	const [error, setError] = useState(null);

	// Get current post ID from the editor.
	const postId = useSelect((select) => {
		return select('core/editor').getCurrentPostId();
	}, []);

	/**
	 * Handle bias check API request.
	 */
	const handleCheckBias = async () => {
		setIsLoading(true);
		setError(null);
		setResults(null);

		try {
			const response = await apiFetch({
				path: `/classifai-bias-check-extension/v1/bias-check/${postId}`,
				method: 'POST',
			});

			// Check if response has error.
			if (response.code && response.message) {
				setError(response.message);
			} else {
				setResults(response);
			}
		} catch (err) {
			setError(err.message || __('An error occurred while checking for bias.', 'classifai-bias-check-extension'));
		} finally {
			setIsLoading(false);
		}
	};

	/**
	 * Render bias category item.
	 */
	const renderCategory = (label, value) => (
		<div style={{
			display: 'flex',
			justifyContent: 'space-between',
			padding: '8px 0',
			borderBottom: '1px solid #e0e0e0'
		}}>
			<span style={{ fontWeight: '500' }}>{label}:</span>
			<span style={{
				color: value ? '#d63638' : '#00a32a',
				fontWeight: 'bold'
			}}>
				{value ? __('Yes', 'classifai-bias-check-extension') : __('No', 'classifai-bias-check-extension')}
			</span>
		</div>
	);

	/**
	 * Render comment item.
	 */
	const renderComment = (comment, index) => (
		<div
			key={index}
			style={{
				marginBottom: '16px',
				padding: '12px',
				backgroundColor: '#fff8e5',
				borderLeft: '3px solid #f0b849',
				borderRadius: '4px'
			}}
		>
			<blockquote style={{
				margin: '0 0 8px 0',
				padding: '8px',
				backgroundColor: '#fff',
				borderLeft: '2px solid #ddd',
				fontStyle: 'italic',
				fontSize: '13px'
			}}>
				{comment.excerpt}
			</blockquote>
			<div style={{ fontSize: '13px', marginTop: '8px' }}>
				<strong>{__('Explanation:', 'classifai-bias-check-extension')}</strong>
				<p style={{ margin: '4px 0 0 0' }}>{comment.explanation}</p>
				{comment.categories && comment.categories.length > 0 && (
					<div style={{ marginTop: '8px', fontSize: '12px', color: '#666' }}>
						<strong>{__('Categories:', 'classifai-bias-check-extension')}</strong> {comment.categories.join(', ')}
					</div>
				)}
			</div>
		</div>
	);

	return (
		<div style={{ padding: '16px' }}>
			<div style={{ marginBottom: '16px' }}>
				<Button
					variant="primary"
					onClick={handleCheckBias}
					disabled={isLoading}
					style={{ width: '100%' }}
				>
					{isLoading ? (
						<>
							<Spinner />
							{__('Checking...', 'classifai-bias-check-extension')}
						</>
					) : (
						__('Check for Bias', 'classifai-bias-check-extension')
					)}
				</Button>
			</div>

			{error && (
				<Notice status="error" isDismissible={false}>
					{error}
				</Notice>
			)}

			{results && (
				<div>
					{/* Overall Status */}
					<div style={{
						padding: '12px',
						marginBottom: '16px',
						backgroundColor: results.bias_detected ? '#ffe5e5' : '#e5ffe5',
						borderRadius: '4px',
						textAlign: 'center'
					}}>
						<strong>
							{results.bias_detected
								? __('⚠️ Bias Detected', 'classifai-bias-check-extension')
								: __('✓ No Bias Detected', 'classifai-bias-check-extension')}
						</strong>
					</div>

					{/* Categories */}
					<PanelBody title={__('Detected Bias by Category', 'classifai-bias-check-extension')} initialOpen={true}>
						<div style={{ marginTop: '8px' }}>
							{results.categories && Object.entries(biasCategories).map(([key, label]) => renderCategory(label, results.categories?.[key]))}
						</div>
					</PanelBody>

					{/* Comments */}
					{results.comments && results.comments.length > 0 && (
						<PanelBody title={__('Comments', 'classifai-bias-check-extension')} initialOpen={true}>
							<div style={{ marginTop: '8px' }}>
								{results.comments.map((comment, index) => renderComment(comment, index))}
							</div>
						</PanelBody>
					)}

					{/* No comments message */}
					{(!results.comments || results.comments.length === 0) && results.bias_detected && (
						<Notice status="info" isDismissible={false}>
							{__('No specific comments provided.', 'classifai-bias-check-extension')}
						</Notice>
					)}
				</div>
			)}
		</div>
	);
};

/**
 * Register the Bias Check sidebar plugin.
 */
registerPlugin('classifai-bias-check-extension', {
    render: () => {
        return (
            <>
                <PluginSidebarMoreMenuItem
                    target="classifai-bias-check-sidebar"
                    icon="shield"
                >
                    {__('Bias Check', 'classifai-bias-check-extension')}
                </PluginSidebarMoreMenuItem>
                <PluginSidebar
                    name="classifai-bias-check-sidebar"
                    title={__('Bias Check', 'classifai-bias-check-extension')}
                    icon="shield"
                >
                    <ClassifAIBiasCheckSidebar />
                </PluginSidebar>
            </>
        );
    },
});

