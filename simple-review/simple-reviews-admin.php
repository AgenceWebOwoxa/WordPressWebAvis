<?php
// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

// Page d’admin comme sous-menu de "Avis"
function sr_admin_menu() {
    add_submenu_page(
        'edit.php?post_type=sr_review', // Parent : menu "Avis"
        'Simple Reviews',
        'Simple Reviews',
        'manage_options',
        'simple-reviews',
        'sr_admin_page'
    );
    add_submenu_page(
        'edit.php?post_type=sr_review', // Parent : menu "Avis"
        'Paramétrage',
        'Paramétrage',
        'manage_options',
        'simple-reviews-settings',
        'sr_settings_page'
    );
    add_submenu_page(
        'edit.php?post_type=sr_review', // Parent : menu "Avis"
        'Statistiques',
        'Statistiques',
        'manage_options',
        'simple-reviews-stats',
        'sr_stats_page'
    );
}
add_action('admin_menu', 'sr_admin_menu');

function sr_admin_page() {
    ?>
    <div class="wrap">
        <h1>Simple Reviews</h1>
        <p>Utilisez ces shortcodes sur vos pages :</p>
        <ul>
            <li><code>[simple_reviews]</code> - Affiche le formulaire pour laisser un avis.</li>
            <li><code>[simple_reviews_list]</code> - Affiche uniquement la liste des avis.</li>
            <li><code>[sr_user_space]</code> - Affiche l’espace utilisateur.</li>
            <li><code>[simple_reviews_grille_list]</code> - Affiche les avis en grille avec 2 colonnes par défaut.</li>
            <li><code>[simple_reviews_grille_list column=X]</code> - Affiche les avis en grille avec X colonnes (max 4, ex. 3 ou 4).</li>
        </ul>
    </div>
    <?php
}

// Page de paramétrage
function sr_settings_page() {
    if (isset($_POST['sr_save_settings']) && check_admin_referer('sr_settings_nonce', 'sr_settings_nonce')) {
        $form_page = sanitize_text_field($_POST['sr_form_page']);
        $review_page = sanitize_text_field($_POST['sr_review_page']);
        $user_space_page = sanitize_text_field($_POST['sr_user_space_page']);
        $validation_message = sanitize_textarea_field($_POST['sr_validation_message']);
        $refuse_message = sanitize_textarea_field($_POST['sr_refuse_message']);
        update_option('sr_form_page', $form_page);
        update_option('sr_review_page', $review_page);
        update_option('sr_user_space_page', $user_space_page);
        update_option('sr_validation_message', $validation_message);
        update_option('sr_refuse_message', $refuse_message);
        echo '<div class="updated"><p>Paramètres enregistrés.</p></div>';
    }
    $form_page = get_option('sr_form_page', '');
    $review_page = get_option('sr_review_page', '');
    $user_space_page = get_option('sr_user_space_page', '');
    $validation_message = get_option('sr_validation_message', 'Votre avis a été validé.');
    $refuse_message = get_option('sr_refuse_message', 'Votre avis a été refusé.');
    ?>
    <div class="wrap">
        <h1>Paramétrage Simple Reviews</h1>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th><label for="sr_form_page">URL de la page du formulaire</label></th>
                    <td>
                        <input type="text" name="sr_form_page" id="sr_form_page" value="<?php echo esc_attr($form_page); ?>" class="regular-text" placeholder="ex. /laisser-un-avis">
                        <p class="description">Entrez l’URL de la page contenant le shortcode [simple_reviews].</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="sr_review_page">URL de la page Liste des Avis</label></th>
                    <td>
                        <input type="text" name="sr_review_page" id="sr_review_page" value="<?php echo esc_attr($review_page); ?>" class="regular-text" placeholder="ex. /avis">
                        <p class="description">Entrez l’URL de la page contenant le shortcode [simple_reviews_list].</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="sr_user_space_page">URL de la page Espace Utilisateur Avis</label></th>
                    <td>
                        <input type="text" name="sr_user_space_page" id="sr_user_space_page" value="<?php echo esc_attr($user_space_page); ?>" class="regular-text" placeholder="ex. /espace-utilisateur">
                        <p class="description">Entrez l’URL de la page contenant le shortcode [sr_user_space].</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="sr_validation_message">E-mail de validation</label></th>
                    <td>
                        <textarea name="sr_validation_message" id="sr_validation_message" rows="4" class="regular-text"><?php echo esc_textarea($validation_message); ?></textarea>
                        <p class="description">Message envoyé dans l’email de validation.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="sr_refuse_message">E-mail de refus</label></th>
                    <td>
                        <textarea name="sr_refuse_message" id="sr_refuse_message" rows="4" class="regular-text"><?php echo esc_textarea($refuse_message); ?></textarea>
                        <p class="description">Message envoyé dans l’email de refus.</p>
                    </td>
                </tr>
            </table>
            <?php wp_nonce_field('sr_settings_nonce', 'sr_settings_nonce'); ?>
            <input type="hidden" name="sr_save_settings" value="1">
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Page de statistiques
function sr_stats_page() {
    $total_reviews = wp_count_posts('sr_review');
    $avg_rating = array_sum(array_map(function($review) {
        return (int) get_post_meta($review->ID, 'sr_rating', true);
    }, get_posts(array('post_type' => 'sr_review', 'posts_per_page' => -1)))) / max(1, $total_reviews->publish);
    ?>
    <div class="wrap">
        <h1>Statistiques Simple Reviews</h1>
        <p>Nombre total d’avis : <?php echo $total_reviews->publish; ?></p>
        <p>Note moyenne : <?php echo number_format($avg_rating, 1); ?>/5</p>
        <p>Exporter les avis : <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=simple-reviews-stats&sr_export_csv=1'), 'sr_export_csv_nonce'); ?>" class="button">Exporter CSV</a></p>
    </div>
    <?php
}

// Ajouter email et note dans l’admin
function sr_add_rating_column($columns) {
    $columns['sr_rating'] = 'Note';
    $columns['sr_email'] = 'Email';
    return $columns;
}
add_filter('manage_sr_review_posts_columns', 'sr_add_rating_column');

function sr_display_rating_column($column, $post_id) {
    if ($column === 'sr_rating') {
        $rating = get_post_meta($post_id, 'sr_rating', true);
        echo $rating ? esc_html($rating . '/5') : 'Non définie';
    }
    if ($column === 'sr_email') {
        $user_id = get_post_field('post_author', $post_id);
        echo $user_id ? esc_html(get_userdata($user_id)->user_email) : 'Anonyme';
    }
}
add_action('manage_sr_review_posts_custom_column', 'sr_display_rating_column', 10, 2);

// Export CSV
function sr_export_csv() {
    if (isset($_GET['sr_export_csv']) && check_admin_referer('sr_export_csv_nonce')) {
        $reviews = get_posts(array(
            'post_type' => 'sr_review',
            'post_status' => array('publish', 'pending', 'trash'),
            'posts_per_page' => -1
        ));

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="simple-reviews-export-' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fputcsv($output, array('Nom', 'Note', 'Commentaire', 'Email', 'Statut', 'Date'));

        foreach ($reviews as $review) {
            $rating = get_post_meta($review->ID, 'sr_rating', true);
            $user_id = $review->post_author;
            $email = $user_id ? get_userdata($user_id)->user_email : 'Anonyme';
            $name = str_replace('Avis de ', '', $review->post_title);
            fputcsv($output, array(
                $name,
                $rating,
                $review->post_content,
                $email,
                $review->post_status,
                $review->post_date
            ));
        }

        fclose($output);
        exit;
    }
}
add_action('admin_init', 'sr_export_csv');