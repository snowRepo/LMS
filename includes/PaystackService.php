<?php
/**
 * Paystack Service Class
 * Handles Paystack payment integration for subscriptions
 */

class PaystackService {
    private $publicKey;
    private $secretKey;
    private $baseUrl;
    
    public function __construct() {
        $this->publicKey = PAYSTACK_PUBLIC_KEY;
        $this->secretKey = PAYSTACK_SECRET_KEY;
        $this->baseUrl = 'https://api.paystack.co';
    }
    
    /**
     * Initialize a payment transaction
     */
    public function initializePayment($email, $amount, $plan, $libraryId, $callbackUrl = null) {
        $data = [
            'email' => $email,
            'amount' => $amount * 100, // Convert to pesewas
            'currency' => 'GHS',
            'reference' => $this->generateReference(),
            'callback_url' => $callbackUrl ?: APP_URL . '/payment-callback.php',
            'metadata' => [
                'plan' => $plan,
                'library_id' => $libraryId,
                'custom_fields' => [
                    [
                        'display_name' => 'Plan',
                        'variable_name' => 'plan',
                        'value' => ucfirst($plan)
                    ],
                    [
                        'display_name' => 'Library ID',
                        'variable_name' => 'library_id',
                        'value' => $libraryId
                    ]
                ]
            ]
        ];
        
        return $this->makeRequest('POST', '/transaction/initialize', $data);
    }
    
    /**
     * Verify a payment transaction
     */
    public function verifyPayment($reference) {
        return $this->makeRequest('GET', '/transaction/verify/' . $reference);
    }
    
    /**
     * Create a subscription plan
     */
    public function createPlan($name, $amount, $interval = 'monthly') {
        $data = [
            'name' => $name,
            'amount' => $amount * 100, // Convert to pesewas
            'interval' => $interval,
            'currency' => 'GHS'
        ];
        
        return $this->makeRequest('POST', '/plan', $data);
    }
    
    /**
     * Create a subscription
     */
    public function createSubscription($customerCode, $planCode, $authorization) {
        $data = [
            'customer' => $customerCode,
            'plan' => $planCode,
            'authorization' => $authorization
        ];
        
        return $this->makeRequest('POST', '/subscription', $data);
    }
    
    /**
     * Create or update customer
     */
    public function createCustomer($email, $firstName, $lastName, $phone = null) {
        $data = [
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName
        ];
        
        if ($phone) {
            $data['phone'] = $phone;
        }
        
        return $this->makeRequest('POST', '/customer', $data);
    }
    
    /**
     * Get subscription details
     */
    public function getSubscription($subscriptionCode) {
        return $this->makeRequest('GET', '/subscription/' . $subscriptionCode);
    }
    
    /**
     * Cancel subscription
     */
    public function cancelSubscription($subscriptionCode, $token) {
        $data = ['token' => $token];
        return $this->makeRequest('POST', '/subscription/disable', $data);
    }
    
    /**
     * Generate unique reference
     */
    private function generateReference() {
        return 'LMS_' . time() . '_' . rand(1000, 9999);
    }
    
    /**
     * Make HTTP request to Paystack API
     */
    private function makeRequest($method, $endpoint, $data = []) {
        $url = $this->baseUrl . $endpoint;
        
        $curl = curl_init();
        
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->secretKey,
                'Content-Type: application/json',
                'Cache-Control: no-cache',
            ],
        ];
        
        if ($method === 'POST' && !empty($data)) {
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }
        
        curl_setopt_array($curl, $options);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        
        curl_close($curl);
        
        if ($error) {
            throw new Exception('CURL Error: ' . $error);
        }
        
        $decodedResponse = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $message = isset($decodedResponse['message']) ? 
                $decodedResponse['message'] : 'HTTP Error ' . $httpCode;
            throw new Exception('Paystack API Error: ' . $message);
        }
        
        return $decodedResponse;
    }
    
    /**
     * Get plan details by name
     */
    public static function getPlanDetails($planName) {
        $plans = [
            'basic' => [
                'name' => 'Basic Plan',
                'price' => BASIC_PLAN_PRICE / 100, // Convert from pesewas to cedis
                'book_limit' => BASIC_PLAN_BOOK_LIMIT,
                'features' => [
                    'Up to ' . number_format(BASIC_PLAN_BOOK_LIMIT) . ' books',
                    'Up to 100 members',
                    'Basic reporting',
                    'Email support',
                    'Mobile responsive'
                ]
            ],
            'standard' => [
                'name' => 'Standard Plan',
                'price' => STANDARD_PLAN_PRICE / 100,
                'book_limit' => STANDARD_PLAN_BOOK_LIMIT,
                'features' => [
                    'Up to ' . number_format(STANDARD_PLAN_BOOK_LIMIT) . ' books',
                    'Up to 500 members',
                    'Advanced reporting',
                    'Priority support',
                    'API access',
                    'Data export'
                ]
            ],
            'premium' => [
                'name' => 'Premium Plan',
                'price' => PREMIUM_PLAN_PRICE / 100,
                'book_limit' => PREMIUM_PLAN_BOOK_LIMIT,
                'features' => [
                    'Unlimited books',
                    'Unlimited members',
                    'Advanced analytics',
                    '24/7 phone support',
                    'Custom integrations',
                    'White-label option',
                    'Priority feature requests'
                ]
            ]
        ];
        
        return isset($plans[$planName]) ? $plans[$planName] : null;
    }
    
    /**
     * Format price for display
     */
    public static function formatPrice($price) {
        return 'GHS ' . number_format($price, 2);
    }
}
?>