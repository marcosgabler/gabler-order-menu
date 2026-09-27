<?php
/**
 * AJAX handler – persists the new drag-and-drop order to the database.
 *
 * @package OrderMenu
 */

defined( 'ABSPATH' ) || exit;

class OM_Ajax {

    public static function init(): void {
        add_action( 'wp_ajax_om_save_order',       [ __CLASS__, 'save_order'       ] );
        add_action( 'wp_ajax_om_reset_order',      [ __CLASS__, 'reset_order'      ] );
        add_action( 'wp_ajax_om_toggle_frontend',  [ __CLASS__, 'toggle_frontend'  ] );
    }

    // ── Save ────────────────────────────────────────────────────────────────
    public static function save_order(): void {
        // [SEC-1] Nonce verification
        check_ajax_referer( 'om_save_order', 'nonce' );

        // [SEC-2] Capability check – die() ensures execution stops even if
        //         wp_send_json_error() is somehow bypassed.
        if ( ! current_user_can( 'edit_pages' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sem permissão.', 'order-menu' ) ], 403 );
            wp_die();
        }

        // [SEC-3] Sanitize raw input before JSON-decoding
        $raw_order = isset( $_POST['order'] ) ? sanitize_textarea_field( wp_unslash( $_POST['order'] ) ) : '';

        if ( empty( $raw_order ) ) {
            wp_send_json_error( [ 'message' => __( 'Dados inválidos.', 'order-menu' ) ], 400 );
            wp_die();
        }

        $items = json_decode( $raw_order, true );

        if ( ! is_array( $items ) || empty( $items ) ) {
            wp_send_json_error( [ 'message' => __( 'JSON inválido.', 'order-menu' ) ], 400 );
            wp_die();
        }

        // [SEC-4] Enforce hard limit to prevent DoS via oversized payload
        if ( count( $items ) > 5000 ) {
            wp_send_json_error( [ 'message' => __( 'Payload excede o limite permitido.', 'order-menu' ) ], 400 );
            wp_die();
        }

        global $wpdb;
        $supported_types = OM_Admin::get_supported_types();

        foreach ( $items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $post_id    = absint( $item['id']     ?? 0 );
            $menu_order = absint( $item['order']  ?? 0 );
            $parent_id  = absint( $item['parent'] ?? 0 );

            if ( ! $post_id ) {
                continue;
            }

            // [SEC-5] Ownership check: verify the post exists, belongs to a
            //         supported type, and the current user can edit it.
            //         Prevents manipulating posts of arbitrary types/IDs.
            $post = get_post( $post_id );
            if (
                ! $post
                || ! in_array( $post->post_type, $supported_types, true )
                || ! current_user_can( 'edit_post', $post_id )
            ) {
                continue;
            }

            // [SEC-6] parent_id must also belong to the same post type
            //         (prevents cross-type hierarchy tampering)
            if ( $parent_id > 0 ) {
                $parent_post = get_post( $parent_id );
                if ( ! $parent_post || $parent_post->post_type !== $post->post_type ) {
                    $parent_id = 0;
                }
            }

            $wpdb->update(
                $wpdb->posts,
                [
                    'menu_order'  => $menu_order,
                    'post_parent' => $parent_id,
                ],
                [ 'ID' => $post_id ],
                [ '%d', '%d' ],
                [ '%d' ]
            );

            update_post_meta( $post_id, OM_META_KEY, $menu_order );

            clean_post_cache( $post_id );
        }

        wp_send_json_success( [
            'message' => __( 'Ordem salva com sucesso!', 'order-menu' ),
            'count'   => count( $items ),
        ] );
        wp_die();
    }

    // ── Reset ───────────────────────────────────────────────────────────────
    public static function reset_order(): void {
        check_ajax_referer( 'om_save_order', 'nonce' );

        if ( ! current_user_can( 'edit_pages' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sem permissão.', 'order-menu' ) ], 403 );
            wp_die();
        }

        $post_type = sanitize_key( $_POST['post_type'] ?? 'page' );

        $supported = OM_Admin::get_supported_types();
        if ( ! in_array( $post_type, $supported, true ) ) {
            wp_send_json_error( [ 'message' => __( 'Tipo inválido.', 'order-menu' ) ], 400 );
            wp_die();
        }

        global $wpdb;

        // Update via prepared statement (already safe; $post_type validated above)
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->posts} SET menu_order = 0 WHERE post_type = %s AND post_status != 'trash'",
                $post_type
            )
        );

        // [SEC-7] Use suppress_filters to avoid re-triggering pre_get_posts hooks
        $ids = get_posts( [
            'post_type'        => $post_type,
            'posts_per_page'   => -1,
            'fields'           => 'ids',
            'post_status'      => 'any',
            'suppress_filters' => true,
        ] );

        foreach ( $ids as $id ) {
            delete_post_meta( (int) $id, OM_META_KEY );
            clean_post_cache( (int) $id );
        }

        wp_send_json_success( [ 'message' => __( 'Ordem redefinida.', 'order-menu' ) ] );
        wp_die();
    }

    // ── Toggle front-end ordering ────────────────────────────────────────────
    public static function toggle_frontend(): void {
        // [SEC-8] Sanitize BEFORE building the nonce action string.
        //         An empty/invalid type is rejected immediately.
        $type = sanitize_key( $_POST['post_type'] ?? '' );

        $supported = OM_Admin::get_supported_types();
        if ( ! in_array( $type, $supported, true ) ) {
            wp_send_json_error( [ 'message' => __( 'Tipo inválido.', 'order-menu' ) ], 400 );
            wp_die();
        }

        // Nonce is verified AFTER we know $type is valid
        check_ajax_referer( 'om_toggle_' . $type, 'nonce' );

        if ( ! current_user_can( 'edit_pages' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sem permissão.', 'order-menu' ) ], 403 );
            wp_die();
        }

        // [SEC-9] Strict boolean cast – reject anything that is not 0 or 1
        $enable = isset( $_POST['enable'] ) && '1' === (string) absint( $_POST['enable'] );
        update_option( 'om_enable_' . $type, $enable );

        wp_send_json_success( [ 'enabled' => $enable ] );
        wp_die();
    }
}
