<?php

namespace Automattic\WooCommerce\Pinterest\WooCommerceMultichannelAiIntegration;

use Automattic\WooCommerce\Pinterest\Product\Attributes\AttributeManager;
use Automattic\WooCommerce\Pinterest\Product\Attributes\GoogleCategory;

class AiIntegration {

    public function init() {
        add_filter( 'woocommerce_multichannel_product_ai_request_attributes', array( $this, 'request_attributes' ), 10, 2 );
        add_action( 'woocommerce_multichannel_product_ai_attributes_ready', array( $this, 'attributes_ready' ), 10, 2 );
    }

    public function request_attributes( $attributes ) {
        $attributes[] = 'google_product_category';
        return $attributes;
    }

    public function attributes_ready( $attributes, $product_id ) {

        foreach ( $attributes as $attribute => $value ) {
            if ( $attribute === 'google_product_category' ) {
                $this->update_google_product_category_for_a_product( $product_id, $value );
            }
        }
        return $attributes;
    }

    private function update_google_product_category_for_a_product( $product_id, $new_category_value ) {

        // Instantiate the AttributeManager.
        $attributeManager = AttributeManager::instance();

        // Get the WooCommerce product.
        $product = wc_get_product($product_id);

        // Create a GoogleCategory attribute object with the new category value.
        $googleCategoryAttribute = new GoogleCategory( $new_category_value );

        // Update the google_product_category for the product.
        $attributeManager->update($product, $googleCategoryAttribute);

    }
}

