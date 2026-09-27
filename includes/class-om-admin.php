<?php
/**
 * Admin class – registers menus, enqueues assets and renders the sorter pages.
 *
 * @package OrderMenu
 */

defined( 'ABSPATH' ) || exit;

class OM_Admin {

    // ── Supported post-types ────────────────────────────────────────────────
    private static array $supported_types = [];

    public static function init(): void {
        // Allow 3rd-party code to extend supported post types
        add_action( 'init', [ __CLASS__, 'register_supported_types' ], 20 );

        add_action( 'admin_menu',            [ __CLASS__, 'add_menu_pages'    ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets'    ] );
        add_filter( 'plugin_action_links_' . plugin_basename( OM_PLUGIN_FILE ),
                    [ __CLASS__, 'plugin_action_links' ] );
    }

    // ── Activation / Deactivation ───────────────────────────────────────────
    public static function on_activate(): void {
        // Nothing to create – we use native post_meta / menu_order column
    }

    public static function on_deactivate(): void {
        // Intentionally left blank
    }

    // ── Post-types ──────────────────────────────────────────────────────────
    public static function register_supported_types(): void {
        $default = [ 'page', 'post' ];

        /**
         * Filter the post types handled by Order Menu.
         *
         * @param string[] $types Post-type slugs.
         */
        // [SEC-10] Sanitize every slug returned by 3rd-party filter code.
        //          Prevents arbitrary strings from poisoning the type list.
        $raw = apply_filters( 'om_supported_post_types', $default );

        self::$supported_types = array_values(
            array_filter(
                array_map( 'sanitize_key', (array) $raw ),
                function ( string $slug ): bool {
                    return $slug !== '' && post_type_exists( $slug );
                }
            )
        );
    }

    public static function get_supported_types(): array {
        return self::$supported_types;
    }

    // ── Admin menu ──────────────────────────────────────────────────────────
    public static function add_menu_pages(): void {
        add_menu_page(
            __( 'Order Menu', 'order-menu' ),
            __( 'Order Menu', 'order-menu' ),
            'edit_pages',
            'order-menu',
            [ __CLASS__, 'render_page' ],
            'dashicons-menu-alt',
            60
        );

        // Sub-pages per post-type
        foreach ( self::$supported_types as $type ) {
            $obj = get_post_type_object( $type );
            if ( ! $obj ) {
                continue;
            }

            add_submenu_page(
                'order-menu',
                sprintf(
                    /* translators: %s: post type label */
                    __( 'Sort %s', 'order-menu' ),
                    $obj->labels->name
                ),
                $obj->labels->name,
                'edit_pages',
                'order-menu-' . $type,
                function () use ( $type ) {
                    self::render_page( $type );
                }
            );
        }

        // Remove the auto-generated duplicate of the parent page
        remove_submenu_page( 'order-menu', 'order-menu' );
    }

    // ── Assets ──────────────────────────────────────────────────────────────
    public static function enqueue_assets( string $hook ): void {
        if ( strpos( $hook, 'order-menu' ) === false ) {
            return;
        }

        // jQuery UI Sortable (bundled with WP)
        wp_enqueue_script( 'jquery-ui-sortable' );

        wp_enqueue_style(
            'om-admin',
            OM_PLUGIN_URL . 'assets/css/admin.css',
            [],
            OM_VERSION
        );

        wp_enqueue_script(
            'om-admin',
            OM_PLUGIN_URL . 'assets/js/admin.js',
            [ 'jquery', 'jquery-ui-sortable' ],
            OM_VERSION,
            true
        );

        wp_localize_script( 'om-admin', 'omData', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'om_save_order' ),
            'i18n'    => [
                'saving'  => __( 'Salvando…',           'order-menu' ),
                'saved'   => __( 'Ordem salva!',         'order-menu' ),
                'error'   => __( 'Erro ao salvar.',      'order-menu' ),
                'confirm' => __( 'Redefinir a ordem de todos os itens?', 'order-menu' ),
            ],
        ] );
    }

    // ── Render page ─────────────────────────────────────────────────────────
    public static function render_page( string $post_type = 'page' ): void {
        if ( ! current_user_can( 'edit_pages' ) ) {
            wp_die( esc_html__( 'Sem permissão.', 'order-menu' ) );
        }

        // Validate post type
        if ( ! in_array( $post_type, self::$supported_types, true ) ) {
            $post_type = self::$supported_types[0] ?? 'page';
        }

        $obj   = get_post_type_object( $post_type );
        $label = $obj ? $obj->labels->name : $post_type;

        // Query posts (no LIMIT – paginate only if truly massive)
        $posts = get_posts( [
            'post_type'      => $post_type,
            'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
            'posts_per_page' => -1,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
            'post_parent'    => 0,             // top-level only
        ] );

        // Build a tree (pages support hierarchy)
        $tree = self::build_tree( $post_type );

        // The template defines & calls om_render_tree_items() (standalone function)
        include OM_PLUGIN_DIR . 'templates/admin-page.php';
    }

    // ── Build hierarchical tree ──────────────────────────────────────────────
    /**
     * Returns a nested array representing the post hierarchy.
     *
     * @param string $post_type
     * @param int    $parent
     * @return array<int,array{post:\WP_Post,children:array}>
     */
    public static function build_tree( string $post_type, int $parent = 0 ): array {
        $posts = get_posts( [
            'post_type'      => $post_type,
            'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
            'posts_per_page' => -1,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
            'post_parent'    => $parent,
        ] );

        $tree = [];
        foreach ( $posts as $post ) {
            $tree[] = [
                'post'     => $post,
                'children' => self::build_tree( $post_type, $post->ID ),
            ];
        }
        return $tree;
    }

    // ── Plugin action links ─────────────────────────────────────────────────
    public static function plugin_action_links( array $links ): array {
        $url = admin_url( 'admin.php?page=order-menu-page' );
        array_unshift(
            $links,
            '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Configurar', 'order-menu' ) . '</a>'
        );
        return $links;
    }
}
