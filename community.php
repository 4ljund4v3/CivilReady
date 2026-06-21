<?php

session_start();

require_once 'config/database.php';
require_once 'includes/update_activity.php';

$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT *
    FROM users
";

$params = [];

if($search){

    $sql .= "
        WHERE
        username LIKE ?
        OR fullname LIKE ?
    ";

    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$sql .= "
    ORDER BY username
";

$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>

<title>Community</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body>

<div class="container py-5">

<h1 class="mb-4">

Community

</h1>

<form
method="GET"
class="mb-4">

<div class="input-group">

<input
type="text"
name="search"
class="form-control"
placeholder="Search users..."
value="<?= htmlspecialchars($search) ?>">

<button
class="btn btn-primary">

Search

</button>

</div>

</form>

<div class="row">

<?php foreach($users as $user): ?>

<div class="col-md-4 mb-3">

<div class="card shadow">

<div class="card-body">

<h5>

<a
href="profile.php?id=<?= $user['id'] ?>"
class="text-decoration-none">

<?= htmlspecialchars($user['username']) ?>

</a>

</h5>

<p class="text-muted">

<?= htmlspecialchars(
    $user['fullname']
) ?>

</p>

</div>

</div>

</div>

<?php endforeach; ?>

</div>

</div>

</body>
</html>