<?php
// Stripe PHP SDK stub for compatibility
namespace Stripe;

class Stripe {
    public static function setApiKey($key) {
        // Placeholder for Stripe API key
    }
}

class Session {
    public static function create($params) {
        return (object)[
            'url' => 'https://checkout.stripe.com/pay/test_session'
        ];
    }
    
    public static function retrieve($id) {
        return (object)[
            'payment_status' => 'paid'
        ];
    }
}

class Checkout {
    const Session = 'Stripe\\Session';
}

