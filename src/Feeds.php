<?php
/**
 * Pinterest for WooCommerce Feeds related helper methods
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Pinterest\API\APIV5;
use Automattic\WooCommerce\Pinterest\API\Base;
use Automattic\WooCommerce\Pinterest\Exception\PinterestApiLocaleException;
use Exception;
use Throwable;

/**
 * Class handling fetch methods for feed profiles.
 */
class Feeds {

	/**
	 * Feed ACTIVE status which mirrors the Pinterest API feed status.
	 */
	const FEED_STATUS_ACTIVE = 'ACTIVE';

	/**
	 * Feed INACTIVE status which mirrors the Pinterest API feed status.
	 */
	const FEED_STATUS_INACTIVE = 'INACTIVE';

	/**
	 * Feed DELETED status which mirrors the Pinterest API feed status.
	 */
	const FEED_STATUS_DELETED = 'DELETED';

	/**
	 * Feed DOES_NOT_EXIST status which is a custom status.
	 * Represents a feed that was never created.
	 * In case fetching the feed returns no results.
	 */
	const FEED_STATUS_DOES_NOT_EXIST = 'DOES_NOT_EXIST';

	/**
	 * Feed COMPLETED status which mirrors the Pinterest API feed processing result status.
	 */
	const FEED_PROCESSING_STATUS_COMPLETED = 'COMPLETED';

	/**
	 * Feed COMPLETED_EARLY status which mirrors the Pinterest API feed processing result status.
	 */
	const FEED_PROCESSING_STATUS_COMPLETED_EARLY = 'COMPLETED_EARLY';

	/**
	 * Feed DISAPPROVED status which mirrors the Pinterest API feed processing result status.
	 */
	const FEED_PROCESSING_STATUS_DISAPPROVED = 'DISAPPROVED';

	/**
	 * Feed STATUS_FAILED status which mirrors the Pinterest API feed processing result status.
	 */
	const FEED_PROCESSING_STATUS_FAILED = 'FAILED';

	/**
	 * Feed PROCESSING status which mirrors the Pinterest API feed processing result status.
	 */
	const FEED_PROCESSING_STATUS_PROCESSING = 'PROCESSING';

	/**
	 * Feed QUEUED_FOR_PROCESSING status which mirrors the Pinterest API feed processing result status.
	 */
	const FEED_PROCESSING_STATUS_QUEUED_FOR_PROCESSING = 'QUEUED_FOR_PROCESSING';

	/**
	 * Feed UNDER_APPEAL status which mirrors the Pinterest API feed processing result status.
	 */
	const FEED_PROCESSING_STATUS_UNDER_APPEAL = 'UNDER_APPEAL';

	/**
	 * Feed UNDER_REVIEW status which mirrors the Pinterest API feed processing result status.
	 */
	const FEED_PROCESSING_STATUS_UNDER_REVIEW = 'UNDER_REVIEW';

	/**
	 * Create a new feed for the given ad account.
	 *
	 * @since x.x.x
	 *
	 * @return string The Feed ID or an empty string if failed.
	 * @throws Exception PHP Exception if there is an error creating the feed, and we are throttling the requests.
	 */
	public static function create_feed(): string {
		$ad_account_id = Pinterest_For_WooCommerce()::get_setting( 'tracking_advertiser' );
		$configs       = LocalFeedConfigs::get_instance()->get_configurations();
		$config        = reset( $configs );

		$default_country  = Pinterest_For_Woocommerce()::get_base_country() ?? 'US';
		$default_currency = get_woocommerce_currency();

		/**
		 * Filters the default feed name: pinterest_for_woocommerce_unique_feed_name.
		 * This vale appears in the Catalogues - Data sources page at Pinterest.
		 *
		 * @since x.x.x
		 *
		 * @param string $feed_name The default feed name.
		 */
		$feed_name = apply_filters(
			'pinterest_for_woocommerce_unique_feed_name',
			sprintf(
				// translators: %1$s is a country ISO 2 code, %2$s is a currency ISO 3 code.
				esc_html__( 'Created by Pinterest for WooCommerce %1$s-%2$s', 'pinterest-for-woocommerce' ),
				esc_html( $default_country ),
				esc_html( $default_currency )
			)
		);

		$data = array(
			'name'                 => $feed_name,
			'format'               => 'XML',
			'location'             => $config['feed_url'],
			'catalog_type'         => 'RETAIL',
			'default_currency'     => $default_currency,
			'default_locale'       => LocaleMapper::get_locale_for_api(),
			'default_country'      => $default_country,
			'default_availability' => 'IN_STOCK',
		);

		$cache_key = PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_request_' . md5( wp_json_encode( $data ) );
		$cache     = get_transient( $cache_key );

		if ( false !== $cache ) {
			throw new Exception(
				esc_html__( 'There was a previous error trying to create a feed.', 'pinterest-for-woocommerce' ),
				(int) $cache
			);
		}

		try {
			$feed = APIV5::create_feed( $data, $ad_account_id );
		} catch ( Throwable $th ) {
			$delay = Pinterest_For_Woocommerce()::get_data( 'create_feed_delay' ) ?? MINUTE_IN_SECONDS;
			set_transient( $cache_key, $th->getCode(), $delay );
			// Double the delay.
			Pinterest_For_Woocommerce()::save_data(
				'create_feed_delay',
				min( $delay * 2, 6 * HOUR_IN_SECONDS )
			);
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new Exception( $th->getMessage(), $th->getCode() );
		}

		static::invalidate_feeds_cache();

		try {
			$feed_id = static::match_local_feed_configuration_to_registered_feeds( array( $feed ) );
		} catch ( Throwable $th ) {
			$feed_id = '';
		}

		// Clean the cached delay.
		Pinterest_For_Woocommerce()::save_data( 'create_feed_delay', false );

		return $feed_id;
	}

	/**
	 * Get a specific merchant feed using the given arguments.
	 *
	 * @param string $feed_id     The ID of the feed.
	 *
	 * @return array The feed profile object.
	 *
	 * @throws PinterestApiException Pinterest API Exception.
	 */
	public static function get_feed( $feed_id ) {
		try {
			$ad_account_id = Pinterest_For_WooCommerce()::get_setting( 'tracking_advertiser' );
			$feeds         = APIV5::get_feeds( $ad_account_id );
			foreach ( $feeds['items'] as $feed ) {
				// Get the feed with the requested id if exists.
				if ( $feed_id === $feed['id'] ) {
					return $feed;
				}
			}
			// No feed found.
			return array();
		} catch ( PinterestApiException $e ) {
			Logger::log( $e->getMessage(), 'error' );
			throw $e;
		}
	}

	/**
	 * Get merchant's feeds.
	 *
	 * @return array The feed profile objects.
	 *
	 * @throws PinterestApiException Pinterest API Exception.
	 */
	public static function get_feeds(): array {
		try {
			$ad_account_id = Pinterest_For_WooCommerce()::get_setting( 'tracking_advertiser' );
			$feeds         = APIV5::get_feeds( $ad_account_id );
			return $feeds['items'] ?? array();
		} catch ( PinterestApiException $e ) {
			Logger::log( $e->getMessage(), 'error' );
			return array();
		}
	}

	/**
	 * Invalidate the merchant feeds cache.
	 *
	 * @since x.x.x
	 *
	 * @return bool True if the cache was invalidated, false otherwise.
	 */
	public static function invalidate_feeds_cache() {
		$ad_account_id = Pinterest_For_WooCommerce()::get_setting( 'tracking_advertiser' );
		return APIV5::invalidate_feeds_cache( $ad_account_id );
	}

	/**
	 * Verify if the local feed is already registered to the merchant.
	 * Return its ID if it is.
	 *
	 * @param array $feeds The list of feeds to check against. If not set, the list will be fetched from the API.
	 *
	 * @return string Returns the ID of the feed if properly registered or an empty string otherwise.
	 * @throws PinterestApiException Pinterest API Exception.
	 * @throws PinterestApiLocaleException No valid locale found to check for the registered feed.
	 */
	public static function match_local_feed_configuration_to_registered_feeds( array $feeds = array() ): string {
		$configs       = LocalFeedConfigs::get_instance()->get_configurations();
		$config        = reset( $configs );
		$local_path    = $config['feed_url'];
		$local_country = Pinterest_For_Woocommerce()::get_base_country() ?? 'US';
		$local_locale  = LocaleMapper::get_locale_for_api();

		if ( empty( $feeds ) ) {
			$feeds = static::get_feeds();
		}

		foreach ( $feeds as $feed ) {
			$does_match = $local_path === $feed['location'];
			$does_match = $does_match && $local_country === $feed['default_country'];
			$does_match = $does_match && $local_locale === $feed['default_locale'];
			if ( $does_match ) {
				// We can assume we're on the same site.
				return $feed['id'];
			}
		}

		return '';
	}

	/**
	 * Check if the registered feed is enabled.
	 *
	 * @param string $feed_id The ID of the feed.
	 *
	 * @return bool True if the feed is active, false otherwise.
	 *@since 1.2.13
	 *
	 */
	public static function is_local_feed_enabled( string $feed_id ): bool {
		if ( empty( $feed_id ) ) {
			return false;
		}

		$feed = static::get_feed( $feed_id );
		return 'ACTIVE' === ( $feed['status'] ?? '' );
	}

	/**
	 * Enabled the feed.
	 *
	 * @since x.x.x
	 *
	 * @param string $feed_id The ID of the feed.
	 *
	 * @return bool True if the feed is has been enabled, false otherwise.
	 */
	public static function enabled_feed( string $feed_id ): bool {
		try {
			$ad_account_id = Pinterest_For_WooCommerce()::get_setting( 'tracking_advertiser' );
			APIV5::enable_feed( $ad_account_id, $feed_id );
			// We don't need to check the status, lets just invalidate the cache for extra safety.
			self::invalidate_feeds_cache();
			return true;
		} catch ( Throwable $th ) {
			Logger::log( $th->getMessage(), 'error' );
			return false;
		}
	}

	/**
	 * Enabled the feed.
	 *
	 * @since x.x.x
	 *
	 * @param string $feed_id The ID of the feed.
	 *
	 * @return bool True if the feed is has been disabled, false otherwise.
	 */
	public static function disable_feed( string $feed_id ): bool {
		try {
			$ad_account_id = Pinterest_For_WooCommerce()::get_setting( 'tracking_advertiser' );
			APIV5::disable_feed( $ad_account_id, $feed_id );
			return true;
		} catch ( Throwable $th ) {
			Logger::log( $th->getMessage(), 'error' );
			return false;
		}
	}

	/**
	 * Get the latest report of the active feed related to the last attempt to process and ingest our feed.
	 *
	 * @since x.x.x
	 *
	 * @param string $feed_id Pinterest feed ID.
	 *
	 * @return array The feed ingestion and processing report or empty array.
	 */
	public static function get_feed_recent_processing_results( $feed_id ): array {
		try {
			$ad_account_id = Pinterest_For_WooCommerce()::get_setting( 'tracking_advertiser' );
			$feed_report   = APIV5::get_feed_processing_results( $feed_id, $ad_account_id );
		} catch ( PinterestApiException $e ) {
			return array();
		}

		return $feed_report['items'][0] ?? array();
	}

	/**
	 * Get the feed report items issues.
	 *
	 * @since x.x.x
	 *
	 * @param string $feed_processing_result_id The feed processing result ID.
	 * @param int    $per_page                  The number of items to return per page. Default 25.
	 *
	 * @return array
	 */
	public static function get_feed_processing_result_items_issues( $feed_processing_result_id, $per_page = 25 ): array {
		try {
			$feed_report = APIV5::get_feed_processing_result_items_issues( $feed_processing_result_id, $per_page );
		} catch ( PinterestApiException $e ) {
			return array();
		}

		return $feed_report['items'] ?? array();
	}
}
