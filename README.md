=== Order Menu – Pages & Posts Sorter ===
Contributors:      seunome
Tags:              menu order, page order, post order, drag and drop, sort
Requires at least: 5.8
Tested up to:      6.6
Requires PHP:      7.4
Stable tag:        1.0.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Reordene Páginas e Posts via arrastar-e-soltar no painel WordPress. A ordem é aplicada automaticamente no front-end.

== Description ==

**Order Menu** adiciona uma interface intuitiva de arrastar-e-soltar ao painel do WordPress, permitindo que você reordene facilmente Páginas e Posts (e outros tipos de post personalizados).

**Recursos principais:**

* 🖱️ Interface drag-and-drop com jQuery UI Sortable
* 🌳 Suporte a hierarquia (páginas filhas aninhadas)
* 🎨 Design moderno e responsivo
* ⚡ Salva na coluna nativa `menu_order` do WordPress
* 🔄 Aplica automaticamente a ordem no front-end (WP_Query + REST API)
* 🔌 Extensível via filtro `om_supported_post_types`
* 🛡️ Nonces e verificações de capacidade em todas as ações AJAX
* 🌍 Preparado para tradução (i18n)

**Como usar:**

1. Instale e ative o plugin
2. Acesse **Order Menu** no menu lateral do WordPress
3. Escolha o tipo de conteúdo (Páginas ou Posts)
4. Arraste os itens para a ordem desejada
5. Clique em **Salvar Ordem**

**Adicionar suporte a Custom Post Types:**

```php
add_filter( 'om_supported_post_types', function( $types ) {
    $types[] = 'produto';  // slug do seu CPT
    return $types;
} );
```

== Installation ==

1. Envie a pasta `order-menu` para `/wp-content/plugins/`
2. Ative o plugin em **Plugins > Plugins Instalados**
3. Acesse **Order Menu** no painel

== Frequently Asked Questions ==

= A ordem funciona com o Elementor / page builders? =
Sim, pois a ordem é salva na coluna nativa `menu_order` do banco de dados.

= Posso reordenar Custom Post Types? =
Sim! Use o filtro `om_supported_post_types` para adicionar qualquer CPT.

= A ordem é aplicada nos menus de navegação? =
Não. Este plugin ordena a listagem de posts/páginas, não os menus de navegação
do WordPress (gerenciados em Aparência > Menus).

== Changelog ==

= 1.0.0 =
* Lançamento inicial

== Upgrade Notice ==

= 1.0.0 =
Primeira versão.
