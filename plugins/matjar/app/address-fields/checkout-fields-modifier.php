class Matjar_Hide_City_Fields {

public function __construct() {

add_filter(
'woocommerce_billing_fields',
array( $this, 'hide_billing_city_for_egypt' )
);

add_filter(
'woocommerce_shipping_fields',
array( $this, 'hide_shipping_city_for_egypt' )
);
}

public function hide_billing_city_for_egypt( $fields ) {

$country = WC()->customer
? WC()->customer->get_billing_country()
: '';

if ( $country === 'EG' ) {

$fields['billing_city']['class'][] = 'hidden';

// unset( $fields['billing_city'] );
}

return $fields;
}

public function hide_shipping_city_for_egypt( $fields ) {

$country = WC()->customer
? WC()->customer->get_shipping_country()
: '';

if ( $country === 'EG' ) {

$fields['shipping_city']['class'][] = 'hidden';

// unset( $fields['shipping_city'] );
}

return $fields;
}
}

new Matjar_Hide_City_Fields();