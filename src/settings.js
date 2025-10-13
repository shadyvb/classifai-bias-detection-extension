/**
 * WordPress dependencies
 */
import { Fill } from '@wordpress/components';
import { registerPlugin } from '@wordpress/plugins';
import { __ } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { SettingsRow } from './components/SettingsRow';
import { PromptRepeater } from './components/PromptRepeater';

/**
 * Component for Bias Check feature settings.
 *
 * This component provides the settings UI for the Bias Check feature,
 * including a prompt repeater field for customizing the bias detection prompt.
 *
 * @return {React.ReactElement} BiasCheckSettings component.
 */
const ClassifAIBiasCheckExtensionSettings = () => {
	const featureSettings = useSelect( ( select ) =>
		select( 'classifai-settings' ).getFeatureSettings()
	);
	const { setFeatureSettings } = useDispatch( 'classifai-settings' );

	/**
	 * Update the prompts in the feature settings.
	 *
	 * @param {Array} prompts Array of prompt objects.
	 */
	const setPrompts = ( prompts ) => {
		setFeatureSettings( {
			bias_check_prompt: prompts,
		} );
	};

	return (
		<>
			<Fill name="ClassifAIFeatureSettings">
				<SettingsRow
					label={ __( 'Prompt', 'classifai-bias-check-extension' ) }
					description={ __(
						'Customize the prompt used for bias detection. The content to be analyzed will be automatically appended to this prompt.',
						'classifai-bias-check-extension'
					) }
				>
					<PromptRepeater
						prompts={ featureSettings.bias_check_prompt }
						setPrompts={ setPrompts }
					/>
				</SettingsRow>
			</Fill>
		</>
	);
};

registerPlugin( 'feature-classifai-bias-check', {
	scope: 'feature-classifai-bias-check',
	render: ClassifAIBiasCheckExtensionSettings,
} );
