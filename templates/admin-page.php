<?php
/**
 * Admin page template – drag-and-drop sorter.
 *
 * Variables available (set by OM_Admin::render_page):
 *   string   $post_type
 *   string   $label
 *   array    $tree      built by OM_Admin::build_tree()
 *
 * @package OrderMenu
 */

defined( 'ABSPATH' ) || exit;

$supported = OM_Admin::get_supported_types();
$tabs      = [];
foreach ( $supported as $_t ) {
    $_obj = get_post_type_object( $_t );
    if ( $_obj ) {
        $tabs[ $_t ] = $_obj->labels->name;
    }
}
?>
<div class="wrap om-wrap" id="om-app" data-post-type="<?php echo esc_attr( $post_type ); ?>">

    <!-- ── Header ──────────────────────────────────────────────────────── -->
    <div class="om-header">
        <div class="om-header__logo">
            <span class="dashicons dashicons-menu-alt"></span>
        </div>
        <div class="om-header__text">
            <h1 class="om-header__title"><?php esc_html_e( 'Order Menu', 'order-menu' ); ?></h1>
            <p class="om-header__subtitle">
                <?php
                // [SEC-11] wp_kses limits allowed HTML from printf so that
                //          a malicious post-type label cannot inject markup.
                echo wp_kses(
                    sprintf(
                        /* translators: %s: post type label */
                        __( 'Arraste e solte para reordenar seus %s', 'order-menu' ),
                        '<strong>' . esc_html( $label ) . '</strong>'
                    ),
                    [ 'strong' => [] ]
                );
                ?>
            </p>
        </div>
        <div class="om-header__actions">
            <button id="om-btn-save" class="om-btn om-btn--primary" disabled>
                <span class="dashicons dashicons-saved"></span>
                <?php esc_html_e( 'Salvar Ordem', 'order-menu' ); ?>
            </button>
            <button id="om-btn-reset" class="om-btn om-btn--ghost">
                <span class="dashicons dashicons-image-rotate"></span>
                <?php esc_html_e( 'Redefinir', 'order-menu' ); ?>
            </button>
        </div>
    </div>

    <!-- ── Notice ──────────────────────────────────────────────────────── -->
    <div id="om-notice" class="om-notice" aria-live="polite" hidden></div>

    <!-- ── Tabs ────────────────────────────────────────────────────────── -->
    <?php if ( count( $tabs ) > 1 ) : ?>
    <nav class="om-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Tipos de conteúdo', 'order-menu' ); ?>">
        <?php foreach ( $tabs as $t_slug => $t_label ) : ?>
        <a
            role="tab"
            aria-selected="<?php echo esc_attr( $t_slug === $post_type ? 'true' : 'false' ); ?>"
            class="om-tab<?php echo $t_slug === $post_type ? ' om-tab--active' : ''; ?>"
            href="<?php echo esc_url( admin_url( 'admin.php?page=order-menu-' . $t_slug ) ); ?>"
        ><?php echo esc_html( $t_label ); ?></a>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <!-- ── Legend ──────────────────────────────────────────────────────── -->
    <div class="om-legend">
        <span class="om-legend__item"><span class="om-badge om-badge--publish"></span><?php esc_html_e( 'Publicado', 'order-menu' ); ?></span>
        <span class="om-legend__item"><span class="om-badge om-badge--draft"></span><?php esc_html_e( 'Rascunho', 'order-menu' ); ?></span>
        <span class="om-legend__item"><span class="om-badge om-badge--pending"></span><?php esc_html_e( 'Pendente', 'order-menu' ); ?></span>
        <span class="om-legend__item"><span class="om-badge om-badge--private"></span><?php esc_html_e( 'Privado', 'order-menu' ); ?></span>
        <span class="om-legend__item om-legend__item--hint">
            <span class="dashicons dashicons-info-outline"></span>
            <?php esc_html_e( 'Arraste os itens para reordenar. Itens aninhados preservam hierarquia.', 'order-menu' ); ?>
        </span>
    </div>

    <!-- ── Sortable list ────────────────────────────────────────────────── -->
    <div class="om-card">
        <?php if ( empty( $tree ) ) : ?>
            <div class="om-empty">
                <span class="dashicons dashicons-editor-ul"></span>
                <p><?php esc_html_e( 'Nenhum item encontrado para este tipo de conteúdo.', 'order-menu' ); ?></p>
            </div>
        <?php else : ?>
            <ul class="om-sortable om-sortable--root" id="om-sortable-root">
                <?php om_render_tree_items( $tree, $post_type ); ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- ── Settings card ───────────────────────────────────────────────── -->
    <div class="om-card om-card--settings">
        <h2 class="om-settings__title">
            <span class="dashicons dashicons-admin-settings"></span>
            <?php esc_html_e( 'Configurações', 'order-menu' ); ?>
        </h2>

        <label class="om-toggle" for="om-enable-front">
            <input
                type="checkbox"
                id="om-enable-front"
                class="om-toggle__input"
                data-nonce="<?php echo esc_attr( wp_create_nonce( 'om_toggle_' . $post_type ) ); ?>"
                data-type="<?php echo esc_attr( $post_type ); ?>"
                data-action="om_toggle_frontend"
                <?php checked( (bool) get_option( 'om_enable_' . $post_type, true ) ); ?>
            >
            <span class="om-toggle__track"><span class="om-toggle__thumb"></span></span>
            <span class="om-toggle__label">
                <?php
                echo wp_kses(
                    sprintf(
                        /* translators: %s: post type label */
                        __( 'Aplicar ordem personalizada de %s no front-end automaticamente', 'order-menu' ),
                        '<strong>' . esc_html( $label ) . '</strong>'
                    ),
                    [ 'strong' => [] ]
                );
                ?>
            </span>
        </label>

        <p class="om-settings__hint">
            <?php esc_html_e( 'Quando ativo, o WP_Query e a API REST retornam os itens na ordem definida acima.', 'order-menu' ); ?>
        </p>
    </div>

</div><!-- /#om-app -->
<?php
// ─────────────────────────────────────────────────────────────────────────────
// Standalone recursive renderer (called from template, not from class)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Recursively render sortable list items.
 *
 * @param array  $tree       Result of OM_Admin::build_tree().
 * @param string $post_type  Current post type slug.
 */
function om_render_tree_items( array $tree, string $post_type ): void {
    $stati_labels = [
        'publish' => __( 'Publicado', 'order-menu' ),
        'draft'   => __( 'Rascunho',  'order-menu' ),
        'pending' => __( 'Pendente',  'order-menu' ),
        'private' => __( 'Privado',   'order-menu' ),
    ];

    foreach ( $tree as $node ) {
        /** @var WP_Post $post */
        $post      = $node['post'];
        $children  = $node['children'];
        $status    = $post->post_status;
        $edit_url  = get_edit_post_link( $post->ID ) ?: '#';
        $view_url  = get_permalink( $post->ID )      ?: '#';
        $thumb_url = has_post_thumbnail( $post ) ? get_the_post_thumbnail_url( $post, 'thumbnail' ) : '';
        $pt_icon   = $post_type === 'page' ? 'admin-page' : 'admin-post';
        ?>
        <li
            class="om-item"
            data-id="<?php echo esc_attr( $post->ID ); ?>"
            data-parent="<?php echo esc_attr( $post->post_parent ); ?>"
            data-status="<?php echo esc_attr( $status ); ?>"
        >
            <div class="om-item__inner">

                <span class="om-item__handle dashicons dashicons-move"
                      title="<?php esc_attr_e( 'Arrastar para reordenar', 'order-menu' ); ?>"></span>

                <div class="om-item__thumb">
                    <?php if ( $thumb_url ) : ?>
                        <img src="<?php echo esc_url( $thumb_url ); ?>" alt="" loading="lazy">
                    <?php else : ?>
                        <span class="dashicons dashicons-<?php echo esc_attr( $pt_icon ); ?>"></span>
                    <?php endif; ?>
                </div>

                <div class="om-item__content">
                    <span class="om-item__title">
                        <?php echo esc_html( $post->post_title ?: __( '(sem título)', 'order-menu' ) ); ?>
                    </span>
                    <span class="om-item__meta">
                        <span class="om-badge om-badge--<?php echo esc_attr( $status ); ?>">
                            <?php echo esc_html( $stati_labels[ $status ] ?? $status ); ?>
                        </span>
                        <?php if ( $post->post_parent ) : ?>
                            <span class="om-item__parent-label">
                                <?php
                                printf(
                                    /* translators: %s: parent post title */
                                    esc_html__( 'Filho de: %s', 'order-menu' ),
                                    esc_html( get_the_title( $post->post_parent ) )
                                );
                                ?>
                            </span>
                        <?php endif; ?>
                        <span class="om-item__order-num">#<span class="om-order-val"><?php echo esc_html( $post->menu_order ); ?></span></span>
                    </span>
                </div>

                <div class="om-item__actions">
                    <a href="<?php echo esc_url( $edit_url ); ?>"
                       class="om-action-btn" title="<?php esc_attr_e( 'Editar', 'order-menu' ); ?>"
                       target="_blank" rel="noopener noreferrer">
                        <span class="dashicons dashicons-edit"></span>
                    </a>
                    <?php if ( $status === 'publish' ) : ?>
                    <a href="<?php echo esc_url( $view_url ); ?>"
                       class="om-action-btn" title="<?php esc_attr_e( 'Visualizar', 'order-menu' ); ?>"
                       target="_blank" rel="noopener noreferrer">
                        <span class="dashicons dashicons-visibility"></span>
                    </a>
                    <?php endif; ?>
                </div>

            </div><!-- /.om-item__inner -->

            <?php if ( ! empty( $children ) ) : ?>
                <ul class="om-sortable om-sortable--nested">
                    <?php om_render_tree_items( $children, $post_type ); ?>
                </ul>
            <?php endif; ?>

        </li>
        <?php
    }
}
