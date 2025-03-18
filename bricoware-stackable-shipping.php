<?php
/**
 * Plugin Name: Bricoware Stackable Shipping
 * Description: Plugin personalizzato per gestire spedizioni con prodotti impilabili e calcolo del peso volumetrico corretto
 * Version: 1.0.0
 * Author: Bricoware
 * Text Domain: bricoware-stackable
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('BRICOWARE_SHIPPING_VERSION', '1.0.0');
define('BRICOWARE_SHIPPING_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BRICOWARE_SHIPPING_PLUGIN_URL', plugin_dir_url(__FILE__));

class Bricoware_Stackable_Shipping {

    /**
     * Istanza singleton
     */
    private static $instance = null;

    /**
     * Ottieni l'istanza singleton
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Costruttore
     */
    private function __construct() {
        // Inizializzazione
        add_action('plugins_loaded', array($this, 'init'));
        
        // Carica gli script e gli stili admin
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Aggiunge tab alle impostazioni di prodotto per definire se è impilabile
        add_filter('woocommerce_product_data_tabs', array($this, 'add_stackable_product_tab'));
        add_action('woocommerce_product_data_panels', array($this, 'add_stackable_product_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_stackable_product_fields'));
        
        // Aggiunge una colonna nella lista prodotti per indicare se è impilabile
        add_filter('manage_edit-product_columns', array($this, 'add_product_list_column'));
        add_action('manage_product_posts_custom_column', array($this, 'populate_product_list_column'), 10, 2);
        
        // Modifica il calcolo del peso volumetrico durante il checkout
        add_filter('woocommerce_package_rates', array($this, 'adjust_shipping_rates'), 10, 2);
        
        // Aggiunge una pagina di impostazioni
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Aggiungi un menu in WooCommerce > Impostazioni > Spedizioni
        add_filter('woocommerce_get_sections_shipping', array($this, 'add_wc_shipping_section'));
        add_filter('woocommerce_get_settings_shipping', array($this, 'add_wc_shipping_settings'), 10, 2);
        
        // Mostra informazioni sul peso volumetrico nella pagina di checkout
        add_action('woocommerce_review_order_before_shipping', array($this, 'show_volumetric_weight_info'));
        
        // Mostra informazioni sul peso volumetrico nella pagina del carrello
        add_action('woocommerce_before_cart_totals', array($this, 'show_volumetric_weight_info'));
        
        // AJAX handler per aggiungere dinamicamente nuove righe tariffe
        add_action('wp_ajax_bricoware_add_rate_row', array($this, 'ajax_add_rate_row'));
    }

    /**
     * Inizializzazione
     */
    public function init() {
        // Carica i file di traduzione
        load_plugin_textdomain('bricoware-stackable', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Verifica che WooCommerce sia attivo
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>' . esc_html__('Bricoware Stackable Shipping richiede WooCommerce per funzionare.', 'bricoware-stackable') . '</p></div>';
            });
            return;
        }
        
        // Carica le classi necessarie
        $this->load_dependencies();
        
        // Registra i metodi di spedizione
        add_action('woocommerce_shipping_init', array($this, 'shipping_init'));
        add_filter('woocommerce_shipping_methods', array($this, 'register_shipping_method'));
    }
    
    /**
     * Carica le dipendenze
     */
    private function load_dependencies() {
        // Verifica che i file esistano
        $settings_path = BRICOWARE_SHIPPING_PLUGIN_DIR . 'includes/class-bricoware-settings.php';
        $shipping_path = BRICOWARE_SHIPPING_PLUGIN_DIR . 'includes/class-bricoware-volumetric-shipping-method.php';
        
        if (!file_exists($settings_path)) {
            error_log('File not found: ' . $settings_path);
            return;
        }
        
        if (!file_exists($shipping_path)) {
            error_log('File not found: ' . $shipping_path);
            return;
        }
        
        // Carica la classe per la gestione delle impostazioni
        require_once $settings_path;
        
        // Carica la classe per il metodo di spedizione
        require_once $shipping_path;
    }
    
    /**
     * Carica script e stili per l'amministrazione
     */
    public function enqueue_admin_scripts($hook) {
        $screen = get_current_screen();
        
        // Solo nelle pagine relative al plugin o ai prodotti
        if (($hook === 'woocommerce_page_wc-settings' && isset($_GET['section']) && $_GET['section'] === 'bricoware_shipping') || 
            ($screen && $screen->post_type === 'product') ||
            ($hook === 'woocommerce_page_bricoware-carriers')) {
            
            wp_enqueue_style('bricoware-admin-style', BRICOWARE_SHIPPING_PLUGIN_URL . 'assets/css/admin.css', array(), BRICOWARE_SHIPPING_VERSION);
            wp_enqueue_script('bricoware-admin-script', BRICOWARE_SHIPPING_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'jquery-ui-sortable'), BRICOWARE_SHIPPING_VERSION, true);
            
            // Passa variabili allo script
            wp_localize_script('bricoware-admin-script', 'bricoware_shipping', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('bricoware_shipping_nonce'),
                'i18n' => array(
                    'confirm_delete' => __('Sei sicuro di voler eliminare questa tariffa?', 'bricoware-stackable'),
                    'confirm_delete_carrier' => __('Sei sicuro di voler eliminare questo corriere?', 'bricoware-stackable'),
                    'add_rate' => __('Aggiungi tariffa', 'bricoware-stackable'),
                    'delete' => __('Elimina', 'bricoware-stackable'),
                    'min_one_rate' => __('Devi mantenere almeno una tariffa.', 'bricoware-stackable')
                )
            ));
        }
    }

    /**
     * Aggiunge un tab per le impostazioni dei prodotti impilabili
     */
    public function add_stackable_product_tab($tabs) {
        $tabs['stackable'] = array(
            'label'    => __('Prodotto impilabile', 'bricoware-stackable'),
            'target'   => 'stackable_product_data',
            'class'    => array('show_if_simple', 'show_if_variable'),
            'priority' => 80,
        );
        return $tabs;
    }

    /**
     * Aggiunge i campi per configurare un prodotto come impilabile
     */
    public function add_stackable_product_fields() {
        echo '<div id="stackable_product_data" class="panel woocommerce_options_panel">';
        
        // Checkbox per definire se il prodotto è impilabile
        woocommerce_wp_checkbox(array(
            'id'          => '_is_stackable',
            'label'       => __('Prodotto impilabile', 'bricoware-stackable'),
            'description' => __('Seleziona se questo prodotto è impilabile durante la spedizione', 'bricoware-stackable'),
        ));
        
        echo '<div class="stackable-options" style="padding: 0 20px 10px; display: none;">';
        
        // Campo per l'incremento dell'altezza
        woocommerce_wp_text_input(array(
            'id'                => '_stackable_height_increment',
            'label'             => __('Incremento altezza (cm)', 'bricoware-stackable'),
            'description'       => __('Di quanti cm aumenta l\'altezza quando si aggiunge un\'unità di questo prodotto', 'bricoware-stackable'),
            'type'              => 'number',
            'custom_attributes' => array(
                'step' => '0.1',
                'min'  => '0',
            ),
            'desc_tip'          => true,
        ));
        
        // Numero massimo di unità impilabili
        woocommerce_wp_text_input(array(
            'id'                => '_stackable_max_units',
            'label'             => __('Massimo unità impilabili', 'bricoware-stackable'),
            'description'       => __('Numero massimo di unità che possono essere impilate. Lascia vuoto per nessun limite.', 'bricoware-stackable'),
            'type'              => 'number',
            'custom_attributes' => array(
                'step' => '1',
                'min'  => '2',
            ),
            'desc_tip'          => true,
        ));
        
        echo '</div>';
        
        echo '<script type="text/javascript">
            jQuery(document).ready(function($) {
                // Mostra/nascondi opzioni impilabili
                function toggleStackableOptions() {
                    if ($("#_is_stackable").is(":checked")) {
                        $(".stackable-options").show();
                    } else {
                        $(".stackable-options").hide();
                    }
                }
                
                // Inizializza
                toggleStackableOptions();
                
                // Evento change
                $("#_is_stackable").change(function() {
                    toggleStackableOptions();
                });
            });
        </script>';
        
        echo '</div>';
    }

    /**
     * Salva i campi del prodotto impilabile
     */
    public function save_stackable_product_fields($product_id) {
        $is_stackable = isset($_POST['_is_stackable']) ? 'yes' : 'no';
        update_post_meta($product_id, '_is_stackable', $is_stackable);
        
        if (isset($_POST['_stackable_height_increment'])) {
            update_post_meta($product_id, '_stackable_height_increment', wc_clean($_POST['_stackable_height_increment']));
        }
        
        if (isset($_POST['_stackable_max_units'])) {
            update_post_meta($product_id, '_stackable_max_units', wc_clean($_POST['_stackable_max_units']));
        }
    }
    
    /**
     * Aggiunge una colonna nella lista prodotti
     */
    public function add_product_list_column($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            
            // Aggiungi la colonna dopo la colonna prezzo
            if ($key === 'price') {
                $new_columns['stackable'] = __('Impilabile', 'bricoware-stackable');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Popola la colonna personalizzata
     */
    public function populate_product_list_column($column, $product_id) {
        if ($column === 'stackable') {
            $is_stackable = get_post_meta($product_id, '_is_stackable', true);
            
            if ($is_stackable === 'yes') {
                $increment = get_post_meta($product_id, '_stackable_height_increment', true);
                $max_units = get_post_meta($product_id, '_stackable_max_units', true);
                
                echo '<span class="dashicons dashicons-yes" style="color: green;"></span> ';
                
                if ($increment) {
                    echo esc_html(sprintf(__('Inc: %s cm', 'bricoware-stackable'), $increment));
                }
                
                if ($max_units) {
                    echo ' / ' . esc_html(sprintf(__('Max: %s', 'bricoware-stackable'), $max_units));
                }
            } else {
                echo '<span class="dashicons dashicons-no" style="color: #ccc;"></span>';
            }
        }
    }
    
    /**
     * Inizializza il metodo di spedizione
     */
    public function shipping_init() {
        // La classe viene già caricata in load_dependencies()
    }
    
    /**
     * Registra il metodo di spedizione
     */
    public function register_shipping_method($methods) {
        $methods['bricoware_volumetric'] = 'Bricoware_Volumetric_Shipping_Method';
        return $methods;
    }
    
    /**
     * Modifica il calcolo delle tariffe di spedizione
     */
    public function adjust_shipping_rates($rates, $package) {
        // Calcola i pesi
        $volumetric_data = $this->calculate_volumetric_data($package);
        
        // Memorizza i dati per uso futuro
        WC()->session->set('bricoware_shipping_data', $volumetric_data);
        
        return $rates;
    }
    
    /**
     * Calcola dati volumetrici completi per il pacchetto
     */
    public function calculate_volumetric_data($package) {
        $grouped_products = $this->group_products_by_id($package['contents']);
        $total_volume = 0;
        $total_real_weight = 0;
        $dimensions = array(
            'length' => 0,
            'width' => 0,
            'height' => 0
        );
        
        $products_data = array();
        
        foreach ($grouped_products as $product_id => $item_data) {
            $product = $item_data['product'];
            $quantity = $item_data['quantity'];
            
            // Ottieni le dimensioni originali
            $length = $product->get_length();
            $width = $product->get_width();
            $base_height = $product->get_height();
            
            // Verifica se il prodotto è impilabile
            $is_stackable = get_post_meta($product_id, '_is_stackable', true) === 'yes';
            $height_increment = 0;
            $max_units = 0;
            $total_height = $base_height;
            
            // Calcola il volume
            if ($is_stackable && $quantity > 1) {
                $height_increment = floatval(get_post_meta($product_id, '_stackable_height_increment', true));
                $max_units = intval(get_post_meta($product_id, '_stackable_max_units', true));
                
                // Se l'incremento non è specificato, usa l'altezza normale
                if (empty($height_increment)) {
                    $height_increment = $base_height;
                }
                
                // Rispetta il numero massimo di unità impilabili
                if ($max_units > 0 && $quantity > $max_units) {
                    // Calcola quanti "stack" completi abbiamo
                    $full_stacks = ceil($quantity / $max_units);
                    $total_height = ($base_height + ($height_increment * ($max_units - 1))) * $full_stacks;
                } else {
                    // Calcola l'altezza totale: prima unità con altezza base + incrementi per le unità aggiuntive
                    $total_height = $base_height + ($height_increment * ($quantity - 1));
                }
                
                $volume = $length * $width * $total_height;
                $product_details = array(
                    'stacked' => true,
                    'quantity' => $quantity,
                    'base_height' => $base_height,
                    'increment' => $height_increment,
                    'total_height' => $total_height,
                    'max_units' => $max_units > 0 ? $max_units : null
                );
            } else {
                // Per prodotti non impilabili, calcola il volume normalmente
                $volume = $length * $width * $base_height * $quantity;
                $total_height = $base_height;
                $product_details = array(
                    'stacked' => false,
                    'quantity' => $quantity,
                    'base_height' => $base_height,
                    'total_height' => $total_height * $quantity
                );
            }
            
            // Aggiorna il peso totale
            $product_weight = $product->get_weight();
            $total_real_weight += $product_weight * $quantity;
            
            // Aggiorna le dimensioni massime
            $dimensions['length'] = max($dimensions['length'], $length);
            $dimensions['width'] = max($dimensions['width'], $width);
            $dimensions['height'] += $total_height; // Somma le altezze per un caso peggiore
            
            // Aggiungi ai prodotti
            $products_data[] = array(
                'id' => $product_id,
                'name' => $product->get_name(),
                'quantity' => $quantity,
                'dimensions' => array(
                    'length' => $length,
                    'width' => $width,
                    'height' => $base_height
                ),
                'weight' => $product_weight,
                'is_stackable' => $is_stackable,
                'details' => $product_details,
                'volume' => $volume
            );
            
            $total_volume += $volume;
        }
        
        // Calcola il peso volumetrico (cm³ / 5000)
        $divisor = $this->get_volumetric_divisor();
        $volumetric_weight = $total_volume / $divisor;
        
        return array(
            'products' => $products_data,
            'total_real_weight' => $total_real_weight,
            'total_volume' => $total_volume,
            'volumetric_weight' => $volumetric_weight,
            'volumetric_divisor' => $divisor,
            'dimensions' => $dimensions,
            'shipping_weight' => max($total_real_weight, $volumetric_weight)
        );
    }
    
    /**
     * Ottiene il divisore per il calcolo del peso volumetrico
     */
    private function get_volumetric_divisor() {
        // Ottieni il divisore dalle impostazioni o usa il valore predefinito 5000
        $divisor = get_option('bricoware_volumetric_divisor', 5000);
        return floatval($divisor) > 0 ? floatval($divisor) : 5000;
    }
    
    /**
     * Raggruppa i prodotti per ID per gestire meglio i prodotti impilabili
     */
    private function group_products_by_id($items) {
        $grouped = array();
        
        foreach ($items as $item) {
            $product = $item['data'];
            $product_id = $product->get_id();
            
            if (!isset($grouped[$product_id])) {
                $grouped[$product_id] = array(
                    'product' => $product,
                    'quantity' => 0,
                );
            }
            
            $grouped[$product_id]['quantity'] += $item['quantity'];
        }
        
        return $grouped;
    }
    
    /**
     * Aggiunge una sezione alle impostazioni di spedizione di WooCommerce
     */
    public function add_wc_shipping_section($sections) {
        $sections['bricoware_shipping'] = __('Bricoware Shipping', 'bricoware-stackable');
        return $sections;
    }
    
    /**
     * Aggiunge le impostazioni per la nuova sezione
     */
    public function add_wc_shipping_settings($settings, $current_section) {
        // Verifica se siamo nella sezione corretta
        if ($current_section === 'bricoware_shipping') {
            return Bricoware_Settings::get_settings();
        }
        
        return $settings;
    }
    
    /**
     * Aggiunge una pagina al menu di amministrazione
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Gestione Corrieri Bricoware', 'bricoware-stackable'),
            __('Gestione Corrieri', 'bricoware-stackable'),
            'manage_woocommerce',
            'bricoware-carriers',
            array($this, 'render_carriers_page')
        );
    }
    
    /**
     * Renderizza la pagina di gestione corrieri
     */
    public function render_carriers_page() {
    $page_path = BRICOWARE_SHIPPING_PLUGIN_DIR . 'includes/admin/views/carriers-page.php';
    if (file_exists($page_path)) {
        include $page_path;
    } else {
        echo '<div class="error"><p>' . esc_html__('File template non trovato: ', 'bricoware-stackable') . esc_html($page_path) . '</p></div>';
    }
}
    
    /**
     * Mostra informazioni sul peso volumetrico
     */
    public function show_volumetric_weight_info() {
    // Ottieni i dati della spedizione
    $shipping_data = WC()->session ? WC()->session->get('bricoware_shipping_data') : null;
    
    if ($shipping_data && is_array($shipping_data) && isset($shipping_data['total_real_weight']) && isset($shipping_data['volumetric_weight']) && isset($shipping_data['shipping_weight'])) {
        $show_info = get_option('bricoware_show_weight_info', 'yes') === 'yes';
        
        if ($show_info) {
                echo '<div class="bricoware-shipping-info">';
                echo '<h4>' . esc_html__('Informazioni spedizione', 'bricoware-stackable') . '</h4>';
                echo '<p>';
                echo esc_html__('Peso reale:', 'bricoware-stackable') . ' <strong>' . wc_format_weight($shipping_data['total_real_weight']) . '</strong><br>';
                echo esc_html__('Peso volumetrico:', 'bricoware-stackable') . ' <strong>' . wc_format_weight($shipping_data['volumetric_weight']) . '</strong><br>';
                echo esc_html__('Peso applicato:', 'bricoware-stackable') . ' <strong>' . wc_format_weight($shipping_data['shipping_weight']) . '</strong>';
                echo '</p>';
                echo '</div>';
            }
        }
    }
    
    /**
     * AJAX handler per aggiungere una nuova riga tariffa
     */
    public function ajax_add_rate_row() {
    // Verifica nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bricoware_shipping_nonce')) {
        wp_send_json_error('Invalid nonce');
    }
    
    // Ottieni i parametri
    $carrier_id = isset($_POST['carrier_id']) ? sanitize_text_field($_POST['carrier_id']) : '';
    $row_index = isset($_POST['row_index']) ? intval($_POST['row_index']) : 0;
    
    $rate = array(
        'weight_from' => '0',
        'weight_to' => '0',
        'cost' => '0'
    );
    
    // Verifica che il file esista
    $template_path = BRICOWARE_SHIPPING_PLUGIN_DIR . 'includes/admin/views/rate-row.php';
    
    if (!file_exists($template_path)) {
        wp_send_json_error('Template file not found');
        return;
    }
    
    // Includi il template della riga
    ob_start();
    include $template_path;
    $html = ob_get_clean();
    
    wp_send_json_success(array('html' => $html));
}
    
    /**
     * Funzione di attivazione del plugin
     */
    public static function activate() {
        // Crea directory per gli assets
        self::create_required_directories();
        
        // Imposta valori predefiniti
        add_option('bricoware_volumetric_divisor', 5000);
        add_option('bricoware_show_weight_info', 'yes');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Funzione di disattivazione del plugin
     */
    public static function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Crea le directory necessarie
     */
    private static function create_required_directories() {
    $dirs = array(
        BRICOWARE_SHIPPING_PLUGIN_DIR . 'assets',
        BRICOWARE_SHIPPING_PLUGIN_DIR . 'assets/css',
        BRICOWARE_SHIPPING_PLUGIN_DIR . 'assets/js',
        BRICOWARE_SHIPPING_PLUGIN_DIR . 'includes/admin',
        BRICOWARE_SHIPPING_PLUGIN_DIR . 'includes/admin/views',
    );
    
    foreach ($dirs as $dir) {
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
    }
}


// Registra funzioni di attivazione/disattivazione
register_activation_hook(__FILE__, array('Bricoware_Stackable_Shipping', 'activate'));
register_deactivation_hook(__FILE__, array('Bricoware_Stackable_Shipping', 'deactivate'));

// Inizializza il plugin
function bricoware_stackable_shipping_init() {
    return Bricoware_Stackable_Shipping::get_instance();
}

// Avvia il plugin
bricoware_stackable_shipping_init();