<?php
/**
 * Form di modifica corriere
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Verifica permessi
if (!current_user_can('manage_woocommerce')) {
    wp_die(__('Non hai i permessi per accedere a questa pagina.', 'bricoware-stackable'));
}

// Inizializza variabili
$carrier = isset($carrier) ? $carrier : array(
    'id' => '',
    'name' => '',
    'weight_type' => 'max',
    'transit_time' => '',
    'dimensions_limit' => array(
        'length' => '',
        'width' => '',
        'height' => '',
    ),
    'rates' => array(
        array(
            'weight_from' => '0',
            'weight_to' => '1',
            'cost' => '5',
        )
    )
);

$is_new = empty($carrier['id']);
$page_title = $is_new ? __('Aggiungi nuovo corriere', 'bricoware-stackable') : __('Modifica corriere', 'bricoware-stackable');
?>

<div class="wrap">
    <h1><?php echo esc_html($page_title); ?></h1>
    
    <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=bricoware-carriers&action=save')); ?>" id="carrier-edit-form">
        <?php wp_nonce_field('save_carrier', 'bricoware_carrier_nonce'); ?>
        
        <input type="hidden" name="carrier_id" value="<?php echo esc_attr($carrier['id']); ?>">
        
        <div id="poststuff">
            <div id="post-body" class="metabox-holder columns-2">
                <div id="post-body-content">
                    <div class="postbox">
                        <h2 class="hndle"><?php echo esc_html__('Informazioni corriere', 'bricoware-stackable'); ?></h2>
                        
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="carrier_name"><?php echo esc_html__('Nome corriere', 'bricoware-stackable'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" name="carrier_name" id="carrier_name" class="regular-text" value="<?php echo esc_attr($carrier['name']); ?>" required>
                                        <p class="description"><?php echo esc_html__('Nome del corriere visualizzato al cliente.', 'bricoware-stackable'); ?></p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="weight_type"><?php echo esc_html__('Tipo peso', 'bricoware-stackable'); ?></label>
                                    </th>
                                    <td>
                                        <select name="weight_type" id="weight_type">
                                            <option value="max" <?php selected(isset($carrier['weight_type']) ? $carrier['weight_type'] : '', 'max'); ?>><?php echo esc_html__('Maggiore tra reale e volumetrico', 'bricoware-stackable'); ?></option>
                                            <option value="real" <?php selected(isset($carrier['weight_type']) ? $carrier['weight_type'] : '', 'real'); ?>><?php echo esc_html__('Solo peso reale', 'bricoware-stackable'); ?></option>
                                            <option value="volumetric" <?php selected(isset($carrier['weight_type']) ? $carrier['weight_type'] : '', 'volumetric'); ?>><?php echo esc_html__('Solo peso volumetrico', 'bricoware-stackable'); ?></option>
                                        </select>
                                        <p class="description"><?php echo esc_html__('Tipo di peso utilizzato per calcolare la tariffa di spedizione.', 'bricoware-stackable'); ?></p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="transit_time"><?php echo esc_html__('Tempo di consegna (giorni)', 'bricoware-stackable'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" name="transit_time" id="transit_time" class="small-text" value="<?php echo esc_attr($carrier['transit_time']); ?>" min="0" step="1">
                                        <p class="description"><?php echo esc_html__('Tempo di consegna stimato in giorni. Lascia vuoto se non applicabile.', 'bricoware-stackable'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="postbox">
                        <h2 class="hndle"><?php echo esc_html__('Limiti dimensionali', 'bricoware-stackable'); ?></h2>
                        
                        <div class="inside">
                            <p><?php echo esc_html__('Specificate i limiti dimensionali massimi accettati da questo corriere. Lasciate vuoto o zero se non ci sono limiti.', 'bricoware-stackable'); ?></p>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="max_length"><?php echo esc_html__('Lunghezza massima (cm)', 'bricoware-stackable'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" name="max_length" id="max_length" class="small-text" value="<?php echo esc_attr(isset($carrier['dimensions_limit']['length']) ? $carrier['dimensions_limit']['length'] : ''); ?>" min="0" step="0.1">
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="max_width"><?php echo esc_html__('Larghezza massima (cm)', 'bricoware-stackable'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" name="max_width" id="max_width" class="small-text" value="<?php echo esc_attr(isset($carrier['dimensions_limit']['width']) ? $carrier['dimensions_limit']['width'] : ''); ?>" min="0" step="0.1">
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="max_height"><?php echo esc_html__('Altezza massima (cm)', 'bricoware-stackable'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" name="max_height" id="max_height" class="small-text" value="<?php echo esc_attr(isset($carrier['dimensions_limit']['height']) ? $carrier['dimensions_limit']['height'] : ''); ?>" min="0" step="0.1">
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="postbox">
                        <h2 class="hndle"><?php echo esc_html__('Tariffe di spedizione', 'bricoware-stackable'); ?></h2>
                        
                        <div class="inside">
                            <p><?php echo esc_html__('Configurate le tariffe di spedizione in base al peso (utilizzando il tipo di peso selezionato sopra).', 'bricoware-stackable'); ?></p>
                            
                            <table class="widefat striped" id="shipping-rates-table">
                                <thead>
                                    <tr>
                                        <th><?php echo esc_html__('Peso da (kg)', 'bricoware-stackable'); ?></th>
                                        <th><?php echo esc_html__('Peso a (kg)', 'bricoware-stackable'); ?></th>
                                        <th><?php echo esc_html__('Costo (€)', 'bricoware-stackable'); ?></th>
                                        <th class="actions"><?php echo esc_html__('Azioni', 'bricoware-stackable'); ?></th>
                                    </tr>
                                </thead>
                                
                                <tbody>
                                    <?php
                                    if (empty($carrier['rates'])) {
                                        $carrier['rates'] = array(array(
                                            'weight_from' => '0',
                                            'weight_to' => '1',
                                            'cost' => '5'
                                        ));
                                    }
                                    
                                    foreach ($carrier['rates'] as $index => $rate) :
                                    ?>
                                        <tr class="rate-row">
                                            <td>
                                                <input type="number" name="rate_weight_from[]" value="<?php echo esc_attr($rate['weight_from']); ?>" class="small-text" step="0.01" min="0" required>
                                            </td>
                                            <td>
                                                <input type="number" name="rate_weight_to[]" value="<?php echo esc_attr($rate['weight_to']); ?>" class="small-text" step="0.01" min="0" required>
                                            </td>
                                            <td>
                                                <input type="number" name="rate_cost[]" value="<?php echo esc_attr($rate['cost']); ?>" class="small-text" step="0.01" min="0" required>
                                            </td>
                                            <td class="actions">
                                                <button type="button" class="button delete-rate"><?php echo esc_html__('Elimina', 'bricoware-stackable'); ?></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                
                                <tfoot>
                                    <tr>
                                        <td colspan="4">
                                            <button type="button" class="button add-rate"><?php echo esc_html__('Aggiungi tariffa', 'bricoware-stackable'); ?></button>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div id="postbox-container-1" class="postbox-container">
                    <div class="postbox">
                        <h2 class="hndle"><?php echo esc_html__('Azioni', 'bricoware-stackable'); ?></h2>
                        
                        <div class="inside">
                            <div class="submitbox" id="submitpost">
                                <div id="major-publishing-actions">
                                    <div id="publishing-action">
                                        <input type="submit" name="save" id="publish" class="button button-primary button-large" value="<?php echo esc_attr__('Salva corriere', 'bricoware-stackable'); ?>">
                                    </div>
                                    <div class="clear"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="postbox">
                        <h2 class="hndle"><?php echo esc_html__('Informazioni', 'bricoware-stackable'); ?></h2>
                        
                        <div class="inside">
                            <p><?php echo esc_html__('Configura questo corriere con le tariffe appropriate in base al peso.', 'bricoware-stackable'); ?></p>
                            
                            <p><?php echo esc_html__('Assicurati di configurare correttamente gli intervalli di peso senza sovrapposizioni.', 'bricoware-stackable'); ?></p>
                            
                            <p><?php echo esc_html__('Esempio:', 'bricoware-stackable'); ?></p>
                            <ul>
                                <li><?php echo esc_html__('0 kg - 1 kg: 5.90 €', 'bricoware-stackable'); ?></li>
                                <li><?php echo esc_html__('1 kg - 3 kg: 8.90 €', 'bricoware-stackable'); ?></li>
                                <li><?php echo esc_html__('3 kg - 5 kg: 12.90 €', 'bricoware-stackable'); ?></li>
                                <li><?php echo esc_html__('5 kg - 10 kg: 19.90 €', 'bricoware-stackable'); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        // Aggiungi nuova tariffa
        $('.add-rate').on('click', function() {
            var lastRow = $('#shipping-rates-table tbody tr:last');
            var newRow = lastRow.clone();
            
            // Pulisci i campi nella nuova riga
            newRow.find('input').each(function() {
                if ($(this).attr('name') === 'rate_weight_from[]') {
                    $(this).val(lastRow.find('input[name="rate_weight_to[]"]').val());
                } else if ($(this).attr('name') === 'rate_weight_to[]') {
                    var lastTo = parseFloat(lastRow.find('input[name="rate_weight_to[]"]').val());
                    $(this).val((lastTo * 2).toFixed(2));
                } else {
                    $(this).val(lastRow.find('input[name="rate_cost[]"]').val());
                }
            });
            
            // Aggiungi la nuova riga alla tabella
            $('#shipping-rates-table tbody').append(newRow);
        });
        
        // Elimina tariffa
        $('#shipping-rates-table').on('click', '.delete-rate', function() {
            var rowCount = $('#shipping-rates-table tbody tr').length;
            
            if (rowCount > 1) {
                $(this).closest('tr').remove();
            } else {
                alert('<?php echo esc_js(__('Devi mantenere almeno una tariffa.', 'bricoware-stackable')); ?>');
            }
        });
        
        // Validazione form
        $('#carrier-edit-form').on('submit', function(e) {
            // Verifica che ci sia almeno una tariffa
            if ($('#shipping-rates-table tbody tr').length === 0) {
                e.preventDefault();
                alert('<?php echo esc_js(__('Devi configurare almeno una tariffa di spedizione.', 'bricoware-stackable')); ?>');
                return false;
            }
            
            // Verifica che gli intervalli di peso siano validi
            var validIntervals = true;
            
            $('#shipping-rates-table tbody tr').each(function() {
                var from = parseFloat($(this).find('input[name="rate_weight_from[]"]').val());
                var to = parseFloat($(this).find('input[name="rate_weight_to[]"]').val());
                
                if (from >= to) {
                    validIntervals = false;
                    $(this).find('input').css('border-color', 'red');
                } else {
                    $(this).find('input').css('border-color', '');
                }
            });
            
            if (!validIntervals) {
                e.preventDefault();
                alert('<?php echo esc_js(__('Gli intervalli di peso non sono validi. Il valore "Da" deve essere minore del valore "A".', 'bricoware-stackable')); ?>');
                return false;
            }
            
            return true;
        });
    });
</script>