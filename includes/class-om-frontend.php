<?php
/**
 * Front-end class – applies the custom order to WP_Query on the front-end.
 *
 * @package OrderMenu
 */

defined( 'ABSPATH' ) || exit;

class OM_Frontend {

    public static function init(): void {
        // Apply custom menu_order on front-end page/post listings
        add_action( 'pre_get_posts', [ __CLASS__, 'apply_order' ] );

        // REST API: honour menu_order too
        add_filter( 'rest_page_query', [ __CLASS__, 'rest_apply_order' ], 10, 2 );
        add_filter( 'rest_post_query', [ __CLASS__, 'rest_apply_order' ], 10, 2 );
    }

    // ── WP_Query ────────────────────────────────────────────────────────────
    public static function apply_order( WP_Query $query ): void {
        // Only touch main or archive queries for supported post-types
        if ( is_admin() ) {
            return;
        }

        $supported = OM_Admin::get_supported_types();
        $post_type = (array) $query->get( 'post_type' );
        if ( empty( $post_type ) ) {
            $post_type = [ 'post' ];
        }

        $relevant = array_intersect( $post_type, $supported );
        if ( empty( $relevant ) ) {
            return;
        }

        // Only override when the caller hasn't set an explicit orderby
        $orderby = $query->get( 'orderby' );
        if ( ! empty( $orderby ) && $orderby !== 'date' ) {
            return;
        }

        // Apply option (per-type toggles stored in WP options)
        foreach ( $relevant as $type ) {
            $enabled = get_option( 'om_enable_' . $type, true );
            if ( ! $enabled ) {
                return;
            }
        }

        $query->set( 'orderby', 'menu_order' );
        $query->set( 'order',   'ASC' );
    }

    // ── REST API ─────────────────────────────────────────────────────────────
    public static function rest_apply_order( array $args, WP_REST_Request $request ): array {
        // Only apply when the client didn't ask for a specific order
        if ( isset( $args['orderby'] ) && $args['orderby'] !== 'date' ) {
            return $args;
        }

        $type    = $args['post_type'] ?? 'post';
        $enabled = get_option( 'om_enable_' . $type, true );

        if ( $enabled ) {
            $args['orderby'] = 'menu_order';
            $args['order']   = 'ASC';
        }

        return $args;
    }
}
