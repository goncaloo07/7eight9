<?php
function decryptPaymentData($payment_id) {
    $connection = db_connect();
    $encryption_key = getenv('goncalo');
    
    $sql = "SELECT NUMERO_CARTAO, CVV FROM PAP_INFO_PAGAMENTO WHERE ID = ?";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("i", $payment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Descriptografar número do cartão
        $data = base64_decode($row['NUMERO_CARTAO']);
        $iv = substr($data, 0, openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = substr($data, openssl_cipher_iv_length('aes-256-cbc'));
        $cardNumber = openssl_decrypt($encrypted, 'aes-256-cbc', $encryption_key, 0, $iv);
        
        // Descriptografar CVV
        $data = base64_decode($row['CVV']);
        $iv = substr($data, 0, openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = substr($data, openssl_cipher_iv_length('aes-256-cbc'));
        $cvv = openssl_decrypt($encrypted, 'aes-256-cbc', $encryption_key, 0, $iv);
        
        return [
            'cardNumber' => $cardNumber,
            'cvv' => $cvv
        ];
    }
    
    return null;
}