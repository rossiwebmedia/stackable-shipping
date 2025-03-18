<?php
/**
 * Bricoware Volumetric Shipping Method
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Classe che implementa il metodo di spedizione volumetrico per gestire vari corrieri
 * con diverse tariffazioni basate su peso reale o volumetrico
 */
class Bricoware_Volumetric_Shipping_Method extends WC_Shipping_Method {
    
    /**
     * Costruttore della classe
     */
    public function __construct($instance_id = 0) {
        $this->id = 'bricoware_volumetric';
        $this->instance_id = absint($instance_id);
        $this->method_title = __('Spedizione Bricoware (peso reale e volumetrico)', 'bricoware-stackable');
        $this->method_description = __('Gestisce le spedizioni considerando il maggiore tra peso reale e peso volumetrico, con supporto per prodotti impilabili', 'bricoware-stackable');
        $this->supports = array(
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        );
        
        // Carica le impostazioni
        $this->init_form_fields();
        $this->init_settings();
        
        // Definisce le variabili in base alle impostazioni
        $this->title = $this->get_option('title');
        $this->tax_status = $this->get_option('tax_status');
        $this->enabled = $this->get_option('enabled');
        $this->carriers = $this->get_carriers();
        
        // Salva le impostazioni
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
        
        // Gestisci le tariffe personalizzate
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_custom_rates'));
    }
    
    /**
     * Inizializza i campi delle impostazioni
     */
    public function init_form_fields() {
        $this->instance_form_fields = array(
            'title' => array(
                'title' => __('Titolo metodo', 'bricoware-stackable'),
                'type' => 'text',
                'description' => __('Titolo visualizzato al cliente', 'bricoware-stackable'),
                'default' => __('Spedizione Standard', 'bricoware-stackable'),
                'desc_tip' => true
            ),
            'tax_status' => array(
                'title' => __('Stato tasse', 'bricoware-stackable'),
                'type' => 'select',
                'description' => __('Seleziona se applicare le tasse alle spedizioni', 'bricoware-stackable'),
                'default' => 'taxable',
                'options' => array(
                    'taxable' => __('Tassabile', 'bricoware-stackable'),
                    'none' => __('Non tassabile', 'bricoware-stackable')
                ),
                'desc_tip' => true
            ),
            'carrier_selection' => array(
                'title' => __('Selezione corriere', 'bricoware-stackable'),
                'type' => 'select',
                'description' => __('Scegli come selezionare il corriere da utilizzare', 'bricoware-stackable'),
                'default' => 'cheapest',
                'options' => array(
                    'cheapest' => __('Corriere più economico', 'bricoware-stackable'),
                    'first_available' => __('Primo corriere disponibile', 'bricoware-stackable'),
                    'fastest' => __('Corriere più veloce', 'bricoware-stackable'),
                    'customer_choice' => __('Scelta cliente', 'bricoware-stackable')
                ),
                'desc_tip' => true
            ),
            'show_carrier_name' => array(
                'title' => __('Mostra nome corriere', 'bricoware-stackable'),
                'type' => 'checkbox',
                'description' => __('Mostra il nome del corriere nel checkout', 'bricoware-stackable'),
                'default' => 'yes',
                'desc_tip' => true
            ),
            'carriers_title' => array(
                'title' => __('Corrieri disponibili', 'bricoware-stackable'),
                'type' => 'title',
                'description' => __('I corrieri possono essere configurati nella pagina <a href="admin.php?page=bricoware-carriers">Gestione Corrieri</a>', 'bricoware-stackable'),
            ),
        );
        
        // Aggiungi i corrieri dalle impostazioni
        $carriers = $this->get_carriers();
        
        if (!empty($carriers)) {
            foreach ($carriers as $carrier_id => $carrier) {
                $this->instance_form_fields['carrier_' . $carrier_id . '_enabled'] = array(
                    'title' => sprintf(__('Abilita %s', 'bricoware-stackable'), $carrier['name']),
                    'type' => 'checkbox',
                    'default' => 'yes',
                    'desc_tip' => true,
                    'description' => sprintf(__('Abilita il corriere %s per questa zona di spedizione', 'bricoware-stackable'), $carrier['name']),
                );
                
                $this->instance_form_fields['carrier_' . $carrier_id . '_priority'] = array(
                    'title' => sprintf(__('Priorità %s', 'bricoware-stackable'), $carrier['name']),
                    'type' => 'number',
                    'default' => '10',
                    'desc_tip' => true,
                    'description' => __('Priorità del corriere (numeri più bassi hanno priorità più alta)', 'bricoware-stackable'),
                    'custom_attributes' => array(
                        'min' => '1',
                        'step' => '1'
                    )
                );
            }
        } else {
            $this->instance_form_fields['no_carriers'] = array(
                'title' => __('Nessun corriere configurato', 'bricoware-stackable'),
                'type' => 'title',
                'description' => __('Non ci sono corrieri configurati. Vai alla pagina <a href="admin.php?page=bricoware-carriers">Gestione Corrieri</a> per configurarne almeno uno.', 'bricoware-stackable'),
            );
        }
    }
    
    /**
     * Ottiene i corrieri configurati
     */
    private function get_carriers() {
        $carriers = get_option('bricoware_shipping_carriers', array());
        return is_array($carriers) ? $carriers : array();
    }
    
    /**
     * Processa le tariffe personalizzate
     */
    public function process_custom_rates() public function process_custom_rates() {
    if (isset($_POST['bricoware_rates']) && is_array($_POST['bricoware_rates'])) {
        $rates = array();
        
        foreach ($_POST['bricoware_rates'] as $carrier_id => $carrier_rates) {
            if (!isset($rates[$carrier_id])) {
                $rates[$carrier_id] = array();
            }
            
            foreach ($carrier_rates as $rate) {
                if (!empty($rate['weight_from']) && !empty($rate['weight_to']) && isset($rate['cost'])) {
                    $weight_from = floatval($rate['weight_from']);
                    $weight_to = floatval($rate['weight_to']);
                    $cost = floatval($rate['cost']);
                    
                    $rates[$carrier_id][] = array(
                        'weight_from' => $weight_from,
                        'weight_to' => $weight_to,
                        'cost' => $cost
                    );
                }
            }
        }
        
        update_option('bricoware_shipping_instance_rates_' . $this->instance_id, $rates);
    }
}
    
    /**
     * Calcola la tariffa di spedizione
     */
    public function calculate_shipping($package = array()) {
    // Ottieni i dati della spedizione
    $shipping_data = WC()->session ? WC()->session->get('bricoware_shipping_data') : null;
    
    if (!$shipping_data || !is_array($shipping_data)) {
        // Se i dati non sono disponibili o non sono nel formato corretto, non possiamo calcolare le tariffe
        return;
    }
    
    // Assicuriamoci che tutti i campi necessari siano presenti
    $required_fields = array('total_real_weight', 'volumetric_weight', 'dimensions');
    foreach ($required_fields as $field) {
        if (!isset($shipping_data[$field])) {
            return;
        }
    }
    
    $real_weight = $shipping_data['total_real_weight'];
    $volumetric_weight = $shipping_data['volumetric_weight'];
    $dimensions = $shipping_data['dimensions'];
    
        // Ottieni la modalità di selezione del corriere
        $carrier_selection = $this->get_option('carrier_selection', 'cheapest');
        $show_carrier_name = $this->get_option('show_carrier_name', 'yes') === 'yes';
        
        // Array per memorizzare le tariffe valide
        $valid_rates = array();
        
        // Controlla tutti i corrieri configurati
        foreach ($this->carriers as $carrier_id => $carrier) {
            // Verifica se il corriere è abilitato per questa istanza
            $carrier_enabled = $this->get_option('carrier_' . $carrier_id . '_enabled', 'yes') === 'yes';
            
            if (!$carrier_enabled) {
                continue;
            }
            
            // Determina il peso da utilizzare in base al tipo di peso del corriere
            $weight = 0;
            switch ($carrier['weight_type']) {
                case 'max':
                    $weight = max($real_weight, $volumetric_weight);
                    break;
                case 'real':
                    $weight = $real_weight;
                    break;
                case 'volumetric':
                    $weight = $volumetric_weight;
                    break;
            }
            
            // Controlla i limiti dimensionali
            $dimensions_ok = true;
            if (isset($carrier['dimensions_limit']) && is_array($carrier['dimensions_limit'])) {
                // Se è un array, usa direttamente i valori
                if (!empty($carrier['dimensions_limit']['length']) && $dimensions['length'] > floatval($carrier['dimensions_limit']['length'])) {
                    $dimensions_ok = false;
                }
                
                if (!empty($carrier['dimensions_limit']['width']) && $dimensions['width'] > floatval($carrier['dimensions_limit']['width'])) {
                    $dimensions_ok = false;
                }
                
                if (!empty($carrier['dimensions_limit']['height']) && $dimensions['height'] > floatval($carrier['dimensions_limit']['height'])) {
                    $dimensions_ok = false;
                }
            }
            
            if (!$dimensions_ok) {
                continue;
            }
            
            // Trova la tariffa applicabile
            $rate_cost = $this->find_rate_cost($carrier_id, $weight);
            
            if ($rate_cost !== null) {
                $carrier_priority = intval($this->get_option('carrier_' . $carrier_id . '_priority', 10));
                
                $valid_rates[] = array(
                    'id' => $carrier_id,
                    'name' => $carrier['name'],
                    'cost' => $rate_cost,
                    'transit_time' => isset($carrier['transit_time']) ? $carrier['transit_time'] : '',
                    'priority' => $carrier_priority
                );
            }
        }
        
        // Se non ci sono tariffe valide, termina
        if (empty($valid_rates)) {
            return;
        }
        
        // Ordina le tariffe in base al criterio selezionato
        if ($carrier_selection === 'cheapest') {
            usort($valid_rates, function($a, $b) {
                if ($a['cost'] === $b['cost']) {
                    return $a['priority'] - $b['priority'];
                }
                return $a['cost'] - $b['cost'];
            });
        } elseif ($carrier_selection === 'fastest') {
            usort($valid_rates, function($a, $b) {
                if (empty($a['transit_time']) || empty($b['transit_time'])) {
                    return $a['priority'] - $b['priority'];
                }
                if ($a['transit_time'] === $b['transit_time']) {
                    return $a['priority'] - $b['priority'];
                }
                return $a['transit_time'] - $b['transit_time'];
            });
        } else {
            // Prima disponibile o scelta cliente, ordina per priorità
            usort($valid_rates, function($a, $b) {
                return $a['priority'] - $b['priority'];
            });
        }
        
        // Aggiungi le tariffe al checkout
        if ($carrier_selection === 'customer_choice') {
            // Mostra tutti i corrieri disponibili al cliente
            foreach ($valid_rates as $rate) {
                $rate_id = $this->get_rate_id($rate['id']);
                $rate_label = $show_carrier_name ? $rate['name'] : $this->title;
                
                if (!empty($rate['transit_time'])) {
                    $rate_label .= ' (' . sprintf(__('consegna in %d giorni', 'bricoware-stackable'), $rate['transit_time']) . ')';
                }
                
                $this->add_rate(array(
                    'id' => $rate_id,
                    'label' => $rate_label,
                    'cost' => $rate['cost'],
                    'package' => $package,
                ));
            }
        } else {
            // Seleziona il corriere secondo il criterio (il primo nell'array dopo l'ordinamento)
            $selected_rate = reset($valid_rates);
            $rate_id = $this->get_rate_id($selected_rate['id']);
            $rate_label = $this->title;
            
            if ($show_carrier_name) {
                $rate_label .= ' (' . $selected_rate['name'] . ')';
            }
            
            if (!empty($selected_rate['transit_time'])) {
                $rate_label .= ' - ' . sprintf(__('consegna in %d giorni', 'bricoware-stackable'), $selected_rate['transit_time']);
            }
            
            $this->add_rate(array(
                'id' => $rate_id,
                'label' => $rate_label,
                'cost' => $selected_rate['cost'],
                'package' => $package,
            ));
        }
    }
    
    /**
     * Trova il costo della tariffa applicabile
     */
    private function find_rate_cost($carrier_id, $weight) {
        // Ottieni le tariffe specifiche dell'istanza
        $instance_rates = get_option('bricoware_shipping_instance_rates_' . $this->instance_id, array());
        
        // Verifica se ci sono tariffe specifiche per questo corriere in questa istanza
        if (isset($instance_rates[$carrier_id]) && !empty($instance_rates[$carrier_id])) {
            $rates = $instance_rates[$carrier_id];
        } else {
            // Utilizza le tariffe globali del corriere
            $rates = isset($this->carriers[$carrier_id]['rates']) ? $this->carriers[$carrier_id]['rates'] : array();
        }
        
        // Trova la tariffa applicabile
        foreach ($rates as $rate) {
            if (isset($rate['weight_from']) && isset($rate['weight_to']) && isset($rate['cost'])) {
                if ($weight >= floatval($rate['weight_from']) && $weight <= floatval($rate['weight_to'])) {
                    return floatval($rate['cost']);
                }
            }
        }
        
        // Se non troviamo una tariffa specifica, cerchiamo la tariffa massima
        $max_rate = null;
        $max_weight_to = 0;
        
        foreach ($rates as $rate) {
            if (isset($rate['weight_to']) && floatval($rate['weight_to']) > $max_weight_to) {
                $max_weight_to = floatval($rate['weight_to']);
                $max_rate = isset($rate['cost']) ? floatval($rate['cost']) : null;
            }
        }
        
        return $max_rate;
    }
    
    /**
     * Genera un ID univoco per la tariffa
     */
    private function get_rate_id($carrier_id) {
        $this->rate_id_count = isset($this->rate_id_count) ? $this->rate_id_count + 1 : 1;
        return $this->id . ':' . $this->instance_id . ':' . $carrier_id . ':' . $this->rate_id_count;
    }
    
    /**
     * Output HTML per le impostazioni nella parte admin
     */
    public function admin_options() {
        // Output del form standard
        parent::admin_options();
        
        // Output dell'editor delle tariffe
        $this->output_rates_editor();
    }
    
    /**
     * Output dell'editor delle tariffe
     */
    private function output_rates_editor() {
        $carriers = $this->get_carriers();
        $instance_rates = get_option('bricoware_shipping_instance_rates_' . $this->instance_id, array());
        
        if (empty($carriers)) {
            return;
        }
        
        echo '<h3>' . esc_html__('Tariffe specifiche per zona', 'bricoware-stackable') . '</h3>';
        echo '<p>' . esc_html__('Qui puoi configurare tariffe specifiche per questa zona di spedizione. Lascia vuoto per utilizzare le tariffe globali dei corrieri.', 'bricoware-stackable') . '</p>';
        
        echo '<div class="bricoware-rates-editor">';
        
        foreach ($carriers as $carrier_id => $carrier) {
            $carrier_enabled = $this->get_option('carrier_' . $carrier_id . '_enabled', 'yes') === 'yes';
            $carrier_display = $carrier_enabled ? '' : 'style="display:none;"';
            
            echo '<div class="bricoware-carrier-rates" ' . $carrier_display . ' data-carrier-id="' . esc_attr($carrier_id) . '">';
            echo '<h4>' . esc_html($carrier['name']) . '</h4>';
            
            echo '<table class="widefat bricoware-rates-table">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>' . esc_html__('Peso da (kg)', 'bricoware-stackable') . '</th>';
            echo '<th>' . esc_html__('Peso a (kg)', 'bricoware-stackable') . '</th>';
            echo '<th>' . esc_html__('Costo (€)', 'bricoware-stackable') . '</th>';
            echo '<th>' . esc_html__('Azioni', 'bricoware-stackable') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            // Ottieni le tariffe specifiche dell'istanza o le tariffe globali
            $rates = isset($instance_rates[$carrier_id]) ? $instance_rates[$carrier_id] : array();
            
            // Se non ci sono tariffe, aggiungi una riga vuota
            if (empty($rates)) {
                $row_index = 0;
                $rate = array(
                    'weight_from' => '0',
                    'weight_to' => '1',
                    'cost' => '5'
                );
                include BRICOWARE_SHIPPING_PLUGIN_DIR . 'includes/admin/views/rate-row.php';
            } else {
                // Mostra le tariffe esistenti
                foreach ($rates as $row_index => $rate) {
                    include BRICOWARE_SHIPPING_PLUGIN_DIR . 'includes/admin/views/rate-row.php';
                }
            }
            
            echo '</tbody>';
            echo '<tfoot>';
            echo '<tr>';
            echo '<td colspan="4">';
            echo '<button type="button" class="button add-rate" data-carrier-id="' . esc_attr($carrier_id) . '">' . esc_html__('Aggiungi tariffa', 'bricoware-stackable') . '</button>';
            echo '</td>';
            echo '</tr>';
            echo '</tfoot>';
            echo '</table>';
            echo '</div>';
        }
        
        echo '</div>';
        
        // JavaScript per gestire le righe delle tariffe
        echo '<script type="text/javascript">
            jQuery(document).ready(function($) {
                // Mostra/nascondi sezioni tariffe in base allo stato del corriere
                $("[id^=\'woocommerce_bricoware_volumetric_carrier_\'][id$=\'_enabled\']").change(function() {
                    var carrierId = $(this).attr("id").match(/carrier_([a-zA-Z0-9_]+)_enabled/)[1];
                    if ($(this).is(":checked")) {
                        $(".bricoware-carrier-rates[data-carrier-id=\'" + carrierId + "\']").show();
                    } else {
                        $(".bricoware-carrier-rates[data-carrier-id=\'" + carrierId + "\']").hide();
                    }
                });
                
                // Aggiungi tariffa
                $(".bricoware-rates-editor").on("click", ".add-rate", function(e) {
                    e.preventDefault();
                    var carrierId = $(this).data("carrier-id");
                    var rowIndex = $(".bricoware-carrier-rates[data-carrier-id=\'" + carrierId + "\'] tbody tr").length;
                    
                    $.ajax({
                        url: ajaxurl,
                        type: "POST",
                        data: {
                            action: "bricoware_add_rate_row",
                            nonce: bricoware_shipping.nonce,
                            carrier_id: carrierId,
                            row_index: rowIndex
                        },
                        success: function(response) {
                            if (response.success) {
                                $(".bricoware-carrier-rates[data-carrier-id=\'" + carrierId + "\'] tbody").append(response.data.html);
                            }
                        }
                    });
                });
                
                // Elimina tariffa
                $(".bricoware-rates-editor").on("click", ".delete-rate", function(e) {
                    e.preventDefault();
                    if (confirm(bricoware_shipping.i18n.confirm_delete)) {
                        $(this).closest("tr").remove();
                    }
                });
            });
        </script>';
    }
}