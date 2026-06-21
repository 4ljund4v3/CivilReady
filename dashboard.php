<?php

require_once 'includes/auth_check.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/update_activity.php';
require_once 'includes/hit_counter.php';


$userId = $_SESSION['user_id'];

$userStmt = $pdo->prepare("
    SELECT
        points,
        rank_title,
        username,
        fullname,
        first_login
    FROM users
    WHERE id = ?
");

$userStmt->execute([$userId]);
$userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);
$showWelcome =
    ($userInfo['first_login'] == 1);

if($showWelcome){

    $stmt = $pdo->prepare("
        UPDATE users
        SET first_login = 0
        WHERE id = ?
    ");

    $stmt->execute([
        $userId
    ]);
}
$resultStats = $pdo->prepare("
    SELECT
        COUNT(*) as levels_completed,
        SUM(perfect_score) as perfect_scores
    FROM quiz_results
    WHERE user_id = ?
");

$resultStats->execute([$userId]);

$stats = $resultStats->fetch(PDO::FETCH_ASSOC);

$levelsCompleted =
    $stats['levels_completed'] ?? 0;

$perfectScores =
    $stats['perfect_scores'] ?? 0;

$nextRank = getNextRankInfo(
    $userInfo['points']
);

$currentPoints = $userInfo['points'];

$neededPoints = $nextRank['points_needed'];

if ($neededPoints > 0) {

    $rankProgress =
        min(
            ($currentPoints / $neededPoints) * 100,
            100
        );

} else {

    $rankProgress = 100;
}

$categories = $pdo->prepare("
    SELECT
        categories.id,
        categories.name,
        categories.is_enabled,
        user_progress.current_level
    FROM categories
    JOIN user_progress
        ON categories.id = user_progress.category_id
    WHERE user_progress.user_id = ?
");

$categories->execute([$userId]);

$resumeStmt = $pdo->prepare("
    SELECT
        qs.*,
        c.name AS category_name
    FROM quiz_sessions qs
    JOIN categories c
        ON c.id = qs.category_id
    WHERE qs.user_id = ?
    ORDER BY qs.updated_at DESC
");

$resumeStmt->execute([
    $userId
]);

$resumeQuizzes =
    $resumeStmt->fetchAll(
        PDO::FETCH_ASSOC
    );

$stmt = $pdo->query("
   SELECT
        f.*,
        u.username,
        u.fullname,
        u.rank_title,
        u.role
   FROM feed f
   JOIN users u
        ON u.id = f.user_id
   WHERE u.role != 'admin'
   ORDER BY f.id DESC
   LIMIT 10
");

$feed = $stmt->fetchAll(PDO::FETCH_ASSOC);

$announcements = $pdo->query("
    SELECT *
    FROM announcements
    WHERE is_active = 1
    ORDER BY
        is_pinned DESC,
        created_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$icons = [
    'General' => '📘',
    'Verbal' => '📝',
    'Analytical' => '🧩',
    'Numerical' => '🔢'
];
?>

<?php require_once 'includes/header.php'; ?>
<head>
    <meta charset="UTF-8">
    <style>
        .announcement-box{
    max-height:300px;
    overflow-y:auto;
}
</style>

</head>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow mobile-navbar">

    <div class="container-fluid">

        <a class="navbar-brand fw-bold d-flex flex-column" href="#"
style="line-height:1;">

    <span class="logo-title">

    CivilReady

</span>
 
   <span class="logo-beta">
    BETA
    </span> 

</a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#mainNavbar">

            <span class="navbar-toggler-icon"></span>

        </button>

        <div
            class="collapse navbar-collapse"
            id="mainNavbar">

            <div class="ms-auto d-flex flex-column flex-lg-row align-items-lg-center gap-2 mt-3 mt-lg-0">
                <span class="text-white" style="text-align: center;">
                    <?= number_format($userInfo['points']) ?> Points ● <?= htmlspecialchars($_SESSION['fullname']) ?>
                </span>
                <span class="badge bg-info text-dark">
                    <?= htmlspecialchars($userInfo['rank_title']) ?>
                </span>



                <a
                href="profile.php"
                class="btn btn-info btn-sm">

                    Profile

                </a>

                <a
                href="community.php"
                class="btn btn-info btn-sm">

                    Community

                </a>

                <?php if(
                    isset($_SESSION['role']) &&
                    $_SESSION['role'] === 'admin'
                ): ?>

                <a
                href="admin/dashboard.php"
                class="btn btn-warning btn-sm">

                    Admin

                </a>

                <?php endif; ?>

                <a
                href="auth/logout.php"
                class="btn btn-danger btn-sm" style="margin-bottom:1rem;">

                    Logout

                </a>

            </div>

        </div>

    </div>

</nav>

<div class="container-fluid mt-4">

<div class="row">

<!-- LEFT -->

<div class="col-lg-3 order-3 order-lg-1">

<div class="card">

<div class="card-header" id="feed">
Global Feed
</div>

<div class="card-body feed-box">

<?php foreach($feed as $item): ?>

<div class="p-2 mb-3 border rounded bg-light">

    <?php if(stripos($item['message'], 'perfect') !== false): ?>

        <span class="badge bg-warning text-dark mb-2">
            PERFECT SCORE
        </span>

    <?php endif; ?>

<?php

$message = htmlspecialchars($item['message']);

$link =
    '<a
    href="profile.php?id=' .
    $item['user_id'] .
    '"
    class="fw-bold text-decoration-none"
    style="color:' .
    getUserColor(
        $item['rank_title'],
        $item['role']
    ) .
    ';">' .
    htmlspecialchars(
        $item['fullname']
    ) .
    '</a>';
    $item['user_id'] .
    '" class="fw-bold text-decoration-none">' .
    htmlspecialchars($item['fullname']) .
    '</a>';

echo str_replace(
    htmlspecialchars($item['fullname']),
    $link,
    $message
);

?>



<div class="small text-muted mt-2">

Activity Feed

</div>

</div>

<?php endforeach; ?>

</div>


</div>
<div class="card mt-4" id="shoutbox">

    <div class="card-header">

        Community Shoutbox

    </div>

    <div
        class="card-body"
        id="shoutboxMessages"
        style="
            height:300px;
            overflow-y:auto;
        ">

        Loading...

    </div>

    <div class="card-footer">

        <form id="shoutboxForm">

            <div class="input-group">

                <input
                    type="text"
                    id="shoutboxInput"
                    class="form-control"
                    maxlength="250"
                    placeholder="Say something..."
                    >

<div
id="cooldownMessage"
class="small text-danger mt-2">
</div>
                <button
                    class="btn btn-primary">

                    Send

                </button>

            </div>

        </form>

    </div>

</div>

</div>



<!-- CENTER -->

<div class="col-lg-6 order-1 order-lg-2">
    <div class="card shadow-lg border-0 mb-4 dede">

    <div class="card-body">

     <h3 class="fw-bold" id="overview">
            Welcome Back,
            <?= htmlspecialchars($_SESSION['username']) ?>
        </h3> 

<div class="row g-3 mt-3">

    <div class="col-md-6">

        <div class="card border-0 bg-light">

            <div class="card-body text-center">

                <div class="text-muted">
                    Rank
                </div>

                <h3>
                    <?= htmlspecialchars($userInfo['rank_title']) ?>
                </h3>

            </div>

        </div>

    </div>

    <div class="col-md-6">

        <div class="card border-0 bg-light">

            <div class="card-body text-center">

                <div class="text-muted">
                    Points
                </div>

                <h3>
                    <?= number_format($userInfo['points']) ?>
                </h3>

            </div>

        </div>

    </div>

    <div class="col-md-6">

        <div class="card border-0 bg-light">

            <div class="card-body text-center">

                <div class="text-muted">
                    Levels Cleared
                </div>

                <h3>
                    <?= $levelsCompleted ?>
                </h3>

            </div>

        </div>

    </div>

    <div class="col-md-6">

        <div class="card border-0 bg-light">

            <div class="card-body text-center">

                <div class="text-muted">
                    Perfect Scores
                </div>

                <h3 class="text-warning">
                    <?= $perfectScores ?>
                </h3>

            </div>

        </div>

    </div>

</div>
</div>
</div>

<div class="row">
    <div class="card shadow-lg border-0 mb-4">

    <div class="card-body">

        <h4>

           
           <!-- <?= htmlspecialchars($userInfo['rank_title']) ?> -->

        </h4>

        <p class="mb-2">

            <?= number_format($currentPoints) ?>

            /

            <?= number_format($neededPoints) ?>

            Points

        </p>

        <div class="progress" style="height:20px;">

            <div
                class="progress-bar progress-bar-striped progress-bar-animated"
                style="width: <?= $rankProgress ?>%">
            </div>

        </div>

        <div class="mt-2 text-muted">

            Next Rank:

            <?= htmlspecialchars($nextRank['rank']) ?>

        </div>

    </div>

</div>

<?php if(!empty($resumeQuizzes)): ?>
    <?php foreach(
    $resumeQuizzes
    as $resumeQuiz
): ?>

<div class="card border-warning shadow mb-3">

    <div class="card-body">

  <!--      <h5>

            Resume Quiz

        </h5> -->

        <p class="mb-1">

            <strong>

                <?= htmlspecialchars(
                    $resumeQuiz['category_name']
                ) ?>

            </strong>

        </p>

        <p class="mb-1">

            Level
            <?= $resumeQuiz['level_no'] ?>

        </p>

        <p>

            Question
            <?= $resumeQuiz['question_index'] + 1 ?>

            of

            <?= getQuestionCount(
                $resumeQuiz['level_no']
            ) ?>

        </p>

        <a
        href="quiz/resume.php?category=<?= $resumeQuiz['category_id'] ?>"
        class="btn btn-warning">

            Continue

        </a>

    </div>

</div>
<?php endforeach; ?>
<?php endif; ?>
<?php foreach($categories as $category): ?>

<?php

$isDisabled = !$category['is_enabled'];

?>


<div class="col-md-6 mb-4" id="categories">


    <div class="card border-0 shadow-lg h-100 <?= $isDisabled ? 'bg-secondary text-white' : '' ?>">

        <div class="card-body text-center p-4">

            <div style="font-size:48px;">
                <?= $icons[$category['name']] ?? '📚' ?>
            </div>

            <h4 class="mt-3">
                <?= htmlspecialchars($category['name']) ?>
            </h4>

            <p class="<?= $isDisabled ? 'text-light' : 'text-muted' ?> mb-1">
                Current Level
            </p>

            <h2 class="text-primary fw-bold">
                <?= $category['current_level'] ?>
            </h2>

            <?php
            $progress = min(
                ($category['current_level'] / 50) * 100,
                100
            );
            ?>

            <div class="progress mb-3" style="height:12px;">

                <div
                    class="progress-bar progress-bar-striped progress-bar-animated"
                    style="width: <?= $progress ?>%">
                </div>

            </div>

            <?php if($isDisabled): ?>

<button
    class="btn btn-light w-100"
    disabled>

    Coming Soon

</button>

<?php else: ?>

<a
    href="quiz/levels.php?category=<?= $category['id'] ?>"
    class="btn btn-primary w-100">

    Start Mission

</a>

<?php endif; ?>

        </div>

    </div>

</div>

<?php endforeach; ?>

</div>

</div>

<!-- RIGHT -->

<div class="col-lg-3 order-2 order-lg-3">

<div class="card" id="leaderboard">

<div class="card-header">
Top Players
</div>

<div class="card-body">

<?php
$onlineStmt = $pdo->query("
    SELECT
        id,
        username,
        role,
        rank_title
    FROM users
    WHERE last_activity >=
        DATE_SUB(
            NOW(),
            INTERVAL 5 MINUTE
        )
    ORDER BY username
");

$onlineUsers =
    $onlineStmt->fetchAll(
        PDO::FETCH_ASSOC
    );
$leaders = $pdo->query("
    SELECT
        id,
        fullname,
        rank_title,
        role,
        points
    FROM users
    WHERE role != 'admin'
    ORDER BY points DESC
    LIMIT 10
");
$rankNumber = 1;
foreach($leaders as $leader):
?>



<div class="mb-3 border-bottom pb-2">

<strong>

#<?= $rankNumber ?>

<a
href="profile.php?id=<?= $leader['id'] ?>"
class="fw-bold text-decoration-none"
style="
color:
<?= getUserColor(
    $leader['rank_title'],
    $leader['role']
) ?>
">

<?= htmlspecialchars(
    $leader['fullname']
) ?>

</a>

</strong>

    <br>

    <span class="badge bg-secondary">
        <?= htmlspecialchars($leader['rank_title']) ?>
    </span>

    <div class="small text-muted">
        <?= number_format($leader['points']) ?> Points
    </div>

</div>

<?php $rankNumber++; endforeach; ?>

</div>

</div>
<div class="card shadow mt-4 mb-3" id="online">

<div class="card-header">

Online Users
(
<?= count(
    $onlineUsers
) ?>
)

</div>

<div class="card-body">

<?php if(empty($onlineUsers)): ?>

<div class="text-muted">

Nobody online

</div>

<?php else: ?>

<?php foreach(
    $onlineUsers
    as $online
): ?>

<div class="mb-2">

<span style="color:#28a745;">
●
</span>

<a
href="profile.php?id=<?= $online['id'] ?>"
class="text-decoration-none fw-bold"
style="
color:
<?= getUserColor(
    $online['rank_title'],
    $online['role']
) ?>
">

<?= htmlspecialchars(
    $online['username']
) ?>

</a>

</div>

<?php endforeach; ?>

<?php endif; ?>

</div>

</div>
<div class="row mt-4">

<div class="col-12">

<div class="card shadow mb-4" id="announcements">

<div class="card-header">
Announcements
</div>

<div class="card-body announcement-box">

<?php foreach($announcements as $announcement): ?>

<div class="alert alert-info">

    <h5 class="mb-2">
        <?php if($announcement['is_pinned']): ?>

📌

<?php endif; ?>

<?= htmlspecialchars($announcement['title']) ?>
    </h5>

    <p class="mb-0">
        <?= ($announcement['content']) ?>
    </p>

</div>

<hr>

<?php endforeach; ?>

</div>

</div>

</div>

</div>

</div>
</div>

</div>

<div class="mobile-bottom-nav d-md-none">


    <a href="#overview" id="nav-overview">
        <i class="bi bi-house-fill"></i>
    </a>


<a href="#categories" id="nav-categories">
    <i class="bi bi-pen-fill"></i>
</a>

    <a href="#leaderboard" id="nav-leaderboard">
        <i class="bi bi-trophy-fill"></i>
    </a>



<a href="#online" id="nav-online">
<i class="bi bi-person-fill-check"></i>
</a>
   <a href="#announcements" id="nav-announcements">
        <i class="bi bi-megaphone-fill"></i>
    </a>
    <a href="#feed" id="nav-feed">
        <i class="bi bi-rss-fill"></i>
    </a>


    <a href="#shoutbox" id="nav-shoutbox">

        <i class="bi bi-chat-dots-fill"></i>
    </a>

</div>
<script>

const navMap = {

    overview:
        'nav-overview',

    categories:
        'nav-categories',

    leaderboard:
        'nav-leaderboard',

    online:
        'nav-online',

    announcements:
        'nav-announcements',

    feed:
        'nav-feed',

    shoutbox:
        'nav-shoutbox'

};

function updateActiveNav(){

    let closestSection = null;

    let smallestDistance =
        Infinity;

    Object.keys(navMap)
    .forEach(id => {

        const section =
            document.getElementById(
                id
            );

        if(!section) return;

        const rect =
            section
            .getBoundingClientRect();

        const distance =
            Math.abs(
                rect.top
            );

        if(
            distance <
            smallestDistance
        ){

            smallestDistance =
                distance;

            closestSection =
                id;
        }

    });

    document
    .querySelectorAll(
        '.mobile-bottom-nav a'
    )
    .forEach(link => {

        link.classList.remove(
            'active'
        );

    });

    if(closestSection){

        document
        .getElementById(
            navMap[
                closestSection
            ]
        )
        ?.classList.add(
            'active'
        );

    }

}

window.addEventListener(
    'scroll',
    updateActiveNav
);

window.addEventListener(
    'load',
    updateActiveNav
);

</script>
<script>

function loadShoutbox()
{
    fetch('ajax/get_wall.php')
    .then(response => response.text())
    .then(html => {

        document.getElementById(
            'shoutboxMessages'
        ).innerHTML = html;

        let box =
            document.getElementById(
                'shoutboxMessages'
            );

        box.scrollTop =
            box.scrollHeight;

    });
}

loadShoutbox();

setInterval(loadShoutbox, 180000);

document
.getElementById('shoutboxForm')
.addEventListener(
    'submit',
    function(e)
{
    e.preventDefault();

    let message =
        document.getElementById(
            'shoutboxInput'
        ).value;

    fetch(
        'ajax/send_wall.php',
        {
            method:'POST',

            headers:{
                'Content-Type':
                'application/x-www-form-urlencoded'
            },

            body:
                'message=' +
                encodeURIComponent(message)
        }
    )
    .then(response => {

        if (!response.ok) {

            return response.text()
            .then(text => {

                document.getElementById(
                    'cooldownMessage'
                ).innerText = text;

                throw new Error(text);

            });
        }

        document.getElementById(
            'cooldownMessage'
        ).innerText = '';

        document.getElementById(
            'shoutboxInput'
        ).value = '';

        loadShoutbox();

    })
    .catch(error => {

        console.log(error);

    });

});

</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if($showWelcome): ?>

<div
class="modal fade"
id="welcomeModal"
tabindex="-1">

<div class="modal-dialog modal-dialog-centered">

<div class="modal-content">

<div class="modal-header">

<h5 class="modal-title">

🎉 Welcome to CivilReady

</h5>

</div>

<div class="modal-body">

<h4>

Welcome,

<strong>

<?= htmlspecialchars(
    $userInfo['username']
) ?>

</strong>

!

</h4>

<p>

Welcome to CivilReady, your Civil Service Examination preparation platform.

Complete levels, earn points, rise through the ranks, and compete with other aspirants.

</p>

<hr>

<h5>📚 Categories & Levels</h5>

<p>

CivilReady currently contains:

</p>

<ul>

<li>📘 General - 50 Levels</li>

<li>📝 Verbal - 50 Levels</li>

<li>🧩 Analytical - 50 Levels</li>

<li>🔢 Numerical - 50 Levels</li>

</ul>

<p>

A total of <strong>200 Levels</strong> are available across all categories.

</p>

<hr>

<h5>🏆 Rank Progression</h5>

<div class="table-responsive">

<table class="table table-sm align-middle">

<thead>

<tr>

<th>Rank</th>

<th>Points</th>

</tr>

</thead>

<tbody>

<tr>
<td><span style="color:#6082B6;font-weight:bold;">Citizen</span></td>
<td>0</td>
</tr>

<tr>
<td><span style="color:#702963;font-weight:bold;">Volunteer</span></td>
<td>100</td>
</tr>

<tr>
<td><span style="color:#008080;font-weight:bold;">Public Servant</span></td>
<td>250</td>
</tr>

<tr>
<td><span style="color:#06B6D4;font-weight:bold;">Community Officer</span></td>
<td>500</td>
</tr>

<tr>
<td><span style="color:#3B82F6;font-weight:bold;">Municipal Officer</span></td>
<td>900</td>
</tr>

<tr>
<td><span style="color:#6366F1;font-weight:bold;">Provincial Officer</span></td>
<td>1,400</td>
</tr>

<tr>
<td><span style="color:#8B5CF6;font-weight:bold;">Regional Officer</span></td>
<td>2,000</td>
</tr>

<tr>
<td><span style="color:#A855F7;font-weight:bold;">Bureau Chief</span></td>
<td>3,000</td>
</tr>

<tr>
<td><span style="color:#D946EF;font-weight:bold;">Director</span></td>
<td>4,500</td>
</tr>

<tr>
<td><span style="color:#EC4899;font-weight:bold;">Commissioner</span></td>
<td>6,500</td>
</tr>

<tr>
<td><span style="color:#F97316;font-weight:bold;">Executive Commissioner</span></td>
<td>9,000</td>
</tr>

<tr>
<td><span style="color:#e49b0f;font-weight:bold;">Civil Service Legend</span></td>
<td>12,000</td>
</tr>

</tbody>

</table>

</div>

<hr>

<h5>🎯 Your Goal</h5>

<p>

Answer questions correctly, earn points, unlock higher ranks, and establish your place among the top CivilReady learners.

</p>

<div class="alert alert-success mb-0">

Good luck on your journey to becoming a

<strong>

Civil Service Legend

</strong>

!

</div>

</div>

<div class="modal-footer">

<button
type="button"
class="btn btn-primary"
data-bs-dismiss="modal">

Start

</button>

</div>

</div>

</div>

</div>

<script>

document.addEventListener(
    'DOMContentLoaded',
    function(){

        new bootstrap.Modal(
            document.getElementById(
                'welcomeModal'
            )
        ).show();

    }
);

</script>

<?php endif; ?>
<?php require_once 'includes/footer.php'; ?>