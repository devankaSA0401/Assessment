<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Database.php';

try {
    $db = Database::get();

    /*
     * 1. Create database schema
     */
    $schemaFile = __DIR__ . '/../database/schema.sql';

    if (!is_file($schemaFile)) {
        throw new RuntimeException("Schema file not found: {$schemaFile}");
    }

    $schema = file_get_contents($schemaFile);

    if ($schema === false || trim($schema) === '') {
        throw new RuntimeException("Unable to read schema file: {$schemaFile}");
    }

    $db->exec($schema);

    /*
     * 2. Load questionnaire
     */
    $questionnaireFile = __DIR__ . '/../database/questionnaire.json';

    if (!is_file($questionnaireFile)) {
        throw new RuntimeException(
            "Questionnaire file not found: {$questionnaireFile}"
        );
    }

    $questionnaireJson = file_get_contents($questionnaireFile);

    if ($questionnaireJson === false || trim($questionnaireJson) === '') {
        throw new RuntimeException(
            "Unable to read questionnaire file: {$questionnaireFile}"
        );
    }

    $data = json_decode(
        $questionnaireJson,
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    if (!is_array($data)) {
        throw new RuntimeException(
            'Questionnaire JSON must contain an array.'
        );
    }

    /*
     * 3. Start transaction
     */
    $db->beginTransaction();

    /*
     * 4. Insert parameters and controls
     */
    $parameterStatement = $db->prepare(
        '
        INSERT INTO parameters (
            code,
            name,
            weight,
            sort_order
        )
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            weight = VALUES(weight),
            sort_order = VALUES(sort_order)
        '
    );

    $parameterIdStatement = $db->prepare(
        '
        SELECT id
        FROM parameters
        WHERE code = ?
        LIMIT 1
        '
    );

    $controlStatement = $db->prepare(
        '
        INSERT INTO controls (
            code,
            parameter_id,
            question,
            objective,
            evidence,
            critical,
            recommendation,
            sort_order
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            parameter_id = VALUES(parameter_id),
            question = VALUES(question),
            objective = VALUES(objective),
            evidence = VALUES(evidence),
            critical = VALUES(critical),
            recommendation = VALUES(recommendation),
            sort_order = VALUES(sort_order)
        '
    );

    foreach ($data as $i => $section) {
        if (!is_array($section)) {
            throw new RuntimeException(
                "Invalid questionnaire section at index {$i}."
            );
        }

        $code = 'P' . str_pad(
            (string) ($i + 1),
            2,
            '0',
            STR_PAD_LEFT
        );

        $name = $section['name'] ?? null;
        $weight = $section['weight'] ?? null;
        $controls = $section['controls'] ?? null;

        if ($name === null) {
            throw new RuntimeException(
                "Missing parameter name for {$code}."
            );
        }

        if ($weight === null || !is_numeric($weight)) {
            throw new RuntimeException(
                "Invalid weight for {$code}."
            );
        }

        if (!is_array($controls)) {
            throw new RuntimeException(
                "Controls must be an array for {$code}."
            );
        }

        /*
         * Parameter
         */
        $parameterStatement->execute([
            $code,
            (string) $name,
            (float) $weight,
            $i + 1,
        ]);

        /*
         * Get parameter ID
         */
        $parameterIdStatement->execute([$code]);

        $parameterId = $parameterIdStatement->fetchColumn();

        if ($parameterId === false) {
            throw new RuntimeException(
                "Unable to retrieve parameter ID for {$code}."
            );
        }

        /*
         * Controls
         */
        foreach ($controls as $j => $control) {
            if (!is_array($control)) {
                throw new RuntimeException(
                    "Invalid control at {$code} index {$j}."
                );
            }

            $controlCode = $control['id'] ?? null;

            if ($controlCode === null || $controlCode === '') {
                throw new RuntimeException(
                    "Missing control ID at {$code} index {$j}."
                );
            }

            $question = $control['question'] ?? '';
            $objective = $control['objective'] ?? null;
            $evidence = $control['evidence'] ?? null;
            $critical = !empty($control['critical']) ? 1 : 0;
            $recommendation = $control['recommendation'] ?? null;

            $controlStatement->execute([
                (string) $controlCode,
                (int) $parameterId,
                (string) $question,
                $objective !== null ? (string) $objective : null,
                $evidence !== null ? (string) $evidence : null,
                $critical,
                $recommendation !== null
                    ? (string) $recommendation
                    : null,
                $j + 1,
            ]);
        }
    }

    /*
     * 5. Create default admin user if it does not exist
     */
    $adminEmail = getenv('ADMIN_EMAIL') ?: 'admin@example.com';
    $adminPassword = getenv('ADMIN_PASSWORD') ?: '';

    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException(
            "Invalid ADMIN_EMAIL: {$adminEmail}"
        );
    }

    if ($adminPassword === '') {
        throw new RuntimeException(
            'ADMIN_PASSWORD cannot be empty.'
        );
    }

    /*
     * Check whether admin already exists.
     *
     * We intentionally DO NOT overwrite the password of an existing
     * account when setup.php is executed again.
     */
    $adminCheck = $db->prepare(
        '
        SELECT id
        FROM users
        WHERE email = ?
        LIMIT 1
        '
    );

    $adminCheck->execute([$adminEmail]);

    $adminExists = $adminCheck->fetchColumn();

    if ($adminExists === false) {
        $passwordHash = password_hash(
            $adminPassword,
            PASSWORD_DEFAULT
        );

        if ($passwordHash === false) {
            throw new RuntimeException(
                'Unable to generate admin password hash.'
            );
        }

        $adminInsert = $db->prepare(
            '
            INSERT INTO users (
                name,
                email,
                password,
                role
            )
            VALUES (?, ?, ?, ?)
            '
        );

        $adminInsert->execute([
            'Administrator',
            $adminEmail,
            $passwordHash,
            'admin',
        ]);

        $adminStatus = 'created';
    } else {
        $adminStatus = 'already exists';
    }

    /*
     * 6. Commit transaction
     */
    $db->commit();

    /*
     * 7. Output result
     */
    echo "========================================\n";
    echo "DNET Vendor Assessment Setup\n";
    echo "========================================\n";
    echo "Database schema : OK\n";
    echo "Questionnaire   : OK\n";
    echo "Parameters      : OK\n";
    echo "Controls        : OK\n";
    echo "Admin user      : {$adminStatus}\n";
    echo "Admin email     : {$adminEmail}\n";
    echo "========================================\n";
    echo "Setup completed successfully.\n";
    echo "========================================\n";

    if ($adminStatus === 'created') {
        echo "IMPORTANT: Change the default admin password before production.\n";
    }

    exit(0);

} catch (Throwable $e) {

    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }

    fwrite(
        STDERR,
        "========================================\n"
        . "SETUP FAILED\n"
        . "========================================\n"
        . $e->getMessage()
        . "\n"
    );

    exit(1);
}
