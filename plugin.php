<?php
/**
 * Plugin Name: ClassifAI Bias Check Extension
 * Description: Extends ClassifAI with bias and non-inclusive language detection in the Gutenberg editor.
 * Version: 1.0.0
 * Author: XWP
 * Author URI: https://xwp.co
 */

namespace ClassifAIBiasCheckExtension;

/**
 * Register the plugin feature.
 *
 * @param array $features The features to register.
 * @return array The features to register.
 */
function register_feature( array $features ): array {
    require_once __DIR__ . '/includes/class-plugin.php';
    $features[] = Plugin::class;
    return $features;
}

add_filter( 'language_processing_features', __NAMESPACE__ . '\register_feature' );
