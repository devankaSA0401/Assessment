<?php

function new_assessment(): void
{
    $title = 'New Assessment';

    ob_start();
    require __DIR__ . '/../views/assessments/new.php';
    $content = ob_get_clean();

    require __DIR__ . '/../views/layout.php';
}


function create_assessment(): void
{
    verify_csrf();

    $db = Database::get();

    $vendorName = trim($_POST['vendor_name'] ?? '');
    $assessorName = trim($_POST['assessor_name'] ?? '');
    $assessmentDate = $_POST['assessment_date'] ?? date('Y-m-d');
    $reference = trim($_POST['reference'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($vendorName === '' || $assessorName === '' || $assessmentDate === '') {
        flash('error', 'Vendor name, assessor name, dan assessment date wajib diisi.');
        redirect('assessments/new');
    }

    $stmt = $db->prepare(
        'INSERT INTO assessments
        (vendor_name, assessor_name, assessment_date, reference, notes, status, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    $stmt->execute([
        $vendorName,
        $assessorName,
        $assessmentDate,
        $reference,
        $notes,
        'In Progress',
        current_user()['id']
    ]);

    $id = (int) $db->lastInsertId();

    flash('success', 'Assessment berhasil dibuat.');

    redirect('assessments/' . $id);
}


function assessment_data(int $id): array
{
    $db = Database::get();

    /*
     * Get assessment
     */
    $st = $db->prepare(
        'SELECT *
         FROM assessments
         WHERE id = ?'
    );

    $st->execute([$id]);

    $assessment = $st->fetch();

    if (!$assessment) {
        http_response_code(404);
        exit('Assessment not found');
    }


    /*
     * Get parameters
     */
    $parameters = $db->query(
        'SELECT *
         FROM parameters
         ORDER BY sort_order'
    )->fetchAll();


    /*
     * Get controls for each parameter.
     *
     * IMPORTANT:
     * controls primary key is "code", not "id".
     */
    foreach ($parameters as &$parameter) {

        $q = $db->prepare(
            'SELECT *
             FROM controls
             WHERE parameter_id = ?
             ORDER BY sort_order'
        );

        $q->execute([
            $parameter['id']
        ]);

        $parameter['controls'] = $q->fetchAll();
    }

    unset($parameter);


    /*
     * Get saved answers
     */
    $q = $db->prepare(
        'SELECT *
         FROM assessment_answers
         WHERE assessment_id = ?'
    );

    $q->execute([$id]);

    $answers = [];

    foreach ($q->fetchAll() as $answer) {
        $answers[$answer['control_id']] = $answer;
    }


    return [
        $assessment,
        $parameters,
        $answers
    ];
}


function calculate_score(array $parameters, array $answers): array
{
    $counts = [
        'Compliant' => 0,
        'Partially Compliant' => 0,
        'Non-Compliant' => 0,
        'N/A' => 0
    ];

    $final = 0.0;
    $critical = 0;
    $answered = 0;

    /*
     * Total parameter weight
     */
    $totalWeight = 0.0;

    foreach ($parameters as $parameter) {
        $totalWeight += (float) ($parameter['weight'] ?? 0);
    }


    $parameterScores = [];


    /*
     * Calculate score per parameter
     */
    foreach ($parameters as $parameter) {

        $sum = 0.0;
        $applicable = 0;

        foreach ($parameter['controls'] as $control) {

            /*
             * controls uses "code" as primary key.
             */
            $controlId = $control['code'];

            $status = $answers[$controlId]['status'] ?? null;

            if ($status === null || $status === '') {
                continue;
            }

            /*
             * Only count known statuses.
             */
            if (!array_key_exists($status, $counts)) {
                continue;
            }

            $counts[$status]++;

            /*
             * N/A does not participate in score.
             */
            if ($status !== 'N/A') {

                $applicable++;
                $answered++;

                $statusScore = status_score($status);

                if ($statusScore !== null) {
                    $sum += $statusScore;
                }
            }


            /*
             * Critical finding
             */
            if (
                !empty($control['critical'])
                && in_array(
                    $status,
                    ['Partially Compliant', 'Non-Compliant'],
                    true
                )
            ) {
                $critical++;
            }
        }


        /*
         * Parameter score
         */
        $score = $applicable > 0
            ? ($sum / $applicable)
            : 0.0;


        /*
         * Weighted score
         */
        $weight = (float) ($parameter['weight'] ?? 0);

        $weighted = $totalWeight > 0
            ? $score * ($weight / $totalWeight)
            : 0.0;


        $final += $weighted;


        $parameterScores[$parameter['id']] = [
            'score' => $score,
            'weighted' => $weighted,
            'applicable' => $applicable,
            'weight' => $weight
        ];
    }


    return [
        'final' => $final,
        'answered' => $answered,
        'counts' => $counts,
        'critical_findings' => $critical,
        'parameters' => $parameterScores
    ];
}


function show_assessment(int $id): void
{
    [
        $assessment,
        $parameters,
        $answers
    ] = assessment_data($id);


    $score = calculate_score(
        $parameters,
        $answers
    );


    $title = $assessment['vendor_name'];

    ob_start();

    require __DIR__ . '/../views/assessments/show.php';

    $content = ob_get_clean();

    require __DIR__ . '/../views/layout.php';
}


function save_assessment(int $id): void
{
    verify_csrf();


    [
        $assessment,
        $parameters,
        $answers
    ] = assessment_data($id);


    /*
     * controls primary key = code
     */
    $controlId = trim($_POST['control_id'] ?? '');

    if ($controlId === '') {
        http_response_code(422);
        exit('Control ID is required');
    }


    /*
     * Find requested control
     */
    $control = null;

    foreach ($parameters as $parameter) {

        foreach ($parameter['controls'] as $candidate) {

            if ((string) $candidate['code'] === $controlId) {
                $control = $candidate;
                break 2;
            }
        }
    }


    if (!$control) {
        http_response_code(422);
        exit('Invalid control');
    }


    /*
     * Validate status
     */
    $status = $_POST['status'] ?? '';

    $allowedStatuses = [
        'Compliant',
        'Partially Compliant',
        'Non-Compliant',
        'N/A'
    ];

    if (!in_array($status, $allowedStatuses, true)) {

        flash(
            'error',
            'Status tidak valid.'
        );

        redirect(
            'assessments/' . $id . '#control-' . urlencode($controlId)
        );
    }


    /*
     * Evidence configuration
     */
    $config = require __DIR__ . '/../../config/config.php';


    $evidencePath =
        $answers[$controlId]['evidence_path'] ?? null;

    $evidenceName =
        $answers[$controlId]['evidence_original_name'] ?? null;


    /*
     * Upload evidence
     */
    if (
        !empty($_FILES['evidence']['name'])
        && $_FILES['evidence']['error'] === UPLOAD_ERR_OK
    ) {

        $allowedExtensions = [
            'pdf',
            'png',
            'jpg',
            'jpeg',
            'doc',
            'docx',
            'xls',
            'xlsx',
            'txt'
        ];


        $extension = strtolower(
            pathinfo(
                $_FILES['evidence']['name'],
                PATHINFO_EXTENSION
            )
        );


        if (
            !in_array(
                $extension,
                $allowedExtensions,
                true
            )
            || $_FILES['evidence']['size']
                > ($config['max_upload_mb'] * 1024 * 1024)
        ) {

            flash(
                'error',
                'Evidence harus berupa file yang diizinkan dan maksimal 10 MB.'
            );

            redirect(
                'assessments/' . $id . '#control-' . urlencode($controlId)
            );
        }


        $name =
            bin2hex(random_bytes(12))
            . '.'
            . $extension;


        $uploadDir = $config['upload_dir'];


        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }


        $destination =
            $uploadDir . '/' . $name;


        if (!move_uploaded_file(
            $_FILES['evidence']['tmp_name'],
            $destination
        )) {

            flash(
                'error',
                'Gagal menyimpan evidence.'
            );

            redirect(
                'assessments/' . $id . '#control-' . urlencode($controlId)
            );
        }


        $evidencePath = $name;

        $evidenceName =
            $_FILES['evidence']['name'];
    }


    /*
     * Save answer
     *
     * IMPORTANT:
     * MariaDB uses ON DUPLICATE KEY UPDATE,
     * NOT PostgreSQL ON CONFLICT.
     */
    $db = Database::get();


    /*
     * Check if answer already exists.
     */
    $check = $db->prepare(
        'SELECT id
         FROM assessment_answers
         WHERE assessment_id = ?
           AND control_id = ?
         LIMIT 1'
    );

    $check->execute([
        $id,
        $controlId
    ]);


    $existingAnswer = $check->fetch();


    if ($existingAnswer) {

        $stmt = $db->prepare(
            'UPDATE assessment_answers
             SET status = ?,
                 evidence_path = COALESCE(?, evidence_path),
                 evidence_original_name = COALESCE(?, evidence_original_name),
                 finding = ?,
                 recommendation = ?,
                 updated_by = ?,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );


        $stmt->execute([
            $status,
            $evidencePath,
            $evidenceName,
            trim($_POST['finding'] ?? ''),
            trim($_POST['recommendation'] ?? ''),
            current_user()['id'],
            $existingAnswer['id']
        ]);

    } else {

        $stmt = $db->prepare(
            'INSERT INTO assessment_answers
             (
                assessment_id,
                control_id,
                status,
                evidence_path,
                evidence_original_name,
                finding,
                recommendation,
                updated_by
             )
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );


        $stmt->execute([
            $id,
            $controlId,
            $status,
            $evidencePath,
            $evidenceName,
            trim($_POST['finding'] ?? ''),
            trim($_POST['recommendation'] ?? ''),
            current_user()['id']
        ]);
    }


    /*
     * Audit log
     */
    $audit = $db->prepare(
        'INSERT INTO audit_logs
        (
            assessment_id,
            user_id,
            action,
            entity_type,
            entity_id,
            details
        )
        VALUES (?, ?, ?, ?, ?, ?)'
    );


    $audit->execute([
        $id,
        current_user()['id'],
        'answer_saved',
        'control',
        $controlId,
        json_encode([
            'status' => $status
        ])
    ]);


    /*
     * Determine assessment completion.
     *
     * Only controls belonging to the assessment schema
     * are counted.
     */
    $totalControls = (int) $db->query(
        'SELECT COUNT(*)
         FROM controls'
    )->fetchColumn();


    $stmt = $db->prepare(
        'SELECT COUNT(*)
         FROM assessment_answers
         WHERE assessment_id = ?
           AND status IS NOT NULL
           AND status <> ""'
    );


    $stmt->execute([$id]);


    $answeredNow = (int) $stmt->fetchColumn();


    $newStatus =
        ($totalControls > 0 && $answeredNow >= $totalControls)
            ? 'Completed'
            : 'In Progress';


    $stmt = $db->prepare(
        'UPDATE assessments
         SET status = ?,
             updated_at = CURRENT_TIMESTAMP
         WHERE id = ?'
    );


    $stmt->execute([
        $newStatus,
        $id
    ]);


    /*
     * AJAX response
     */
    if (
        isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH'])
            === 'xmlhttprequest'
    ) {

        header('Content-Type: application/json');

        echo json_encode([
            'ok' => true,
            'message' => 'Saved',
            'control_id' => $controlId,
            'status' => $status
        ]);

        return;
    }


    flash(
        'success',
        $controlId . ' tersimpan.'
    );


    redirect(
        'assessments/' . $id . '#control-' . urlencode($controlId)
    );
}


function show_report(int $id): void
{
    [
        $assessment,
        $parameters,
        $answers
    ] = assessment_data($id);


    $score = calculate_score(
        $parameters,
        $answers
    );


    $rating =
        $score['final'] >= 90
            ? 'Excellent'
            : (
                $score['final'] >= 75
                    ? 'Good'
                    : (
                        $score['final'] >= 60
                            ? 'Needs Improvement'
                            : 'High Risk'
                    )
            );


    $db = Database::get();


    /*
     * Critical findings
     */
    $q = $db->prepare(
        'SELECT
            c.code AS control_code,
            c.question,
            c.recommendation AS baseline,
            aa.finding,
            aa.recommendation
         FROM assessment_answers aa
         JOIN controls c
           ON c.code = aa.control_id
         WHERE aa.assessment_id = ?
           AND c.critical = 1
           AND aa.status IN (
                "Partially Compliant",
                "Non-Compliant"
           )
         ORDER BY c.sort_order'
    );


    $q->execute([$id]);


    $findings = $q->fetchAll();


    /*
     * Recommendations
     */
    $q = $db->prepare(
        'SELECT
            c.code AS control_code,
            COALESCE(
                NULLIF(aa.recommendation, ""),
                c.recommendation
            ) AS recommendation
         FROM assessment_answers aa
         JOIN controls c
           ON c.code = aa.control_id
         WHERE aa.assessment_id = ?
           AND aa.status IN (
                "Partially Compliant",
                "Non-Compliant"
           )
         ORDER BY c.sort_order'
    );


    $q->execute([$id]);


    $recommendations = $q->fetchAll();


    require __DIR__ . '/../views/report/show.php';
}
