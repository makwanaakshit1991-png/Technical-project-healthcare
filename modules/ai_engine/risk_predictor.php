<?php
// ============================================================
//  modules/ai_engine/risk_predictor.php
//  Simulated Random Forest: diabetes & hypertension risk
// ============================================================

require_once __DIR__ . '/../../config/db.php';

/**
 * Run diabetes risk prediction for a patient.
 * Uses latest vital signs and lab results.
 *
 * @param int $patient_id
 * @return array ['score', 'level', 'features', 'recommendation']
 */
function predict_diabetes_risk(int $patient_id): array {
    global $conn;

    // Fetch latest vitals
    $stmt = $conn->prepare("SELECT blood_glucose, bmi FROM vital_signs WHERE patient_id = ? ORDER BY recorded_at DESC LIMIT 1");
    $stmt->bind_param('i', $patient_id);
    $stmt->execute();
    $vitals = $stmt->get_result()->fetch_assoc() ?? [];
    $stmt->close();

    // Fetch latest HbA1c lab result
    $stmt = $conn->prepare(
        "SELECT result_value FROM lab_results
         WHERE patient_id = ? AND test_name LIKE '%HbA1c%'
         ORDER BY result_date DESC LIMIT 1"
    );
    $stmt->bind_param('i', $patient_id);
    $stmt->execute();
    $lab = $stmt->get_result()->fetch_assoc() ?? [];
    $stmt->close();

    $hba1c  = (float)($lab['result_value']    ?? 5.0);
    $glucose = (float)($vitals['blood_glucose'] ?? 90.0);
    $bmi    = (float)($vitals['bmi']           ?? 22.0);

    // --- Simulated Random Forest scoring ---
    $score = 0.10; // baseline

    // HbA1c contribution (weight 0.40)
    if ($hba1c >= 6.5)       $score += 0.40;
    elseif ($hba1c >= 5.7)   $score += 0.20;

    // Fasting glucose contribution (weight 0.35)
    if ($glucose >= 126)     $score += 0.35;
    elseif ($glucose >= 100) $score += 0.18;

    // BMI contribution (weight 0.15)
    if ($bmi >= 30)          $score += 0.15;
    elseif ($bmi >= 25)      $score += 0.08;

    $score = min(1.0, round($score, 4));

    if ($score >= 0.65)      { $level = 'high';     $rec = 'Intensify glucose monitoring. Consider medication adjustment. Consult endocrinologist. Low-carbohydrate diet recommended.'; }
    elseif ($score >= 0.35)  { $level = 'moderate'; $rec = 'Lifestyle modification advised. Increase physical activity. Re-test HbA1c in 3 months. Reduce refined carbohydrate intake.'; }
    else                     { $level = 'low';      $rec = 'Low diabetes risk. Maintain healthy lifestyle. Annual screening recommended.'; }

    $features = json_encode(['hba1c' => $hba1c, 'fasting_glucose' => $glucose, 'bmi' => $bmi]);

    return compact('score', 'level', 'features', 'rec');
}

/**
 * Run hypertension risk prediction for a patient.
 */
function predict_hypertension_risk(int $patient_id): array {
    global $conn;

    $stmt = $conn->prepare("SELECT mean_arterial_pressure, heart_rate, bmi FROM vital_signs WHERE patient_id = ? ORDER BY recorded_at DESC LIMIT 1");
    $stmt->bind_param('i', $patient_id);
    $stmt->execute();
    $vitals = $stmt->get_result()->fetch_assoc() ?? [];
    $stmt->close();

    $map = (float)($vitals['mean_arterial_pressure'] ?? 80.0);
    $hr  = (float)($vitals['heart_rate']             ?? 72.0);
    $bmi = (float)($vitals['bmi']                    ?? 22.0);

    $score = 0.10;

    // MAP contribution (weight 0.50)
    if ($map > 100)          $score += 0.50;
    elseif ($map >= 90)      $score += 0.25;

    // BMI contribution (weight 0.25)
    if ($bmi >= 30)          $score += 0.25;
    elseif ($bmi >= 25)      $score += 0.12;

    // Heart rate contribution (weight 0.15)
    if ($hr > 100)           $score += 0.15;

    $score = min(1.0, round($score, 4));

    if ($score >= 0.65)      { $level = 'high';     $rec = 'Strict BP management required. Review antihypertensive therapy. Daily home BP monitoring. Reduce sodium intake immediately.'; }
    elseif ($score >= 0.35)  { $level = 'moderate'; $rec = 'Regular BP monitoring required. Reduce sodium intake. Increase aerobic exercise. Consider pharmacological evaluation.'; }
    else                     { $level = 'low';      $rec = 'BP within acceptable range. Maintain healthy diet and activity. Annual BP check recommended.'; }

    $features = json_encode(['map' => $map, 'bmi' => $bmi, 'heart_rate' => $hr]);

    return compact('score', 'level', 'features', 'rec');
}

/**
 * Save a prediction to the database.
 */
function save_prediction(int $patient_id, string $model_type, array $result): int {
    global $conn;

    // Check if a recent prediction (<24h) already exists for this model/patient
    $stmt = $conn->prepare(
        "SELECT prediction_id FROM ai_predictions
         WHERE patient_id = ? AND model_type = ?
           AND generated_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
         LIMIT 1"
    );
    $stmt->bind_param('is', $patient_id, $model_type);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) return $existing['prediction_id'];

    $ins = $conn->prepare(
        "INSERT INTO ai_predictions (patient_id, model_type, risk_score, risk_level, feature_summary, recommendation)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $ins->bind_param('isdsss', $patient_id, $model_type, $result['score'], $result['level'], $result['features'], $result['rec']);
    $ins->execute();
    $id = $conn->insert_id;
    $ins->close();
    return $id;
}

/**
 * Get or generate the latest predictions for a patient (used on dashboard).
 */
function get_patient_predictions(int $patient_id): array {
    global $conn;

    $out = [];

    foreach (['diabetes_risk', 'hypertension_risk'] as $model) {
        // Try to get a recent prediction (last 24h)
        $stmt = $conn->prepare(
            "SELECT * FROM ai_predictions
             WHERE patient_id = ? AND model_type = ?
             ORDER BY generated_at DESC LIMIT 1"
        );
        $stmt->bind_param('is', $patient_id, $model);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            // Generate fresh
            $result = ($model === 'diabetes_risk')
                ? predict_diabetes_risk($patient_id)
                : predict_hypertension_risk($patient_id);
            save_prediction($patient_id, $model, $result);

            $stmt = $conn->prepare("SELECT * FROM ai_predictions WHERE patient_id = ? AND model_type = ? ORDER BY generated_at DESC LIMIT 1");
            $stmt->bind_param('is', $patient_id, $model);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }

        if ($row) $out[$model] = $row;
    }

    return $out;
}
