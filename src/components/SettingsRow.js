/**
 * Settings row component, copied from ClassifAI.
 */

/**
 * Settings row component.
 *
 * Simple wrapper component for consistent settings field layout.
 *
 * @param {Object} props             All the props passed to this function.
 * @param {string} props.label       Settings label.
 * @param {string} props.description Settings description (optional).
 * @param {string} props.className   Additional CSS class (optional).
 * @param {Object} props.children    The children of the component.
 * @return {React.ReactElement} SettingsRow component.
 */
export const SettingsRow = ( props ) => {
	const className = `settings-row${ props?.className ? ' ' + props.className : '' }`;

	return (
		<div className={ className }>
			<div className="settings-label">{ props.label }</div>
			<div className="settings-control">
				{ props.children }
				{ props.description && (
					<div className="settings-description">
						{ props.description }
					</div>
				) }
			</div>
		</div>
	);
};


