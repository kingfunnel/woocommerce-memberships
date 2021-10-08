<?php
/**
 * WooCommerce Memberships
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Memberships to newer
 * versions in the future. If you wish to customize WooCommerce Memberships for your
 * needs please refer to https://docs.woocommerce.com/document/woocommerce-memberships/ for more information.
 *
 * @author    SkyVerge
 * @copyright Copyright (c) 2014-2021, SkyVerge, Inc. (info@skyverge.com)
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace SkyVerge\WooCommerce\Memberships;

use SkyVerge\WooCommerce\PluginFramework\v5_10_6 as Framework;

defined( 'ABSPATH' ) or exit;

/**
 * Memberships shortcodes.
 *
 * This class is responsible for adding and handling shortcodes for Memberships.
 *
 * @since 1.21.0
 */
class Shortcodes {


	/**
	 * Initializes and registers Memberships shortcodes.
	 *
	 * @since 1.21.0
	 */
	public static function initialize() {

		$shortcodes = [
			'wcm_directory'          => __CLASS__ . '::directory',
			'wcm_restrict'           => __CLASS__ . '::restrict',
			'wcm_nonmember'          => __CLASS__ . '::nonmember',
			'wcm_content_restricted' => __CLASS__ . '::content_restricted',
			'wcm_discounted_product' => __CLASS__ . '::has_product_discount',
			'wcm_product_discount'   => __CLASS__ . '::get_product_discount',
		];

		foreach ( $shortcodes as $shortcode => $function ) {

			/**
			 * Filters a Memberships shortcode tag.
			 *
			 * @since 1.0.0
			 *
			 * @param string $shortcode shortcode tag
			 */
			add_shortcode( apply_filters( "{$shortcode}_shortcode_tag", $shortcode ), $function );
		}
	}


	/**
	 * Restrict content shortcode.
	 *
	 * Shortcode: [wcm_restrict]
	 * Usage: [wcm_restrict plans="{int|int[]|string|string[]}" delay="{string|datetime}" start_after_trial="{yes/no}"]<Content, HTML>[/wcm_restrict]
	 *
	 * Attributes usage:
	 *
	 * - plans: the plan slugs or IDs to limit the wrapped content to certain members
	 * - delay: a period of time (e.g. '5 days', '2 weeks', '3 months', '1 year') or a fixed date that can be parsed by PHP to delay access to the wrapped content by a certain time, or makes it available on a particular date
	 * - start_after_trial: either 'yes' or 'no' -  delays access to the wrapped content until a trial period is over (when WooCommerce Subscriptions is in use)
	 *
	 * @internal
	 *
	 * @since 1.21.0
	 *
	 * @param array $atts shortcode attributes
	 * @param string|null $content the content
	 * @return string HTML output
	 */
	public static function restrict( $atts, $content = null ) {

		$defaults = array(
			'plan'              => null,
			'plans'             => null,
			'delay'             => null,
			'start_after_trial' => 'no',
		);

		// filters attributes
		$atts = shortcode_atts( $defaults, is_array( $atts ) ? $atts : array(), 'wcm_restrict' );

		// parse attributes for use in function
		$plans      = self::parse_atts( 'plans', $atts, null );
		$delay      = ! empty( $atts['delay'] ) ? $atts['delay'] : null;
		$free_trial = isset( $atts['start_after_trial'] ) && 'yes' === $atts['start_after_trial'];

		ob_start();

		wc_memberships_restrict( do_shortcode( $content ), $plans, $delay, $free_trial );

		return ob_get_clean();
	}


	/**
	 * Nonmember content shortcode.
	 *
	 * Shortcode: [wcm_nonmember]
	 * Usage: [wcm_nonmember plans="{int|int[]|string|string[]}"]<Content, HTML>[/wcm_nonmember]
	 *
	 * Attributes behavior:
	 *
	 * When no attributes are specified, only non-members (including non-active members of any plan) will see shortcode content.
	 * When a 'plans' attribute is used, non-members but also members who are not in the plans specified will see the content.
	 * The 'plans' attribute can be a single or a comma separated list of plan IDs or plan names.
	 *
	 * @internal
	 *
	 * @since 1.1.0
	 *
	 * @param array $atts shortcode attributes
	 * @param string|null $content the shortcode content
	 * @return string HTML content intended to non-members (or empty string)
	 */
	public static function nonmember( $atts, $content = null ) {

		$non_member_content = '';

		// hide non-member messages for super users
		if ( ! current_user_can( 'wc_memberships_access_all_restricted_content' ) ) {

			// default attribute values
			$defaults = array(
				'plan'  => null,
				'plans' => null,
			);

			// filters attributes
			$atts = shortcode_atts( $defaults, $atts, 'wcm_nonmember' );

			$plans         = wc_memberships_get_membership_plans();
			$non_member    = true;
			$exclude_plans = self::parse_atts( 'plans', $atts, array() );

			foreach ( $plans as $plan ) {

				// excluded plans can use plan IDs or slugs
				if ( ! empty( $exclude_plans ) && ! in_array( $plan->get_id(), $exclude_plans, false ) && ! in_array( $plan->get_slug(), $exclude_plans, false ) ) {
					continue;
				}

				if ( wc_memberships_is_user_active_member( get_current_user_id(), $plan ) ) {
					$non_member = false;
					break;
				}
			}

			if ( $non_member ) {
				$non_member_content = do_shortcode( $content );
			}
		}

		return $non_member_content;
	}


	/**
	 * Restricted content messages shortcode.
	 *
	 * Shortcode: [wcm_content_restricted]
	 * Usage: [wcm_content_restricted]
	 *
	 * This shortcode has no optional attributes.
	 *
	 * @internal
	 *
	 * @since 1.21.0
	 *
	 * @param array $atts shortcode attributes
	 * @param string|null $content content
	 * @return string HTML shortcode result
	 */
	public static function content_restricted( $atts, $content = null ) {

		$object_id = isset( $_GET['r'] ) && is_numeric( $_GET['r'] ) ? absint( $_GET['r'] ) : null;
		$post      = null;
		$term      = null;
		$output    = '';

		if ( isset( $_GET['wcm_redirect_to'], $_GET['wcm_redirect_id'] ) && is_numeric( $_GET['wcm_redirect_id'] ) ) {

			$object_id        = absint( $_GET['wcm_redirect_id'] );
			$object_type_name = (string) $_GET['wcm_redirect_to'];

			if ( in_array( $object_type_name, get_post_types(), true ) ) {
				$post = get_post( $object_id );
			} else {
				$term = get_term( $object_id, $object_type_name );
			}

		} elseif ( $object_id > 0 ) {

			$term = get_term( $object_id );
			$post = get_post( $object_id );
		}

		if ( $term instanceof \WP_Term ) {

			if ( 'product_cat' === $term->taxonomy ) {

				if ( ! current_user_can( 'wc_memberships_view_restricted_product_taxonomy_term', $term->taxonomy, $term->term_id ) ) {
					$output .= \WC_Memberships_User_Messages::get_message_html( 'product_category_viewing_restricted', array( 'term' => $term ) );
				} elseif ( ! current_user_can( 'wc_memberships_view_delayed_taxonomy_term', $term->taxonomy, $term->term_id ) ) {
					$output .= \WC_Memberships_User_Messages::get_message_html( 'product_category_viewing_delayed', array( 'term' => $term ) );
				}

			} else {

				if ( ! current_user_can( 'wc_memberships_view_restricted_taxonomy_term', $term->taxonomy, $term->term_id ) ) {
					$output .= \WC_Memberships_User_Messages::get_message_html( 'content_category_restricted', array( 'term' => $term ) );
				} elseif ( ! current_user_can( 'wc_memberships_view_delayed_taxonomy_term', $term->taxonomy, $term->term_id ) ) {
					$output .= \WC_Memberships_User_Messages::get_message_html( 'content_category_delayed', array( 'term' => $term ) );
				}
			}

		} elseif ( $post instanceof \WP_Post ) {

			if ( in_array( $post->post_type, array( 'product', 'product_variation' ) ) ) {

				if ( ! current_user_can( 'wc_memberships_view_restricted_product', $post->ID ) ) {
					$output .= \WC_Memberships_User_Messages::get_message_html( 'product_viewing_restricted', array( 'post' => $post ) );
				} elseif ( ! current_user_can( 'wc_memberships_view_delayed_product', $post->ID ) ) {
					$output .= \WC_Memberships_User_Messages::get_message_html( 'product_access_delayed', array( 'post' => $post ) );
				}

			} else {

				if ( ! current_user_can( 'wc_memberships_view_restricted_post_content', $post->ID ) ) {
					$output .= \WC_Memberships_User_Messages::get_message_html( 'content_restricted', array( 'post' => $post ) );
				} elseif ( ! current_user_can( 'wc_memberships_view_delayed_post_content', $post->ID ) ) {
					$output .= \WC_Memberships_User_Messages::get_message_html( 'content_delayed', array( 'post' => $post ) );
				}
			}
		}

		return $output;
	}


	/**
	 * Displays content conditionally whether a product has discounts.
	 *
	 * Shortcode: [wcm_discounted_product]
	 * Usage: [wcm_discounted_product id="{int}" plan="{int|string}"]<Content, HTML>[/wcm_discounted_product]
	 *
	 * Optional attributes:
	 *
	 * - id: check discounts for a specific product (if unspecified will get discounts for the current product)
	 * - plans: check discounts for a specific plan (if unspecified will gather results based on all discounts offered by all plans)
	 *
	 * @internal
	 *
	 * @since 1.21.0
	 *
	 * @param array $atts shortcode arguments
	 * @param null $content the shortcode content
	 * @return string HTML shortcode result
	 */
	public static function has_product_discount( $atts, $content = null ) {

		$output   = '';
		$defaults = array(
			'id'         => null,
			'product_id' => null,
			'plan'       => null,
			'plans'      => null,
		);

		// filters shortcode attributes
		$atts       = shortcode_atts( $defaults, $atts, 'wcm_discounted_product' );
		$product_id = self::parse_atts( 'product_id', $atts );

		if ( $product_id && $product_id > 0 ) {

			$plan            = self::parse_atts( 'plans', $atts, null );
			$discount_amount = wc_memberships()->get_member_discounts_instance()->get_product_discount( $product_id, 'max', $plan );
			$output          = $discount_amount > 0 && is_string( $content ) ? do_shortcode( $content ) : '';
		}

		return $output;
	}


	/**
	 * Displays the discount for a product.
	 *
	 * Shortcode: [wcm_product_discount]
	 * Usage: [wcm_product_discount id="{int}" plan="{int|string}" display="{string}"]
	 *
	 * Optional attributes:
	 *
	 * - id: get discounts for a specific product (if unspecified will get discounts for the current product)
	 * - plans: get discounts for a specific plan (if unspecified will gather results based on all discounts offered by all plans)
	 * - display: either 'min', 'max' or 'average' discount (default 'max', i.e. the highest possible discount)
	 * - format: 'amount' (default) or 'percentage', to display the discount as a fixed price amount or a percentage of the normal price
	 *
	 * @internal
	 *
	 * @since 1.21.0
	 *
	 * @param array $atts shortcode arguments
	 * @param null|string $content the shortcode content
	 * @return string formatted discount HTML
	 */
	public static function get_product_discount( $atts, $content = null ) {

		// shortcode defaults
		$defaults = array(
			'id'         => null,
			'product_id' => null,
			'plan'       => null,
			'plans'      => null,
			'display'    => 'max',
			'format'     => 'amount',
		);

		$atts       = shortcode_atts( $defaults, $atts, 'wcm_product_discount' );
		$product_id = self::parse_atts( 'product_id', $atts );

		if ( $product_id && $product_id > 0 ) {

			$plan   = self::parse_atts( 'plans', $atts, null );
			$value  = in_array( $atts['display'], array( 'min', 'max', 'avg', 'average', 'mean' ), true ) ? $atts['display'] : 'max';
			$format = in_array( $atts['format'], array( 'amount', 'percentage', 'percent', '%' ), true ) ? $atts['format'] : '%';
			$output = wc_memberships()->get_member_discounts_instance()->get_product_discount_html( $product_id, $value, $format, $plan );

		} else {

			$output = wc_price( 0 );
		}

		return $output;
	}


	/**
	 * Outputs a members directory.
	 *
	 * Shortcode: [wcm_directory]
	 * Usage: [wcm_directory plans="{string}" status="{string}" per_page="{int}" bios="{string}" avatars="{string}" avatar_size="{int}"]
	 *
	 * Optional attributes:
	 *
	 * - plans: comma-separated list of plans to limit returned members by plan (default 'any')
	 * - status: comma-separated list of the status of the members to list for output (default 'any')
	 * - per_page: number of members to show per page (default 12)
	 * - bios: 'yes' or 'no' if user descriptions should be shown (default 'yes')
	 * - avatars: 'yes' or 'no' if avatars should be shown (default 'yes')
	 * - avatar_size: pixel size for avatars (default 128)
	 *
	 * @internal
	 *
	 * @since 1.21.0
	 *
	 * @param array|string $atts shortcode arguments
	 * @return string formatted discount HTML
	 */
	public static function directory( $atts ) : string {
		global $paged;

		$atts = shortcode_atts( [
			'plans'       => 'any',
			'status'      => 'any',
			'per_page'    => 12,
			'bios'        => 'yes',
			'avatars'     => 'yes',
			'avatar_size' => 128,
		], $atts );

		$members       = self::get_directory_members( $atts );
		$display_count = (int) $atts['per_page'];
		$total_found   = count( $members );
		$total_pages   = ceil( $total_found / $display_count );

		ob_start();

		if ( ! empty( $members ) ) {

			// determine if we should be slicing member display into pages
			if ( $total_pages > 1 ) {
				$start   = $paged ? ( absint( $paged ) - 1 ) * $display_count : 0;
				$members = array_slice( $members, $start, $display_count, true );
			}

			wc_get_template( 'member-directory-start.php', [
				'members' => $members,
			] );

			foreach ( $members as $member => $membership_ids ) {

				// setup a membership object for our template
				setup_postdata( get_post( $membership_ids[0] ) );

				$plans = [];

				// build a set of plans for this member
				foreach ( $membership_ids as $id ) {
					$plan_id           = wp_get_post_parent_id( $id );
					$plans[ $plan_id ] = get_the_title( $plan_id );
				}

				/**
				 * Filters the plans that will be listed in the template.
				 *
				 * @since 1.21.0
				 *
				 * @param string[] $plans the plans for this member (slugs)
				 * @param int $member the member's WP_User ID
				 */
				$plans = (array) apply_filters( 'wc_memberships_member_directory_listing_plans', $plans, $member );

				wc_get_template( 'member-directory-listing.php', [
					'shortcode' => $atts,
					'plans'     => $plans,
				] );
			}

			wc_get_template( 'member-directory-navigation.php', [
				'total_pages' => $total_pages,
			] );

			// reset queried object
			wp_reset_query();

			wc_get_template( 'member-directory-end.php', [
				'members' => $members,
			] );
		}

		return ob_get_clean();
	}


	/**
	 * Gets the set of members for the directory shortcode.
	 *
	 * @since 1.21.0
	 *
	 * @param array $atts shortcode attributes
	 * @return array $members the set of members for the directory
	 */
	private static function get_directory_members( array $atts ) : array {
		global $wpdb;

		$members     = [];
		$members_raw = $wpdb->get_results( self::prepare_members_directory_query( $atts ), ARRAY_A );

		foreach( $members_raw as $member ) {
			if ( array_key_exists( $member['post_author'], $members ) ) {
				$members[ (int) $member['post_author'] ][] = (int) $member['ID'];
			} else {
				$members[ (int) $member['post_author'] ] = [ (int) $member['ID'] ];
			}
		}

		/**
		 * Filters the members included in the directory.
		 *
		 * @since 1.21.0
		 *
		 * @param array $members the members returned by the shortcode args (associative array of user IDs and user memberships per user)
		 */
		return (array) apply_filters( 'wc_memberships_member_directory_included_members', $members );
	}


	/**
	 * Builds the SQL to find all members.
	 *
	 * @since 1.21.0
	 *
	 * @param array $atts shortcode attributes
	 * @return string the SQL query
	 */
	private static function prepare_members_directory_query( array $atts ) : string {
		global $wpdb;

		// build the basic SQL to find all authors of a user membership
		$sql = "
			SELECT {$wpdb->posts}.post_author, {$wpdb->posts}.ID
			FROM {$wpdb->posts}
			WHERE {$wpdb->posts}.post_type = 'wc_user_membership'
		";

		// now add a clause for status if set in the shortcode
		if ( 'any' !== $atts['status'] ) {

			$status_list  = array_map( 'trim', explode( ',', $atts['status'] ) );
			$status_where = [];

			foreach ( $status_list as $key => $status ) {

				// ensure we have status prefixes for the direct SQL query
				if ( strpos( $status, 'wcm-' ) !== 0 ) {
					$status_list[ $key ] = "wcm-{$status}";
				}

				// store this condition
				if ( in_array( $status_list[ $key ], wc_memberships_get_user_membership_statuses( false, true ), false ) ) {
					$status_where[] = $wpdb->prepare( "{$wpdb->posts}.post_status = '%s'", $status_list[ $key ] );
				}
			}

			// add a condition for all statuses
			if ( ! empty( $status_where ) ) {
				$sql .= " AND (" . implode( ' OR ', $status_where ) . ")";
			}
		}

		// check for plans by searching parent IDs
		if ( 'any' !== $atts['plans'] ) {

			$plans_list  = array_map( 'trim', explode( ',', $atts['plans'] ) );
			$plans_where = [];

			foreach ( $plans_list as $plan_slug ) {

				$plan = wc_memberships_get_membership_plan( $plan_slug );

				// be sure the plan slug is a valid one
				if ( $plan instanceof \WC_Memberships_Membership_Plan ) {
					$plans_where[] = $wpdb->prepare( "{$wpdb->posts}.post_parent = '%s'", $plan->get_id() );
				}
			}

			// add a condition for all plans
			if ( ! empty( $plans_where ) ) {
				$sql .= " AND (" . implode( ' OR ', $plans_where ) . ")";
			}
		}

		return $sql;
	}


	/**
	 * Parses common shortcode attributes into useful variables (helper method).
	 *
	 * Do not open this method to public.
	 *
	 * @since 1.21.0
	 *
	 * @param string $key item of entity to determine from $atts
	 * @param array $atts shortcode attributes
	 * @param null|mixed $default default value to return
	 * @return mixed
	 */
	private static function parse_atts( $key, $atts, $default = null ) {
		global $post, $product;

		$value = $default;

		switch ( $key ) {

			case 'product_id' :

				// we accept both 'id' or 'product_id' as long as they're set by the user (non null)
				if ( isset( $atts['id'] ) && null !== $atts['id'] ) {
					$product_id = is_numeric( $atts['id'] ) ? (int) $atts['id'] : $default;
				} elseif ( isset( $atts['product_id'] ) && null !== $atts['product_id'] ) {
					$product_id = is_numeric( $atts['product_id'] ) ? (int) $atts['product_id'] : $default;
				} elseif( $product instanceof \WP_Product ) {
					$product_id = $product->get_id();
				} elseif ( $post instanceof \WP_Post ) {
					$product_id = (int) $post->ID;
				} else {
					$product_id = $default;
				}

				$value = $product_id;

			break;

			case 'plan' :
			case 'plans' :

				$plan     = $default;
				$plan_att = null;

				// we accept either 'plan' or 'plans'
				if ( ! empty( $atts['plan'] ) ) {
					$plan_att = trim( $atts['plan'] );
				} elseif ( ! empty( $atts['plans'] ) ) {
					$plan_att = trim( $atts['plans'] );
				}

				if ( is_numeric( $plan_att ) ) {

					$plan = (int) $plan_att;

				} elseif ( '' !== $plan_att && is_string( $plan_att ) ) {

					$plan_ids          = [];
					$plan_ids_or_slugs = array_map( 'trim', explode( ',', $plan_att ) );

					foreach ( $plan_ids_or_slugs as $plan_id_or_slug ) {

						if ( ! $plan_id_or_slug || ( ! is_numeric( $plan_id_or_slug ) && ! is_string( $plan_id_or_slug ) ) ) {
							continue;
						}

						$plan = wc_memberships_get_membership_plan( $plan_id_or_slug );

						if ( $plan ) {
							$plan_ids[] = $plan->get_id();
						}
					}

					$plan = empty( $plan_ids ) ? null : $plan_ids;
				}

				$value = $plan;

			break;
		}

		return $value;
	}


}
