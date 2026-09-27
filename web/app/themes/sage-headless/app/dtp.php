<?php

/**
 * Decolonising the Page project code.
 *
 * Everything specific to this site lives here so the template's setup.php,
 * filters.php and blocks.php stay merge-clean for future template updates.
 */

namespace App;

use function Roots\asset;

/**
 * Load DTP's editor stylesheet into the block editor (the template loads
 * app.css via add_editor_style and the editor bundle JS-only).
 */
add_action('after_setup_theme', function () {
    add_editor_style(asset('editor.css')->relativePath(get_theme_file_path()));
}, 21);

/**
 * Allow this project's ACF blocks (and the core blocks its content uses) in
 * the editor, on top of the template's allowlist in setup.php.
 */
add_filter('allowed_block_types_all', function ($allowed) {
    if (! is_array($allowed)) {
        return $allowed;
    }

    return array_values(array_unique(array_merge($allowed, [
        'acf/exhibition-room',
        'acf/home-page-block',
        'acf/home-page-section',
        'core/separator',
        'core/more',
        'core/page-list',
    ])));
}, 20);

/**
 * Height-based image sizes for book covers and spreads, replacing the
 * template's width-based sizes (registered in setup.php at priority 20).
 * Registered as additional sizes rather than written to the size options on
 * every request, as the previous setup did.
 */
add_action('after_setup_theme', function () {
    foreach (['small', 'x_large'] as $size) {
        remove_image_size($size);
    }

    add_image_size('thumbnail', 0, 200, false);
    add_image_size('medium', 0, 400, false);
    add_image_size('medium_large', 0, 800, false);
    add_image_size('large', 0, 1400, false);
    add_image_size('x_large', 0, 2000, false);
}, 30);

add_filter('intermediate_image_sizes', function ($sizes) {
    return array_diff($sizes, ['1536x1536', '2048x2048', 'small']);
});

add_filter('big_image_size_threshold', '__return_false');

add_action('init', function () {
    remove_image_size('1536x1536');
    remove_image_size('2048x2048');
});

/**
 * Increase the GraphQL connection limit (book listings).
 */
add_filter('graphql_connection_max_query_amount', function ($amount, $source, $args, $context, $info) {
    return 1000;
}, 10, 5);

add_filter('request', function ($vars) {
    if (isset($vars['graphql']) && !empty($vars['name'])) {
        $vars['suppress_filters'] = true;
    }

    return $vars;
});

add_action('admin_menu', function () {
    add_options_page(
        'Vercel Deploy Hook',
        'Vercel Deploy Hook',
        'manage_options',
        'vercel-deploy-hook',
        function () {
?>
        <div class="wrap">
            <h1>Vercel Deploy Hook</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('vercel_deploy_hook');
                do_settings_sections('vercel_deploy_hook');
                submit_button();
                ?>
            </form>
        </div>
    <?php
        }
    );
});

add_action('admin_init', function () {
    register_setting('vercel_deploy_hook', 'vercel_deploy_hook_url');

    add_settings_section(
        'vercel_deploy_hook_section',
        'Vercel Deploy Hook Settings',
        null,
        'vercel_deploy_hook'
    );

    add_settings_field(
        'vercel_deploy_hook_url',
        'Deploy Hook URL',
        function () {
            $url = get_option('vercel_deploy_hook_url');
            echo "<input type='text' name='vercel_deploy_hook_url' value='" . esc_attr($url) . "' class='regular-text' />";
        },
        'vercel_deploy_hook',
        'vercel_deploy_hook_section'
    );
});

add_action('admin_bar_menu', function ($wp_admin_bar) {
    if (!current_user_can('edit_posts')) {
        return;
    }

    $wp_admin_bar->add_node([
        'id'    => 'vercel_deploy',
        'title' => 'Update content on website',
        'href'  => '#',
        'meta'  => [
            'onclick' => 'triggerVercelDeploy()',
        ],
    ]);
}, 100);

add_action('admin_footer', function () {
    ?>
    <script type="text/javascript">
        function triggerVercelDeploy() {
            const url = '<?php echo esc_js(get_option('vercel_deploy_hook_url')); ?>';
            if (!url) {
                alert('Vercel deploy hook URL is not set.');
                return;
            }

            fetch(url, {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => alert('Content is updating. Please wait for a minute or two before reloading the DTP webpage.'))
                .catch(error => alert('Error triggering deploy: ' + error));
        }
    </script>
<?php
});

add_filter('register_post_type_args', function ($args, $post_type) {
    if ($post_type === 'post') {
        $args['labels'] = [
            'name' => 'Learning Hub Posts',
            'singular_name' => 'Learning Hub Post',
            'add_new' => 'Add New',
            'add_new_item' => 'Add New Learning Hub Post',
            'edit_item' => 'Edit Learning Hub Post',
            'new_item' => 'New Learning Hub Post',
            'view_item' => 'View Learning Hub Post',
            'search_items' => 'Search Learning Hub Posts',
            'not_found' => 'No Learning Hub posts found',
            'not_found_in_trash' => 'No Learning Hub posts found in trash',
            'all_items' => 'All Learning Hub Posts',
            'menu_name' => 'Learning Hub Posts',
        ];
    }
    return $args;
}, 10, 2);

/**
 * Keep `align` nullable on ACF block attributes.
 *
 * wpgraphql-acf 2.8 exposes `align` on ACF blocks as String! while core blocks
 * expose String, so a query selecting `attributes { align }` across both fails
 * validation ("conflicting types"). Mirrors the core-block fix in blocks.php.
 */
add_filter('graphql_object_fields', function ($fields, $type_name) {
    if (str_starts_with($type_name, 'Acf') && str_ends_with($type_name, 'Attributes')) {
        return unwrap_nonnull_fields($fields, ['align']);
    }

    return $fields;
}, 10, 2);
