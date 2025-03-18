<?php
/**
 * Pagina di gestione corrieri
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Verifica permessi
if (!current_user_can('manage_woocommerce')) {
    wp_die(__('Non hai i permessi per accedere a questa pagina.', 'bricoware-stackable'));
}

// Processa le azioni
$action = isset($_REQUEST['action']) ? sanitize_text_field($_REQUEST['action']) : '';
$carrier_id = isset($_REQUEST['carrier_id']) ? sanitize_text_field($_REQUEST['carrier_id']) : '';

// Gestione delle azioni
if ($action === 'edit' && !empty($carrier_id)) {
    // Modifica corriere
    $carrier = Bricoware_Settings::get_carrier($carrier_id);
    
    if (!$carrier) {
        // Corriere non trovato, reindirizza alla lista
        wp_redirect(admin_url('admin.php?page=bricoware-carriers'));
        exit;
    }
    
    // Visualizza form di modifica
    include BRICOWARE_SHIPPING_PLUGIN_DIR . 'includes/admin/views/carrier-edit.php';
    return;
} elseif ($action === 'save') {
    // Salva corriere
    if (!isset($_POST['bricoware_carrier_nonce']) || !wp_verify_nonce($_POST['bricoware_carrier_nonce'], 'save_carrier')) {
        wp_die(__('Nonce di sicurezza non valido.', 'bricoware-stackable'));
    }
    
    // Raccogli i dati dal form
    $carrier_data = array(
        'id' => isset($_POST['carrier_id']) ? sanitize_text_field($_POST['carrier_id']) : '',
        'name' => isset($_POST['carrier_name']) ? sanitize_text_field($_POST['carrier_name']) : '',
        'weight_type' => isset($_POST['weight_type']) ? sanitize_text_field($_POST['weight_type']) : 'max',
        'transit_time' => isset($_POST['transit_time']) ? intval($_POST['transit_time']) : '',
        'dimensions_limit' => array(
            'length' => isset($_POST['max_length']) ? floatval($_POST['max_length']) : 0,
            'width' => isset($_POST['max_width']) ? floatval($_POST['max_width']) : 0,
            'height' => isset($_POST['max_height']) ? floatval($_POST['max_height']) : 0,
        ),
        'rates' => array()
    );
    
    // Raccogli le tariffe
    if (isset($_POST['rate_weight_from']) && is_array($_POST['rate_weight_from'])) {
        foreach ($_POST['rate_weight_from'] as $index => $weight_from) {
            if (isset($_POST['rate_weight_to'][$index]) && isset($_POST['rate_cost'][$index])) {
                $carrier_data['rates'][] = array(
                    'weight_from' => floatval($weight_from),
                    'weight_to' => floatval($_POST['rate_weight_to'][$index]),
                    'cost' => floatval($_POST['rate_cost'][$index])
                );
            }
        }
    }
    
    // Salva il corriere
    $saved_id = Bricoware_Settings::save_carrier($carrier_data);
    
    // Reindirizza con messaggio di successo
    wp_redirect(admin_url('admin.php?page=bricoware-carriers&message=saved'));
    exit;
} elseif ($action === 'delete' && !empty($carrier_id)) {
    // Elimina corriere
    if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], 'delete_carrier_' . $carrier_id)) {
        wp_die(__('Nonce di sicurezza non valido.', 'bricoware-stackable'));
    }
    
    Bricoware_Settings::delete_carrier($carrier_id);
    
    // Reindirizza con messaggio di successo
    wp_redirect(admin_url('admin.php?page=bricoware-carriers&message=deleted'));
    exit;
} elseif ($action === 'add') {
    // Aggiungi nuovo corriere
    include BRICOWARE_SHIPPING_PLUGIN_DIR . 'includes/admin/views/carrier-edit.php';
    return;
}

// Ottieni tutti i corrieri
$carriers = Bricoware_Settings::get_all_carriers();

// Visualizza la lista dei corrieri
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html__('Gestione Corrieri', 'bricoware-stackable'); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=bricoware-carriers&action=add')); ?>" class="page-title-action"><?php echo esc_html__('Aggiungi nuovo', 'bricoware-stackable'); ?></a>
    <hr class="wp-header-end">
    
    <?php
    // Visualizza messaggi
    if (isset($_GET['message'])) {
        $message = sanitize_text_field($_GET['message']);
        $message_text = '';
        
        switch ($message) {
            case 'saved':
                $message_text = __('Corriere salvato con successo.', 'bricoware-stackable');
                break;
            case 'deleted':
                $message_text = __('Corriere eliminato con successo.', 'bricoware-stackable');
                break;
        }
        
        if (!empty($message_text)) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message_text) . '</p></div>';
        }
    }
    ?>
    
    <div id="dashboard-widgets" class="metabox-holder">
        <div id="postbox-container-1" class="postbox-container">
            <div class="meta-box-sortables">
                <div class="postbox">
                    <h2 class="hndle"><span><?php echo esc_html__('Corrieri configurati', 'bricoware-stackable'); ?></span></h2>
                    
                    <div class="inside">
                        <?php if (empty($carriers)) : ?>
                            <p><?php echo esc_html__('Non hai ancora configurato nessun corriere.', 'bricoware-stackable'); ?></p>
                        <?php else : ?>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php echo esc_html__('Nome', 'bricoware-stackable'); ?></th>
                                        <th><?php echo esc_html__('Tipo peso', 'bricoware-stackable'); ?></th>
                                        <th><?php echo esc_html__('Tempo consegna', 'bricoware-stackable'); ?></th>
                                        <th><?php echo esc_html__('Limiti dimensionali', 'bricoware-stackable'); ?></th>
                                        <th><?php echo esc_html__('Tariffe', 'bricoware-stackable'); ?></th>
                                        <th><?php echo esc_html__('Azioni', 'bricoware-stackable'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($carriers as $id => $carrier) : ?>
                                        <tr>
                                            <td><?php echo esc_html($carrier['name']); ?></td>
                                            <td>
                                                <?php
                                                $weight_types = array(
                                                    'max' => __('Maggiore tra reale e volumetrico', 'bricoware-stackable'),
                                                    'real' => __('Solo peso reale', 'bricoware-stackable'),
                                                    'volumetric' => __('Solo peso volumetrico', 'bricoware-stackable'),
                                                );
                                                echo esc_html(isset($weight_types[$carrier['weight_type']]) ? $weight_types[$carrier['weight_type']] : $carrier['weight_type']);
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                if (!empty($carrier['transit_time'])) {
                                                    echo esc_html(sprintf(__('%d giorni', 'bricoware-stackable'), $carrier['transit_time']));
                                                } else {
                                                    echo '—';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                if (isset($carrier['dimensions_limit']) && is_array($carrier['dimensions_limit'])) {
                                                    $limits = array();
                                                    
                                                    if (!empty($carrier['dimensions_limit']['length'])) {
                                                        $limits[] = sprintf(__('L: %s cm', 'bricoware-stackable'), $carrier['dimensions_limit']['length']);
                                                    }
                                                    
                                                    if (!empty($carrier['dimensions_limit']['width'])) {
                                                        $limits[] = sprintf(__('W: %s cm', 'bricoware-stackable'), $carrier['dimensions_limit']['width']);
                                                    }
                                                    
                                                    if (!empty($carrier['dimensions_limit']['height'])) {
                                                        $limits[] = sprintf(__('H: %s cm', 'bricoware-stackable'), $carrier['dimensions_limit']['height']);
                                                    }
                                                    
                                                    echo !empty($limits) ? esc_html(implode(', ', $limits)) : '—';
                                                } else {
                                                    echo '—';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                if (isset($carrier['rates']) && is_array($carrier['rates'])) {
                                                    echo count($carrier['rates']) . ' ' . esc_html(_n('tariffa', 'tariffe', count($carrier['rates']), 'bricoware-stackable'));
                                                } else {
                                                    echo '0 ' . esc_html__('tariffe', 'bricoware-stackable');
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <a href="<?php echo esc_url(admin_url('admin.php?page=bricoware-carriers&action=edit&carrier_id=' . $id)); ?>" class="button button-small"><?php echo esc_html__('Modifica', 'bricoware-stackable'); ?></a>
                                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=bricoware-carriers&action=delete&carrier_id=' . $id), 'delete_carrier_' . $id)); ?>" class="button button-small delete-carrier" onclick="return confirm('<?php echo esc_js(__('Sei sicuro di voler eliminare questo corriere?', 'bricoware-stackable')); ?>')"><?php echo esc_html__('Elimina', 'bricoware-stackable'); ?></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div id="postbox-container-2" class="postbox-container">
            <div class="meta-box-sortables">
                <div class="postbox">
                    <h2 class="hndle"><span><?php echo esc_html__('Guida rapida', 'bricoware-stackable'); ?></span></h2>
                    
                    <div class="inside">
                        <h3><?php echo esc_html__('Come funziona il calcolo del peso volumetrico', 'bricoware-stackable'); ?></h3>
                        <p><?php echo esc_html__('Il peso volumetrico viene calcolato con la seguente formula:', 'bricoware-stackable'); ?></p>
                        <p><code>(Lunghezza × Larghezza × Altezza) / Divisore</code></p>
                        <p><?php echo esc_html__('Dove il divisore è configurabile nelle impostazioni generali (valore predefinito: 5000).', 'bricoware-stackable'); ?></p>
                        
                        <h3><?php echo esc_html__('Prodotti impilabili', 'bricoware-stackable'); ?></h3>
                        <p><?php echo esc_html__('Per i prodotti impilabili, quando un cliente acquista più unità dello stesso prodotto, il plugin calcola correttamente il volume considerando solo l\'incremento dell\'altezza per ogni unità aggiuntiva, invece di moltiplicare l\'intero volume.', 'bricoware-stackable'); ?></p>
                        
                        <h3><?php echo esc_html__('Configurazione dei corrieri', 'bricoware-stackable'); ?></h3>
                        <p><?php echo esc_html__('Per ogni corriere puoi configurare:', 'bricoware-stackable'); ?></p>
                        <ul>
                            <li><?php echo esc_html__('Nome del corriere', 'bricoware-stackable'); ?></li>
                            <li><?php echo esc_html__('Tipo di peso da utilizzare (reale, volumetrico o il maggiore tra i due)', 'bricoware-stackable'); ?></li>
                            <li><?php echo esc_html__('Tempo di consegna stimato in giorni', 'bricoware-stackable'); ?></li>
                            <li><?php echo esc_html__('Limiti dimensionali (lunghezza, larghezza, altezza massime)', 'bricoware-stackable'); ?></li>
                            <li><?php echo esc_html__('Tariffe di spedizione in base al peso', 'bricoware-stackable'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>