<?php

require_once __DIR__ . '/AssessmentController.php';

$db = Database::get();

$assessments = $db
    ->query('SELECT * FROM assessments ORDER BY id DESC')
    ->fetchAll();

foreach ($assessments as &$a) {
    [$dummy, $params, $answers] = assessment_data((int) $a['id']);

    $s = calculate_score($params, $answers);

    $totalControls = 0;
    foreach ($params as $p) {
        $totalControls += count($p['controls']);
    }

    $a['final_score'] = $s['final'];
    $a['answered'] = $s['answered'];
    $a['total_controls'] = $totalControls;
    $a['progress'] = $totalControls > 0
        ? round($s['answered'] * 100 / $totalControls, 1)
        : 0;
}

unset($a);

$title = 'Dashboard';

ob_start();

require __DIR__ . '/../views/dashboard/index.php';

$content = ob_get_clean();

require __DIR__ . '/../views/layout.php';
