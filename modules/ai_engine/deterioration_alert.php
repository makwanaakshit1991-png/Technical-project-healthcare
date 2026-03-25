<?php
// ============================================================
//  modules/ai_engine/deterioration_alert.php
//  Simulated LSTM: ICU deterioration scoring
// ============================================================

require_once __DIR__ . '/../../config/db.php';

/**
 * Compute deterioration score for a patient based on last 5 vital signs.
 *
 * Scoring:
 *  SpO2 < 92      → +3 pts
 *  SpO2 92-95     → +1 pt
 *  HR > 120 or < 50 → +2 pts
 *  RR > 24        → +2 pts
 *  Temp > 38.5 or < 36 → +1 pt
 *  MAP < 65       → +3 pts
 *
 * Total 0-3: low | 4-6: moderate | 7+: high
 */
function compute_deterioration_score(int $patient_id): array {
    global $conn;

    $stmt = $conn->prepare(
        "SELECT heart_rate, respiratory_rate, spo2, mean_arterial_pressure, temperature
         FROM vital_signs WHERE patient_id = ?
         ORDER BY recorded_at DESC LIMIT 5"
    );
    $stmt->bind_param('i', $patient_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($rows)) {
        return [
            'score'    => 0.10,
            'level'    => 'low',
            'features' => json_encode(['message' => 'No vital signs recorded']),
            'rec'      => 'No vital signs data available. Record vitals to generate deterioration assessment.',
        ];
    }

    $total_points = 0;
    $avg = [];

    foreach (['heart_rate','respiratory_rate','spo2','mean_arterial_pressure','temperature'] as $field) {
        $values = array_filter(array_column($rows, $field), fn($v) => $v !== null);
        $avg[$field] = count($values) ? array_sum($values) / count($values) : 0;
    }

    // SpO2
    if ($avg['spo2'] > 0 && $avg['spo2'] < 92)       $total_points += 3;
    elseif ($avg['spo2'] >= 92 && $avg['spo2'] <= 95) $total_points += 1;

    // Heart rate
    if ($avg['heart_rate'] > 120 || ($avg['heart_rate'] > 0 && $avg['heart_rate'] < 50)) $total_points += 2;

    // Respiratory rate
    if ($avg['respiratory_rate'] > 24) $total_points += 2;

    // Temperature
    if ($avg['temperature'] > 38.5 || ($avg['temperature'] > 0 && $avg['temperature'] < 36)) $total_points += 1;

    // MAP
    if ($avg['mean_arterial_pressure'] > 0 && $avg['mean_arterial_pressure'] < 65) $total_points += 3;

    // Map points to 0-1 score (max theoretical = 11 points)
    $score = round(min(1.0, $total_points / 11), 4);

    if ($total_points >= 7)     { $level = 'high';     $rec = 'IMMEDIATE CLINICAL REVIEW REQUIRED. Patient shows signs of rapid deterioration. Consider ICU escalation and urgent intervention.'; }
    elseif ($total_points >= 4) { $level = 'moderate'; $rec = 'Increased monitoring frequency recommended. Reassess vitals every 2 hours. Alert attending physician.'; }
    else                        { $level = 'low';      $rec = 'Patient vitals within acceptable range. Continue routine monitoring per care plan.'; }

    $features = json_encode([
        'avg_spo2'  => round($avg['spo2'], 1),
        'avg_hr'    => round($avg['heart_rate'], 0),
        'avg_rr'    => round($avg['respiratory_rate'], 0),
        'avg_map'   => round($avg['mean_arterial_pressure'], 1),
        'avg_temp'  => round($avg['temperature'], 1),
        'points'    => $total_points,
        'readings'  => count($rows),
    ]);

    return compact('score', 'level', 'features', 'rec');
}

/**
 * Get or compute deterioration alert for a patient.
 */
function get_deterioration_alert(int $patient_id): ?array {
    global $conn;

    // Try recent result (last 6h)
    $stmt = $conn->prepare(
        "SELECT * FROM ai_predictions
         WHERE patient_id = ? AND model_type = 'icu_deterioration'
           AND generated_at > DATE_SUB(NOW(), INTERVAL 6 HOUR)
         ORDER BY generated_at DESC LIMIT 1"
    );
    $stmt->bind_param('i', $patient_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) return $row;

    // Compute and save
    $result = compute_deterioration_score($patient_id);

    $ins = $conn->prepare(
        "INSERT INTO ai_predictions (patient_id, model_type, risk_score, risk_level, feature_summary, recommendation)
         VALUES (?, 'icu_deterioration', ?, ?, ?, ?)"
    );
    $ins->bind_param('idsss', $patient_id, $result['score'], $result['level'], $result['features'], $result['rec']);
    $ins->execute();
    $ins->close();

    // High risk → create system log alert
    if ($result['level'] === 'high') {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $msg = "HIGH DETERIORATION RISK: Patient ID $patient_id — score {$result['score']}";
        $sys = $conn->prepare("INSERT INTO system_logs (user_id, action, module, status, ip_address) VALUES (?, ?, 'ai_engine', 'warning', ?)");
        $sys->bind_param('iss', $patient_id, $msg, $ip);
        $sys->execute();
        $sys->close();
    }

    $stmt = $conn->prepare("SELECT * FROM ai_predictions WHERE patient_id = ? AND model_type = 'icu_deterioration' ORDER BY generated_at DESC LIMIT 1");
    $stmt->bind_param('i', $patient_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Get all high-risk patients for the clinician dashboard alert panel.
 */
function get_high_risk_patients(): array {
    global $conn;

    $result = $conn->query(
        "SELECT DISTINCT p.patient_id, u.full_name, ai.risk_score, ai.risk_level, ai.generated_at
         FROM ai_predictions ai
         JOIN patients p ON ai.patient_id = p.patient_id
         JOIN users u ON p.user_id = u.user_id
         WHERE ai.model_type = 'icu_deterioration'
           AND ai.risk_level = 'high'
           AND ai.generated_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
         ORDER BY ai.risk_score DESC"
    );

    $patients = [];
    while ($r = $result->fetch_assoc()) $patients[] = $r;
    return $patients;
}
