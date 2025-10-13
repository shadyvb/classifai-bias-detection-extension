/**
 * Prompt Repeater component, copied from ClassifAI.
 */

/**
 * WordPress dependencies
 */
import {
	Button,
	__experimentalInputControl as InputControl, // eslint-disable-line @wordpress/no-unsafe-wp-apis
	TextareaControl,
	__experimentalConfirmDialog as ConfirmDialog, // eslint-disable-line @wordpress/no-unsafe-wp-apis
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Component for the Prompt Repeater.
 *
 * This component allows users to add, edit, or remove prompts for the Bias Check feature.
 * It provides a UI for managing multiple prompts with a default prompt selector.
 *
 * @param {Object} props          Component props.
 * @param {Array}  props.prompts  Array of prompt objects.
 * @param {Function} props.setPrompts Function to update prompts.
 *
 * @return {React.ReactElement} PromptRepeater component.
 */
export const PromptRepeater = ( props ) => {
	const [ showConfirmDialog, setShowConfirmDialog ] = useState( false );
	const [ activeIndex, setActiveIndex ] = useState( null );
	const { prompts = [], setPrompts } = props;

	// Get the original/default prompt text for use as placeholder.
	const placeholder =
		prompts?.filter( ( prompt ) => prompt.original )[ 0 ]?.prompt || '';

	/**
	 * Add a new prompt.
	 */
	const addPrompt = () => {
		setPrompts( [
			...prompts,
			{ default: 0, original: 0, prompt: '', title: '' },
		] );
	};

	/**
	 * Remove a prompt at the given index.
	 *
	 * @param {number} index Index of prompt to remove.
	 */
	const removePrompt = ( index ) => {
		const prompt = prompts.splice( index, 1 );
		// Make the first prompt default if default prompt is removed.
		if ( prompt[ 0 ]?.default ) {
			prompts[ 0 ].default = 1;
		}
		setPrompts( [ ...prompts ] );
	};

	/**
	 * Update a prompt with new values.
	 *
	 * @param {number} index   Index of prompt to update.
	 * @param {Object} changes Changes to apply to the prompt.
	 */
	const onChange = ( index, changes ) => {
		// Remove default from all other prompts if this is being set as default.
		if ( changes.default ) {
			prompts.forEach( ( prompt, i ) => {
				if ( i !== index ) {
					prompt.default = 0;
				}
			} );
		}

		prompts[ index ] = {
			...prompts[ index ],
			...changes,
		};
		setPrompts( [ ...prompts ] );
	};

	/**
	 * Handle confirmation to remove a prompt.
	 */
	const handleConfirm = () => {
		setShowConfirmDialog( false );
		removePrompt( activeIndex );
	};

	return (
		<div className="classifai-prompts">
			{ prompts.map( ( prompt, index ) => (
				<div
					className="classifai-field-type-prompt-setting"
					id={ `classifai-prompt-setting-${ index }` }
					key={ index }
				>
					{ !! prompt.original && (
						<>
							<p className="classifai-original-prompt">
								<strong>
									{ __(
										'ClassifAI default prompt:',
										'classifai-bias-check-extension'
									) }
								</strong>{ ' ' }
								{ prompt.prompt }
							</p>
						</>
					) }
					{ ! prompt.original && (
						<>
							<InputControl
								type="text"
								value={ prompt.title }
								label={ __( 'Title', 'classifai-bias-check-extension' ) }
								placeholder={ __(
									'Prompt title',
									'classifai-bias-check-extension'
								) }
								onChange={ ( value ) => {
									onChange( index, {
										title: value,
									} );
								} }
								help={ __(
									'Short description of prompt to use for identification.',
									'classifai-bias-check-extension'
								) }
								className="classifai-prompt-title"
							/>
							<TextareaControl
								value={ prompt.prompt }
								label={ __( 'Prompt', 'classifai-bias-check-extension' ) }
								placeholder={ placeholder }
								onChange={ ( value ) => {
									onChange( index, {
										prompt: value,
									} );
								} }
								className="classifai-prompt-text"
								__nextHasNoMarginBottom
							/>
						</>
					) }
					<div className="actions-rows">
						<Button
							className="action__set_default"
							variant={ 'link' }
							disabled={ !! prompt.default }
							onClick={ () => {
								onChange( index, {
									default: 1,
								} );
							} }
						>
							{ !! prompt.default
								? __( 'Default prompt', 'classifai-bias-check-extension' )
								: __( 'Set as default prompt', 'classifai-bias-check-extension' ) }
						</Button>
						{ ! prompt.original && (
							<>
								<span className="separator">{ '|' }</span>
								<Button
									className="action__remove_prompt"
									variant={ 'link' }
									onClick={ () => {
										setActiveIndex( index );
										setShowConfirmDialog( true );
									} }
								>
									{ __( 'Trash', 'classifai-bias-check-extension' ) }
								</Button>
							</>
						) }
					</div>
				</div>
			) ) }
			<ConfirmDialog
				isOpen={ showConfirmDialog }
				onConfirm={ handleConfirm }
				onCancel={ () => setShowConfirmDialog( false ) }
				confirmButtonText={ __( 'Remove', 'classifai-bias-check-extension' ) }
				size="medium"
			>
				{ __(
					'Are you sure you want to remove the prompt?',
					'classifai-bias-check-extension'
				) }
			</ConfirmDialog>
			<Button
				className="action__add_prompt"
				onClick={ addPrompt }
				variant={ 'secondary' }
			>
				{ __( 'Add new prompt', 'classifai-bias-check-extension' ) }
			</Button>
		</div>
	);
};


