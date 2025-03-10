<?php
/*
Plugin Name: Custom Price Ranges for WooCommerce
Plugin URI:  https://github.com/brunosantoslab/plugins
Description: A plugin to add price ranges based on quantity and user role in WooCommerce.
Version:     1.0
Author:      Bruno Santos
Author URI:  https://brunosantos.app
License:     GPL2
*/

// Adiciona os campos personalizados de faixas de preço por unidade e role
function custom_price_ranges_fields() {
    global $woocommerce, $post;
    ?>
    <div class="options_group">
        <h3><?php _e('Faixas de Preço por Quantidade e Role', 'custom-price-ranges'); ?></h3>

        <!-- Campo para preços por faixa e role -->
		
		<!-- Preços para Atacado -->
        <p class="form-field">
            <label for="wholesale_price_20_50"><?php _e('Preço Atacado para 20 a 50 unidades (R$)', 'custom-price-ranges'); ?></label>
            <input type="text" class="short" name="wholesale_price_20_50" id="wholesale_price_20_50" value="<?php echo esc_attr(get_post_meta($post->ID, '_wholesale_price_20_50', true)); ?>" />
        </p>
        
        <p class="form-field">
            <label for="wholesale_price_100_plus"><?php _e('Preço Atacado para 100+ unidades (R$)', 'custom-price-ranges'); ?></label>
            <input type="text" class="short" name="wholesale_price_100_plus" id="wholesale_price_100_plus" value="<?php echo esc_attr(get_post_meta($post->ID, '_wholesale_price_100_plus', true)); ?>" />
        </p>

        <!-- Preços para Parceria -->
        <p class="form-field">
            <label for="partnership_price_1_20"><?php _e('Preço Parceria para 1 a 20 unidades (R$)', 'custom-price-ranges'); ?></label>
            <input type="text" class="short" name="partnership_price_1_20" id="partnership_price_1_20" value="<?php echo esc_attr(get_post_meta($post->ID, '_partnership_price_1_20', true)); ?>" />
        </p>

        <p class="form-field">
            <label for="partnership_price_20_plus"><?php _e('Preço Parceria para 20+ unidades (R$)', 'custom-price-ranges'); ?></label>
            <input type="text" class="short" name="partnership_price_20_plus" id="partnership_price_20_plus" value="<?php echo esc_attr(get_post_meta($post->ID, '_partnership_price_20_plus', true)); ?>" />
        </p>
    </div>
    <?php
}

// Adiciona os campos na tela de edição de produto
add_action('woocommerce_product_options_pricing', 'custom_price_ranges_fields');

// Salva os campos personalizados quando o produto for atualizado
function save_custom_price_ranges($post_id) {
	// Verifica se não é um autosave e se o tipo de post é 'product'
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if ('product' != $_POST['post_type']) return;

    // Verifica se o usuário tem permissão para editar o produto
    if (!current_user_can('edit_post', $post_id)) return;
	
    // Verifica se os campos de preço estão presentes e salva
    if (isset($_POST['wholesale_price_20_50'])) {
        update_post_meta($post_id, '_wholesale_price_20_50', sanitize_text_field($_POST['wholesale_price_20_50']));
    }
    if (isset($_POST['wholesale_price_100_plus'])) {
        update_post_meta($post_id, '_wholesale_price_100_plus', sanitize_text_field($_POST['wholesale_price_100_plus']));
    }
    if (isset($_POST['partnership_price_1_20'])) {
        update_post_meta($post_id, '_partnership_price_1_20', sanitize_text_field($_POST['partnership_price_1_20']));
    }
    if (isset($_POST['partnership_price_20_plus'])) {
        update_post_meta($post_id, '_partnership_price_20_plus', sanitize_text_field($_POST['partnership_price_20_plus']));
    }
}

// Garante que os campos sejam salvos ao salvar o produto
add_action('save_post', 'save_custom_price_ranges');

// Aplica preços dinâmicos no frontend baseado na quantidade e role do usuário
function apply_dynamic_price_by_role_and_quantity($price, $product) {

    // Verifica se o usuário está logado e se o WooCommerce e o carrinho estão disponíveis
    if (is_user_logged_in() && function_exists('WC') && WC()->cart !== null) {
        $user = wp_get_current_user();
        $roles = $user->roles; // Obtém as roles do usuário
		
		// Obtém a quantidade do produto no carrinho
        $cart = WC()->cart->get_cart();
        $quantity = 0;
        
        // Itera sobre os itens no carrinho para encontrar a quantidade do produto específico
        foreach ($cart as $cart_item) {
            if ($cart_item['product_id'] == $product->get_id()) {
                $quantity = $cart_item['quantity'];
                break; // Encontra o produto e sai do loop
            }
        }
        
        // Obtém as faixas de preço para cada role
        $wholesale_price_20_50 = esc_attr(get_post_meta($product->get_id(), '_wholesale_price_20_50', true));
        $wholesale_price_100_plus = esc_attr(get_post_meta($product->get_id(), '_wholesale_price_100_plus', true));
        $partnership_price_1_20 = esc_attr(get_post_meta($product->get_id(), '_partnership_price_1_20', true));
        $partnership_price_20_plus = esc_attr(get_post_meta($product->get_id(), '_partnership_price_20_plus', true));

        // Preço para Atacado
        if (in_array('cliente_atacado', $roles)) {
            if ($quantity > 100 && !empty($wholesale_price_100_plus)) {
                $price = $wholesale_price_100_plus; // Preço para 100+ unidades
            } elseif ($quantity >= 20 && $quantity <= 50 && !empty($wholesale_price_20_50)) {
                $price = $wholesale_price_20_50; // Preço para 20 a 50 unidades
            }
        }
        
        // Preço para Parceria
        if (in_array('cliente_parceria', $roles)) {
            if ($quantity > 20 && !empty($partnership_price_20_plus)) {
                $price = $partnership_price_20_plus; // Preço para 20+ unidades
            } elseif ($quantity <= 20 && !empty($partnership_price_1_20)) {
                $price = $partnership_price_1_20; // Preço para 1 a 20 unidades
            }
        }
    }

    return $price;
}

add_filter('woocommerce_product_get_price', 'apply_dynamic_price_by_role_and_quantity', 10, 2);

/**
 * Exibe as faixas de preço por role na página do produto
 */
function display_price_ranges_on_product_page() {
    global $product;
    
    // Verifica se o usuário está logado e se o WooCommerce está carregado
    if (is_user_logged_in() && function_exists('WC')) {
        $user = wp_get_current_user();
        $roles = $user->roles; // Obtém as roles do usuário
	    
        // Recupera as faixas de preço armazenadas para o produto
        $wholesale_price_20_50 = esc_attr(get_post_meta($product->get_id(), '_wholesale_price_20_50', true));
        $wholesale_price_100_plus = esc_attr(get_post_meta($product->get_id(), '_wholesale_price_100_plus', true));
        $partnership_price_1_20 = esc_attr(get_post_meta($product->get_id(), '_partnership_price_1_20', true));
        $partnership_price_20_plus = esc_attr(get_post_meta($product->get_id(), '_partnership_price_20_plus', true));

        // Exibe as faixas de preço para o Atacado
        if (in_array('cliente_atacado', $roles)) {
            echo '<h3>' . __('Faixas de Preço para Atacado', 'meu-plugin') . '</h3>';
            if ($wholesale_price_20_50) {
                echo '<p>' . __('20 a 50 unidades: R$ ', 'meu-plugin') . $wholesale_price_20_50 . '</p>';
            }
            if ($wholesale_price_100_plus) {
                echo '<p>' . __('100+ unidades: R$ ', 'meu-plugin') . $wholesale_price_100_plus . '</p>';
            }
        }
        
        // Exibe as faixas de preço para Parceria
        if (in_array('cliente_parceria', $roles)) {
            echo '<h3>' . __('Faixas de Preço para Parceria', 'meu-plugin') . '</h3>';
            if ($partnership_price_1_20) {
                echo '<p>' . __('1 a 20 unidades: R$ ', 'meu-plugin') . $partnership_price_1_20 . '</p>';
            }
            if ($partnership_price_20_plus) {
                echo '<p>' . __('20+ unidades: R$ ', 'meu-plugin') . $partnership_price_20_plus . '</p>';
            }
        }
    }
}

// Adiciona a função à exibição do produto individual (woocommerce_single_product_summary)
add_action('woocommerce_single_product_summary', 'display_price_ranges_on_product_page', 25);

// Adiciona as colunas de preço de atacado e parceria na listagem administrativa de produtos
function add_custom_columns_to_products_list($columns) {
    $columns['wholesale_price'] = __('Preço Atacado', 'custom-price-ranges');
    $columns['partnership_price'] = __('Preço Parceria', 'custom-price-ranges');
    
    return $columns;
}

add_filter('manage_edit-product_columns', 'add_custom_columns_to_products_list');

// Exibe os valores de preço nas colunas da listagem administrativa
function display_custom_columns_content($column, $post_id) {
    if ('wholesale_price' === $column) {
        $wholesale_price_20_50 = get_post_meta($post_id, '_wholesale_price_20_50', true);
        $wholesale_price_100_plus = get_post_meta($post_id, '_wholesale_price_100_plus', true);
        echo '20-50 unidades: R$ ' . $wholesale_price_20_50 . '<br>';
        echo '100+ unidades: R$ ' . $wholesale_price_100_plus;
    }

    if ('partnership_price' === $column) {
        $partnership_price_1_20 = get_post_meta($post_id, '_partnership_price_1_20', true);
        $partnership_price_20_plus = get_post_meta($post_id, '_partnership_price_20_plus', true);
        echo '1-20 unidades: R$ ' . $partnership_price_1_20 . '<br>';
        echo '20+ unidades: R$ ' . $partnership_price_20_plus;
    }
}

add_action('manage_product_posts_custom_column', 'display_custom_columns_content', 10, 2);

