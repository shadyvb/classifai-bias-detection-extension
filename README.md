# Extending ClassifAI: Adding a New Feature

## Overview

This tutorial demonstrates how to extend ClassifAI with a custom feature using the Bias Check Extension as a practical example. You'll learn how to create a feature that integrates with ClassifAI's architecture, leverages existing AI providers, and provides both backend API functionality and frontend UI components.

## Architecture Understanding

ClassifAI uses a three-tier architecture:

```
Service (e.g., Language Processing)
  └── Feature (e.g., Bias Check, Excerpt Generation)
        └── Provider (e.g., ChatGPT, Gemini, Azure OpenAI)
```

**Key Concepts:**
- **Services**: High-level groupings (LanguageProcessing, ImageProcessing, ContentRecommendation)
- **Features**: Specific AI capabilities that belong to a Service
- **Providers**: AI service implementations that Features can use

Your custom feature will:
1. Extend the `Feature` abstract class
2. Register itself with an appropriate Service
3. Use existing Providers from ClassifAI
4. Provide settings UI and functionality

## Prerequisites

Before starting, ensure you have:
- ClassifAI plugin installed and active
- Node.js and npm installed
- Composer installed (for PHP dependencies)
- Basic understanding of WordPress plugin development
- Familiarity with React/WordPress block editor

## Step 1: Create Plugin Structure

Create your extension plugin with the following structure:

```
my-classifai-extension/
├── plugin.php                    # Main plugin file
├── includes/
│   └── class-plugin.php         # Feature class
├── src/
│   ├── index.js                 # Editor integration
│   ├── settings.js              # Settings page integration
│   └── components/              # Reusable React components
├── build/                       # Compiled assets (generated)
├── package.json                 # JavaScript dependencies
└── README.md
```

## Step 2: Create the Main Plugin File

Create `plugin.php` as your plugin's entry point:

```php
<?php
/**
 * Plugin Name: My ClassifAI Extension
 * Description: Extends ClassifAI with custom feature
 * Version: 1.0.0
 * Author: Your Name
 */

namespace MyClassifAIExtension;

/**
 * Register the feature with ClassifAI.
 *
 * @param array $features The features to register.
 * @return array The features to register.
 */
function register_feature( array $features ): array {
    require_once __DIR__ . '/includes/class-plugin.php';
    $features[] = Plugin::class;
    return $features;
}

// Hook into the appropriate service.
// Options: language_processing_features, image_processing_features, content_recommendation_features
add_filter( 'language_processing_features', __NAMESPACE__ . '\register_feature' );
```

**Key Points:**
- Use a unique namespace for your extension
- The filter name determines which Service your Feature belongs to
- Require your Feature class file before adding it to the features array

## Step 3: Create the Feature Class

Create `includes/class-plugin.php` extending ClassifAI's `Feature` abstract class:

```php
<?php

namespace MyClassifAIExtension;

use Classifai\Features\Feature;
use Classifai\Providers\OpenAI\ChatGPT;
use Classifai\Services\LanguageProcessing;

class Plugin extends Feature {
    /**
     * Unique ID for this feature.
     *
     * This will be used as:
     * - WordPress option name to store settings
     * - Feature identifier in ClassifAI's system
     *
     * @var string
     */
    const ID = 'feature_my_custom_feature';

    /**
     * Constructor - Set up the feature.
     */
    public function __construct() {
        // Set the display label for this feature.
        $this->label = __( 'My Custom Feature', 'my-extension' );

        // Get all available providers from the service.
        $this->provider_instances = $this->get_provider_instances(
            LanguageProcessing::get_service_providers()
        );

        // Define which providers this feature supports.
        $this->supported_providers = [
            ChatGPT::ID => __( 'OpenAI ChatGPT', 'my-extension' ),
            // Add more providers as needed
        ];
    }

    /**
     * Set up hooks that run when the feature is enabled.
     *
     * Only fires if the feature is enabled in settings.
     */
    public function feature_setup() {
        // Register REST API endpoints.
        add_action( 'rest_api_init', [ $this, 'register_endpoints' ] );

        // Enqueue editor assets.
        add_action( 'enqueue_block_assets', [ $this, 'enqueue_editor_assets' ] );

        // Enqueue admin assets (settings page).
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    /**
     * Get default settings for the feature.
     *
     * @return array Default settings.
     */
    public function get_feature_default_settings(): array {
        return [
            'provider' => ChatGPT::ID,
            // Add your custom settings here
        ];
    }

    /**
     * Sanitize settings before saving.
     *
     * @param array $new_settings Settings being saved.
     * @return array Sanitized settings.
     */
    public function sanitize_default_feature_settings( array $new_settings ): array {
        // Sanitize your custom settings
        return $new_settings;
    }

    /**
     * Get description for the enable field.
     *
     * @return string Feature description.
     */
    public function get_enable_description(): string {
        return esc_html__( 'Description of what your feature does.', 'my-extension' );
    }
}
```

**Critical Points:**
- The `ID` constant must be unique and follow the pattern `feature_*`
- `feature_setup()` only runs when the feature is enabled
- Use `$this->provider_instances` to access configured providers
- `$this->supported_providers` limits which providers users can select

## Step 4: Register REST API Endpoints

Add REST API functionality to your Feature class:

```php
/**
 * Register REST API endpoints.
 */
public function register_endpoints() {
    register_rest_route(
        'my-extension/v1',
        '/process(?:/(?P<id>\d+))?',
        [
            'methods'             => 'POST',
            'callback'            => [ $this, 'process_request' ],
            'permission_callback' => fn() => current_user_can( 'edit_posts' ),
            'args'                => [
                'id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => 'is_numeric',
                ],
            ],
        ]
    );
}

/**
 * Process the API request.
 *
 * @param \WP_REST_Request $request The REST request.
 * @return array|\WP_Error Response or error.
 */
public function process_request( \WP_REST_Request $request ) {
    $post_id = $request->get_param( 'id' );

    // Validate the feature is enabled.
    if ( ! $this->is_feature_enabled() ) {
        return new \WP_Error(
            'feature_disabled',
            __( 'Feature is not enabled.', 'my-extension' )
        );
    }

    $settings = $this->get_settings();
    $provider_id = $settings['provider'] ?? ChatGPT::ID;

    // Check provider authentication.
    if ( empty( $settings[ $provider_id ]['authenticated'] ) ) {
        return new \WP_Error(
            'not_authenticated',
            __( 'Provider is not authenticated.', 'my-extension' )
        );
    }

    // Your feature logic here
    $result = $this->do_something( $post_id, $settings );

    if ( is_wp_error( $result ) ) {
        return $result;
    }

    return rest_ensure_response( $result );
}
```

**Best Practices:**
- Always validate the feature is enabled
- Check provider authentication status
- Use `\WP_Error` for error handling
- Return responses with `rest_ensure_response()`

## Step 5: Integrate with AI Providers

Call AI provider APIs using ClassifAI's authentication:

```php
/**
 * Call the provider API.
 *
 * @param string $provider_id Provider ID.
 * @param array  $settings    Feature settings.
 * @param string $prompt      The prompt to send.
 * @param string $content     The content to process.
 * @return string|\WP_Error Response or error.
 */
private function call_provider_api( string $provider_id, array $settings, string $prompt, string $content ) {
    switch ( $provider_id ) {
        case ChatGPT::ID:
            return $this->call_chatgpt( $settings, $prompt, $content );

        // Add more providers as needed
        default:
            return new \WP_Error(
                'unsupported_provider',
                sprintf(
                    __( 'Provider "%s" is not supported.', 'my-extension' ),
                    $provider_id
                )
            );
    }
}

/**
 * Call ChatGPT API.
 *
 * @param array  $settings Feature settings.
 * @param string $prompt   System prompt.
 * @param string $content  Content to process.
 * @return string|\WP_Error Response or error.
 */
private function call_chatgpt( array $settings, string $prompt, string $content ) {
    $api_key = $settings[ ChatGPT::ID ]['api_key'] ?? '';

    if ( empty( $api_key ) ) {
        return new \WP_Error(
            'missing_api_key',
            __( 'API key is missing.', 'my-extension' )
        );
    }

    // Use ClassifAI's APIRequest class for authenticated requests.
    $request = new \Classifai\Providers\OpenAI\APIRequest(
        $api_key,
        $this->get_option_name()
    );

    $body = [
        'model'       => 'gpt-4o-mini',
        'messages'    => [
            [
                'role'    => 'system',
                'content' => $prompt,
            ],
            [
                'role'    => 'user',
                'content' => $content,
            ],
        ],
        'temperature' => 0.3,
        'max_tokens'  => 2000,
    ];

    // Make the API request.
    $response = $request->post(
        'https://api.openai.com/v1/chat/completions',
        [
            'body' => wp_json_encode( $body ),
        ]
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    // Extract the response content.
    if ( ! empty( $response['choices'][0]['message']['content'] ) ) {
        return $response['choices'][0]['message']['content'];
    }

    return new \WP_Error(
        'empty_response',
        __( 'Provider returned an empty response.', 'my-extension' )
    );
}
```

**Key Points:**
- Use `\Classifai\Providers\OpenAI\APIRequest` for OpenAI requests (handles authentication and rate limiting)
- Access API keys from `$settings[ $provider_id ]['api_key']`
- Each provider requires its own implementation method
- Apply filters to allow customization of API requests

## Step 6: Add Custom Prompts Support

To support custom prompts (like ClassifAI's prompt repeater):

```php
use function Classifai\sanitize_prompts;
use function Classifai\get_default_prompt;

/**
 * Default prompt for your feature.
 *
 * @var string
 */
public $prompt = 'Your default system prompt here...';

/**
 * Get default settings including prompt.
 *
 * @return array Default settings.
 */
public function get_feature_default_settings(): array {
    return [
        'my_feature_prompt' => [
            [
                'title'    => __( 'ClassifAI default', 'my-extension' ),
                'prompt'   => $this->prompt,
                'original' => 1,
            ],
        ],
        'provider' => ChatGPT::ID,
    ];
}

/**
 * Build the prompt for your feature.
 *
 * @return string The prompt to use.
 */
private function build_prompt(): string {
    $settings = $this->get_settings();

    // Get the selected prompt or use default.
    $prompt = esc_textarea(
        get_default_prompt( $settings['my_feature_prompt'] ?? [] ) ?? $this->prompt
    );

    /**
     * Filter the prompt before sending to provider.
     *
     * @param string $prompt   The prompt.
     * @param array  $settings Feature settings.
     */
    return apply_filters( 'my_extension_prompt', $prompt, $settings );
}

/**
 * Sanitize settings including prompts.
 *
 * @param array $new_settings Settings being saved.
 * @return array Sanitized settings.
 */
public function sanitize_default_feature_settings( array $new_settings ): array {
    $new_settings['my_feature_prompt'] = sanitize_prompts(
        'my_feature_prompt',
        $new_settings
    );

    return $new_settings;
}

/**
 * Override get_settings to keep using original prompt.
 *
 * This ensures the default prompt gets updated when the plugin is updated.
 *
 * @param string $index Setting index to retrieve.
 * @return array|mixed Settings.
 */
public function get_settings( $index = false ) {
    $settings = parent::get_settings( $index );

    // Update the original prompt from codebase.
    if ( $settings && ! empty( $settings['my_feature_prompt'] ) ) {
        foreach ( $settings['my_feature_prompt'] as $key => $prompt ) {
            if ( 1 === intval( $prompt['original'] ?? 0 ) ) {
                $settings['my_feature_prompt'][ $key ]['prompt'] = $this->prompt;
                break;
            }
        }
    }

    return $settings;
}
```

## Step 7: Create Settings UI

Create `package.json` for JavaScript dependencies:

```json
{
  "name": "my-classifai-extension",
  "version": "1.0.0",
  "scripts": {
    "build": "wp-scripts build src/index.js src/settings.js --output-path=build",
    "start": "wp-scripts start src/index.js src/settings.js --output-path=build"
  },
  "dependencies": {
    "@wordpress/api-fetch": "^6.0.0",
    "@wordpress/components": "^25.0.0",
    "@wordpress/data": "^9.0.0",
    "@wordpress/element": "^5.0.0",
    "@wordpress/i18n": "^4.0.0",
    "@wordpress/plugins": "^6.0.0"
  },
  "devDependencies": {
    "@wordpress/scripts": "^26.0.0"
  }
}
```

Create `src/settings.js` for the settings interface:

```javascript
/**
 * WordPress dependencies
 */
import { Fill } from '@wordpress/components';
import { registerPlugin } from '@wordpress/plugins';
import { __ } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';

/**
 * Settings component.
 */
const MyFeatureSettings = () => {
    // Access feature settings from ClassifAI's store.
    const featureSettings = useSelect( ( select ) =>
        select( 'classifai-settings' ).getFeatureSettings()
    );

    // Get the dispatch function to update settings.
    const { setFeatureSettings } = useDispatch( 'classifai-settings' );

    return (
        <Fill name="ClassifAIFeatureSettings">
            {/* Add your custom settings fields here */}
            <div className="settings-row">
                <div className="settings-label">
                    { __( 'My Setting', 'my-extension' ) }
                </div>
                <div className="settings-control">
                    {/* Your controls */}
                </div>
            </div>
        </Fill>
    );
};

/**
 * Register the settings plugin.
 *
 * IMPORTANT: The scope must match your feature ID with underscores replaced by hyphens.
 * Feature ID: feature_my_custom_feature
 * Scope:      feature-my-custom-feature
 */
registerPlugin( 'feature-my-custom-feature', {
    scope: 'feature-my-custom-feature',
    render: MyFeatureSettings,
} );
```

**Critical Points:**
- Use `Fill` component with name `ClassifAIFeatureSettings`
- The `registerPlugin` scope MUST match your feature ID (with hyphens instead of underscores)
- Access settings via `select( 'classifai-settings' ).getFeatureSettings()`
- Update settings via `dispatch( 'classifai-settings' ).setFeatureSettings()`

Enqueue the settings script in your Feature class:

```php
/**
 * Enqueue admin scripts.
 *
 * @param string $hook_suffix Current admin page.
 */
public function enqueue_admin_assets( string $hook_suffix ) {
    // Only on ClassifAI settings page.
    if ( 'tools_page_classifai' !== $hook_suffix ) {
        return;
    }

    $asset_file = include( plugin_dir_path( __FILE__ ) . '../build/settings.asset.php' );

    wp_enqueue_script(
        'my-extension-settings',
        plugins_url( '../build/settings.js', __FILE__ ),
        $asset_file['dependencies'],
        $asset_file['version'],
        true
    );
}
```

## Step 8: Create Editor Integration

Create `src/index.js` for block editor integration:

```javascript
/**
 * WordPress dependencies
 */
import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { Button, Spinner, Notice } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

/**
 * Sidebar component.
 */
const MyFeatureSidebar = () => {
    const [ isLoading, setIsLoading ] = useState( false );
    const [ results, setResults ] = useState( null );
    const [ error, setError ] = useState( null );

    // Get current post ID.
    const postId = useSelect( ( select ) => {
        return select( 'core/editor' ).getCurrentPostId();
    }, [] );

    /**
     * Handle API request.
     */
    const handleProcess = async () => {
        setIsLoading( true );
        setError( null );
        setResults( null );

        try {
            const response = await apiFetch( {
                path: `/my-extension/v1/process/${ postId }`,
                method: 'POST',
            } );

            if ( response.code && response.message ) {
                setError( response.message );
            } else {
                setResults( response );
            }
        } catch ( err ) {
            setError(
                err.message ||
                __( 'An error occurred.', 'my-extension' )
            );
        } finally {
            setIsLoading( false );
        }
    };

    return (
        <div style={ { padding: '16px' } }>
            <Button
                variant="primary"
                onClick={ handleProcess }
                disabled={ isLoading }
                style={ { width: '100%' } }
            >
                { isLoading ? (
                    <>
                        <Spinner />
                        { __( 'Processing...', 'my-extension' ) }
                    </>
                ) : (
                    __( 'Process', 'my-extension' )
                ) }
            </Button>

            { error && (
                <Notice status="error" isDismissible={ false }>
                    { error }
                </Notice>
            ) }

            { results && (
                <div>
                    {/* Display your results */}
                </div>
            ) }
        </div>
    );
};

/**
 * Register the sidebar plugin.
 */
registerPlugin( 'my-extension', {
    render: () => {
        return (
            <>
                <PluginSidebarMoreMenuItem
                    target="my-extension-sidebar"
                    icon="admin-generic"
                >
                    { __( 'My Feature', 'my-extension' ) }
                </PluginSidebarMoreMenuItem>
                <PluginSidebar
                    name="my-extension-sidebar"
                    title={ __( 'My Feature', 'my-extension' ) }
                    icon="admin-generic"
                >
                    <MyFeatureSidebar />
                </PluginSidebar>
            </>
        );
    },
} );
```

Enqueue the editor script in your Feature class:

```php
/**
 * Enqueue editor assets.
 */
public function enqueue_editor_assets() {
    global $post;

    if ( empty( $post ) ) {
        return;
    }

    $asset_file = include( plugin_dir_path( __FILE__ ) . '../build/index.asset.php' );

    wp_enqueue_script(
        'my-extension-editor',
        plugins_url( '../build/index.js', __FILE__ ),
        $asset_file['dependencies'],
        $asset_file['version'],
        true
    );

    // Pass data to JavaScript.
    wp_localize_script(
        'my-extension-editor',
        'myExtensionData',
        [
            'apiUrl' => rest_url( 'my-extension/v1/' ),
            'nonce'  => wp_create_nonce( 'wp_rest' ),
            'postId' => $post->ID,
        ]
    );
}
```

## Step 9: Build and Test

Build your JavaScript assets:

```bash
# Install dependencies
npm install

# Build for production
npm run build

# Or start development watch mode
npm run start
```

The build process creates:
- `build/index.js` - Editor integration
- `build/settings.js` - Settings page integration
- `build/*.asset.php` - Dependency manifests

## Step 10: Activate and Configure

1. **Activate Your Plugin**
   - Navigate to WordPress admin → Plugins
   - Activate your extension plugin

2. **Configure ClassifAI Provider**
   - Go to Tools → ClassifAI
   - Navigate to the appropriate Service (e.g., Language Processing)
   - Configure and authenticate your chosen provider (e.g., ChatGPT)

3. **Enable Your Feature**
   - In the same Service, find your custom feature
   - Enable it and configure settings
   - Save changes

4. **Test in Editor**
   - Create or edit a post
   - Look for your feature in the editor toolbar (three dots menu)
   - Test the functionality

## Advanced Topics

### Adding Support for Multiple Providers

To support additional AI providers:

1. **Update Constructor**:
```php
$this->supported_providers = [
    ChatGPT::ID   => __( 'OpenAI ChatGPT', 'my-extension' ),
    GeminiAPI::ID => __( 'Google AI (Gemini)', 'my-extension' ),
    // Add more as needed
];
```

2. **Add Provider-Specific Methods**:
```php
private function call_provider_api( string $provider_id, array $settings, string $prompt, string $content ) {
    switch ( $provider_id ) {
        case ChatGPT::ID:
            return $this->call_chatgpt( $settings, $prompt, $content );

        case GeminiAPI::ID:
            return $this->call_gemini( $settings, $prompt, $content );

        default:
            return new \WP_Error( 'unsupported_provider', __( 'Unsupported provider.', 'my-extension' ) );
    }
}

private function call_gemini( array $settings, string $prompt, string $content ) {
    // Gemini-specific implementation
}
```

### Adding Extensibility Hooks

Provide filters for developers to customize your feature:

```php
/**
 * Filter the prompt.
 *
 * @param string $prompt   The prompt.
 * @param array  $settings Feature settings.
 */
$prompt = apply_filters( 'my_extension_prompt', $prompt, $settings );

/**
 * Filter the API request body.
 *
 * @param array $body     Request body.
 * @param array $settings Feature settings.
 */
$body = apply_filters( 'my_extension_request_body', $body, $settings );

/**
 * Filter the processed results.
 *
 * @param array  $results  Processed results.
 * @param int    $post_id  Post ID.
 * @param array  $settings Feature settings.
 */
$results = apply_filters( 'my_extension_results', $results, $post_id, $settings );
```

### Custom Settings Components

Create reusable React components for complex settings:

```javascript
// src/components/CustomControl.js
export const CustomControl = ( { value, onChange } ) => {
    return (
        <div className="custom-control">
            {/* Your control implementation */}
        </div>
    );
};

// Use in settings.js
import { CustomControl } from './components/CustomControl';

const MyFeatureSettings = () => {
    const { featureSettings } = useSelect( /*...*/ );
    const { setFeatureSettings } = useDispatch( /*...*/ );

    return (
        <Fill name="ClassifAIFeatureSettings">
            <CustomControl
                value={ featureSettings.my_setting }
                onChange={ ( newValue ) => {
                    setFeatureSettings( {
                        my_setting: newValue,
                    } );
                } }
            />
        </Fill>
    );
};
```

