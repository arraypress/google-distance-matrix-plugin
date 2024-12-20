<?php
/**
 * ArrayPress - Google Distance Matrix Tester
 *
 * @package     ArrayPress\Google\DistanceMatrix
 * @author      David Sherlock
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @link        https://arraypress.com/
 * @since       1.0.0
 *
 * @wordpress-plugin
 * Plugin Name:         ArrayPress - Google Distance Matrix Tester
 * Plugin URI:          https://github.com/arraypress/google-distance-matrix-plugin
 * Description:         A plugin to test and demonstrate the Google Distance Matrix API integration.
 * Version:             1.0.0
 * Requires at least:   6.7.1
 * Requires PHP:        7.4
 * Author:              David Sherlock
 * Author URI:          https://arraypress.com/
 * License:             GPL v2 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:         arraypress-google-distance-matrix
 * Domain Path:         /languages
 * Network:             false
 * Update URI:          false
 */

declare( strict_types=1 );

namespace ArrayPress\Google\DistanceMatrix;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/vendor/autoload.php';

class Plugin {

	/**
	 * API Client instance
	 *
	 * @var Client|null
	 */
	private ?Client $client = null;

	/**
	 * Hook name for the admin page.
	 *
	 * @var string
	 */
	const MENU_HOOK = 'google_page_arraypress-google-distance-matrix';

	/**
	 * Plugin constructor
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'load_textdomain' ] );
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

		// Initialize client if API key exists
		$api_key = get_option( 'google_distance_matrix_api_key' );
		if ( ! empty( $api_key ) ) {
			$this->client = new Client(
				$api_key,
				(bool) get_option( 'google_distance_matrix_enable_cache', true ),
				(int) get_option( 'google_distance_matrix_cache_duration', 86400 )
			);
		}
	}

	/**
	 * Load plugin text domain
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'arraypress-google-distance-matrix',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	}

	/**
	 * Add menu page
	 */
	public function add_menu_page(): void {
		global $admin_page_hooks;

		if ( ! isset( $admin_page_hooks['arraypress-google'] ) ) {
			add_menu_page(
				__( 'Google', 'arraypress-google-distance-matrix' ),
				__( 'Google', 'arraypress-google-distance-matrix' ),
				'manage_options',
				'arraypress-google',
				null,
				'dashicons-google',
				30
			);
		}

		add_submenu_page(
			'arraypress-google',
			__( 'Distance Matrix', 'arraypress-google-distance-matrix' ),
			__( 'Distance Matrix', 'arraypress-google-distance-matrix' ),
			'manage_options',
			'arraypress-google-distance-matrix',
			[ $this, 'render_test_page' ]
		);
	}

	/**
	 * Register settings
	 */
	public function register_settings(): void {
		register_setting( 'distance_matrix_settings', 'google_distance_matrix_api_key' );
		register_setting( 'distance_matrix_settings', 'google_distance_matrix_enable_cache', 'bool' );
		register_setting( 'distance_matrix_settings', 'google_distance_matrix_cache_duration', 'int' );
	}

	/**
	 * Enqueue admin assets
	 */
	public function enqueue_admin_assets( $hook ): void {
		if ( $hook !== self::MENU_HOOK ) {
			return;
		}

		wp_enqueue_style(
			'google-distance-matrix-test-admin',
			plugins_url( 'assets/css/admin.css', __FILE__ ),
			[],
			'1.0.0'
		);

		wp_enqueue_script(
			'google-distance-matrix-test-admin',
			plugins_url( 'assets/js/admin.js', __FILE__ ),
			[ 'jquery' ],
			'1.0.0',
			true
		);
	}

	/**
	 * Process form submissions
	 */
	private function process_form_submissions(): array {
		$results = [
			'distance' => null
		];

		if ( isset( $_POST['submit_api_key'] ) ) {
			check_admin_referer( 'distance_matrix_api_key' );
			$api_key        = sanitize_text_field( $_POST['google_distance_matrix_api_key'] );
			$enable_cache   = isset( $_POST['google_distance_matrix_enable_cache'] );
			$cache_duration = (int) sanitize_text_field( $_POST['google_distance_matrix_cache_duration'] );

			update_option( 'google_distance_matrix_api_key', $api_key );
			update_option( 'google_distance_matrix_enable_cache', $enable_cache );
			update_option( 'google_distance_matrix_cache_duration', $cache_duration );

			$this->client = new Client( $api_key, $enable_cache, $cache_duration );
		}

		if ( ! $this->client ) {
			return $results;
		}

		// Process distance calculation test
		if ( isset( $_POST['submit_calculation'] ) ) {
			check_admin_referer( 'distance_matrix_test' );

			$origins      = sanitize_text_field( $_POST['origins'] );
			$destinations = sanitize_text_field( $_POST['destinations'] );

			$options = [
				'mode'     => sanitize_text_field( $_POST['travel_mode'] ?? 'driving' ),
				'units'    => sanitize_text_field( $_POST['units'] ?? 'metric' ),
				'language' => sanitize_text_field( $_POST['language'] ?? 'en' ),
			];

			if ( isset( $_POST['avoid'] ) ) {
				$options['avoid'] = sanitize_text_field( $_POST['avoid'] );
			}

			$results['distance'] = $this->client->calculate( $origins, $destinations, $options );
		}

		// Clear cache if requested
		if ( isset( $_POST['clear_cache'] ) ) {
			check_admin_referer( 'distance_matrix_test' );
			$this->client->clear_cache();
			add_settings_error(
				'distance_matrix_test',
				'cache_cleared',
				__( 'Cache cleared successfully', 'arraypress-google-distance-matrix' ),
				'success'
			);
		}

		return $results;
	}

	/**
	 * Render settings form
	 */
	private function render_settings_form(): void {
		?>
        <h2><?php _e( 'Settings', 'arraypress-google-distance-matrix' ); ?></h2>
        <form method="post" class="distance-form">
			<?php wp_nonce_field( 'distance_matrix_api_key' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="google_distance_matrix_api_key"><?php _e( 'API Key', 'arraypress-google-distance-matrix' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="google_distance_matrix_api_key"
                               id="google_distance_matrix_api_key"
                               class="regular-text"
                               value="<?php echo esc_attr( get_option( 'google_distance_matrix_api_key' ) ); ?>"
                               placeholder="<?php esc_attr_e( 'Enter your Google Distance Matrix API key...', 'arraypress-google-distance-matrix' ); ?>">
                        <p class="description">
							<?php _e( 'Your Google Distance Matrix API key. Required for making API requests.', 'arraypress-google-distance-matrix' ); ?>
                        </p>
                    </td>
                </tr>
                <!-- Cache settings similar to original -->
                <tr>
                    <th scope="row">
                        <label for="google_distance_matrix_enable_cache"><?php _e( 'Enable Cache', 'arraypress-google-distance-matrix' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="google_distance_matrix_enable_cache"
                                   id="google_distance_matrix_enable_cache"
                                   value="1" <?php checked( get_option( 'google_distance_matrix_enable_cache', true ) ); ?>>
							<?php _e( 'Cache calculation results', 'arraypress-google-distance-matrix' ); ?>
                        </label>
                        <p class="description">
							<?php _e( 'Caching results can help reduce API usage and improve performance.', 'arraypress-google-distance-matrix' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="google_distance_matrix_cache_duration"><?php _e( 'Cache Duration', 'arraypress-google-distance-matrix' ); ?></label>
                    </th>
                    <td>
                        <input type="number" name="google_distance_matrix_cache_duration"
                               id="google_distance_matrix_cache_duration"
                               class="regular-text"
                               value="<?php echo esc_attr( get_option( 'google_distance_matrix_cache_duration', 86400 ) ); ?>"
                               min="300" step="300">
                        <p class="description">
							<?php _e( 'How long to cache results in seconds. Default is 86400 (24 hours).', 'arraypress-google-distance-matrix' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
			<?php submit_button(
				empty( get_option( 'google_distance_matrix_api_key' ) )
					? __( 'Save Settings', 'arraypress-google-distance-matrix' )
					: __( 'Update Settings', 'arraypress-google-distance-matrix' ),
				'primary',
				'submit_api_key'
			); ?>
        </form>
		<?php
	}

	/**
	 * Render calculation form
	 */
	private function render_calculation_form(): void {
		?>
        <h2><?php _e( 'Distance Calculator', 'arraypress-google-distance-matrix' ); ?></h2>
        <form method="post" class="calculation-form">
			<?php wp_nonce_field( 'distance_matrix_test' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="origins"><?php _e( 'Origin', 'arraypress-google-distance-matrix' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="origins" id="origins" class="regular-text"
                               value="1600 Amphitheatre Parkway, Mountain View, CA"
                               placeholder="<?php esc_attr_e( 'Enter origin address...', 'arraypress-google-distance-matrix' ); ?>">
                        <p class="description">
							<?php _e( 'Enter a single origin address or multiple addresses separated by |', 'arraypress-google-distance-matrix' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="destinations"><?php _e( 'Destination', 'arraypress-google-distance-matrix' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="destinations" id="destinations" class="regular-text"
                               value="Googleplex, Mountain View, CA"
                               placeholder="<?php esc_attr_e( 'Enter destination address...', 'arraypress-google-distance-matrix' ); ?>">
                        <p class="description">
							<?php _e( 'Enter a single destination address or multiple addresses separated by |', 'arraypress-google-distance-matrix' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="travel_mode"><?php _e( 'Travel Mode', 'arraypress-google-distance-matrix' ); ?></label>
                    </th>
                    <td>
                        <select name="travel_mode" id="travel_mode">
                            <option value="driving"><?php _e( 'Driving', 'arraypress-google-distance-matrix' ); ?></option>
                            <option value="walking"><?php _e( 'Walking', 'arraypress-google-distance-matrix' ); ?></option>
                            <option value="bicycling"><?php _e( 'Bicycling', 'arraypress-google-distance-matrix' ); ?></option>
                            <option value="transit"><?php _e( 'Transit', 'arraypress-google-distance-matrix' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="units"><?php _e( 'Units', 'arraypress-google-distance-matrix' ); ?></label>
                    </th>
                    <td>
                        <select name="units" id="units">
                            <option value="metric"><?php _e( 'Metric (kilometers)', 'arraypress-google-distance-matrix' ); ?></option>
                            <option value="imperial"><?php _e( 'Imperial (miles)', 'arraypress-google-distance-matrix' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="avoid"><?php _e( 'Avoid', 'arraypress-google-distance-matrix' ); ?></label>
                    </th>
                    <td>
                        <select name="avoid" id="avoid">
                            <option value=""><?php _e( 'None', 'arraypress-google-distance-matrix' ); ?></option>
                            <option value="tolls"><?php _e( 'Tolls', 'arraypress-google-distance-matrix' ); ?></option>
                            <option value="highways"><?php _e( 'Highways', 'arraypress-google-distance-matrix' ); ?></option>
                            <option value="ferries"><?php _e( 'Ferries', 'arraypress-google-distance-matrix' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="language"><?php _e( 'Language', 'arraypress-google-distance-matrix' ); ?></label>
                    </th>
                    <td>
                        <select name="language" id="language" class="regular-text">
                            <option value="en">English</option>
                            <option value="fr">French</option>
                            <option value="de">German</option>
                            <option value="es">Spanish</option>
                            <option value="it">Italian</option>
                            <option value="pt">Portuguese</option>
                            <option value="nl">Dutch</option>
                            <option value="pl">Polish</option>
                            <option value="ja">Japanese</option>
                            <option value="ko">Korean</option>
                            <option value="zh">Chinese</option>
                        </select>
                    </td>
                </tr>
            </table>
			<?php submit_button( __( 'Calculate Distance', 'arraypress-google-distance-matrix' ), 'primary', 'submit_calculation' ); ?>
        </form>
		<?php
	}

	/**
	 * Render results details
	 */
	private function render_results_details( $result ): void {
		if ( is_wp_error( $result ) ) {
			?>
            <div class="notice notice-error">
                <p><?php echo esc_html( $result->get_error_message() ); ?></p>
            </div>
			<?php
			return;
		}
		?>
        <table class="widefat striped">
            <thead>
            <tr>
                <th><?php _e( 'Origin', 'arraypress-google-distance-matrix' ); ?></th>
                <th><?php _e( 'Destination', 'arraypress-google-distance-matrix' ); ?></th>
                <th><?php _e( 'Distance', 'arraypress-google-distance-matrix' ); ?></th>
                <th><?php _e( 'Duration', 'arraypress-google-distance-matrix' ); ?></th>
                <th><?php _e( 'Status', 'arraypress-google-distance-matrix' ); ?></th>
            </tr>
            </thead>
            <tbody>
			<?php
			$origins      = $result->get_origins();
			$destinations = $result->get_destinations();

			foreach ( $origins as $i => $origin ) {
				foreach ( $destinations as $j => $destination ) {
					$status = $result->get_element_status( $i, $j );
					if ( $status === 'OK' ) {
						?>
                        <tr>
                            <td><?php echo esc_html( $origin ); ?></td>
                            <td><?php echo esc_html( $destination ); ?></td>
                            <td><?php echo esc_html( $result->get_formatted_distance( $i, $j ) ); ?></td>
                            <td><?php echo esc_html( $result->get_formatted_duration( $i, $j ) ); ?></td>
                            <td><span class="status-ok"><?php _e( 'Success', 'arraypress-google-distance-matrix' ); ?></span>
                            </td>
                        </tr>
						<?php
					} else {
						?>
                        <tr>
                            <td><?php echo esc_html( $origin ); ?></td>
                            <td><?php echo esc_html( $destination ); ?></td>
                            <td colspan="2"><?php _e( 'Not Available', 'arraypress-google-distance-matrix' ); ?></td>
                            <td><span class="status-error"><?php echo esc_html( $status ); ?></span></td>
                        </tr>
						<?php
					}
				}
			}
			?>
            </tbody>
        </table>

		<?php if ( $result->is_complete() ): ?>
            <div class="distance-matrix-summary">
                <h3><?php _e( 'Summary', 'arraypress-google-distance-matrix' ); ?></h3>
                <p>
					<?php
					$total_distances = $result->get_all_distances();
					printf(
						__( 'Successfully calculated %d routes between %d origins and %d destinations.', 'arraypress-google-distance-matrix' ),
						count( $total_distances ),
						count( $origins ),
						count( $destinations )
					);
					?>
                </p>
            </div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render test page
	 */
	public function render_test_page(): void {
		$results = $this->process_form_submissions();
		?>
        <div class="wrap distance-matrix-test">
            <h1><?php _e( 'Google Distance Matrix API Test', 'arraypress-google-distance-matrix' ); ?></h1>

			<?php settings_errors( 'distance_matrix_test' ); ?>

			<?php if ( empty( get_option( 'google_distance_matrix_api_key' ) ) ): ?>
                <div class="notice notice-warning">
                    <p><?php _e( 'Please enter your Google Distance Matrix API key to begin testing.', 'arraypress-google-distance-matrix' ); ?></p>
                </div>
				<?php $this->render_settings_form(); ?>
			<?php else: ?>
                <div class="distance-matrix-test-container">
                    <div class="distance-matrix-test-section">
						<?php $this->render_calculation_form(); ?>

						<?php if ( $results['distance'] ): ?>
                            <h3><?php _e( 'Results', 'arraypress-google-distance-matrix' ); ?></h3>
							<?php $this->render_results_details( $results['distance'] ); ?>
						<?php endif; ?>
                    </div>
                </div>

                <div class="distance-matrix-test-section">
                    <h2><?php _e( 'Cache Management', 'arraypress-google-distance-matrix' ); ?></h2>
                    <form method="post" class="distance-form">
						<?php wp_nonce_field( 'distance_matrix_test' ); ?>
                        <p class="description">
							<?php _e( 'Clear the cached distance matrix results. This will force new API requests for subsequent calculations.', 'arraypress-google-distance-matrix' ); ?>
                        </p>
						<?php submit_button( __( 'Clear Cache', 'arraypress-google-distance-matrix' ), 'delete', 'clear_cache' ); ?>
                    </form>
                </div>

                <div class="distance-matrix-test-section">
					<?php $this->render_settings_form(); ?>
                </div>
			<?php endif; ?>
        </div>
		<?php
	}
}

new Plugin();