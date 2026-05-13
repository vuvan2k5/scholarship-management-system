<?php
include '../../config/db.php';
include '../../includes/auth.php';

$id = $_GET['id'];

$query = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    die("User not found");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $update = "UPDATE users
               SET full_name = ?, email = ?, role = ?
               WHERE id = ?";

    $stmt = mysqli_prepare($conn, $update);
    mysqli_stmt_bind_param($stmt, "sssi", $full_name, $email, $role, $id);

    if (mysqli_stmt_execute($stmt)) {
        header("Location: index.php");
        exit;
    } else {
        $error = "Update failed";
    }
}
?>

<?php include '../../includes/header.php'; ?>

<div class="container mt-4">
    <h2>Edit User</h2>

    <?php if(isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
         <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label>Full Name</label>
            <input type="text" name="full_name"
                   value="<?php echo $user['full_name']; ?>"
                   class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Email</label>
            <input type="email" name="email"
                   value="<?php echo $user['email']; ?>"
                   class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Role</label>
            <select name="role" class="form-control">
                <option value="admin" <?php if($user['role']=='admin') echo 'selected'; ?>>Admin</option>
                <option value="student" <?php if($user['role']=='student') echo 'selected'; ?>>Student</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Update</button>
        <a href="index.php" class="btn btn-secondary">Back</a>
    </form>
</div>

<?php include '../../includes/footer.php'; ?>