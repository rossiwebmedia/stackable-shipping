<?php
/**
 * Template per una riga della tabella tariffe
 */

// Gestione sicura quando incluso da AJAX
if (!defined('ABSPATH')) {
    // Non fare exit direttamente, potrebbe essere incluso da AJAX
    if (!function_exists('add_action')) {
        return;
    }
}

// Inizializza le variabili se non esistono
$carrier_id = isset($carrier_id) ? $carrier_id : '';
$row_index = isset($row_index) ? $row_index : 0;
$rate = isset($rate) ? $rate : array(
    'weight_from' => '0',
    'weight_to' => '0',
    'cost' => '0'
);
?>

<tr class="rate-row">
    <td>
        <input type="number" 
               name="bricoware_rates[<?php echo esc_attr($carrier_id); ?>][<?php echo esc_attr($row_index); ?>][weight_from]" 
               value="<?php echo esc_attr(isset($rate['weight_from']) ? $rate['weight_from'] : '0'); ?>" 
               step="0.01" 
               min="0" 
               class="regular-text" 
               required>
    </td>
    <td>
        <input type="number" 
               name="bricoware_rates[<?php echo esc_attr($carrier_id); ?>][<?php echo esc_attr($row_index); ?>][weight_to]" 
               value="<?php echo esc_attr(isset($rate['weight_to']) ? $rate['weight_to'] : '0'); ?>" 
               step="0.01" 
               min="0" 
               class="regular-text" 
               required>
    </td>
    <td>
        <input type="number" 
               name="bricoware_rates[<?php echo esc_attr($carrier_id); ?>][<?php echo esc_attr($row_index); ?>][cost]" 
               value="<?php echo esc_attr(isset($rate['cost']) ? $rate['cost'] : '0'); ?>" 
               step="0.01" 
               min="0" 
               class="regular-text" 
               required>
    </td>
    <td>
        <button type="button" class="button delete-rate">
            <?php echo esc_html__('Elimina', 'bricoware-stackable'); ?>
        </button>
    </td>
</tr>