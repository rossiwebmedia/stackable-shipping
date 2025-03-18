/**
 * Bricoware Stackable Shipping - Admin JavaScript
 */

(function($) {
    'use strict';
    
    // Inizializza quando il documento è pronto
    $(document).ready(function() {
        // Gestione delle tariffe di spedizione
        initShippingRates();
        
        // Gestione delle opzioni dei prodotti impilabili
        initStackableProducts();
        
        // Gestione delle opzioni dei corrieri
        initCarrierOptions();
    });
    
    /**
     * Inizializza la gestione delle tariffe di spedizione
     */
    function initShippingRates() {
        // Aggiungi tariffa
        $('.bricoware-rates-editor').on('click', '.add-rate', function(e) {
            e.preventDefault();
            var carrierId = $(this).data('carrier-id');
            var rowIndex = $('.bricoware-carrier-rates[data-carrier-id="' + carrierId + '"] tbody tr').length;
            
            $.ajax({
                url: bricoware_shipping.ajax_url,
                type: 'POST',
                data: {
                    action: 'bricoware_add_rate_row',
                    nonce: bricoware_shipping.nonce,
                    carrier_id: carrierId,
                    row_index: rowIndex
                },
                success: function(response) {
                    if (response.success) {
                        $('.bricoware-carrier-rates[data-carrier-id="' + carrierId + '"] tbody').append(response.data.html);
                    }
                }
            });
        });
        
        // Elimina tariffa
        $('.bricoware-rates-editor').on('click', '.delete-rate', function(e) {
            e.preventDefault();
            var row = $(this).closest('tr');
            var table = row.closest('table');
            
            // Verifica che ci sia almeno una riga
            if (table.find('tbody tr').length > 1) {
                if (confirm(bricoware_shipping.i18n.confirm_delete)) {
                    row.remove();
                }
            } else {
                alert(bricoware_shipping.i18n.min_one_rate);
            }
        });
        
        // Validazione campi
        $('.bricoware-rates-editor').on('change', 'input[type="number"]', function() {
            validateRateFields($(this).closest('tr'));
        });
    }
    
    /**
     * Valida i campi di una riga tariffa
     */
    function validateRateFields(row) {
        var weightFrom = parseFloat(row.find('input[name$="[weight_from]"]').val());
        var weightTo = parseFloat(row.find('input[name$="[weight_to]"]').val());
        
        if (weightFrom >= weightTo) {
            row.find('input').addClass('error');
            return false;
        } else {
            row.find('input').removeClass('error');
            return true;
        }
    }
    
    /**
     * Inizializza la gestione delle opzioni dei prodotti impilabili
     */
    function initStackableProducts() {
        // Mostra/nascondi opzioni impilabili
        var isStackableCheckbox = $('#_is_stackable');
        
        if (isStackableCheckbox.length) {
            function toggleStackableOptions() {
                if (isStackableCheckbox.is(':checked')) {
                    $('.stackable-options').show();
                } else {
                    $('.stackable-options').hide();
                }
            }
            
            // Inizializza
            toggleStackableOptions();
            
            // Evento change
            isStackableCheckbox.change(function() {
                toggleStackableOptions();
            });
        }
    }
    
    /**
     * Inizializza la gestione delle opzioni dei corrieri
     */
    function initCarrierOptions() {
        // Mostra/nascondi tariffe in base allo stato del corriere
        $('[id^="woocommerce_bricoware_volumetric_carrier_"][id$="_enabled"]').each(function() {
            var checkbox = $(this);
var carrierId = checkbox.attr('id').match(/carrier_([a-zA-Z0-9_]+)_enabled/)[1];            
            function toggleCarrierRates() {
                if (checkbox.is(':checked')) {
                    $('.bricoware-carrier-rates[data-carrier-id="' + carrierId + '"]').show();
                } else {
                    $('.bricoware-carrier-rates[data-carrier-id="' + carrierId + '"]').hide();
                }
            }
            
            // Inizializza
            toggleCarrierRates();
            
            // Evento change
            checkbox.change(function() {
                toggleCarrierRates();
            });
        });
        
        // Sortable per le righe delle tariffe
        if ($.fn.sortable) {
            $('.bricoware-rates-table tbody').sortable({
                items: 'tr',
                cursor: 'move',
                axis: 'y',
                handle: 'td:first',
                scrollSensitivity: 40,
                helper: function(e, ui) {
                    ui.children().each(function() {
                        $(this).width($(this).width());
                    });
                    return ui;
                },
                start: function(event, ui) {
                    ui.item.css('background-color', '#f9f9f9');
                },
                stop: function(event, ui) {
                    ui.item.removeAttr('style');
                }
            });
        }
    }
})(jQuery);