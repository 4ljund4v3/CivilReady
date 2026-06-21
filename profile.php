<?php

session_start();

require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/update_activity.php';
require_once 'includes/hit_counter.php';

$userId = (int)($_GET['id'] ?? 0);

if (!$userId) {

    $userId = $_SESSION['user_id'];
}

$stmt = $pdo->prepare("
    SELECT *
    FROM users
    WHERE id = ?
");

$stmt->execute([$userId]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {

    die("User not found.");
}
$totalPoints = $user['points'];

    $progressStmt = $pdo->prepare("
    SELECT
        up.*,
        c.name
    FROM user_progress up
    JOIN categories c
        ON c.id = up.category_id
    WHERE up.user_id = ?
");

$progressStmt->execute([
    $userId
]);

$progress =
    $progressStmt->fetchAll(
        PDO::FETCH_ASSOC
    );

    $statsStmt = $pdo->prepare("
    SELECT

        COUNT(*) AS levels_completed,

        SUM(score) AS total_correct,

        SUM(total_questions) AS total_questions

    FROM quiz_results

    WHERE user_id = ?
");

$statsStmt->execute([
    $userId
]);

$stats =
    $statsStmt->fetch(
        PDO::FETCH_ASSOC
    );

$accuracy = 0;

if (
    $stats['total_questions'] > 0
) {

    $accuracy =
        round(
            (
                $stats['total_correct']
                /
                $stats['total_questions']
            ) * 100,
            1
        );
}

$activityStmt = $pdo->prepare("
    SELECT *
    FROM feed
    WHERE user_id = ?
    ORDER BY id DESC
    LIMIT 10
");

$activityStmt->execute([
    $userId
]);

$activities =
    $activityStmt->fetchAll(
        PDO::FETCH_ASSOC
    );

    ?>
<!DOCTYPE html>
<html>
<head>

<title>

<?= htmlspecialchars(
    $user['username']
) ?>

- Profile

</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


</head>

<body class="bg-light">

<div class="container py-5">

<div class="row">

<div class="col-lg-4">

<div class="card shadow">

<div class="card-body text-center">

<h2>
<?= htmlspecialchars(
    $user['fullname']
) ?></h2>
<h3
style="
color:
<?= getUserColor(
    $user['rank_title'],
    $user['role']
) ?>
">
<p style="font-size:25px;">(<?= htmlspecialchars(
    $user['username']
) ?>)</p>



</h3>

<h5 class="text-muted">

<?= getRank(
    $totalPoints
) ?>

</h5>

<hr>

<h3>

<?= number_format(
    $totalPoints
) ?>

</h3>

<p>

Total Points

</p>

<p>

Joined

<br>

<?= date(
    'F d, Y',
    strtotime(
        $user['created_at']
    )
) ?>

</p>

</div>

</div>

</div>

<div class="col-lg-8">

<div class="card shadow mb-4">

<div class="card-header">

Statistics

</div>

<div class="card-body">

<div class="row text-center">

<div class="col">

<h4>

<?= $stats['levels_completed'] ?? 0 ?>

</h4>

Levels

</div>

<div class="col">

<h4>

<?= $accuracy ?>%

</h4>

Accuracy

</div>

<div class="col">

<h4>

<?= $stats['total_correct'] ?? 0 ?>

</h4>

Correct

</div>

</div>

</div>

</div>

<div class="card shadow mb-4">

<div class="card-header">

Category Progress

</div>

<div class="card-body">

<?php foreach($progress as $item): ?>

<div class="mb-3">

<strong>

<?= htmlspecialchars(
    $item['name']
) ?>

</strong>

<div>

Level

<?= $item['highest_level'] ?>

/ 50

</div>

<div class="progress">

<div
class="progress-bar"
style="
width:
<?= ($item['highest_level']/50)*100 ?>%;
">

</div>

</div>

</div>

<?php endforeach; ?>

</div>

</div>

<div class="card shadow">

<div class="card-header">

Recent Activity

</div>

<div class="card-body">

<?php foreach($activities as $activity): ?>

<div class="border-bottom pb-2 mb-2">

<?= htmlspecialchars(
    $activity['message']
) ?>

</div>

<?php endforeach; ?>

</div>
<br>


</div>

</div>

</div>
<a
    href="dashboard.php"
    class="btn btn-secondary">

        Back

    </a>
</div>

</body>
</html>