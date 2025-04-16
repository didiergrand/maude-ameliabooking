// Désactiver les mises à jour d'Amelia Booking
add_filter('site_transient_update_plugins', 'disable_amelia_updates');
function disable_amelia_updates($value) {
    if (isset($value->response['ameliabooking/ameliabooking.php'])) {
        unset($value->response['ameliabooking/ameliabooking.php']);
    }
    return $value;
} 