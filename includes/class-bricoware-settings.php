<?php
/**
 * Gestione delle impostazioni del plugin
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Bricoware_Settings {
    
    /**
     * Restituisce le impostazioni del plugin
     */
    public static function get_settings() {
        return array(
            'general_title' => array(
                'title' => __('Impostazioni generali', 'bricoware-stackable'),
                'type' => 'title',
                'id' => 'bricoware_general_title',
                'desc' => __('Impostazioni generali per il calcolo del peso volumetrico e la gestione delle spedizioni.', 'bricoware-stackable'),
            ),
            
            'volumetric_divisor' => array(
                'title' => __('Divisore peso volumetrico', 'bricoware-stackable'),
                'type' => 'number',
                'desc' => __('Divisore utilizzato per calcolare il peso volumetrico (cm³ / divisore). Standard: 5000 per spedizioni aeree, 4000 per spedizioni via terra.', 'bricoware-stackable'),
                'desc_tip' => true,
                'id' => 'bricoware_volumetric_divisor',
                'default' => '5000',
                'custom_attributes' => array(
                    'min' => '100',
                    'step' => '100',
                ),
            ),
            
            'show_weight_info' => array(
                'title' => __('Mostra info peso nel checkout', 'bricoware-stackable'),
                'type' => 'checkbox',
                'desc' => __('Mostra informazioni sul peso reale e volumetrico nella pagina di checkout.', 'bricoware-stackable'),
                'desc_tip' => true,
                'id' => 'bricoware_show_weight_info',
                'default' => 'yes',
            ),
            
            'general_end' => array(
                'type' => 'sectionend',
                'id' => 'bricoware_general_end',
            ),
            
            'carriers_title' => array(
                'title' => __('Gestione corrieri', 'bricoware-stackable'),
                'type' => 'title',
                'id' => 'bricoware_carriers_title',
                'desc' => __('Gestisci i corrieri disponibili per le spedizioni. Puoi configurare i dettagli di ogni corriere nella pagina <a href="admin.php?page=bricoware-carriers">Gestione Corrieri</a>.', 'bricoware-stackable'),
            ),
            
            'carriers_end' => array(
                'type' => 'sectionend',
                'id' => 'bricoware_carriers_end',
            ),
        );
    }
    
    /**
     * Salva un nuovo corriere
     */
    public static function save_carrier($carrier_data) {
        $carriers = get_option('bricoware_shipping_carriers', array());
        
        // Genera un ID univoco se non specificato
        if (empty($carrier_data['id'])) {
            $carrier_data['id'] = uniqid('carrier_');
        }
        
        // Aggiungi o aggiorna il corriere
        $carriers[$carrier_data['id']] = $carrier_data;
        
        // Salva l'impostazione
        update_option('bricoware_shipping_carriers', $carriers);
        
        return $carrier_data['id'];
    }
    
    /**
     * Elimina un corriere
     */
    public static function delete_carrier($carrier_id) {
        $carriers = get_option('bricoware_shipping_carriers', array());
        
        if (isset($carriers[$carrier_id])) {
            unset($carriers[$carrier_id]);
            update_option('bricoware_shipping_carriers', $carriers);
            return true;
        }
        
        return false;
    }
    
    /**
     * Ottiene un corriere specifico
     */
    public static function get_carrier($carrier_id) {
        $carriers = get_option('bricoware_shipping_carriers', array());
        
        return isset($carriers[$carrier_id]) ? $carriers[$carrier_id] : null;
    }
    
    /**
     * Ottiene tutti i corrieri
     */
    public static function get_all_carriers() {
        return get_option('bricoware_shipping_carriers', array());
    }
}