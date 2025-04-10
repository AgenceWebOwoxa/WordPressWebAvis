<?php
/*
Plugin Name: Simple Reviews
Description: Permet aux utilisateurs de laisser des avis sur le site avec gestion utilisateur.
Version: 1.3.4
Author: Grok & Agence Internet Owoxa
Requires at least: 6.7.2
Requires PHP: 8.0.29
*/

// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

// Vérification des versions minimales requises
function sr_check_requirements() {
    global $wp_version;
    $required_wp_version = '6.7.2';
    $required_php_version = '8.0.29';

    if (version_compare($wp_version, $required_wp_version, '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Simple Reviews nécessite WordPress version ' . $required_wp_version . ' ou supérieure. Votre version : ' . $wp_version);
    }

    if (version_compare(PHP_VERSION, $required_php_version, '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Simple Reviews nécessite PHP version ' . $required_php_version . ' ou supérieure. Votre version : ' . PHP_VERSION);
    }
}
add_action('admin_init', 'sr_check_requirements');

// Inclure les fichiers spécifiques
require_once plugin_dir_path(__FILE__) . 'simple-reviews-user.php';
require_once plugin_dir_path(__FILE__) . 'simple-reviews-admin.php';
require_once plugin_dir_path(__FILE__) . 'simple-reviews-emails.php';

// Enregistrer les styles
function sr_enqueue_styles() {
    wp_enqueue_style('sr-style', plugin_dir_url(__FILE__) . 'css/style.css');
}
add_action('wp_enqueue_scripts', 'sr_enqueue_styles');

// Charger Dashicons sur le front-end
function sr_enqueue_dashicons() {
    wp_enqueue_style('dashicons');
}
add_action('wp_enqueue_scripts', 'sr_enqueue_dashicons');

// Créer un Custom Post Type pour les avis
function sr_register_review_post_type() {
    register_post_type('sr_review', array(
        'labels' => array(
            'name' => 'Avis',
            'singular_name' => 'Avis'
        ),
        'public' => false,
        'show_ui' => true,
        'supports' => array('title', 'editor'),
        'menu_icon' => 'dashicons-star-filled',
        'capability_type' => 'post',
        'capabilities' => array(
            'create_posts' => 'do_not_allow',
        ),
        'map_meta_cap' => true
    ));
}
add_action('init', 'sr_register_review_post_type');

function sr_reviews_list_shortcode($atts) {
    $atts = shortcode_atts(array(
        'categorie' => '', // Ajout de l’attribut categorie
    ), $atts, 'simple_reviews_list');

    ob_start();
    ?>
    <div class="sr-review-list">
        <h2>Avis des utilisateurs<?php echo $atts['categorie'] ? ' - ' . esc_html(get_term_by('slug', $atts['categorie'], 'sr_review_category')->name) : ''; ?></h2>
        <?php
        $args = array(
            'post_type' => 'sr_review',
            'post_status' => 'publish',
            'posts_per_page' => 10,
        );
        if ($atts['categorie']) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'sr_review_category',
                    'field' => 'slug',
                    'terms' => $atts['categorie'],
                ),
            );
        }
        $reviews = get_posts($args);
        $current_user = wp_get_current_user();
        $is_admin = in_array('administrator', $current_user->roles);

        if ($reviews) {
            echo '<ul>';
            foreach ($reviews as $review) {
                $rating = get_post_meta($review->ID, 'sr_rating', true);
                $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
                $user_space_page = get_option('sr_user_space_page', '');
                $is_author = is_user_logged_in() && $review->post_author == get_current_user_id();

                echo '<li>';
                echo '<div class="sr-review-header">';
                echo '<strong>' . esc_html($review->post_title) . '</strong>';
                if ($user_space_page && ($is_author || $is_admin)) {
                    echo '<a href="' . esc_url($user_space_page) . '" class="sr-edit-icon" title="Modifier cet avis"><span class="dashicons dashicons-edit"></span></a>';
                }
                echo '</div>';
                echo '<div class="sr-stars">' . $stars . '</div>';
                echo wp_kses_post($review->post_content);
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>Aucun avis publié pour le moment dans cette catégorie.</p>';
        }

        $form_page = get_option('sr_form_page', '');
        $user_space_page = get_option('sr_user_space_page', '');

        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $existing_review = get_posts(array(
                'post_type' => 'sr_review',
                'author' => $user_id,
                'posts_per_page' => 1,
                'post_status' => array('publish', 'pending')
            ));

            echo '<div class="sr-buttons">';
            if ($form_page && !$existing_review) {
                echo '<a href="' . esc_url($form_page) . '" class="sr-form-button">Laisser un avis</a>';
            }
            echo '</div>';
        } elseif ($form_page) {
            echo '<div class="sr-buttons">';
            echo '<a href="' . esc_url($form_page) . '" class="sr-form-button">Laisser un avis</a>';
            echo '</div>';
        }
        ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('simple_reviews_list', 'sr_reviews_list_shortcode');

function sr_reviews_list() {
    return sr_reviews_list_shortcode();
}

// Ajouter des actions personnalisées dans la liste des avis
function sr_add_review_row_actions($actions, $post) {
    if ($post->post_type === 'sr_review') {
        $user_id = $post->post_author;
        $email = $user_id ? get_userdata($user_id)->user_email : '';
        $site_title = get_bloginfo('name');
        $review_page = get_option('sr_review_page', '');
        $user_space_page = get_option('sr_user_space_page', '');

        if ($post->post_status === 'pending') {
            $actions['validate'] = sprintf(
                '<a href="%s">Validé Avis</a>',
                wp_nonce_url(admin_url('admin.php?action=sr_validate_review&post_id=' . $post->ID), 'sr_validate_' . $post->ID)
            );
            $actions['refuse'] = sprintf(
                '<a href="%s">Refusé Avis</a>',
                wp_nonce_url(admin_url('admin.php?action=sr_refuse_review&post_id=' . $post->ID), 'sr_refuse_' . $post->ID)
            );
        }
    }
    return $actions;
}
add_filter('post_row_actions', 'sr_add_review_row_actions', 10, 2);

// Action pour valider un avis
function sr_validate_review() {
    if (isset($_GET['action']) && $_GET['action'] === 'sr_validate_review' && isset($_GET['post_id']) && check_admin_referer('sr_validate_' . $_GET['post_id'])) {
        $post_id = intval($_GET['post_id']);
        $user_id = get_post_field('post_author', $post_id);
        $email = $user_id ? get_userdata($user_id)->user_email : '';
        $site_title = get_bloginfo('name');
        $review_page = get_option('sr_review_page', '');
        $user_space_page = get_option('sr_user_space_page', '');
        $validation_message = get_option('sr_validation_message', 'Votre avis a été validé.');

        wp_update_post(array('ID' => $post_id, 'post_status' => 'publish'));
        if ($email) {
            $message = "$site_title\n\n$validation_message\nVoir les avis : $review_page\nModifier son avis : $user_space_page";
            wp_mail($email, 'Avis validé - ' . $site_title, $message);
        }
        wp_redirect(admin_url('edit.php?post_type=sr_review'));
        exit;
    }
}
add_action('admin_init', 'sr_validate_review');

// Action pour refuser un avis
function sr_refuse_review() {
    if (isset($_GET['action']) && $_GET['action'] === 'sr_refuse_review' && isset($_GET['post_id']) && check_admin_referer('sr_refuse_' . $_GET['post_id'])) {
        $post_id = intval($_GET['post_id']);
        $user_id = get_post_field('post_author', $post_id);
        $email = $user_id ? get_userdata($user_id)->user_email : '';
        $site_title = get_bloginfo('name');
        $review_page = get_option('sr_review_page', '');
        $refuse_message = get_option('sr_refuse_message', 'Votre avis a été refusé.');

        wp_trash_post($post_id);
        if ($email) {
            $message = "$site_title\n\n$refuse_message\nContacter l’administrateur du site internet si besoin d’information sur ce refus.";
            wp_mail($email, 'Avis refusé - ' . $site_title, $message);
        }
        wp_redirect(admin_url('edit.php?post_type=sr_review'));
        exit;
    }
}
add_action('admin_init', 'sr_refuse_review');

function sr_reviews_grille_list_shortcode($atts) {
    $atts = shortcode_atts(array(
        'column' => 2,
        'categorie' => '', // Ajout de l’attribut categorie
    ), $atts, 'simple_reviews_grille_list');

    $columns = max(1, min(4, intval($atts['column'])));

    ob_start();
    ?>
    <div class="sr-review-grille-list" style="--sr-columns: <?php echo esc_attr($columns); ?>;">
        <h2>Avis des utilisateurs<?php echo $atts['categorie'] ? ' - ' . esc_html(get_term_by('slug', $atts['categorie'], 'sr_review_category')->name) : ''; ?></h2>
        <?php
        $args = array(
            'post_type' => 'sr_review',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        );
        if ($atts['categorie']) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'sr_review_category',
                    'field' => 'slug',
                    'terms' => $atts['categorie'],
                ),
            );
        }
        $reviews = get_posts($args);
        $current_user = wp_get_current_user();
        $is_admin = in_array('administrator', $current_user->roles);

        if ($reviews) {
            echo '<div class="sr-grille">';
            foreach ($reviews as $review) {
                $rating = get_post_meta($review->ID, 'sr_rating', true);
                $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
                $user_space_page = get_option('sr_user_space_page', '');
                $is_author = is_user_logged_in() && $review->post_author == get_current_user_id();

                ?>
                <div class="sr-grille-item">
                    <div class="sr-review-header">
                        <strong><?php echo esc_html($review->post_title); ?></strong>
                        <?php if ($user_space_page && ($is_author || $is_admin)) : ?>
                            <a href="<?php echo esc_url($user_space_page); ?>" class="sr-edit-icon" title="Modifier cet avis"><span class="dashicons dashicons-edit"></span></a>
                        <?php endif; ?>
                    </div>
                    <div class="sr-stars"><?php echo $stars; ?></div>
                    <p><?php echo wp_kses_post($review->post_content); ?></p>
                </div>
                <?php
            }
            echo '</div>';
        } else {
            echo '<p>Aucun avis publié pour le moment dans cette catégorie.</p>';
        }

        $form_page = get_option('sr_form_page', '');
        $user_space_page = get_option('sr_user_space_page', '');

        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $existing_review = get_posts(array(
                'post_type' => 'sr_review',
                'author' => $user_id,
                'posts_per_page' => 1,
                'post_status' => array('publish', 'pending')
            ));

            echo '<div class="sr-buttons">';
            if ($form_page && !$existing_review) {
                echo '<a href="' . esc_url($form_page) . '" class="sr-form-button">Laisser un avis</a>';
            }
            echo '</div>';
        } elseif ($form_page) {
            echo '<div class="sr-buttons">';
            echo '<a href="' . esc_url($form_page) . '" class="sr-form-button">Laisser un avis</a>';
            echo '</div>';
        }
        ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('simple_reviews_grille_list', 'sr_reviews_grille_list_shortcode');

// Fonction alternative sans shortcode
function sr_reviews_grille_list($columns = 2) {
    return sr_reviews_grille_list_shortcode(array('column' => $columns));
}

// Créer une taxonomie pour les catégories d'avis
function sr_register_review_categories() {
    register_taxonomy('sr_review_category', 'sr_review', array(
        'labels' => array(
            'name' => 'Catégories d’avis',
            'singular_name' => 'Catégorie d’avis',
            'menu_name' => 'Catégories',
            'all_items' => 'Toutes les catégories',
            'edit_item' => 'Modifier la catégorie',
            'view_item' => 'Voir la catégorie',
            'update_item' => 'Mettre à jour la catégorie',
            'add_new_item' => 'Ajouter une nouvelle catégorie',
            'new_item_name' => 'Nom de la nouvelle catégorie',
            'search_items' => 'Rechercher des catégories',
        ),
        'public' => true,
        'hierarchical' => true, // Comme des catégories, pas des étiquettes
        'show_ui' => true,
        'show_in_menu' => 'edit.php?post_type=sr_review', // Sous-menu dans "Avis"
        'show_admin_column' => true, // Affiche la colonne dans la liste des avis
        'rewrite' => array('slug' => 'review-category'),
    ));
}
add_action('init', 'sr_register_review_categories');

// Dans la fonction sr_enqueue_styles ou une nouvelle fonction
function sr_enqueue_admin_assets() {
    if (is_admin()) {
        wp_enqueue_script('sr-admin-script', plugin_dir_url(__FILE__) . 'js/admin-script.js', array('jquery'), '1.0', true);
    }
}
add_action('admin_enqueue_scripts', 'sr_enqueue_admin_assets');

//Ajouter une taxonomie pour les catégories d’avis
function sr_register_review_category() {
    register_taxonomy('sr_review_category', 'sr_review', array(
        'labels' => array(
            'name' => 'Catégories d’avis',
            'singular_name' => 'Catégorie d’avis',
        ),
        'public' => true,
        'hierarchical' => true,
        'show_admin_column' => true,
    ));
}
add_action('init', 'sr_register_review_category');