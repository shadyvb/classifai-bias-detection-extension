<?php

namespace ClassifAIBiasCheckExtension;
use Classifai\Features\Feature;
use Classifai\Providers\OpenAI\ChatGPT;
use Classifai\Services\LanguageProcessing;

use function Classifai\sanitize_prompts;
use function Classifai\get_default_prompt;

class Plugin extends Feature {
    /**
     * ID of the current feature.
     *
     * @var string
     */
    const ID = 'feature_classifai_bias_check';

    /**
     * Default prompt for bias detection.
     *
     * @var string
     */
    public $prompt = 'You are a bias and inclusive language analyzer. Analyze the following content for potential bias or non-inclusive language in these categories:

1. Race/Ethnicity.
2. Gender.
3. Socioeconomic Status.
4. Age/Ageism.
5. Religion.
6. Disability
7. Sexual Orientation.
8. Nationality/Xenophobia.
9. Body/Appearance.

For each category, determine if bias is detected (true/false).

If you find any biased or non-inclusive language, provide specific excerpts and explanations.

Return your analysis in the following JSON format:
{
  "bias_detected": true/false,
  "categories": {
    "race": true/false,
    "gender": true/false,
    "socioeconomic": true/false,
    "age": true/false,
    "religion": true/false,
    "disability": true/false,
    "sexual_orientation": true/false,
    "nationality": true/false,
    "body_appearance": true/false
  },
  "comments": [
    {
      "excerpt": "specific text excerpt",
      "explanation": "explanation of the bias",
      "categories": ["category1", "category2"]
    }
  ]
}

Respond ONLY with valid JSON, no additional text.';

    /**
     * Constructor.
     */
    public function __construct() {
        $this->label = __( 'ClassifAI Bias Check', 'classifai-bias-check-extension' );

        // Contains all providers that are registered to the service.
        $this->provider_instances = $this->get_provider_instances( LanguageProcessing::get_service_providers() );

        // Contains just the providers this feature supports.
        $this->supported_providers = [
            ChatGPT::ID   => __( 'OpenAI ChatGPT', 'classifai-bias-check-extension' ),

            // Note: Other providers below require special handling for each provider given the different URLs and APIs.

            // GeminiAPI::ID => __( 'Google AI (Gemini API)', 'classifai-bias-check-extension' ),
            // OpenAI::ID    => __( 'Azure OpenAI', 'classifai-bias-check-extension' ),
            // Grok::ID      => __( 'xAI Grok', 'classifai-bias-check-extension' ),
            // ChromeAI::ID  => __( 'Chrome AI (experimental)', 'classifai-bias-check-extension' ),
            // Ollama::ID    => __( 'Ollama', 'classifai-bias-check-extension' ),
        ];
    }

    /**
     * Set up necessary hooks.
     *
     * This will only fire if the Feature is enabled.
     */
    public function feature_setup() {
        // Register REST endpoint.
        add_action( 'rest_api_init', [ $this, 'register_endpoints' ] );

        // Enqueue editor assets.
        add_action( 'enqueue_block_assets', [ $this, 'enqueue_editor_assets' ] );

        // Enqueue admin assets (only on ClassifAI settings page).
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    /**
     * Register REST endpoint.
     */
    public function register_endpoints() {
        register_rest_route(
            'classifai-bias-check-extension/v1',
            'bias-check(?:/(?P<id>\d+))?',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'generate_bias_analysis' ],
                'permission_callback' => fn() => current_user_can( 'edit_posts' ),
                'args'                => [
                    'id' => [
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]
        );
    }

    /**
     * Generate bias analysis for a post.
     *
     * @param int $post_id The post ID to analyze.
     * @return array|\WP_Error
     */
    public function generate_bias_analysis( \WP_REST_Request $request ) {
        $post_id = $request->get_param( 'id' );

        // Validate post exists.
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'invalid_post', __( 'Invalid post ID.', 'classifai-bias-check-extension' ) );
        }

        // Check if feature is enabled.
        if ( ! $this->is_feature_enabled() ) {
            return new WP_Error( 'feature_disabled', __( 'Bias check feature is not enabled.', 'classifai-bias-check-extension' ) );
        }

        $settings = $this->get_settings();

        // Get the selected provider.
        $provider_id = $settings['provider'] ?? ChatGPT::ID;

        // Check if provider is authenticated.
        if ( empty( $settings[ $provider_id ]['authenticated'] ) ) {
            return new WP_Error( 'not_authenticated', __( 'The selected provider is not authenticated. Please check your settings.', 'classifai-bias-check-extension' ) );
        }

        // Get post content.
        $content = $this->get_post_content( $post );

        if ( empty( $content ) ) {
            return new WP_Error( 'empty_content', __( 'Post content is empty.', 'classifai-bias-check-extension' ) );
        }

        // Get the custom prompt.
        $prompt = $this->build_bias_detection_prompt();

        // Call the provider API.
        $response = $this->call_provider_api( $provider_id, $settings, $prompt, $content );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        // Parse and structure the response.
        return rest_ensure_response( $this->parse_bias_response( $response ) );
    }

    /**
     * Get post content for analysis.
     *
     * @param \WP_Post $post The post object.
     * @return string
     */
    private function get_post_content( \WP_Post $post ): string {
        $content = '';

        // Add title.
        if ( ! empty( $post->post_title ) ) {
            $content .= "Title: " . $post->post_title . "\n\n";
        }

        // Add content.
        if ( ! empty( $post->post_content ) ) {
            $post_content = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
            $content .= "Content: " . $post_content;
        }

        return trim( $content );
    }

    /**
     * Build the prompt for bias detection.
     *
     * @return string
     */
    private function build_bias_detection_prompt(): string {
        $settings = $this->get_settings();

        // Get custom prompt from settings or use default.
        $prompt = esc_textarea( get_default_prompt( $settings['bias_check_prompt'] ?? [] ) ?? $this->prompt );

        /**
         * Filter the prompt we will send to the AI provider.
         *
         * @param string $prompt Prompt we are sending to the provider.
         * @param array  $settings Feature settings.
         *
         * @return string Prompt.
         */
        return apply_filters( 'classifai_bias_check_prompt', $prompt, $settings );
    }

    /**
     * Call the provider API for bias analysis.
     *
     * This method routes to the appropriate provider-specific implementation.
     * Currently supports ChatGPT, with room to add more providers in the future.
     *
     * @param string $provider_id The provider ID (e.g., ChatGPT::ID).
     * @param array  $settings Feature settings.
     * @param string $prompt System prompt for bias detection.
     * @param string $content Content to analyze.
     * @return string|\WP_Error The response text or WP_Error on failure.
     */
    private function call_provider_api( string $provider_id, array $settings, string $prompt, string $content ) {
        switch ( $provider_id ) {
            case ChatGPT::ID:
                return $this->call_chatgpt( $settings, $prompt, $content );

            // Future providers can be added here:
            // case GeminiAPI::ID:
            //     return $this->call_gemini( $settings, $prompt, $content );
            //
            // case OpenAI::ID:
            //     return $this->call_azure_openai( $settings, $prompt, $content );

            default:
                return new WP_Error(
                    'unsupported_provider',
                    sprintf(
                        /* translators: %s: provider ID */
                        __( 'Provider "%s" is not yet supported for bias checking.', 'classifai-bias-check-extension' ),
                        $provider_id
                    )
                );
        }
    }

    /**
     * Call ChatGPT API for bias analysis.
     *
     * @param array  $settings Feature settings.
     * @param string $prompt System prompt.
     * @param string $content Content to analyze.
     * @return string|\WP_Error
     */
    private function call_chatgpt( array $settings, string $prompt, string $content ) {
        // Get API key from settings.
        $api_key = $settings[ ChatGPT::ID ]['api_key'] ?? '';

        if ( empty( $api_key ) ) {
            return new WP_Error( 'missing_api_key', __( 'ChatGPT API key is missing.', 'classifai-bias-check-extension' ) );
        }

        // Create API request instance using ClassifAI's APIRequest class.
        $request = new \Classifai\Providers\OpenAI\APIRequest( $api_key, $this->get_option_name() );

        /**
         * Filter the request body before sending to ChatGPT.
         *
         * @param array $body Request body that will be sent to ChatGPT.
         * @param array $settings Feature settings.
         *
         * @return array Request body.
         */
        $body = apply_filters(
            'classifai_bias_check_chatgpt_request_body',
            [
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
            ],
            $settings
        );

        // Make the API request using ClassifAI's APIRequest class.
        $response = $request->post(
            'https://api.openai.com/v1/chat/completions',
            [
                'body' => wp_json_encode( $body ),
            ]
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        // Extract the message content from the response.
        if ( ! empty( $response['choices'][0]['message']['content'] ) ) {
            return $response['choices'][0]['message']['content'];
        }

        return new WP_Error( 'empty_response', __( 'ChatGPT returned an empty response.', 'classifai-bias-check-extension' ) );
    }

    /**
     * Parse the bias response from provider.
     *
     * @param string $response The JSON response from ChatGPT.
     * @return array|\WP_Error
     */
    private function parse_bias_response( string $response ) {
        if ( empty( $response ) ) {
            return new WP_Error( 'empty_response', __( 'Empty response from provider.', 'classifai-bias-check-extension' ) );
        }

        // Try to extract JSON from the response (in case there's extra text).
        $json_start = strpos( $response, '{' );
        $json_end   = strrpos( $response, '}' );

        if ( $json_start === false || $json_end === false ) {
            return new WP_Error( 'invalid_json', __( 'Could not parse provider response.', 'classifai-bias-check-extension' ) );
        }

        $json_string = substr( $response, $json_start, $json_end - $json_start + 1 );
        $parsed      = json_decode( $json_string, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'json_error', __( 'Invalid JSON in provider response.', 'classifai-bias-check-extension' ) );
        }

        // Ensure the response has the expected structure.
        $default_structure = [
            'bias_detected' => false,
            'categories'    => [
                'race'               => false,
                'gender'             => false,
                'socioeconomic'      => false,
                'age'                => false,
                'religion'           => false,
                'disability'         => false,
                'sexual_orientation' => false,
                'nationality'        => false,
                'body_appearance'    => false,
            ],
            'comments'      => [],
        ];

        return wp_parse_args( $parsed, $default_structure );
    }

    /**
     * Enqueue the editor scripts.
     */
    public function enqueue_editor_assets() {
        global $post;

        if ( empty( $post ) ) {
            return;
        }

        $asset_file = include( plugin_dir_path( __FILE__ ) . '../build/index.asset.php' );

        wp_enqueue_script(
            'classifai-bias-check-sidebar',
            plugins_url( '../build/index.js', __FILE__ ),
            $asset_file['dependencies'],
            $asset_file['version'],
            true
        );

        // Pass data to JavaScript.
        wp_localize_script(
            'classifai-bias-check-sidebar',
            'classifaiBiasCheck',
            [
                'apiUrl' => rest_url( 'classifai-bias-check-extension/v1/bias-check/' ),
                'nonce'  => wp_create_nonce( 'wp_rest' ),
                'postId' => $post->ID,
            ]
        );
    }

    /**
     * Enqueue the admin scripts.
     *
     * @param string $hook_suffix The current admin page.
     */
    public function enqueue_admin_assets( string $hook_suffix ) {
        // Enqueue assets only on the ClassifAI settings page.
        if ( 'tools_page_classifai' !== $hook_suffix ) {
            return;
        }

        $asset_file = include( plugin_dir_path( __FILE__ ) . '../build/settings.asset.php');

        wp_enqueue_script(
            'classifai-bias-check-scripts',
            plugins_url( '../build/settings.js', __FILE__ ),
            $asset_file['dependencies'],
            $asset_file['version']
        );
    }

    /**
     * Get the description for the enable field.
     *
     * @return string
     */
    public function get_enable_description(): string {
        return esc_html__( 'A sidebar panel will be added to the editor that can be used to check content for bias and non-inclusive language.', 'classifai-bias-check-extension' );
    }

    /**
     * Add any needed custom fields.
     */
    public function add_custom_settings_fields() {
        $settings = $this->get_settings();

        add_settings_field(
            'bias_check_prompt',
            esc_html__( 'Prompt', 'classifai-bias-check-extension' ),
            [ $this, 'render_prompt_repeater_field' ],
            $this->get_option_name(),
            $this->get_option_name() . '_section',
            [
                'label_for'     => 'bias_check_prompt',
                'placeholder'   => $this->prompt,
                'default_value' => $settings['bias_check_prompt'],
                'description'   => esc_html__( 'Customize the prompt used for bias detection. The content to be analyzed will be automatically appended to this prompt.', 'classifai-bias-check-extension' ),
            ]
        );
    }

    /**
     * Returns the default settings for the feature.
     *
     * @return array
     */
    public function get_feature_default_settings(): array {
        return [
            'bias_check_prompt' => [
                [
                    'title'    => esc_html__( 'ClassifAI default', 'classifai-bias-check-extension' ),
                    'prompt'   => $this->prompt,
                    'original' => 1,
                ],
            ],
            'provider'          => ChatGPT::ID,
        ];
    }

    /**
     * Returns the settings for the feature.
     *
     * @param string $index The index of the setting to return.
     * @return array|mixed
     */
    public function get_settings( $index = false ) {
        $settings = parent::get_settings( $index );

        // Keep using the original prompt from the codebase to allow updates.
        if ( $settings && ! empty( $settings['bias_check_prompt'] ) ) {
            foreach ( $settings['bias_check_prompt'] as $key => $prompt ) {
                if ( 1 === intval( $prompt['original'] ?? 0 ) ) {
                    $settings['bias_check_prompt'][ $key ]['prompt'] = $this->prompt;
                    break;
                }
            }
        }

        return $settings;
    }

    /**
     * Sanitizes the default feature settings.
     *
     * @param array $new_settings Settings being saved.
     * @return array
     */
    public function sanitize_default_feature_settings( array $new_settings ): array {
        $new_settings['bias_check_prompt'] = sanitize_prompts( 'bias_check_prompt', $new_settings );

        return $new_settings;
    }
}
