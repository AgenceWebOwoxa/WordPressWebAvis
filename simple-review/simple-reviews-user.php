<?php
// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

// Formulaire de connexion
function sr_login_form() {
    ob_start();
    if (isset($_POST['sr_login']) && check_admin_referer('sr_login_nonce', 'sr_login_nonce')) {
        $email = sanitize_email($_POST['sr_email']);
        $user = get_user_by('email', $email);
        
        if (!$user) {
            $password = wp_generate_password(12, true);
            $user_id = wp_create_user($email, $password, $email);
            if (!is_wp_error($user_id)) {
                wp_mail($email, 'Votre mot de passe Simple Reviews', "Voici votre mot de passe : $password\nConnectez-vous et changez-le dans votre espace utilisateur.");
                echo '<p class="sr-success">Un mot de passe a été envoyé à votre email.</p>';
            }
        } else {
            wp_signon(array('user_login' => $email, 'user_password' => $_POST['sr_password']));
            if (!is_wp_error(wp_get_current_user())) {
                $user_space_page = get_option('sr_user_space_page', '');
                wp_redirect($user_space_page ? esc_url($user_space_page) : home_url('/espace-utilisateur')); // Redirige vers URL de la page Espace Utilisateur Avis
                exit;
            } else {
                echo '<p class="sr-error">Mot de passe incorrect.</p>';
            }
        }
    }
    ?>
    <div class="sr-login-form">
        <h2>Connexion</h2>
        <form method="post">
            <p>
                <label>Email</label>
                <input type="email" name="sr_email" required>
            </p>
            <p>
                <label>Mot de passe</label>
                <input type="password" name="sr_password" required>
            </p>
            <?php wp_nonce_field('sr_login_nonce', 'sr_login_nonce'); ?>
            <input type="hidden" name="sr_login" value="1">
            <button type="submit">Se connecter</button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

// Espace utilisateur
function sr_user_space_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Veuillez vous connecter.</p>' . sr_login_form();
    }

    $user_id = get_current_user_id();
    $review = get_posts(array('post_type' => 'sr_review', 'author' => $user_id, 'posts_per_page' => 1))[0] ?? null;

    ob_start();
    echo '<div class="sr-user-space">';
    echo '<h2>Votre espace utilisateur</h2>';
    echo '<p>Email utilisé : ' . esc_html(wp_get_current_user()->user_email) . '</p>';

    if (isset($_POST['sr_update_review']) && check_admin_referer('sr_update_nonce', 'sr_nonce')) {
        $name = sanitize_text_field($_POST['sr_name']);
        $rating = intval($_POST['sr_rating']);
        $comment = sanitize_textarea_field($_POST['sr_comment']);
        wp_update_post(array('ID' => $review->ID, 'post_title' => 'Avis de ' . $name, 'post_content' => $comment));
        update_post_meta($review->ID, 'sr_rating', $rating);
        echo '<p class="sr-success">Avis mis à jour !</p>';
    }

    if (isset($_POST['sr_delete_review']) && check_admin_referer('sr_delete_nonce', 'sr_nonce')) {
        wp_delete_post($review->ID);
        $review = null;
        echo '<p class="sr-success">Avis supprimé !</p>';
    }

    if (isset($_POST['sr_update_password']) && check_admin_referer('sr_password_nonce', 'sr_nonce')) {
        $new_password = sanitize_text_field($_POST['sr_new_password']);
        wp_set_password($new_password, $user_id);
        echo '<p class="sr-success">Mot de passe mis à jour !</p>';
    }

    if ($review) {
        $rating = get_post_meta($review->ID, 'sr_rating', true);
        $name = str_replace('Avis de ', '', $review->post_title);
        ?>
        <h3>Votre avis</h3>
        <form method="post">
            <p>
                <label>Nom</label>
                <input type="text" name="sr_name" value="<?php echo esc_attr($name); ?>" required>
            </p>
            <p>
                <label>Note (1 à 5)</label>
                <select name="sr_rating" required>
                    <?php for ($i = 1; $i <= 5; $i++) : ?>
                        <option value="<?php echo $i; ?>" <?php selected($rating, $i); ?>><?php echo $i; ?> étoile<?php echo $i > 1 ? 's' : ''; ?></option>
                    <?php endfor; ?>
                </select>
            </p>
            <p>
                <label>Commentaire</label>
                <textarea name="sr_comment" rows="4" required><?php echo esc_textarea($review->post_content); ?></textarea>
            </p>
            <?php wp_nonce_field('sr_update_nonce', 'sr_nonce'); ?>
            <button type="submit" name="sr_update_review" value="1">Mettre à jour</button>
        </form>
        <h3>Supprimer l’avis</h3>
        <form method="post">
            <?php wp_nonce_field('sr_delete_nonce', 'sr_nonce'); ?>
            <button type="submit" name="sr_delete_review" value="1" class="sr-delete-btn">Supprimer</button>
        </form>
        <?php
    } else {
        echo '<p>Vous n’avez pas encore soumis d’avis.</p>';
    }

    ?>
    <h3>Changer le mot de passe</h3>
    <form method="post">
        <p>
            <label>Nouveau mot de passe</label>
            <input type="password" name="sr_new_password" required>
        </p>
        <?php wp_nonce_field('sr_password_nonce', 'sr_nonce'); ?>
        <button type="submit" name="sr_update_password" value="1">Mettre à jour</button>
    </form>
    <p><a href="<?php echo wp_logout_url(home_url()); ?>">Se déconnecter</a></p>
    <?php
    $review_page = get_option('sr_review_page', '');
    if ($review_page) {
        echo '<p><a href="' . esc_url($review_page) . '">Retourner aux avis</a></p>';
    }
    ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('sr_user_space', 'sr_user_space_shortcode');
