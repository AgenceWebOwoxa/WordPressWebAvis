<?php
// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

// Shortcode pour le formulaire avec validation par email
function sr_reviews_shortcode() {
    ob_start();

    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        $existing_review = get_posts(array(
            'post_type' => 'sr_review',
            'author' => $user_id,
            'posts_per_page' => 1,
            'post_status' => array('pending', 'publish') // Inclut les avis en attente et publiés
        ));

        if ($existing_review) {
            $review_status = $existing_review[0]->post_status;
            $user_space_page = get_option('sr_user_space_page', '');
            if ($review_status === 'pending') {
                echo '<p>Votre avis est en attente de validation. Vous pourrez le consulter une fois qu’il sera validé.</p>';
            } else {
                echo '<p>Vous avez déjà soumis un avis. Consultez votre espace utilisateur pour le modifier. <a href="' . esc_url($user_space_page) . '">Mon espace utilisateur</a></p>';
            }
        } else {
            if (isset($_POST['sr_submit_review']) && check_admin_referer('sr_review_nonce', 'sr_nonce')) {
                $name = sanitize_text_field($_POST['sr_name']);
                $rating = intval($_POST['sr_rating']);
                $comment = sanitize_textarea_field($_POST['sr_comment']);

                $review_id = wp_insert_post(array(
                    'post_type' => 'sr_review',
                    'post_title' => 'Avis de ' . $name,
                    'post_content' => $comment,
                    'post_status' => 'pending',
                    'post_author' => $user_id
                ));

                if ($review_id) {
                    update_post_meta($review_id, 'sr_rating', $rating);
                    $review_page = get_option('sr_review_page', '');
                    $link = $review_page ? '<a href="' . esc_url($review_page) . '">Voir les autres avis</a>' : '';
                    echo '<p class="sr-success">Merci pour votre avis, celui-ci est en attente de validation par un administrateur. ' . $link . '</p>';
                }
            } else {
                ?>
                <div class="sr-review-form">
                    <h2>Laisser un avis</h2>
                    <form method="post">
                        <p>
                            <label>Nom</label>
                            <input type="text" name="sr_name" required>
                        </p>
                        <p>
                            <label>Note (1 à 5)</label>
                            <select name="sr_rating" required>
                                <option value="">-- Choisir --</option>
                                <?php for ($i = 1; $i <= 5; $i++) : ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?> étoile<?php echo $i > 1 ? 's' : ''; ?></option>
                                <?php endfor; ?>
                            </select>
                        </p>
                        <p>
                            <label>Commentaire</label>
                            <textarea name="sr_comment" rows="4" required></textarea>
                        </p>
                        <?php wp_nonce_field('sr_review_nonce', 'sr_nonce'); ?>
                        <input type="hidden" name="sr_submit_review" value="1">
                        <button type="submit">Envoyer</button>
                    </form>
                </div>
                <?php
            }
        }
    } else {
        // Étape 1 : Demande d’email
        if (!isset($_POST['sr_send_code']) && !isset($_POST['sr_validate_code'])) {
            ?>
            <div class="sr-review-form">
                <h2>Laisser un avis</h2>
                <form method="post">
                    <p>
                        <label>Email</label>
                        <input type="email" name="sr_email" required>
                    </p>
                    <?php wp_nonce_field('sr_email_nonce', 'sr_nonce'); ?>
                    <input type="hidden" name="sr_send_code" value="1">
                    <button type="submit">Envoyer</button>
                </form>
            </div>
            <?php
        } 
        // Étape 2 : Envoi du code et demande de validation
        elseif (isset($_POST['sr_send_code']) && check_admin_referer('sr_email_nonce', 'sr_nonce')) {
            $email = sanitize_email($_POST['sr_email']);
            $code = rand(1000, 9999); // Code à 4 chiffres
            $temp_password = wp_generate_password(12, true); // Mot de passe temporaire
            update_option('sr_validation_code_' . md5($email), $code); // Stocke temporairement le code
            update_option('sr_temp_password_' . md5($email), $temp_password); // Stocke temporairement le mot de passe
            wp_mail($email, 'Votre code de validation Simple Reviews', "Votre code de validation est le : $code\nVotre mot de passe temporaire est : $temp_password");
            ?>
            <div class="sr-review-form">
                <p>Merci de consulter votre boîte email, un code vous a été envoyé.</p>
                <form method="post">
                    <p>
                        <label>Code de validation (Code : <?php echo esc_html($code); ?>)</label>
                        <input type="text" name="sr_code" maxlength="4" required>
                    </p>
                    <p>
                        <label>Mot de passe temporaire : <?php echo esc_html($temp_password); ?></label>
                        <p class="sr-error">Enregistrez bien ce mot de passe.</p>
                    </p>
                    <input type="hidden" name="sr_email" value="<?php echo esc_attr($email); ?>">
                    <?php wp_nonce_field('sr_code_nonce', 'sr_nonce'); ?>
                    <input type="hidden" name="sr_validate_code" value="1">
                    <button type="submit">Valider le code</button>
                </form>
            </div>
            <?php
        } 
        // Étape 3 : Validation du code
        elseif (isset($_POST['sr_validate_code']) && check_admin_referer('sr_code_nonce', 'sr_nonce')) {
            $email = sanitize_email($_POST['sr_email']);
            $submitted_code = sanitize_text_field($_POST['sr_code']);
            $stored_code = get_option('sr_validation_code_' . md5($email), '');
            $temp_password = get_option('sr_temp_password_' . md5($email), '');

            if ($submitted_code == $stored_code) {
                $user = get_user_by('email', $email);
                if (!$user) {
                    $user_id = wp_create_user($email, $temp_password, $email);
                    if (!is_wp_error($user_id)) {
                        wp_set_current_user($user_id);
                        wp_set_auth_cookie($user_id);
                    }
                } else {
                    wp_set_current_user($user->ID);
                    wp_set_auth_cookie($user->ID);
                }
                delete_option('sr_validation_code_' . md5($email)); // Supprime le code après validation
                delete_option('sr_temp_password_' . md5($email)); // Supprime le mot de passe temporaire
                $review_page = get_option('sr_review_page', '');
                wp_redirect($review_page ? esc_url($review_page) : home_url()); // Redirige vers URL de la page Avis
                exit;
            } else {
                echo '<p class="sr-error">Code erroné. Contactez l’administrateur du site internet.</p>';
            }
        }
    }

    return ob_get_clean();
}
add_shortcode('simple_reviews', 'sr_reviews_shortcode');