<?php

/**
 * Plugin Name: Bosta WooCommerce
 * Description: WooCommerce integration for Bosta eCommerce
 * Author: Bosta
 * Author URI: https://www.bosta.co/
 * Version: 4.5.7
 * Requires at least: 5.0
 * php version 7.0
 * Tested up to: 6.9
 * WC requires at least: 2.6
 * WC tested up to: 10.0.4
 * Text Domain: bosta-woocommerce
 * Domain Path: /languages
 *
 */

// Define plugin file constant
if (!defined('BOSTA_PLUGIN_FILE')) {
	define('BOSTA_PLUGIN_FILE', __FILE__);
}

// Include webhook loader
require_once plugin_dir_path(__FILE__) . 'includes/class-bosta-webhook-loader.php';

// Include webhook manager for activation hooks
require_once plugin_dir_path(__FILE__) . 'includes/class-bosta-webhook-manager.php';

// Include plugin hooks for activation/deactivation
require_once plugin_dir_path(__FILE__) . 'includes/class-bosta-plugin-hooks.php';

// Register activation and deactivation hooks immediately
Bosta_Plugin_Hooks::register_hooks();

// Include fulfillment classes
require_once plugin_dir_path(__FILE__) . 'includes/class-bosta-fulfillment.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-bosta-fulfillment-ui.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-bosta-fulfillment-cache.php';

// Include consignee ranking class
require_once plugin_dir_path(__FILE__) . 'includes/class-bosta-consignee-ranking.php';

// Include payment method handler
require_once plugin_dir_path(__FILE__) . 'includes/class-bosta-payment-method-handler.php';

// Include product sync class
require_once plugin_dir_path(__FILE__) . 'includes/class-bosta-product-sync.php';

// Include product info class
require_once plugin_dir_path(__FILE__) . 'includes/class-bosta-product-info.php';



// Initialize webhook system after WordPress is loaded
add_action('init', function () {
	Bosta_Webhook_Loader::init();
	Bosta_Payment_Method_Handler::init();
});

// Initialize fulfillment functionality
add_action('init', function () {
	Bosta_Fulfillment_Cache::init();
	Bosta_Fulfillment::init();
	Bosta_Fulfillment_UI::init();
	Bosta_Consignee_Ranking::init();
	Bosta_Product_Sync::init();

	// Handle form submissions for custom actions
	bosta_handle_form_submissions();
});





add_action('before_woocommerce_init', function () {
	if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
	}
});

include plugin_dir_path(__FILE__) . 'components/pickups/pickups.php';
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

add_action('admin_print_styles', 'bosta_stylesheet');
function bosta_stylesheet()
{
	$main_css_file = plugin_dir_path(__FILE__) . 'Css/main.css';
	$pickups_css_file = plugin_dir_path(__FILE__) . 'components/pickups/pickups.css';
	$flexship_css_file = plugin_dir_path(__FILE__) . 'components/settings/flexship/flexship.css';
	$fulfillment_css_file = plugin_dir_path(__FILE__) . 'Css/fulfillment.css';

	$main_css_version = filemtime($main_css_file);
	$pickups_css_version = filemtime($pickups_css_file);
	$flexship_css_version = filemtime($flexship_css_file);
	$fulfillment_css_version = filemtime($fulfillment_css_file);

	wp_enqueue_style(
		'myCSS',
		plugins_url('/Css/main.css', __FILE__),
		array(),
		$main_css_version
	);
	wp_enqueue_style(
		'pickupsCSS',
		plugins_url('components/pickups/pickups.css', __FILE__),
		array(),
		$pickups_css_version
	);
	wp_enqueue_style(
		'flexshipCSS',
		plugins_url('components/settings/flexship/flexship.css', __FILE__),
		array(),
		$flexship_css_version
	);
	wp_enqueue_style(
		'fulfillmentCSS',
		plugins_url('Css/fulfillment.css', __FILE__),
		array(),
		$fulfillment_css_version
	);
}

const BOSTA_ENV_URL_V0 = 'https://app.bosta.co/api/v0';
const BOSTA_ENV_URL_V2 = 'https://app.bosta.co/api/v2';
const PLUGIN_VERSION = '4.5.7';
const bosta_cache_duration = 86400;
const bosta_country_id_duration = 604800;
const BOSTA_EGYPT_COUNTRY_ID = "60e4482c7cb7d4bc4849c4d5";
const FLEX_SHIPPING_DEFAULT_VALUE = 75;
//region Bosta Utils Functions

function bosta_send_api_request($method, $url, $APIKey = null, $body = null)
{
	$args = [
		'timeout' => 300,
		'method'  => strtoupper($method),
		'headers' => [
			'Content-Type'     => 'application/json',
			'X-Requested-By'   => 'WooCommerce',
			'X-Plugin-Version' => PLUGIN_VERSION,
		],
	];

	if (!empty($APIKey)) {
		$args['headers']['authorization'] = $APIKey;
	}

	if ($body) {
		$args['body'] = json_encode($body);
	}

	$response = wp_remote_request($url, $args);
	if (is_wp_error($response)) {
		return [
			'success' => false,
			'error'   => $response->get_error_message(),
		];
	}
	$response_body = wp_remote_retrieve_body($response);
	$response_code = wp_remote_retrieve_response_code($response);

	if ($response_code < 200 || $response_code >= 300 || empty($response_body)) {
		$decoded_body = json_decode($response_body, true);

		if (isset($decoded_body['message'])) {
			$error_message = $decoded_body['message'];
		} elseif (isset($decoded_body[0]['message'])) {
			$error_message = $decoded_body[0]['message'];
		} else {
			$error_message = 'Unknown error';
		}

		$error_messages = [
			'success' => false,
			'error'   => $error_message,
		];

		if (isset($decoded_body['data'])) {
			$error_messages['data'] = $decoded_body['data'];
		}

		return $error_messages;
	}

	return [
		'success' => true,
		'code'    => $response_code,
		'body'    => json_decode($response_body, true),
	];
}

function bosta_validate_api_key($bostaApiKey)
{
	if ($bostaApiKey == null) {
		return false;
	}

	$url = BOSTA_ENV_URL_V0 . '/businesses/' . esc_html($bostaApiKey) . '/info';
	$response = bosta_send_api_request('GET', $url);

	if (!$response['success']) {
		return false;
	}

	return true;
}

function bosta_get_api_key()
{
	$apikey = get_option('woocommerce_bosta_settings', [])['APIKey'] ?? null;
	if (isset($apikey)) {
		return sanitize_text_field($apikey);
	}
}

function bosta_check_disable_bosta_zoning_checkbox()
{
	$bosta_settings = get_option('woocommerce_bosta_settings', []);
	$disable_bosta_zoning = $bosta_settings['DisableBostaZoning'] ?? null;
	return $disable_bosta_zoning === 'yes';
}

function bosta_get_country_id()
{
	return BOSTA_EGYPT_COUNTRY_ID;
}

function bosta_check_area_coverage($area)
{
	return isset($area['dropOffAvailability']) && $area['dropOffAvailability'] == true;
}

function bosta_get_zoning()
{
	$country_id = bosta_get_country_id();
	$bosta_zoning_key_cache = 'bosta_zoning';
	$bosta_zoning = get_transient($bosta_zoning_key_cache);

	if (!$bosta_zoning) {
		$url = BOSTA_ENV_URL_V2 .  '/cities/getAllDistricts?countryId=' . esc_html($country_id);
		$response = bosta_send_api_request('GET', $url);
		if (!$response['success']) {
			return array();
		}
		// Check if response body is valid and contains expected structure
		if (!$response['body'] || !is_array($response['body']) || !isset($response['body']['data'])) {
			return array();
		}
		$bosta_zoning = $response['body']['data'];
		set_transient($bosta_zoning_key_cache, $bosta_zoning, bosta_cache_duration);
	}

	return $bosta_zoning ? $bosta_zoning : [];
}

/**
 * Get the current language code (either 'en' or 'ar')
 * Checks WPML first, then WordPress native locale
 * 
 * @return string 'en' for English, 'ar' for Arabic
 */
function bosta_get_current_language()
{
	// Check WPML first if available
	if (defined('ICL_SITEPRESS_VERSION')) {
		$current_language = apply_filters('wpml_current_language', null);
		if ($current_language === 'en') {
			return 'en';
		}
		if ($current_language === 'ar') {
			return 'ar';
		}
	}

	// Fall back to WordPress native locale
	$locale = get_locale();
	if (function_exists('get_user_locale') && is_admin()) {
		$locale = get_user_locale();
	}

	// Check if locale starts with 'en' (en, en_US, en_GB, etc.)
	if (strpos($locale, 'en') === 0) {
		return 'en';
	}

	// Check if locale starts with 'ar' (ar, ar_EG, ar_SA, etc.)
	if (strpos($locale, 'ar') === 0) {
		return 'ar';
	}

	// Default to Arabic for backward compatibility
	return 'ar';
}

function bosta_get_cities()
{
	$bosta_zoning = bosta_get_zoning();
	$bosta_cities = [];

	$current_language = bosta_get_current_language();
	$is_arabic = $current_language === 'ar';

	foreach ($bosta_zoning as $city) {
		if (!isset($city['cityOtherName']) || !bosta_check_area_coverage($city)) {
			continue;
		}
		$city_code = $city['cityCode'];
		$city_name = $is_arabic ? $city['cityOtherName'] : $city['cityName'];
		$bosta_cities[$city_code] = $city_name;
	}
	return $bosta_cities;
}

function bosta_get_city_areas()
{
	$bosta_zoning = bosta_get_zoning();

	$current_language = bosta_get_current_language();
	$is_arabic = $current_language === 'ar';

	$bosta_city_areas_cache_key = 'bosta_city_areas' . '_' . $current_language;
	$bosta_city_areas = get_transient($bosta_city_areas_cache_key);
	if (!$bosta_city_areas) {
		$bosta_city_areas = [];
		foreach ($bosta_zoning as $city) {
			$city_code = $city['cityCode'];
			$city_areas = '';
			foreach ($city['districts'] as $district) {
				// filter not covered districts
				if (empty($district['dropOffAvailability'])) {
					continue;
				}
				$zone_name = $is_arabic ? $district['zoneOtherName'] : $district['zoneName'];
				$district_name = $is_arabic ? $district['districtOtherName'] : $district['districtName'];

				if ($zone_name === $district_name) {
					$area = $district_name;
				} else {
					$area = $zone_name . ' - ' . $district_name;
				}

				$city_areas .= sprintf(
					'<option value="%s">%s</option>',
					esc_attr($district['districtId']),
					esc_html($area)
				);
			}
			$bosta_city_areas[$city_code] = $city_areas;
		}
		set_transient($bosta_city_areas_cache_key, $bosta_city_areas, bosta_cache_duration);
	}
	return $bosta_city_areas ? $bosta_city_areas : [];
}

function bosta_format_failed_order_message($error_message, $order_id = null)
{
	$formatted_error_message = '<p>' . ($order_id ? '<strong>Order ID:</strong> ' . esc_html($order_id) . '<br>' : '') .
		'<strong>Reason:</strong> ' . esc_html(print_r($error_message, true)) . '</p>';
	bosta_set_transient('bosta_failed_orders', $formatted_error_message);
}

function bosta_format_date($date)
{
	try {
		$pos = strrpos($date, '(');
		$clean_date = $pos !== false ? substr($date, 0, $pos) : $date;
		$datetime = new DateTime($clean_date, new DateTimeZone('UTC'));
		$datetime->setTimezone(new DateTimeZone('Africa/Cairo'));
		return $datetime->format('l, d/m/Y h:ia');
	} catch (Exception $e) {
		error_log('Error parsing date: ' . $e->getMessage());
		return null;
	}
}

function bosta_set_transient($key, $value, $expiration = HOUR_IN_SECONDS)
{
	$existing_value = get_transient($key) ?: '';
	$updated_value = $existing_value . $value;
	set_transient($key, $updated_value, $expiration);
}

function bosta_render_pdf($pdf_data)
{
	header('Content-Type: application/pdf');
	header('Cache-Control: public, must-revalidate, max-age=0');
	header('Pragma: public');
	ob_clean();
	flush();
	echo $pdf_data;
	exit;
}

function bosta_redirect_to_settings_page()
{
	$redirect_url = admin_url('admin.php?') . 'page=wc-settings&tab=shipping&section=bosta';
	wp_redirect($redirect_url);
	exit;
}

function bosta_redirect_to_orders_page()
{
	$redirect_url = admin_url('edit.php?') . 'post_type=shop_order&paged=1';
	wp_redirect($redirect_url);
}

function bosta_redirect_to_dashboard_page()
{
	$redirect_url = 'https://bosta.co/tracking-shipments';
	wp_redirect($redirect_url);
}

function bosta_redirect_to_documentation_page()
{
	$redirect_url = 'https://docs.bosta.co/docs/plugins-and-sdks/integrate-with-woocommerce';
	wp_redirect($redirect_url);
}

function bosta_inventory_page()
{
	// Check if user has permissions
	if (!current_user_can('manage_woocommerce')) {
		wp_die(__('You do not have sufficient permissions to access this page.', 'bosta'));
	}

	// Handle form submissions
	if (isset($_POST['bosta_inventory_action']) && wp_verify_nonce($_POST['bosta_inventory_nonce'], 'bosta_inventory_action')) {
		$action = sanitize_text_field($_POST['bosta_inventory_action']);

		switch ($action) {
			case 'sync_quantity':
				// Handle quantity sync
				$result = Bosta_Fulfillment::sync_quantity();
				if ($result['success']) {
					// Store success message in a transient for display
					set_transient('bosta_inventory_success', $result['message'], 60);
				} else {
					// Store error message in a transient for display
					set_transient('bosta_inventory_error', $result['message'], 60);
				}
				// Redirect to prevent form resubmission
				wp_redirect(admin_url('admin.php?page=bosta-woocommerce-inventory'));
				exit;
				break;
		}
	}

	// Get current settings
	$api_key = bosta_get_api_key();
	$is_fulfillment_enabled = Bosta_Fulfillment_Cache::is_fulfillment_enabled();
	$cache_info = Bosta_Fulfillment_Cache::get_cache_info();

?>
	<div class="wrap">
		<h1><?php _e('Bosta Inventory Management', 'bosta'); ?></h1>

		<?php
		// Display success message
		$success_message = get_transient('bosta_inventory_success');
		if ($success_message) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($success_message) . '</p></div>';
			delete_transient('bosta_inventory_success');
		}

		// Display error message
		$error_message = get_transient('bosta_inventory_error');
		if ($error_message) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error_message) . '</p></div>';
			delete_transient('bosta_inventory_error');
		}
		?>

		<?php if (empty($api_key)): ?>
			<div class="notice notice-error">
				<p><?php _e('API Key is required to manage inventory. Please configure your API key in the Bosta settings.', 'bosta'); ?></p>
				<p><a href="<?php echo admin_url('admin.php?page=wc-settings&tab=shipping&section=bosta'); ?>" class="button button-primary"><?php _e('Go to Settings', 'bosta'); ?></a></p>
			</div>
		<?php else: ?>
			<div class="bosta-inventory-container">
				<div class="bosta-inventory-section">


					<?php if ($is_fulfillment_enabled): ?>
						<div class="bosta-inventory-actions">
							<h3><?php _e('Inventory Actions', 'bosta'); ?></h3>

							<form method="post" action="">
								<?php wp_nonce_field('bosta_inventory_action', 'bosta_inventory_nonce'); ?>

								<div class="bosta-action-buttons">
									<button type="submit" name="bosta_inventory_action" value="sync_quantity" class="button button-primary">
										<?php _e('Sync Quantity', 'bosta'); ?>
									</button>
								</div>

								<div class="bosta-action-descriptions">
									<p><strong><?php _e('Sync Quantity:', 'bosta'); ?></strong> <?php _e('Sync Quantity will force sync bosta quantity', 'bosta'); ?></p>
								</div>
							</form>
						</div>
					<?php else: ?>
						<div class="notice notice-warning">
							<p><?php _e('Fulfillment features are not available for your account. Please contact Bosta support to enable fulfillment services.', 'bosta'); ?></p>
						</div>

						<!-- Temporary fallback for testing -->
						<div style="background: #fff3cd; padding: 10px; margin: 10px 0; border-radius: 4px; border: 1px solid #ffeaa7;">
							<strong><?php _e('Testing Mode:', 'bosta'); ?></strong> <?php _e('Buttons shown for testing purposes', 'bosta'); ?>

							<form method="post" action="">
								<?php wp_nonce_field('bosta_inventory_action', 'bosta_inventory_nonce'); ?>

								<div class="bosta-action-buttons">
									<button type="submit" name="bosta_inventory_action" value="sync_quantity" class="button button-primary">
										<?php _e('Sync Quantity', 'bosta'); ?>
									</button>
								</div>

								<div class="bosta-action-descriptions">
									<p><strong><?php _e('Sync Quantity:', 'bosta'); ?></strong> <?php _e('Sync Quantity will force sync bosta quantity', 'bosta'); ?></p>
								</div>
							</form>
						</div>
					<?php endif; ?>

					<div class="bosta-cache-info">
						<h3><?php _e('Cache Information', 'bosta'); ?></h3>
						<p class="description">
							<strong><?php _e('Cache Status:', 'bosta'); ?></strong>
							<?php
							if ($cache_info['transient_exists']) {
								$expiry_time = $cache_info['transient_expiry'];
								$time_remaining = $expiry_time - time();
								if ($time_remaining > 0) {
									$minutes = floor($time_remaining / 60);
									printf(__('Cached (expires in %d minutes)', 'bosta'), $minutes);
								} else {
									_e('Cache expired', 'bosta');
								}
							} else {
								_e('Not cached', 'bosta');
							}
							?>
							<button type="button" id="refresh-fulfillment-status" class="button button-small" style="margin-left: 10px;">
								<?php _e('Refresh Status', 'bosta'); ?>
							</button>
						</p>
					</div>
				</div>
			</div>

			<style>
				.bosta-inventory-container {
					max-width: 800px;
				}

				.bosta-inventory-section {
					background: #fff;
					padding: 20px;
					border: 1px solid #ccd0d4;
					border-radius: 4px;
					margin-top: 20px;
				}

				.bosta-action-buttons {
					margin: 20px 0;
				}

				.bosta-action-buttons .button {
					margin-right: 10px;
				}

				.bosta-action-descriptions {
					margin-top: 15px;
				}

				.bosta-action-descriptions p {
					margin-bottom: 10px;
					color: #666;
				}

				.bosta-cache-info {
					margin-top: 30px;
					padding-top: 20px;
					border-top: 1px solid #eee;
				}
			</style>

			<script type="text/javascript">
				jQuery(document).ready(function($) {
					// Refresh fulfillment status button
					$('#refresh-fulfillment-status').on('click', function() {
						var button = $(this);
						var originalText = button.text();

						button.prop('disabled', true).text('<?php _e('Refreshing...', 'bosta'); ?>');

						$.ajax({
							url: ajaxurl,
							type: 'POST',
							data: {
								action: 'bosta_refresh_fulfillment_status',
								nonce: '<?php echo wp_create_nonce('bosta_refresh_fulfillment_status'); ?>'
							},
							success: function(response) {
								if (response.success) {
									// Reload the page to reflect the new status
									location.reload();
								} else {
									alert('<?php _e('Failed to refresh fulfillment status:', 'bosta'); ?> ' + response.data);
								}
							},
							error: function() {
								alert('<?php _e('An error occurred while refreshing fulfillment status.', 'bosta'); ?>');
							},
							complete: function() {
								button.prop('disabled', false).text(originalText);
							}
						});
					});
				});
			</script>
		<?php endif; ?>
	</div>
	<?php
}

function bosta_get_order_by_metadata($meta_key, $meta_value)
{
	try {
		$page_num = isset($_GET['page_num']) ? $_GET['page_num'] : 1;
		$query = new WC_Order_Query([
			'limit' => 1,
			'meta_key' => $meta_key,
			'meta_value' => $meta_value,
			'paged' => $page_num
		]);
		$orders = $query->get_orders();
		return !empty($orders) ? $orders[0] : null;
	} catch (Exception $e) {
		error_log('Bosta Plugin: Failed to get order by metadata. Meta Key: ' . $meta_key . ', Meta Value: ' . $meta_value . '. Error: ' . $e->getMessage());

		$message = 'Bosta Plugin: Failed to get order by metadata. Meta Key: ' . esc_html($meta_key) . ', Meta Value: ' . esc_html($meta_value) . '. Error: ' . esc_html($e->getMessage());
		bosta_set_transient('bosta_errors', "<p>{$message}</p>");
		return null;
	}
}

function bosta_update_order_metadata($order, $bosta_data)
{
	try {
		$is_order_delivered = $bosta_data['state']['code'] == 45;
		$deliveried_at = $is_order_delivered ? bosta_format_date($bosta_data['state']['delivering']['time']) : null;
		$meta_mapping = [
			'bosta_delivery_id'     => $bosta_data['_id'] ?? null,
			'bosta_status'          => $bosta_data['state']['value'] ?? null,
			'bosta_tracking_number' => $bosta_data['trackingNumber'] ?? null,
			'bosta_customer_phone'  => $bosta_data['receiver']['phone'] ?? null,
			'bosta_delivery_date' => $deliveried_at
		];

		foreach ($meta_mapping as $meta_key => $meta_value) {
			if (!empty($meta_value)) {
				$order->update_meta_data($meta_key, $meta_value);
			}
		}

		// Check if this is a fulfillment order (FXF_SEND type)
		if (isset($bosta_data['type']) && $bosta_data['type'] === 'FXF_SEND') {
			$order->update_meta_data('bosta_is_fulfillment', true);
		}

		// Clean up sync-related metadata when order is successfully synced
		// This ensures orders that previously failed auto-sync are no longer filtered as failed
		if (!empty($bosta_data['trackingNumber'])) {
			$order->update_meta_data('bosta_sync_status', 'success');
			$order->delete_meta_data('bosta_sync_error');
		}

		$order->save();
	} catch (Exception $e) {
		$order_id = is_object($order) && method_exists($order, 'get_id') ? $order->get_id() : '';
		error_log('Bosta Plugin: Failed to update order metadata. Order ID: ' . $order_id . '. Error: ' . $e->getMessage());

		$message = 'Bosta Plugin: Failed to update order metadata. Order ID: ' . esc_html($order_id) . '. Error: ' . esc_html($e->getMessage());
		bosta_set_transient('bosta_errors', "<p>{$message}</p>");
	}
}

function bosta_delete_order_metadata($order)
{
	try {
		$meta_keys = [
			'bosta_delivery_id',
			'bosta_status',
			'bosta_tracking_number',
			'bosta_customer_phone',
			'bosta_delivery_date',
			'bosta_sync_status',
			'bosta_sync_error',
			'bosta_is_fulfillment'
		];

		foreach ($meta_keys as $meta_key) {
			$order->delete_meta_data($meta_key);
		}

		$order->save();
	} catch (Exception $e) {
		$order_id = is_object($order) && method_exists($order, 'get_id') ? $order->get_id() : '';
		error_log('Bosta Plugin: Failed to delete order metadata. Order ID: ' . $order_id . '. Error: ' . $e->getMessage());

		$message = 'Bosta Plugin: Failed to delete order metadata. Order ID: ' . esc_html($order_id) . '. Error: ' . esc_html($e->getMessage());
		bosta_set_transient('bosta_errors', "<p>{$message}</p>");
	}
}
//endregion

//region Bosta Customize City Fields

add_filter('woocommerce_states', 'bosta_custom_woocommerce_states');
function bosta_custom_woocommerce_states($states)
{
	$bosta_cities = bosta_get_cities();
	$states['EG'] = $bosta_cities;
	return $states;
}

add_filter('woocommerce_checkout_fields', 'bosta_add_dynamic_area_dropdown_to_checkout', 20);
function bosta_add_dynamic_area_dropdown_to_checkout($fields)
{
	if (!bosta_check_disable_bosta_zoning_checkbox()) {
		$field_priority = 50;
		if (isset($fields['billing']['billing_state']['priority'])) {
			$field_priority = $fields['billing']['billing_state']['priority'] + 1;
		}

		$fields['billing']['billing_area'] = array(
			'type'     => 'select',
			'label'    => __('Area', 'woocommerce'),
			'required' => false,
			'options'  => array(
				'' => __('حدد خيارا...', 'woocommerce'),
			),
			'input_class' => array(
				'wc-enhanced-select',
			),
			'custom_attributes' => array(
				'data-selected' => WC()->checkout()->get_value('billing_area'),
			),
			'priority' => $field_priority,
		);

		wc_enqueue_js("
    jQuery(document).ready(function($) {
        $(':input.wc-enhanced-select').filter(':not(.enhanced)').each(function() {
            var select2_args = {
                minimumResultsForSearch: 5,
                language: {
                       noResults: function () {
                       return 'لم يتم العثور على نتائج';
                    }
                }
            };
            $(this).select2(select2_args).addClass('enhanced');
        });

		console.log('Bosta Plugin: Adding dynamic area dropdown to checkout');
		
		/* Fix not selecting the area dropdown on page reload if the state is already selected and has a saved area value */
        // $('select.wc-enhanced-select').val('').trigger('change');
		// $('#billing_state').val('').trigger('change');
		/* populate area dropdown using saved state */
    	$('#billing_state').trigger('change');
    });	
	");
	}

	return $fields;
}

add_action('woocommerce_admin_order_data_after_billing_address', 'bosta_add_area_dropdown_admin_order', 10, 1);
function bosta_add_area_dropdown_admin_order($order)
{
	if (!bosta_check_disable_bosta_zoning_checkbox()) {


		$current_state = get_post_meta($order->get_id(), '_billing_state', true);
		$current_area = get_post_meta($order->get_id(), '_billing_area', true);

		$bosta_city_areas = bosta_get_city_areas();
		$areas_options = '<option value="">' . __('Select an area...', 'woocommerce') . '</option>';

		if (!empty($bosta_city_areas[$current_state])) {
			$areas = explode('</option>', $bosta_city_areas[$current_state]);
			foreach ($areas as $area_option) {
				if (strpos($area_option, 'value="' . esc_attr($current_area) . '"') !== false) {
					$area_option = str_replace('<option', '<option selected="selected"', $area_option);
				}
				$areas_options .= $area_option . '</option>';
			}
		}

		echo '<p class="form-field" style="width:100%">' .
			'<label for="billing_area">' . __('Area', 'woocommerce') . '</label>' .
			'<select name="billing_area" id="billing_area" class="wc-enhanced-select" >' .
			$areas_options .
			'</select>' .
			'</p>';
	}
}

add_action('wp_footer', 'bosta_enqueue_dynamic_area_dropdown_script');
add_action('admin_footer', 'bosta_enqueue_dynamic_area_dropdown_script');
function bosta_enqueue_dynamic_area_dropdown_script()
{
	if (!bosta_check_disable_bosta_zoning_checkbox()) {
		$bosta_city_areas = bosta_get_city_areas();
		$city_areas_js = json_encode($bosta_city_areas);

		$is_valid_screen = false;
		$is_checkout = is_checkout();
		$is_admin = is_admin();
		if ($is_checkout) {
			$is_valid_screen = true;
		}
		if ($is_admin) {
			$current_screen = get_current_screen();
			if ($current_screen && isset($current_screen->post_type) && $current_screen->post_type === 'shop_order') {
				$is_valid_screen = true;
			}
		}
		if (!$is_valid_screen) {
			return;
		}

	?>
		<script type="text/javascript">
			jQuery(function($) {
				function updateAreaDropdown(stateSelector, areaSelector, cityAreas) {
					$(document).on('change', stateSelector, function() {
						var selectedState = $(this).val();
						var areaDropdown = $(areaSelector);

						var savedArea = areaDropdown.val() || areaDropdown.attr('data-selected');

						areaDropdown.empty();
						areaDropdown.append($('<option></option>').attr('value', '').text('Select an option...'));

						if (selectedState && cityAreas[selectedState]) {
							var areas = cityAreas[selectedState];
							areaDropdown.append(areas);
						} else {
							areaDropdown.append($('<option></option>').attr('value', '').text('No areas available'));
						}

						if (savedArea) {
							areaDropdown.val(savedArea);
						}

						areaDropdown.trigger('change');
					});
				}

				var cityAreasJs = <?php echo $city_areas_js; ?>;

				<?php if ($is_checkout): ?>
					updateAreaDropdown('#billing_state', '#billing_area', cityAreasJs);
				<?php endif; ?>

				<?php if ($is_admin): ?>
					updateAreaDropdown('#_billing_state', '#billing_area', cityAreasJs);
				<?php endif; ?>
			});
		</script>
	<?php
	}
}

add_action('woocommerce_checkout_update_order_meta', 'bosta_save_billing_area_to_order_metadata', 10, 2);
add_action('woocommerce_process_shop_order_meta', 'bosta_save_billing_area_to_order_metadata', 10, 2);
function bosta_save_billing_area_to_order_metadata($order_id, $posted_data)
{
	if (bosta_check_disable_bosta_zoning_checkbox()) {
		return;
	}

	if (isset($_POST['billing_area'])) {
		$billing_area = sanitize_text_field($_POST['billing_area']);
		$order = wc_get_order($order_id);
		$order->update_meta_data('_billing_area', $billing_area);
		$order->save();
	}
}

//endregion

//region Bosta Notice Messages

add_action('admin_notices', 'bosta_woocommerce_notice');
function bosta_woocommerce_notice()
{
	//check if woocommerce installed and activated
	if (!class_exists('WooCommerce')) {
		echo
		'<div class="error notice-warning text-bold">
              <p>
				<img src="' . esc_url(plugins_url('assets/images/bosta.svg', __FILE__)) . '" alt="Bosta" style="height:13px; width:25px;">
				<strong>' . sprintf(esc_html__('Bosta requires WooCommerce to be installed and active. You can download %s here.'), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>') . '</strong>
              </p>
			</div>';
	}

	$success_count = get_transient('bosta_success_count');
	if ($success_count) {
		bosta_render_success_notice($success_count);
		delete_transient('bosta_success_count');
	}

	$bosta_success = get_transient('bosta_success');
	if ($bosta_success) {
		bosta_render_success_message($bosta_success);
		delete_transient('bosta_success');
	}

	$bosta_errors = get_transient('bosta_errors');
	if ($bosta_errors) {
		bosta_render_error_notice($bosta_errors);
		delete_transient('bosta_errors');
	}

	$failed_orders = get_transient('bosta_failed_orders');
	if ($failed_orders) {
		bosta_render_failed_orders_notice($failed_orders);
		delete_transient('bosta_failed_orders');
	}
}

function bosta_render_success_notice($success_count)
{
	if ($success_count) {
		echo '<div class="notice notice-success is-dismissible">';
		echo '<p>' . sprintf(esc_html__('%d orders successfully synced at Bosta.'), $success_count) . '</p>';
		echo '</div>';
	}
}

function bosta_render_success_message($message)
{
	echo '<div class="notice notice-success is-dismissible">';
	echo '<p>' . wp_kses_post($message) . '</p>';
	echo '</div>';
}

function bosta_render_error_notice($error_message)
{
	echo '<div class="notice notice-error is-dismissible" style="padding: 10px;">';
	echo $error_message;
	echo '</div>';
}

function bosta_render_failed_orders_notice($failed_orders)
{
	echo '<div class="notice notice-error is-dismissible">';
	echo '<p>Some orders failed to be synced at Bosta. <span class="toggle-details" style="cursor: pointer; color: red;">&#9660;</span></p>';
	echo '<div class="details hidden" style="max-height: 150px; overflow-y: auto; margin: 10px;">';
	echo $failed_orders;
	echo '</div>';
	echo '</div>';
	?>
	<script>
		document.addEventListener('DOMContentLoaded', () => {
			document.querySelector('.toggle-details').addEventListener('click', function() {
				const detailsSection = document.querySelector('.details');
				detailsSection.classList.toggle('hidden');
				this.innerHTML = detailsSection.classList.contains('hidden') ? '&#9660;' : '&#9650;';
			});
		});
	</script>
<?php
}

//endregion

//region Bosta Customize Orders Table 

add_filter('manage_edit-shop_order_columns', 'bosta_wco_add_columns');
add_filter('manage_woocommerce_page_wc-orders_columns', 'bosta_wco_add_columns');
function bosta_wco_add_columns($columns)
{
	$order_total = $columns['order_total'];
	$order_date = $columns['order_date'];
	$order_status = $columns['order_status'];

	unset($columns['order_date']);
	unset($columns['order_status']);
	unset($columns['order_total']);

	$columns["bosta_customer_info"] = __("Customer Info", "themeprefix");
	$columns["bosta_tracking_number"] = __("Bosta Tracking Number", "themeprefix");
	$columns['order_date'] = $order_date;
	$columns['order_status'] = $order_status;
	$columns["bosta_status"] = __("Bosta Status", "themeprefix");
	$columns["bosta_delivery_date"] = __("Delivered at", "themeprefix");
	$columns["bosta_sync_status"] = __("Bosta Sync Status", "themeprefix");
	$columns['order_total'] = $order_total;

	return $columns;
}

add_action('manage_shop_order_posts_custom_column', 'bosta_wco_column_cb_data', 10, 2);
add_action('manage_woocommerce_page_wc-orders_custom_column', 'bosta_wco_column_cb_data', 10, 2);
function bosta_wco_column_cb_data($colName, $orderId)
{
	$order = wc_get_order($orderId);

	$status = $order->get_meta('bosta_status', true);
	$trackingNumber = $order->get_meta('bosta_tracking_number', true);
	$deliveryDate = $order->get_meta('bosta_delivery_date', true);
	$customerPhone = $order->get_meta('bosta_customer_phone', true);
	$syncStatus = $order->get_meta('bosta_sync_status', true);
	$syncError = $order->get_meta('bosta_sync_error', true);

	if ($colName == 'bosta_status') {
		echo !empty($status) ? esc_html($status) : "---";
	}

	if ($colName == 'bosta_tracking_number') {
		echo !empty($trackingNumber) ? esc_html($trackingNumber) : "---";
	}

	if ($colName == 'bosta_delivery_date') {
		echo !empty($deliveryDate) ? $deliveryDate : "---";
	}

	if ($colName == 'bosta_customer_info') {
		echo Bosta_Consignee_Ranking::format_customer_info_display($order);
	}

	if ($colName == 'bosta_sync_status') {
		if (!empty($trackingNumber)) {
			// Order is synced successfully
			echo '<div class="bosta-sync-status-wrapper">';
			echo '<span class="bosta-status-badge bosta-status-success" data-tooltip="Successfully synced with Bosta">';
			echo '<span class="bosta-status-text">Synced</span>';
			echo '</span>';
			echo '</div>';
		} elseif ($syncStatus === 'failed') {
			// Order sync failed
			$tooltip = !empty($syncError) ? esc_attr($syncError) : 'Sync failed';
			echo '<div class="bosta-sync-status-wrapper">';
			echo '<span class="bosta-status-badge bosta-status-failed" data-tooltip="' . $tooltip . '">';
			echo '<span class="bosta-status-text">Failed</span>';
			echo '</span>';
			echo '</div>';
		} elseif ($syncStatus === 'processing') {
			// Order is being processed
			echo '<div class="bosta-sync-status-wrapper">';
			echo '<span class="bosta-status-badge bosta-status-processing" data-tooltip="Syncing with Bosta...">';
			echo '<span class="bosta-status-text">Processing</span>';
			echo '</span>';
			echo '</div>';
		} else {
			// Not synced yet
			echo '<div class="bosta-sync-status-wrapper">';
			echo '<span class="bosta-status-badge bosta-status-none" data-tooltip="Not synced yet">';
			echo '<span class="bosta-status-text">Not Synced</span>';
			echo '</span>';
			echo '</div>';
		}
	}
}

//endregion

//region Bosta Bulk Actions

add_filter('bulk_actions-edit-shop_order', 'bosta_sync_cash_collection_orders', 20);
add_filter('bulk_actions-woocommerce_page_wc-orders', 'bosta_sync_cash_collection_orders', 20);
function bosta_sync_cash_collection_orders($actions)
{
	$actions['sync_cash_collection_orders'] = __('Send Cash Collection Orders', 'woocommerce');
	return $actions;
}

add_filter('bulk_actions-edit-shop_order', 'bosta_sync', 20);
add_filter('bulk_actions-woocommerce_page_wc-orders', 'bosta_sync', 20);
function bosta_sync($actions)
{
	$actions['sync_to_bosta'] = __('Send To Bosta', 'woocommerce');
	return $actions;
}

// Add fulfillment modal for bulk actions - Moved to Bosta_Fulfillment_UI class

add_filter('bulk_actions-edit-shop_order', 'bosta_print_awb', 20);
add_filter('bulk_actions-woocommerce_page_wc-orders', 'bosta_print_awb', 20);
function bosta_print_awb($actions)
{
	$actions['print_bosta_awb'] = __('Print Bosta AirWaybill', 'woocommerce');
	return $actions;
}


add_filter('handle_bulk_actions-edit-shop_order', 'bosta_handle_bulk_action', 10, 3);
add_filter('handle_bulk_actions-woocommerce_page_wc-orders', 'bosta_handle_bulk_action', 10, 3);
function bosta_handle_bulk_action($redirect_to, $action, $order_ids, $fulfillment_type = null)
{
	// Get fulfillment type from POST or GET if available (fallback to parameter)
	if ($fulfillment_type === null) {
		$fulfillment_type = isset($_POST['fulfillment_type']) ? sanitize_text_field($_POST['fulfillment_type']) : null;
		if ($fulfillment_type === null) {
			$fulfillment_type = isset($_GET['fulfillment_type']) ? sanitize_text_field($_GET['fulfillment_type']) : null;
		}
	}



	$order_action = bosta_handle_order_action($action, $fulfillment_type);
	if (!$order_action) {
		return;
	}



	$APIKey = bosta_get_api_key();
	if (empty($APIKey)) {
		$message = 'API Key is required to be able to sync with Bosta';
		bosta_set_transient('bosta_errors', "<p>{$message}</p>");
		bosta_redirect_to_settings_page();
		return;
	}

	$orders = wc_get_orders([
		'limit'    => -1,
		'post__in' => $order_ids,
	]);

	if (!empty($orders)) {
		switch ($order_action['actionType']) {
			case 'sync_orders':
				bosta_handle_send_orders_bulk_action([
					'APIKey'       => $APIKey,
					'redirect_to'  => $redirect_to,
					'orders'       => $orders,
					'order_action' => $order_action,
				]);
				break;

			case 'auto_sync_orders':
				bosta_handle_auto_sync_bulk_action([
					'APIKey' => $APIKey,
					'redirect_to' => $redirect_to,
				]);
				break;

			case 'print_awbs':
				bosta_handle_print_awbs_bulk_action([
					'APIKey' => $APIKey,
					'orders' => $orders,
				]);
				break;

			case 'fetch_status':
				bosta_handle_fetch_status_bulk_action([
					'APIKey' => $APIKey,
					'redirect_to'  => $redirect_to,
					'orders' => $orders,
				]);
				break;
			default:
				throw new Exception('Unknown action type: ' . $order_action['actionType']);
		}
	}
	return $redirect_to;
}

function bosta_handle_order_action($action, $fulfillment_type = null)
{
	// Handle fulfillment-related actions through the modular class
	$fulfillment_result = Bosta_Fulfillment::handle_order_action($action, $fulfillment_type);
	if ($fulfillment_result !== null) {
		return $fulfillment_result;
	}

	// Handle other actions
	switch ($action) {
		case 'sync_cash_collection_orders':
			return [
				'actionType' => 'sync_orders',
				'orderType' => 15,
				'addressType' => 'pickupAddress',
			];
		case 'print_bosta_awb':
			return [
				'actionType' => 'print_awbs',
			];
		case 'fetch_latest_status':
			return [
				'actionType' => 'fetch_status',
			];
		default:
			return null;
	}
}

function bosta_validate_order_fields($order, $order_action = null)
{
	$errors = [];
	$msg = '';

	$fields = [
		'First Name' => $order->get_billing_first_name() ?: $order->get_shipping_first_name(),
		'Phone' => $order->get_billing_phone() ?: $order->get_shipping_phone(),
		'Address line 1' => $order->get_billing_address_1() ?: $order->get_shipping_address_1(),
		'State / County' => $order->get_billing_state() ?: $order->get_shipping_state(),
	];

	foreach ($fields as $label => $value) {
		if (empty($value)) {
			$errors[] = '[' . $label . '] ' . 'is required.';
		}
	}

	$firstLine = $fields['Address line 1'];
	if (!empty($firstLine) && strlen($firstLine) < 10) {
		$errors[] = 'Error: character limit. The address entered is less than 10 characters. Please modify it and try again.';
	}

	// Check if this is a Bosta fulfillment sync and validate Bosta SKU
	if ($order_action && isset($order_action['fulfillmentType']) && $order_action['fulfillmentType'] === 'bosta_fulfillment') {
		$products_without_sku = [];

		foreach ($order->get_items() as $item) {
			$product = $item->get_product();
			if ($product) {
				$product_id = $product->get_id();
				$bosta_sku = '';

				// If it's a variation, get the SKU from the variation
				if ($product->is_type('variation')) {
					$bosta_sku = get_post_meta($product_id, '_bosta_sku', true);
				} else {
					// For simple products, get the SKU from the product
					$bosta_sku = Bosta_Fulfillment::get_product_sku($product_id);
				}

				if (empty($bosta_sku)) {
					$products_without_sku[] = $product->get_name();
				}
			}
		}

		if (!empty($products_without_sku)) {
			$errors[] = 'Bosta SKU is required for products: ' . implode(', ', $products_without_sku);
		}
	}

	if (!empty($errors)) {
		$msg = implode('', $errors);
	}

	return $msg;
}

function bosta_handle_send_orders_bulk_action($params)
{
	$APIKey = $params['APIKey'];
	$orders = $params['orders'];
	$order_action = $params['order_action'];

	// Set all orders to processing status first
	foreach ($orders as $order) {
		$order->update_meta_data('bosta_sync_status', 'processing');
		$order->delete_meta_data('bosta_sync_error');
		$order->save();
	}

	$formatted_orders = [];
	$failed_orders = [];

	foreach ($orders as $order) {
		$isOrderSyncedWithBosta = !empty($order->get_meta('bosta_tracking_number'));
		if (!$isOrderSyncedWithBosta) {
			$validation_error = bosta_validate_order_fields($order, $order_action);
			if ($validation_error) {
				$order_id = '#' . $order->get_id();
				bosta_format_failed_order_message($validation_error, $order_id);
				// Update order metadata with error
				$order->update_meta_data('bosta_sync_status', 'failed');
				$order->update_meta_data('bosta_sync_error', $validation_error);
				$order->save();
				$failed_orders[] = ['order_id' => $order->get_id(), 'error' => $validation_error];
				continue;
			}
			$formatted_orders[] = bosta_format_order_payload($order, $order_action);
		} else {
			// Already synced - update status to success if not already set
			if (empty($order->get_meta('bosta_sync_status'))) {
				$order->update_meta_data('bosta_sync_status', 'success');
				$order->delete_meta_data('bosta_sync_error');
				$order->save();
			}
		}
	}

	if (empty($formatted_orders)) {
		wp_safe_redirect(add_query_arg(['post_type' => 'shop_order'], admin_url('edit.php')));
		exit;
	}

	// Determine API endpoint based on fulfillment type
	$is_fulfillment_sync = isset($order_action['fulfillmentType']) && $order_action['fulfillmentType'] === 'bosta_fulfillment';
	$url = BOSTA_ENV_URL_V2 . '/deliveries/bulk';

	$chunkSize = 100;
	$chunks = array_chunk($formatted_orders, $chunkSize);
	$successfulDeliveriesCount = 0;
	$allFailedDeliveries = [];

	foreach ($chunks as $chunk) {
		$body = (object)[
			'deliveries' => $chunk,
			'deleteFailedDeliveries' => false
		];

		$response = bosta_send_api_request('POST', $url, $APIKey, $body);

		if (!$response['success']) {
			$error_message = $response['error'] ?? $response['message'] ?? 'Unknown error';
			bosta_set_transient('bosta_errors', $error_message);
			// Update all orders in this chunk to failed status
			foreach ($chunk as $order_payload) {
				// Convert stdClass to array if needed
				if (is_object($order_payload)) {
					$order_payload = json_decode(json_encode($order_payload), true);
				}

				$business_reference = $order_payload['businessReference'] ?? '';
				if (strpos($business_reference, 'Woocommerce_') === 0) {
					$order_id = str_replace('Woocommerce_', '', $business_reference);
					if (is_numeric($order_id)) {
						$order = wc_get_order($order_id);
						if ($order) {
							$order->update_meta_data('bosta_sync_status', 'failed');
							$order->update_meta_data('bosta_sync_error', $error_message);
							$order->save();
						}
					}
				}
			}
			continue; // Continue with next chunk instead of returning
		}

		// Regular sync response structure
		if (!$response['body'] || !is_array($response['body']) || !isset($response['body']['data'])) {
			$error_message = 'Invalid response structure from API';
			bosta_set_transient('bosta_errors', $error_message);
			// Update all orders in this chunk to failed status
			foreach ($chunk as $order_payload) {
				// Convert stdClass to array if needed
				if (is_object($order_payload)) {
					$order_payload = json_decode(json_encode($order_payload), true);
				}

				$business_reference = $order_payload['businessReference'] ?? '';
				if (strpos($business_reference, 'Woocommerce_') === 0) {
					$order_id = str_replace('Woocommerce_', '', $business_reference);
					if (is_numeric($order_id)) {
						$order = wc_get_order($order_id);
						if ($order) {
							$order->update_meta_data('bosta_sync_status', 'failed');
							$order->update_meta_data('bosta_sync_error', $error_message);
							$order->save();
						}
					}
				}
			}
			continue; // Continue with next chunk instead of returning
		}
		$data = $response['body']['data'];
		$failedDeliveries = $data['failedDeliveries'] ?? [];
		$createdDeliveriesIds = $data['createdDeliveriesIds'] ?? $data;

		// Update order metadata (this will also clean up sync status and errors for successful orders)
		bosta_get_woocommerce_deliveries_data($createdDeliveriesIds, $APIKey);

		if (!empty($failedDeliveries)) {
			$allFailedDeliveries = array_merge($allFailedDeliveries, $failedDeliveries);
		}

		$successfulDeliveriesCount += count($createdDeliveriesIds);
	}

	// Process failed deliveries and update order metadata
	if (!empty($allFailedDeliveries)) {
		foreach ($allFailedDeliveries as $failed_delivery) {
			$business_reference = $failed_delivery['businessReference'] ?? 'Unknown';
			$error_message = $failed_delivery['errorMessage'] ?? 'Unknown error';

			// Extract order ID from business reference (format: Woocommerce_123)
			$order_id = null;
			if (strpos($business_reference, 'Woocommerce_') === 0) {
				$order_id = str_replace('Woocommerce_', '', $business_reference);
			}

			if ($order_id && is_numeric($order_id)) {
				$order = wc_get_order($order_id);
				if ($order) {
					// Update order metadata with error
					$order->update_meta_data('bosta_sync_status', 'failed');
					$order->update_meta_data('bosta_sync_error', $error_message);
					$order->save();
				}
			}

			bosta_format_failed_order_message($error_message, $business_reference);
		}
	}

	// Final cleanup: Update any orders still in 'processing' status that have tracking numbers to 'success'
	foreach ($orders as $order) {
		if ($order->get_meta('bosta_sync_status') === 'processing' && !empty($order->get_meta('bosta_tracking_number'))) {
			$order->update_meta_data('bosta_sync_status', 'success');
			$order->delete_meta_data('bosta_sync_error');
			$order->save();
		}
	}

	if ($successfulDeliveriesCount > 0) {
		set_transient('bosta_success_count', $successfulDeliveriesCount, HOUR_IN_SECONDS);
	}

	wp_safe_redirect(add_query_arg(['post_type' => 'shop_order'], admin_url('edit.php')));
	exit;
}

function bosta_handle_print_awbs_bulk_action($params)
{
	$APIKey = $params['APIKey'];
	$orders = $params['orders'];

	$delivery_ids = array_filter(array_map(function ($order) {
		return $order->get_meta('bosta_delivery_id');
	}, $orders));

	if (empty($delivery_ids)) {
		$error_message = '<p>No orders have been synced with Bosta for AWB printing</p>';
		bosta_set_transient('bosta_errors', $error_message);
		return;
	}

	$url = BOSTA_ENV_URL_V2 . '/deliveries/mass-awb?ids=' . implode(',', $delivery_ids) . '&lang=ar';
	$response = bosta_send_api_request('GET', $url, $APIKey);

	if (!$response['success']) {
		bosta_format_failed_order_message($response['error']);
		return;
	}

	// Check if response body is valid and contains expected structure
	if (!$response['body'] || !is_array($response['body']) || !isset($response['body']['data'])) {
		$error_message = '<p>Invalid response structure from API</p>';
		bosta_set_transient('bosta_errors', $error_message);
		return;
	}
	$pdf_data = base64_decode($response['body']['data'], true);

	if ($pdf_data === false) {
		$error_message = '<p>Failed to decode PDF data</p>';
		bosta_set_transient('bosta_errors', $error_message);
		return;
	}

	bosta_render_pdf($pdf_data);
}

function bosta_handle_auto_sync_bulk_action($params)
{
	$APIKey = $params['APIKey'];
	$redirect_to = $params['redirect_to'];

	// Check if auto sync is enabled
	if (!Bosta_Auto_Sync::is_enabled()) {
		$error_message = '<p>Auto sync is disabled. Please enable it in the Bosta settings.</p>';
		bosta_set_transient('bosta_errors', $error_message);
		bosta_redirect_to_orders_page();
		return;
	}

	// Get orders that need to be synced
	$order_ids = Bosta_Auto_Sync::get_orders_to_sync(100);

	if (empty($order_ids)) {
		$message = '<p>No orders found that need to be synced with Bosta.</p>';
		bosta_set_transient('bosta_success', $message);
		bosta_redirect_to_orders_page();
		return;
	}

	// Sync orders using the auto sync class
	$result = Bosta_Auto_Sync::sync_orders_bulk($order_ids);

	if ($result['success'] > 0) {
		$success_message = '<p>Successfully synced ' . $result['success'] . ' orders with Bosta.</p>';
		bosta_set_transient('bosta_success', $success_message);
	}

	if ($result['failed'] > 0) {
		$error_message = '<p>Failed to sync ' . $result['failed'] . ' orders: ' . $result['errors'] . '</p>';
		bosta_set_transient('bosta_errors', $error_message);
	}

	bosta_redirect_to_orders_page();
}

function bosta_handle_fetch_status_bulk_action($params)
{
	$APIKey = $params['APIKey'];
	$redirect_to = $params['redirect_to'];
	$orders = $params['orders'];

	$deliveriesIds = [];
	foreach ($orders as $order) {
		$deliveryId = $order->get_meta('bosta_delivery_id', true);
		if (!empty($deliveryId)) {
			$deliveriesIds[] = $deliveryId;
		}
	}

	$chunkSize = 50;
	$chunks = array_chunk($deliveriesIds, $chunkSize);
	foreach ($chunks as $chunk) {
		bosta_get_woocommerce_deliveries_data($chunk, $APIKey);
	}

	if (!empty($redirect_to)) {
		wp_safe_redirect($redirect_to);
	} else {
		wp_safe_redirect(add_query_arg(['post_type' => 'shop_order'], admin_url('edit.php')));
	}
	exit;
}

function bosta_get_woocommerce_deliveries_data($deliveriesIds, $APIKey)
{
	try {
		if (!empty($deliveriesIds)) {
			$url = BOSTA_ENV_URL_V2 . '/deliveries/woocommerce-data';
			$body = (object)[
				'deliveriesIds' => $deliveriesIds,
			];

			$response = bosta_send_api_request('POST', $url, $APIKey, $body);
			if (!$response['success']) {
				$errorMessage = $response['error'] ?? '';
				throw new Exception($errorMessage);
			}

			$deliveriesData = $response['body']['data'] ?? [];

			$returnedDeliveryIds = [];
			foreach ($deliveriesData as $deliveryData) {
				$order_id = substr($deliveryData['uniqueBusinessReference'], 3);
				$order = wc_get_order($order_id);
				if ($order) {
					bosta_update_order_metadata($order, $deliveryData);
				}
				$returnedDeliveryIds[$deliveryData['_id']] = $deliveryData['_id'];
			}

			if (count($deliveriesIds) !== count($returnedDeliveryIds)) {
				foreach ($deliveriesIds as $deliveryId) {
					$order = bosta_get_order_by_metadata('bosta_delivery_id', $deliveryId);
					if (!isset($returnedDeliveryIds[$deliveryId])) {

						if ($order) {
							bosta_delete_order_metadata($order);
						}
					}
				}
			}
		}
	} catch (Exception $e) {
		error_log('Bosta Plugin: Failed to fetch orders status. Error: ' . $e->getMessage());

		$message = 'Bosta Plugin: Failed to fetch orders status. Error: ' . esc_html($e->getMessage());
		bosta_set_transient('bosta_errors', "<p>{$message}</p>");
	}
}

function bosta_format_order_payload($order, $order_action)
{
	$bosta_settings = get_option('woocommerce_bosta_settings');
	$productDescription = isset($bosta_settings['ProductDescription']) ? $bosta_settings['ProductDescription'] : array('name');
	$includeProductDescriptions = isset($bosta_settings['IncludeProductDescriptions']) ? $bosta_settings['IncludeProductDescriptions'] : 'yes';
	$allowToOpenPackage = get_option('woocommerce_bosta_settings')['AllowToOpenPackage'];
	$orderRef = get_option('woocommerce_bosta_settings')['OrderRef'];

	$newOrder = new stdClass();
	$newOrder->id = $order->get_id();
	$newOrder->type = $order_action['orderType'];
	$newOrder->notes = $order->get_customer_note();
	$newOrder->uniqueBusinessReference = "WC_" . $order->get_id();
	$newOrder->specs = new stdClass();

	// Only include package details if the checkbox is enabled
	if ($includeProductDescriptions === 'yes') {
		$newOrder->specs->packageDetails = bosta_format_package_details($order, $productDescription);
	}

	// Only add webhook fields if not running on localhost
	$site_url = get_site_url();
	if (strpos($site_url, 'localhost') === false && strpos($site_url, '127.0.0.1') === false) {
		$newOrder->webhookUrl = $site_url . '/wp-json/bosta/v1/webhook/orders/' . $order->get_id();
		$newOrder->webhookCustomHeaders = new stdClass();
		$newOrder->webhookCustomHeaders->{"X-Bosta-Secret"} = Bosta_Webhook_Manager::get_webhook_secret();
	}

	if ($allowToOpenPackage === 'yes') {
		$newOrder->allowToOpenPackage = true;
	}

	if ($orderRef === 'yes') {
		$newOrder->businessReference = 'Woocommerce_' . $order->get_id();
	}

	$newOrder->receiver = bosta_format_receiver_details($order);
	$newOrder->{$order_action['addressType']} = bosta_format_address_details($order);
	if ($order->get_payment_method() === 'cod') {
		$newOrder->cod = (float) $order->get_total();
	} else {
		$newOrder->cod = 0;
	}

	$newOrder->goodsInfo = bosta_format_goods_info($order);

	// Add fulfillment info if using Bosta fulfillment
	if (isset($order_action['fulfillmentType']) && $order_action['fulfillmentType'] === 'bosta_fulfillment') {
		$newOrder->fulfillmentInfo = Bosta_Fulfillment::format_fulfillment_info($order);
	}

	// Add productInfo if all products have bosta_product_id
	$productInfo = Bosta_Product_Info::format_product_info($order);
	if (!empty($productInfo)) {
		$newOrder->productInfo = $productInfo;
	}

	return $newOrder;
}

function bosta_format_goods_info($order)
{
	$goodsInfo = new stdClass();

	$order_regular_price = 0;
	foreach ($order->get_items() as $item) {
		$product = $item->get_product();
		$product_regular_price = (float) ($product->get_regular_price() ?? 0);
		$item_quantity = (float) ($item->get_quantity() ?? 0);
		$order_regular_price += $product_regular_price * $item_quantity;
	}

	$goodsInfo->amount = $order_regular_price;

	return $goodsInfo;
}

// Moved to Bosta_Fulfillment class

function bosta_format_package_details($order, $productDescription)
{
	$items = $order->get_items();
	$itemsCount = 0;
	$descArray = [];
	$index = 1;

	foreach ($items as $item) {
		$product = $item->get_product();

		// If product type includes bundle or woosb, don't count it
		$product_type = $product->get_type();
		if (strpos($product_type, 'bundle') === false && strpos($product_type, 'woosb') === false) {
			$itemsCount += $item->get_quantity();
		}

		$descArray[] = bosta_format_order_description($productDescription, $product->get_sku(), $product->get_name(), $item->get_quantity());
		$index++;
	}

	$packageDetails = new stdClass();
	$packageDetails->itemsCount = $itemsCount;
	$description = implode(", ", $descArray);

	// Limit description to 500 characters as per requirements
	if (mb_strlen($description) > 500) {
		$description = mb_substr($description, 0, 497) . '...';
	}

	$packageDetails->description = $description;

	return $packageDetails;
}

function bosta_format_order_description($productDescription, $sku, $name, $quantity)
{
	$desc = "";
	$bosta_settings = get_option('woocommerce_bosta_settings');
	$maintainOldDescription = isset($bosta_settings['MaintainOldDescription']) ? $bosta_settings['MaintainOldDescription'] : 'no';

	$name = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');

	if (is_array($productDescription)) {
		if (in_array('name', $productDescription)) {
			$desc .= $name;
		}

		if ($maintainOldDescription === 'yes') {
			// Old format: Product Name [SKU] (Quantity)
			if (in_array('sku', $productDescription) && !empty($sku)) {
				$desc .= " [$sku]";
			}
			$desc .= " ($quantity)";
		} else {
			// New format: Product Name x Quantity (SKU)
			$desc .= " x $quantity";
			if (in_array('sku', $productDescription) && !empty($sku)) {
				$desc .= " ($sku)";
			}
		}
	}
	return $desc;
}

function bosta_format_receiver_details($order)
{
	$firstname = $order->get_billing_first_name() ?: $order->get_shipping_first_name();
	$lastname = $order->get_billing_last_name() ?: $order->get_shipping_last_name();
	$receiver = new stdClass();
	$receiver->firstName = mb_substr($firstname, 0, 50);
	$receiver->lastName = $lastname;
	$receiver->phone = $order->get_billing_phone() ?: $order->get_shipping_phone();

	// Add second phone number if billing_phone_2 field exists and has a value
	$second_phone = $order->get_meta('_billing_phone_2', true);
	if (!empty($second_phone)) {
		$receiver->secondPhone = $second_phone;
	}

	return $receiver;
}

function bosta_format_address_details($order)
{
	$states = WC()->countries->get_states('EG');
	$address = new stdClass();

	$address->firstLine = $order->get_billing_address_1() ?: $order->get_shipping_address_1();
	$address->secondLine = $order->get_billing_address_2() ?: $order->get_shipping_address_2();

	$city_code = $order->get_billing_state() ?: $order->get_shipping_state();
	if (isset($city_code) && isset($states[$city_code])) {
		$address->city = $states[$city_code];
	}

	$district_id = $order->get_meta('_billing_area');
	if (!empty($district_id)) {
		$address->districtId = $district_id;
	}
	return $address;
}

//endregion

//region Bosta Update and Delete Actions

add_action('wp_trash_post', 'bosta_custom_delete_function');
function bosta_custom_delete_function($id)
{
	$screen = get_current_screen();
	if (!isset($screen->post_type) || 'shop_order' != $screen->post_type) {
		return;
	}

	$order = wc_get_order($id);
	if (!$order) {
		return;
	}

	$bostaStatus = $order->get_meta('bosta_status', true);
	if ($bostaStatus != 'Pickup requested' && $bostaStatus != 'Created') {
		$error_message = '<p>Failed to delete order in the current Bosta Status</p>';
		bosta_set_transient('bosta_errors', $error_message);
		return;
	}

	$APIKey = bosta_get_api_key();
	if (empty($APIKey)) {
		$error_message = '<p>API Key is required to be able to sync with Bosta</p>';
		bosta_set_transient('bosta_errors', $error_message);
		bosta_redirect_to_settings_page();
		return;
	}

	$deliveryId = $order->get_meta('bosta_delivery_id', true);

	$url = BOSTA_ENV_URL_V2 .  '/deliveries/business/' . $deliveryId . '/terminate';

	$response = bosta_send_api_request('DELETE', $url, $APIKey);

	if (!$response['success']) {
		bosta_format_failed_order_message($response['error'], $id);
	} else {
		bosta_delete_order_metadata($order);
		$success_count = get_transient('bosta_success_count') ?: 0;
		set_transient('bosta_success_count', ++$success_count, HOUR_IN_SECONDS);
	}
}

add_action('woocommerce_update_order', 'bosta_enqueue_order_update_logic', 10, 1);
function bosta_enqueue_order_update_logic($id)
{
	if (is_admin() && isset($_POST['action']) && ($_POST['action'] === 'edit_order' || $_POST['action'] === 'editpost')) {
		set_transient('deferred_order_update', $id, 10);
		add_action('shutdown', 'bosta_handle_order_update_action');
	}
}

function bosta_handle_order_update_action()
{
	$bosta_settings = get_option('woocommerce_bosta_settings', []);
	$disable_sync = $bosta_settings['DisableSyncOrderUpdates'] ?? 'no';
	if ($disable_sync === 'yes') {
		return;
	}

	$id = get_transient('deferred_order_update');

	if (!$id) {
		return;
	}

	$order = wc_get_order($id);
	if (!$order) {
		return;
	}

	$bostaStatus = $order->get_meta('bosta_status', true);
	if (empty($bostaStatus)) {
		return;
	}

	if ($bostaStatus != 'Pickup requested' && $bostaStatus != 'Created') {
		$error_message = '<p>Failed to update order in the current Bosta Status</p>';
		bosta_set_transient('bosta_errors', $error_message);
		return;
	}

	$APIKey = bosta_get_api_key();
	if (empty($APIKey)) {
		$error_message = '<p>API Key is required to be able to sync with Bosta</p>';
		bosta_set_transient('bosta_errors', $error_message);
		// bosta_redirect_to_settings_page();
		return;
	}

	$deliveryId = $order->get_meta('bosta_delivery_id', true);
	$newOrder = bosta_format_updated_order($order);

	$url = BOSTA_ENV_URL_V2 .  '/deliveries/business/' . $deliveryId;

	$response = bosta_send_api_request('PUT', $url, $APIKey, $newOrder);

	if (!$response['success']) {
		bosta_format_failed_order_message($response['error'], $id);
	} else {
		set_transient('bosta_success_count', 1, HOUR_IN_SECONDS);
	}
}

function bosta_format_updated_order($order)
{
	$states = WC()->countries->get_states('EG');
	$newOrder = new stdClass();

	$newOrder->notes = $order->get_customer_note();

	$fullname = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
	$newOrder->receiver = (object) [
		'fullName' => mb_substr($fullname, 0, 50),
		'phone'    => $order->get_billing_phone(),
	];

	$city_code = $order->get_billing_state();
	$city_name = null;
	if (isset($city_code) && isset($states[$city_code])) {
		$city_name = $states[$city_code];
	}

	$district_id = $order->get_meta('_billing_area');
	$district_id = !empty($district_id) ? $district_id : null;

	$newOrder->dropOffAddress = (object) [
		'firstLine'  => $order->get_billing_address_1(),
		'secondLine' => $order->get_billing_address_2(),
		'city'       => $city_name,
		'districtId' => $district_id,
	];

	// Add second phone number if billing_phone_2 field exists and has a value
	$second_phone = $order->get_meta('_billing_phone_2', true);
	if (!empty($second_phone)) {
		$newOrder->receiver->secondPhone = $second_phone;
	}

	$newOrder->goodsInfo = bosta_format_goods_info($order);

	$bosta_settings = get_option('woocommerce_bosta_settings');
	$productDescription = isset($bosta_settings['ProductDescription']) ? $bosta_settings['ProductDescription'] : array('name');
	$includeProductDescriptions = isset($bosta_settings['IncludeProductDescriptions']) ? $bosta_settings['IncludeProductDescriptions'] : 'yes';
	$newOrder->specs = new stdClass();

	// Check if this is a fulfillment order
	$is_fulfillment = $order->get_meta('bosta_is_fulfillment');

	// Only include package details if the checkbox is enabled AND it's not a fulfillment order
	if ($includeProductDescriptions === 'yes' && !$is_fulfillment) {
		$newOrder->specs->packageDetails = bosta_format_package_details($order, $productDescription);
	}

	// Add COD handling
	if ($order->get_payment_method() === 'cod') {
		$newOrder->cod = (float) $order->get_total();
	} else {
		$newOrder->cod = 0;
	}

	return $newOrder;
}


//endregion

//region Bosta Order Page Custom Buttons

function bosta_render_custom_buttons($send_all_nonce, $fetch_status)
{
	$auto_sync_nonce = wp_create_nonce('bosta_auto_sync_nonce');
	$api_key = bosta_get_api_key();
	$is_fulfillment_enabled = false;

	// Check if business has fulfillment enabled
	$is_fulfillment_enabled = Bosta_Fulfillment_Cache::is_fulfillment_enabled();

?>
	<div class="alignleft bosta_custom_buttons_div">
		<div class="rightDiv">
			<button type="submit" name="create_pickup" class="orders-button bosta_custom_button" value="yes">Create Pickup</button>
			<button type="button" id="send-all-orders-btn" class="orders-button bosta_custom_button">Send all Orders to Bosta</button>
			<!-- <button type="submit" name="auto_sync_orders" class="orders-button bosta_custom_button" value="yes">Auto Sync Orders</button> -->
			<input type="hidden" name="bosta_send_all_nonce_field" value="<?php echo esc_attr($send_all_nonce); ?>">
			<input type="hidden" name="bosta_auto_sync_nonce_field" value="<?php echo esc_attr($auto_sync_nonce); ?>">
		</div>
		<div class="leftDiv">
			<button type="submit" name="fetch_status" class="danger-button bosta_custom_button" value="yes">
				<img class="refreshIcon" src="<?php echo esc_url(plugins_url("assets/images/refreshIcon.png", __FILE__)); ?>" alt="Bosta"> Refresh Bosta Status
			</button>
			<input type="hidden" name="bosta_fetch_status_nonce_field" value="<?php echo esc_attr($fetch_status); ?>">
		</div>
		<input type="hidden" name="page_num" value="<?php echo esc_attr($_GET['paged'] ?? '1'); ?>">
	</div>

	<?php
	// Render fulfillment modal using modular class
	if ($is_fulfillment_enabled) {
		Bosta_Fulfillment_UI::render_fulfillment_modal();
	}
	?>
<?php
}

function bosta_render_status_search_tags()
{
	$current_sync_status = isset($_GET['bosta_sync_status']) ? sanitize_text_field($_GET['bosta_sync_status']) : '';
	$current_bosta_status = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
	$current_ranking_status = isset($_GET['bosta_ranking_status']) ? sanitize_text_field($_GET['bosta_ranking_status']) : '';
?>
	<div class="alignleft">
		<p class="bosta_custom_p">Filter with Bosta status:</p>
	</div>
	<div class="alignleft bosta_status_search_tags">
		<select id="bosta_status_filter" class="bosta-filter-select">
			<option value="">Select Bosta Status</option>
			<option value="created" <?php echo ($current_bosta_status === 'created') ? 'selected' : ''; ?>>Created</option>
			<option value="delivered" <?php echo ($current_bosta_status === 'delivered') ? 'selected' : ''; ?>>Delivered</option>
			<option value="terminated" <?php echo ($current_bosta_status === 'terminated') ? 'selected' : ''; ?>>Terminated</option>
			<option value="returned" <?php echo ($current_bosta_status === 'returned') ? 'selected' : ''; ?>>Returned</option>
		</select>
	</div>
	<div class="alignleft">
		<p class="bosta_custom_p">Filter with Bosta Sync Status:</p>
	</div>
	<div class="alignleft bosta_status_search_tags">
		<select id="bosta_sync_status_filter" class="bosta-filter-select">
			<option value="">Select Sync Status</option>
			<option value="none" <?php echo ($current_sync_status === 'none') ? 'selected' : ''; ?>>Not Synced</option>
			<option value="failed" <?php echo ($current_sync_status === 'failed') ? 'selected' : ''; ?>>Failed</option>
			<option value="synced" <?php echo ($current_sync_status === 'synced') ? 'selected' : ''; ?>>Synced</option>
		</select>
	</div>
	<div class="alignleft">
		<p class="bosta_custom_p">Filter with Consignee Ranking:</p>
	</div>
	<div class="alignleft bosta_status_search_tags">
		<select id="bosta_ranking_status_filter" class="bosta-filter-select">
			<option value="">Select Ranking Status</option>
			<option value="new_customer" <?php echo ($current_ranking_status === 'new_customer') ? 'selected' : ''; ?>>New Customer</option>
			<option value="high_rate" <?php echo ($current_ranking_status === 'high_rate') ? 'selected' : ''; ?>>High Acceptance Rate</option>
			<option value="average_rate" <?php echo ($current_ranking_status === 'average_rate') ? 'selected' : ''; ?>>Average Acceptance Rate</option>
			<option value="low_rate" <?php echo ($current_ranking_status === 'low_rate') ? 'selected' : ''; ?>>Low Acceptance Rate</option>
		</select>
	</div>
	<div class="alignleft bosta_status_search_tags">
		<button type="button" id="bosta_apply_filters" class="bosta-filter-button">Apply Filters</button>
		<?php if (!empty($current_sync_status) || !empty($current_bosta_status) || !empty($current_ranking_status)): ?>
			<button type="button" id="bosta_clear_filters" class="bosta-clear-button">Clear Filters</button>
		<?php endif; ?>
	</div>

	<script>
		document.addEventListener('DOMContentLoaded', function() {
			// Apply filters button
			document.getElementById('bosta_apply_filters').addEventListener('click', function() {
				var bostaStatus = document.getElementById('bosta_status_filter').value;
				var syncStatus = document.getElementById('bosta_sync_status_filter').value;
				var rankingStatus = document.getElementById('bosta_ranking_status_filter').value;

				var url = 'edit.php?post_type=shop_order&paged=1';

				if (bostaStatus) {
					url += '&s=' + encodeURIComponent(bostaStatus);
				}

				if (syncStatus) {
					url += '&bosta_sync_status=' + encodeURIComponent(syncStatus);
				}

				if (rankingStatus) {
					url += '&bosta_ranking_status=' + encodeURIComponent(rankingStatus);
				}

				window.location.href = url;
			});

			// Clear filters button
			document.getElementById('bosta_clear_filters').addEventListener('click', function() {
				window.location.href = 'edit.php?post_type=shop_order&paged=1';
			});

			// Allow Enter key to apply filters
			document.getElementById('bosta_status_filter').addEventListener('keypress', function(e) {
				if (e.key === 'Enter') {
					document.getElementById('bosta_apply_filters').click();
				}
			});

			document.getElementById('bosta_sync_status_filter').addEventListener('keypress', function(e) {
				if (e.key === 'Enter') {
					document.getElementById('bosta_apply_filters').click();
				}
			});

			document.getElementById('bosta_ranking_status_filter').addEventListener('keypress', function(e) {
				if (e.key === 'Enter') {
					document.getElementById('bosta_apply_filters').click();
				}
			});
		});
	</script>
<?php
}

add_filter('woocommerce_order_table_search_query_meta_keys', 'woocommerce_shop_order_search_order_total');
add_filter('woocommerce_shop_order_search_fields', 'woocommerce_shop_order_search_order_total');
function woocommerce_shop_order_search_order_total($search_fields)
{
	$search_fields[] = 'bosta_tracking_number';
	$search_fields[] = 'bosta_customer_info';
	$search_fields[] = 'bosta_status';
	$search_fields[] = 'bosta_sync_status';

	return $search_fields;
}

/**
 * Filter orders by Bosta Sync Status
 * 
 * This function filters orders based on their Bosta sync status:
 * - 'synced': Orders that have been successfully synced with Bosta (have tracking number)
 * - 'failed': Orders that failed to sync with Bosta
 * - 'none': Orders that haven't been synced yet
 * 
 * @param array $query_args The query arguments for the orders list
 * @return array Modified query arguments
 */
function bosta_filter_orders_by_sync_status($query_args)
{
	if (!isset($_GET['bosta_sync_status']) || empty($_GET['bosta_sync_status'])) {
		return $query_args;
	}

	$sync_status = sanitize_text_field($_GET['bosta_sync_status']);

	// Initialize meta_query if it doesn't exist
	if (!isset($query_args['meta_query'])) {
		$query_args['meta_query'] = array();
	}

	switch ($sync_status) {
		case 'synced':
			// Orders that have a tracking number (successfully synced)
			$query_args['meta_query'][] = array(
				'relation' => 'AND',
				array(
					'key' => 'bosta_tracking_number',
					'compare' => 'EXISTS'
				),
				array(
					'key' => 'bosta_tracking_number',
					'compare' => '!=',
					'value' => ''
				)
			);
			break;

		case 'failed':
			// Orders that have failed sync status
			$query_args['meta_query'][] = array(
				'key' => 'bosta_sync_status',
				'value' => 'failed',
				'compare' => '='
			);
			break;

		case 'none':
			// Orders that have no tracking number and no sync status (not synced)
			$query_args['meta_query'][] = array(
				'relation' => 'AND',
				array(
					'key' => 'bosta_tracking_number',
					'compare' => 'NOT EXISTS'
				),
				array(
					'key' => 'bosta_sync_status',
					'compare' => 'NOT EXISTS'
				)
			);
			break;
	}

	return $query_args;
}

// Filter orders by Bosta Consignee Ranking - HPOS (High-Performance Order Storage) system
function bosta_filter_orders_by_ranking_status($query_args)
{
	if (!isset($_GET['bosta_ranking_status']) || empty($_GET['bosta_ranking_status'])) {
		return $query_args;
	}

	$ranking_status = sanitize_text_field($_GET['bosta_ranking_status']);

	// Store the ranking status for our custom filter
	set_transient('bosta_current_ranking_filter', $ranking_status, 60);

	// Add a marker to indicate we need custom filtering
	$query_args['meta_query'][] = array(
		'key' => 'bosta_ranking_filter_marker',
		'value' => $ranking_status,
		'compare' => 'EXISTS'
	);

	return $query_args;
}

// Custom filter to handle ranking status filtering after query execution
add_filter('woocommerce_order_list_table_prepare_items_query_args', 'bosta_apply_ranking_filter_post_query', 20, 1);
add_filter('woocommerce_shop_order_list_table_prepare_items_query_args', 'bosta_apply_ranking_filter_post_query', 20, 1);

function bosta_apply_ranking_filter_post_query($query_args)
{
	$ranking_status = get_transient('bosta_current_ranking_filter');
	if (!$ranking_status) {
		return $query_args;
	}

	// Remove our marker filter
	$query_args['meta_query'] = array_filter($query_args['meta_query'], function ($meta) {
		return $meta['key'] !== 'bosta_ranking_filter_marker';
	});

	// Get all orders first, then filter them based on ranking data
	$all_orders = wc_get_orders(array(
		'limit' => -1,
		'return' => 'ids',
		'status' => array_keys(wc_get_order_statuses()),
		'meta_query' => $query_args['meta_query']
	));

	$filtered_order_ids = array();

	foreach ($all_orders as $order_id) {
		$order = wc_get_order($order_id);
		if (!$order) continue;

		$ranking_data = $order->get_meta('bosta_consignee_ranking', true);

		switch ($ranking_status) {
			case 'new_customer':
				// Include both new customers and orders with no ranking data
				if (empty($ranking_data) || (!empty($ranking_data) && isset($ranking_data['isNewCustomer']) && $ranking_data['isNewCustomer'] === true)) {
					$filtered_order_ids[] = $order_id;
				}
				break;

			case 'high_rate':
				if (
					!empty($ranking_data) && isset($ranking_data['deliverySuccessRate']) &&
					isset($ranking_data['isNewCustomer']) && $ranking_data['isNewCustomer'] === false &&
					$ranking_data['deliverySuccessRate'] > 70
				) {
					$filtered_order_ids[] = $order_id;
				}
				break;

			case 'average_rate':
				if (
					!empty($ranking_data) && isset($ranking_data['deliverySuccessRate']) &&
					isset($ranking_data['isNewCustomer']) && $ranking_data['isNewCustomer'] === false &&
					$ranking_data['deliverySuccessRate'] >= 40 && $ranking_data['deliverySuccessRate'] <= 70
				) {
					$filtered_order_ids[] = $order_id;
				}
				break;

			case 'low_rate':
				if (
					!empty($ranking_data) && isset($ranking_data['deliverySuccessRate']) &&
					isset($ranking_data['isNewCustomer']) && $ranking_data['isNewCustomer'] === false &&
					$ranking_data['deliverySuccessRate'] < 40
				) {
					$filtered_order_ids[] = $order_id;
				}
				break;
		}
	}

	// Apply the filtered order IDs
	if (!empty($filtered_order_ids)) {
		$query_args['post__in'] = $filtered_order_ids;
		$query_args['meta_query'] = array(); // Clear meta query since we're filtering by IDs
	} else {
		// No orders match, return empty result
		$query_args['post__in'] = array(0);
		$query_args['meta_query'] = array();
	}

	// Clean up transient
	delete_transient('bosta_current_ranking_filter');

	return $query_args;
}

// Filter orders by Bosta Sync Status - HPOS (High-Performance Order Storage) system
add_filter('woocommerce_order_list_table_prepare_items_query_args', 'bosta_filter_orders_by_sync_status', 10, 1);
add_filter('woocommerce_shop_order_list_table_prepare_items_query_args', 'bosta_filter_orders_by_sync_status', 10, 1);

// Filter orders by Bosta Consignee Ranking - HPOS (High-Performance Order Storage) system
add_filter('woocommerce_order_list_table_prepare_items_query_args', 'bosta_filter_orders_by_ranking_status', 10, 1);
add_filter('woocommerce_shop_order_list_table_prepare_items_query_args', 'bosta_filter_orders_by_ranking_status', 10, 1);

// Legacy support for post-based orders table
add_action('pre_get_posts', 'bosta_filter_orders_by_sync_status_legacy');
/**
 * Legacy filter for post-based orders table
 * 
 * This function provides the same filtering functionality for the legacy post-based
 * orders table system used in older WooCommerce versions.
 * 
 * @param WP_Query $query The WordPress query object
 */
function bosta_filter_orders_by_sync_status_legacy($query)
{
	// Only apply to admin orders list
	if (!is_admin() || !$query->is_main_query()) {
		return;
	}

	$screen = get_current_screen();
	if (!$screen || $screen->post_type !== 'shop_order') {
		return;
	}

	if (!isset($_GET['bosta_sync_status']) || empty($_GET['bosta_sync_status'])) {
		return;
	}

	$sync_status = sanitize_text_field($_GET['bosta_sync_status']);

	// Initialize meta_query if it doesn't exist
	if (!isset($query->query_vars['meta_query'])) {
		$query->query_vars['meta_query'] = array();
	}

	switch ($sync_status) {
		case 'synced':
			// Orders that have a tracking number (successfully synced)
			$query->query_vars['meta_query'][] = array(
				'relation' => 'AND',
				array(
					'key' => 'bosta_tracking_number',
					'compare' => 'EXISTS'
				),
				array(
					'key' => 'bosta_tracking_number',
					'compare' => '!=',
					'value' => ''
				)
			);
			break;

		case 'failed':
			// Orders that have failed sync status
			$query->query_vars['meta_query'][] = array(
				'key' => 'bosta_sync_status',
				'value' => 'failed',
				'compare' => '='
			);
			break;

		case 'none':
			// Orders that have no tracking number and no sync status (not synced)
			$query->query_vars['meta_query'][] = array(
				'relation' => 'AND',
				array(
					'key' => 'bosta_tracking_number',
					'compare' => 'NOT EXISTS'
				),
				array(
					'key' => 'bosta_sync_status',
					'compare' => 'NOT EXISTS'
				)
			);
			break;
	}
}

// Legacy support for post-based orders table - Consignee Ranking
add_action('pre_get_posts', 'bosta_filter_orders_by_ranking_status_legacy');
/**
 * Legacy filter for post-based orders table - Consignee Ranking
 * 
 * This function provides the same filtering functionality for the legacy post-based
 * orders table system used in older WooCommerce versions.
 * 
 * @param WP_Query $query The WordPress query object
 */
function bosta_filter_orders_by_ranking_status_legacy($query)
{
	// Only apply to admin orders list
	if (!is_admin() || !$query->is_main_query()) {
		return;
	}

	$screen = get_current_screen();
	if (!$screen || $screen->post_type !== 'shop_order') {
		return;
	}

	if (!isset($_GET['bosta_ranking_status']) || empty($_GET['bosta_ranking_status'])) {
		return;
	}

	$ranking_status = sanitize_text_field($_GET['bosta_ranking_status']);

	// Store the ranking status for our custom filter
	set_transient('bosta_current_ranking_filter_legacy', $ranking_status, 60);

	// Get all orders first, then filter them based on ranking data
	$all_orders = get_posts(array(
		'post_type' => 'shop_order',
		'post_status' => array_keys(wc_get_order_statuses()),
		'numberposts' => -1,
		'fields' => 'ids',
		'meta_query' => $query->query_vars['meta_query'] ?? array()
	));

	$filtered_order_ids = array();

	foreach ($all_orders as $order_id) {
		$order = wc_get_order($order_id);
		if (!$order) continue;

		$ranking_data = $order->get_meta('bosta_consignee_ranking', true);

		switch ($ranking_status) {
			case 'new_customer':
				// Include both new customers and orders with no ranking data
				if (empty($ranking_data) || (!empty($ranking_data) && isset($ranking_data['isNewCustomer']) && $ranking_data['isNewCustomer'] === true)) {
					$filtered_order_ids[] = $order_id;
				}
				break;

			case 'high_rate':
				if (
					!empty($ranking_data) && isset($ranking_data['deliverySuccessRate']) &&
					isset($ranking_data['isNewCustomer']) && $ranking_data['isNewCustomer'] === false &&
					$ranking_data['deliverySuccessRate'] > 70
				) {
					$filtered_order_ids[] = $order_id;
				}
				break;

			case 'average_rate':
				if (
					!empty($ranking_data) && isset($ranking_data['deliverySuccessRate']) &&
					isset($ranking_data['isNewCustomer']) && $ranking_data['isNewCustomer'] === false &&
					$ranking_data['deliverySuccessRate'] >= 40 && $ranking_data['deliverySuccessRate'] <= 70
				) {
					$filtered_order_ids[] = $order_id;
				}
				break;

			case 'low_rate':
				if (
					!empty($ranking_data) && isset($ranking_data['deliverySuccessRate']) &&
					isset($ranking_data['isNewCustomer']) && $ranking_data['isNewCustomer'] === false &&
					$ranking_data['deliverySuccessRate'] < 40
				) {
					$filtered_order_ids[] = $order_id;
				}
				break;
		}
	}

	// Apply the filtered order IDs
	if (!empty($filtered_order_ids)) {
		$query->set('post__in', $filtered_order_ids);
		$query->set('meta_query', array()); // Clear meta query since we're filtering by IDs
	} else {
		// No orders match, return empty result
		$query->set('post__in', array(0));
		$query->set('meta_query', array());
	}

	// Clean up transient
	delete_transient('bosta_current_ranking_filter_legacy');
}

// Handle form submissions at init stage
function bosta_handle_form_submissions()
{
	// Check if we're on the orders page and have a send_all_orders action
	if (isset($_GET['send_all_orders']) && $_GET['send_all_orders'] === 'yes') {
		// Verify nonce
		$nonce_field = 'bosta_send_all_nonce_field';
		if (!isset($_GET[$nonce_field]) || !wp_verify_nonce($_GET[$nonce_field], 'bosta_send_all_nonce')) {
			wp_die('Security check failed');
		}

		// Get fulfillment type
		$fulfillment_type = isset($_GET['fulfillment_type']) ? sanitize_text_field($_GET['fulfillment_type']) : null;

		// Handle the action
		bosta_handle_custom_bulk_action(
			'bosta_send_all_nonce',
			$nonce_field,
			'sync_to_bosta',
			$fulfillment_type
		);

		// Redirect to prevent form resubmission
		$redirect_url = remove_query_arg(['send_all_orders', 'fulfillment_type', $nonce_field], $_SERVER['REQUEST_URI']);
		wp_safe_redirect($redirect_url);
		exit;
	}
}

add_action('woocommerce_order_list_table_extra_tablenav', 'bosta_add_extra_tablenav_components_hpos', 20, 2);
function bosta_add_extra_tablenav_components_hpos($post_type, $which)
{
	if ('shop_order' !== $post_type || 'top' !== $which) {
		return;
	}

	$nonces = [
		'send_all' => wp_create_nonce('bosta_send_all_nonce'),
		'fetch_status' => wp_create_nonce('bosta_fetch_status_nonce'),
	];

	// Handle other actions that might still be processed here
	$action_handlers = [
		'create_pickup' => function () {
			$redirect_url = add_query_arg('page', 'bosta-woocommerce-create-edit-pickup', admin_url('admin.php'));
			wp_safe_redirect($redirect_url);
			exit;
		},
		'auto_sync_orders' => function () {
			bosta_handle_custom_bulk_action(
				'bosta_auto_sync_nonce',
				'bosta_auto_sync_nonce_field',
				'auto_sync_orders'
			);
		},
		'fetch_status' => function () {
			bosta_handle_custom_bulk_action(
				'bosta_fetch_status_nonce',
				'bosta_fetch_status_nonce_field',
				'fetch_latest_status'
			);
		},
	];

	foreach ($action_handlers as $action => $handler) {
		$value = isset($_GET[$action]) ? sanitize_text_field($_GET[$action]) : null;
		if ($value === 'yes') {
			$handler();
			break;
		}
	}

	bosta_render_custom_buttons($nonces['send_all'], $nonces['fetch_status']);
	bosta_render_status_search_tags();
}

add_action('manage_posts_extra_tablenav', 'bosta_add_extra_tablenav_components', 20);
function bosta_add_extra_tablenav_components($which)
{
	$screen = get_current_screen();
	if ($screen->post_type === 'product' && $which === 'top') {
		bosta_add_product_sync_button();
	} else {
		bosta_add_extra_tablenav_components_hpos($screen->post_type, $which);
	}
}

/**
 * Add product sync button to products listing page
 */
function bosta_add_product_sync_button()
{
	$api_key = bosta_get_api_key();
	if (empty($api_key)) {
		return;
	}

	// Hide button only if auto-sync is enabled AND initial sync via button has been completed
	$is_auto_sync_enabled = Bosta_Product_Sync::is_auto_sync_enabled();
	$initial_sync_completed = get_option('bosta_initial_product_sync_completed', false);

	if ($is_auto_sync_enabled && $initial_sync_completed) {
		return;
	}

?>
	<div class="alignleft bosta_product_sync_button_div" style="margin: 10px 0;">
		<button type="button" id="sync-products-to-bosta-btn" class="button button-primary">
			<?php _e('Sync my products to Bosta', 'bosta'); ?>
		</button>
	</div>
<?php
}

/**
 * Enqueue product sync CSS on products listing page
 */
add_action('admin_enqueue_scripts', 'bosta_enqueue_product_sync_styles');
function bosta_enqueue_product_sync_styles($hook)
{
	$screen = get_current_screen();
	if (!$screen || $screen->post_type !== 'product' || $screen->base !== 'edit') {
		return;
	}

	wp_enqueue_style(
		'bosta-product-sync-css',
		plugins_url('Css/product-sync.css', __FILE__),
		array(),
		filemtime(plugin_dir_path(__FILE__) . 'Css/product-sync.css')
	);
}

/**
 * Get total count of products for modal display
 * Since we sync all products (create unsynced and update synced), we show total count
 */
function bosta_get_unsynced_products_count()
{
	$products = Bosta_Product_Sync::get_all_products();
	return count($products);
}

/**
 * Add product sync modal and JavaScript to products listing page
 */
add_action('admin_footer', 'bosta_add_product_sync_modal');
function bosta_add_product_sync_modal()
{
	$screen = get_current_screen();
	if (!$screen || $screen->post_type !== 'product' || $screen->base !== 'edit') {
		return;
	}

	$api_key = bosta_get_api_key();
	if (empty($api_key)) {
		return;
	}

	// Get total count of products (we sync all products: create unsynced and update synced)
	$product_count = bosta_get_unsynced_products_count();

?>
	<!-- Product Sync Confirmation Modal -->
	<div id="bosta-product-sync-modal" class="bosta-product-sync-modal" style="display: none;">
		<div class="bosta-product-sync-modal-overlay"></div>
		<div class="bosta-product-sync-modal-container">
			<div class="bosta-product-sync-modal-header">
				<h3><?php _e('Syncing to Bosta', 'bosta'); ?></h3>
				<button type="button" class="bosta-product-sync-modal-close" aria-label="<?php _e('Close', 'bosta'); ?>">
					<span>&times;</span>
				</button>
			</div>
			<div class="bosta-product-sync-modal-body">
				<p class="bosta-product-sync-modal-question"><?php printf(__('Syncing %d Products', 'bosta'), $product_count); ?></p>
				<p class="bosta-product-sync-modal-description"><?php _e('You\'re about to sync your products to Bosta. Once activated, product updates sync automatically. You can disable this anytime from plugin settings.', 'bosta'); ?></p>
			</div>
			<div class="bosta-product-sync-modal-footer">
				<button type="button" id="product-sync-modal-cancel" class="button bosta-product-sync-btn-cancel"><?php _e('Cancel', 'bosta'); ?></button>
				<button type="button" id="product-sync-modal-confirm" class="button button-primary bosta-product-sync-btn-confirm"><?php _e('Start Syncing', 'bosta'); ?></button>
			</div>
		</div>
	</div>

	<script type="text/javascript">
		jQuery(document).ready(function($) {
			// Product Sync to Bosta functionality
			var syncButton = $('#sync-products-to-bosta-btn');
			var syncModal = $('#bosta-product-sync-modal');
			var confirmButton = $('#product-sync-modal-confirm');
			var cancelButton = $('#product-sync-modal-cancel');
			var modalClose = $('.bosta-product-sync-modal-close');
			var isSyncing = false;
			var originalButtonText = confirmButton.text();

			// Function to show toast notification
			function showToast(message, type) {
				var tagText = type === 'error' ? '<?php _e('Failed', 'bosta'); ?>' : '<?php _e('Success', 'bosta'); ?>';
				var tagClass = type === 'error' ? 'bosta-toast-tag-error' : 'bosta-toast-tag-success';
				var toast = $('<div class="bosta-toast bosta-toast-' + (type || 'success') + '">' +
					'<span class="bosta-toast-tag ' + tagClass + '">' + tagText + '</span>' +
					'<span class="bosta-toast-message">' + message + '</span>' +
					'</div>');
				$('body').append(toast);
				setTimeout(function() {
					toast.addClass('show');
				}, 100);
				setTimeout(function() {
					toast.removeClass('show');
					setTimeout(function() {
						toast.remove();
					}, 300);
				}, 3000);
			}

			// Show modal when sync button is clicked
			syncButton.on('click', function(e) {
				e.preventDefault();
				// Reset button state
				confirmButton.prop('disabled', false).text(originalButtonText);
				isSyncing = false;
				syncModal.show();
			});

			// Close modal when cancel button or close icon is clicked (only if not syncing)
			cancelButton.add(modalClose).on('click', function() {
				if (!isSyncing) {
					syncModal.hide();
				}
			});

			// Prevent closing modal when clicking overlay during sync
			$('.bosta-product-sync-modal-overlay').on('click', function(e) {
				if (!isSyncing) {
					syncModal.hide();
				}
			});

			// Confirm sync
			confirmButton.on('click', function() {
				isSyncing = true;

				// Set button to loading state
				confirmButton.prop('disabled', true).html('<span class="spinner is-active" style="float: none; margin: 0 5px 0 0; visibility: visible;"></span><?php _e('Syncing...', 'bosta'); ?>');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'bosta_sync_products_to_bosta',
						nonce: '<?php echo wp_create_nonce('bosta_sync_products_to_bosta'); ?>'
					},
					success: function(response) {
						// Close modal
						syncModal.hide();

						if (response.success) {
							// Show success toast (green)
							showToast(response.data, 'success');
							// Reload page after toast to update button state
							setTimeout(function() {
								location.reload();
							}, 3500);
						} else {
							// Show error toast (red)
							showToast(response.data, 'error');
							// Reset button state
							confirmButton.prop('disabled', false).text(originalButtonText);
							isSyncing = false;
						}
					},
					error: function() {
						// Close modal
						syncModal.hide();
						// Show error toast (red)
						showToast('<?php _e('An error occurred while syncing products.', 'bosta'); ?>', 'error');
						// Reset button state
						confirmButton.prop('disabled', false).text(originalButtonText);
						isSyncing = false;
					}
				});
			});
		});
	</script>
	<?php
}

function bosta_handle_custom_bulk_action($nonce_action, $nonce_field, $action_type, $fulfillment_type = null)
{
	$nonce_value = isset($_GET[$nonce_field]) ? sanitize_text_field($_GET[$nonce_field]) : null;

	if ($nonce_value && check_admin_referer($nonce_action, $nonce_field)) {
		$current_user_id = get_current_user_id();
		$current_page = isset($_GET['page_num']) ? $_GET['page_num'] : 1;
		$orders_per_page = get_user_option('edit_shop_order_per_page', $current_user_id);

		$orderIds = wc_get_orders([
			'limit' => $orders_per_page,
			'paged' => $current_page,
			'return' => 'ids',
		]);

		$redirect_url = add_query_arg('paged', $current_page, admin_url('edit.php?post_type=shop_order'));
		bosta_handle_bulk_action($redirect_url, $action_type, $orderIds, $fulfillment_type);
	} else {
		wp_die(__('Invalid nonce! Something went wrong.', 'bosta'));
	}
}

//endregion

//region Bosta Settings Functions

if (!function_exists('bosta_add_custom_box')) {
	function bosta_add_custom_box()
	{
		if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil') && Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
			add_meta_box('wporg_box_id', __('My Field', 'woocommerce'), 'bosta_wporg_custom_box_html', 'woocommerce_page_wc-orders', 'side', 'core');
		} else {
			add_meta_box('wporg_box_id', __('My Field', 'woocommerce'), 'bosta_wporg_custom_box_html', 'shop_order', 'side', 'core');
		}
	}
}

add_action('add_meta_boxes', 'bosta_add_custom_box');
function bosta_wporg_custom_box_html($post)
{
	$screen = get_current_screen();
	if (!isset($screen->post_type) || 'shop_order' != $screen->post_type) {
		return;
	}

	$order = wc_get_order($post->ID);
	if (!$order) {
		return;
	}

	$APIKey = bosta_get_api_key();
	if (empty($APIKey)) {
		$error_message = '<p>API Key is required to be able to sync with Bosta</p>';
		bosta_set_transient('bosta_errors', $error_message);
		return;
	}


	$trackingNumber = $order->get_meta('bosta_tracking_number', true);

	if (empty($trackingNumber)) {
		return;
	}

	$body = [
		'trackingNumbers' => $trackingNumber,
	];

	$url = BOSTA_ENV_URL_V2 .  '/deliveries/search';
	$response = bosta_send_api_request('POST', $url, $APIKey, $body);

	if (!$response['success']) {
		bosta_format_failed_order_message($response['error'], $post->ID);
	} else {
		// Check if response body is valid and contains expected structure
		if (!$response['body'] || !is_array($response['body']) || !isset($response['body']['data']) || !isset($response['body']['data']['deliveries']) || empty($response['body']['data']['deliveries'])) {
			bosta_format_failed_order_message('Invalid response structure from API', $post->ID);
			return;
		}
		$delivery = $response['body']['data']['deliveries'][0];
		if ($delivery['state']['value'] != 'Created' && $delivery['state']['value'] != 'Pickup requested') {
	?>
			<script>
				let div = document.createElement("div");
				let p = document.createElement("p");
				let textnode = document.createTextNode("The order is being shipped by bosta. Any updating or deleting on the order info will not reflect to bosta system. For support email help@bosta.co");
				p.appendChild(textnode);
				div.appendChild(p);
				div.setAttribute('class', 'error error-note');
				const parent = document.getElementsByClassName("wrap")[0];
				parent.insertBefore(div, parent.children[3]);
			</script>
	<?php
		}
	}
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'bosta_plugin_action_links');
function bosta_plugin_action_links($links)
{
	$plugin_links = array(
		'<a href="' . menu_page_url('bosta-woocommerce', false) . '">' . __('Settings') . '</a>',
	);
	return array_merge($plugin_links, $links);
}

add_action('plugins_loaded', 'bosta_init_shipping_class');
add_action('woocommerce_shipping_init', 'bosta_init_shipping_class');
function bosta_init_shipping_class()
{
	if (!class_exists('WooCommerce')) {
		return;
	}

	if (!class_exists('bosta_Shipping_Method')) {
		class bosta_Shipping_Method extends WC_Shipping_Method
		{
			public function __construct()
			{
				parent::__construct();

				$this->id = 'bosta';
				$this->method_title = __('Bosta Shipping', 'bosta');
				$this->method_description = __('Custom Shipping Method for bosta', 'bosta');
				$this->init();
				$this->enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : 'yes';
				$this->title = isset($this->settings['title']) ? $this->settings['title'] : __('bosta Shipping', 'bosta');
			}

			function init()
			{
				$this->init_form_fields();
				$this->init_settings();
				$this->auto_update_flex_ship_on_settings_page();
				$this->bosta_generate_webhook_secret();
				add_action('woocommerce_update_options_shipping_' . $this->id, array(
					$this,
					'process_admin_options',
				));
				add_action('admin_footer', array($this, 'handle_flex_shipping_toggle'));
				// Fulfillment section field rendering moved to Bosta_Fulfillment_UI class
			}

			private function is_bosta_shipping_settings_screen()
			{
				if (!is_admin()) {
					return false;
				}
				if (!isset($_GET['page'], $_GET['tab'], $_GET['section'])) {
					return false;
				}
				return $_GET['page'] === 'wc-settings' && $_GET['tab'] === 'shipping' && $_GET['section'] === 'bosta';
			}


			function handle_flex_shipping_toggle()
			{
				if (!$this->is_bosta_shipping_settings_screen()) {
					return;
				}
				$settings_html_file = plugin_dir_path(__FILE__) . 'components/settings/flexship/flexship.html';
				if (file_exists($settings_html_file)) {
					include $settings_html_file;
				}
			}



			// Fulfillment section field rendering moved to Bosta_Fulfillment_UI class

			private function get_business_info($api_key)
			{
				$url = BOSTA_ENV_URL_V0 . '/businesses/' . esc_html($api_key) . '/info';
				$response = bosta_send_api_request('GET', $url);

				if ($response['success']) {
					return $response['body'];
				}

				return array();
			}

			function auto_update_flex_ship_on_settings_page()
			{
				if ($this->is_bosta_shipping_settings_screen()) {
					$apiKey = $this->get_option('APIKey');
					if (!empty($apiKey)) {
						$this->update_plugin_flex_ship_settings($apiKey);
					}
				}
			}

			function bosta_generate_webhook_secret()
			{
				// Handle webhook registration for existing users
				$webhook_secret = Bosta_Webhook_Manager::get_webhook_secret();
				if (empty($webhook_secret)) {
					$webhook_secret = Bosta_Webhook_Manager::generate_webhook_secret();
					Bosta_Webhook_Manager::set_webhook_secret($webhook_secret);
					Bosta_Webhook_Manager::register_webhook_with_bosta($webhook_secret);
				}
			}

			function init_form_fields()
			{
				$this->form_fields = array(
					'APIKey' => array(
						'title' => __('APIKey', 'bosta'),
						'type' => 'text',
					),
					'IncludeProductDescriptions' => array(
						'label' => 'Include Woocommerce Product Descriptions in Bosta Airway Bills',
						'title' => __('Product Descriptions', 'bosta'),
						'type' => 'checkbox',
						'default' => 'yes',
						'description' => __('Product descriptions included in the airway bill shall not exceed 500 characters.', 'bosta'),
					),
					'ProductDescription' => array(
						'label' => 'Select product info to display in AWB',
						'title' => __('Product Description Fields', 'bosta'),
						'type' => 'multiselect',
						'class' => 'wc-enhanced-select',
						'options' => array(
							'name' => __('Product Name', 'bosta'),
							'sku' => __('Product SKU', 'bosta'),
						),
						'default' => array('name'),
						'description' => __('Select one or more product fields to display in the AWB.', 'bosta'),
					),
					'MaintainOldDescription' => array(
						'label' => 'Maintain old description',
						'title' => __('Maintain Old Description', 'bosta'),
						'type' => 'checkbox',
						'default' => 'no',
						'description' => __('If checked, maintains the old description format: Product Name [SKU] (Quantity). If unchecked, uses new format: Product Name x Quantity (SKU).', 'bosta'),
					),
					'AllowToOpenPackage' => array(
						'label' => 'Allow customer to open package',
						'title' => __('Allow to open package', 'bosta'),
						'type' => 'checkbox',
						'default' => 'no',
					),
					'EnableFlexShipping' => array(
						'label' => 'Enable FlexShip',
						'title' => __('Enable FlexShip', 'bosta'),
						'type' => 'checkbox',
						'default' => 'no',
						'description' => 'Charge your customer a service fee in case of package refusal to reduce your refund costs',
					),
					'FlexShippingValue' => array(
						'type' => 'hidden',
						'default' => FLEX_SHIPPING_DEFAULT_VALUE,
					),
					'OrderRef' => array(
						'label' => 'Display Woocomerce order reference in AWB',
						'title' => __('Order reference', 'bosta'),
						'type' => 'checkbox',
						'default' => 'yes',
					),
					'DisableBostaZoning' => array(
						'label' => 'Disable Bosta area fields',
						'title' => __('Disable Bosta Zoning', 'bosta'),
						'type' => 'checkbox',
						'default' => 'no',
					),
					'ResetZoningCache' => array(
						'label' => 'Reset zoning cache',
						'title' => __('Reset Zoning Cache', 'bosta'),
						'type' => 'checkbox',
						'default' => 'no',
					),
					// 'WebhookSecret' => array(
					// 	'title' => __('Webhook Secret', 'bosta'),
					// 	'type' => 'text',
					// 	'default' => Bosta_Webhook_Manager::get_webhook_secret(),
					// 	'custom_attributes' => array(
					// 		'readonly' => 'readonly',
					// 	),
					// 	'description' => __('This secret key is used to validate incoming webhooks from Bosta. Keep this secure.', 'bosta'),
					// ),
					'DisableSyncOrderUpdates' => array(
						'label' => 'Disable syncing order updates to Bosta',
						'title' => __('Disable Order Updates Sync', 'bosta'),
						'type' => 'checkbox',
						'default' => 'no',
						'description' => __('When enabled, changes to existing orders will not be synced to Bosta.', 'bosta'),
					),
					'EnableAutoSync' => array(
						'label' => 'Enable automatic order synchronization',
						'title' => __('Enable Auto Sync', 'bosta'),
						'type' => 'checkbox',
						'default' => 'no',
						'description' => __('Automatically sync new orders with Bosta when they are created. Orders will be sent to Bosta immediately after checkout completion.', 'bosta'),
					),
					'AutoSyncFulfillmentType' => array(
						'title' => __('Auto Sync Type', 'bosta'),
						'type' => 'select',
						'class' => 'wc-enhanced-select',
						'options' => array(
							'your_location' => __('From Your Location', 'bosta'),
							'bosta_fulfillment' => __('Bosta Fulfillment Center', 'bosta'),
						),
						'default' => 'your_location',
						'description' => __('Choose where orders will be fulfilled from when auto sync is enabled.', 'bosta'),
					),
					'EnableProductAutoSync' => array(
						'label' => 'Enable automatic product sync',
						'title' => __('Enable Product Sync', 'bosta'),
						'type' => 'checkbox',
						'default' => 'no',
						'description' => __('Automatically sync your products to Bosta when they are created or updated.', 'bosta'),
					),
				);
			}

			private function bosta_check_reset_zoning_cache_toggle()
			{
				$is_toggle_enabled = $this->get_option('ResetZoningCache') === 'yes';
				if ($is_toggle_enabled) {
					delete_transient('bosta_zoning');
					delete_transient('bosta_city_areas');
					delete_transient('bosta_country_id_Transient');

					$this->update_option('ResetZoningCache', 'no');
				}
			}

			public function process_admin_options()
			{
				$oldAPIKey = $this->get_option('APIKey');
				$oldFlexship = $this->get_option('EnableFlexShipping');
				$oldFlexshipValue = $this->get_option('FlexShippingValue');
				$oldProductDescription = $this->get_option('ProductDescription');
				$oldProductAutoSync = $this->get_option('EnableProductAutoSync');
				$regenerateWebhookSecret = $this->get_option('RegenerateWebhookSecret');
				$testWebhook = $this->get_option('TestWebhook');

				$productDescription = isset($_POST['woocommerce_bosta_ProductDescription']) ? $_POST['woocommerce_bosta_ProductDescription'] : array();
				if (empty($productDescription) || (is_array($productDescription) && count($productDescription) === 0)) {
					WC_Admin_Settings::add_error(__('Please select at least one product info field for AWB.', 'bosta'));
					$this->settings['ProductDescription'] = $oldProductDescription;
					return false;
				}

				$settings_saved = parent::process_admin_options();
				$this->bosta_check_reset_zoning_cache_toggle();

				// Check if Product Auto Sync is disabled (clear flag whenever it's disabled)
				$newProductAutoSync = isset($_POST['woocommerce_bosta_EnableProductAutoSync']) ? 'yes' : 'no';
				if ($newProductAutoSync === 'no') {
					// Clear the initial sync flag when auto-sync is disabled, so button can reappear
					delete_option('bosta_initial_product_sync_completed');

					// Update feature configuration to indicate products are not synced
					Bosta_Product_Sync::update_feature_configuration(false);
				}

				$newAPIKey = $this->get_option('APIKey');
				$newFlexship = $this->get_option('EnableFlexShipping');
				$newFlexshipValue = $this->get_option('FlexShippingValue');

				if (empty($newAPIKey)) {
					WC_Admin_Settings::add_error(__('Error: API Key is required.', 'bosta'));
					return false;
				}
				if (!bosta_validate_api_key($newAPIKey)) {
					WC_Admin_Settings::add_error(__('Error: API Key is invalid.', 'bosta'));
					return false;
				}

				if (!$settings_saved) {
					return false;
				}

				if ($newAPIKey !== $oldAPIKey) {
					// Register webhook with new API key
					$webhook_secret = Bosta_Webhook_Manager::get_webhook_secret();
					if (!empty($webhook_secret)) {
						Bosta_Webhook_Manager::register_webhook_with_bosta($webhook_secret);
					}

					if ($newFlexship === 'yes') {
						$this->update_bosta_flex_ship_settings($newAPIKey, $newFlexship, $newFlexshipValue);
					} else {
						$this->update_plugin_flex_ship_settings($newAPIKey);
					}
					return true;
				}

				if ($newFlexship !== $oldFlexship || $newFlexshipValue !== $oldFlexshipValue) {
					$this->update_bosta_flex_ship_settings($newAPIKey, $newFlexship, $newFlexshipValue);
				}

				// Handle webhook secret regeneration
				if ($regenerateWebhookSecret === 'yes') {
					$new_webhook_secret = Bosta_Webhook_Manager::generate_webhook_secret();
					Bosta_Webhook_Manager::set_webhook_secret($new_webhook_secret);
					Bosta_Webhook_Manager::register_webhook_with_bosta($new_webhook_secret);
					$this->update_option('RegenerateWebhookSecret', 'no');
					WC_Admin_Settings::add_message(__('Webhook secret has been regenerated and registered with Bosta.', 'bosta'));
				}

				// Handle webhook testing
				if ($testWebhook === 'yes') {
					$test_result = Bosta_Webhook_Manager::test_webhook_endpoint();
					$this->update_option('TestWebhook', 'no');

					if ($test_result) {
						WC_Admin_Settings::add_message(__('Webhook test successful! The endpoint is working correctly.', 'bosta'));
					} else {
						WC_Admin_Settings::add_error(__('Webhook test failed! Please check your server configuration and try again.', 'bosta'));
					}
				}

				return true;
			}

			private function update_plugin_flex_ship_settings($apikey)
			{
				$url = BOSTA_ENV_URL_V0 . '/businesses/' . esc_html($apikey) . '/info';
				$response = bosta_send_api_request('GET', $url);

				if ($response['success'] && isset($response['body']['flexShip'])) {
					$flexShip = $response['body']['flexShip'];
					$isSubscribed = !empty($flexShip['isSubscribed']);
					$isAppliedToAllOrders = !empty($flexShip['isAppliedToAllOrders']);
					if ($isSubscribed && $isAppliedToAllOrders) {
						$this->update_option('EnableFlexShipping', 'yes');
						$this->update_option('FlexShippingValue', $flexShip['amountToBeCollected'] ?? FLEX_SHIPPING_DEFAULT_VALUE);
						$this->update_option('AllowToOpenPackage', 'yes');
					} else {
						$this->update_option('EnableFlexShipping', 'no');
						$this->update_option('FlexShippingValue', FLEX_SHIPPING_DEFAULT_VALUE);
					}
				} else {
					$this->update_option('EnableFlexShipping', 'no');
					$this->update_option('FlexShippingValue', FLEX_SHIPPING_DEFAULT_VALUE);
				}
			}

			private function update_bosta_flex_ship_settings($apikey, $flexship, $flexship_value)
			{
				$url = BOSTA_ENV_URL_V2 . '/businesses/flex-ship';
				$body = [
					'isSubscribed' => ($flexship === 'yes'),
					'amountToBeCollected' => intval($flexship_value),
					'isAppliedToAllOrders' => true
				];
				$response = bosta_send_api_request('PUT', $url, $apikey, $body);

				if (!$response['success']) {
					$error_message = 'Failed to update FlexShip settings: ' . $response['error'];
					WC_Admin_Settings::add_error(__($error_message, 'bosta'));
				} else {
					$success_message = $flexship === 'yes'
						? 'FlexShip service has been activated successfully.'
						: 'FlexShip service has been deactivated successfully.';
					WC_Admin_Settings::add_message(__($success_message, 'bosta'));
				}
			}
		}
	}
}

add_filter('woocommerce_shipping_methods', 'bosta_add_shipping_method');
function bosta_add_shipping_method($methods)
{
	$methods[] = 'bosta_Shipping_Method';
	return $methods;
}

add_action('admin_menu', 'bosta_setup_menu', 20);
function bosta_setup_menu()
{
	//check if woocommerce is activated
	if (!class_exists('WooCommerce')) {
		return;
	}

	add_menu_page('Test Plugin Page', 'Bosta', 'manage_options', 'bosta-woocommerce', 'bosta_redirect_to_settings_page', esc_url(plugins_url('assets/images/bosta.svg', __FILE__)), 56);

	// link to plugin settings
	add_submenu_page('bosta-woocommerce', 'Setting', 'Setting', 'manage_options', 'bosta-woocommerce', 'bosta_redirect_to_settings_page');

	// link to inventory management
	add_submenu_page('bosta-woocommerce', 'Inventory', 'Inventory', 'manage_options', 'bosta-woocommerce-inventory', 'bosta_inventory_page');

	// link to woocommerce orders
	add_submenu_page('bosta-woocommerce', 'Send Orders', 'Send Orders', 'manage_options', 'bosta-woocommerce-orders', 'bosta_redirect_to_orders_page');

	// create pickup request
	add_submenu_page('bosta-woocommerce', 'Create Pickup', 'Create Pickup', 'manage_options', 'bosta-woocommerce-create-edit-pickup', 'bosta_create_edit_pickup_form');

	//view pickups
	add_submenu_page('bosta-woocommerce', 'Pickup Requests', 'Pickup Requests', 'manage_options', 'bosta-woocommerce-view-pickups', 'bosta_view_scheduled_pickups');

	// link to bosta shipments
	add_submenu_page('bosta-woocommerce', 'Track Bosta Orders', 'Track Bosta Orders', 'manage_options', 'bosta-woocommerce-shipments', 'bosta_redirect_to_dashboard_page');

	// link to bosta documentation
	add_submenu_page('bosta-woocommerce', 'Bosta Documentation', 'Bosta Documentation', 'manage_options', 'bosta-woocommerce-documentation', 'bosta_redirect_to_documentation_page');
}

add_action('admin_enqueue_scripts', function ($hook) {
	if ($hook === 'woocommerce_page_wc-settings' && isset($_GET['section']) && $_GET['section'] === 'bosta') {
		// Enqueue flexship script
		wp_enqueue_script(
			'bosta-flexship-js',
			plugins_url('components/settings/flexship/flexship.js', __FILE__),
			array('jquery'),
			filemtime(plugin_dir_path(__FILE__) . 'components/settings/flexship/flexship.js'),
			true
		);

		// Enqueue auto sync warning script
		wp_enqueue_script(
			'bosta-auto-sync-warning-js',
			plugins_url('assets/js/auto-sync-warning.js', __FILE__),
			array('jquery'),
			filemtime(plugin_dir_path(__FILE__) . 'assets/js/auto-sync-warning.js'),
			true
		);

		// Enqueue auto sync warning CSS
		wp_enqueue_style(
			'bosta-auto-sync-warning-css',
			plugins_url('Css/auto-sync-warning.css', __FILE__),
			array(),
			filemtime(plugin_dir_path(__FILE__) . 'Css/auto-sync-warning.css')
		);

		// Enqueue product description settings CSS
		wp_enqueue_style(
			'bosta-product-description-settings-css',
			plugins_url('Css/product-description-settings.css', __FILE__),
			array(),
			filemtime(plugin_dir_path(__FILE__) . 'Css/product-description-settings.css')
		);

		// Enqueue product description settings JavaScript
		wp_enqueue_script(
			'bosta-product-description-settings-js',
			plugins_url('assets/js/product-description-settings.js', __FILE__),
			array('jquery'),
			filemtime(plugin_dir_path(__FILE__) . 'assets/js/product-description-settings.js'),
			true
		);
	}

	// Enqueue tooltip script for orders page
	if (
		in_array($hook, ['edit.php', 'woocommerce_page_wc-orders']) &&
		(isset($_GET['post_type']) && $_GET['post_type'] === 'shop_order') ||
		$hook === 'woocommerce_page_wc-orders'
	) {
		wp_enqueue_script(
			'bosta-tooltip-js',
			plugins_url('assets/js/tooltip.js', __FILE__),
			array('jquery'),
			filemtime(plugin_dir_path(__FILE__) . 'assets/js/tooltip.js'),
			true
		);
	}
});

// AJAX handlers for fulfillment sync - Moved to Bosta_Fulfillment class
//endregion



//region Bosta Preview Functions

add_filter('woocommerce_admin_order_preview_get_order_details', 'bosta_admin_order_preview_add_custom_meta_data', 10, 2);
function bosta_admin_order_preview_add_custom_meta_data($data, $order)
{
	$APIKey = bosta_get_api_key();
	if (empty($APIKey)) {
		$message = 'API Key is required to be able to sync with Bosta';
		bosta_set_transient('bosta_errors', "<p>{$message}</p>");
		bosta_redirect_to_settings_page();
		return $data;
	}

	$trackingNumber = $order->get_meta('bosta_tracking_number', true);
	if (empty($trackingNumber)) {
		$message = 'Order is not synced at Bosta';
		bosta_set_transient('bosta_errors', "<p>{$message}</p>");
		return $data;
	}

	$url = BOSTA_ENV_URL_V2 . '/deliveries/business/' . $trackingNumber;
	$response = bosta_send_api_request('GET', $url, $APIKey);
	if (!$response['success']) {
		bosta_format_failed_order_message($response['error']);
		return $data;
	}
	// Check if response body is valid and contains expected structure
	if (!$response['body'] || !is_array($response['body']) || !isset($response['body']['data'])) {
		bosta_format_failed_order_message('Invalid response structure from API');
		return $data;
	}
	$orderDetails = $response['body']['data'];
	$data = array_merge($data, bosta_preview_extract_order_timeline_details($orderDetails));
	$data = array_merge($data, bosta_preview_extract_order_details($orderDetails));
	$data = array_merge($data, bosta_preview_extract_customer_info($orderDetails));
	$data = array_merge($data, bosta_preview_extract_pickup_info($orderDetails));
	$data = array_merge($data, bosta_preview_extract_bosta_performance_info($orderDetails));

	return $data;
}

function bosta_preview_extract_order_timeline_details($orderDetails)
{
	$timelineData = [];

	if (!empty($orderDetails['timeline'])) {
		foreach ($orderDetails['timeline'] as $x => $timeline) {
			$timelineData["timeline_value_$x"] = $timeline['value'] ?? 'N/A';
			$timelineData["timeline_date_$x"] = isset($timeline['date']) ? bosta_format_date($timeline['date']) : 'N/A';
			$isDone = $timeline['done'] ?? false;
			$timelineData["timeline_done_$x"] = $isDone ? 'status_done' : 'status_not_done';
			if ($isDone) {
				$timelineData["timeline_next_action"] = $timeline['nextAction'] ?? 'N/A';
				$timelineData["timeline_shipment_age"] = $timeline['nextAction'] ?? 'N/A';
			}
		}
		$timelineLength = count($orderDetails['timeline']);
		set_transient('bosta_timelineLength', $timelineLength, HOUR_IN_SECONDS);
	}

	return $timelineData;
}

function bosta_preview_extract_order_details($orderDetails)
{
	return [
		'trackingNumber' => $orderDetails['trackingNumber'] ?? 'N/A',
		'status' => $orderDetails['state']['value'] ?? 'N/A',
		'type' => $orderDetails['type']['value'] ?? 'N/A',
		'cod' => $orderDetails['cod'] ?? '0',
		'createdAt' => bosta_format_date($orderDetails['createdAt']),
		'updatedAt' => bosta_format_date($orderDetails['updatedAt']),
		'itemsCount' => $orderDetails['specs']['packageDetails']['itemsCount'] ?? 'N/A',
		'notes' => $orderDetails['notes'] ?? 'N/A'
	];
}

function bosta_preview_extract_customer_info($orderDetails)
{
	return [
		'fullName' => $orderDetails['receiver']['fullName'] ?? 'N/A',
		'phone' => $orderDetails['receiver']['phone'] ?? 'N/A',
		'dropOffAddressCity' => $orderDetails['dropOffAddress']['city']['name'] ?? 'N/A',
		'dropOffAddressZone' => $orderDetails['dropOffAddress']['zone']['name'] ?? 'N/A',
		'dropOffAddressDistrict' => $orderDetails['dropOffAddress']['district']['name'] ?? 'N/A',
		'dropOffAddressFistLine' => $orderDetails['dropOffAddress']['firstLine'] ?? 'N/A',
		'dropOffAddressBuilding' => $orderDetails['dropOffAddress']['buildingNumber'] ?? 'N/A',
		'dropOffAddressFloor' => $orderDetails['dropOffAddress']['floor'] ?? 'N/A',
		'dropOffAddressApartment' => $orderDetails['dropOffAddress']['apartment'] ?? 'N/A'
	];
}

function bosta_preview_extract_pickup_info($orderDetails)
{
	return [
		'pickupAddressCity' => $orderDetails['pickupAddress']['city']['name'] ?? 'N/A',
		'pickupAddressZone' => $orderDetails['pickupAddress']['zone']['name'] ?? 'N/A',
		'pickupAddressDistrict' => $orderDetails['pickupAddress']['district']['name'] ?? 'N/A',
		'pickupAddressFistLine' => $orderDetails['pickupAddress']['firstLine'] ?? 'N/A',
		'pickupAddressBuilding' => $orderDetails['pickupAddress']['buildingNumber'] ?? 'N/A',
		'pickupAddressFloor' => $orderDetails['pickupAddress']['floor'] ?? 'N/A',
		'pickupAddressApartment' => $orderDetails['pickupAddress']['apartment'] ?? 'N/A',
		'pickupRequestId' => $orderDetails['pickupRequestId'] ?? 'N/A'
	];
}

function bosta_preview_extract_bosta_performance_info($orderDetails)
{
	$promise = 'Not started yet';
	if (!empty($orderDetails['sla'])) {
		$isExceeded = $orderDetails['sla']['e2eSla']['isExceededE2ESla'] ?? false;
		$data['promise'] = $isExceeded ? 'Not met' : 'Met';
	}

	return [
		'outboundActionsCount' => $orderDetails['outboundActionsCount'] ?? '0',
		'deliveryAttemptsLength' => $orderDetails['deliveryAttemptsLength'] ?? '0',
		'promise' => $promise
	];
}

add_action('woocommerce_admin_order_preview_start', 'bosta_custom_display_order_data_in_admin');
function bosta_custom_display_order_data_in_admin()
{
	$timelineLength = get_transient('bosta_timelineLength') ?? 0;

	?>
	<div class="container-div">
		<h4 class="table-title">Order Timeline</h4>
		<div class="timeline-table">
			<?php for ($x = 0; $x < $timelineLength; $x++): ?>
				<div class="timeline-entry">
					<div class="entry-progress">
						<span class="<?php echo "{{data.timeline_done_" . esc_attr($x) . "}}" ?>"></span>
						<span class="<?php echo "{{data.timeline_done_" . esc_attr($x) . "}}_line"; ?>"></span>
					</div>
					<div class="entry-data">
						<span class="data-title"><?php echo "{{data.timeline_value_$x}}"; ?></span>
						<span class="data-date"><?php echo "{{data.timeline_date_$x}}"; ?></span>
					</div>
				</div>
			<?php endfor; ?>
		</div>
		<div class="timeline-next-action">
			<span class="next-action-title">Next Action:</span>
			<span> {{data.timeline_next_action}} </span>
		</div>
	</div>
	<?php

	?>
	<div class="container-div">
		<h4 class="table-title">Order Details</h4>
		<div class="container-table">
			<div class="cell">
				<p class="cell-header">Bosta tracking number: </p>
				<p class="cell-data"><?php echo "{{data.trackingNumber}}"; ?></p>
			</div>
			<div class="cell">
				<p class="cell-header">Status: </p>
				<p class="cell-data"><?php echo "{{data.status}}"; ?></p>
			</div>
			<div class="cell">
				<p class="cell-header">Type: </p>
				<p class="cell-data"><?php echo "{{data.type}}"; ?></p>
			</div>
			<div class="cell">
				<p class="cell-header">Cash on delivery: </p>
				<p class="cell-data"><?php echo "{{data.cod}}"; ?></p>
			</div>
			<div class="cell">
				<p class="cell-header">Creation date: </p>
				<p class="cell-data"><?php echo "{{data.createdAt}}"; ?></p>
			</div>
			<div class="cell">
				<p class="cell-header">Last update date: </p>
				<p class="cell-data"><?php echo "{{data.updatedAt}}"; ?></p>
			</div>
			<div class="cell">
				<p class="cell-header">Items count: </p>
				<p class="cell-data"><?php echo "{{data.itemsCount}}"; ?></p>
			</div>
			<div class="cell">
				<p class="cell-header">Delivery Notes: </p>
				<p class="cell-data"><?php echo "{{data.notes}}"; ?></p>
			</div>
		</div>
	</div>
	<?php

	?>
	<div class="container-div">
		<h4 class="table-title">Customer Info</h4>
		<div class="container-table">
			<div class="cell">
				<p class="cell-header">Customer name: </p>
				<p class="cell-data"><?php echo "{{data.fullName}}"; ?></p>
			</div>
			<div class="cell">
				<p class="cell-header">Phone number: </p>
				<p class="cell-data"><?php echo "{{data.phone}}"; ?></p>
			</div>
			<div class="cell">
				<p class="cell-header">Area, City: </p>
				<p class="cell-data"><?php echo "{{data.dropOffAddressZone}} - {{data.dropOffAddressDistrict}}, {{data.dropOffAddressCity}}"; ?></p>
			</div>
			<div class="cell">
				<p class="cell-header">Customer address: </p>
				<p class="cell-data"><?php echo "{{data.dropOffAddressFistLine}}"; ?></p>
			</div>
			<div class="cell">
				<p class="cell-header">Building number: </p>
				<p class="cell-data"><?php echo "{{data.dropOffAddressBuilding}}"; ?></p>
			</div>
			<div class="cell">
				<p class="cell-header">Floor, Apartment: </p>
				<p class="cell-data"><?php echo "{{data.dropOffAddressFloor}}, {{data.dropOffAddressApartment}}"; ?></p>
			</div>
		</div>
	</div>
	<?php

	?>
	<div class="container-div">
		<h4 class="table-title">Pickup Info</h4>
		<div class="container-table">
			<div class="cell">
				<p class="cell-header">City: </p>
				<p class="cell-data"><?php echo "{{data.pickupAddressCity}}"; ?></p>
			</div>
			<div class="cell">
				<p class="cell-header">Area: </p>
				<p class="cell-data"><?php echo "{{data.pickupAddressZone}} - {{data.pickupAddressDistrict}}"; ?></p>
			</div>
			<div class="cell">
				<p class="cell-header">Street name: </p>
				<p class="cell-data"><?php echo "{{data.pickupAddressFistLine}}"; ?></p>
			</div>
			<div class="cell">
				<p class="cell-header">Building number: </p>
				<p class="cell-data"><?php echo "{{data.pickupAddressBuilding}}"; ?></p>
			</div>
			<div class="cell">
				<p class="cell-header">Floor, Apartment: </p>
				<p class="cell-data"><?php echo "{{data.pickupAddressFloor}}, {{data.pickupAddressApartment}}"; ?></p>
			</div>
			<div class="cell">
				<p class="cell-header">Pickup ID: </p>
				<p class="cell-data"><?php echo "{{data.pickupRequestId}}"; ?></p>
			</div>
		</div>
	</div>
	<?php

	?>
	<div class="container-div">
		<h4 class="table-title">Bosta Performance</h4>
		<div class="container-table">
			<div class="cell">
				<p class="cell-header" data-tooltip="Number of times Bosta tried to deliver the order">Delivery attempts:</p>
				<p class="cell-data"><?php echo "{{data.deliveryAttemptsLength}}"; ?></p>
			</div>
			<div class="cell">
				<p class="cell-header" data-tooltip="Number of calls made by the outbound team to verify the star actions and take corrective actions if needed to deliver the order on time">Outbound calls:</p>
				<p class="cell-data"><?php echo "{{data.outboundActionsCount}}"; ?></p>
			</div>
			<div class="cell">
				<p class="cell-header" data-tooltip="Bosta promises next day delivery (calculated from the pickup date) for orders with Cairo as the pickup and drop city. The expected delivery period increases to two or three days depending on the distance between the pick up and the drop off cities i.e. Alexandria, Delta or Upper Egypt.">Delivery promise:</p>
				<p class="cell-data"><?php echo "{{data.promise}}"; ?></p>
			</div>
		</div>
	</div>
<?php
}
