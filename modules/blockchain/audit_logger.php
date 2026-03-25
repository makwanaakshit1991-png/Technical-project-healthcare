<?php
// ============================================================
//  modules/blockchain/audit_logger.php
//  SHA-256 hash-chained audit trail
// ============================================================

if (!function_exists('log_transaction')) {

/**
 * Append a new block to the blockchain_audit_log.
 *
 * @param string   $type       Transaction type (enum value)
 * @param int      $actor_id   user_id performing the action
 * @param int|null $record_id  Affected record ID (if applicable)
 * @param array    $payload    Data to hash as resource_hash
 * @param string   $ip         IP address of actor
 */
function log_transaction(string $type, int $actor_id, ?int $record_id, array $payload, string $ip = ''): bool {
    global $conn;

    if (!$conn) return false;

    // 1. Fetch last block_hash to form chain link
    $res = $conn->query("SELECT block_hash FROM blockchain_audit_log ORDER BY log_id DESC LIMIT 1");
    $previous_hash = ($res && $res->num_rows > 0)
        ? $res->fetch_assoc()['block_hash']
        : '0000000000000000000000000000000000000000000000000000000000000000';

    // 2. Hash the payload
    $resource_hash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE));

    // 3. Compute block hash
    $block_hash = hash('sha256', $previous_hash . $resource_hash . time());

    // 4. Insert
    $details = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $stmt = $conn->prepare(
        "INSERT INTO blockchain_audit_log
         (transaction_type, actor_user_id, affected_record_id, resource_hash, previous_hash, block_hash, ip_address, details)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) return false;

    $stmt->bind_param('siisssss', $type, $actor_id, $record_id, $resource_hash, $previous_hash, $block_hash, $ip, $details);
    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

} // end if !function_exists
